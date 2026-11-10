#!/usr/bin/env python3
"""
The audit report renderer and its checker.

What these are really protecting: the report's only claim is that every figure
in it is measured and checkable. A renderer that draws an arc at the wrong
angle, crops a y-axis, or silently invents a section is making a claim the
audit never made — to a reader holding the tool that would catch it.
"""
import importlib.util
import json
import math
import re
import subprocess
import sys
import tempfile
from pathlib import Path
# Resolve the skill from this file, so the suite runs from any checkout.
REPO = Path(__file__).resolve().parent.parent.parent

SKILL = REPO / ".claude/skills/seo-audit-report"


def load(name):
    spec = importlib.util.spec_from_file_location(name, SKILL / "scripts" / f"{name}.py")
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod


render = load("render_report")
check = load("check_audit_report")
speed = load("fetch_speed")

PASS = FAIL = 0
LOG = []


def ok(label, cond, detail=""):
    global PASS, FAIL
    if cond:
        PASS += 1
        LOG.append(f"  OK  {label}")
    else:
        FAIL += 1
        LOG.append(f"FAIL  {label}" + (f"\n        {detail}" if detail else ""))


EXAMPLE = json.loads((SKILL / "assets" / "example-audit.json").read_text())

print("── The arc actually encodes the score ───────────────────────────────")


def arc_sweep(svg):
    """The drawn angle of the fill arc, read back out of the path."""
    # The track is stroked with a CSS variable and the fill with a status hex,
    # so match any stroke and take the second path: the fill.
    paths = re.findall(r'<path d="([^"]+)"[^>]*stroke="[^"]+"', svg)
    if len(paths) < 2:
        return 0.0
    d = paths[1]
    nums = [float(x) for x in re.findall(r"-?\d+\.?\d*", d)]
    x1, y1, r = nums[0], nums[1], nums[2]
    x2, y2 = nums[-2], nums[-1]
    cx = cy = 84.0  # size 168 / 2
    a1 = math.degrees(math.atan2(y1 - cy, x1 - cx))
    a2 = math.degrees(math.atan2(y2 - cy, x2 - cx))
    return (a2 - a1) % 360


for score, expect in ((25, 67.5), (50, 135.0), (75, 202.5), (100, 270.0)):
    got = arc_sweep(render.gauge(score))
    ok(f"a score of {score} draws {expect}° of the 270° dial",
       abs(got - expect) < 1.5, f"drew {got:.1f}°")

ok("a score of 0 draws no fill at all, rather than a misleading sliver",
   render.gauge(0).count("<path") == 1)
ok("a score above 100 is clamped, not drawn past the end",
   abs(arc_sweep(render.gauge(140)) - 270.0) < 1.5)
ok("a missing score renders nothing instead of a zero", render.gauge(None) == "")
ok("a non-numeric score renders nothing", render.gauge("n/a") == "")

print("\n── The gauge's colour follows the score, by one rule ────────────────")
ok("a healthy score is good", render.status_for_score(82) == "good")
ok("a middling score is a warning", render.status_for_score(60) == "warning")
ok("a poor score is serious", render.status_for_score(40) == "serious")
ok("a failing score is critical", render.status_for_score(12) == "critical")
ok("the accessible name states the score, so it is not colour-only",
   'aria-label="SEO Health Score: 41 out of 100"' in render.gauge(41, label="SEO Health Score"))

print("\n── The line chart does not flatter the client ───────────────────────")
chart = render.line_chart(EXAMPLE["competitors"])
ok("it renders", "<svg" in chart)
ok("the y-axis starts at zero, so the gap is the real gap",
   re.search(r'class="tick" text-anchor="end">0<', chart) is not None)
ok("every plotted site is direct-labelled", chart.count('class="endlabel"') == 4)
ok("  → which is what makes the low-contrast slots legal", chart.count('class="endvalue"') == 4)
ok("a legend is present for four series", chart.count('class="legend-item"') == 4)
ok("the figures also ship as a table", "<table>" in chart)
ok("the source is stated, not implied", "Ahrefs" in chart)

many = json.loads(json.dumps(EXAMPLE["competitors"]))
many["series"].append({"name": "fifth.ca", "values": [1] * 12})
many["series"].append({"name": "sixth.ca", "values": [2] * 12})
chart_many = render.line_chart(many)
ok("a fifth and sixth competitor are dropped rather than given a colour nobody can tell apart",
   chart_many.count('class="endlabel"') == 4)
ok("  → and the report says how many were left out",
   "2 further competitors measured but not plotted" in chart_many)

you_first = render.line_chart(EXAMPLE["competitors"])
ok("the client's own line is drawn heavier than the rest",
   you_first.count('class="line emph"') == 1)

gapped = {"labels": ["a", "b", "c"], "metric": "x",
          "series": [{"name": "s.ca", "you": True, "values": [10, None, 30]}]}
g = render.line_chart(gapped)
ok("a month with no data is left out of the line rather than interpolated",
   g.count(",") == 1 or len(re.findall(r"\d+\.\d,\d+\.\d", g)) == 2, g[:200])

ok("a chart with no series renders nothing", render.line_chart({"labels": ["a"], "series": []}) == "")
ok("a chart whose every value is zero renders nothing rather than a flat lie",
   render.line_chart({"labels": ["a", "b"], "series": [{"name": "x", "values": [0, 0]}]}) == "")

print("\n── Labels do not collide or run off the canvas ──────────────────────")
tight = {"labels": ["a", "b"], "metric": "x", "series": [
    {"name": "one.ca", "you": True, "values": [1000, 1000]},
    {"name": "two.ca", "values": [1001, 1001]},
    {"name": "three.ca", "values": [1002, 1002]},
]}
t = render.line_chart(tight)
ys = sorted(float(y) for y in re.findall(r'class="endlabel"[^>]*y="(-?\d+\.?\d*)"', t.replace('\n', '')))
ys = sorted(float(m) for m in re.findall(r'<text x="[\d.]+" y="([\d.]+)" class="endlabel"', t))
gaps = [ys[i] - ys[i - 1] for i in range(1, len(ys))]
ok("three sites within 0.2% of each other still get three readable labels",
   len(ys) == 3 and all(g >= 30 for g in gaps), f"gaps {gaps}")

long_name = {"labels": ["a", "b"], "metric": "x", "series": [
    {"name": "a" * 60 + ".example.com", "you": True, "values": [10, 20]}]}
ln = render.line_chart(long_name)
ok("an absurdly long domain is shortened for the label", "…" in ln)
ok("  → and kept in full in the legend and table",
   ln.count("a" * 60) >= 2)

print("\n── A section with no data is absent, never invented ─────────────────")
minimal = {"client": {"name": "Bare", "domain": "bare.ca"}}
page = render.build(minimal)
ok("a report with only a client still renders", "<html" in page and "Bare" in page)
for absent in ("Scorecard", "Website speed", "Where the traffic is going"):
    ok(f"  → no {absent} section appears", absent not in page)
ok("  → and no score is implied", "out of 100" not in page)

no_bounce = render.build({"client": {"name": "X"}, "speed": {
    "source": "PSI", "score": 50,
    "bounce_rate_unavailable": "Bounce rate is measured in your own Google Analytics."}})
ok("a speed section without GA4 says bounce rate is unavailable",
   "measured in your own Google Analytics" in no_bounce)
ok("  → and shows no bounce-rate figure", "Visitors who leave" not in no_bounce)

with_bounce = render.build({"client": {"name": "X"}, "speed": {
    "source": "PSI", "score": 50, "bounce_rate": {"value": "62%", "source": "GA4"}}})
ok("a speed section with GA4 does show it", "62%" in with_bounce)

print("\n── Nothing a client types can break the page ────────────────────────")
nasty = render.build({"client": {"name": '<script>alert(1)</script>', "domain": 'a"b'},
                      "summary": ["5 < 6 & 7 > 2"]})
ok("a script tag in a client name is escaped, not executed",
   "<script>alert" not in nasty and "&lt;script&gt;" in nasty)
ok("ampersands and angle brackets survive as text", "5 &lt; 6 &amp; 7 &gt; 2" in nasty)

print("\n── The speed fetcher reads PageSpeed honestly ───────────────────────")
payload = {
    "id": "https://x.ca/",
    "lighthouseResult": {
        "fetchTime": "2026-11-09T10:00:00.000Z",
        "categories": {"performance": {"score": 0.34}},
        "audits": {
            "first-contentful-paint": {"displayValue": "3.2 s", "score": 0.21},
            "largest-contentful-paint": {"displayValue": "4.8 s", "score": 0.18},
            "cumulative-layout-shift": {"displayValue": "0.04", "score": 0.95},
            "total-blocking-time": {"displayValue": "890 ms", "score": 0.27},
        },
    },
    "loadingExperience": {"metrics": {"FIRST_CONTENTFUL_PAINT_MS": {}}},
}
built = speed.build(payload, "https://x.ca/", "mobile")
ok("the performance score is scaled to 0-100", built["score"] == 34)
ok("each metric keeps Google's own displayed value", built["metrics"][0]["value"] == "3.2 s")
by_label = {m["label"]: m for m in built["metrics"]}
ok("  → and Google's own banding, not ours",
   by_label["First Contentful Paint"]["status"] == "critical"
   and by_label["Cumulative Layout Shift"]["status"] == "good"
   and by_label["Total Blocking Time"]["status"] == "serious",
   str({k: v["status"] for k, v in by_label.items()}))
ok("a metric Google did not return is left out, not zeroed",
   all(m["label"] != "Speed Index" for m in built["metrics"]))
ok("real-user data is flagged as real-user data", built.get("field_data") is True)
ok("bounce rate is never filled in", "bounce_rate" not in built)
ok("  → but its absence is explained", "Google Analytics" in built["bounce_rate_unavailable"])

lab_only = speed.build({"lighthouseResult": payload["lighthouseResult"]}, "https://x.ca/", "mobile")
ok("a site with no field data is not claimed to have any", "field_data" not in lab_only)

print("\n── Canadian English ─────────────────────────────────────────────────")
slips = dict(check.find_spelling_slips(
    "We optimise and prioritise enquiries about the color of the catalog centre."))
for wrong, right in (("optimise", "optimize"), ("prioritise", "prioritize"),
                     ("enquiries", "inquiries"), ("color", "colour"), ("catalog", "catalogue")):
    ok(f"{wrong} → {right}", slips.get(wrong) == right, f"got {slips.get(wrong)!r}")
ok("centre is already Canadian and is left alone", "centre" not in slips)

clean = check.find_spelling_slips(
    "They advertise, exercise, franchise and surprise. The doctor, author and editor "
    "analyze the water meter and the program. We specialize in inquiries about colour.")
ok("words whose -ise or -or spelling is correct are not flagged", clean == [], str(clean))

cap = dict(check.find_spelling_slips("Enquiries are up. Optimise the page."))
ok("a capitalised slip keeps its capital in the suggestion", cap.get("Enquiries") == "Inquiries")

print("\n── The checker reads the report the client will actually get ────────")
with tempfile.TemporaryDirectory() as tmp:
    tmp = Path(tmp)
    aj = tmp / "a.json"
    aj.write_text(json.dumps(EXAMPLE))
    ah = tmp / "a.html"
    ah.write_text(render.build(EXAMPLE))

    def run(path):
        r = subprocess.run([sys.executable, str(SKILL / "scripts" / "check_audit_report.py"), str(path)],
                           capture_output=True, text=True)
        return r.returncode, r.stdout

    code, out = run(aj)
    ok("the example audit passes as JSON", "PASS" in out and code == 0, out)
    code, out = run(ah)
    ok("and passes again once rendered to HTML", "PASS" in out and code == 0, out)

    bad = json.loads(json.dumps(EXAMPLE))
    bad["sections"][0]["finding"] = "We will optimise this. [NEEDS DATA: keyword volume]"
    bj = tmp / "b.json"
    bj.write_text(json.dumps(bad))
    code, out = run(bj)
    ok("an unresolved placeholder is a hard failure", code == 1 and "FAIL" in out, out)
    ok("  → and the British spelling beside it is reported too", "optimise → optimize" in out, out)

    nosev = json.loads(json.dumps(EXAMPLE))
    nosev["sections"][0]["severity"] = ""
    nj = tmp / "c.json"
    nj.write_text(json.dumps(nosev))
    code, out = run(nj)
    ok("a weakness section with no severity is flagged", "no severity rating" in out, out)

    howto = json.loads(json.dumps(EXAMPLE))
    howto["sections"][0]["teaser"] = (
        "To fix this: export the backlink list, cross-reference each URL, build a "
        "redirect map, implement it, then re-crawl and monitor for six weeks.")
    hj = tmp / "d.json"
    hj.write_text(json.dumps(howto))
    code, out = run(hj)
    ok("a teaser that turned into a how-to is flagged", "full fix" in out, out)

print("\n" + "\n".join(LOG))
print(f"\nPassed: {PASS}  Failed: {FAIL}")
sys.exit(1 if FAIL else 0)
