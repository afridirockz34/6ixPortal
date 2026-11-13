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
# The service-page section contract from references/content-templates.md. The
# headings are the contract; the prose is filler to reach the word count.
FILL = "Substantive sentence about bail hearings in Ontario. " * 42
SERVICE_SECTIONS = [
    "Who Needs a Bail Hearing Lawyer in Toronto",
    "What NR Lawyers Provides in Bail Hearing Representation",
    "How the Bail Hearing Process Works in Ontario",
    "What Experienced Bail Representation Delivers",
    "Why Individuals Charged in Toronto Choose NR Lawyers",
    "Related Criminal Defence Services",
    "Bail Hearing Representation Across Toronto and Ontario",
]
BODY = "\n".join(
    ["# Bail Hearing Lawyer Toronto", "",
     "A bail hearing is time-critical. See [defence](/criminal-defence/), "
     "[bail](/bail/), [contact](/contact/) and [team](/team/).", ""]
    + [f"## {h}\n{FILL}" for h in SERVICE_SECTIONS]
    + ["## Frequently Asked Questions"]
    + [f"### Question {i}?\nAnswer {i}." for i in range(1, 8)]
    + ["## Request an Immediate Confidential Consultation\nCall us today.",
       "## Key Takeaways\n- A bail hearing decides release pending trial.",
       "## Schema Recommendations\n- Service schema: bail hearing representation."])


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

# ── The section contract ──────────────────────────────────────────────────
# A page set reads as one body of work only if every page of a type answers the
# same questions in the same order. Absence of a required section is a failure;
# writing it in the wrong place is a review note.
no_who = good.replace("## Who Needs a Bail Hearing Lawyer in Toronto", "## Overview")
code, out = run(no_who, approver="J Ireland")
check("a service page missing 'who needs this' fails the contract",
      code == 1 and "Section contract" in out and "Who needs this" in out, out[-700:])

no_provides = good.replace(
    "## What NR Lawyers Provides in Bail Hearing Representation", "## More Detail")
code, out = run(no_provides, approver="J Ireland")
check("and one missing 'what the client provides' fails too",
      code == 1 and "What the client provides" in out, out[-600:])

code, out = run(good, approver="J Ireland")
check("the full contract reports as satisfied",
      "every required section present" in out, out[-900:])

# Reordering is flagged but is not on its own a failure.
reordered = good.replace(
    "## Who Needs a Bail Hearing Lawyer in Toronto", "## ZZPLACEHOLDER").replace(
    "## Why Individuals Charged in Toronto Choose NR Lawyers",
    "## Who Needs a Bail Hearing Lawyer in Toronto").replace(
    "## ZZPLACEHOLDER", "## Why Individuals Charged in Toronto Choose NR Lawyers")
code, out = run(reordered, approver="J Ireland")
check("sections written out of contract order are flagged, not failed",
      "Out of contract order" in out and code == 0, out[-700:])

# A blog post has a much looser contract — only the FAQ is required — so the
# same strictness must not be applied to it.
blog = (META.replace("Primary Keyword: bail hearing lawyer Toronto",
                     "Primary Keyword: how does a bail hearing work ontario")
        + "\n# How does a bail hearing work in Ontario?\n\n"
        + "See [bail](/bail/), [rights](/rights/), [contact](/contact/).\n\n"
        + "\n".join(f"## Topical question {i}?\n{FILL}" for i in range(1, 5))
        + "\n## Frequently Asked Questions\n"
        + "\n".join(f"### Q{i}?\nA{i}." for i in range(1, 6))
        + "\n## Key Takeaways\n- A bail hearing happens within 24 hours.\n"
        + "## Schema Recommendations\n- Article schema with author.\n")
code, out = run(blog, ctype="Blog post", approver="J Ireland")
check("a blog post is not held to the service-page contract",
      code == 0 and "CLEARED" in out, out[-800:])

# ── The universal blocks ──────────────────────────────────────────────────
no_takeaways = good.replace("## Key Takeaways", "## Summary Notes")
code, out = run(no_takeaways, approver="J Ireland")
check("a page with no Key Takeaways fails",
      code == 1 and "Key Takeaways" in out, out[-600:])

no_schema = good.replace("## Schema Recommendations", "## Notes For The Developer")
code, out = run(no_schema, approver="J Ireland")
check("a page with no Schema Recommendations fails",
      code == 1 and "Schema Recommendations" in out, out[-600:])

# ── [H2] tag style is understood as well as markdown ─────────────────────
tagged = good.replace("\n## ", "\n[H2] ").replace("\n### ", "\n[H3] ").replace(
    "\n# Bail Hearing Lawyer Toronto", "\n[H1] Bail Hearing Lawyer Toronto")
code, out = run(tagged, approver="J Ireland")
check("headings written as [H2] are read as headings",
      "every required section present" in out, out[-900:])

# ── Unresolved markers ───────────────────────────────────────────────────
# These are correct in a draft and a failure in a finished page. The claims
# policy's standing rule puts unsourced figures here rather than inventing one,
# so nothing may reach a page by being forgotten.
for marker, label in (("[VERIFY: volume-of-results claim]", "VERIFY"),
                      ("[NEEDS CLIENT INPUT: which court serves Weston]", "NEEDS CLIENT INPUT")):
    code, out = run(good.replace("Call us today.", f"Call us today. {marker}"),
                    approver="J Ireland")
    check(f"an unresolved {label} marker blocks publication",
          code == 1 and "unresolved marker" in out, out[-600:])

code, out = run(good, approver="J Ireland")
check("and a clean page says so", "No unresolved" in out)

# ── Two bugs that only showed up against real delivered pages ────────────
# Both were found by running all 72 of the agency's documents through this
# gate. Each one marked good work down for the checker's convenience.

# 1. FAQ questions written as H2 rather than H3. A page with six questions has
#    six questions whichever level they were written at.
faq_as_h2 = good
for i in range(1, 8):
    faq_as_h2 = faq_as_h2.replace(f"### Question {i}?", f"## Question {i}?")
code, out = run(faq_as_h2, approver="J Ireland")
check("FAQ questions written as H2 are still counted",
      "FAQ questions detected: 7" in out and code == 0, out[:900])

# 2. An H3 sub-step inside a section is not a section. "Step 1: Immediate
#    First Contact" must not match the call-to-action pattern and report the
#    page out of order.
with_steps = good.replace(
    "## How the Bail Hearing Process Works in Ontario",
    "## How the Bail Hearing Process Works in Ontario\n"
    "### Step 1: Immediate First Contact\nCall the firm.\n"
    "### Step 2: Disclosure and Bail Plan Preparation\nReview disclosure.\n"
    "### Step 3: Representation at the Bail Hearing\nAppear in court.\n"
    "### Step 4: Post-Release Guidance\nAdvise on conditions.")
code, out = run(with_steps, approver="J Ireland")
check("an H3 sub-step is not mistaken for a top-level section",
      "Out of contract order" not in out and code == 0, out[-900:])

# ── The gate never claims to have read the draft ──────────────────────────
code, out = run(good, approver="J Ireland <j@x.ca>")
check("clearing still says the score cannot judge truth or voice",
      "not that it is true" in out and "sounds like the client" in out, out[-400:])
check("and names who is on record for that judgement",
      "J Ireland" in out and "on record" in out)

print()
print(f"Passed: {passed}  Failed: {failed}")
sys.exit(1 if failed else 0)
