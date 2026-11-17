#!/usr/bin/env python3
"""
The strategy document's readability gate.

The complaint this answers is "I read the strategy document three times and
still could not understand it." Nothing in it was false; it was built out of
hedges, actions buried in nouns, and jargon used before it was explained.

Two properties matter and pull against each other:

  * prose written the way references/plain-language.md asks must PASS — a gate
    that fails good writing gets switched off within a week;
  * a document built out of the six failure patterns must FAIL, not merely
    emit a warning nobody reads.

Which is why these are counted against thresholds rather than failed on first
sight: one "consider" is a sentence, fifteen is the document the client could
not read.

    python tools/tests/test_strategy_language.py
"""
import subprocess
import sys
import tempfile
from pathlib import Path

REPO = Path(__file__).resolve().parent.parent.parent
SCRIPT = REPO / ".claude/skills/seo-strategy-builder/scripts/check_strategy_doc.py"
WORKED = REPO / ".claude/skills/seo-strategy-builder/assets/nr-lawyers-foundation-document.md"
TMP = Path(tempfile.mkdtemp(prefix="strategy-lang-"))

passed = failed = 0


def check(name, ok, detail=""):
    global passed, failed
    if ok:
        passed += 1
        print(f"PASS  {name}")
    else:
        failed += 1
        print(f"FAIL  {name}" + (f"\n        {detail}" if detail else ""))


def run(text, *extra, name="doc.md"):
    p = TMP / name
    p.write_text(text, encoding="utf-8")
    r = subprocess.run([sys.executable, str(SCRIPT), str(p), *extra],
                       capture_output=True, text=True)
    return r.returncode, r.stdout + r.stderr


# ── Prose written the way the house style asks ───────────────────────────
GOOD = """# Phase 5 — SEO strategy

## Three firms own the searches you are not on

Your Domain Rating — Ahrefs' 0-100 estimate of how many other sites link to
you — is 4. The three firms you named sit at 22, 31 and 38.

We write six new pages about bail hearings, one for each stage of the process.
Right now you have one. Those three firms have between four and nine each.

That gap is why a search for any part of a bail hearing reaches them and not
you. Six pages closes it.

## 2,900 monthly searches, none of them reaching you

Search Console measured 31,400 impressions — the number of times you appeared
in a Google result, whether or not anybody clicked — over the 28 days to
4 November. Clicks were 412.

We rewrite the nine service pages in month one. Your developer needs to give
us WordPress editor access first. That is the one thing we need from you.
"""

code, out = run(GOOD)
check("prose written the way plain-language.md asks passes",
      code == 0, out[-800:])
check("and reports each language group as clean",
      out.count("[OK]") >= 5, out[-800:])

# The worked example in the skill is the real-world version of the same test.
# Its [NEEDS DATA] markers are deliberate, so only the language part is judged.
r = subprocess.run([sys.executable, str(SCRIPT), str(WORKED)],
                   capture_output=True, text=True)
body = r.stdout
check("the bundled worked example passes every language check",
      "[FAIL] Hedged" not in body and "[FAIL] Action buried" not in body
      and "[FAIL] Sentences over" not in body
      and "[FAIL] Terms used before" not in body, body[-900:])

# ── Each failure pattern, past its threshold ─────────────────────────────
HEDGED = "# Phase 5\n\n" + "\n\n".join(
    [f"Consider improving the {w} where possible." for w in
     ("homepage", "service pages", "blog", "footer", "sitemap", "schema")]
    + ["It is recommended that review velocity be increased.",
       "Page speed should be explored as needed.",
       "This may help if applicable."])
code, out = run(HEDGED)
check("a document built out of hedges fails",
      code == 1 and "[FAIL] Hedged non-commitment" in out, out[-700:])

BURIED = "# Phase 5\n\n" + "\n\n".join([
    "Implementation of schema markup across the site.",
    "Optimization of the existing service pages.",
    "Creation of twelve new location pages.",
    "Development of the internal linking plan.",
    "Utilization of the existing content inventory.",
    "Establishment of a review programme.",
    "Enhancement of the profile description.",
    "Deployment of the tracking configuration.",
    "We will leverage the existing authority to facilitate a robust strategy.",
    "The pages will be optimized and the markup will be deployed.",
])
code, out = run(BURIED)
check("a document whose actions are all buried in nouns fails",
      code == 1 and "[FAIL] Action buried" in out, out[-700:])

LONG = "# Phase 5\n\n" + "\n\n".join(
    ["The " + "very " * 20 + "long sentence about the client's organic search "
     "performance continues past every reasonable point and keeps adding "
     "clauses until the reader has lost the subject entirely and gives up."] * 6)
code, out = run(LONG)
check("a document of 45-word sentences fails",
      code == 1 and "[FAIL] Sentences over 45 words" in out, out[-700:])

JARGON = """# Phase 5

Their Domain Rating sits at 4.

Impressions fell last period.

Anchor text needs work.

Core Web Vitals look poor.

Crawl budget gets wasted.
"""
code, out = run(JARGON)
check("terms used before they are explained fails",
      code == 1 and "[FAIL] Terms used before being explained" in out, out[-700:])

# ── The thresholds hold: a few is fine, a document of them is not ────────
MILD = GOOD + "\n\nConsider improving the footer where possible.\n"
code, out = run(MILD)
check("one hedge in otherwise good prose does not fail the document",
      code == 0 and "[REVIEW] Hedged non-commitment: 2" in out, out[-700:])

# ── A gloss in the same sentence is what clears a term ──────────────────
code, out = run("# Phase 5\n\nYour Domain Rating — Ahrefs' estimate of how many "
                "sites link to you — is 4.\n\nLater we raise the Domain Rating.\n")
check("a term explained on first use is clear, including its later bare uses",
      "Every tracked term is explained" in out and "Domain Rating:" not in out,
      out[-600:])

# The mirror of that: only the FIRST use is judged, so an unexplained first
# use is still caught when later uses happen to sit next to a dash.
code, out = run("# Phase 5\n\nTheir crawl budget gets wasted.\n\nCrawl budget — "
                "how many pages Google will fetch per visit — matters here.\n")
check("explaining a term only after using it bare is still a finding",
      "crawl budget" in out.lower() and "Terms used before being explained" in out,
      out[-600:])

# ── Tables and code blocks are not prose ────────────────────────────────
TABLED = GOOD + """
## The keywords

| # | Keyword | Intent | Est. MSV | Difficulty | Priority | Target Page |
|---|---|---|---|---|---|---|
| 1 | criminal lawyer toronto | Commercial | 2,400 | High | 1 | Service Page |
| 2 | bail hearing lawyer | Transactional | 590 | High | 1 | Service Page |

```
Implementation of schema. Consider improving things where possible.
Utilization of various best practices as needed, if applicable.
```
"""
code, out = run(TABLED)
check("a code block's text is not judged as the document's prose",
      code == 0, out[-800:])
check("and a terse table row is not read as a figure with no period",
      "2,400" not in out, out[-800:])

# ── A placeholder is not a jargon or figure finding ──────────────────────
code, out = run(GOOD.replace("Clicks were 412.", "Clicks were [NEEDS DATA: GSC clicks]."))
check("an unresolved placeholder fails on its own terms, not the language ones",
      code == 1 and "unresolved placeholder" in out
      and "[FAIL] Hedged" not in out, out[-700:])

# ── The escape hatch, and the thing it must not quietly do ──────────────
code, out = run(HEDGED, "--no-language")
check("--no-language skips the readability pass entirely",
      code == 0 and "Hedged non-commitment" not in out, out[-600:])

code, out = run(GOOD.replace("Clicks were 412.", "Clicks were [NEEDS DATA: x]."),
                "--no-language")
check("but it never skips the placeholder check",
      code == 1 and "unresolved placeholder" in out, out[-500:])

# ── Operating the script ────────────────────────────────────────────────
r = subprocess.run([sys.executable, str(SCRIPT), "--help"],
                   capture_output=True, text=True)
check("--help documents the readability pass and its escape hatch",
      "--no-language" in r.stdout and "LANGUAGE_LIMITS" in r.stdout, r.stdout[:300])

code, out = run(GOOD, "--phases", "5,7")
check("phase coverage still reports alongside the language pass",
      "Phase headings detected" in out and "no detected heading" in out, out[-600:])

print()
print(f"Passed: {passed}  Failed: {failed}")
sys.exit(1 if failed else 0)
