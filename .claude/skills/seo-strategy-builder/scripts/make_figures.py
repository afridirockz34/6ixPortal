#!/usr/bin/env python3
"""
Generate the figures a client-facing strategy document carries instead of a
table of numbers.

Why this exists
---------------
A strategy document is read by somebody who does not know what "average
position 17.1" means, and a table of eleven such rows tells them nothing. The
same eleven rows as progress rings tell them, at a glance, that two things
are nearly done and nine have not started. That is the whole purpose: the
figure carries the meaning, and the number is a detail inside it.

What it makes
-------------
    goal        Today against the target, two bars a row. THE DEFAULT for a
                strategy document: at kickoff every `ring` reads 0% and a page
                of empty rings says nothing. Two bars show the jump.
    ring        A progress circle. Right in a monthly report, where progress
                genuinely exists. Wrong at kickoff, for the reason above.
    bar         A horizontal progress bar. Same data as a ring, better when
                several sit in a stack and get compared.
    compare     Horizontal bars ranking the firm against competitors. The
                firm's own bar is always drawn in the accent colour so it is
                findable without reading the labels.
    journey     A twelve-month track with the work marked on it. Replaces
                "the twelve-month plan" table for a reader who only wants to
                know roughly when things happen.

Everything is plain SVG from the standard library. No matplotlib, no network,
nothing to install — this has to run inside a client session on any machine.

Usage
-----
    python make_figures.py spec.json --out figures/

A spec is a list of figures. Every figure needs `kind`, `id` and `title`:

    [
      {"kind": "ring",    "id": "visits",  "title": "Monthly visits from search",
       "now": 173, "target": 400, "unit": "visits",
       "now_label": "today", "target_label": "by month 12"},

      {"kind": "goal",    "id": "targets", "title": "Where this plan takes us",
       "now_label": "today", "target_label": "by month 12",
       "rows": [["Monthly visits from search", 173, 400],
                ["Keywords on page one", 0, 25],
                ["Pages optimized", 0, 23]]},

      {"kind": "bar",     "id": "pages",   "title": "Existing pages optimized",
       "now": 0, "target": 23, "unit": "pages"},

      {"kind": "compare", "id": "reviews", "title": "Google reviews",
       "ours": "Mira Law",
       "rows": [["Charitsis Law", 358], ["Citadel Law", 216],
                ["Mira Law", 13]]},

      {"kind": "journey", "id": "plan",    "title": "What happens when",
       "months": {"1": "Foundations", "5": "Brampton and Oshawa",
                  "11": "The guide"}}
    ]

Direction of improvement
------------------------
Some metrics improve downward — average search position, page load time. Pass
`"lower_is_better": true` and the fill is computed from the right side, so a
drop from 17.1 to 9 reads as progress rather than as collapse. Getting this
backwards is the bug `tools/tests/test_metric_math.php` exists to catch on
the portal side, and the same mistake is available here.

Embedding
---------
Word 2016+, Pages and Google Docs all place SVG directly. Where a PNG is
needed, `rsvg-convert -w 1600 fig.svg -o fig.png` or any headless Chromium
will do it; nothing in the output depends on an external font or stylesheet.
"""

import json
import re
import sys
import os

# ── Colour ─────────────────────────────────────────────────────────────────
# Four colours and nothing else, because a client document with eight is a
# pie chart. Each one is distinguishable in greyscale print and passes AA
# against both the page and the track, which matters: these get printed and
# they get read by people who cannot tell red from green.
INK = "#1d2430"        # text
MUTE = "#6b7682"       # secondary text
TRACK = "#e6e9ed"      # the unfilled part of any bar or ring
FILL = "#1f6feb"       # progress, and the firm's own bar
AHEAD = "#9aa4b1"      # competitors
GOOD = "#1a7f5a"       # a target that is met

FONT = ("-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, "
        "'Helvetica Neue', Arial, sans-serif")


def esc(s) -> str:
    """XML-escape. Client names carry ampersands — "Caramanna, Friedberg &
    Co" in a raw SVG makes the file unopenable, and the failure looks like a
    broken image rather than an encoding bug."""
    return (str(s).replace("&", "&amp;").replace("<", "&lt;")
            .replace(">", "&gt;").replace('"', "&quot;"))


def fmt(n) -> str:
    """Numbers as a client would write them.

    Rounds a four-figure estimate, because the same rule that governs the
    prose governs a figure's label: plain-language.md bans an unhedged
    `4,501` and this is where it would otherwise sneak back in, since a chart
    label looks like a specification and is not one.
    """
    if isinstance(n, str):
        return n
    if n != n or n in (float("inf"), float("-inf")):   # NaN / inf
        return "—"
    if isinstance(n, float) and abs(n - round(n)) > 0.05:
        return f"{n:.1f}"
    n = int(round(n))
    # Round from a thousand up, not ten thousand. The first version of this
    # started at 10,000 and let "4,501" straight through — the exact figure
    # plain-language.md bans, in the one place that looks like a specification
    # and is not one. A count under a thousand is left alone: "23 pages" and
    # "49 points" are exact and the client can verify both.
    if abs(n) >= 1000:
        return f"{round(n, -2):,}"
    return f"{n:,}"


def _share(now, target, lower_is_better=False, start=None):
    """How full the ring or bar is, as 0.0-1.0.

    `start` is where we began, and it is what makes a downward metric
    meaningful: going from 17.1 to a target of 9 is 0% done at the outset and
    100% done on arrival, which cannot be computed from `now` and `target`
    alone. Without it, a downward metric is scored as target/now, which at
    least points the right way.
    """
    try:
        now = float(now)
        target = float(target)
    except (TypeError, ValueError):
        return 0.0
    if lower_is_better:
        if start is not None:
            try:
                start = float(start)
            except (TypeError, ValueError):
                start = None
        if start is not None and start != target:
            return max(0.0, min(1.0, (start - now) / (start - target)))
        if now <= 0:
            return 1.0
        return max(0.0, min(1.0, target / now))
    if target == 0:
        return 1.0 if now >= 0 else 0.0
    return max(0.0, min(1.0, now / target))


def _wrap(s, n):
    out, line = [], ""
    for w in str(s).split():
        if len(line) + len(w) + 1 > n and line:
            out.append(line)
            line = w
        else:
            line = f"{line} {w}".strip()
    if line:
        out.append(line)
    return out


def _row_label(name, x, y, width_px, size=14, weight="400"):
    """A row label wrapped to fit its column.

    Without this, "Seconds for the homepage to look finished" ran straight
    under the bar beside it. There is no text measurement available in plain
    SVG, so this estimates from the glyph count: at 14px in the system stack,
    an average character is about 0.55em. The estimate only has to be close —
    the column is 250px and the penalty for wrapping one word early is
    nothing, while the penalty for overflowing is an unreadable figure.
    """
    chars = max(8, int(width_px / (size * 0.55)))
    lines = _wrap(name, chars)[:2]
    if len(lines) == 2 and len(_wrap(name, chars)) > 2:
        lines[1] = lines[1][:chars - 1] + "…"
    # Two lines straddle the bar's centre; one line sits on it.
    top = y - (len(lines) - 1) * 7
    out = ""
    for i, line in enumerate(lines):
        out += (f'<text x="{x}" y="{top + i * 15:.1f}" font-family="{FONT}" '
                f'font-size="{size}" font-weight="{weight}" fill="{INK}">'
                f'{esc(line)}</text>')
    return out


def _svg(w, h, body):
    return (f'<svg xmlns="http://www.w3.org/2000/svg" width="{w}" '
            f'height="{h}" viewBox="0 0 {w} {h}" role="img">'
            f'<rect width="{w}" height="{h}" fill="none"/>{body}</svg>')


# ── ring ───────────────────────────────────────────────────────────────────
def ring(f):
    now, target = f.get("now", 0), f.get("target", 0)
    lower = bool(f.get("lower_is_better"))
    s = _share(now, target, lower, f.get("start"))
    met = s >= 0.999
    colour = GOOD if met else FILL

    # cy 118, not 104: at 104 the top of the stroke sat at y=22 and collided
    # with the title baseline at y=24.
    W, H, cx, cy, r, sw = 300, 264, 150, 118, 72, 20
    circ = 2 * 3.14159265 * r
    # -90 degrees so the arc starts at the top, which is where a reader
    # expects a progress ring to start.
    arc = (f'<circle cx="{cx}" cy="{cy}" r="{r}" fill="none" stroke="{TRACK}" '
           f'stroke-width="{sw}"/>'
           f'<circle cx="{cx}" cy="{cy}" r="{r}" fill="none" stroke="{colour}" '
           f'stroke-width="{sw}" stroke-linecap="round" '
           f'stroke-dasharray="{circ:.1f}" '
           f'stroke-dashoffset="{circ * (1 - s):.1f}" '
           f'transform="rotate(-90 {cx} {cy})"/>')

    pct = f'{int(round(s * 100))}%'
    centre = (f'<text x="{cx}" y="{cy - 2}" text-anchor="middle" font-family="{FONT}" '
              f'font-size="40" font-weight="700" fill="{INK}">{pct}</text>')
    # The number under the percentage, because a percentage alone is not
    # checkable and the client has to be able to argue with the figure.
    #
    # "of" only works counting upward. On a downward metric "17.1 of 9" reads
    # as a fraction and makes no sense, so it becomes an arrow.
    unit = f.get("unit", "")
    if lower:
        sub = esc(f'{fmt(now)} → {fmt(target)} {unit}'.strip())
    else:
        sub = esc(f'{fmt(now)} of {fmt(target)} {unit}'.strip())
    centre += (f'<text x="{cx}" y="{cy + 24}" text-anchor="middle" font-family="{FONT}" '
               f'font-size="14" fill="{MUTE}">{sub}</text>')

    head = ""
    for i, line in enumerate(_wrap(f.get("title", ""), 30)[:2]):
        head += (f'<text x="{cx}" y="{24 + i * 20}" text-anchor="middle" '
                 f'font-family="{FONT}" font-size="15" font-weight="600" '
                 f'fill="{INK}">{esc(line)}</text>')

    foot = ""
    nl, tl = f.get("now_label", "today"), f.get("target_label", "target")
    foot += (f'<text x="{cx}" y="{H - 14}" text-anchor="middle" font-family="{FONT}" '
             f'font-size="13" fill="{MUTE}">'
             f'{esc(f"{fmt(now)} {nl}")} → {esc(f"{fmt(target)} {tl}")}</text>')
    return _svg(W, H, head + arc + centre + foot)


# ── bar ────────────────────────────────────────────────────────────────────
def bar(f):
    rows = f.get("rows")
    if rows is None:
        rows = [[f.get("label", f.get("title", "")), f.get("now", 0),
                 f.get("target", 0)]]
    lower = bool(f.get("lower_is_better"))

    LBL, BARW, PAD, RH = 250, 300, 16, 46
    W = LBL + BARW + 110
    H = 44 + len(rows) * RH + PAD

    out = (f'<text x="{PAD}" y="26" font-family="{FONT}" font-size="16" '
           f'font-weight="600" fill="{INK}">{esc(f.get("title", ""))}</text>')
    y = 50
    for row in rows:
        name = row[0]
        now = row[1] if len(row) > 1 else 0
        target = row[2] if len(row) > 2 else f.get("target", 0)
        s = _share(now, target, lower, row[3] if len(row) > 3 else f.get("start"))
        met = s >= 0.999
        colour = GOOD if met else FILL
        out += _row_label(name, PAD, y + 15, LBL - PAD - 14)
        out += (f'<rect x="{LBL}" y="{y + 2}" width="{BARW}" height="16" rx="8" '
                f'fill="{TRACK}"/>')
        w = max(0.0, s * BARW)
        if w > 0.5:
            out += (f'<rect x="{LBL}" y="{y + 2}" width="{w:.1f}" height="16" '
                    f'rx="8" fill="{colour}"/>')
        out += (f'<text x="{LBL + BARW + 10}" y="{y + 15}" font-family="{FONT}" '
                f'font-size="13" fill="{MUTE}">'
                f'{esc(f"{fmt(now)} / {fmt(target)}")}</text>')
        y += RH
    return _svg(W, H, out)


# ── compare ────────────────────────────────────────────────────────────────
def compare(f):
    rows = [r for r in f.get("rows", []) if len(r) >= 2]
    if not rows:
        return _svg(320, 60, f'<text x="16" y="34" font-family="{FONT}" '
                             f'font-size="14" fill="{MUTE}">no rows</text>')
    ours = str(f.get("ours", "")).strip().lower()

    def val(r):
        try:
            return float(r[1])
        except (TypeError, ValueError):
            return 0.0

    top = max(val(r) for r in rows) or 1.0
    LBL, BARW, PAD, RH = 230, 320, 16, 38
    W = LBL + BARW + 90
    H = 46 + len(rows) * RH + PAD + 24

    out = (f'<text x="{PAD}" y="26" font-family="{FONT}" font-size="16" '
           f'font-weight="600" fill="{INK}">{esc(f.get("title", ""))}</text>')
    y = 52
    for r in rows:
        mine = ours and str(r[0]).strip().lower() == ours
        colour = FILL if mine else AHEAD
        weight = "700" if mine else "400"
        out += _row_label(r[0], PAD, y + 14, LBL - PAD - 14, weight=weight)
        w = max(2.0, val(r) / top * BARW)
        out += (f'<rect x="{LBL}" y="{y + 1}" width="{w:.1f}" height="16" rx="3" '
                f'fill="{colour}"/>')
        out += (f'<text x="{LBL + w + 8:.1f}" y="{y + 14}" font-family="{FONT}" '
                f'font-size="13" font-weight="{weight}" fill="{MUTE}">'
                f'{esc(fmt(r[1]))}</text>')
        y += RH
    if f.get("note"):
        out += (f'<text x="{PAD}" y="{H - 14}" font-family="{FONT}" font-size="12" '
                f'fill="{MUTE}">{esc(f["note"])}</text>')
    return _svg(W, H, out)


# ── journey ────────────────────────────────────────────────────────────────
def journey(f):
    months = f.get("months", {}) or {}
    n = int(f.get("length", 12))
    W, PAD = 980, 30
    H = 230
    track_y = 108
    step = (W - 2 * PAD - 20) / max(1, n - 1)

    out = (f'<text x="{PAD}" y="28" font-family="{FONT}" font-size="17" '
           f'font-weight="600" fill="{INK}">{esc(f.get("title", ""))}</text>')
    out += (f'<rect x="{PAD}" y="{track_y - 3}" width="{W - 2 * PAD}" height="6" '
            f'rx="3" fill="{TRACK}"/>')

    # A centred label on month 1 ran off the left edge — the first plan drawn
    # with this read "oundations and Brampton". Labels are clamped into the
    # canvas rather than centred blindly.
    half = 58

    for i in range(n):
        m = str(i + 1)
        x = PAD + 10 + i * step
        lx = min(max(x, half), W - half)
        marked = m in months
        rad = 11 if marked else 6
        out += (f'<circle cx="{x:.1f}" cy="{track_y}" r="{rad}" '
                f'fill="{FILL if marked else TRACK}"/>')
        if marked:
            out += (f'<text x="{x:.1f}" y="{track_y + 4}" text-anchor="middle" '
                    f'font-family="{FONT}" font-size="11" font-weight="700" '
                    f'fill="#ffffff">{esc(m)}</text>')
        else:
            out += (f'<text x="{x:.1f}" y="{track_y + 26}" text-anchor="middle" '
                    f'font-family="{FONT}" font-size="11" fill="{MUTE}">{esc(m)}</text>')
        if marked:
            # Alternate above and below so neighbouring labels cannot collide,
            # which they do on any plan that marks consecutive months.
            above = (i % 2 == 0)
            base = track_y - 26 if above else track_y + 34
            lines = _wrap(months[m], 16)[:3]
            for j, line in enumerate(lines):
                yy = (base - (len(lines) - 1 - j) * 14) if above else (base + j * 14)
                out += (f'<text x="{lx:.1f}" y="{yy:.1f}" text-anchor="middle" '
                        f'font-family="{FONT}" font-size="12" fill="{INK}">'
                        f'{esc(line)}</text>')
    out += (f'<text x="{PAD}" y="{H - 10}" font-family="{FONT}" font-size="12" '
            f'fill="{MUTE}">{esc(f.get("note", "Month of the plan"))}</text>')
    return _svg(W, H, out)


# ── goal ───────────────────────────────────────────────────────────────────
def goal(f):
    """Today against the target, as two bars per row.

    The figure the strategy document actually needs, and the reason it exists
    is worth recording. A `ring` shows how far through the work we are, which
    is the right question in a monthly report and a useless one in a strategy:
    at kickoff every ring reads 0%, and a page of 0% rings tells the client
    nothing except that nothing has happened yet.

    Two bars say the thing that matters instead — where the firm is now, and
    where this plan takes it. The jump is the point, and it is visible without
    reading a single number.
    """
    rows = [r for r in f.get("rows", []) if len(r) >= 3]
    if not rows:
        return _svg(320, 60, f'<text x="16" y="34" font-family="{FONT}" '
                             f'font-size="14" fill="{MUTE}">no rows</text>')

    def num(v):
        try:
            return float(v)
        except (TypeError, ValueError):
            return 0.0

    lower = bool(f.get("lower_is_better"))
    LBL, BARW, PAD, RH = 250, 290, 16, 58
    W = LBL + BARW + 110
    H = 52 + len(rows) * RH + PAD + 20

    out = (f'<text x="{PAD}" y="26" font-family="{FONT}" font-size="16" '
           f'font-weight="600" fill="{INK}">{esc(f.get("title", ""))}</text>')
    now_lab = f.get("now_label", "today")
    tgt_lab = f.get("target_label", "by month 12")
    out += (f'<rect x="{LBL}" y="34" width="11" height="11" rx="2" fill="{AHEAD}"/>'
            f'<text x="{LBL + 17}" y="44" font-family="{FONT}" font-size="12" '
            f'fill="{MUTE}">{esc(now_lab)}</text>'
            f'<rect x="{LBL + 90}" y="34" width="11" height="11" rx="2" fill="{FILL}"/>'
            f'<text x="{LBL + 107}" y="44" font-family="{FONT}" font-size="12" '
            f'fill="{MUTE}">{esc(tgt_lab)}</text>')

    y = 62
    for r in rows:
        name, now, target = r[0], num(r[1]), num(r[2])
        # Both bars share one scale per row, so their lengths are comparable.
        # On a downward metric the larger number is the worse one, and drawing
        # it longer is correct: the bar visibly shrinks toward the goal.
        top = max(abs(now), abs(target)) or 1.0
        wn = max(2.0, abs(now) / top * BARW)
        wt = max(2.0, abs(target) / top * BARW)
        out += _row_label(name, PAD, y + 18, LBL - PAD - 14)
        out += (f'<rect x="{LBL}" y="{y + 1}" width="{wn:.1f}" height="14" rx="3" '
                f'fill="{AHEAD}"/>'
                f'<text x="{LBL + wn + 8:.1f}" y="{y + 12}" font-family="{FONT}" '
                f'font-size="12" fill="{MUTE}">{esc(fmt(r[1]))}</text>')
        out += (f'<rect x="{LBL}" y="{y + 21}" width="{wt:.1f}" height="14" rx="3" '
                f'fill="{FILL}"/>'
                f'<text x="{LBL + wt + 8:.1f}" y="{y + 32}" font-family="{FONT}" '
                f'font-size="12" font-weight="600" fill="{FILL}">'
                f'{esc(fmt(r[2]))}</text>')
        y += RH

    note = f.get("note")
    if not note and lower:
        note = "Lower is better on these."
    if note:
        out += (f'<text x="{PAD}" y="{H - 14}" font-family="{FONT}" font-size="12" '
                f'fill="{MUTE}">{esc(note)}</text>')
    return _svg(W, H, out)


KINDS = {"ring": ring, "bar": bar, "compare": compare, "journey": journey,
         "goal": goal}
SAFE_ID = re.compile(r"[^a-z0-9_-]+")


def main():
    args = [a for a in sys.argv[1:]]
    if not args or args[0] in ("-h", "--help", "help"):
        print(__doc__.strip())
        return 0
    out_dir = "."
    spec_path = None
    i = 0
    while i < len(args):
        if args[i] == "--out":
            i += 1
            out_dir = args[i] if i < len(args) else "."
        else:
            spec_path = args[i]
        i += 1
    if not spec_path:
        print(__doc__.strip())
        return 2

    with open(spec_path, encoding="utf-8") as fh:
        spec = json.load(fh)
    if isinstance(spec, dict):
        spec = spec.get("figures", [spec])

    os.makedirs(out_dir, exist_ok=True)
    made, errs = [], []
    for idx, f in enumerate(spec):
        # Sanitised, because the id becomes a filename and these specs get
        # assembled from client data.
        fid = SAFE_ID.sub("-", str(f.get("id") or f"fig{idx}").lower()).strip("-")
        kind = str(f.get("kind", "")).lower()
        if kind not in KINDS:
            # Named, not just numbered. In a twenty-figure spec "figure 7" is
            # a thing to go and count; the id is the thing to go and fix.
            errs.append(f"figure {idx} ({fid}): unknown kind {kind!r} "
                        f"(use {', '.join(sorted(KINDS))})")
            continue
        path = os.path.join(out_dir, f"{fid or f'fig{idx}'}.svg")
        try:
            svg = KINDS[kind](f)
        except Exception as exc:                      # noqa: BLE001
            errs.append(f"figure {idx} ({fid}): {exc}")
            continue
        with open(path, "w", encoding="utf-8") as fh:
            fh.write(svg)
        made.append(path)

    for p in made:
        print(f"wrote {p}")
    for e in errs:
        print(f"ERROR {e}", file=sys.stderr)
    # A figure that silently did not get written is the portal's own recurring
    # bug in another form: the document then references an image that is not
    # there, and nobody notices until the client opens it.
    if errs:
        return 1
    if not made:
        print("ERROR no figures in spec", file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
