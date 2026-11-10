#!/usr/bin/env python3
"""
Does the rank-grid section state the grid honestly?

The two failure modes this guards are both from a verified real BrightLocal
run (see seo-strategy-builder/references/local-and-gmb.md):

  1. rank 0 counted as rank zero  -> a grid that looks ~20% better than it is
  2. the average recomputed       -> same thing, by a different route

Plus the ordinary renderer duties: north-up layout, buckets that match
BrightLocal's own definition, escaping, no crash on a ragged grid.
"""
import json
import re
import subprocess
import tempfile
import sys
from pathlib import Path
# Resolve the skill from this file, so the suite runs from any checkout.
REPO = Path(__file__).resolve().parent.parent.parent

HERE = Path(tempfile.mkdtemp(prefix="audit-render-"))
SCRIPT = REPO / ".claude/skills/seo-audit-report/scripts/render_report.py"
sys.path.insert(0, str(SCRIPT.parent))
import render_report as rr  # noqa: E402

fails = []


def check(name, ok, detail=""):
    print(f"{'PASS' if ok else 'FAIL'}  {name}" + (f"\n        {detail}" if detail and not ok else ""))
    if not ok:
        fails.append(name)


# ── A 7x7 grid with a deliberate distance-decay shape and three dead points ──
# Centre ranks well; the east edge is invisible. 49 points.
RANKS = [
    [12, 9, 6, 4, 7, 14, 0],
    [10, 7, 4, 2, 5, 11, 0],
    [8, 5, 2, 1, 3, 9, 19],
    [9, 6, 3, 1, 4, 10, 0],
    [11, 8, 5, 3, 6, 13, 22],
    [14, 11, 8, 6, 9, 17, 28],
    [18, 15, 12, 10, 14, 21, 34],
]
LAT0, LON0, STEP = 43.70, -79.50, 0.01
points = []
for ri, row in enumerate(RANKS):
    for ci, rank in enumerate(row):
        points.append({
            # Walk south as the row index grows, east as the column grows.
            "latitude": round(LAT0 - ri * STEP, 5),
            "longitude": round(LON0 + ci * STEP, 5),
            "rank": rank,
        })
# Hand them over shuffled, because the API does not promise an order.
import random
random.Random(7).shuffle(points)

flat = [r for row in RANKS for r in row]
n_high = sum(1 for r in flat if 1 <= r <= 3)
n_med = sum(1 for r in flat if 4 <= r <= 10)
n_absent = sum(1 for r in flat if r == 0)
n_low_true = sum(1 for r in flat if r >= 11) + n_absent
# What BrightLocal would report: unranked substituted with ~100 before averaging.
avg_reported = round(sum(r if r > 0 else 100 for r in flat) / len(flat), 1)
avg_naive = round(sum(flat) / len(flat), 1)

print(f"Fixture: 49 points · {n_high} high · {n_med} med · {n_low_true} low "
      f"({n_absent} of them invisible)")
print(f"         honest average {avg_reported} · naive average {avg_naive} "
      f"({(1 - avg_naive / avg_reported) * 100:.0f}% flattering)\n")

# ── 1. Layout ────────────────────────────────────────────────────────────────
matrix, oriented = rr.grid_matrix(points)
check("lattice detected from shuffled lat/lon", oriented)
check("7 rows of 7", len(matrix) == 7 and all(len(r) == 7 for r in matrix),
      f"got {len(matrix)} rows of {[len(r) for r in matrix]}")
got = [[c["rank"] for c in row] for row in matrix]
check("north-up, west-left: rows match the fixture", got == RANKS,
      f"row 0 is {got[0]}, expected {RANKS[0]}")

# ── 2. Buckets, computed when the run gives no summary ───────────────────────
kw_nosummary = {"keyword": "emergency plumber toronto", "grid_points": points}
html_ns = rr.render_local({"business": "Acme", "keywords": [kw_nosummary]})
check(f"high bucket reads {n_high} of 49", f"{n_high} of 49" in html_ns)
check(f"medium bucket reads {n_med} of 49", f"{n_med} of 49" in html_ns)
check(f"low bucket reads {n_low_true} of 49 (11+ AND invisible)",
      f"{n_low_true} of 49" in html_ns,
      "computed buckets must follow BrightLocal's rule, not exclude unranked")
check("low bucket says it includes the invisible points",
      "Position 11+ or not showing" in html_ns)

# ── 3. Trap one: rank 0 is never a good cell ─────────────────────────────────
cells = re.findall(r'<title>([^<]+)</title>', html_ns)
check(f"{n_absent} cells say 'not in the top 20'",
      sum(1 for c in cells if "Not in the top 20" in c) == n_absent,
      f"found {sum(1 for c in cells if 'Not in the top 20' in c)}")
check("no cell claims 'Position 0'", "Position 0 " not in html_ns)
absent_role = rr.rank_role(0)
check("rank 0 maps to its own role, not to good", absent_role == "absent",
      f"rank_role(0) == {absent_role!r}")
# Count cell fills only — the legend swatch and the bucket meter's bar use the
# same hex, so a bare count over the whole document proves nothing.
cell_fills = re.findall(r'<rect [^>]*fill="([^"]+)"', html_ns)
check(f"exactly {n_high} cells drawn in the 1-3 colour",
      cell_fills.count(rr.STATUS["good"]) == n_high,
      f"{cell_fills.count(rr.STATUS['good'])} cells, expected {n_high}")
check(f"exactly {n_absent} cells drawn in the pale 'absent' fill",
      cell_fills.count("var(--gauge-track)") == n_absent,
      f"{cell_fills.count('var(--gauge-track)')} cells, expected {n_absent}")
check("every one of the 49 points got a cell", len(cell_fills) == 49,
      f"{len(cell_fills)} cells")

# ── 4. Trap two: the average is quoted, never recomputed ─────────────────────
check("no average printed when the run supplied none",
      "average position across" not in html_ns,
      "the renderer must not invent an average from the raw ranks")

kw_summary = dict(kw_nosummary)
kw_summary["summary"] = {"avg_rank": avg_reported, "num_points": 49,
                         "num_high_ranking_points": n_high,
                         "num_med_ranking_points": n_med,
                         "num_low_ranking_points": n_low_true}
html_s = rr.render_local({"business": "Acme", "keywords": [kw_summary]})
check(f"supplied average {avg_reported} is printed verbatim",
      f"<strong>{avg_reported:g}</strong>" in html_s)
check(f"the flattering average {avg_naive} appears nowhere",
      f"{avg_naive:g}" not in html_s.replace(f"{avg_reported:g}", ""))
check("the average says what it counts",
      "counting the ones where you don't appear" in html_s
      or "counting the ones where you don&#x27;t appear" in html_s)
check("the average is framed as the lesser finding",
      "shape above matters more" in html_s)

# ── 5. Ragged / degenerate input does not crash or lie ───────────────────────
ragged = rr.render_local({"keywords": [{"keyword": "x", "grid_points": [
    {"latitude": 1.0, "longitude": 2.0, "rank": 5},
    {"latitude": 1.0, "longitude": 2.1, "rank": 0},
    {"latitude": 0.9, "longitude": 2.0, "rank": 3},
]}]})
check("3-point non-lattice renders with the honest caption",
      "this layout is not" in ragged and "2 of 3" not in ragged.split("Positions 1")[0],
      "a non-lattice must not claim a compass direction")
check("no keywords -> no section", rr.render_local({"keywords": []}) == "")
check("no local block -> no section", rr.render_local(None) == "")
noranks = rr.render_local({"keywords": [{"keyword": "y", "rows": [[0, 0], [0, 0]]}]})
check("an all-invisible grid reports 4 of 4 low", "4 of 4" in noranks)
check("an all-invisible grid claims no high points", "0 of 4" in noranks)

# ── 6. Escaping and the map link ─────────────────────────────────────────────
nasty = rr.render_local({
    "business": '<script>x</script>',
    "lede": 'A & B "quoted"',
    "keywords": [{"keyword": '<img src=x>', "rows": [[1]],
                  "shape": "falls off to the <east>",
                  "grid_url": 'https://lmr.com/m?a=1&b=2"'}],
})
check("keyword text escaped", "<img src=x>" not in nasty and "&lt;img" in nasty)
check("lede escaped", "A &amp; B" in nasty)
check("shape escaped", "&lt;east&gt;" in nasty)
check("map url escaped in the href", 'b=2&quot;' in nasty)
check("javascript: url refused", "javascript:" not in rr.render_local(
    {"keywords": [{"keyword": "k", "rows": [[1]], "grid_url": "javascript:alert(1)"}]}))

# ── 7. End to end through the CLI, into a real file ──────────────────────────
audit = {
    "client": {"name": "Acme Plumbing", "domain": "acmeplumbing.ca",
               "market": "Toronto", "date": "2026-11-10"},
    "health": {"score": 41, "note": "Measured 10 November 2026."},
    "summary": ["Acme is visible at its own address and invisible four kilometres east."],
    "local": {
        "business": "Acme Plumbing", "area": "Toronto, 6 km grid",
        "fetched": "10 November 2026",
        "keywords": [kw_summary, dict(kw_summary, keyword="drain cleaning toronto")],
        "verdict": "The pattern is distance decay, not weak ranking.",
    },
}
out = HERE / "rank_grid_report.html"
src = HERE / "rank_grid_audit.json"
src.write_text(json.dumps(audit), encoding="utf-8")
res = subprocess.run([sys.executable, str(SCRIPT), str(src), "-o", str(out)],
                     capture_output=True, text=True)
check("CLI exits 0", res.returncode == 0, res.stderr.strip())
doc = out.read_text(encoding="utf-8") if out.exists() else ""
check("section lands in the document", "Where you show up on the map" in doc)
check("both keywords rendered", doc.count('class="grid-card"') == 2)
check("no external resource", "http://" not in doc.replace("http://www.w3.org", "")
      and doc.count("https://") == doc.count('href="https://'))

print()
if fails:
    print(f"{len(fails)} FAILED: " + ", ".join(fails))
    sys.exit(1)
print(f"All checks passed. Report: {out}")
