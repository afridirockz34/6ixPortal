#!/usr/bin/env python3
"""
The competitor half of the rank grid.

The claim this section makes — "they are in the pack at N points where you do
not come up at all" — is only true if the two grids are the same places. So the
tests that matter here are the alignment ones: same points shuffled must align,
different points must refuse to be compared rather than compare by index.
"""
import re
import sys
from pathlib import Path

# Resolve the skill from this file, so the suite runs from any checkout.
REPO = Path(__file__).resolve().parent.parent.parent
sys.path.insert(0, str(REPO / ".claude/skills/seo-audit-report/scripts"))
import render_report as rr  # noqa: E402

fails = []


def check(name, ok, detail=""):
    print(f"{'PASS' if ok else 'FAIL'}  {name}" + (f"\n        {detail}" if detail and not ok else ""))
    if not ok:
        fails.append(name)


LAT0, LON0, D = 43.80, -79.47, 0.02
# 5x5. You own the north-west; they own the south-east.
YOU = [[1, 2, 3, 6, 9],
       [2, 1, 4, 8, 14],
       [3, 5, 9, 0, 0],
       [7, 11, 0, 0, 0],
       [12, 0, 0, 0, 0]]
THEM = [[8, 7, 5, 3, 2],
        [9, 6, 4, 2, 1],
        [11, 8, 3, 1, 1],
        [14, 9, 2, 1, 2],
        [19, 12, 3, 2, 1]]


def pts(ranks):
    return [{"latitude": round(LAT0 - r * D, 5), "longitude": round(LON0 + c * D, 5),
             "rank": v} for r, row in enumerate(ranks) for c, v in enumerate(row)]


you_pts, them_pts = pts(YOU), pts(THEM)
flat_you = [v for r in YOU for v in r]
flat_them = [v for r in THEM for v in r]
you_pack = sum(1 for v in flat_you if 1 <= v <= 3)
them_pack = sum(1 for v in flat_them if 1 <= v <= 3)
both = list(zip(flat_you, flat_them))
them_pack_you_gone = sum(1 for c, t in both if 1 <= t <= 3 and c == 0)
print(f"Fixture 5x5: you in the pack at {you_pack}, them at {them_pack}, "
      f"{them_pack_you_gone} of theirs where you are invisible\n")

# ── Alignment ────────────────────────────────────────────────────────────────
import random
shuffled = list(them_pts)
random.Random(3).shuffle(shuffled)
pairs = rr.align_grids(you_pts, shuffled)
check("same points in a different order still align", pairs is not None)
check("aligned pairs are matched by place, not by index",
      pairs == both, "a shuffled rival must still line up with the right squares")

check("a rival measured on different coordinates refuses to compare",
      rr.align_grids(you_pts, pts([[r for r in row] for row in THEM])[:20]) is None)
shifted = [dict(p, latitude=round(p["latitude"] + 0.5, 5)) for p in them_pts]
check("a rival on a shifted grid refuses to compare",
      rr.align_grids(you_pts, shifted) is None)
check("points with no coordinates refuse to compare",
      rr.align_grids([{"rank": 1}], [{"rank": 2}]) is None)
check("a rival with duplicate points refuses to compare",
      rr.align_grids(you_pts, them_pts[:-1] + [them_pts[0]]) is None)

# ── The sentence it writes ───────────────────────────────────────────────────
line = rr.versus_line("First Rank Plumbing", both)
check(f"states their pack count ({them_pack})", f"<strong>{them_pack}</strong>" in line)
check(f"states your pack count ({you_pack})", f"<strong>{you_pack}</strong>" in line)
check(f"names the {them_pack_you_gone} points where you are absent",
      f"At {them_pack_you_gone} of those points" in line)
check("says nothing about money or lost customers",
      not re.search(r"\$|revenue|lead|worth|cost", line, re.I), line)
check("the absent clause is dropped when it would be zero",
      "At 0 of those points" not in rr.versus_line("X", [(1, 1), (2, 2)]))

# ── Rendered ─────────────────────────────────────────────────────────────────
kw = {"keyword": "emergency plumber toronto", "grid_points": you_pts,
      "rivals": [{"name": "First Rank Plumbing", "grid_points": shuffled,
                  "grid_url": "https://lmr.example/map"}]}
html = rr.render_local({"business": "Northside Plumbing", "keywords": [kw]})
check("both grids drawn", html.count("<svg") == 2, f"{html.count('<svg')} svgs")
check("your column is tagged as yours", 'class="you-tag">you<' in html)
check("the rival is named", "First Rank Plumbing" in html)
check("card goes full width", 'class="grid-card compare"' in html)
check("caption says the maps are the same points",
      "Every map here is the same points" in html)
check("the comparison line is printed", "is in the top three at" in html)
check("your own buckets are still reported", "Position 11+ or not showing" in html)

# Two rivals, one of which cannot be aligned.
kw2 = {"keyword": "k", "grid_points": you_pts, "rivals": [
    {"name": "Aligned Co", "grid_points": them_pts},
    {"name": "Elsewhere Co", "grid_points": shifted},
]}
h2 = rr.render_local({"business": "You", "keywords": [kw2]})
check("the alignable rival still gets its comparison", "Aligned Co is in the top three" in h2)
check("the unalignable one is shown but not compared",
      "Elsewhere Co is in the top three" not in h2 and "Elsewhere Co" in h2)
check("and the report says why", "different set of points" in h2)
check("three grids drawn", h2.count("<svg") == 3)

# A third rival is dropped rather than squeezed in.
kw3 = {"keyword": "k", "grid_points": you_pts,
       "rivals": [{"name": f"R{i}", "grid_points": them_pts} for i in range(4)]}
h3 = rr.render_local({"business": "You", "keywords": [kw3]})
check("at most two rivals are drawn", h3.count("<svg") == 3, f"{h3.count('<svg')} svgs")
check("R2 and R3 are not drawn", "R2" not in h3 and "R3" not in h3)

# Degenerate rivals do not break the keyword.
kw4 = {"keyword": "k", "grid_points": you_pts,
       "rivals": [{"name": "No data"}, "not a dict", {"grid_points": []}]}
h4 = rr.render_local({"business": "You", "keywords": [kw4]})
check("a rival with no grid falls back to the single-grid card",
      h4.count("<svg") == 1 and "compare" not in h4)
check("rival names are escaped", "&lt;b&gt;" in rr.render_local(
    {"business": "You", "keywords": [{"keyword": "k", "grid_points": you_pts,
     "rivals": [{"name": "<b>x</b>", "grid_points": them_pts}]}]}))

print()
if fails:
    print(f"{len(fails)} FAILED: " + ", ".join(fails))
    sys.exit(1)
print("All checks passed.")
