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
#
# Rewritten in November 2026 to the voice the skill now specifies: "we" for
# the work, the firm's name for facts, "you" only for what is genuinely
# theirs. The old version said "you" or "your" eleven times in 140 words and
# named the firm never — which is the document the client said was talking at
# him, and a positive fixture has to demonstrate the standard it is testing.
GOOD = """# Phase 5 — SEO strategy

## Six pages that close the bail-hearing gap

Mira Law's Domain Rating — Ahrefs' 0-100 estimate of how many other sites link
to a domain — is 4. The three firms named in the questionnaire sit at 22, 31
and 38.

We write six new pages about bail hearings, one for each stage of the process.
Mira Law has one today. Those three firms have between four and nine each.

That gap is why a search for any part of a bail hearing reaches them first. Six
pages closes it.

## 2,900 monthly searches, and where they go

Search Console measured 31,400 impressions — the number of times a page
appeared in a Google result, whether or not anybody clicked — over the 28 days
to 4 November. Clicks were 412.

We rewrite the nine service pages in month one. Your developer gives us
WordPress editor access first. That is the one thing we need.
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
check("a document of overlong sentences is flagged",
      "Sentences over 40 words" in out, out[-700:])

# The ceiling went 45 -> 25 -> 40, and the middle step was a mistake worth
# recording. 25 came from a competitor's style guide, which governs an AUDIT
# written for an agency audience. The client-letter benchmark runs 24% of its
# sentences over 25 words, averages 19.2 and peaks at 42 — and it is the
# document the client asked us to write like. Our own rewrite ran 3% over 25,
# averaged 14.5, and he gave up on it in the first two sections.
#
# So a 30-word sentence must now PASS. Shape is what costs a re-read, and the
# SHAPE_PATTERNS checks are what catch shape.
MID = "# Phase 5\n\n" + "\n\n".join(
    ["We'll rewrite the nine service pages in month one and then build the twelve "
     "location pages across months two to five, starting with the two cities "
     "you named as most profitable for the firm."] * 8)
code, out = run(MID)
check("a 30-word sentence passes — 25 was the wrong ceiling",
      "[FAIL] Sentences over" not in out, out[-700:])

# The benchmark's own longest sentence, reproduced. It reads straight through
# because it is a list: subject, verb, three parallel things. A gate that
# fails this is a gate that fails the house voice.
BENCH_LONG = "# Phase 5\n\n" + "\n\n".join(
    ["There is quite a bit to cover, but we wanted to provide enough information "
     "for you to understand where the website currently stands, what "
     "opportunities we've identified, and how we plan to position Mira Law "
     "competitively across Toronto and the surrounding cities."]
    + ["We'll optimize all 23 existing pages in month one."] * 6)
code, out = run(BENCH_LONG)
check("the benchmark's own 42-word sentence does not fail",
      "[FAIL] Sentences over" not in out, out[-700:])

# Share, not count: a long document legitimately carries more than five.
OCCASIONAL = "# Phase 5\n\n" + "\n\n".join(
    ["We rewrite the nine service pages in month one and then build the twelve "
     "location pages across months two to five, starting with the two cities "
     "you named."]
    + ["We add schema to all 14 pages. Your developer gives us access first."] * 20)
code, out = run(OCCASIONAL)
check("one long sentence among twenty short ones does not fail the document",
      "[FAIL] Sentences over 25 words" not in out, out[-700:])

JARGON = """# Phase 5

Their Domain Rating sits at 4.

Impressions fell last period.

Anchor text needs work.

Core Web Vitals look poor.

Crawl budget gets wasted.

Their topical authority looks thin across the practice areas.

Referring domains fell again last quarter.

The long-tail went unclaimed for most of the year.

Their citations carry three different phone numbers.
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

# ── Audit content leaking into a client document ────────────────────────
# The test the client applies: can they forward this to their partner without
# editing it? A strategy carrying crawl counts and severity scores fails it.
AUDITY = """# SEO strategy

## Technical findings

The crawl found 412 crawl errors and 38 redirect chains across the site.
Overall health score is 61/100. Severity: high. The previous agency left
fourteen orphan pages behind when they handed over.
"""
code, out = run(AUDITY)
check("audit vocabulary in a strategy is surfaced",
      "audit vocabulary" in out or "an audit finding" in out, out[-900:])
check("and each phrase is named with what to say instead",
      "pages nothing links to" in out, out[-900:])
check("but it does not fail the document on its own",
      code == 0, "a review item became a hard failure")

code, out = run(GOOD)
check("a plan with no audit vocabulary reports clean",
      "[OK] No audit vocabulary" in out, out[-600:])

# One sentence of finding is how a plan is introduced. It must survive.
ONE_FINDING = GOOD + """
## Meta descriptions

One hundred and eighty pages have no meta description. We write them in
month one, starting with the nine service pages.
"""
code, out = run(ONE_FINDING)
check("a single finding used to introduce a plan still passes",
      code == 0, out[-700:])

# ── Page speed is never a saving ────────────────────────────────────────
# A commitment to clients, so unlike everything else here it fails on sight.
for sentence in (
    "Because your website is already fast, we will not be charging you for speed optimization.",
    "Speed optimization is an optional extra at $400 per month.",
    "You save the speed optimization fee this quarter.",
):
    code, out = run(GOOD + "\n## Scope\n\n" + sentence + "\n")
    check(f"speed as a saving fails: {sentence[:44]}…",
          code == 1 and "included in the SEO plan" in out, out[-700:])

SPEED_OK = GOOD + """
## Page speed and Core Web Vitals

The site loads in 2.1 seconds on mobile. We keep it under 2.5 as pages are
added, and we report the figure every month.
"""
code, out = run(SPEED_OK)
check("speed written as included work passes",
      code == 0 and "[OK] Page speed is not offered as a saving" in out, out[-700:])

# ── The sections a full document carries ────────────────────────────────
FULL = """# SEO strategy — Example Firm

## What we will do first
One table.

## What happens when
This plan covers twelve months, and we keep optimizing what is already live
for the whole of it.

## How it will sound — voice and tone
We write the way the partners speak.

## The 100 keywords, by cluster
Ninety-six phrases.

## The page plan
Fourteen new pages.

## Changes to pages you already have
Four titles and one address.

## Links and authority
Six hundred and twelve sites link here. The closest competitor has 2,100.

## Page speed and Core Web Vitals
The site loads in 2.1 seconds on mobile.

## Objectives, with baselines
Organic sessions go from 2,800 a month to 5,000 a month.

## What the monthly report contains
Rankings, traffic, and what we changed.
"""
code, out = run(FULL, "--full")
check("a complete document passes the section check",
      code == 0 and "Every required client-facing section" in out, out[-900:])

for dropped, label in (("## Links and authority", "links and authority"),
                       ("## Changes to pages you already have", "changes to existing pages"),
                       ("## Page speed and Core Web Vitals", "page speed")):
    cut = "\n".join(l for l in FULL.splitlines() if not l.startswith(dropped))
    code, out = run(cut, "--full")
    check(f"a document missing {label} fails --full",
          code == 1 and label in out, out[-700:])

# The gate that fails good work gets switched off, so a partial build —
# a keyword refresh, a calendar — must never be judged against the full list.
code, out = run(GOOD)
check("a partial document is not judged against the full section list",
      code == 0 and "required client-facing section" not in out, out[-700:])

r = subprocess.run([sys.executable, str(SCRIPT), "--help"],
                   capture_output=True, text=True)
check("--help explains that --full is for a complete document only",
      "--full" in r.stdout and "partial build" in r.stdout, r.stdout[:2000])

# ── How long the plan runs, and that it keeps running ───────────────────
# A strategy arrives with a hundred blog topics and nowhere says the whole
# thing is a year of work. The client reads it as a list of what is about to
# happen, is surprised in month two that four posts exist, and surprised
# again in month nine that there is work left.
# The monthly plan in its agreed shape: three columns, Month / Writing /
# What we work on, GMB posting from month one, and nothing in it that waits
# on the client or that is a report rather than work.
MONTHS = "\n".join(
    f"| {i} | 1 service page, 8 articles | **SEO:** Rewrite the page titles. "
    f"**GMB:** 2 posts. **New Content:** The Brampton hub page |"
    for i in range(1, 13))
YEAR = f"""# SEO strategy

## Summary

This plan covers twelve months. Everything below is spread across that year,
and we keep optimizing what is already live for the whole of it.

## What happens when

| Month | Writing | What we work on |
|---|---|---|
{MONTHS}
"""
code, out = run(YEAR)
check("a plan that states its term and its ongoing work reports clean",
      "[OK] The plan's length is stated" in out, out[-700:])
check("and its month table is recognised",
      "months 1-12" in out, out[-700:])

code, out = run(YEAR.replace("and we keep optimizing what is already live for the whole of it.", "."))
check("stating the term without the ongoing work is flagged",
      "optimizing continues" in out, out[-700:])
check("but it does not fail the document",
      code == 0, "a review item became a hard failure")

code, out = run(GOOD)
check("a document that never says how long the plan is, is flagged",
      "never says how long the plan is" in out, out[-700:])

# A month missing from the table is a month the client will ask about.
gap = YEAR.replace(
    "| 7 | 1 service page, 8 articles | **SEO:** Rewrite the page titles. "
    "**GMB:** 2 posts. **New Content:** The Brampton hub page |\n", "")
code, out = run(gap)
check("a month missing from the table is named",
      "skips month(s) [7]" in out, out[-700:])

# Six months is a plan too — the check must not demand twelve.
SIX = YEAR.replace("twelve months", "six months").replace("that year", "those six months")
for m in range(7, 13):
    SIX = SIX.replace(
        f"| {m} | 1 service page, 8 articles | **SEO:** Rewrite the page titles. "
        f"**GMB:** 2 posts. **New Content:** The Brampton hub page |\n", "")
code, out = run(SIX)
check("a six-month plan is a plan, not a twelve-month one with gaps",
      "months 1-6" in out and "skips month" not in out, out[-700:])

# ── Sentence shape ───────────────────────────────────────────────────────
# The checks above look for bad words. The November 2026 strategy had almost
# none of them: no hedges, no nominalizations, median sentence 13 words,
# Flesch-Kincaid grade 8.5 — better than the 11.4 of the agency document it was
# measured against. The client needed four hours to read it and said he could
# not present it to his team.
#
# What was wrong was how the sentences were built. Every string below is
# verbatim from that document. Each one is grammatical, and each one makes the
# reader hold the first half open until the second half arrives.
print("\n-- Sentence shape --")

SHIPPED = [
    "The useful part is not that they are ahead. It is how.",
    "The route through is the one your competitors already took.",
    "A hub that links to its children using each child's own search term is "
    "what tells Google which page owns which search.",
    "Naming the actual courthouse is what makes a location page a real page.",
    "Each one is a stream of work rather than a single job.",
    "We have tiered the nine cities by what the searches are worth, so the "
    "sequence is commercial rather than geographic.",
    "A page nobody reads is a cost rather than an asset.",
    "Where a larger search sits in work you did not name, it waits behind a "
    "smaller search in work you did.",
    "When the second pass lands in month ten, the pages that moved are the "
    "ones we keep.",
    "Once the base is holding, the next thing we take on is the resource guide.",
]
code, out = run("# Phase 5\n\n" + "\n\n".join(SHIPPED))
check("the prose the client could not read now fails",
      code == 1 and "[FAIL] Sentence shape" in out, out[-900:])
for want in ("rather than", "fronted subordinate"):
    check(f"  and it names the construction: {want}", want in out, out[-900:])

# The cleft gets its own fixture. In the shipped document it appears twice,
# against 28 "rather than" and 10 "is what" — it is the construction that hurts
# most per instance and fires least often, so a mixed sample will not show it.
CLEFTS = [
    "The useful part is not that they are ahead.",
    "The table above is what gets built.",
    "What matters here is not the volume. It is the intent behind it.",
    "The thing that moves first is what sits closest to page one.",
]
code, out = run("# Phase 5\n\n" + "\n\n".join(CLEFTS))
check("  and a run of clefts is caught on its own",
      "[FAIL] Sentence shape" in out and "cleft" in out, out[-900:])

# The same facts, written the way plain-language.md now asks. Nothing here is
# softer or shorter — it is the same content with the subject in front.
REWRITTEN = """# Phase 5

You named sexual assault and impaired driving as the firm's most profitable
work, so we'll build those pages first.

A bigger search in an area you did not name waits until the year's second half.

Four things run for the whole twelve months, not just the build phase.

Those are the 100 articles, a weekly Google Business post, monthly reporting
and the quarterly review.

In month ten we'll run a second pass over everything published so far.

We'll keep the pages that moved and rewrite the ones that didn't.

We'll publish every topic inside the twelve months, because the set is sized
to the year and nothing carries over into a second engagement.

Reviews need to come back inside two days to hold that pace.

The quarterly review asks whether the searches we bet on were the right ones.

Where the evidence says they weren't, we'll move the next quarter's priorities.

Month one publishes no new pages and is still the busiest month of the twelve.

That's because the whole profile build and the listings correction happen in
it, and neither one waits on new content.

We'll label what already exists before we add to it, so the new pages land on
a site Google can read.

The city pages come before the neighbourhood pages, because a neighbourhood
page with no city page above it has nothing to link up to.
"""
code, out = run(REWRITTEN)
check("the same facts, rewritten, pass clean",
      "[FAIL] Sentence shape" not in out, out[-900:])
check("  and the rewrite is not over-labelled",
      "open with a label" not in out,
      "the ceiling is 15%; there is no floor any more")
check("  and it still commits to work",
      "we add content" in REWRITTEN.lower() or "we build" in REWRITTEN.lower()
      or "keep the pages" in REWRITTEN.lower())

# A density on its own cannot judge a short section: one cleft in a 140-word
# excerpt is 7 per 1,000 words. A construction has to be repeated before it is
# a pattern rather than a sentence.
code, out = run("# Phase 5\n\n" + "\n\n".join([
    "The useful part is not that they are ahead. It is how.",
    "We rewrite the nine service pages in month one.",
    "Add LocalBusiness schema to all 14 pages.",
    "You have 11 Google reviews. The three firms you named have 47, 112 and 139.",
]))
check("one such sentence in otherwise good prose does not fail the document",
      "[FAIL] Sentence shape" not in out, out[-900:])

# Two phrasings that read perfectly well and were in the first draft of the
# banned list. Both came straight back out.
code, out = run("# Phase 5\n\n" + "\n\n".join([
    "That gap is why a search for any part of a bail hearing reaches them.",
    "Your developer gives us editor access. That is the one thing we need from you.",
    "That gap is why the six pages come first.",
    "Access is the one thing we need. That is why it is in week one.",
] * 2))
check("'is why' and 'is the one' are clear English and are not flagged",
      "[FAIL] Sentence shape" not in out,
      "a gate that fails clear writing gets switched off")

print("\n-- Scannability --")
# This block used to assert the opposite, and it was the most damaging
# assertion in the file. It required a FLOOR of 25% labelled paragraphs, on
# the evidence that a competitor's audit ran 32%.
#
# That competitor document is a reference work for an agency audience. The
# client-letter benchmark labels 3% of its paragraphs and is what the client
# asked for. So an unlabelled document must now pass cleanly.
WALL = "# Phase 5\n\n" + "\n\n".join([
    "We'll rewrite the nine service pages in month one.",
    "The firm has one page about bail hearings today.",
    "The three competing firms have between four and nine each.",
    "We'll write six more, one for each stage of a bail hearing.",
    "They'll publish across months two and three.",
    "Each one answers a question somebody actually types into Google.",
] * 2)
code, out = run(WALL)
check("a document of unlabelled paragraphs passes — there is no floor now",
      "open with a scannable" not in out and "[FAIL] " not in out, out[-900:])

# And the form the old check could not see at all: a verbless fragment, a
# full stop, then the real sentence. 44% of the rejected strategy's
# paragraphs, scored by the old gate at 0%.
STUBS = "# Phase 5\n\n" + "\n\n".join([
    "The year's totals. The plan delivers 4 service pages and 24 location pages.",
    "How each keyword is scored. Every keyword carries a score out of 100.",
    "The wider GTA. Mississauga and Vaughan enter the plan in month nine.",
    "Who is reading. The reader is frightened and often reading at night.",
    "Claim the memberships first. The firm already holds three of them.",
    "The blog topics. The 86 topics fall into nine groups.",
    "Link text plan. Rotate the phrases so no page collects the same text twice.",
    "Set the service area in month one. It covers twenty cities and boroughs.",
    "We'll rewrite the nine service pages in month one.",
    "The firm has one page about bail hearings today.",
    "We'll write six more, one for each stage of a bail hearing.",
    "Each one answers a question somebody actually types into Google.",
])
code, out = run(STUBS)
check("fragment openers are caught — the habit the old gate was blind to",
      code == 1 and "open with a sentence fragment" in out, out[-900:])
check("  and the fragment itself is quoted back",
      "The year's totals" in out, out[-900:])

# A short opening sentence is not a fragment. It has a verb, and a gate that
# cannot tell the difference fails good writing.
SHORT_BUT_WHOLE = "# Phase 5\n\n" + "\n\n".join([
    "SEO isn't a one-time process. We'll keep optimizing for the whole year.",
    "Mira Law ranks for none of them. The three competing firms rank for 881.",
    "Google needs more than a fast website. It also needs content people want.",
    "The profile matters more than the website here. Only a profile answers a map search.",
    "This will be one of the most significant components of our strategy.",
    "We'll develop dedicated pages targeting specific services and locations.",
    "Before expanding the website, our first priority is the existing foundation.",
    "These improvements will provide the base for everything else in the year.",
    "You'll receive monthly performance reports showing what moved and what we did.",
    "We'll also conduct quarterly strategy reviews to evaluate our progress.",
    "Our focus will remain on searches that can attract real potential clients.",
    "Think of this as making sure Google properly understands what the firm does.",
])
code, out = run(SHORT_BUT_WHOLE)
check("a short sentence with a verb is not mistaken for a fragment",
      "open with a sentence fragment" not in out, out[-900:])

NO_ACTION = "# Phase 5\n\n" + "\n\n".join([
    "The site has one page per practice area today. Competitors have between "
    "four and nine each, and theirs are longer.",
    "Search volume in this market sits mostly on city-and-offence phrases. "
    "Difficulty on those phrases is low.",
    "The profile is claimed and verified. Its category is correct.",
] * 3)
code, out = run(NO_ACTION)
# This used to require a FLOOR of 3 bare imperatives per 1,000 words, and the
# floor is what put "Profile the render path in month 4" into a client
# document. The benchmark uses ZERO and writes "we'll develop", "we'll
# prioritize". So prose with no imperatives in it must now pass, and the
# describing-without-committing fault is caught by the vague-action check
# instead, which looks for the missing subject rather than the missing verb.
check("prose with no bare imperatives passes — the floor is gone",
      "bare instructions per 1k words" not in out
      or "[FAIL]" not in out.split("bare instructions")[0].split("\n")[-1],
      out[-900:])

# A document written as a worklist now fails.
WORKLIST = "# Phase 5\n\n" + "\n\n".join([
    "Profile the render path in month 4, then defer the scripts that block it.",
    "Build 17 city-and-offence pages across months 2 to 9.",
    "Claim the four professional listings in month one.",
    "Rewrite the wording of the links between pages.",
    "Add the Google code to every new page in month eight.",
    "Publish one guide other sites would want to link to.",
    "Check the wording of every link built so far.",
    "Set the service area to the twenty cities the firm wants calls from.",
])
code, out = run(WORKLIST)
check("a document written as a worklist fails",
      code == 1 and "bare instructions per 1k words" in out, out[-900:])
check("  and it names the fix",
      "we'll" in out.lower(), out[-900:])

# Two label forms are in use and both are scannable: the agency's "Label: ..."
# and this repo's "**Label.** ...". The bold form has to be matched before the
# markdown is stripped, or it looks like an ordinary opening sentence. It was,
# and the check silently reported "no paragraphs" on a correctly written
# section — a check that skips is worse than one that fails.
BOLD = "# Phase 5\n\n" + "\n\n".join([
    "**What we build.** Build 17 city-and-offence pages across months 2-9. "
    "Start with Brampton and Oshawa, the two largest markets on your list.",
    "**What you do.** Confirm the two cities before 1 December. Send us the "
    "courthouse details for each one.",
    "**How they got there.** They built a page for each offence in each city. "
    "You have not built those pages. That is the whole difference.",
    "A Dhillon Law repeats the same shape in nine more cities, so the pattern is "
    "proven in this market rather than a guess about it.",
    "Neither firm got there on links, which means we can match them without "
    "matching their link profile.",
])
code, out = run(BOLD)
check("a bold **Label.** is still recognised as a label",
      "Labels used sparingly" in out or "open with a label" in out, out[-900:])
check("  and the paragraphs are counted, not skipped",
      "0 of 0" not in out, out[-900:])

# Over the ceiling it fails. The rejected strategy ran 91% labelled
# paragraphs at one point, and the client called it a filing system.
ALL_LABELS = "# Phase 5\n\n" + "\n\n".join([
    "**What we build.** 17 city-and-offence pages across months 2 to 9.",
    "**What you do.** Confirm the two cities before 1 December this year.",
    "**How they got there.** They built a page for each offence in each city.",
    "**Pattern 1.** A page for each offence in each city, in eleven cities.",
    "**Pattern 2.** Plain explanations of what each charge actually means.",
    "**The year's totals.** 4 service pages, 24 location pages, 86 articles.",
    "**Month one.** Titles, descriptions and schema on all 23 existing pages.",
    "**Month two.** The impaired driving page, and eight articles beside it.",
])
code, out = run(ALL_LABELS)
check("a document of nothing but labels fails",
      code == 1 and "open with a label" in out, out[-900:])

# ── The worked example ───────────────────────────────────────────────────
# The skill had no sample of a finished client-facing section in the house
# voice. Without one the model infers the voice from the tone of the
# instructions, and the instructions are not written in the voice of a
# deliverable. That is how a 10,000-word strategy came out in essay register.
print("\n-- Rule 1: the document is for the client, not the reviewer --")
# A strategy gets reviewed internally. The reviewer pushes back, the next draft
# answers the pushback, and the answer stays in the document. Ship a dozen
# rounds of that and the client receives a transcript of an argument they did
# not attend. Every string below is verbatim from a shipped document. The
# agency this was calibrated against does it zero times in 5,893 words.
REVIEWER_VOICE = "# Phase 5\n\n" + "\n\n".join([
    "One thing we checked that is worth your knowing: neither competitor got "
    "there on the strength of their links.",
    "The 551 figure needs explaining, because it looks like a strength and is not.",
    "Read the last two columns together, because they are the finding.",
    "We would rather give 96 rows that can be built than pad four more.",
    "That is the honest answer, not a gap in it.",
    "This appendix is here so it is clear the question was asked.",
    "That about-the-city section is not decoration.",
])
code, out = run(REVIEWER_VOICE)
check("a document arguing with its reviewer fails",
      code == 1 and "explaining or defending itself" in out, out[-900:])
check("  and it says who the document is actually for",
      "did not attend the review" in out, out[-900:])

code, out = run("# Phase 5\n\n" + "\n\n".join([
    "Neither competitor got there on the strength of their links.",
    "Of the 551 sites linking to the firm, 547 are automated junk.",
    "Citadel Law has 216 reviews and ranks 25.8 across the grid.",
    "We write 96 searches into the sheet, each with the page that owns it.",
]))
check("  the same facts without the commentary pass",
      "explaining or defending itself" not in out, out[-900:])

print("\n-- Rule 2: pronouns --")
# "We" says two parties are doing this. "You" with no "we" beside it says one
# party is being assessed by another. Theirs: 5.3 per 1k. Ours was 23.3.
JABBING = "# Phase 5\n\n" + "\n\n".join([
    "Your homepage title is your firm name and nothing else, so your rankings "
    "depend on your brand. Your competitors took your searches while your "
    "pages sat unchanged, and your profile is not helping you either."
] * 3)
code, out = run(JABBING)
check("prose that jabs \"you\" at the reader fails",
      code == 1 and '"you" / "your"' in out, out[-900:])
check("  and the limit is stated with the benchmark",
      "limit 14.0" in out, out[-900:])

# The limit moved 8.0 -> 14.0. The benchmark runs 3.0 and uses "you" in its
# warmest sentences — "you'll receive monthly performance reports", "enough
# information for you to understand". A limit tight enough to push a writer
# off those is working against the voice. The word was never the fault.
WARM_YOU = "# Phase 5\n\n" + "\n\n".join([
    "You'll receive monthly performance reports showing exactly what moved.",
    "We wanted to give you enough information to see where the site stands.",
    "Based on our research and your feedback, we'll start with impaired driving.",
    "We'll send you a short note each month with the work we completed.",
    "Mira Law's website gets about 170 clicks a month from search today.",
    "We'll take that to roughly 400 by month twelve.",
    "The firm's profile doesn't appear on the map outside the office.",
    "We'll work that up to fifteen of the forty-nine points we track.",
])
code, out = run(WARM_YOU)
check("  \"you\" used warmly is not penalised",
      '[FAIL] "you"' not in out, out[-900:])

code, out = run("# Phase 5\n\n" + "\n\n".join([
    "Your company's rankings depend on brand searches today.",
    "We rewrite the titles in month one.",
    "Your business needs the profile completed before the map moves.",
] * 2))
check("\"your company\" and \"your business\" fail outright",
      code == 1 and '"your company" / "your business"' in out, out[-900:])

BALANCED = "# Phase 5\n\n" + "\n\n".join([
    "Mira Law's website got 173 clicks in November. We looked at 139 of them, "
    "and almost all came from people searching for your name.",
    "We start with the pages and the Google Business Profile you already have. "
    "Optimizing those builds the foundation.",
    "Then we add content to support our ranking goals around the office. Mira "
    "Law holds one page per service today and we take that to nine.",
])
code, out = run(BALANCED, "--client", "Mira Law")
check("the voice the client asked for passes",
      '[FAIL] "you"' not in out and '"your company"' not in out, out[-900:])
check("  and the balance is reported",
      "Pronouns balanced" in out, out[-900:])

print("\n-- Rule 3: a strategy is not an audit --")
# The audit says what is true now. The strategy says what we will do. A
# baseline is allowed beside the target it is measured against, and nowhere
# else. Verbatim from the shipped document again.
FINDINGS = "# Phase 5\n\n" + "\n\n".join([
    "No page title contains the word \"lawyer\".",
    "The services list is empty and the service area is unset.",
    "The sexual assault page is 426 words today.",
    "The citation score is 6 out of 100.",
    "The profile does not appear at any of the other 48 points.",
])
code, out = run(FINDINGS)
check("a strategy full of audit findings fails",
      code == 1 and "Audit findings in a strategy document" in out, out[-900:])
check("  and it says where a baseline is allowed",
      "beside the target it is measured against" in out, out[-900:])

PROJECTED = """# Phase 5

We rewrite the titles on all 23 pages in month one.

We add nine services to the Google Business Profile in month one, and set the
service area across all nine cities.

We take the sexual assault page to 2,000 words in month two.

| What we measure | Today | Month 6 | Month 12 |
|---|---|---|---|
| Clicks from searches that are not the firm's name | 1 | 40 | 120 |
| Grid points where the profile appears | 1 of 49 | 20 of 49 | 35 of 49 |

We start from 173 clicks a month and aim for 400 by month six.
"""
code, out = run(PROJECTED)
check("the same numbers, paired with targets, pass",
      "Audit findings in a strategy document" not in out, out[-900:])
check("  and a baseline column in a table is not a finding",
      "[OK] No audit findings" in out, out[-900:])

print("\n-- Rule 3, structurally --")
# The phrase list shipped in the first version of Rule 3 was fitted to one
# document. On the next strategy the skill produced it caught 1 finding in 8:
# "No page title on miralaw.ca contains the word lawyer" slipped a \\w+-only
# pattern because a domain has a dot in it, and an entire section headed
# "Where the firm stands today" — 323 words of measured present with no
# target anywhere — was invisible, because nothing was looking at sections.
#
# Phrasings are endless. The structure is not.
FINDINGS_SECTION = """# SEO strategy

## Where the firm stands today

Google served the site 1,965 times in the 28 days to 20 November and sent 202
clicks. Average position across everything: 17.1.

Of the 202 clicks, 153 came from somebody typing the firm's name. Not one came
from a search by somebody who did not already know it.

The site took 383 visits from 252 people in the same 28 days, with 5 enquiries.
"""
code, out = run(FINDINGS_SECTION)
check("a section headed with the present, committing to nothing, fails",
      code == 1 and "report the current state" in out, out[-900:])
check("  and it says where those figures belong",
      "beside the target it is measured against" in out, out[-900:])

# The same heading over a section that does commit is the shape Rule 3 wants.
WITH_TARGETS = FINDINGS_SECTION + """
| What we measure | Today | Month 6 | Month 12 |
|---|---|---|---|
| Non-brand clicks | 1 | 40 | 120 |
| Organic visits | 147 | 250 | 400 |
"""
code, out = run(WITH_TARGETS)
check("  the same heading passes once the section carries targets",
      "report the current state" not in out,
      "failing this would fail the exact shape the rule asks for")

# One stray month reference must not excuse 300 words of findings.
code, out = run(FINDINGS_SECTION + "\nSpeed work is included in month 4.\n")
check("  and one passing mention of a month does not excuse it",
      "report the current state" in out, out[-900:])

# The domain-with-a-dot miss, verbatim.
code, out = run("# Phase 5\n\n" + "\n\n".join([
    'No page title on miralaw.ca contains the word "lawyer".',
    "Two directories carry a different postcode and phone number.",
    "The site is invisible to anyone who does not already know the firm.",
    "Those searches produced no clicks at all.",
]))
check("deficiencies survive a domain, a qualifier or a passive dressing",
      code == 1 and "Audit findings in a strategy document" in out, out[-900:])

# "Finding" is the audit's word for the audit's content.
code, out = run("# Phase 5\n\nThe finding that drives this plan is simple enough.\n")
check("\"the finding\" fails outright in a strategy",
      code == 1 and "the audit's word" in out, out[-900:])
code, out = run("# Phase 5\n\nWe build 17 pages across months 2 to 9, starting with Brampton.\n")
check("  and a plan that just says what happens does not trip it",
      "the audit's word" not in out, out[-900:])

# Market demand is not a measurement of the client. The keyword opportunity
# table is the most forward-looking thing in a strategy and the first version
# of this check failed it.
MARKET = """# SEO strategy

## The searches we go after first

| Search | Est. searches a month | Difficulty | What we do |
|---|---|---|---|
| dui lawyer | 2,900 | 0 | Retitle and expand the existing page |
| peace bond | 1,600 | 0 | One new article |
| criminal harassment | 1,000 | 0 | One new service page |
"""
code, out = run(MARKET)
check("a keyword opportunity table is not a findings section",
      "report the current state" not in out,
      "search volume is market demand, not a measurement of the client")

print("\n-- A floor with no ceiling is a target to max out --")
# The label rule shipped as "25%+". The next strategy came back at 94%
# labelled paragraphs with two connectives in fifty, and the client called it
# unreadable. He was right: every paragraph a bolded stub is a filing system,
# not a document. The benchmark agency document runs at 32%.
ALL_LABELS = "# SEO strategy\n\n" + "\n\n".join(
    [f"**Point {i}.** A separate set of {i*10}, in the sheet. These are the city "
     f"phrases. The profile answers them, not a page." for i in range(1, 13)])
code, out = run(ALL_LABELS)
check("a document of nothing but labels fails",
      code == 1 and "of paragraphs open with a label" in out, out[-900:])
check("  and it says why, rather than just naming a number",
      "filing system, not a document" in out, out[-900:])

MIXED = """# SEO strategy

A Dhillon Law draws an estimated $16,432 a month in traffic they would
otherwise pay for, which is the size of the organic prize in this market. We
pulled every non-branded search both firms rank in the top ten for, and two
patterns account for nearly all of it.

**A page for each offence in each city.** A Dhillon Law ranks first for
"impaired driving lawyer brampton" at difficulty 0, and repeats the shape in
nine more cities. These pages are easy to rank because almost nobody has built
them, so we build 17 between months 2 and 9.

Neither firm got there on the strength of their links. Both carry the same
automated junk our domain does, so we can match them without matching their
link profile.

**Plain explanations of charges.** A Dhillon Law holds first place for
"criminal harassment" at an estimated 1,000 searches a month, which means a
well-written explainer can take it. We write four in months 2 to 4.
"""
code, out = run(MIXED)
# This fixture is the old "house voice" example, and it used to pass here.
# It now fails at 50% labelled paragraphs against a 15% ceiling, which is the
# whole correction: the ceiling replaced a 25% FLOOR, and the floor is what
# produced the document the client called a filing system. Kept as the
# regression marker — if this ever passes again, the floor has come back.
check("the old house-voice example is now over-labelled, and fails",
      code == 1 and "of paragraphs open with a label" in out, out[-900:])
check("  and the ceiling is named in the message",
      "ceiling 15%" in out, out[-900:])

print("\n-- Findings hiding in a table --")
# The findings in the last strategy had migrated into a "Why it is first"
# column. prose_only() strips table rows, so the checker read a clean document
# while the findings sat in the first table the reader meets.
IN_TABLE = """# SEO strategy

| # | Priority | Why it is first | Month |
|---|---|---|---|
| 1 | Put a title on all 23 pages | No page title on miralaw.ca contains the word "lawyer". | 1 |
| 2 | Fill in the profile services list | Both are empty. The profile cannot rank for an offence it does not name. | 1 |
| 3 | Correct the business details | Two directories carry a different postcode. | 1 |
"""
code, out = run(IN_TABLE)
check("audit findings inside a table are caught",
      "Audit findings in a strategy document" in out, out[-900:])
check("  including \"are empty\", which an is-only pattern missed",
      "are empty" in out or "No page title" in out, out[-900:])

print("\n-- Connectives are reported, never used to block --")
# The agency document this spec is calibrated against runs at 0.01
# connectives a paragraph and the client holds it up as the model. A gate
# that fails the benchmark is a gate that gets switched off.
NO_CONN = "# SEO strategy\n\n" + "\n\n".join(
    [f"We build {i} pages in month {i}. Each one targets a named search and "
     f"carries a title, a description and schema markup." for i in range(1, 11)])
code, out = run(NO_CONN)
check("prose with no connectives is reported",
      "connectives across" in out, out[-900:])
check("  but it does not block, because the benchmark would fail it too",
      "[FAIL]" not in out.split("connectives across")[0].split("\n")[-1], out[-900:])

print("\n-- Future work takes \"will\" --")
# The spec said "imperatives, not we will be building", and the imperative
# metric scored "we build" 1 and "we will build" 0. Both the written rule and
# the measured one were training documents out of the future tense, and a
# real strategy came back with 21 bare "we <verb>" against 6 "we will".
PRESENT = "# SEO strategy\n\n" + "\n\n".join([
    "We optimize the existing pages in month one.",
    "We rewrite all 23 titles in month one.",
    "We build two Oakville pages in month six.",
    "We re-measure the map grid in month twelve.",
])
code, out = run(PRESENT)
check("future work in the present tense fails",
      code == 1 and "present tense" in out, out[-900:])
code, out = run(PRESENT.replace("We ", "We will "))
check("  and the same sentences with \"will\" pass",
      "present tense" not in out, out[-900:])
# This used to assert a FLOOR on imperatives. The floor is gone: the
# benchmark writes "we'll develop" and uses zero bare imperatives, and the
# floor is what put "Profile the render path in month 4" into a client
# document. "We will build" must simply not be penalised either way.
check("  \"we will build\" is not counted as a bare instruction",
      "bare instructions per 1k words" not in out
      or "[FAIL]" not in out.split("bare instructions")[0].split("\n")[-1],
      out[-900:])
code, out = run("# SEO strategy\n\n" + "\n\n".join([
    "Add nine services to the Google Business Profile.",
    "Rewrite the 750-character description.",
    "Claim the four professional listings.",
] * 3))
check("  an imperative in a specification row is still correct",
      "present tense" not in out, out[-900:])

print("\n-- Internal shorthand in the month table --")
# Reported verbatim: "What do 4 Tier 1 listings mean? Profile photo set
# expanded. Speed work: the 9.0-second render path. What do Profile posts
# start and Q&A seeded mean?" The client runs the agency and could not read
# his own plan.
SHORTHAND = """# SEO strategy

| Month | Content | Off-page | Technical |
|---|---|---|---|
| 1 | 0 new pages | 4 Tier 1 listings | Q&A seeded |
| 2 | 2 service pages | Internal link text repaired | Speed work: the render path |
| 3 | 4 location pages | 1 citable resource published | Profile photo set expanded |
"""
code, out = run(SHORTHAND)
check("planning vocabulary in a table fails",
      code == 1 and "internal shorthand" in out, out[-900:])
for term in ("Tier 1", "Q&A seeded", "render path", "citable", "photo set expanded"):
    check(f'  names "{term}"', term.lower() in out.lower(), out[-900:])
check("  and says what to write instead, not just what is wrong",
      "say what the listing is" in out or "we write and answer eight" in out, out[-900:])

PLAIN = """# SEO strategy

| Month | Content | Off-page | Technical |
|---|---|---|---|
| 1 | No new pages | Claim the four professional listings the firm is entitled to | Write and answer eight questions on the Google profile |
| 2 | Two service pages | Rewrite the wording of the links between pages | Cut the time the homepage takes to look finished |
| 3 | Four city pages | Publish one guide other sites would link to | Add six more photographs to the profile |
"""
code, out = run(PLAIN)
check("the same rows in plain words pass",
      "[FAIL]" not in out.split("internal shorthand")[0].rsplit("\n", 2)[-1]
      and "piece(s) of internal shorthand" not in out, out[-900:])

print("\n-- Objectives cited before they exist --")
# The introduction said "Objective 1 sets the target" and "Objective 7 takes
# the profile from 0 of 49". The objectives table was 550 lines further down.
# The client read the first two sections and stopped.
FWD = """# SEO strategy

## Introduction

We go after the searches that bring new clients. Objective 1 sets the target:
120 clicks a month that are not the firm's name, by month 12.

## Objectives, and how we will know

| # | Objective | Today | Target | By |
|---|---|---|---|---|
| 1 | Non-brand clicks a month | 1 | 120 | Month 12 |
"""
code, out = run(FWD)
check("a numbered objective cited before its table fails",
      code == 1 and "before the table that defines it" in out, out[-900:])
check("  and it offers both ways out",
      "State the target in words" in out and "move the table up" in out, out[-900:])

code, out = run(FWD.replace(
    "Objective 1 sets the target:\n120 clicks a month that are not the firm's name, by month 12.",
    "We are going from 1 non-brand click a month to 120 by month 12."))
check("  stating the target in words where it is used passes",
      "before the table that defines it" not in out, out[-900:])


print("\n-- The monthly plan: six prohibitions --")
# The first table the client reads, and the one they came back on hardest
# after four rejected drafts. references/document-assembly.md has the
# reasoning; these are the mechanical half.
PLAN_HEAD = """# SEO strategy

This plan covers twelve months, and we keep optimizing what is already live
for the whole of it.

## Monthly Plan

| Month | Writing | What we work on |
|---|---|---|
"""
def plan(rows):
    return PLAN_HEAD + "\n".join(rows) + "\n"

GOOD_ROWS = ["| %d | 1 service page, 8 articles | **SEO:** Rewrite the page titles. "
             "**GMB:** 2 posts. **New Content:** The Brampton hub page |" % i
             for i in range(1, 13)]

code, out = run(plan(GOOD_ROWS))
check("a clean monthly plan passes", "[FAIL]" not in out, out[-900:])
check("  and its columns are confirmed",
      "Month, Writing, What we work on" in out, out[-500:])

# 1-2. Reporting is not a month's work.
for bad, label in [
    ("Half-year review. Re-run the map ranking check across the GTA", "half-year review"),
    ("Reporting: Quarterly strategy review", "quarterly review"),
    ("Set the priorities for year two", "next year's priorities"),
    ("Re-run the map grid", "the map grid re-check"),
]:
    rows = list(GOOD_ROWS)
    rows[5] = "| 6 | 8 articles | **SEO:** " + bad + " |"
    code, out = run(plan(rows))
    check("  %s is kept out of the plan" % label,
          code == 1 and "[FAIL]" in out and "beside a month" in out, out[-700:])

# Re-optimising a published page is WORK and stays. This is the line the
# client drew: somebody does something to the site that month.
rows = list(GOOD_ROWS)
rows[6] = "| 7 | 8 articles | **SEO:** Re-optimize the pages published in months 1 to 4 |"
code, out = run(plan(rows))
check("  but re-optimising a published page is work, and stays",
      "[FAIL]" not in out, out[-900:])

# 3. Work that waits on the client cannot carry a date.
for bad, label in [
    ("Add further photographs of the office and the team", "photographs"),
    ("Claim the professional memberships the firm holds", "memberships"),
    ("Manager access to the Google Business Profile", "access"),
    ("Feedback on drafts within two to three days", "the client's own replies"),
]:
    rows = list(GOOD_ROWS)
    rows[4] = "| 5 | 8 articles | **GMB:** " + bad + " |"
    code, out = run(plan(rows))
    check("  %s gets no month" % label,
          code == 1 and "[FAIL]" in out and "beside a month" in out, out[-700:])

# And the same rule in any other table that dates work — this is how the
# firm's own memberships ended up with months beside them in Links and
# Authority, which is a different table entirely.
LINKS = PLAN_HEAD + "\n".join(GOOD_ROWS) + """

## Links and Authority

| Where the links will come from | Month |
|---|---|
| The Criminal Lawyers' Association directory, a membership the firm holds | 1 |
"""
code, out = run(LINKS)
check("  and a Month column anywhere else is held to the same rule",
      code == 1 and "beside a month" in out, out[-700:])

# 4. Columns that were removed.
for col, label in [("Links and listings", "Links and listings"),
                   ("Website and Google profile", "Website and Google profile")]:
    doc = plan(GOOD_ROWS).replace("| Month | Writing | What we work on |",
                                  "| Month | Writing | " + col + " | What we work on |")
    code, out = run(doc)
    check("  the %s column is rejected" % label,
          code == 1 and "column(s) that were removed" in out, out[-700:])

# 5. Column ORDER. Asked for in these words: Month, Writing, what we work on.
doc = plan(GOOD_ROWS).replace("| Month | Writing | What we work on |",
                              "| Month | What we work on | Writing |")
code, out = run(doc)
check("  Writing on the right is flagged, as the approved document had it",
      "Writing is column 3" in out, out[-700:])

# 6. Month one has to show them something.
rows = list(GOOD_ROWS)
rows[0] = "| 1 | content optimization | **SEO:** Titles, descriptions and schema on all 23 pages |"
code, out = run(plan(rows))
check("  month one with nothing visible fails",
      code == 1 and "shows the client nothing" in out, out[-700:])
check("    and it names the fix",
      "GMB posting starts in month one" in out, out[-700:])

# A keyword table's Priority column is not a schedule. The first version of
# this check read every cell for a bare 1-12 and failed a good document on
# a keyword row, which is how a gate starts failing good work.
KW = plan(GOOD_ROWS) + """
## Keywords

| # | Keyword | Est. searches | Priority | Target page |
|---|---|---|---|---|
| 1 | criminal lawyer toronto | 2,400 | 1 | Service page |
| 2 | dui lawyer brampton | 600 | 1 | Location page |
"""
code, out = run(KW)
check("  a Priority column is not mistaken for a schedule",
      "[FAIL]" not in out, out[-900:])

print("\n-- No pink --")
code, out = run(plan(GOOD_ROWS) + "\nThe chart uses #FF6699 for the trend line.\n")
check("pink in a client document fails",
      code == 1 and "Pink in a client document" in out, out[-700:])

print("\n-- The benchmark the voice is calibrated against --")
# The highest-leverage file in the skill: the model copies the example it is
# shown far more faithfully than it follows a rule. The previous example here
# was calibrated against a competitor's AUDIT — a dense, labelled, imperative
# reference document for an agency audience — and the client gave up on four
# drafts written to it. The benchmark is a client LETTER.
BENCH = REPO / ".claude/skills/seo-strategy-builder/assets/client-letter-benchmark.md"
check("the skill ships the client-letter benchmark", BENCH.exists())
if BENCH.exists():
    whole = BENCH.read_text(encoding="utf-8")
    body = whole.split("# \u25bc THE BENCHMARK BEGINS")[1].split("# \u25b2 THE BENCHMARK ENDS")[0]

    # The benchmark must pass every gate in the checker. A check that fails it
    # is a check pointed the wrong way, which is exactly what happened to four
    # of them, so this assertion is the guard against repeating it.
    code, out = run("# Phase 2\n" + body, name="benchmark.md")
    check("  the benchmark passes the checker outright",
          code == 0,
          "a gate that fails the house voice is the bug, not the document:\n"
          + "\n".join(l for l in out.splitlines() if l.startswith("[FAIL]")))
    check("  no bare imperatives in it",
          "[FAIL] " not in out.split("bare instructions")[0].split("\n")[-1], out[-900:])
    check("  it contracts like a person",
          "contractions per 1k" in out and "[FAIL] " not in
          out.split("contractions per 1k")[0].split("\n")[-1], out[-900:])
    check("  it opens paragraphs with sentences, not fragments",
          "sentences, not fragments" in out, out[-900:])

    # The measured comparison is the evidence, and losing it is how the
    # calibration drifts back.
    check("  it records the measured difference from what we were producing",
          "1.1" in whole and "24.5" in whole and "44%" in whole,
          "sentences per paragraph, contractions, fragment openers")
    check("  and that readability metrics pointed the wrong way",
          "14.5" in whole and "7.0" in whole,
          "the readable document scores HARDER; this is why grade level is not a gate")
    check("  it says no appendices and no provenance",
          "appendix" in whole.lower() and "pull date" in whole.lower())

# The old example stays, marked, because the mistake in it is instructive.
VOICE = REPO / ".claude/skills/seo-strategy-builder/assets/worked-section-house-voice.md"
if VOICE.exists():
    old = VOICE.read_text(encoding="utf-8")
    check("the superseded example says so at the top, before any of its prose",
          "SUPERSEDED" in old[:600], old[:300])
    check("  and sends the reader to the benchmark instead",
          "client-letter-benchmark.md" in old[:1200], old[:600])
    check("  and names why it was wrong: it was calibrated against an audit",
          "audit" in old[:1600].lower(), old[:900])

# The skill has to point at it, or nothing reads it.
SKILL_MD = (REPO / ".claude/skills/seo-strategy-builder/SKILL.md").read_text(encoding="utf-8")
check("the skill tells a session to read the benchmark first",
      "client-letter-benchmark.md" in SKILL_MD)
check("  and says not to infer the voice from the instructions themselves",
      "not a client deliverable" in SKILL_MD,
      "this is the actual root cause: the model mirrors the register it is reading")
check("  and states the rules that replaced the inverted ones",
      "one idea per paragraph" in SKILL_MD.lower()
      and "we'll" in SKILL_MD.lower()
      and "bare imperative" in SKILL_MD.lower(),
      SKILL_MD[:200])

print()
print(f"Passed: {passed}  Failed: {failed}")
sys.exit(1 if failed else 0)
