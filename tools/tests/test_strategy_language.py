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
check("a document of overlong sentences fails",
      code == 1 and "[FAIL] Sentences over 25 words" in out, out[-700:])

# The threshold moved from 45 words to 25 in November 2026. 45 was the reason
# this check never fired on the strategy a client needed four hours to read:
# that document's median sentence was 13 words and its longest was 46. The
# number now matches what plain-language.md asks for, and what the agency we
# are measured against states in its own style guide.
MID = "# Phase 5\n\n" + "\n\n".join(
    ["We rewrite the nine service pages in month one and then build the twelve "
     "location pages across months two to five, starting with the two cities "
     "you named as most profitable for the firm."] * 8)
code, out = run(MID)
check("a 30-word sentence is now caught, where 45 was the old bar",
      code == 1 and "[FAIL] Sentences over 25 words" in out, out[-700:])

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
MONTHS = "\n".join(f"| {m} | Focus | 2 posts | Tier 1 | Titles |" for m in range(1, 13))
YEAR = f"""# SEO strategy

## Summary

This plan covers twelve months. Everything below is spread across that year,
and we keep optimizing what is already live for the whole of it.

## What happens when

| Month | Focus | Content | Off-page | Technical |
|---|---|---|---|---|
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
gap = YEAR.replace("| 7 | Focus | 2 posts | Tier 1 | Titles |\n", "")
code, out = run(gap)
check("a month missing from the table is named",
      "skips month(s) [7]" in out, out[-700:])

# Six months is a plan too — the check must not demand twelve.
SIX = YEAR.replace("twelve months", "six months").replace("that year", "those six months")
for m in range(7, 13):
    SIX = SIX.replace(f"| {m} | Focus | 2 posts | Tier 1 | Titles |\n", "")
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
REWRITTEN = """# Phase 5 — SEO strategy

Why they are ahead: They built a page for each offence in each city. You have
not built those pages. That is the whole difference.

Location pages: Name the actual courthouse on every one. Without it the page
is a template with the city swapped, and Google treats it as one.

Internal links: Link each hub to its children using the child's own search
term. That tells Google which page owns which search.

Order of work: You named sexual assault and impaired driving as your most
profitable work. We build those pages first. A bigger search in an area you
did not name waits.

What runs all year: Four things. 100 articles, a weekly Google Business post,
monthly reporting, and the quarterly review.

Month ten: We run a second pass over everything published. Keep the pages that
moved. Rewrite the pages that did not.
"""
code, out = run(REWRITTEN)
check("the same facts, rewritten, pass clean",
      "[FAIL] Sentence shape" not in out, out[-900:])
check("  and the rewrite is recognised as scannable",
      "paragraphs lead with a label" in out, out[-900:])
check("  and its actions are recognised as instructions",
      "[OK] Actions are written as instructions" in out, out[-900:])

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
# The largest single structural difference between the two documents: 32% of
# the agency's paragraphs open with a scannable label, against 6% of ours.
WALL = "# Phase 5\n\n" + "\n\n".join([
    "We rewrite the nine service pages in month one. You have one page about "
    "bail hearings today and your competitors have between four and nine each."
] * 6)
code, out = run(WALL)
check("a document of unlabelled paragraphs is flagged as unscannable",
      "open with a scannable" in out, out[-900:])
check("  and it is a review, not a failure — some sections are narrative",
      "[REVIEW] Only" in out, out[-900:])

code, out = run(WALL)
check("prose with no imperative verbs is flagged",
      "Actions written as instructions" in out, out[-900:])

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
])
code, out = run(BOLD)
check("a bold **Label.** counts as a scannable opener",
      "% of paragraphs lead with a label" in out, out[-900:])
check("  and the paragraphs are counted, not skipped",
      "0 of 0" not in out and "Only 0%" not in out, out[-900:])

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
      "limit 8.0" in out, out[-900:])

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

print("\n-- The worked example --")
VOICE = REPO / ".claude/skills/seo-strategy-builder/assets/worked-section-house-voice.md"
check("the skill ships a worked section in the house voice", VOICE.exists())
if VOICE.exists():
    whole = VOICE.read_text(encoding="utf-8")
    check("  it shows the same findings written both ways",
          "# As it shipped" in whole and "# The same findings, in the house voice" in whole)
    rewritten = whole.split("# The same findings, in the house voice")[1].split("## What changed")[0]
    code, out = run("# Phase 2\n" + rewritten, name="voice.md")
    check("  the house-voice half passes the shape check",
          "[FAIL] Sentence shape" not in out, out[-900:])
    check("  and is scannable",
          "% of paragraphs lead with a label" in out and "[REVIEW] Only" not in out, out[-900:])
    check("  and its actions are instructions",
          "[OK] Actions are written as instructions" in out, out[-900:])
    shipped = whole.split("# As it shipped")[1].split("**What is wrong with it.**")[0]
    code, out = run("# Phase 2\n" + shipped, name="shipped.md")
    check("  the shipped half is flagged as unscannable",
          "open with a scannable" in out, out[-900:])
    check("  and the file is honest that one section is too short for the shape check",
          "too short for the sentence-shape check" in " ".join(whole.split()),
          "claiming a fail the reader cannot reproduce is how a gate loses trust")
    check("  and it records that our document scored an EASIER reading grade",
          "7.6" in whole and "10.9" in whole,
          "the whole point: simpler words did not make it readable")

# The skill has to point at it, or nothing reads it.
SKILL_MD = (REPO / ".claude/skills/seo-strategy-builder/SKILL.md").read_text(encoding="utf-8")
check("the skill tells a session to read the worked example before drafting",
      "worked-section-house-voice.md" in SKILL_MD)
check("  and says not to infer the voice from the instructions themselves",
      "not the voice of a client deliverable" in SKILL_MD,
      "this is the actual root cause: the model mirrors the register it is reading")

print()
print(f"Passed: {passed}  Failed: {failed}")
sys.exit(1 if failed else 0)
