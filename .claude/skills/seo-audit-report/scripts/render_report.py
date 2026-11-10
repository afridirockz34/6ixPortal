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
        cell = {}
        for p, la, lo in zip(pts, lats, lons):
            cell[(la, lo)] = p
        # Place every point on its own lat/lon row and column and leave the
        # rest of the rectangle empty.
        #
        # This used to require a perfect rectangle — rows times columns equal
        # to the point count — and fall back to chunking the list in order
        # when that failed. BrightLocal grids are routinely NOT rectangles:
        # a report can exclude individual points (`excluded_points`), and a
        # custom shape drawn around a service area has holes by design. Those
        # grids were being drawn as a list of squares in arrival order, which
        # is not a map and cannot show the thing a grid exists to show.
        if len(cell) == len(pts) and len(uniq_lat) > 1 and len(uniq_lon) > 1:
            rows = [[cell.get((la, lo)) for lo in uniq_lon] for la in uniq_lat]
            # A lattice this sparse is not a grid that was drawn on a map; it
            # is more likely scattered points, where a rectangle would be
            # mostly holes and imply a shape that is not there.
            if len(pts) >= 0.25 * len(uniq_lat) * len(uniq_lon):
                return rows, True

    side = max(1, int(round(math.sqrt(len(pts)))))
    return [pts[i:i + side] for i in range(0, len(pts), side)], False


def keyword_matrix(obj):
    """A grid holder (a keyword, or one rival) to (matrix, oriented)."""
    if not isinstance(obj, dict):
        return [], False
    rows = obj.get("rows")
    if rows:
        # null in an explicit row is a hole in a custom-shaped grid — a place
        # nothing was measured — and stays None so nothing is drawn there.
        return ([[None if c is None else (c if isinstance(c, dict) else {"rank": c})
                  for c in r] for r in rows],
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


def rank_grid(rows, oriented, box=250, caption=True, map_image=None):
    """
    One keyword's grid of points, as the map lays it out.

    `map_image` is a background the squares sit on — a data URI or URL of the
    same ground the grid covers. Without it the grid is a coloured abstraction
    nobody can place; with it, "strong at the shop, gone past Eglinton" is
    something the reader can see rather than take on trust.
    """
    if not rows:
        return ""
    cols = max(len(r) for r in rows)
    n = max(cols, len(rows))
    gap = 3 if n <= 9 else 2
    cell = max(10, min(36, (box - gap * (n - 1)) // n))
    show_numbers = cell >= 22
    gw = cols * cell + (cols - 1) * gap
    gh = len(rows) * cell + (len(rows) - 1) * gap
    # With a map behind it, the grid is inset so the surrounding streets show
    # around the measured area. Without that margin the squares cover the map
    # almost completely and it may as well not be there — the point of the map
    # is the ground the grid sits on, not a texture under it.
    pad = int(round(max(gw, gh) * 0.1)) if map_image else 0
    w, h = gw + pad * 2, gh + pad * 2
    live = sum(1 for r in rows for c in r if c is not None)

    svg = [f'<svg class="rgrid" viewBox="0 0 {w} {h}" width="{w}" height="{h}" '
           f'role="img" aria-label="Rank at each of {live} '
           f'points across the service area">']
    if map_image:
        # Decoration for the squares on top of it, so it is hidden from
        # assistive tech rather than described twice.
        svg.append(f'<image href="{esc(map_image)}" x="0" y="0" width="{w}" height="{h}" '
                   'preserveAspectRatio="xMidYMid slice" aria-hidden="true"/>'
                   f'<rect x="0" y="0" width="{w}" height="{h}" fill="#ffffff" '
                   'opacity="0.12" aria-hidden="true"/>')
    for ri, row in enumerate(rows):
        for ci, p in enumerate(row):
            # A hole in a custom-shaped grid: no point was measured here, so
            # nothing is drawn. Drawing a square would invent a measurement.
            if p is None:
                continue
            rank = p.get("rank") if isinstance(p, dict) else p
            role = rank_role(rank)
            x, y = pad + ci * (cell + gap), pad + ri * (cell + gap)
            fill = "#e4e2dc" if role == "absent" else STATUS[role]
            r_num = num(rank)
            shown = "—" if role == "absent" else f"{int(r_num)}"
            # Not "not in the top 20": BrightLocal reports positions well past
            # 20 — a grid routinely carries ranks in the 90s — so naming a
            # cutoff the data does not use states something untrue about every
            # square that has one.
            title = ("Did not appear here" if role == "absent"
                     else f"Position {int(r_num)} here")
            # Let the map read through a little where there is one.
            op = (' opacity="0.88" stroke="#ffffff" stroke-width="0.75"'
                  if map_image else "")
            svg.append(f'<g><title>{esc(title)}</title>'
                       f'<rect x="{x}" y="{y}" width="{cell}" height="{cell}" rx="4" '
                       f'fill="{fill}"{op}/>')
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
           + esc(local.get("heading") or "Google Business Profile") + "</h2>"]

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

        # The ground the grid covers. Per keyword if given, else the section's
        # own, since every keyword in one report shares the same grid. Only
        # drawn when the layout really is a map — behind a list of squares in
        # arrival order it would imply a geography that is not there.
        map_image = kw.get("map_image") or local.get("map_image") or ""
        if not oriented:
            map_image = ""

        flat = [c for r in matrix for c in r if c is not None]
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
                + rank_grid(matrix, oriented, box=box, caption=False,
                            map_image=map_image) + "</figure>")
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
            out.append(rank_grid(matrix, oriented, map_image=map_image))

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
            client_pts = [c for r in matrix for c in r if c is not None]
            unaligned = []
            for rv, rmat in rival_grids:
                pairs = align_grids(client_pts,
                                    [c for r in rmat for c in r if c is not None])
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

    # A competitor chart with no client line is a chart about other people.
    # It is the most persuasive section in the report precisely because the
    # reader can find themselves on it, so refuse to draw it rather than
    # quietly ship a comparison with one party missing.
    if not any(s.get("you") for s in series):
        return ('<p class="gap-note">This chart was not drawn: none of the series is marked '
                'as the client\'s own, so there would be no line for them to find themselves '
                'on. Add <code>"you": true</code> to their series.</p>')

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
    """
    One table: category, status, verdict, score.

    A table rather than a column of bars because this is the part a skimming
    reader actually reads in full, and a verdict that has to stand on its own
    belongs on the same line as the thing it is judging.
    """
    if not rows:
        return ""
    out = ['<section class="block"><h2>Scorecard</h2>',
           '<table class="scorecard"><thead><tr>'
           '<th scope="col">Category</th><th scope="col">Status</th>'
           '<th scope="col">What we found</th><th scope="col" class="num">Score</th>'
           "</tr></thead><tbody>"]
    for r in rows:
        score = num(r.get("score"))
        role  = status_for(r)
        # The scorecard's vocabulary is the severity rubric — Critical, High,
        # Medium, Low, Healthy — not the renderer's internal colour roles. An
        # audit that declares `"status": "warning"` means Medium; printing the
        # role name leaks an implementation detail into the client's document.
        role_word = {"good": "Healthy", "warning": "Medium",
                     "serious": "High", "critical": "Critical"}[role]
        declared  = str(r.get("status", "")).strip()
        sev = declared if declared.lower() in SEVERITY_STATUS else role_word
        figure = f"{score:g}" if score is not None else "—"
        verdict = r.get("verdict") or ("Not checked in this audit." if score is None else "")
        out.append(
            f'<tr><th scope="row">{esc(r.get("category", ""))}</th>'
            f'<td><span class="sev" style="background:{STATUS[role]};'
            f'color:{"#0b0b0b" if role in ("warning", "good") else "#fff"}">{esc(sev)}</span></td>'
            f'<td class="verdict-cell">{esc(verdict)}</td>'
            f'<td class="num">{esc(figure)}</td></tr>'
        )
    out.append("</tbody></table></section>")
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
        # The heading names the area audited — "Organic keyword visibility",
        # "Backlink profile" — and the finding goes in the first paragraph
        # where there is room to say it properly. A heading that is itself a
        # finding ("Only one of your five priority searches makes your top
        # 15") has to be decoded before it can be read, and a report whose
        # headings need decoding does not look like one that knows its subject.
        heading = s.get("category") or s.get("title", "")
        out.append(
            f'<h2>{esc(heading)}'
            + (f'<span class="sev" style="background:{STATUS[role]};'
               f'color:{"#0b0b0b" if role == "warning" else "#fff"}">{esc(sev)}</span>' if sev else "")
            + "</h2>"
        )
        for key in ("finding", "why"):
            if s.get(key):
                out.append(f"<p>{esc(s[key])}</p>")
        # What we would do about it. This is 6ix speaking rather than
        # reporting, so it is set as a statement in its own right — not a
        # quotation hanging off a coloured rule, which reads like a footnote
        # apologising for itself.
        if s.get("teaser"):
            out.append(f'<p class="action"><span class="action-label">Our recommendation</span>'
                       f'{esc(s["teaser"])}</p>')
        out.append("</section>")
    return "".join(out)


STYLE = """
/* A report, not a web page.
   White only, on purpose: this is printed, emailed and turned into a PDF, and
   a dark background is wrong in every one of those. No cards either — boxed
   sections make a document look like a dashboard, and a dashboard does not
   read as a considered opinion. Sections are separated by space and a rule,
   the way a report is. */
:root{
  color-scheme: light;
  --page:#ffffff; --surface:#ffffff; --ink:#111111; --ink-2:#44443f; --muted:#6f6e68;
  --grid:#e4e2dc; --axis:#b9b7ad; --border:#ddd9cf; --rule:#111111; --gauge-track:#eceae4;
  --series-1:#FF6699; --series-2:#2a78d6; --series-3:#eb6834; --series-4:#1baf7a;
}
*{box-sizing:border-box}
html{-webkit-print-color-adjust:exact;print-color-adjust:exact}
body{margin:0;background:var(--page);color:var(--ink);
  font:16px/1.7 -apple-system,BlinkMacSystemFont,"Segoe UI",Helvetica,Arial,sans-serif;
  -webkit-font-smoothing:antialiased}
.sheet{max-width:780px;margin:0 auto;padding:56px 32px 64px}

/* ── cover ── */
header.cover{margin-bottom:44px}
.eyebrow{font-size:11px;font-weight:700;letter-spacing:1.6px;text-transform:uppercase;color:var(--muted)}
h1{font-size:clamp(30px,5vw,42px);line-height:1.12;margin:14px 0 10px;letter-spacing:-.025em;font-weight:700}
.sub{color:var(--ink-2);font-size:15px;margin:0}
.cover-rule{height:3px;background:var(--rule);margin:22px 0 0;width:64px}

/* ── sections: rule, heading, prose ── */
.block{margin:0 0 40px;padding:0;background:none;border:0}
.block + .block{border-top:1px solid var(--border);padding-top:34px}
h2{font-size:21px;margin:0 0 16px;letter-spacing:-.015em;font-weight:700;
  display:flex;align-items:center;gap:12px;flex-wrap:wrap;line-height:1.3}
h3{font-size:15px;margin:0 0 10px;font-weight:700}
.lede{color:var(--ink-2);font-size:14.5px;margin:0 0 18px}
p{margin:0 0 14px}
p:last-child{margin-bottom:0}
.verdict{color:var(--ink-2);font-size:14.5px;margin:12px 0 0}
.sev{font-size:10.5px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;
  padding:3px 9px;border-radius:3px;white-space:nowrap}

/* What we would do: our recommendation, stated plainly. */
.action{margin:16px 0 0;padding:16px 18px;background:#f7f6f3;border-left:3px solid var(--rule)}
.action-label{display:block;font-size:10.5px;font-weight:700;letter-spacing:1.2px;
  text-transform:uppercase;color:var(--muted);margin-bottom:5px}

/* ── score ── */
.score-row{display:flex;gap:32px;align-items:center;flex-wrap:wrap}
.score-row .prose{flex:1 1 320px;min-width:280px}
.gauge-wrap{text-align:center;flex:0 0 auto}
.gauge-value{font-size:40px;font-weight:700;fill:var(--ink)}
.gauge-of{font-size:11px;fill:var(--muted);letter-spacing:.4px}
.gauge-label{font-size:13px;font-weight:600;margin-top:2px}
.gauge-caption{font-size:11.5px;color:var(--muted);max-width:190px;margin:2px auto 0;line-height:1.45}

/* ── tiles ── */
.tiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:0;margin-bottom:34px;
  border-top:1px solid var(--border);border-bottom:1px solid var(--border)}
.tile{padding:18px 20px 18px 0}
.tile + .tile{padding-left:20px;border-left:1px solid var(--border)}
.tile.wide{margin-top:14px;border:0;padding-left:0}
.tile-value{font-size:30px;font-weight:700;line-height:1.05;letter-spacing:-.02em}
.tile.bad .tile-value{color:#c0392b}
.tile.good .tile-value{color:#0a8a0a}
.tile-label{font-size:13px;color:var(--ink-2);margin-top:6px;line-height:1.45}
.tile-note{font-size:11.5px;color:var(--muted);margin-top:5px}

/* ── scorecard table ── */
table.scorecard{width:100%;border-collapse:collapse;font-size:14px}
table.scorecard th,table.scorecard td{text-align:left;padding:11px 12px 11px 0;
  border-bottom:1px solid var(--border);vertical-align:top}
table.scorecard thead th{font-size:10.5px;letter-spacing:1px;text-transform:uppercase;
  color:var(--muted);font-weight:700;border-bottom:2px solid var(--rule);padding-bottom:8px}
table.scorecard th[scope=row]{font-weight:700;width:30%}
table.scorecard .verdict-cell{color:var(--ink-2);font-size:13.5px;line-height:1.6}
table.scorecard .num{text-align:right;font-variant-numeric:tabular-nums;font-weight:700;
  padding-right:0;width:64px}

/* ── meters (speed only) ── */
.meters{display:flex;flex-direction:column;gap:2px}
.meter{display:grid;grid-template-columns:1fr 170px 92px;gap:14px;align-items:center;padding:9px 0}
.meter-name{font-size:14px;font-weight:600}
.meter-note{display:block;font-size:11.5px;font-weight:400;color:var(--muted)}
.meter-track{height:8px;border-radius:5px;background:var(--gauge-track);overflow:hidden}
.meter-fill{height:100%;border-radius:5px}
.meter-figure{font-size:14px;font-weight:700;text-align:right;font-variant-numeric:tabular-nums}
.speed-grid{display:flex;gap:32px;align-items:center;flex-wrap:wrap}
.speed-meters{flex:1 1 360px;min-width:300px}
.gap-note{font-size:13px;color:var(--ink-2);background:#f7f6f3;border-left:3px solid var(--border);
  padding:12px 14px;margin-top:14px}

/* ── rank grid ── */
.rgrid-legend{margin:0 0 20px;gap:10px 18px;font-size:12px}
.rgrid-legend strong{color:var(--ink);font-variant-numeric:tabular-nums}
.grids{display:grid;grid-template-columns:repeat(auto-fit,minmax(272px,1fr));gap:26px}
.grid-card{min-width:0;max-width:430px}
.grid-card.compare{max-width:none;grid-column:1/-1}
.grid-card.compare .grid-meters{max-width:460px}
.versus{display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start;margin-bottom:4px}
.vs-col{margin:0;min-width:0}
.vs-name{font-size:12.5px;font-weight:700;margin-bottom:7px;display:flex;align-items:center;gap:7px}
.vs-finding{font-size:13.5px;color:var(--ink-2);margin:12px 0 0}
.vs-finding strong{color:var(--ink);font-variant-numeric:tabular-nums}
.grid-kw{font-size:14.5px;margin:0 0 10px;letter-spacing:-.01em}
.grid-kw::before{content:"“"}
.grid-kw::after{content:"”"}
.rgrid-figure{margin:0 0 10px}
.rgrid{max-width:100%;height:auto;display:block;border:1px solid var(--border);border-radius:6px}
.rcell{font-size:11px;font-weight:700;fill:#0b0b0b;font-variant-numeric:tabular-nums}
.rcell-absent{fill:var(--ink-2);font-weight:400}
.grid-meters .meter{grid-template-columns:1fr 100px 74px;gap:10px;padding:5px 0}
.grid-meters .meter-name{font-size:12.5px;font-weight:500}
.grid-meters .meter-figure{font-size:12.5px}
.grid-avg{font-size:12.5px;color:var(--ink-2);margin:12px 0 0}
.grid-avg strong{font-size:15px;color:var(--ink);font-variant-numeric:tabular-nums}
.map-link{font-size:12.5px;margin:8px 0 0}
.map-link a{color:var(--series-2)}

/* ── chart ── */
.chart-figure{margin:0}
.chart{max-width:100%;height:auto;display:block}
.grid{stroke:var(--grid);stroke-width:1}
.tick{font-size:11px;fill:var(--muted)}
.line{stroke-width:2;stroke-linejoin:round;stroke-linecap:round}
.line.emph{stroke-width:3.5}
.dot{stroke:#ffffff;stroke-width:2}
.endlabel{font-size:12px;fill:var(--ink)}
.endvalue{font-size:11px;fill:var(--muted);font-variant-numeric:tabular-nums}
.leader{stroke:var(--axis);stroke-width:1}
.legend{display:flex;flex-wrap:wrap;gap:14px;margin-top:12px;font-size:12.5px;color:var(--ink-2)}
.legend-item{display:inline-flex;align-items:center;gap:6px}
.swatch{width:11px;height:11px;border-radius:2px;display:inline-block}
.you-tag{font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--muted)}
.chart-note{font-size:11.5px;color:var(--muted);margin:8px 0 0}

@media print{
  .sheet{max-width:none;padding:0}
  .block,.tile,.grid-card{break-inside:avoid}
  h2{break-after:avoid}
  .block{margin-bottom:30px}
}
@media (max-width:620px){
  .sheet{padding:36px 16px 48px}
  .meter{grid-template-columns:1fr 84px;row-gap:6px}
  .meter-track{grid-column:1/-1;order:3}
  .grid-meters .meter{grid-template-columns:1fr 74px;row-gap:6px}
  .tile + .tile{padding-left:0;border-left:0;border-top:1px solid var(--border)}
  table.scorecard th[scope=row]{width:auto}
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
        f'<div class="eyebrow">SEO Audit · {esc(client.get("prepared_by") or "6ix Developers")}</div>',
        f"<h1>{esc(name)}</h1>",
        '<p class="sub">'
        + esc(" · ".join([b for b in (client.get("domain"), client.get("market")) if b]))
        + "</p>"
        + (f'<p class="sub">Prepared {esc(client["date"])}</p>' if client.get("date") else "")
        + '<div class="cover-rule"></div></header>',
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
            parts.append('<section class="block"><h2>Competitor Analysis</h2>')
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

    if data.get("footer"):
        parts.append("<footer>" + esc(data["footer"]) + "</footer>")

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
