#!/usr/bin/env python3
"""
The content publish gate.

A gate that can be passed by accident is not a gate. The three things that
have to hold together are: a score at or above the mark, no failed check, and
a named human who read it. This checks that no two of the three are enough,
and that a profile with missing rows cannot produce a flattering score from
half the checks.

    python tools/tests/test_content_gate.py
"""
import subprocess
import sys
import tempfile
from pathlib import Path

REPO = Path(__file__).resolve().parent.parent.parent
SCRIPT = REPO / ".claude/skills/seo-content-production/scripts/check_draft.py"
PROFILE = REPO / ".claude/skills/seo-content-production/assets/nr-lawyers-client-profile.md"
TMP = Path(tempfile.mkdtemp(prefix="content-gate-"))

passed = failed = 0


def check(name, ok, detail=""):
    global passed, failed
    if ok:
        passed += 1
        print(f"PASS  {name}")
    else:
        failed += 1
        print(f"FAIL  {name}" + (f"\n        {detail}" if detail else ""))


META = """Meta Title: Bail Hearing Lawyer Toronto | NR Lawyers
Meta Description: Facing a bail hearing in Toronto? NR Lawyers provides immediate, experienced bail defence across Ontario. Contact us for a confidential case review.
Slug: /general-criminal-defence/bail/
Primary Keyword: bail hearing lawyer Toronto
"""
BODY = "\n".join(
    ["# Bail Hearing Lawyer Toronto", "",
     "A bail hearing is time-critical. See [defence](/criminal-defence/), "
     "[bail](/bail/), [contact](/contact/) and [team](/team/).", ""]
    + [f"## Section {i}\n" + ("Substantive sentence about bail in Ontario. " * 60)
       for i in range(1, 6)]
    + ["## Frequently asked questions"]
    + [f"### Question {i}?\nAnswer {i}." for i in range(1, 8)])


def run(draft, ctype="Service page", approver=None):
    f = TMP / "d.md"
    f.write_text(draft, encoding="utf-8")
    cmd = [sys.executable, str(SCRIPT), str(f), str(PROFILE), ctype]
    if approver:
        cmd.append(f"--approver={approver}")
    r = subprocess.run(cmd, capture_output=True, text=True)
    return r.returncode, r.stdout


good = META + "\n" + BODY

# ── The happy path has to actually work, or the rest proves nothing ───────
code, out = run(good, approver="J Ireland <j@x.ca>")
check("a complete draft with an approver clears", code == 0 and "CLEARED TO PUBLISH" in out,
      out[-700:])
check("and reports a score", "Readiness score:" in out)

# ── Each leg of the gate, removed in turn ─────────────────────────────────
code, out = run(good)
check("the same draft without an approver does NOT clear",
      code == 1 and "NOT CLEARED" in out, out[-400:])
check("and says what is missing", "No approver named" in out)

code, out = run(META + "\n# Short\n\nToo little.", approver="J Ireland")
check("a failing draft does not clear even with an approver",
      code == 1 and "NOT CLEARED" in out)

no_meta = BODY
code, out = run(no_meta, approver="J Ireland")
check("a draft with no meta block does not clear", code == 1)
check("and names the meta block as the problem", "Meta block" in out)

# ── Coverage floor: a high score from few checks is not a pass ────────────
code, out = run(good, ctype="nonexistent_type", approver="J Ireland")
check("an unmatched content type does not clear despite a high score",
      code == 1 and "NOT CLEARED" in out, out[-500:])
check("and says too little could be checked",
      "below the 70 needed" in out or "points of checks could run" in out)

# ── Meta field limits ─────────────────────────────────────────────────────
long_title = good.replace("Meta Title: Bail Hearing Lawyer Toronto | NR Lawyers",
                          "Meta Title: " + "A Very Long Title Indeed " * 4)
code, out = run(long_title, approver="J Ireland")
check("an over-long meta title is caught", "meta title is" in out and code == 1)

short_desc = good.replace(
    "Meta Description: Facing a bail hearing in Toronto? NR Lawyers provides immediate, experienced bail defence across Ontario. Contact us for a confidential case review.",
    "Meta Description: Too short.")
code, out = run(short_desc, approver="J Ireland")
check("an out-of-range meta description is caught",
      "meta description is" in out and code == 1)

# ── The gate never claims to have read the draft ──────────────────────────
code, out = run(good, approver="J Ireland <j@x.ca>")
check("clearing still says the score cannot judge truth or voice",
      "not that it is true" in out and "sounds like the client" in out, out[-400:])
check("and names who is on record for that judgement",
      "J Ireland" in out and "on record" in out)

print()
print(f"Passed: {passed}  Failed: {failed}")
sys.exit(1 if failed else 0)
