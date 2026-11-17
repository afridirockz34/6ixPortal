#!/usr/bin/env python3
"""
The keyword / topic / GMB set checker.

The deliverable this guards is the one the client said they could not read:
a hundred keywords "all over the place" instead of ten named clusters. So
what matters here is that the checker objects to the pile and accepts the
plan — and, in particular, that it accepts a *real* well-formed set rather
than only the idealised one in the reference file. Every vocabulary in
keyword-strategy.md was widened at least once because it rejected a set that
was actually fine; these tests hold those widenings in place.

    python tools/tests/test_keyword_set.py
"""
import subprocess
import sys
import tempfile
from pathlib import Path

REPO = Path(__file__).resolve().parent.parent.parent
SCRIPT = REPO / ".claude/skills/seo-strategy-builder/scripts/check_keyword_set.py"
TMP = Path(tempfile.mkdtemp(prefix="keyword-set-"))

passed = failed = 0


def check(name, ok, detail=""):
    global passed, failed
    if ok:
        passed += 1
        print(f"PASS  {name}")
    else:
        failed += 1
        print(f"FAIL  {name}" + (f"\n        {detail}" if detail else ""))


def run(text, kind="website", name="set.csv"):
    p = TMP / name
    p.write_text(text, encoding="utf-8")
    r = subprocess.run([sys.executable, str(SCRIPT), str(p), "--kind", kind],
                       capture_output=True, text=True)
    return r.returncode, r.stdout + r.stderr


# ── Builders ──────────────────────────────────────────────────────────────
WEBSITE_HEADER = "#,Keyword,Intent,Est. MSV,Difficulty,Priority,Target Page"
TOPIC_HEADER = "#,Blog Title,Target Keyword,Funnel Stage,Angle,Priority"
GMB_HEADER = "#,Keyword,Est. MSV,Local Priority,GMB Placement"

# Ten plausible clusters, ten rows each, in the spreadsheet shape these sets
# actually arrive in: a bare cluster name, then the header, then the rows.
CLUSTERS = ["Core Criminal Defence", "Sexual Assault Defence",
            "Domestic Assault Defence", "Bail Hearings & Urgent Matters",
            "Location Pages - GTA Boroughs", "Location Pages - Ontario Cities",
            "Other Practice Areas", "Informational - What To Do If Charged",
            "Commercial Intent - Comparison & Hire",
            "Authority & Long-Tail"]


def website_set(clusters=CLUSTERS, per=10, priority_cycle=("1", "1", "2", "2", "3")):
    out, n = [], 0
    for ci, c in enumerate(clusters):
        out.append(c)
        out.append(WEBSITE_HEADER)
        for i in range(per):
            n += 1
            out.append(f"{n},keyword {ci}-{i} toronto,Commercial,{200 + n * 10},"
                       f"Med,{priority_cycle[i % len(priority_cycle)]},Service Page")
    return "\n".join(out) + "\n"


# Angles spread so no cluster leans on one — the normal, passing case.
SPREAD = ["Emergency Scenario", "Beginner's Guide", "Problem-Solution",
          "Comparison", "Myth-Busting", "Case Study", "Cost-Focused",
          "Checklist", "Local Spotlight", "Trend Analysis"]


def topic_set(clusters=CLUSTERS, per=10, angles=None):
    out, n = [], 0
    for ci, c in enumerate(clusters):
        out.append(c)
        out.append(TOPIC_HEADER)
        for i in range(per):
            n += 1
            angle = (angles or SPREAD)[i % len(angles or SPREAD)]
            out.append(f"{n},What To Do About Thing {n} in Ontario,"
                       f"target keyword {n},Top,{angle},{(i % 3) + 1}")
    return "\n".join(out) + "\n"


def gmb_set(per=10):
    out, n = [], 0
    for ci, c in enumerate(["Core Local", "Near Me", "Boroughs", "Urgent"]):
        out.append(c)
        out.append(GMB_HEADER)
        for i in range(per):
            n += 1
            kw = "criminal lawyer near me" if (ci == 1 and i == 0) else f"local term {n}"
            out.append(f"{n},{kw},{100 + n * 5},High,\"Services list, Q&A\"")
    return "\n".join(out) + "\n"


# ── A well-formed set passes ──────────────────────────────────────────────
code, out = run(website_set())
check("a well-formed 100-keyword set in 10 clusters passes",
      code == 0 and "well-formed" in out, out[-500:])

code, out = run(topic_set(), kind="topics")
check("a well-formed 100-topic set passes", code == 0, out[-600:])

code, out = run(gmb_set(), kind="gmb")
check("a well-formed 40-keyword GMB set passes", code == 0, out[-500:])

# ── The pile: no clusters at all ──────────────────────────────────────────
flat = WEBSITE_HEADER + "\n" + "\n".join(
    f"{i},keyword {i},Commercial,500,Med,1,Service Page" for i in range(1, 101)) + "\n"
code, out = run(flat)
check("a flat 100-row dump with no clusters fails",
      code == 1 and ("no cluster" in out or "cluster" in out), out[-500:])

# ── Every keyword needs a target page ─────────────────────────────────────
no_target = website_set().replace(",Service Page", ",", 1)
code, out = run(no_target)
check("a keyword with no target page fails",
      code == 1 and "Target Page" in out, out[-400:])

# ── Controlled vocabularies ───────────────────────────────────────────────
code, out = run(website_set().replace(",Commercial,", ",Buying Intent,", 1))
check("an intent outside the vocabulary fails",
      code == 1 and "Intent 'Buying Intent'" in out, out[-400:])

code, out = run(website_set().replace(",Med,1,", ",KD 43,1,", 1))
check("a raw difficulty number instead of High/Med/Low fails",
      code == 1 and "Difficulty 'KD 43'" in out, out[-400:])

bad_angle = topic_set().replace(",Emergency Scenario,", ",Listicle,", 1)
code, out = run(bad_angle, kind="topics")
check("an angle outside the vocabulary fails",
      code == 1 and "Angle 'Listicle'" in out, out[-400:])

# Info/Comm is legitimate — a term genuinely across two intents.
code, out = run(website_set().replace(",Commercial,", ",Info/Comm,", 1))
check("Info/Comm is accepted as a real split intent", code == 0, out[-400:])

# ── Priority ──────────────────────────────────────────────────────────────
all_p1 = website_set(priority_cycle=("1",))
code, out = run(all_p1)
check("everything marked Priority 1 is warned about",
      "Priority 1" in out and "nothing is" in out, out[-500:])

no_p1 = website_set(priority_cycle=("2", "3"))
code, out = run(no_p1)
check("a set with no Priority 1 at all fails",
      code == 1 and "Nothing is scheduled to be built first" in out, out[-400:])

# ── Volume honesty ────────────────────────────────────────────────────────
code, out = run(website_set().replace(",210,", ",~500,", 1))
check("a volume written as an estimate is warned about",
      "not a number or an explicit gap" in out, out[-400:])

code, out = run(website_set().replace(",210,", ",—,", 1))
check("an em dash for a genuinely missing volume is accepted",
      "not a number" not in out, out[-400:])

# ── Duplicates ────────────────────────────────────────────────────────────
dupe = website_set().replace("keyword 0-1 toronto", "keyword 0-0 toronto", 1)
code, out = run(dupe)
check("the same keyword twice fails",
      code == 1 and "appears 2 times" in out, out[-400:])

# ── Angle monotony, and the exemption that keeps it honest ────────────────
mono = topic_set(angles=["Problem-Solution"])
code, out = run(mono, kind="topics")
check("one angle across every cluster fails",
      code == 1 and "padding" in out, out[-600:])

# A cluster NAMED for an angle is supposed to use it throughout. This is the
# false positive that fired on Proximity's real set, and it must stay fixed.
named = []
named.append("Myth-Busting & Common Misconceptions")
named.append(TOPIC_HEADER)
for i in range(1, 11):
    named.append(f"{i},Myth {i} About Criminal Charges,myth keyword {i},Top,"
                 f"Myth-Busting,1")
for ci, c in enumerate(CLUSTERS[:5]):
    named.append(c)
    named.append(TOPIC_HEADER)
    for i in range(10):
        n = 100 + ci * 10 + i
        named.append(f"{n},Topic {n} Explained,other keyword {n},Middle,"
                     f"{SPREAD[i % len(SPREAD)]},2")
code, out = run("\n".join(named) + "\n", kind="topics")
check("a cluster named for its angle is not flagged for using it",
      "Myth-Busting & Common Misconceptions" not in out, out[-600:])

# ── An unfinished set is distinguished from a genuinely small market ──────
code, out = run(website_set(clusters=CLUSTERS[:3], per=5))
check("15 rows against a target of 100 fails as unfinished",
      code == 1 and "unfinished one" in out, out[-400:])

code, out = run(website_set(clusters=CLUSTERS[:6], per=10))
check("60 rows is a note about market size, not a failure",
      code == 0 and "market really" in out, out[-500:])

# ── Cannibalisation ───────────────────────────────────────────────────────
cannib = topic_set().replace("target keyword 2,", "target keyword 1,", 1)
code, out = run(cannib, kind="topics")
check("two topics targeting one keyword is warned as cannibalisation",
      "cannibalisation" in out, out[-500:])

# ── GMB-specific ──────────────────────────────────────────────────────────
no_near = gmb_set().replace("criminal lawyer near me", "criminal lawyer toronto")
code, out = run(no_near, kind="gmb")
check("a GMB set with no 'near me' terms is warned about",
      "near me" in out, out[-400:])

code, out = run(gmb_set().replace(",High,", ",1,", 1), kind="gmb")
check("a numeric priority in a GMB set fails",
      code == 1 and "Local priority '1'" in out, out[-400:])

# ── Operating the script itself ───────────────────────────────────────────
r = subprocess.run([sys.executable, str(SCRIPT)], capture_output=True, text=True)
check("no arguments prints usage instead of a traceback",
      r.returncode == 0 and "check_keyword_set.py" in r.stdout, r.stdout[:200])

r = subprocess.run([sys.executable, str(SCRIPT), str(TMP / "nope.csv")],
                   capture_output=True, text=True)
check("a missing file is reported, not raised",
      r.returncode == 2 and "No such file" in r.stderr, r.stderr[:200])

# JSON, keyed by cluster, is the other shape these arrive in.
json_set = ('{"Core Criminal Defence": [' + ",".join(
    '{"Keyword": "kw %d", "Intent": "Commercial", "Est. MSV": "300", '
    '"Difficulty": "Med", "Priority": "1", "Target Page": "Service Page"}' % i
    for i in range(1, 41)) + '], "Bail Hearings": [' + ",".join(
    '{"Keyword": "bail kw %d", "Intent": "Transactional", "Est. MSV": "120", '
    '"Difficulty": "High", "Priority": "2", "Target Page": "Service Page"}' % i
    for i in range(1, 31)) + '], "Locations": [' + ",".join(
    '{"Keyword": "loc kw %d", "Intent": "Commercial", "Est. MSV": "90", '
    '"Difficulty": "Low", "Priority": "3", "Target Page": "Location Page"}' % i
    for i in range(1, 31)) + ']}')
code, out = run(json_set, name="set.json")
check("a JSON set keyed by cluster is read and passes",
      code == 0 and "3 clusters" in out, out[-500:])

print()
print(f"Passed: {passed}  Failed: {failed}")
sys.exit(1 if failed else 0)
