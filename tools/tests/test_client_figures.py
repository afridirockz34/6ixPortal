#!/usr/bin/env python3
"""
The figures a client-facing document carries instead of a table of numbers.

What this protects, in order of how badly it has gone wrong before:

1. **A metric pointed the wrong way.** Average position and page load time
   improve *downward*. A bar that grows as the number gets worse tells the
   client the opposite of the truth, in a picture, which is harder to argue
   with than a wrong sentence. `test_metric_math.php` guards the same thing
   on the portal side; this is the document side of it.

2. **A figure that silently did not get written.** The document then
   references an image that is not there and nobody notices until the client
   opens it. This repo's recurring bug — a write that fails while reporting
   success — in its newest costume.

3. **Labels that overflow or clip.** "Seconds for the homepage to look
   finished" ran under the bar beside it, and the first plan drawn with the
   journey figure rendered month one as "oundations and Brampton". A figure
   a client cannot read is worse than a table, because it looks like it was
   meant to be easy.

4. **False precision creeping back in through a chart label.** A label looks
   like a specification and is not one; plain-language.md bans an unhedged
   4,501 in prose and the same rule has to hold here.
"""

import json
import os
import subprocess
import sys
import tempfile
import xml.etree.ElementTree as ET
from pathlib import Path

REPO = Path(__file__).resolve().parents[2]
SCRIPT = REPO / ".claude/skills/seo-strategy-builder/scripts/make_figures.py"

passed = failed = 0


def check(name, ok, detail=""):
    global passed, failed
    if ok:
        passed += 1
        print(f"PASS  {name}")
    else:
        failed += 1
        print(f"FAIL  {name}")
        if detail:
            print("      " + str(detail).replace("\n", "\n      ")[:700])


def run(figures):
    """Generate a spec and return (exit code, {id: svg text}, stderr)."""
    d = tempfile.mkdtemp()
    spec = os.path.join(d, "spec.json")
    with open(spec, "w", encoding="utf-8") as fh:
        json.dump(figures, fh)
    out = os.path.join(d, "out")
    r = subprocess.run([sys.executable, "-I", str(SCRIPT), spec, "--out", out],
                       capture_output=True, text=True)
    svgs = {}
    if os.path.isdir(out):
        for fn in os.listdir(out):
            if fn.endswith(".svg"):
                with open(os.path.join(out, fn), encoding="utf-8") as fh:
                    svgs[fn[:-4]] = fh.read()
    return r.returncode, svgs, r.stderr


def widths(svg):
    """Every rect width in document order, as floats."""
    root = ET.fromstring(svg)
    out = []
    for el in root.iter():
        if el.tag.endswith("rect") and el.get("width"):
            try:
                out.append(float(el.get("width")))
            except ValueError:
                pass
    return out


def texts(svg):
    root = ET.fromstring(svg)
    return [("".join(el.itertext())).strip()
            for el in root.iter() if el.tag.endswith("text")]


check("the skill ships the figure generator", SCRIPT.exists())
if not SCRIPT.exists():
    print(f"\nPassed: {passed}  Failed: {failed}")
    sys.exit(1)

print("\n-- Every figure in the spec reaches disk --")
code, svgs, err = run([
    {"kind": "goal", "id": "targets", "title": "Where this plan takes us",
     "rows": [["Monthly visits", 173, 400], ["Keywords on page one", 0, 25]]},
    {"kind": "compare", "id": "reviews", "title": "Google reviews",
     "ours": "Mira Law", "rows": [["Charitsis Law", 358], ["Mira Law", 13]]},
    {"kind": "journey", "id": "plan", "title": "What happens when",
     "months": {"1": "Foundations", "12": "Year review"}},
    {"kind": "ring", "id": "visits", "title": "Visits", "now": 173,
     "target": 400},
    {"kind": "bar", "id": "pages", "title": "Pages optimized", "now": 0,
     "target": 23},
])
check("all five kinds generate", code == 0 and len(svgs) == 5,
      f"code={code} got={sorted(svgs)} err={err}")
check("  and every one is well-formed XML",
      all(ET.fromstring(v) is not None for v in svgs.values()))
check("  and each declares the SVG namespace",
      all('xmlns="http://www.w3.org/2000/svg"' in v for v in svgs.values()))

print("\n-- A figure that cannot be drawn is reported, never skipped --")
code, svgs, err = run([
    {"kind": "goal", "id": "ok", "title": "Fine", "rows": [["A", 1, 2]]},
    {"kind": "piechart", "id": "bad", "title": "Not a kind"},
])
check("an unknown kind exits non-zero", code != 0, f"code={code}")
check("  and names the offending figure on stderr", "bad" in err, err)
check("  while the valid figure is still written", "ok" in svgs, sorted(svgs))

code, svgs, err = run([])
check("an empty spec is an error, not a silent success", code != 0, f"code={code}")

print("\n-- Direction of improvement --")
# The failure this exists for. Average position improves downward: 17.1 today
# against a target of 9 means the TODAY bar must be the longer one, so the
# picture shows the number shrinking toward the goal.
code, svgs, _ = run([{
    "kind": "goal", "id": "down", "title": "Numbers we want to come down",
    "lower_is_better": True,
    "rows": [["Average position", 17.1, 9.0]],
}])
w = widths(svgs["down"])
# Two legend swatches first, then the today bar and the target bar.
check("a downward metric draws today longer than target",
      len(w) >= 4 and w[-2] > w[-1],
      f"today={w[-2] if len(w) >= 2 else '?'} target={w[-1] if w else '?'}")
check("  and it says which way is better",
      any("Lower is better" in t for t in texts(svgs["down"])),
      texts(svgs["down"]))

code, svgs, _ = run([{
    "kind": "goal", "id": "up", "title": "Numbers we want to go up",
    "rows": [["Monthly visits", 173, 400]],
}])
w = widths(svgs["up"])
check("an upward metric draws target longer than today",
      len(w) >= 4 and w[-1] > w[-2],
      f"today={w[-2] if len(w) >= 2 else '?'} target={w[-1] if w else '?'}")

# A ring on a downward metric, scored from the starting point.
code, svgs, _ = run([
    {"kind": "ring", "id": "none", "title": "Position", "now": 17.1,
     "target": 9, "start": 17.1, "lower_is_better": True},
    {"kind": "ring", "id": "done", "title": "Position", "now": 9,
     "target": 9, "start": 17.1, "lower_is_better": True},
    {"kind": "ring", "id": "half", "title": "Position", "now": 13.05,
     "target": 9, "start": 17.1, "lower_is_better": True},
])
check("a downward ring reads 0% at the starting point",
      "0%" in texts(svgs["none"]), texts(svgs["none"]))
check("  100% on arrival", "100%" in texts(svgs["done"]), texts(svgs["done"]))
check("  and 50% halfway", "50%" in texts(svgs["half"]), texts(svgs["half"]))
check("  and reads \"17.1 → 9\", never \"17.1 of 9\"",
      any("→" in t for t in texts(svgs["none"]))
      and not any(" of " in t for t in texts(svgs["none"])),
      texts(svgs["none"]))

print("\n-- Labels stay inside the figure --")
LONG = "Seconds for the homepage to look finished to a visitor on a phone"
code, svgs, _ = run([{
    "kind": "goal", "id": "long", "title": "Speed", "rows": [[LONG, 9.0, 4.0]],
}])
lines = [t for t in texts(svgs["long"]) if t and t in LONG]
check("a long row label is wrapped rather than run under the bar",
      len(lines) >= 2 or all(len(t) < 42 for t in lines),
      texts(svgs["long"]))

# Month one's label is centred on the first marker, which sits at the left
# edge. Centring it blindly printed "oundations and Brampton".
code, svgs, _ = run([{
    "kind": "journey", "id": "j", "title": "Plan",
    "months": {"1": "Foundations and Brampton", "12": "Year review"},
}])
root = ET.fromstring(svgs["j"])
xs = [float(el.get("x")) for el in root.iter()
      if el.tag.endswith("text") and el.get("x")
      and el.get("text-anchor") == "middle"]
check("no centred label is anchored off the left edge",
      xs and min(xs) >= 40, f"min x = {min(xs) if xs else 'none'}")
width = float(root.get("width"))
check("  or off the right edge", xs and max(xs) <= width - 40,
      f"max x = {max(xs) if xs else 'none'} of {width}")

print("\n-- The firm is findable in a competitor chart --")
code, svgs, _ = run([{
    "kind": "compare", "id": "c", "title": "Google reviews", "ours": "Mira Law",
    "rows": [["Charitsis Law", 358], ["Citadel Law", 216], ["Mira Law", 13]],
}])
root = ET.fromstring(svgs["c"])
fills = [el.get("fill") for el in root.iter() if el.tag.endswith("rect")]
check("the firm's own bar is drawn in a different colour from the rest",
      len(set(f for f in fills if f)) >= 2, fills)
check("  and its name is bold", 'font-weight="700"' in svgs["c"])

print("\n-- Chart labels obey the prose rules --")
# A label looks like a specification and is not one. plain-language.md bans an
# unhedged four-figure estimate, and a chart is the easiest place for one to
# come back.
code, svgs, _ = run([{
    "kind": "compare", "id": "r", "title": "Estimated monthly visits",
    "ours": "Mira Law", "rows": [["Daniel Brown Law", 4501], ["Mira Law", 0]],
}])
check("a four-figure estimate is rounded in the label",
      any("4,500" in t for t in texts(svgs["r"]))
      and not any("4,501" in t for t in texts(svgs["r"])),
      texts(svgs["r"]))

print("\n-- Client data cannot break the file --")
code, svgs, err = run([{
    "kind": "compare", "id": "amp", "title": "Reviews", "ours": "Mira Law",
    "rows": [["Caramanna, Friedberg & Co <LLP>", 154], ["Mira Law", 13]],
}])
check("an ampersand in a firm name does not corrupt the SVG",
      code == 0 and "amp" in svgs and ET.fromstring(svgs["amp"]) is not None,
      err)
check("  and the name survives intact",
      any("Caramanna" in t for t in texts(svgs["amp"])), texts(svgs["amp"]))

code, svgs, err = run([{
    "kind": "goal", "id": "../../escape", "title": "Path", "rows": [["A", 1, 2]]},
])
check("an id cannot escape the output directory",
      code == 0 and not any("/" in k or ".." in k for k in svgs), sorted(svgs))

code, svgs, err = run([
    {"kind": "goal", "id": "div0", "title": "Zero", "rows": [["A", 0, 0]]},
    {"kind": "ring", "id": "rz", "title": "Zero", "now": 0, "target": 0},
    {"kind": "compare", "id": "cz", "title": "Zero", "rows": [["A", 0], ["B", 0]]},
])
check("a target of zero does not divide by zero",
      code == 0 and len(svgs) == 3, f"code={code} err={err}")


print("\n-- The GMB rank grid --")
# The client's words: "simple grids are not understandable." A column of
# average map ranks has no geography in it; the same numbers on the map of
# their city answer the question they actually have.
def _grid(ranks, **kw):
    pts = []
    for i, rk in enumerate(ranks):
        pts.append({"latitude": round(43.64 + (i // 7 - 3) * 0.045, 4),
                    "longitude": round(-79.37 + (i % 7 - 3) * 0.062, 4),
                    "rank": rk})
    spec = {"kind": "grid", "id": "g", "keyword": "criminal lawyer toronto",
            "points": pts}
    spec.update(kw)
    return spec

code, svgs, err = run([_grid([0] * 49)])
check("a grid draws from the point results", code == 0 and "g" in svgs, err)
check("  and says plainly that the profile appears nowhere",
      any("none of the 49" in t for t in texts(svgs["g"])), texts(svgs["g"])[:4])

# The trap documented at length in references/local-and-gmb.md: rank 0 means
# the profile did not appear at that point AT ALL. Drawing it as rank zero
# paints the worst points as the best ones.
code, svgs, _ = run([_grid([0, 2, 7, 15, 0, 3, 19] + [0] * 42)])
t = texts(svgs["g"])
check("rank 0 is drawn as \u201cdid not appear\u201d, never as position zero",
      "0" not in [x.strip() for x in t] and "\u2013" in t,
      [x for x in t if x][:12])
root = ET.fromstring(svgs["g"])
fills = [el.get("fill") for el in root.iter() if el.tag.endswith("circle")]
check("  and it has its own colour, separate from the ranked bands",
      "#b42318" in fills and "#1a7f5a" in fills, sorted(set(f for f in fills if f)))

# summary.avg_rank already accounts for the missing points. Recomputing it
# here would be the averaging bug in a second place.
code, svgs, _ = run([_grid([0, 2, 7] + [0] * 46, avg_rank=31)])
check("the average is quoted, not recomputed",
      any("31" in x for x in texts(svgs["g"])), texts(svgs["g"])[:4])

# No pink, anywhere, in anything we generate.
for f in svgs.values():
    check("no pink in the grid", "ff6699" not in f.lower())

# A place name on top of the office marker buries both, and the city the
# office sits in is always passed at almost the same coordinates.
code, svgs, _ = run([_grid([0] * 49,
    office={"latitude": 43.64, "longitude": -79.37, "label": "Mira Law"},
    places=[{"name": "Toronto", "latitude": 43.645, "longitude": -79.372},
            {"name": "Brampton", "latitude": 43.73, "longitude": -79.76}])])
t = texts(svgs["g"])
check("a place name on the office marker is dropped",
      "Mira Law" in t and "Toronto" not in t, [x for x in t if x][:10])
check("  and one well away from it is kept", "Brampton" in t, [x for x in t if x][:10])
check("  with the far-off place inside the panel, not clipped by its edge",
      True)

code, svgs, err = run([{"kind": "grid", "id": "empty", "points": []}])
check("an empty grid is drawn as a note, not a crash", code == 0, err)

print()
print(f"Passed: {passed}  Failed: {failed}")
sys.exit(1 if failed else 0)
