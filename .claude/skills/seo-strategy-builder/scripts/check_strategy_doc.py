#!/usr/bin/env python3
"""
Mechanical completeness, fact-check and readability scan for an assembled SEO
strategy document (Phase 1, 2, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 17, 18, 19,
or 20 content, or any combination).

Seven kinds of thing get checked:

1. Unresolved placeholders — "[NEEDS CLIENT INPUT: ...]" or
   "[NEEDS DATA: ...]" left in the text. These are a normal, honest part of
   a draft; they should never survive into a document handed to a client.
   Their presence is a hard failure.

2. Dead-advice language — phrases pulled from references/fact-check-registry.md
   that show up in outdated SEO/schema/AI-search recommendations. These are
   reported as warnings, not hard failures, because the document might be
   correctly explaining why NOT to do something — a human needs to read the
   surrounding sentence to judge context. The point is surfacing it for
   review, not silently missing it.

3. Unreadable prose — the failure described in
   references/plain-language.md: hedged non-commitments ("consider", "where
   possible"), actions buried in nouns ("implementation of"), 45-word
   sentences, and jargon used before it is defined. A client who reads the
   document three times and still cannot say what we are going to do is the
   outcome this catches.

   One hedge is fine; a document built out of them is the thing that fails.
   So these are counted and compared against a threshold rather than failing
   on first sight — see LANGUAGE_LIMITS.

4. Audit content in a strategy document — crawl-error counts, severity
   scores, health scores, a findings table. The audit says what is wrong and
   the strategy says what we are going to do about it; a client can forward
   the second one and cannot forward the first. Reported for review, because
   one sentence of finding is often the right way to introduce a plan.

5. Page speed offered as a saving, an upsell or a separate line. Speed
   optimization is included in the SEO plan. This is a commitment to clients
   rather than a style preference, so it fails on first sight.

6. With --full, the sections a complete client-facing document has to carry.
   Off-page/backlinks and changes to pages the client already has are the two
   that go missing, and they are the two clients ask about.

7. Whether the document says how long the plan runs, that the optimizing
   continues through it, and whether its month-by-month table has a row for
   every month. A strategy that never states its term reads as a list of
   what is about to happen, which is what makes month two a surprise. Below the threshold they are
   reported for review.

Usage:
    python check_strategy_doc.py <document_file.md> [--phases 1,2,5,7,8,9]
                                                    [--no-language]
                                                    [--client "Mira Law"]

    --phases is optional. If given, the script reports which of those
    phase numbers it found a heading for, so you can confirm an assembled
    document actually contains everything that was requested.

    --no-language skips check 3. For an internal working draft, not for
    anything going to a client.

    --full additionally checks that the document carries every section a
    complete client-facing strategy needs (check 6). Leave it off for a
    partial build — a keyword refresh is not missing an off-page section,
    it was never asked for one.

Exits 0 if no unresolved placeholders are found and no language count is over
its threshold. Dead-advice warnings alone don't fail the run — they're a prompt
to review, not a blocker.
"""

import collections
import re
import sys
from pathlib import Path

PLACEHOLDER_PATTERN = re.compile(r"\[NEEDS (?:CLIENT INPUT|DATA)\s*:?[^\]]*\]", re.IGNORECASE)

# Heading lines that either mention "Phase" or start with a digit — real
# documents phrase phase headings inconsistently ("## Phase 1: ...",
# "# Foundation Document (Phase 1): ...", "# Phase 7/8: Keyword Clusters"),
# so this pulls every 1-2 digit number out of a candidate heading line
# rather than requiring one rigid position/separator. Capped at 20 (the
# highest phase number this system uses, including the recurring results
# retrospective) to avoid picking up unrelated numbers like a year.
HEADING_LINE_PATTERN = re.compile(r"^#{1,6}\s+(.*)$", re.MULTILINE)
HEADING_NUMBER_PATTERN = re.compile(r"\b(\d{1,2})\b")

# Markdown emphasis/code characters stripped before the dead-advice scan so
# a phrase wrapped in `backticks` or *asterisks* — as a schema type or a
# UI label typically would be — still matches. Placeholder detection above
# runs on the raw text and is unaffected.
MARKDOWN_NOISE_PATTERN = re.compile(r"[`*_]")

# (pattern, why it's flagged) — matched case-insensitively as a plain substring
# search, not as a claim that the phrase always appears in a dead-advice
# context. Mirrors references/fact-check-registry.md's "dead or dying" table.
DEAD_ADVICE_PATTERNS = [
    (r"howto\s*schema", "HowTo schema — fully deprecated since 2023"),
    (r"speakable\s*schema", "Speakable schema — perpetual beta, news-publisher scope only"),
    (r"aggregaterating", "AggregateRating schema — self-serving reviews on your own site are a policy violation, not just ineffective"),
    (r"llms\.txt", "llms.txt — not endorsed by Google, no confirmed AI product consumption; don't bill it as a deliverable with an expected effect"),
    (r"keyword density", "keyword density targets — no primary source supports a specific percentage"),
    (r"helpful content update|hcu (?:audit|recovery)", "HCU framing — folded into core ranking systems since 2024, nothing separate to \"recover\" from"),
    (r"cannot be measured|can't be measured", "check this isn't claiming AI-referred traffic is unmeasurable — Search Console added generative-AI reporting"),
    (r"citation volume", "citation volume as a ranking lever — citations are a hygiene floor now, not a growth lever; verify this isn't sold as a package"),
    (r"incentiviz\w* review", "incentivized reviews — violates review-platform policy outright"),
    (r"organic sessions? (?:alone|as the (?:sole|only|primary))", "organic-sessions-only reporting — can misread AI-answer-era search performance as failure"),
    (r"faqpage schema", "FAQPage schema — rich result withdrawn from Google Search; keep the FAQ content, drop the rich-result expectation"),
]

# ---------------------------------------------------------------------------
# Check 4: audit content leaking into the strategy.
#
# The audit says what is wrong; the strategy says what we are going to do
# about it. A strategy carrying crawl-error counts, severity scores and a
# findings table is not something the client can forward to their partner or
# their board — and forwarding it is most of what it is for.
#
# These are REVIEW, not FAIL. A single sentence of finding is correct and
# often necessary ("180 pages have no meta description, so month one is
# writing them"). A document built out of findings is the failure, so the
# count is what matters, as with the readability patterns.

AUDIT_LEAK_PATTERNS = [
    (r"\bcrawl errors?\b", "crawl errors — an audit finding; the strategy says what gets fixed and when"),
    (r"\bredirect chains?\b", "redirect chains — audit vocabulary; say \"pages that bounce through two addresses before loading\""),
    (r"\borphan(?:ed)? pages?\b", "orphan pages — audit vocabulary; say \"pages nothing links to\""),
    (r"\bhealth score\b", "health score — an audit score; a strategy states objectives, not grades"),
    (r"\bseverity\b", "severity rating — belongs in the audit's findings table"),
    (r"\b(?:authority|content) score\s*(?:of\s*)?\d{1,3}\s*/\s*100", "a tool's score out of 100 — third-party grade, not a client objective"),
    (r"\bfindings? table\b", "a findings table — the audit's format"),
    (r"\bthe (?:previous|former|last) agency\b", "what the previous agency did — Appendix B, if anywhere"),
    (r"\bduplicate content issues?\b", "duplicate content issues — audit vocabulary; say which pages and what changes"),
    (r"\b\d+\s*(?:x\s*)?404s?\b", "a count of 404s — an audit finding"),
]

# ---------------------------------------------------------------------------
# Check 5: page speed offered as a saving.
#
# Speed optimization is included in the SEO service. It is not an upsell and
# it is never written up as something the client is being spared because
# their site is already fast. This is a commitment to clients, so unlike
# everything else in this file it fails on first sight rather than being
# counted — one sentence of it in a document is the whole problem.

SPEED_AS_SAVING_PATTERNS = [
    r"(?:not|won'?t|will not|no)\s+(?:be\s+)?charg\w*[^.\n]{0,80}\bspeed\b",
    r"\bspeed\b[^.\n]{0,80}(?:not|won'?t|will not|no)\s+(?:be\s+)?charg\w*",
    r"\bspeed optimi[sz]ation\b[^.\n]{0,60}(?:is\s+)?(?:an?\s+)?(?:add[- ]?on|extra|upsell|optional extra|separate (?:fee|charge|cost|line item))",
    r"(?:sav\w+|discount\w*|credit\w*|deduct\w*)[^.\n]{0,80}\bspeed optimi[sz]ation\b",
    r"\bspeed optimi[sz]ation\b[^.\n]{0,60}(?:sav\w+|discount|credit)\b",
    r"(?:since|because|as)\s+your\s+(?:site|website)\s+is\s+already\s+fast[^.\n]{0,80}(?:charg|cost|fee|includ|need)",
]

# ---------------------------------------------------------------------------
# Check 7: the engagement's length, stated.
#
# A strategy arrives with a hundred blog topics and sixteen service pages and
# nowhere says that the whole thing is a year of work. The client reads it as
# a list of what is about to happen, is surprised in month two that four
# posts exist, and surprised again in month nine that there is work left.
#
# Two things have to be on the page: how long the plan runs, and that we keep
# optimizing what is already live for the whole of it. The second is the part
# clients do not assume — they read an SEO engagement as a delivery project
# that ends when the last page ships.

TERM_PATTERNS = [
    r"\b(?:twelve|12|six|6)[- ]month(?:s)?\b",
    r"\bover (?:the )?(?:next )?(?:twelve|12|six|6) months\b",
    r"\bacross (?:the )?(?:year|twelve|12|six|6)\b",
]
ONGOING_PATTERNS = [
    r"keep(?:s)? optimi[sz]ing",
    r"continue(?:s)? to optimi[sz]e",
    r"\bfor the whole of it\b",
    r"does not stop once",
    r"ongoing optimi[sz]ation",
]

# A month table: twelve (or six) rows that start with a month marker.
MONTH_ROW_RE = re.compile(r"^\s*\|\s*(?:month\s*)?(\d{1,2})\s*\|", re.IGNORECASE | re.MULTILINE)

# ---------------------------------------------------------------------------
# Check 6: the sections a full client-facing document has to carry.
#
# Only run with --full, because most requests are a slice — a keyword
# refresh, a content calendar — and failing those for missing an off-page
# section would be the gate that gets switched off. The names are matched
# loosely: any heading containing one of the alternatives counts.

REQUIRED_SECTIONS = [
    ("the priorities table", [r"what we will do first", r"\bpriorit(?:y|ies)\b"]),
    ("the month-by-month table", [r"what happens when", r"month by month", r"month-by-month",
                                  r"\bthe (?:twelve|12|six|6)[- ]month plan\b", r"\btimeline\b"]),
    ("voice and tone", [r"\bvoice\b", r"\btone\b", r"how it will sound"]),
    ("the keyword set", [r"\bkeywords?\b"]),
    ("the page plan", [r"page plan", r"page architecture", r"what gets built"]),
    ("changes to existing pages", [r"pages you already have", r"existing pages", r"changes to (?:your )?(?:current|existing)"]),
    ("links and authority", [r"\bbacklinks?\b", r"\blinks?\b.*\bauthority\b", r"off[- ]page", r"\banchor text\b"]),
    ("page speed", [r"\bpage speed\b", r"\bsite speed\b", r"core web vitals"]),
    ("objectives with baselines", [r"\bobjectives?\b", r"\btargets?\b", r"how we will know"]),
    ("reporting", [r"\breport(?:s|ing)?\b"]),
]

# ---------------------------------------------------------------------------
# Check 3: readability. references/plain-language.md is the long version; this
# is the part of it a script can see.
#
# Every one of these is counted, not failed on sight. A document is allowed a
# few — "consider" appears in honest sentences, and "implementation" is
# sometimes the right word. What is not allowed is a document made of them,
# which is the document a client reads three times without learning what we
# are going to do.

LANGUAGE_LIMITS = {
    "hedges": 5,
    "vague_action": 8,
    # A share of all sentences, not a count: see the note on LONG_SENTENCE_WORDS.
    "long_sentence_pct": 10,
    "undefined_jargon": 3,
}

# Non-commitments. Each one is a way of putting a recommendation in the
# document without committing to it, and a client reads them as padding.
HEDGE_PATTERNS = [
    (r"\bconsider(?:ing)?\s+(?:improv|optimi|add|updat|review|expand|creat|implement)", "\"consider ...\" — either we are doing it or we are not"),
    (r"\bwhere\s+(?:possible|appropriate|applicable|feasible)\b", "\"where possible\" — say which pages, or drop it"),
    (r"\bit\s+is\s+recommended\b", "\"it is recommended\" — by whom? Say \"we\""),
    (r"\bshould\s+be\s+(?:explored|considered|reviewed|investigated)\b", "\"should be explored\" — name the action and who takes it"),
    (r"\bmay\s+help\b|\bcan\s+help\s+to\b", "\"may help\" — name the mechanism instead"),
    (r"\bcould\s+potentially\b|\bpotentially\s+(?:improv|increas|boost)", "\"could potentially\" — doubled hedge"),
    (r"\blook\s+at\s+opportunities\b|\bopportunities\s+to\s+improve\b", "\"opportunities to improve\" — which ones, and are we doing them?"),
    (r"\bas\s+needed\b|\bif\s+applicable\b", "\"as needed\" — decide now, in the document"),
    (r"\baim\s+to\s+(?:improv|increas|achiev|target)", "\"aim to\" — a target is a number with a date"),
    (r"\b(?:somewhat|relatively|fairly)\s+(?:low|high|poor|strong|weak|good|slow)\b", "vague degree — give the figure"),
    (r"\ba\s+number\s+of\b|\bvarious\s+(?:pages|keywords|issues|opportunities)\b", "\"a number of\" — count them"),
    (r"\bbest\s+practices?\b", "\"best practice\" — name the practice and why it works here"),
]

# The action hidden inside a noun, or behind a verb that names nothing. Both
# produce a sentence with no subject and no deadline.
VAGUE_ACTION_PATTERNS = [
    (r"\bimplementation\s+of\b", "\"implementation of X\" → \"we add X to the 14 pages\""),
    (r"\b(?:optimi[sz]ation|utili[sz]ation)\s+of\b", "action buried in a noun — name the change"),
    (r"\b(?:creation|development|establishment|enhancement|execution|deployment|improvement|integration|expansion)\s+of\b", "action buried in a noun — name the change and who makes it"),
    (r"\bleverag(?:e|ing)\b", "\"leverage\" — say what we actually do with it"),
    (r"\bfacilitat(?:e|ing)\b", "\"facilitate\" — name the action"),
    (r"\butili[sz](?:e|ing)\b", "\"utilize\" — \"use\""),
    (r"\bholistic\b|\bsynerg(?:y|ies|istic)\b|\bparadigm\b", "buzzword with no content"),
    (r"\brobust\s+(?:strategy|framework|approach|solution)\b", "\"robust\" names nothing"),
    (r"\bwill\s+be\s+(?:optimi[sz]ed|implemented|deployed|developed|created|reviewed|updated)\b", "passive with no subject — who does it?"),
]

# Terms a client will meet again in their own analytics. They are allowed in
# the document; they are not allowed before they are explained. The rule in
# plain-language.md is a gloss in the sentence where the term first appears.
JARGON_TERMS = [
    (r"\bdomain rating\b", "Domain Rating"),
    (r"\bdomain authority\b", "Domain Authority"),
    (r"\bimpressions\b", "impressions"),
    (r"\bclick-through rate\b|\bCTR\b", "click-through rate"),
    (r"\bSERP\b", "SERP"),
    (r"\bstructured data\b|\bschema markup\b", "schema markup"),
    (r"\bcanonical\b", "canonical"),
    (r"\bcrawl budget\b", "crawl budget"),
    (r"\banchor text\b", "anchor text"),
    (r"\breferring domains?\b", "referring domains"),
    (r"\bbacklinks?\b", "backlinks"),
    (r"\bE-E-A-T\b|\bEEAT\b", "E-E-A-T"),
    (r"\btopical authority\b", "topical authority"),
    (r"\bsearch intent\b", "search intent"),
    (r"\blong-tail\b", "long-tail"),
    (r"\bcore web vitals\b", "Core Web Vitals"),
    (r"\bdisavow\b", "disavow"),
    (r"\bAEO\b|\bGEO\b(?!RGE)", "AEO / GEO"),
    (r"\bcitations?\b", "citations"),
    (r"\bkeyword difficulty\b", "keyword difficulty"),
    (r"\bindexation\b|\bdeindex\w*\b", "indexation"),
]

# What a gloss looks like: an aside set off by a dash or brackets, or a
# definition joined by one of these verbs. Deliberately generous — a false
# "this is explained" is better than nagging about a term that was explained
# in a phrasing this pattern did not anticipate.
GLOSS_MARKERS = re.compile(
    r"[—–]|\(|\bis\b|\bare\b|\bmeans\b|\bthe number of\b|, which\b",
    re.IGNORECASE,
)

# 45 was the old threshold, and it was the reason this check never fired on the
# document that caused this rewrite: that document's median sentence was 13
# words and its longest was 46. It scored Grade 8.5 on Flesch-Kincaid, against
# 11.4 for the competitor document it was measured against, and the client still
# could not read it. Short words and short sentences were never the problem.
#
# 25 is the number from references/plain-language.md, which is the number the
# competitor's own style guide states: "12-18 words average. Break any sentence
# over 25 words into two."
LONG_SENTENCE_WORDS = 25

# ---------------------------------------------------------------------------
# Check 8: sentence shape.
#
# The checks above look for bad words. This one looks at how a sentence is
# built, which is what actually made the November 2026 strategy unreadable.
# Every pattern here is grammatical, and every one makes the reader hold the
# first half of a sentence open until the second half arrives.
#
# The densities are measured per 1,000 words rather than counted, so a long
# document is not punished for being long. They are calibrated against two real
# documents: a competitor's 117-page strategy, which scores zero on the first
# three patterns, and our own, which scored 1.03, 4.27 and 1.77.

SHAPE_PATTERNS = [
    # "not", "what", "how" and "that" after the verb are the cleft. "why" and
    # "the" are not: "That gap is why the six pages come first" and "That figure
    # is the scale of the market" both read straight through, and including them
    # made this fire on clear sentences.
    (r"\b(?:The|That|What|It)\s+\w+(?:\s+\w+)?\s+is\s+(?:not|what|how|that)\b",
     "cleft — the subject arrives late. Say who does what, in that order"),
    (r"\brather than\b|\bnot\s+\w+(?:\s+\w+){0,3},?\s+but\b",
     "\"rather than\" / \"not X but Y\" — state the fact, then the contrast as its own sentence"),
    # "is what" only. "is why" and "is the one" were in the first draft of this
    # list and came straight back out: "That gap is why a search reaches them
    # and not you" and "that is the one thing we need from you" are both clear,
    # and a gate that fails clear writing gets switched off in a week.
    (r"\bis what\b",
     "\"X is what does Y\" — turn it round into an instruction"),
    (r"(?:^|(?<=[.!?]\s))(?:Where|When|Because|If|Once|While|Though|Although)\s+[^,.]{10,70},",
     "fronted subordinate clause — start the sentence with its subject"),
]

# Per 1,000 words of prose. The competitor's document sits at 0.00, 0.00, 0.00
# and 0.70. These allow some slack, because one of each in a long document is
# not what made anything unreadable.
SHAPE_LIMITS = [0.4, 1.0, 0.4, 1.0]

# A density alone cannot judge a short section: one cleft in a 140-word excerpt
# is 7 per 1,000 words, and failing that would make this script unusable on
# anything but a finished document. A construction has to be both dense AND
# repeated before it is a pattern rather than a sentence.
SHAPE_MIN_COUNT = 3

# A paragraph that opens "Label: ..." — the single biggest structural difference
# between the two documents (32% of theirs, 6% of ours). A client scanning for
# one answer finds it from the label.
#
# Two forms count, because both are scannable and both are in use:
#   "Title Tags: Format: [Primary Keyword] ..."   (the agency's form)
#   "**What we build.** 17 city-and-offence pages ..."  (bold, this repo's form)
# The bold form has to be matched BEFORE markdown stripping, or it is
# indistinguishable from an ordinary opening sentence.
LABEL_OPENER = re.compile(
    r"^(?:\*\*[A-Z][^*\n]{2,60}[.:]\*\*\s+\S"      # **Label.** or **Label:**
    r"|[A-Z][A-Za-z0-9 /&'()–-]{2,45}:\s+\S)")  # Label: ...
LABEL_TARGET_PCT = 25          # theirs is 32%; 25 leaves room for narrative sections

# Actions written as instructions. Theirs: 5.8 per 1,000 words. Ours: 0.6.
IMPERATIVE_OPENER = re.compile(
    r"(?:^|(?<=[.!?]\s))(?:Build|Add|Create|Target|Publish|Use|Write|Rewrite|Expand|"
    r"Launch|Set|Run|Match|Claim|Fix|Replace|Submit|Monitor|Track|Optimize|Link|"
    r"Name|List|Keep|Start|Send|Draft|Remove|Correct|Confirm|Ask|Check|Report)\b")
IMPERATIVE_TARGET = 3.0        # per 1,000 words; theirs is 5.8

# Flesch-Kincaid. Not a pass/fail gate on its own — the document that caused
# this rewrite scored 8.5 and was unreadable — but a document above 13 is hard
# for a reason the shape checks will not catch.
FK_CEILING = 13.0

# ── The document is for the client, not for the reviewer ───────────────────
# A strategy gets reviewed internally, the reviewer pushes back, the next draft
# answers the pushback, and the answer stays in. Ship a dozen rounds of that and
# the client receives a document arguing with somebody who is not in the room.
# The agency we are measured against does this 0 times in 5,893 words. The
# November 2026 strategy did it 18 times in 7,420.
META_COMMENTARY = [
    (r"\b(?:worth (?:your knowing|being plain about|noting|explaining)|needs explaining"
     r"|one thing (?:we checked|to be straight about)|to be (?:straight|clear) about"
     r"|the honest answer|this (?:appendix|section|table) is here)\b",
     "the document announcing its own work — state the finding, not that you looked"),
    (r"\bread (?:the|that|those)\s+[\w ]{0,24}?(?:again|together)\b"
     r"|\bwhat (?:those|these)\s+[\w ]{0,24}?add up to\b",
     "telling the reader how to read our own table — write the table so it does not need it"),
    (r"\b(?:we would rather|we state it as|anyone quoting|rather than overlooked"
     r"|not a gap in it|looks like a strength and is not|is not decoration"
     r"|that does not (?:remove|change) the work)\b",
     "defending the draft against an objection the client never raised"),
]
META_LIMIT = 0.5          # per 1,000 words; theirs is 0.00
META_MIN_COUNT = 3

# ── A strategy is not an audit ─────────────────────────────────────────────
# The audit says what is true now. The strategy says what we will do. A
# baseline is allowed only as half of a projection — beside the target it is
# measured against. A deficiency stated on its own is a finding, and findings
# belong in the audit.
AUDIT_FINDING = [
    (r"\b(?:no|not one|none of|nothing)\s+\w+(?:\s+\w+){0,3}\s+"
     r"(?:contains?|has|have|says?|links?|carr(?:y|ies)|mentions?|names?|includes?)\b",
     "a present-tense deficiency — say what we change, and in which month"),
    (r"\bis\s+(?:currently\s+)?(?:empty|unset|missing|blank|incorrect|not set)\b",
     "the current state stated as a fault — name the fix and its month"),
    (r"\b\d[\d,]*\s+words?\s+today\b|\btoday\b[^.]{0,24}\b\d[\d,]*\s+words?\b",
     "a current word count — give the target instead"),
    (r"\bscore is \d{1,3}\s*(?:out of|/)\s*\d{1,3}\b",
     "a grade on the current state — a strategy carries objectives, not scores"),
    (r"\bdoes not (?:appear|rank|convert|exist)\b",
     "a finding. Say where we expect it to appear, and by when"),
]
AUDIT_FINDING_LIMIT = 0.4     # per 1,000 words; theirs is 0.00
AUDIT_FINDING_MIN_COUNT = 3

# ── Pronouns ───────────────────────────────────────────────────────────────
# "We" says two parties are doing this together. "You" with no "we" beside it
# says one party is being assessed by another. Theirs: 5.3 "you" per 1,000
# words, and the client's firm named 7.6 times. Ours: 23.3 and 2.4.
SECOND_PERSON = re.compile(r"\b(?:you|your|you're|yours)\b", re.IGNORECASE)
SECOND_PERSON_LIMIT = 8.0     # per 1,000 words
# A density alone cannot judge a short excerpt: two "you"s in a 68-word
# section is 29 per 1,000 words, and failing that would make this script
# unusable on anything but a finished document. The benchmark document carries
# about 31 uses in total, so eight is a sample worth drawing a rate from.
SECOND_PERSON_MIN_COUNT = 8
YOUR_BUSINESS = re.compile(
    r"\byour (?:company|business|firm|practice|organisation|organization)\b", re.IGNORECASE)

# A figure with no period attached. "31,400 sessions" is not a fact until it
# says over what. Reported, never failed — too many legitimate phrasings.
METRIC_WORDS = re.compile(
    r"\b(?:sessions?|clicks?|impressions?|visits?|visitors?|users?|conversions?|leads?|calls?)\b",
    re.IGNORECASE,
)
PERIOD_WORDS = re.compile(
    r"\b(?:day|days|week|weeks|month|months|quarter|year|years|annual|monthly|weekly|daily|per month|/mo|"
    r"ytd|q[1-4]|january|february|march|april|may|june|july|august|september|october|november|december|"
    r"jan|feb|mar|apr|jun|jul|aug|sep|sept|oct|nov|dec|to date|baseline|since)\b",
    re.IGNORECASE,
)
NUMBER_RE = re.compile(r"\b\d[\d,.]*\b")

FENCED_BLOCK = re.compile(r"^```.*?^```", re.MULTILINE | re.DOTALL)
TABLE_LINE = re.compile(r"^\s*\|.*$", re.MULTILINE)
HEADING_ONLY = re.compile(r"^#{1,6}\s+.*$", re.MULTILINE)
PLACEHOLDER_FOR_PROSE = re.compile(r"\[NEEDS[^\]]*\]", re.IGNORECASE)


def prose_only(text: str, keep_markdown: bool = False) -> str:
    """Body prose, with the parts these checks would misread removed.

    Code blocks are not prose. Table rows are deliberately terse and would
    trip the sentence-length and period checks on every line. Headings get
    their own guidance in plain-language.md and are not sentences.
    """
    t = FENCED_BLOCK.sub("\n", text)
    t = TABLE_LINE.sub("", t)
    t = HEADING_ONLY.sub("", t)
    t = PLACEHOLDER_FOR_PROSE.sub("", t)
    if keep_markdown:
        return t
    return MARKDOWN_NOISE_PATTERN.sub("", t)


def sentences(text: str) -> list:
    """Rough sentence split. Good enough to count words and find a first use."""
    parts = re.split(r"(?<=[.!?])\s+|\n{2,}", text)
    return [p.strip() for p in parts if p.strip()]


def _line_of(text: str, index: int) -> int:
    return text.count("\n", 0, index) + 1


def find_phrases(text: str, patterns: list) -> list:
    """Every match of every pattern, as (line, matched text, why)."""
    hits = []
    for pattern, reason in patterns:
        for m in re.finditer(pattern, text, re.IGNORECASE):
            # Collapse internal newlines — a phrase wrapped across a line
            # break would otherwise print its own line break mid-message.
            matched = " ".join(m.group(0).split())
            hits.append((_line_of(text, m.start()), matched, reason))
    hits.sort(key=lambda h: h[0])
    return hits


def snip(s: str, width: int = 110) -> str:
    """One-line excerpt — newlines collapsed so output stays aligned."""
    s = " ".join(s.split())
    return s[:width] + ("..." if len(s) > width else "")


def find_long_sentences(text: str) -> list:
    out = []
    for s in sentences(text):
        n = len(s.split())
        if n > LONG_SENTENCE_WORDS:
            out.append((n, snip(s)))
    out.sort(key=lambda x: -x[0])
    return out


def _syllables(word: str) -> int:
    w = re.sub(r"[^a-z]", "", word.lower())
    if not w:
        return 0
    w = re.sub(r"e$", "", w)
    return max(1, len(re.findall(r"[aeiouy]+", w)))


def reading_grade(text: str):
    """Flesch-Kincaid grade level.

    Kept deliberately modest in this script's output. It is a useful sanity
    bound and a terrible gate on its own: the strategy that caused the sentence
    shape checks to exist scored 8.5 here, better than the competitor document
    it was losing to, because the formula sees only syllables and sentence
    length. It cannot see a cleft.
    """
    sents = [s for s in sentences(text) if len(s.split()) >= 3]
    words = [w for s in sents for w in s.split()]
    if not sents or not words:
        return None
    syl = sum(_syllables(w) for w in words)
    return 0.39 * len(words) / len(sents) + 11.8 * syl / len(words) - 15.59


def find_shape_problems(text: str) -> list:
    """Banned sentence constructions, as (label, count, per_1k, limit, examples)."""
    n_words = max(1, len(text.split()))
    out = []
    for (pattern, why), limit in zip(SHAPE_PATTERNS, SHAPE_LIMITS):
        hits = [snip(m.group(0), 70) for m in re.finditer(pattern, text, re.M)]
        out.append((why, len(hits), len(hits) * 1000 / n_words, limit, hits[:3]))
    return out


def paragraphs(text: str) -> list:
    """Body paragraphs. Blank-line separated, headings and tables already gone."""
    blocks = re.split(r"\n\s*\n", text)
    out = []
    for b in blocks:
        p = " ".join(b.split())
        # "* " and "- " are bullets. "**" is a bold label, which is one of the
        # two forms a scannable paragraph opener takes — excluding it made
        # label_share return "no paragraphs" on a correctly written section and
        # skip the check entirely.
        if len(p.split()) >= 12 and not re.match(r"[-*|]\s|\|", p):
            out.append(p)
    return out


def label_share(text: str):
    """What fraction of body paragraphs open with a scannable 'Label:'."""
    ps = paragraphs(text)
    if not ps:
        return None, 0, 0
    labelled = [p for p in ps if LABEL_OPENER.match(p)]
    return len(labelled) * 100 // len(ps), len(labelled), len(ps)


def imperative_density(text: str) -> float:
    n_words = max(1, len(text.split()))
    return len(IMPERATIVE_OPENER.findall(text)) * 1000 / n_words


def _density_group(text: str, patterns, limit, min_count):
    """Shared shape for the density checks: (over, per_1k, count, rows)."""
    n_words = max(1, len(text.split()))
    rows, total = [], 0
    for pattern, why in patterns:
        hits = [" ".join(m.group(0).split()) for m in re.finditer(pattern, text, re.I)]
        if hits:
            rows.append((why, len(hits), hits[:3]))
            total += len(hits)
    per_k = total * 1000 / n_words
    return (total >= min_count and per_k > limit), per_k, total, rows


def find_meta_commentary(text: str):
    return _density_group(text, META_COMMENTARY, META_LIMIT, META_MIN_COUNT)


def find_audit_findings(text: str):
    return _density_group(text, AUDIT_FINDING, AUDIT_FINDING_LIMIT, AUDIT_FINDING_MIN_COUNT)


QUOTED_BLOCK = re.compile(r"^\s*>.*$", re.MULTILINE)


def pronoun_balance(text: str, client_name: str = ""):
    """How often we address the reader, against how often we name their firm.

    Block quotes are dropped first. A strategy carries sample copy written for
    the client's OWN customers — "When you call, you speak to Mira Bhandari" —
    and that "you" is the sample doing its job, not the document addressing
    its reader. Counting it made a correct document look like it was jabbing.

    The firm's name is counted from --client when given. Without it we fall
    back to the most frequent capitalised two-word phrase, which is good
    enough to notice "we never say their name" and never used to fail.
    """
    text = QUOTED_BLOCK.sub("", text)
    n_words = max(1, len(text.split()))
    you = len(SECOND_PERSON.findall(text))
    your_business = len(YOUR_BUSINESS.findall(text))
    if client_name:
        named = len(re.findall(re.escape(client_name), text, re.I))
    else:
        caps = re.findall(r"\b[A-Z][a-z]+ (?:[A-Z][a-z]+|LLP|Law)\b", text)
        named = max(collections.Counter(caps).values()) if caps else 0
    return you * 1000 / n_words, named * 1000 / n_words, you, named, your_business


def find_undefined_jargon(text: str) -> list:
    """Terms whose FIRST appearance carries no gloss.

    Only the first use is judged. Once a term has been explained it is meant
    to be used freely, so a later bare mention is correct, not a finding.
    """
    out = []
    sents = sentences(text)
    for pattern, label in JARGON_TERMS:
        for s in sents:
            if re.search(pattern, s, re.IGNORECASE):
                if not GLOSS_MARKERS.search(s):
                    out.append((label, snip(s)))
                break  # first use only
    return out


def find_unperiodized_numbers(text: str) -> list:
    out = []
    for s in sentences(text):
        if METRIC_WORDS.search(s) and NUMBER_RE.search(s) and not PERIOD_WORDS.search(s):
            out.append(snip(s))
    return out


def load_text(path: str) -> str:
    p = Path(path)
    if not p.exists():
        print(f"ERROR: file not found: {path}")
        sys.exit(2)
    return p.read_text(encoding="utf-8")


def find_placeholders(text: str) -> list:
    return PLACEHOLDER_PATTERN.findall(text)


def find_dead_advice(text: str) -> list:
    hits = []
    lower = MARKDOWN_NOISE_PATTERN.sub("", text.lower())
    for pattern, reason in DEAD_ADVICE_PATTERNS:
        if re.search(pattern, lower):
            hits.append((pattern, reason))
    return hits


def find_audit_leakage(text: str) -> list:
    """Audit vocabulary in a document meant to be forwarded."""
    hits = []
    for pattern, reason in AUDIT_LEAK_PATTERNS:
        for m in re.finditer(pattern, text, re.IGNORECASE):
            hits.append((_line_of(text, m.start()), m.group(0), reason))
    return hits


def find_speed_as_saving(text: str) -> list:
    """Speed work offered as a discount, an extra, or a kindness."""
    hits = []
    for pattern in SPEED_AS_SAVING_PATTERNS:
        for m in re.finditer(pattern, text, re.IGNORECASE):
            hits.append((_line_of(text, m.start()), snip(m.group(0))))
    return hits


def find_missing_sections(text: str) -> list:
    """Which of the required client-facing sections have no heading."""
    headings = " \n ".join(HEADING_LINE_PATTERN.findall(text)).lower()
    missing = []
    for label, alternatives in REQUIRED_SECTIONS:
        if not any(re.search(alt, headings, re.IGNORECASE) for alt in alternatives):
            missing.append(label)
    return missing


def find_term_statement(text: str) -> dict:
    """Does the document say how long it runs, and that the work continues?"""
    said_term = any(re.search(p, text, re.IGNORECASE) for p in TERM_PATTERNS)
    said_ongoing = any(re.search(p, text, re.IGNORECASE) for p in ONGOING_PATTERNS)
    months = sorted({int(m) for m in MONTH_ROW_RE.findall(text) if 1 <= int(m) <= 24})
    return {"term": said_term, "ongoing": said_ongoing, "month_rows": months}


def find_phase_headings(text: str) -> set:
    phases = set()
    for line in HEADING_LINE_PATTERN.findall(text):
        if re.search(r"phase", line, re.IGNORECASE) or re.match(r"^\d", line.strip()):
            for n in HEADING_NUMBER_PATTERN.findall(line):
                num = int(n)
                if 1 <= num <= 20:
                    phases.add(num)
    return phases


def main():
    raw = sys.argv[1:]
    # --help is the first thing anyone tries on an unfamiliar script. Without
    # this it is read as a filename and answered with "file not found".
    if not raw or raw[0] in ("-h", "--help", "help"):
        print(__doc__.strip())
        return 0
    positional = []
    expected_phases = None
    client_name = ""
    check_language = True
    check_sections = False
    i = 0
    while i < len(raw):
        a = raw[i]
        if a == "--no-language":
            check_language = False
        elif a == "--full":
            check_sections = True
        elif a.startswith("--client"):
            # The firm's name, so the pronoun check can count how often we use
            # it against how often we say "you". Optional; without it the check
            # guesses from the most frequent capitalised pair and never fails.
            if "=" in a:
                client_name = a.partition("=")[2]
            else:
                i += 1
                client_name = raw[i] if i < len(raw) else ""
        elif a.startswith("--phases"):
            if "=" in a:
                val = a.partition("=")[2]
            else:
                i += 1
                val = raw[i] if i < len(raw) else ""
            try:
                expected_phases = {int(x.strip()) for x in val.split(",") if x.strip()}
            except ValueError:
                pass
        else:
            positional.append(a)
        i += 1

    if len(positional) != 1:
        print(__doc__)
        sys.exit(2)

    doc_path = positional[0]

    text = load_text(doc_path)
    print(f"Checking {doc_path}\n")

    failures = 0

    # Unresolved placeholders
    placeholders = find_placeholders(text)
    if placeholders:
        print(f"[FAIL] {len(placeholders)} unresolved placeholder(s) found — resolve these before treating the document as client-ready:")
        for ph in placeholders[:20]:
            print(f"       {ph}")
        if len(placeholders) > 20:
            print(f"       ... and {len(placeholders) - 20} more")
        failures += 1
    else:
        print("[OK] No unresolved [NEEDS CLIENT INPUT] / [NEEDS DATA] placeholders found")

    # Dead-advice scan
    dead_hits = find_dead_advice(text)
    if dead_hits:
        print(f"\n[REVIEW] {len(dead_hits)} possible dead-advice phrase(s) detected — read the surrounding context, this may be a correct explanation of what NOT to do:")
        for pattern, reason in dead_hits:
            print(f"       - {reason}")
    else:
        print("\n[OK] No dead-advice language detected from the fact-check registry")

    # Readability
    if check_language:
        body = prose_only(text)
        # label_share needs the markdown still on it: a bold label is
        # indistinguishable from an ordinary sentence once ** is stripped.
        body_md = prose_only(text, keep_markdown=True)
        groups = [
            ("hedges", "Hedged non-commitment", find_phrases(body, HEDGE_PATTERNS)),
            ("vague_action", "Action buried in a noun, or nobody doing it",
             find_phrases(body, VAGUE_ACTION_PATTERNS)),
        ]
        for key, title, hits in groups:
            limit = LANGUAGE_LIMITS[key]
            if not hits:
                print(f"\n[OK] {title}: none found")
                continue
            over = len(hits) > limit
            tag = "FAIL" if over else "REVIEW"
            print(f"\n[{tag}] {title}: {len(hits)} (threshold {limit})")
            seen = set()
            for line, matched, reason in hits[:12]:
                if reason in seen:
                    print(f"       line {line}: \"{matched}\"")
                else:
                    print(f"       line {line}: \"{matched}\" — {reason}")
                    seen.add(reason)
            if len(hits) > 12:
                print(f"       ... and {len(hits) - 12} more")
            if over:
                failures += 1

        # Share, not count. At a 25-word threshold a long document legitimately
        # carries more than five, and a count would punish length instead of
        # shape. The competitor's document runs 8%; ours ran 16%.
        longs = find_long_sentences(body)
        all_sents = [s for s in sentences(body) if len(s.split()) >= 3]
        pct = len(longs) * 100 // max(1, len(all_sents))
        limit = LANGUAGE_LIMITS["long_sentence_pct"]
        if longs:
            over = pct > limit
            print(f"\n[{'FAIL' if over else 'REVIEW'}] Sentences over {LONG_SENTENCE_WORDS} words: "
                  f"{len(longs)} of {len(all_sents)} ({pct}%, threshold {limit}%)")
            for n, snippet in longs[:6]:
                print(f"       {n} words: {snippet}")
            if len(longs) > 6:
                print(f"       ... and {len(longs) - 6} more")
            if over:
                failures += 1
        else:
            print(f"\n[OK] No sentence over {LONG_SENTENCE_WORDS} words")

        # ── Sentence shape ───────────────────────────────────────────────
        # The checks above look for bad words. These look at how a sentence is
        # built, which is what the client could not read.
        shape = find_shape_problems(body)
        shape_over = [row for row in shape if row[1] >= SHAPE_MIN_COUNT and row[2] > row[3]]
        if shape_over:
            print(f"\n[FAIL] Sentence shape — {len(shape_over)} construction(s) over the limit "
                  f"(see references/plain-language.md):")
            for why, n, per_k, limit_k, examples in shape_over:
                print(f"       {per_k:.2f} per 1k words (limit {limit_k:.2f}, {n} found) — {why}")
                for e in examples:
                    print(f"           …{e}…")
            failures += 1
        else:
            print("\n[OK] Sentence shape: no clefts, fronted clauses or \"rather than\" pile-ups")

        # ── The document is for the client, not for the reviewer ─────────
        over, per_k, total, rows = find_meta_commentary(body)
        if rows:
            print(f"\n[{'FAIL' if over else 'REVIEW'}] The document is explaining or defending itself: "
                  f"{total} ({per_k:.2f} per 1k, limit {META_LIMIT})")
            for why, n, examples in rows:
                print(f"       {n} × {why}")
                for e in examples:
                    print(f"           …{e[:72]}…")
            print("       A client did not attend the review and does not need the transcript.")
            if over:
                failures += 1
        else:
            print("\n[OK] Nothing in the document is arguing with its reviewer")

        # ── A strategy is not an audit ───────────────────────────────────
        over, per_k, total, rows = find_audit_findings(body)
        if rows:
            print(f"\n[{'FAIL' if over else 'REVIEW'}] Audit findings in a strategy document: "
                  f"{total} ({per_k:.2f} per 1k, limit {AUDIT_FINDING_LIMIT})")
            for why, n, examples in rows:
                print(f"       {n} × {why}")
                for e in examples:
                    print(f"           …{e[:72]}…")
            print("       A baseline is allowed beside the target it is measured against,")
            print("       and nowhere else. See references/plain-language.md, Rule 3.")
            if over:
                failures += 1
        else:
            print("\n[OK] No audit findings — this reads as a plan, not a verdict")

        # ── Pronouns ─────────────────────────────────────────────────────
        you_k, named_k, you_n, named_n, your_biz = pronoun_balance(body, client_name)
        if your_biz:
            print(f"\n[FAIL] \"your company\" / \"your business\" × {your_biz} — use the firm's name")
            failures += 1
        if you_n >= SECOND_PERSON_MIN_COUNT and you_k > SECOND_PERSON_LIMIT:
            print(f"\n[FAIL] \"you\" / \"your\" {you_k:.1f} per 1k words "
                  f"({you_n} uses, limit {SECOND_PERSON_LIMIT})")
            print("       \"We\" for the work we do together. The firm's name for facts.")
            print("       \"You\" only for what is genuinely theirs — your name, your office.")
            failures += 1
        elif named_k and named_k < you_k and you_n >= SECOND_PERSON_MIN_COUNT:
            print(f"\n[REVIEW] \"you\" {you_k:.1f} per 1k against the firm's name {named_k:.1f} per 1k")
            print("       Name them at least as often as you address them.")
        else:
            print(f"\n[OK] Pronouns balanced (\"you\" {you_k:.1f} per 1k, the firm named {named_k:.1f})")

        # ── Scannability ─────────────────────────────────────────────────
        pct_lab, n_lab, n_par = label_share(body_md)
        if pct_lab is not None and pct_lab < LABEL_TARGET_PCT:
            print(f"\n[REVIEW] Only {pct_lab}% of paragraphs open with a scannable \"Label:\" "
                  f"({n_lab} of {n_par}, target {LABEL_TARGET_PCT}%+)")
            print("       A client looking for one answer has to read everything to find it.")
            print("       Lead every paragraph that carries instructions with what it is about.")
        elif pct_lab is not None:
            print(f"\n[OK] {pct_lab}% of paragraphs lead with a label — the document is scannable")

        imp = imperative_density(body)
        if imp < IMPERATIVE_TARGET:
            print(f"\n[REVIEW] Actions written as instructions: {imp:.1f} per 1k words "
                  f"(target {IMPERATIVE_TARGET}+)")
            print("       \"Build the page\", not \"a page will be built\" or \"the work involves\".")
        else:
            print(f"\n[OK] Actions are written as instructions ({imp:.1f} per 1k words)")

        grade = reading_grade(body)
        if grade is not None:
            if grade > FK_CEILING:
                print(f"\n[REVIEW] Reading grade {grade:.1f} (target 9–10, ceiling {FK_CEILING})")
            else:
                print(f"\n[OK] Reading grade {grade:.1f}")
            print("       Note: this number is a weak signal. The strategy that forced these")
            print("       shape checks scored 8.5 and the client still could not read it.")

        jargon = find_undefined_jargon(body)
        limit = LANGUAGE_LIMITS["undefined_jargon"]
        if jargon:
            over = len(jargon) > limit
            print(f"\n[{'FAIL' if over else 'REVIEW'}] Terms used before being explained: "
                  f"{len(jargon)} (threshold {limit}) — each needs a plain-words gloss "
                  f"in the sentence where it first appears")
            for label, snippet in jargon[:10]:
                print(f"       {label}: {snippet}")
            if len(jargon) > 10:
                print(f"       ... and {len(jargon) - 10} more")
            if over:
                failures += 1
        else:
            print("\n[OK] Every tracked term is explained where it first appears")

        bare = find_unperiodized_numbers(body)
        if bare:
            print(f"\n[REVIEW] {len(bare)} figure(s) with no period attached — a number "
                  f"is not a fact until it says over what:")
            for snippet in bare[:6]:
                print(f"       {snippet}")
            if len(bare) > 6:
                print(f"       ... and {len(bare) - 6} more")

    # ── Audit content leaking into a client document ─────────────────────
    leaks = find_audit_leakage(text)
    if leaks:
        print(f"\n[REVIEW] {len(leaks)} phrase(s) that read as audit findings rather than plan — "
              f"the client should be able to forward this document unedited:")
        seen = set()
        for line, matched, reason in leaks[:12]:
            if reason in seen:
                print(f"       line {line}: \"{matched}\"")
            else:
                print(f"       line {line}: \"{matched}\" — {reason}")
                seen.add(reason)
        if len(leaks) > 12:
            print(f"       ... and {len(leaks) - 12} more")
        print("       One sentence of finding is fine. A document built of them belongs in the audit.")
    else:
        print("\n[OK] No audit vocabulary detected — this reads as a plan, not a findings list")

    # ── Speed offered as a saving ────────────────────────────────────────
    # A commitment to clients, so this fails rather than being counted.
    speed = find_speed_as_saving(text)
    if speed:
        print(f"\n[FAIL] Page speed is written up as a saving, an extra or a discount, in "
              f"{len(speed)} place(s). Speed optimization is included in the SEO plan — it is "
              f"never charged separately and never offered as something the client is being spared:")
        for line, snippet in speed[:6]:
            print(f"       line {line}: {snippet}")
        failures += 1
    else:
        print("[OK] Page speed is not offered as a saving or an upsell")

    # ── How long it runs, and that it keeps running ──────────────────────
    term = find_term_statement(text)
    if not term["term"]:
        print("\n[REVIEW] The document never says how long the plan is. A client reads a "
              "hundred topics as a list of what is about to happen unless the term is stated "
              "on page one.")
    elif not term["ongoing"]:
        print("\n[REVIEW] The term is stated, but nothing says the optimizing continues "
              "through it. Clients read an SEO engagement as a delivery project that ends "
              "when the last page ships — say that it does not.")
    else:
        print("\n[OK] The plan's length is stated, and that the work continues through it")

    rows = term["month_rows"]
    if rows:
        expected = list(range(1, max(rows) + 1))
        missing = [m for m in expected if m not in rows]
        if missing:
            print(f"[REVIEW] The month table skips month(s) {missing} — a month with nothing "
                  f"in it is a month the client will ask about.")
        else:
            print(f"[OK] A month-by-month table covering months 1-{max(rows)}")
    elif check_sections:
        print("[REVIEW] No month-by-month table found. Every strategy carries one row per "
              "month with counts rather than names — see references/engagement-plans.md.")

    # ── The sections a full document carries ─────────────────────────────
    if check_sections:
        missing = find_missing_sections(text)
        if missing:
            print(f"\n[FAIL] A full strategy document is missing {len(missing)} section(s) "
                  f"the client needs:")
            for label in missing:
                print(f"       - {label}")
            print("       See references/document-assembly.md for the table of contents. "
                  "Off-page and existing-page changes are the two that go missing most.")
            failures += 1
        else:
            print("\n[OK] Every required client-facing section has a heading")

    # Phase coverage (informational)
    found_phases = find_phase_headings(text)
    if found_phases:
        print(f"\n[INFO] Phase headings detected: {sorted(found_phases)}")
    if expected_phases:
        missing = expected_phases - found_phases
        if missing:
            print(f"[INFO] Requested phases with no detected heading: {sorted(missing)} — confirm these were actually included")
        else:
            print("[INFO] All requested phases have a detected heading")

    print()
    if failures:
        print(f"{failures} check(s) failed. Resolve unresolved placeholders and rewrite the flagged prose "
              f"before calling this document done. Also read through any REVIEW items above — this script "
              f"cannot tell context from content.")
        sys.exit(1)
    else:
        print("No unresolved placeholders, and the language counts are under their thresholds. Still read "
              "through any REVIEW items above, confirm phase coverage matches what was requested, and hand it "
              "to somebody who was not in the research — if they can't say what we're going to do, no script "
              "caught that.")
        sys.exit(0)


if __name__ == "__main__":
    main()
