#!/usr/bin/env python3
"""
render_report.py — turn an audit's findings into the client-facing HTML report.

Why a script rather than writing the HTML by hand: the numbers in this report
are the whole product. Hand-drawn SVG drifts — an arc that is 3% short, a line
chart whose y-axis starts at something other than zero, a bar that encodes the
wrong field — and every one of those misstates a measurement to a client who
can check it. Here the geometry is computed once, from the data, and the only
thing the audit supplies is facts.

    python render_report.py audit.json -o report.html

The input contract is documented in references/report-template.md and shown
worked-through in assets/example-audit.json. Every section is optional except
`client`: an audit that could not measure page speed simply omits `speed`, and
the section does not appear. Nothing here invents a value to fill a gap.

Output is one self-contained .html file — no external CSS, fonts, scripts or
images — so it opens anywhere, survives being emailed, and prints to PDF with
Ctrl+P.

Colours are the validated palette from the dataviz skill (light and dark steps
each checked against their own surface for lightness band, chroma, colour-vision
separation and contrast). Do not substitute hexes here without re-running that
validator: the client series and the aqua competitor slot both sit below 3:1 on
the light surface, which is permitted only because every series is direct-
labelled and a table view of the chart ships with it.
"""

import argparse
import html
import json
import math
import sys
from pathlib import Path

# ── Palette ───────────────────────────────────────────────────────────────
# Validated as a set; see the module docstring before changing any value.
SERIES_LIGHT = ["#FF6699", "#2a78d6", "#eb6834", "#1baf7a"]
SERIES_DARK  = ["#d55181", "#3987e5", "#d95926", "#199e70"]

STATUS = {
    "good":     "#0ca30c",
    "warning":  "#fab219",
    "serious":  "#ec835a",
    "critical": "#d03b3b",
}
# Severity words the audit uses, mapped onto those four status roles.
SEVERITY_STATUS = {
    "healthy":  "good",
    "low":      "good",
    "medium":   "warning",
    "high":     "serious",
    "critical": "critical",
}

MAX_SERIES = 4          # one client + three competitors; see the series cap
LABEL_MAX  = 28         # characters in a direct label before it is shortened
GAUGE_TRACK_LIGHT = "#e8e6e0"
GAUGE_TRACK_DARK  = "#2f2f2c"


def esc(value):
    """Everything that reaches the page goes through here."""
    return html.escape(str(value if value is not None else ""), quote=True)


def status_for_score(score):
    """A 0-100 score to a status role. One rule, used by every gauge."""
    try:
        score = float(score)
    except (TypeError, ValueError):
        return "warning"
    if score >= 75:
        return "good"
    if score >= 50:
        return "warning"
    if score >= 30:
        return "serious"
    return "critical"


def status_for(item):
    """A scorecard row's status: what it declared, else derived from its score."""
    declared = str(item.get("status", "")).strip().lower()
    if declared in STATUS:
        return declared
    if declared in SEVERITY_STATUS:
        return SEVERITY_STATUS[declared]
    return status_for_score(item.get("score"))


def num(value):
    """A number, or None — never a guess."""
    if value is None or value is True or value is False:
        return None
    try:
        out = float(value)
    except (TypeError, ValueError):
        return None
    return None if math.isnan(out) or math.isinf(out) else out


def compact(value):
    """1284 → 1,284 · 12900 → 12.9K. Readable at a glance, exact under 10k."""
    n = num(value)
    if n is None:
        return "—"
    if abs(n) >= 1_000_000:
        return f"{n / 1_000_000:.1f}M".replace(".0M", "M")
    if abs(n) >= 10_000:
        return f"{n / 1_000:.1f}K".replace(".0K", "K")
    if n == int(n):
        return f"{int(n):,}"
    return f"{n:,.1f}"


# ── SVG pieces ────────────────────────────────────────────────────────────

def arc_path(cx, cy, r, start_deg, end_deg):
    """An SVG arc from start to end, measured clockwise from the 12 o'clock."""
    def point(deg):
        rad = math.radians(deg - 90)
        return cx + r * math.cos(rad), cy + r * math.sin(rad)

    x1, y1 = point(start_deg)
    x2, y2 = point(end_deg)
    large = 1 if (end_deg - start_deg) % 360 > 180 else 0
    return f"M {x1:.2f} {y1:.2f} A {r} {r} 0 {large} 1 {x2:.2f} {y2:.2f}"


def gauge(score, size=168, label=None, out_of=100, caption=None):
    """
    The score as an arc, with the number itself as the hero.

    A 270° arc rather than a full ring, so the gap reads as the bottom of a
    dial and the eye can find where the fill stops. The track is a quiet step
    of the same ramp, so "how much is missing" is visible as well as "how much
    is there".
    """
    value = num(score)
    if value is None:
        return ""
    value = max(0.0, min(float(out_of), value))
    frac = value / float(out_of) if out_of else 0.0

    sweep = 270.0
    start = 225.0                      # bottom-left, going clockwise
    r = size / 2 - 16
    cx = cy = size / 2
    stroke = 13

    track = arc_path(cx, cy, r, start, start + sweep)
    # An arc of exactly 0 draws nothing, which is the honest rendering of a
    # zero score; nudging it to a visible sliver would overstate it.
    fill = arc_path(cx, cy, r, start, start + sweep * frac) if frac > 0 else ""
    role = status_for_score(value if out_of == 100 else frac * 100)

    shown = f"{value:g}"
    parts = [
        f'<svg class="gauge" viewBox="0 0 {size} {size}" width="{size}" height="{size}" '
        f'role="img" aria-label="{esc(label or "Score")}: {esc(shown)} out of {esc(out_of)}">',
        f'<path d="{track}" fill="none" stroke="var(--gauge-track)" '
        f'stroke-width="{stroke}" stroke-linecap="round"/>',
    ]
    if fill:
        parts.append(
            f'<path d="{fill}" fill="none" stroke="{STATUS[role]}" '
            f'stroke-width="{stroke}" stroke-linecap="round"/>'
        )
    parts.append(
        f'<text x="{cx}" y="{cy + 4}" class="gauge-value" text-anchor="middle">{esc(shown)}</text>'
    )
    parts.append(
        f'<text x="{cx}" y="{cy + 26}" class="gauge-of" text-anchor="middle">out of {esc(out_of)}</text>'
    )
    parts.append("</svg>")

    block = ['<div class="gauge-wrap">', "".join(parts)]
    if label:
        block.append(f'<div class="gauge-label">{esc(label)}</div>')
    if caption:
        block.append(f'<div class="gauge-caption">{esc(caption)}</div>')
    block.append("</div>")
    return "".join(block)


def meter_row(name, ratio, figure, role, note=None):
    """
    One named row, its bar, and its figure — the form for several ratios that
    each have their own limit. A dial per row would be four dials to read
    where one aligned column of bars reads in a glance.
    """
    pct = 0.0 if ratio is None else max(0.0, min(1.0, float(ratio))) * 100
    return (
        '<div class="meter">'
        f'<div class="meter-name">{esc(name)}'
        + (f'<span class="meter-note">{esc(note)}</span>' if note else "")
        + "</div>"
        f'<div class="meter-track"><div class="meter-fill" style="width:{pct:.1f}%;'
        f'background:{STATUS[role]}"></div></div>'
        f'<div class="meter-figure">{esc(figure)}</div>'
        "</div>"
    )


def rank_role(rank):
    """
    A grid point's rank to a status role, using BrightLocal's own bucket
    boundaries so the cells and the counts beneath them can never disagree.

    `rank` 0 (or None) is not rank zero — it means the business did not appear
    at that point at all. It gets its own role so the *shape* of the grid stays
    readable, but it is counted with 11+ below, which is how BrightLocal counts
    it and so how the client will see it in any report they check this against.
    """
    r = num(rank)
    if r is None or r <= 0:
        return "absent"
    if r <= 3:
        return "good"
    if r <= 10:
        return "warning"
    return "serious"


RANK_LEGEND = [
    ("good",     "1–3",    "in the local pack"),
    ("warning",  "4–10",   "on page one, below the pack"),
    ("serious",  "11+",    "found, but nobody scrolls that far"),
    ("absent",   "—",      "not showing at all"),
]


def grid_matrix(points):
    """
    BrightLocal hands back a flat list of points with coordinates, not rows.
    Lay them out as they sit on the map — north at the top, west on the left —
    because the finding on a rank grid is which direction the visibility falls
    off in, and a grid laid out in list order cannot show that.

    Returns (rows, oriented). `oriented` is False when the points do not form a
    clean lattice, in which case they are chunked in the order supplied and the
    caption says so rather than implying a compass direction that isn't there.
    """
    pts = [p for p in (points or []) if isinstance(p, dict)]
    if not pts:
        return [], False

    def coord(p, *keys):
        for k in keys:
            if k in p:
                v = num(p[k])
                if v is not None:
                    return round(v, 5)
        return None

    lats = [coord(p, "latitude", "lat") for p in pts]
    lons = [coord(p, "longitude", "lng", "lon") for p in pts]

    if None not in lats and None not in lons:
        uniq_lat = sorted(set(lats), reverse=True)   # north first
        uniq_lon = sorted(set(lons))                 # west first
        if len(uniq_lat) * len(uniq_lon) == len(pts) and len(uniq_lat) > 1:
            cell = {}
            for p, la, lo in zip(pts, lats, lons):
                cell[(la, lo)] = p
            if len(cell) == len(pts):
                return [[cell[(la, lo)] for lo in uniq_lon] for la in uniq_lat], True

    side = max(1, int(round(math.sqrt(len(pts)))))
    return [pts[i:i + side] for i in range(0, len(pts), side)], False


def keyword_matrix(obj):
    """A grid holder (a keyword, or one rival) to (matrix, oriented)."""
    if not isinstance(obj, dict):
        return [], False
    rows = obj.get("rows")
    if rows:
        return ([[c if isinstance(c, dict) else {"rank": c} for c in r] for r in rows],
                bool(obj.get("oriented", True)))
    return grid_matrix(obj.get("grid_points") or obj.get("points"))


def point_key(p):
    """What makes two grid points the same place. Coordinates, not list order."""
    la, lo = num(p.get("latitude", p.get("lat"))), num(p.get("longitude", p.get("lng")))
    return None if la is None or lo is None else (round(la, 5), round(lo, 5))


def align_grids(client_pts, rival_pts):
    """
    Pair the client's points with a rival's, by coordinate.

    Returns None when they cannot be paired — a different grid, a different
    run, or points with no coordinates. Every comparison below is a claim about
    *the same place*, so a comparison that could not be aligned is not printed
    at all rather than printed against whatever happened to be at the same
    index in both lists.
    """
    ckeys = [point_key(p) for p in client_pts]
    rkeys = [point_key(p) for p in rival_pts]
    if None in ckeys or None in rkeys:
        return None
    rmap = dict(zip(rkeys, rival_pts))
    if len(rmap) != len(rkeys) or len(set(ckeys)) != len(ckeys):
        return None
    if not set(ckeys) <= set(rmap):
        return None
    return [(num(c.get("rank")) or 0, num(rmap[k].get("rank")) or 0)
            for c, k in zip(client_pts, ckeys)]


def versus_line(rival_name, pairs):
    """
    The comparison, stated as something the prospect can go and check.

    Only counts are reported — no modelled difference, no 'you are losing N
    customers'. Who is in the pack where, is a fact; what it is worth is not.
    """
    n = len(pairs)
    you_pack = sum(1 for c, _ in pairs if 1 <= c <= 3)
    they_pack = sum(1 for _, r in pairs if 1 <= r <= 3)
    they_pack_you_absent = sum(1 for c, r in pairs if 1 <= r <= 3 and c <= 0)
    out = (f"{esc(rival_name)} is in the top three at <strong>{they_pack}</strong> of "
           f"the {n} points, against your <strong>{you_pack}</strong>.")
    if they_pack_you_absent:
        out += (f" At {they_pack_you_absent} of those points you do not come up "
                "at all.")
    return f'<p class="vs-finding">{out}</p>'


def grid_caption(oriented):
    """What the layout is, in the one case and in the other."""
    return ("North at the top, west on the left — the grid as it sits on the map."
            if oriented else
            "Points in the order the rank tracker returned them; this layout is not "
            "a map, so read the counts rather than the direction.")


def rank_grid(rows, oriented, box=250, caption=True):
    """One keyword's grid of points, as the map lays it out."""
    if not rows:
        return ""
    cols = max(len(r) for r in rows)
    n = max(cols, len(rows))
    gap = 3 if n <= 9 else 2
    cell = max(10, min(36, (box - gap * (n - 1)) // n))
    show_numbers = cell >= 22
    w = cols * cell + (cols - 1) * gap
    h = len(rows) * cell + (len(rows) - 1) * gap

    svg = [f'<svg class="rgrid" viewBox="0 0 {w} {h}" width="{w}" height="{h}" '
           f'role="img" aria-label="Rank at each of {sum(len(r) for r in rows)} '
           f'points across the service area">']
    for ri, row in enumerate(rows):
        for ci, p in enumerate(row):
            rank = p.get("rank") if isinstance(p, dict) else p
            role = rank_role(rank)
            x, y = ci * (cell + gap), ri * (cell + gap)
            fill = "var(--gauge-track)" if role == "absent" else STATUS[role]
            r_num = num(rank)
            shown = "—" if role == "absent" else f"{int(r_num)}"
            title = ("Not in the top 20 here" if role == "absent"
                     else f"Position {int(r_num)} here")
            svg.append(f'<g><title>{esc(title)}</title>'
                       f'<rect x="{x}" y="{y}" width="{cell}" height="{cell}" rx="4" '
                       f'fill="{fill}"/>')
            if show_numbers:
                svg.append(f'<text x="{x + cell / 2:.1f}" y="{y + cell / 2 + 4:.1f}" '
                           f'class="rcell {"rcell-absent" if role == "absent" else ""}" '
                           f'text-anchor="middle">{esc(shown)}</text>')
            svg.append("</g>")
    svg.append("</svg>")

    if not caption:
        return "".join(svg)
    return (f'<figure class="rgrid-figure">{"".join(svg)}'
            f'<figcaption class="chart-note">{esc(grid_caption(oriented))}'
            "</figcaption></figure>")


def render_local(local):
    """
    Where the business actually shows up on the map — the one finding a local
    client can check against their own phone in ten seconds, which is exactly
    why nothing here is computed from anything other than the run itself.

    Two rules, both from references/local-and-gmb.md and both verified against
    a real run: the average is quoted as the tool reported it and never
    recomputed (recomputing it over raw values counts the invisible points as
    rank 0 and flatters the grid by about 20%), and the 11+ bucket includes
    every point where the business did not appear, so it is labelled that way.
    """
    if not local:
        return ""
    keywords = [k for k in (local.get("keywords") or []) if isinstance(k, dict)]
    if not keywords:
        return ""

    out = ['<section class="block"><h2>'
           + esc(local.get("heading") or "Where you show up on the map") + "</h2>"]

    bits = [b for b in (local.get("source") or "BrightLocal Local Search Grid",
                        local.get("area"), local.get("fetched")) if b]
    lede = esc(local["lede"]) if local.get("lede") else (
        "Every square is one place somebody could be standing when they search. "
        "The number in it is where " + esc(local.get("business") or "the business")
        + " came up for them.")
    out.append(f'<p class="lede">{lede}'
               + (f' Measured with {esc(" · ".join(bits))}.' if bits else "")
               + "</p>")

    out.append('<div class="legend rgrid-legend">')
    for role, label, meaning in RANK_LEGEND:
        sw = ("background:var(--gauge-track);border:1px solid var(--border)"
              if role == "absent" else f"background:{STATUS[role]}")
        out.append(f'<span class="legend-item"><span class="swatch" style="{sw}"></span>'
                   f'<strong>{esc(label)}</strong>&nbsp;{esc(meaning)}</span>')
    out.append("</div>")

    shown, overflow = keywords[:6], keywords[6:]
    out.append('<div class="grids">')
    for kw in shown:
        matrix, oriented = keyword_matrix(kw)
        if not matrix:
            continue

        flat = [c for r in matrix for c in r]
        total = len(flat)
        summary = kw.get("summary") or {}
        high = summary.get("num_high_ranking_points")
        med = summary.get("num_med_ranking_points")
        low = summary.get("num_low_ranking_points")
        if high is None or med is None or low is None:
            # Count them ourselves, by BrightLocal's rule: 11+ and unranked
            # land in the same bucket, so a client comparing this against their
            # own BrightLocal view sees the same three numbers.
            roles = [rank_role(c.get("rank")) for c in flat]
            high = roles.count("good")
            med = roles.count("warning")
            low = roles.count("serious") + roles.count("absent")

        # At most two rivals: three grids is the most a reader can hold side by
        # side, and a fourth column shrinks every cell below readable.
        rivals = [r for r in (kw.get("rivals") or []) if isinstance(r, dict)][:2]
        rival_grids = []
        for rv in rivals:
            rmat, _ = keyword_matrix(rv)
            if rmat:
                rival_grids.append((rv, rmat))

        out.append(f'<div class="grid-card{" compare" if rival_grids else ""}">')
        out.append(f'<h3 class="grid-kw">{esc(kw.get("keyword", ""))}</h3>')

        if rival_grids:
            box = 220 if len(rival_grids) == 1 else 178
            out.append('<div class="versus">')
            out.append(
                '<figure class="vs-col"><figcaption class="vs-name">'
                + esc(local.get("business") or "This business")
                + '<span class="you-tag">you</span></figcaption>'
                + rank_grid(matrix, oriented, box=box, caption=False) + "</figure>")
            for rv, rmat in rival_grids:
                out.append('<figure class="vs-col"><figcaption class="vs-name">'
                           + esc(rv.get("name", "Competitor"))
                           + "</figcaption>"
                           + rank_grid(rmat, oriented, box=box, caption=False)
                           + "</figure>")
            out.append("</div>")
            out.append(f'<p class="chart-note">{esc(grid_caption(oriented))} '
                       'Every map here is the same points, measured in the same run.</p>')
        else:
            out.append(rank_grid(matrix, oriented))

        out.append('<div class="meters grid-meters">')
        for role, label, count in (
            ("good",    "Positions 1–3", high),
            ("warning", "Positions 4–10", med),
            ("serious", "Position 11+ or not showing", low),
        ):
            c = num(count)
            out.append(meter_row(label, None if c is None or not total else c / total,
                                 "—" if c is None else f"{int(c)} of {total}", role))
        out.append("</div>")

        avg = num(summary.get("avg_rank") if summary else kw.get("avg_rank"))
        if avg is not None:
            out.append(
                f'<p class="grid-avg"><strong>{avg:g}</strong> average position across all '
                f'{total} points, counting the ones where you don\'t appear at all. '
                'The shape above matters more than this number.</p>')
        # The comparison, one line per rival, only where the two grids could be
        # matched point for point.
        if rival_grids:
            client_pts = [c for r in matrix for c in r]
            unaligned = []
            for rv, rmat in rival_grids:
                pairs = align_grids(client_pts, [c for r in rmat for c in r])
                name = rv.get("name", "The competitor")
                if pairs:
                    out.append(versus_line(name, pairs))
                else:
                    unaligned.append(str(name))
            if unaligned:
                out.append(
                    '<p class="chart-note">Shown side by side only: '
                    + esc(", ".join(unaligned))
                    + (" was" if len(unaligned) == 1 else " were")
                    + " measured on a different set of points, so a point-by-point "
                      "comparison would not be comparing the same places.</p>")

        if kw.get("shape"):
            out.append(f'<p class="verdict">{esc(kw["shape"])}</p>')
        url = kw.get("grid_url") or kw.get("map_url")
        if url and str(url).startswith(("https://", "http://")):
            out.append(f'<p class="map-link"><a href="{esc(url)}" rel="noopener">'
                       'Open the live map for this search</a></p>')
        out.append("</div>")
    out.append("</div>")

    if overflow:
        out.append(f'<p class="chart-note">{len(overflow)} further search'
                   + ("es were" if len(overflow) > 1 else " was")
                   + " measured on the same grid; "
                   + esc(", ".join(str(k.get("keyword", "")) for k in overflow if k.get("keyword")))
                   + ". Full results on request.</p>")

    if local.get("verdict"):
        out.append(f'<p class="verdict">{esc(local["verdict"])}</p>')
    out.append("</section>")
    return "".join(out)


def line_chart(data, width=720, height=300):
    """
    The client against the competitors they are actually losing to.

    Rules this follows and why: one y-axis only, starting at zero, so the gap
    is the real gap and not a cropped one; at most four series, because a
    fifth cannot be told from a fourth by colour; every series direct-labelled
    at its endpoint, which is what lets the two low-contrast slots be used at
    all; and a table of the same numbers underneath, so nothing here is
    readable only by someone who can see colour.
    """
    series = [s for s in (data.get("series") or []) if s.get("values")]
    labels = data.get("labels") or []
    if not series or not labels:
        return ""

    # The client first, then the biggest competitors. A series beyond the cap
    # is dropped rather than given a colour that cannot be distinguished.
    series.sort(key=lambda s: (0 if s.get("you") else 1, -max(num(v) or 0 for v in s["values"])))
    dropped = max(0, len(series) - MAX_SERIES)
    series = series[:MAX_SERIES]

    # The right margin has to hold the longest site name, or the direct labels
    # — the thing that makes the two low-contrast slots usable at all — get
    # clipped by the viewBox. Roughly 6.7px per character at 12px. A very long
    # domain is shortened for the label only; the legend and the table under
    # the chart both carry it in full, so nothing is lost.
    for s in series:
        name = str(s.get("name", ""))
        s["_label"] = name if len(name) <= LABEL_MAX else name[: LABEL_MAX - 1] + "…"
    longest = max((len(s["_label"]) for s in series), default=0)
    pad_l, pad_t, pad_b = 54, 18, 34
    pad_r = max(132, int(longest * 6.7) + 26)
    # Widen the canvas rather than squeeze the plot: the SVG scales to its
    # container, so a wider viewBox costs a little size, not a clipped label.
    width = max(width, pad_l + pad_r + 320)
    plot_w = width - pad_l - pad_r
    plot_h = height - pad_t - pad_b

    peak = 0.0
    for s in series:
        for v in s["values"]:
            n = num(v)
            if n is not None:
                peak = max(peak, n)
    if peak <= 0:
        return ""

    # A round ceiling, so the gridline figures are numbers a reader recognises.
    step = 10 ** max(0, len(str(int(peak))) - 2)
    top = math.ceil(peak / step) * step if step else peak
    ticks = [top * i / 4 for i in range(5)]

    n_points = max(len(labels), max(len(s["values"]) for s in series))

    def x_at(i):
        return pad_l + (plot_w * i / (n_points - 1) if n_points > 1 else plot_w / 2)

    def y_at(v):
        return pad_t + plot_h - (plot_h * (v / top))

    out = [
        f'<svg class="chart" viewBox="0 0 {width} {height}" width="{width}" height="{height}" '
        f'role="img" aria-label="{esc(data.get("metric") or "Comparison")} over time, '
        f'{esc(len(series))} sites. The same figures are in the table below.">'
    ]

    # Hairline grid, one shade off the surface, solid — a dashed grid reads as
    # a threshold that is not there.
    for t in ticks:
        y = y_at(t)
        out.append(
            f'<line x1="{pad_l}" y1="{y:.1f}" x2="{pad_l + plot_w}" y2="{y:.1f}" class="grid"/>'
        )
        out.append(f'<text x="{pad_l - 10}" y="{y + 4:.1f}" class="tick" text-anchor="end">{esc(compact(t))}</text>')

    every = max(1, n_points // 7)
    for i, lab in enumerate(labels[:n_points]):
        if i % every and i != n_points - 1:
            continue
        out.append(
            f'<text x="{x_at(i):.1f}" y="{height - 10}" class="tick" text-anchor="middle">{esc(lab)}</text>'
        )

    endpoints = []
    for idx, s in enumerate(series):
        colour = f"var(--series-{idx + 1})"
        pts, last = [], None
        for i, v in enumerate(s["values"][:n_points]):
            n = num(v)
            if n is None:
                continue                       # a gap stays a gap
            pts.append(f"{x_at(i):.1f},{y_at(n):.1f}")
            last = (x_at(i), y_at(n), n)
        if not pts:
            continue
        emphasis = " emph" if s.get("you") else ""
        out.append(
            f'<polyline class="line{emphasis}" points="{" ".join(pts)}" fill="none" stroke="{colour}"/>'
        )
        if last:
            out.append(f'<circle cx="{last[0]:.1f}" cy="{last[1]:.1f}" r="5" fill="{colour}" class="dot"/>')
            endpoints.append({
                "x": last[0], "y": last[1], "value": last[2],
                "name": s.get("_label") or s.get("name", ""),
                "full": s.get("name", ""),
                "you": bool(s.get("you")),
            })

    # Two sites whose traffic is close put their labels on top of each other,
    # which turns the one thing holding the chart together — knowing which line
    # is whose — into a smudge. Push them apart, keeping their order.
    endpoints.sort(key=lambda e: e["y"])
    gap = 34
    for i in range(1, len(endpoints)):
        if endpoints[i]["y"] - endpoints[i - 1]["y"] < gap:
            endpoints[i]["y"] = endpoints[i - 1]["y"] + gap
    overshoot = (endpoints[-1]["y"] if endpoints else 0) - (pad_t + plot_h)
    if overshoot > 0:
        for e in endpoints:
            e["y"] -= overshoot

    for e in endpoints:
        weight = "700" if e["you"] else "600"
        # A leader line where the label had to move, so it still reads as
        # belonging to its own line rather than to whichever is now nearest.
        if abs(e["y"] - y_at(e["value"])) > 2:
            out.append(
                f'<line x1="{e["x"] + 6:.1f}" y1="{y_at(e["value"]):.1f}" '
                f'x2="{e["x"] + 10:.1f}" y2="{e["y"]:.1f}" class="leader"/>'
            )
        out.append(
            f'<text x="{e["x"] + 13:.1f}" y="{e["y"] + 4:.1f}" class="endlabel" '
            f'style="font-weight:{weight}"><title>{esc(e["full"])}</title>{esc(e["name"])}</text>'
        )
        out.append(
            f'<text x="{e["x"] + 13:.1f}" y="{e["y"] + 19:.1f}" class="endvalue">{esc(compact(e["value"]))}</text>'
        )

    out.append("</svg>")

    legend = ['<div class="legend">']
    for idx, s in enumerate(series):
        mark = "You" if s.get("you") else ""
        legend.append(
            f'<span class="legend-item"><span class="swatch" style="background:var(--series-{idx + 1})"></span>'
            f'{esc(s.get("name", ""))}' + (f' <span class="you-tag">{mark}</span>' if mark else "") + "</span>"
        )
    legend.append("</div>")

    # The table is not an extra: it is what makes the chart readable without
    # colour, and it is where a client checks a figure they want to argue with.
    table = ['<details class="table-view"><summary>See these figures as a table</summary>',
             '<table><thead><tr><th scope="col">Site</th>']
    for lab in labels[:n_points]:
        table.append(f'<th scope="col">{esc(lab)}</th>')
    table.append("</tr></thead><tbody>")
    for s in series:
        table.append(f'<tr><th scope="row">{esc(s.get("name", ""))}</th>')
        for i in range(n_points):
            v = s["values"][i] if i < len(s["values"]) else None
            table.append(f"<td>{esc(compact(v)) if num(v) is not None else '—'}</td>")
        table.append("</tr>")
    table.append("</tbody></table></details>")

    note = ""
    if dropped:
        note = (f'<p class="chart-note">Showing the {MAX_SERIES} sites with the most traffic; '
                f'{dropped} further competitor{"s" if dropped > 1 else ""} measured but not plotted.</p>')

    source = data.get("source")
    src = f'<p class="chart-note">Source: {esc(source)}</p>' if source else ""

    return (
        '<figure class="chart-figure">'
        + "".join(out)
        + "".join(legend)
        + note
        + src
        + "".join(table)
        + "</figure>"
    )


# ── Sections ──────────────────────────────────────────────────────────────

def render_headlines(items):
    if not items:
        return ""
    tiles = []
    for it in items:
        direction = str(it.get("direction", "")).lower()
        arrow = "↓" if direction == "down" else ("↑" if direction == "up" else "")
        good = it.get("good")
        tone = "" if good is None else (" good" if good else " bad")
        tiles.append(
            f'<div class="tile{tone}">'
            f'<div class="tile-value">{esc(arrow)}{esc(it.get("value", "—"))}</div>'
            f'<div class="tile-label">{esc(it.get("label", ""))}</div>'
            + (f'<div class="tile-note">{esc(it.get("note"))}</div>' if it.get("note") else "")
            + "</div>"
        )
    return f'<div class="tiles">{"".join(tiles)}</div>'


def render_scorecard(rows):
    if not rows:
        return ""
    out = ['<section class="block"><h2>Scorecard</h2>',
           '<p class="lede">Each category scored out of 100 on how healthy it is, and what it '
           'contributes to the overall score.</p>', '<div class="meters">']
    for r in rows:
        score = num(r.get("score"))
        role = status_for(r)
        figure = f'{score:g}' if score is not None else "not checked"
        weight = r.get("weight")
        note = f'{weight}% of the score' if weight is not None else None
        out.append(meter_row(r.get("category", ""), None if score is None else score / 100,
                             figure, role, note))
        if r.get("verdict"):
            out.append(f'<p class="verdict">{esc(r["verdict"])}</p>')
    out.append("</div></section>")
    return "".join(out)


def render_speed(speed):
    """
    What the site's own pages do when somebody opens one.

    Deliberately silent where it has nothing: a report that prints a bounce
    rate for a prospect whose analytics we have never seen has invented it,
    and this is a document whose only value is being checkable.
    """
    if not speed:
        return ""
    out = ['<section class="block"><h2>Website speed</h2>']

    src = speed.get("source")
    url = speed.get("url")
    strat = speed.get("strategy")
    bits = [b for b in (src, f"{strat} test" if strat else None, speed.get("fetched")) if b]
    if bits:
        out.append(f'<p class="lede">Measured on {esc(url or "the site")} — {esc(" · ".join(bits))}. '
                   'Anyone can reproduce this against the same tool.</p>')

    score = num(speed.get("score"))
    body = ['<div class="speed-grid">']
    if score is not None:
        body.append(gauge(score, size=150, label="Speed score",
                          caption=speed.get("score_note")))
    metrics = speed.get("metrics") or []
    if metrics:
        body.append('<div class="meters speed-meters">')
        for m in metrics:
            ratio = num(m.get("ratio"))
            role = status_for(m) if m.get("status") else status_for_score(
                None if ratio is None else ratio * 100)
            body.append(meter_row(m.get("label", ""), ratio, m.get("value", "—"), role,
                                  m.get("note")))
        body.append("</div>")
    body.append("</div>")
    out.append("".join(body))

    bounce = speed.get("bounce_rate")
    if bounce and bounce.get("value"):
        out.append(
            f'<div class="tile wide"><div class="tile-value">{esc(bounce["value"])}</div>'
            f'<div class="tile-label">Visitors who leave without going further</div>'
            + (f'<div class="tile-note">{esc(bounce.get("source"))}</div>' if bounce.get("source") else "")
            + "</div>"
        )
    elif speed.get("bounce_rate_unavailable"):
        out.append(f'<p class="gap-note">{esc(speed["bounce_rate_unavailable"])}</p>')

    if speed.get("verdict"):
        out.append(f'<p class="verdict">{esc(speed["verdict"])}</p>')
    out.append("</section>")
    return "".join(out)


def render_sections(sections):
    if not sections:
        return ""
    out = []
    for s in sections:
        sev = str(s.get("severity", "")).strip()
        role = SEVERITY_STATUS.get(sev.lower(), "warning")
        out.append('<section class="block finding">')
        out.append(
            f'<h2>{esc(s.get("title", ""))}'
            + (f'<span class="sev" style="background:{STATUS[role]};'
               f'color:{"#0b0b0b" if role == "warning" else "#fff"}">{esc(sev)}</span>' if sev else "")
            + "</h2>"
        )
        for key in ("finding", "why", "teaser"):
            if s.get(key):
                cls = ' class="teaser"' if key == "teaser" else ""
                out.append(f"<p{cls}>{esc(s[key])}</p>")
        out.append("</section>")
    return "".join(out)


STYLE = """
:root{
  color-scheme: light;
  --page:#f9f9f7; --surface:#fcfcfb; --ink:#0b0b0b; --ink-2:#52514e; --muted:#898781;
  --grid:#e1e0d9; --axis:#c3c2b7; --border:#e8e6e0; --gauge-track:#e8e6e0;
  --series-1:#FF6699; --series-2:#2a78d6; --series-3:#eb6834; --series-4:#1baf7a;
}
@media (prefers-color-scheme: dark){
  :root:not([data-theme="light"]){
    color-scheme: dark;
    --page:#0d0d0d; --surface:#1a1a19; --ink:#f0efec; --ink-2:#c3c2b7; --muted:#898781;
    --grid:#2c2c2a; --axis:#383835; --border:#2c2c2a; --gauge-track:#2f2f2c;
    --series-1:#d55181; --series-2:#3987e5; --series-3:#d95926; --series-4:#199e70;
  }
}
:root[data-theme="dark"]{
  color-scheme: dark;
  --page:#0d0d0d; --surface:#1a1a19; --ink:#f0efec; --ink-2:#c3c2b7; --muted:#898781;
  --grid:#2c2c2a; --axis:#383835; --border:#2c2c2a; --gauge-track:#2f2f2c;
  --series-1:#d55181; --series-2:#3987e5; --series-3:#d95926; --series-4:#199e70;
}
*{box-sizing:border-box}
body{margin:0;background:var(--page);color:var(--ink);
  font:16px/1.65 -apple-system,BlinkMacSystemFont,"Segoe UI",Helvetica,Arial,sans-serif;
  -webkit-font-smoothing:antialiased}
.sheet{max-width:860px;margin:0 auto;padding:40px 24px 72px}
header.cover{border-bottom:1px solid var(--border);padding-bottom:22px;margin-bottom:30px}
.eyebrow{font-size:12px;font-weight:700;letter-spacing:.9px;text-transform:uppercase;color:var(--muted)}
h1{font-size:clamp(26px,4vw,36px);line-height:1.2;margin:10px 0 6px;letter-spacing:-.02em}
.sub{color:var(--ink-2);font-size:15px;margin:0}
h2{font-size:19px;margin:0 0 12px;letter-spacing:-.01em;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.block{background:var(--surface);border:1px solid var(--border);border-radius:14px;
  padding:22px 24px;margin-bottom:18px}
.lede{color:var(--ink-2);font-size:14px;margin:0 0 16px}
p{margin:0 0 12px}
p:last-child{margin-bottom:0}
.teaser{color:var(--ink-2);font-size:15px;border-left:3px solid var(--series-1);padding-left:12px}
.verdict{color:var(--ink-2);font-size:14px;margin:10px 0 18px}
.sev{font-size:11px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;
  padding:3px 9px;border-radius:20px;white-space:nowrap}
/* ── score ── */
.score-row{display:flex;gap:28px;align-items:center;flex-wrap:wrap}
.score-row .prose{flex:1 1 320px;min-width:280px}
.gauge-wrap{text-align:center;flex:0 0 auto}
.gauge-value{font-size:40px;font-weight:700;fill:var(--ink)}
.gauge-of{font-size:11px;fill:var(--muted);letter-spacing:.4px}
.gauge-label{font-size:13px;font-weight:600;margin-top:2px}
.gauge-caption{font-size:11.5px;color:var(--muted);max-width:190px;margin:2px auto 0;line-height:1.45}
/* ── tiles ── */
.tiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;margin-bottom:18px}
.tile{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px 20px}
.tile.wide{margin-top:14px}
.tile-value{font-size:34px;font-weight:700;line-height:1.05;letter-spacing:-.02em}
.tile.bad .tile-value{color:#d03b3b}
.tile.good .tile-value{color:#0ca30c}
.tile-label{font-size:13px;color:var(--ink-2);margin-top:6px;line-height:1.45}
.tile-note{font-size:11.5px;color:var(--muted);margin-top:5px}
/* ── meters ── */
.meters{display:flex;flex-direction:column;gap:2px}
.meter{display:grid;grid-template-columns:1fr 170px 92px;gap:14px;align-items:center;padding:9px 0}
.meter-name{font-size:14px;font-weight:600}
.meter-note{display:block;font-size:11.5px;font-weight:400;color:var(--muted)}
.meter-track{height:8px;border-radius:5px;background:var(--gauge-track);overflow:hidden}
.meter-fill{height:100%;border-radius:5px}
.meter-figure{font-size:14px;font-weight:700;text-align:right;font-variant-numeric:tabular-nums}
.speed-grid{display:flex;gap:28px;align-items:center;flex-wrap:wrap}
.speed-meters{flex:1 1 360px;min-width:300px}
.gap-note{font-size:13px;color:var(--ink-2);background:var(--page);border:1px solid var(--border);
  border-radius:10px;padding:12px 14px;margin-top:14px}
/* ── rank grid ── */
.rgrid-legend{margin:0 0 18px;gap:10px 18px;font-size:12px}
.rgrid-legend strong{color:var(--ink);font-variant-numeric:tabular-nums}
.grids{display:grid;grid-template-columns:repeat(auto-fit,minmax(272px,1fr));gap:22px}
/* max-width, because the common case is one keyword: without it a single card
   fills the block and leaves a 250px grid stranded beside 500px of nothing. */
.grid-card{min-width:0;max-width:430px}
.grid-kw{font-size:14.5px;margin:0 0 10px;letter-spacing:-.01em}
.grid-kw::before{content:"“"}
.grid-kw::after{content:"”"}
.rgrid-figure{margin:0 0 10px}
.rgrid{max-width:100%;height:auto;display:block}
.rcell{font-size:11px;font-weight:700;fill:#0b0b0b;font-variant-numeric:tabular-nums}
.rcell-absent{fill:var(--ink-2);font-weight:400}
.grid-meters .meter{grid-template-columns:1fr 100px 74px;gap:10px;padding:5px 0}
.grid-meters .meter-name{font-size:12.5px;font-weight:500}
.grid-meters .meter-figure{font-size:12.5px}
/* A keyword with rivals is the same map more than once, so it takes the full
   width of the block rather than sharing a column with another keyword. */
.grid-card.compare{max-width:none;grid-column:1/-1}
/* The card is full width here, but a meter row is not prose: stretched to
   760px the label and its bar end up at opposite ends of the eye's travel. */
.grid-card.compare .grid-meters{max-width:460px}
.versus{display:flex;gap:22px;flex-wrap:wrap;align-items:flex-start;margin-bottom:4px}
.vs-col{margin:0;min-width:0}
.vs-name{font-size:12.5px;font-weight:600;margin-bottom:7px;display:flex;
  align-items:center;gap:7px}
.vs-finding{font-size:13px;color:var(--ink-2);margin:10px 0 0}
.vs-finding strong{color:var(--ink);font-variant-numeric:tabular-nums}
.grid-avg{font-size:12.5px;color:var(--ink-2);margin:10px 0 0}
.grid-avg strong{font-size:15px;color:var(--ink);font-variant-numeric:tabular-nums}
.map-link{font-size:12.5px;margin:8px 0 0}
.map-link a{color:var(--series-2)}
/* ── chart ── */
.chart-figure{margin:0}
.chart{max-width:100%;height:auto;display:block}
.grid{stroke:var(--grid);stroke-width:1}
.tick{font-size:11px;fill:var(--muted)}
.line{stroke-width:2;stroke-linejoin:round;stroke-linecap:round}
.line.emph{stroke-width:3}
.dot{stroke:var(--surface);stroke-width:2}
.endlabel{font-size:12px;fill:var(--ink)}
.endvalue{font-size:11px;fill:var(--muted);font-variant-numeric:tabular-nums}
.leader{stroke:var(--axis);stroke-width:1}
.legend{display:flex;flex-wrap:wrap;gap:14px;margin-top:10px;font-size:12.5px;color:var(--ink-2)}
.legend-item{display:inline-flex;align-items:center;gap:6px}
.swatch{width:11px;height:11px;border-radius:3px;display:inline-block}
.you-tag{font-size:10px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:var(--muted)}
.chart-note{font-size:11.5px;color:var(--muted);margin:8px 0 0}
.table-view{margin-top:14px;font-size:12.5px}
.table-view summary{cursor:pointer;color:var(--ink-2)}
.table-view table{border-collapse:collapse;margin-top:10px;width:100%}
.table-view th,.table-view td{border-bottom:1px solid var(--border);padding:6px 8px;text-align:right;
  font-variant-numeric:tabular-nums}
.table-view th[scope=row]{text-align:left;font-weight:600}
.table-view thead th{color:var(--muted);font-weight:600}
footer{margin-top:26px;padding-top:18px;border-top:1px solid var(--border);
  font-size:12px;color:var(--muted)}
@media print{
  body{background:#fff}
  .sheet{max-width:none;padding:0}
  .block,.tile,.grid-card{break-inside:avoid;border-color:#ddd}
  .table-view[open]{display:block}
  details{break-inside:avoid}
}
@media (max-width:620px){
  .meter{grid-template-columns:1fr 84px;row-gap:6px}
  .meter-track{grid-column:1/-1;order:3}
  /* The grid-meters rule is more specific than the one above, so it has to be
     restated here or the three bucket rows keep their desktop columns and run
     off a phone. */
  .grid-meters .meter{grid-template-columns:1fr 74px;row-gap:6px}
}
"""


def build(data):
    client = data.get("client") or {}
    name = client.get("name") or client.get("domain") or "This site"
    title = f"SEO audit — {name}"

    parts = [
        "<!doctype html><html lang=\"en-CA\"><head><meta charset=\"utf-8\">",
        '<meta name="viewport" content="width=device-width,initial-scale=1">',
        f"<title>{esc(title)}</title>",
        f"<style>{STYLE}</style>",
        "</head><body><div class=\"sheet\">",
        '<header class="cover">',
        f'<div class="eyebrow">{esc(client.get("prepared_by") or "6ix Developers")} · SEO audit</div>',
        f"<h1>{esc(name)}</h1>",
        '<p class="sub">'
        + esc(" · ".join([b for b in (client.get("domain"), client.get("market"), client.get("date")) if b]))
        + "</p></header>",
    ]

    # Executive summary, led by the score, because it is the one number a
    # skimming reader takes away.
    health = data.get("health") or {}
    summary = data.get("summary") or []
    if health.get("score") is not None or summary:
        parts.append('<section class="block"><h2>Executive summary</h2><div class="score-row">')
        if health.get("score") is not None:
            parts.append(gauge(health["score"], label="SEO Health Score",
                               caption=health.get("note")))
        parts.append('<div class="prose">')
        for para in (summary if isinstance(summary, list) else [summary]):
            parts.append(f"<p>{esc(para)}</p>")
        parts.append("</div></div></section>")

    parts.append(render_headlines(data.get("headlines")))
    parts.append(render_scorecard(data.get("scorecard")))

    comp = data.get("competitors")
    if comp:
        chart = line_chart(comp)
        if chart:
            parts.append('<section class="block"><h2>Where the traffic is going instead</h2>')
            if comp.get("lede"):
                parts.append(f'<p class="lede">{esc(comp["lede"])}</p>')
            parts.append(chart)
            if comp.get("verdict"):
                parts.append(f'<p class="verdict">{esc(comp["verdict"])}</p>')
            parts.append("</section>")

    parts.append(render_local(data.get("local")))
    parts.append(render_speed(data.get("speed")))
    parts.append(render_sections(data.get("sections")))

    closing = data.get("closing") or {}
    if closing:
        parts.append('<section class="block"><h2>'
                     + esc(closing.get("heading") or "What we would do about it")
                     + "</h2>")
        for key in ("body", "service", "next_step"):
            if closing.get(key):
                cls = ' class="teaser"' if key == "next_step" else ""
                parts.append(f"<p{cls}>{esc(closing[key])}</p>")
        parts.append("</section>")

    parts.append(
        "<footer>"
        + esc(data.get("footer")
              or "Figures in this report are measured, not modelled. Each section names the "
                 "tool it came from so anything here can be checked independently.")
        + "</footer>"
    )
    parts.append("</div></body></html>")
    return "".join(p for p in parts if p)


def main():
    ap = argparse.ArgumentParser(description="Render an audit JSON file as a self-contained HTML report.")
    ap.add_argument("audit", help="Path to the audit JSON file.")
    ap.add_argument("-o", "--out", help="Where to write the HTML (default: alongside the JSON).")
    args = ap.parse_args()

    src = Path(args.audit)
    try:
        data = json.loads(src.read_text(encoding="utf-8"))
    except FileNotFoundError:
        print(f"No such file: {src}", file=sys.stderr)
        return 2
    except json.JSONDecodeError as exc:
        print(f"{src} is not valid JSON: {exc}", file=sys.stderr)
        return 2

    if not isinstance(data, dict) or not data.get("client"):
        print("The audit file needs at least a `client` object (name, domain, date).", file=sys.stderr)
        return 2

    out = Path(args.out) if args.out else src.with_suffix(".html")
    out.write_text(build(data), encoding="utf-8")
    print(f"Wrote {out} ({out.stat().st_size:,} bytes)")

    missing = [k for k in ("health", "scorecard", "sections", "closing") if not data.get(k)]
    if missing:
        print("Sections absent from the audit file, so absent from the report: "
              + ", ".join(missing))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
