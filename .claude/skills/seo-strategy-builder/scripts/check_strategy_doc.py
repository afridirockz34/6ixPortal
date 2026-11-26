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
    #
    # This was 10%, and it was wrong. The client-letter benchmark in
    # assets/client-letter-benchmark.md runs 24% of sentences over 25 words,
    # averages 19.2 words and peaks at 42 — and it is the document the client
    # asked us to write like. Our own rewrite ran 3% over 25 words, averaged
    # 14.5, and he gave up on it. A 10% gate on a 25-word ceiling is what
    # produced the telegram.
    "long_sentence_pct": 30,
    # Raised from 3 after the benchmark tripped it on "search intent", which
    # that very sentence explains by example, and on "citations", which is
    # glossed one sentence later. Three strikes in a 60-page document is not
    # a pattern.
    "undefined_jargon": 6,
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
# 25 was the number, taken from a competitor's internal style guide: "12-18
# words average. Break any sentence over 25 words into two."
#
# It came out. That guide governs an agency-audience audit — a spec sheet. The
# strategy document is a letter to a frightened non-specialist, and the
# client-letter benchmark we are now measured against writes 42-word sentences
# that read straight through:
#
#   "There is quite a bit to cover, but we wanted to provide enough
#    information for you to understand where the website currently stands,
#    what opportunities we've identified, and how we plan to position Mira Law
#    competitively across Toronto and the surrounding cities."
#
# Nothing is gained by cutting that into three. Length was never the fault;
# shape was, and the SHAPE_PATTERNS below are what catch shape. 40 words is
# where a sentence genuinely starts costing a re-read.
LONG_SENTENCE_WORDS = 40

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

# ── Labels: a ceiling, and no floor ───────────────────────────────────────
#
# This block used to carry a FLOOR of 25% labelled paragraphs, on the evidence
# that a competitor's audit ran 32%. That was the most damaging line in this
# file, and it is now inverted. Two reasons, both measured.
#
# 1. That competitor document is an AUDIT written for an agency audience. It
#    is a reference work, and a reference work is meant to be looked things up
#    in. The strategy document is a letter the client reads front to back and
#    forwards to a partner. The client-letter benchmark in
#    assets/client-letter-benchmark.md labels 3% of its paragraphs, and it is
#    the document the client told us to write like.
# 2. The floor had no effect on the thing it was aimed at and every effect on
#    readability. Our own strategy scored 0% here while 44% of its paragraphs
#    actually opened with a label — because LABEL_OPENER only matched a colon
#    or a bold run, and our habit is a bare sentence fragment and a full stop:
#    "The year's totals. The plan delivers ...". The gate was blind to the
#    pattern it was supposed to govern, so the model kept scoring 0, kept
#    being told to add labels, and kept adding more of them.
#
# So: no floor, a ceiling, and STUB_OPENER below catches the form that was
# invisible. Labels still earn their place in a specification block — a meta
# standards row, a schema table. They do not belong on narrative paragraphs.
LABEL_OPENER = re.compile(
    r"^(?:\*\*[A-Z][^*\n]{2,60}[.:]\*\*\s+\S"      # **Label.** or **Label:**
    r"|[A-Z][A-Za-z0-9 /&'()–-]{2,45}:\s+\S)")  # Label: ...

# The form the old check could not see: a fragment, a full stop, then the real
# sentence. "The year's totals. The plan delivers 4 service pages ..." Twenty
# of our forty-five paragraphs opened this way; two of the benchmark's
# sixty-six did.
#
STUB_OPENER = re.compile(
    r"^(?:\*\*)?([A-Z][^.!?:\n]{2,70}?)(?:\*\*)?\.\s+[A-Z]")

# Identifying a fragment needs care, and the first attempt here got it wrong
# in a way worth recording. It used a whitelist of verbs and asked whether the
# opener contained one. That misfired twice on correct prose:
#
#   "SEO isn't a one-time process."              -> "isn't" is not "is", so
#                                                   \bis\b never matched
#   "The profile matters more than the website." -> "matters" was not on the
#                                                   list, and no list is
#
# Both are complete sentences and both were reported as fragments. A gate that
# fails good writing gets switched off, so this is now built the other way
# round: narrow, with the length doing most of the work.
#
# A label is short. "The year's totals", "The wider GTA", "Link text plan",
# "The blog topics" — all three or four words. A real opening sentence that
# happens to be terse is usually longer than six, and when it is not it almost
# always carries an auxiliary. Six words plus the auxiliary test catches the
# habit and leaves correct prose alone; a couple of longer stubs slip through
# and are caught by the imperative ceiling instead. That trade is the right
# way round.
STUB_MAX_WORDS = 6
# Auxiliaries and copulas, contractions included — "isn't", "doesn't",
# "won't", "it's", "that's". Mandating contractions elsewhere in this file
# while a check here cannot read them is exactly the kind of blind spot that
# produced the label problem.
STUB_VERB = re.compile(
    r"\b(?:is|are|was|were|be|been|am|has|have|had|will|would|shall|should|"
    r"can|could|may|might|must|do|does|did|goes|go|went)\b"
    # "'s" is the trap: "it's" is a verb and "the year's totals" is a
    # possessive, and a bare \w+'s clears both. It cleared "The year's
    # totals." — the exact example printed in this check's own message. So
    # only the pronouns and determiners that actually contract are listed.
    r"|\b(?:it|that|there|here|what|who|he|she|this|one|everything|nothing|"
    r"something|someone|nobody)(?:'|’)s\b"
    r"|\b\w+(?:'|’)(?:re|ve|ll|d)\b"              # we've, you'll, they'd
    r"|\b(?:is|are|was|were|has|have|had|does|do|did|wo|ca|could|should|"
    r"would|must|need|might)(?:n(?:'|’)?t)\b"     # isn't, won't, can't
    r"|\b\w{3,}(?:ed|ing)\b",                          # ranked, ranking
    re.IGNORECASE)
# Benchmark 3%, ours 44%. Ten leaves room for a genuine specification block
# without licensing the habit.
STUB_CEILING_PCT = 10
# Below this many paragraphs a percentage is noise.
STUB_MIN_PARAS = 12
LABEL_CEILING_PCT = 15

# ── One idea, one paragraph ───────────────────────────────────────────────
#
# The structural finding nobody was looking for. The benchmark runs 1.1
# sentences per paragraph: almost every sentence gets its own line and its own
# air. Ours runs 2.8, because plain-language.md said "two to four sentences,
# then stop" — and once the paragraphs were dense, labels got bolted on to
# make them scannable again. The labels were a symptom.
#
# In a 60-page document this is the single thing that decides whether anyone
# reaches the end.
SENTS_PER_PARA_CEILING = 1.9
SENTS_PER_PARA_MIN_PARAS = 15

# Two facts side by side with nothing between them make the reader build the
# argument themselves. This was a floor of 0.35 per paragraph. The benchmark
# runs 0.03 — with one sentence per paragraph, the connective moves to the
# start of the next sentence ("This means ...", "To put things into
# perspective ...") where the old pattern could not see it. Kept as an
# informational count only; it never gates anything now.
CONNECTIVE = re.compile(
    r"\b(?:because|so that|which means|which is why|so we|and so|therefore|"
    r"that is why|which lets|which gives|in order to|this means|which is)\b",
    re.IGNORECASE)

# ── Bare imperatives: a ceiling, and no floor ─────────────────────────────
#
# Also inverted. This carried a floor of 3.0 per 1,000 words against a
# competitor's 5.8, and our document duly hit 8.9 and shipped sentences like
# "Profile the render path in month 4, then defer the scripts that block it."
# That is a line from an internal worklist sitting in a client document.
#
# The benchmark runs 0.0. It writes "we'll develop", "we'll prioritize",
# "we'll optimize" — a commitment with somebody attached to it. A bare
# imperative in a client letter reads as a note to the implementer, because
# that is what it is.
#
# Imperatives remain correct inside a specification row ("Under 60
# characters", "Add nine services to the profile"), which is why this measures
# prose only and allows a non-zero rate.
#
# re.M matters here and its absence was a silent hole. Without it, `^` only
# anchors at the very start of the document and the lookbehind only fires
# mid-paragraph, because a paragraph break is "\n\n" and not "[.!?]\s". A
# document of nothing but imperatives, one per paragraph, scored 0.0.
IMPERATIVE_OPENER = re.compile(
    r"(?:^|(?<=[.!?]\s)|(?<=[.!?]\n))(?:Build|Add|Create|Target|Publish|Use|Write|Rewrite|"
    r"Expand|Launch|Set|Run|Match|Claim|Fix|Replace|Submit|Monitor|Track|Optimize|Link|"
    r"Name|List|Keep|Start|Send|Draft|Remove|Correct|Confirm|Ask|Check|Report|"
    r"Profile|Rotate|Join|Delete|Resolve|Hold|Leave|Cut|Take|Make|Read|Say|Measure)\b",
    re.MULTILINE)
IMPERATIVE_CEILING = 2.0       # per 1,000 words of prose; benchmark 0.0, ours 6.1
IMPERATIVE_MIN_COUNT = 4

# ── Contractions ──────────────────────────────────────────────────────────
#
# The benchmark: 24.5 per 1,000 words — we'll, we've, I've, isn't, you'll.
# Ours: 6.1, and not one "we'll" in 4,000 words against twelve "We will".
#
# This is a large share of "humans don't talk like this". A document with no
# contractions in it reads as a notice, and nobody speaks that way about work
# they are about to start together. Nothing here licenses sloppiness: the
# benchmark is a professional document and it contracts constantly.
CONTRACTION = re.compile(r"\b\w+(?:'|’)(?:ll|ve|re|s|t|d|m)\b", re.IGNORECASE)
CONTRACTION_MIN_PER_1K = 10.0
CONTRACTION_MIN_WORDS = 400

# Flesch-Kincaid. Informational only, and no longer compared against a target.
# The benchmark scores 14.5 and reads easily; the document the client gave up
# on scored 7.0. The metric cannot see sentence shape, which is the only thing
# that mattered, and printing a target next to it invited writing toward the
# number. It is printed so a wild outlier is visible, and nothing more.
FK_REPORT_ONLY = True

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
    # Up to 60 characters between the negative and its verb, because the thing
    # being negated is often a domain or a qualified noun phrase: "No page
    # title on miralaw.ca contains the word lawyer" slipped straight through a
    # \w+-only version of this.
    # A dot is allowed mid-match when it sits inside a word, because the thing
    # being negated is usually a domain: excluding "." outright meant "No page
    # title on miralaw.ca contains the word lawyer" never matched.
    (r"\b(?:no|not one|not a single|none of|nothing)\b(?:[^.!?\n]|\.(?=[a-z])){0,60}?\b"
     r"(?:contains?|has|have|says?|links?|carr(?:y|ies)|mentions?|names?|includes?|convert(?:s|ed)?)\b",
     "a present-tense deficiency — say what we change, and in which month"),
    (r"\b(?:is|are) (?:currently )?(?:invisible|not competing|nowhere|absent)\b",
     "a verdict on the present — name the position we are going after instead"),
    (r"\b(?:produced|drew|took|earned|returned) no\b|\bnot one came\b",
     "a measured absence with no target beside it"),
    (r"\b(?:is|are)\s+(?:currently\s+)?(?:empty|unset|missing|blank|incorrect|not set)\b",
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

# ── The structural half of Rule 3 ──────────────────────────────────────────
# The phrase list above was fitted to one document and caught 1 of 8 findings
# in the next one. Phrasings are endless; the structure is not. The rule is
# "a current-state figure appears beside the target it is measured against",
# so what actually needs checking is whether a section reports the present
# and commits to nothing.
#
# A heading that announces the current state is an audit section whatever the
# sentences inside it say. "Where the firm stands today" is a findings
# chapter; the strategy's version of that content is an objectives table with
# a Target column.
AUDIT_HEADING = re.compile(
    r"\b(?:where (?:the firm|we|they|things|you) (?:stand|stands|are)(?!\s+(?:going|headed|heading|taking))"
    r"|where (?:the firm|we|they|you) (?:is|are) (?:now|today)"
    r"|current (?:state|position|performance|standing|situation)"
    r"|the (?:current )?baseline"
    r"|what we found|our findings|the findings"
    r"|today'?s? (?:numbers|position|performance)"
    r"|how (?:the firm|the site|things) (?:is|are) performing)\b", re.IGNORECASE)

# "Finding" is the audit's own word for its own content. A plan has
# priorities, targets and months.
AUDIT_VOCAB_HARD = re.compile(r"\bthe finding\b|\bour finding\b|\bkey finding\b|\bfindings?\b(?!\s+table)",
                              re.IGNORECASE)

# A figure that describes the present.
# Deliberately NOT "N searches a month". That is market demand — how many
# people type a phrase — and it is the raw material of a plan, not a
# measurement of the client. Counting it made this fire on the keyword
# opportunity table, which is the most forward-looking thing in the document.
MEASURED_NOW = re.compile(
    r"\b\d[\d,]*(?:\.\d+)?\s*%?\s*(?:clicks?|visits?|visitors?|sessions?|enquiries|inquiries|"
    r"impressions?|times shown|seconds?|reviews?|listings?)\b"
    r"|\b(?:average )?position\s+\d"
    r"|\b\d[\d,]*\s+(?:of|out of)\s+\d", re.IGNORECASE)

# A commitment: a month, a target, or something we will do.
# A commitment: a month, a target, or something that will be done. The bare
# verbs matter as much as the "we ..." forms, because a "What we do" column
# in a table reads "Retitle and expand the existing page" with no subject —
# and that is a commitment, not a finding.
FORWARD_MARKER = re.compile(
    r"\bmonth\s*\d|\bby month\b|\bmonths?\s+\d+\s*[\u2013-]\s*\d"
    r"|\btargets?\b"
    r"|\b(?:we\s+)?(?:build|write|add|publish|rewrite|retitle|expand|split|merge|redirect|"
    r"remove|claim|correct|set|fix|start|take|get|run|ship|fold)(?:s|ed|ing)?\b"
    r"|\bby (?:January|February|March|April|May|June|July|August|September|October|November|December)\b",
    re.IGNORECASE)

# Three measurements with nothing committed is a findings section. Two could
# be scene-setting in a section that then gets on with the plan.
AUDIT_SECTION_MIN_FIGURES = 3


# ── Future work is written in the future tense ────────────────────────────
# "We optimize the website in month one" is the present tense describing
# something that has not happened. A reader stops on it. The spec used to say
# imperatives and "not we will be building", and the imperative metric scored
# "we build" 1 and "we will build" 0 — so both the written rule and the
# measured one were training the document out of the future tense.
#
# Imperatives stay correct in a specification row ("Add nine services"). A
# sentence about what happens in a named month takes "will".
MISSING_WILL = re.compile(
    r"\bwe\s+(?!will\b|have\b|had\b|are\b|were\b|do\b|did\b|can\b|cannot\b|already\b|also\b)"
    r"(build|write|add|publish|rewrite|optimi[sz]e|correct|claim|fix|set|take|get|run|ship|"
    r"start|send|draft|remove|expand|split|merge|redirect|track|monitor|report|measure|re-measure)"
    r"\b[^.!?]{0,80}?\b(?:in|from|by|during|across)\s+month", re.IGNORECASE)
MISSING_WILL_LIMIT = 2

# ── Internal shorthand in the month table ─────────────────────────────────
# "4 Tier 1 listings". "Q&A seeded". "Profile photo set expanded". "Speed
# work: the 9.0-second render path". Every one is planning vocabulary, and the
# month table is the first thing a client reads.
#
# The generic version of this check — words that appear in a table and nowhere
# in the prose — was tried and thrown away: it flagged 188 words in a real
# document, most of them page names and ordinary English. There is no
# mechanical signal that separates "4 location pages" from "Q&A seeded".
#
# So this is a blocklist, and a blocklist of OUR shorthand rather than of
# jargon in general. It will not catch shorthand we have not invented yet.
# The thing that actually governs this is the worked example and the rule in
# plain-language.md; this list is the floor under them. When a new piece of
# shorthand reaches a client, add it here.
MONTH_TABLE_SHORTHAND = [
    (r"\bTier\s*\d\b", "\"Tier 1\" means nothing to a client — say what the listing is"),
    (r"\bQ&?A\s+seed(?:ed|ing)?\b", "\"Q&A seeded\" — say \"we write and answer eight common questions\""),
    (r"\brender path\b", "\"render path\" — say \"how long the page takes to appear\""),
    (r"\bcitable\b", "\"citable resource\" — say what it is and who would link to it"),
    (r"\banchor text\b|\blink text repaired\b", "say what the words in a link will say"),
    (r"\b(?:photo set|asset set)\s+expanded\b", "say how many photographs, and of what"),
    (r"\bseeded\b|\bre-?measured\b(?!\s+(?:in|on|at))", "planning vocabulary — say who does what"),
    (r"\bdisavow\b(?![^.]{0,40}(?:\u2014|-|is |means ))", "\"disavow\" needs its one-line gloss on first use"),
    (r"\bE-?E-?A-?T\b(?![^.]{0,40}(?:\u2014|-|is |means ))", "spell it out on first use"),
    (r"\bGBP\b", "\"GBP\" reads as pounds sterling — write Google Business Profile"),
]
MONTH_TABLE_SHORTHAND_LIMIT = 0

# ── The monthly plan: six prohibitions ────────────────────────────────────
#
# The first table the client reads, and the one they came back on hardest
# after four drafts. Every pattern below was in a rejected draft, and the
# reasoning is in references/document-assembly.md under "The monthly plan".
#
# Two separate faults, kept apart because they have different fixes:
#
#   1. Reporting in a delivery plan. A half-year review, a quarterly review,
#      re-running the map grid, setting next year's priorities — none of
#      these is somebody doing something to the site that month. They belong
#      in the reporting section. Re-OPTIMISING a published page is not in
#      this list: that is a month of real work and it stays.
#
#   2. Work that waits on the client. Photographs, logins, approvals, the
#      firm's own memberships. A month beside one of those is a promise we
#      do not control, and the plan is then late in month two through
#      nobody's fault.
MONTH_PLAN_REPORTING = [
    (r"\bhalf[- ]year review\b", "a half-year review is reporting, not work on the site"),
    (r"\bquarterly (?:strategy )?review\b", "the quarterly review belongs in the reporting section"),
    (r"\byear(?:ly)? review\b|\bthe year review\b", "the year review belongs in the reporting section"),
    (r"\bset the priorities for year two\b|\bpriorities for year two\b", "planning next year is not this year's work"),
    (r"re-?run the map (?:ranking|grid)|map (?:ranking|grid) (?:check|re-?run)",
     "the map grid is re-checked every month — say so once in the reporting section"),
    (r"\bmonthly report\b|\breporting:\s", "reports are not a month's deliverable in this table"),
]
# Client-dependent work. Matched anywhere a month is attached, not only in
# the monthly plan, because the Links and Authority table had a Month column
# full of the firm's own memberships.
MONTH_PLAN_CLIENT_DEPENDENT = [
    (r"\bphotograph", "photographs come from the client, so we cannot date them"),
    (r"\bmembership", "the firm's memberships wait on their login details"),
    (r"\b(?:manager )?access\b|\blogin\b|\bcredential", "access waits on the client"),
    (r"\bapprov(?:e|al)\b|\bsign-?off\b", "an approval waits on the client"),
    (r"\bfeedback (?:on|within)\b", "we do not schedule the client's own replies"),
]
MONTH_PLAN_LIMIT = 0

# The columns, in order. Month, Writing, What we work on — asked for in
# those words. The rejected drafts carried a "Links and listings" column and
# a separate "Website and Google profile" column; both are part of what we
# work on, and splitting them made a five-column table nobody could read.
MONTH_PLAN_HEADER = re.compile(
    r"^\|\s*#?\s*Month\s*\|\s*Writing\s*\|\s*What we work on\s*\|", re.IGNORECASE | re.MULTILINE)
MONTH_PLAN_BANNED_COLS = [
    (r"\|\s*Links and listings\s*\|", "the \"Links and listings\" column — fold it in as \"Listings:\""),
    (r"\|\s*Website and Google profile\s*\|", "\"Website and Google profile\" IS what we work on"),
]

# Month one has to show the client something. GMB posting is the one thing
# they can open their phone and look at, so it starts in month one; a first
# month of titles, schema and internal links reads as a month of nothing.
MONTH_ONE_VISIBLE = re.compile(
    r"\bGMB\b[^|]{0,200}?\bpost", re.IGNORECASE)

# ── Pink ──────────────────────────────────────────────────────────────────
# #FF6699 is the portal's interface colour. It is not a document colour, and
# the client asked for it out of everything we generate.
PINK = re.compile(r"#(?:ff6699|FF6699|ff66|e91e63|ff69b4)\b|\bpink\b", re.IGNORECASE)

# ── Pronouns ───────────────────────────────────────────────────────────────
# "We" says two parties are doing this together. "You" with no "we" beside it
# says one party is being assessed by another. Theirs: 5.3 "you" per 1,000
# words, and the client's firm named 7.6 times. Ours: 23.3 and 2.4.
SECOND_PERSON = re.compile(r"\b(?:you|your|you're|yours)\b", re.IGNORECASE)
# Raised from 8.0. The benchmark runs 3.0 and uses "you" warmly — "enough
# information for you to understand", "you'll receive monthly performance
# reports", "based on our research and your feedback". The word was never the
# problem; institutional coldness was, and that is what the contraction floor
# and the stub ceiling now catch. A limit tight enough to make a writer avoid
# "you'll receive" is a limit working against the voice we want.
SECOND_PERSON_LIMIT = 14.0    # per 1,000 words
# A density alone cannot judge a short excerpt: two "you"s in a 68-word
# section is 29 per 1,000 words, and failing that would make this script
# unusable on anything but a finished document. The benchmark document carries
# about 31 uses in total, so eight is a sample worth drawing a rate from.
SECOND_PERSON_MIN_COUNT = 8
YOUR_BUSINESS = re.compile(
    r"\byour (?:company|business|firm|practice|organisation|organization)\b", re.IGNORECASE)

# ── False precision ───────────────────────────────────────────────────────
#
# "Daniel Brown Law draws an estimated 4,501 visits a month. That traffic
# would cost about $23,655 a month to buy." Both numbers are Ahrefs estimates
# of somebody else's traffic. The last three digits of each are noise, and
# printing them claims an accuracy nobody has.
#
# The benchmark writes "approximately 4,500 organic visits per month, traffic
# that would cost approximately $23,600/month". Same facts, no false claim,
# and it reads like a person talking.
#
# This is most of what makes our prose feel like a lab report: ours runs 6.1
# over-precise figures per 1,000 words against the benchmark's 3.0. It is also
# a quiet accuracy problem, which is why it fails rather than reviews once it
# is dense — a client who checks 4,501 against their own analytics and finds
# 4,120 has caught us being precisely wrong instead of roughly right.
#
# Prose only. A table of Opportunity Scores or a keyword volume column is a
# specification and keeps its digits.
FALSE_PRECISION = [
    # An estimate carried to four significant figures: 4,501 / 23,655 / 1,243.
    (r"(?<![\d.,])(?:\d,\d{3}|\d{2},\d{3})(?![\d.,])",
     "a four-figure estimate — round it (4,501 -> about 4,500)"),
    # Penny-or-dollar-precise money above a thousand.
    (r"\$\s?\d{1,3},\d{3}\b",
     "an exact dollar figure for an estimate — round it ($23,655 -> about $23,600)"),
    # A percentage to one decimal place in prose: "9.6% of links".
    (r"\b\d{1,3}\.\d\s?%",
     "a percentage to one decimal — whole numbers read better in prose"),
    # A range of exact word or item counts: "426-451 words", "53-61% the same".
    (r"\b\d{2,4}\s?[–-]\s?\d{2,4}\s?(?:words|%|percent)\b",
     "an exact range — say \"about 430 words\" or \"roughly half the same text\""),
]
# Per 1,000 words of prose. The benchmark sits at 3.0, so this allows it and a
# little more; ours sat at 6.1.
# With the hedge-proximity rule above doing the separating, the benchmark
# scores a clean ZERO here and ours scores four. So these only exist to stop a
# single stray figure in a short excerpt from failing a draft; the gap between
# the two documents is not a matter of where the line sits.
FALSE_PRECISION_LIMIT = 1.5
FALSE_PRECISION_MIN_COUNT = 3

# ── Provenance clutter ────────────────────────────────────────────────────
#
# The client asked for this out, in these words: "We don't need to tell the
# client where everything is coming from. The information we are giving to
# client should be fundamentally confidence giving."
#
# He is right about the audit trail. A sentence like "All the figures below
# are Ahrefs estimates taken on 21 November 2026" and an "Appendix A - Where
# every figure came from" are us showing our work to a reviewer, which is
# Rule 1 in another costume. The client has already signed; the document's job
# is now to say what happens next.
#
# What does NOT come out, and this is the line that matters: the word
# "estimated" or "approximately" attached to the figure itself. CLAUDE.md
# commits us to never stating a client's own traffic as fact from Ahrefs,
# because it is the one number they can check against an analytics tab they
# already have open and be right that we were wrong. The benchmark keeps it
# inline too - "receives an estimated 4,500 organic visits per month" - which
# is the proof that honesty and confidence are not in tension here.
#
# So: pull dates, source attributions and provenance appendices are flagged.
# Inline hedging on a third-party figure is not, and ESTIMATE_WORDS exists so
# nothing in this file ever starts flagging it by accident.
PROVENANCE_CLUTTER = [
    (r"\b(?:measured|taken|pulled|as\s+(?:of|at)|recorded|read)\s+(?:on|at)\s+\d{1,2}\s+\w+\s+\d{4}",
     "a pull date in the prose — the client does not audit our sources"),
    (r"\((?:Ahrefs|GA4|Google Analytics|Search Console|BrightLocal|PageSpeed)[^)]{0,30}\d{4}\)",
     "a parenthetical source-and-date stamp — keep the word \"estimated\", drop the citation"),
    (r"\bfigures? below (?:are|is)\b|\ball the figures\b|\bevery figure\b",
     "a sentence about where the figures came from"),
    (r"^#{1,4}\s*Appendix\s+[A-Z]?\s*[—–-]?\s*(?:Where|Sources?|Provenance)",
     "a provenance appendix — the sources belong in our own notes"),
    (r"\bmeasured or estimated\b|\bMeasured\s*\|\s*",
     "a Measured/Estimated column — say \"estimated\" in the sentence instead"),
]
PROVENANCE_LIMIT = 0
# Named so that a future widening of PROVENANCE_CLUTTER cannot swallow them.
# These are the words that keep an Ahrefs figure honest and they stay.
ESTIMATE_WORDS = re.compile(
    r"\b(?:estimated?|approximately|about|roughly|around)\b", re.IGNORECASE)

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


def sections_text(text: str) -> str:
    """Body text with its headings still attached.

    prose_only() strips headings, which is right for sentence checks and
    fatal for anything that reasons about sections: the first version of
    find_audit_sections() was handed a blob with no headings in it and
    reported, very confidently, that there were no sections at all.
    """
    t = FENCED_BLOCK.sub("\n", text)
    t = PLACEHOLDER_FOR_PROSE.sub("", t)
    return MARKDOWN_NOISE_PATTERN.sub("", t)


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


# The floor was 12 words, and under the new voice that is a bug rather than a
# tuning choice. The house style is now one idea per paragraph, usually one
# sentence — and a one-sentence paragraph is frequently under 12 words. "We'll
# rewrite the nine service pages in month one." is nine. A 12-word floor
# discards most of a correctly written document, which would leave every
# paragraph-level check reading a small and unrepresentative sample of it.
#
# Six is low enough to keep real prose and high enough to drop a stray caption
# or a table remnant. Bullets and table rows are excluded by shape regardless.
PARA_MIN_WORDS = 6


def paragraphs(text: str, min_words: int = PARA_MIN_WORDS) -> list:
    """Body paragraphs. Blank-line separated, headings and tables already gone."""
    blocks = re.split(r"\n\s*\n", text)
    out = []
    for b in blocks:
        p = " ".join(b.split())
        # "* " and "- " are bullets. "**" is a bold label, which is one of the
        # two forms a paragraph opener takes — excluding it made label_share
        # return "no paragraphs" on a correctly written section and skip the
        # check entirely.
        if len(p.split()) >= min_words and not re.match(r"[-*|]\s|\|", p):
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


def find_stub_openers(text: str):
    """Paragraphs that open with a verbless fragment and a full stop.

    "The year's totals. The plan delivers 4 service pages ..." The fragment is
    a heading pretending to be a sentence, and it is this repo's signature
    habit: 44% of the paragraphs in the strategy the client gave up on, 3% of
    the benchmark's.

    The old LABEL_OPENER could not see it, because it only matched a colon or
    a bold run. That blindness is why the model was told for months to add
    labels to a document already made of them.

    A fragment is identified by the absence of a verb before the full stop,
    which is what keeps this off genuine short opening sentences — "SEO isn't
    a one-time process." and "Mira Law ranks for none." both carry a verb and
    both pass.
    """
    ps = paragraphs(text)
    if len(ps) < STUB_MIN_PARAS:
        return [], None, len(ps)
    stubs = []
    for p in ps:
        m = STUB_OPENER.match(p)
        if not m:
            continue
        frag = m.group(1).strip()
        # Length first: it is the reliable half of the test.
        if len(frag.split()) > STUB_MAX_WORDS:
            continue
        if STUB_VERB.search(frag):
            continue
        stubs.append(frag)
    return stubs, len(stubs) * 100 // len(ps), len(ps)


def sents_per_para(text: str):
    """Average sentences per body paragraph.

    The benchmark runs 1.1 — nearly every sentence on its own line. Ours ran
    2.8 because plain-language.md asked for "two to four sentences, then
    stop", and the labels were then bolted on to make the resulting blocks
    scannable again.
    """
    ps = paragraphs(text)
    if len(ps) < SENTS_PER_PARA_MIN_PARAS:
        return None, len(ps)
    total = 0
    for p in ps:
        total += len([s for s in re.split(r"(?<=[.!?])\s+", p) if s.strip()])
    return total / len(ps), len(ps)


def find_false_precision(text: str):
    """Four-figure estimates and penny-precise money in prose.

    Table rows are dropped first: a keyword volume column or an Opportunity
    Score is a specification and keeps its digits. This is about a sentence
    claiming an accuracy the underlying estimate does not have.
    """
    prose = "\n".join(
        ln for ln in text.splitlines()
        if not ln.lstrip().startswith("|") and not ln.lstrip().startswith("#")
    )
    out = []
    for pattern, why in FALSE_PRECISION:
        for m in re.finditer(pattern, prose):
            # A density threshold alone could not separate the two documents:
            # ours scored 3.9 per 1k against a 4.5 limit and slipped under,
            # while the benchmark scored 3.0 — too close to gate on, and
            # tightening the limit to 3.5 would have been fitting the number
            # to one document again.
            #
            # The real signal is whether a rounding word sits next to the
            # figure. "approximately 20,370 monthly searches" is honest about
            # what it is; "4,501 visits a month" claims four significant
            # figures of somebody else's traffic. Same digits, different
            # claim, and the hedge is what distinguishes them.
            lo = max(0, m.start() - 60)
            if ESTIMATE_WORDS.search(prose[lo:m.start()]):
                continue
            out.append((m.group(0).strip(), why))
    return out


def _tables(text: str):
    """Every markdown table, as (header_cells_lowercased, [row_cells])."""
    out, cur = [], None
    for ln in text.splitlines():
        t = ln.strip()
        if t.startswith("|") and t.endswith("|"):
            cells = [c.strip() for c in t.strip("|").split("|")]
            if set(t) <= set("|-: "):          # the ---|--- separator
                continue
            if cur is None:
                cur = ([c.lower() for c in cells], [])
            else:
                cur[1].append(cells)
        else:
            if cur is not None:
                out.append(cur)
            cur = None
    if cur is not None:
        out.append(cur)
    return out


def month_rows(text: str):
    """Rows of tables that put a month beside a piece of work.

    Deliberately not "rows of the monthly plan". The Links and Authority
    table had its own Month column full of the firm's memberships, and the
    rule — a date is a promise, so only promise what we control — applies to
    any table that dates work.

    Found by the COLUMN HEADER, not by looking for a number that could be a
    month. The first version scanned every cell for a bare 1-12, which made
    a keyword table's Priority column look like a schedule and failed a
    perfectly good document on it.
    """
    out = []
    for header, rows in _tables(text):
        idx = [i for i, h in enumerate(header)
               if re.fullmatch(r"months?|when|by|by when|timing", h)]
        if not idx:
            continue
        for cells in rows:
            if any(i < len(cells) and re.fullmatch(
                    r"(?:1[0-2]|[1-9])\s*[–-]?\s*(?:1[0-2]|[1-9])?", cells[i]) for i in idx):
                out.append("| " + " | ".join(cells) + " |")
    return out


def find_month_plan_problems(text: str):
    """Reporting, and client-dependent work, sitting next to a month."""
    rows = month_rows(text)
    hay = "\n".join(rows)
    out = []
    for patterns, kind in ((MONTH_PLAN_REPORTING, "reporting"),
                           (MONTH_PLAN_CLIENT_DEPENDENT, "client-dependent")):
        for pattern, why in patterns:
            for m in re.finditer(pattern, hay, re.IGNORECASE):
                out.append((m.group(0).strip(), why, kind))
    return out


def find_month_plan_columns(text: str):
    """Columns the monthly plan must not have, and the order it must.

    Returns (problems, header_ok). A table whose first column is Month is
    the monthly plan, so its remaining headers are checked against the
    order that was asked for in those words: Month, Writing, What we work
    on. The approved document still had Writing on the right, which is why
    the wrong order is reported rather than assumed correct.
    """
    bad = []
    for pattern, why in MONTH_PLAN_BANNED_COLS:
        if re.search(pattern, text, re.IGNORECASE):
            bad.append(why)

    header_ok = bool(MONTH_PLAN_HEADER.search(text))
    if not header_ok:
        for header, _rows in _tables(text):
            if header and header[0] in ("month", "months") and len(header) >= 3:
                if "writing" in header and header.index("writing") != 1:
                    bad.append("Writing is column %d; it belongs second, beside the month"
                               % (header.index("writing") + 1))
                break
    return bad, header_ok


def month_one_shows_something(text: str):
    """Is there anything visible to the client in month one?

    None means there is no monthly plan to judge. True/False otherwise.
    """
    # The monthly plan only — a table whose FIRST column is Month. Scanning
    # every table for a row starting "1" found the keyword table's first row
    # and failed a good document for having no GMB post in it.
    for header, rows in _tables(text):
        if not header or header[0] not in ("month", "months"):
            continue
        for cells in rows:
            if cells and cells[0].strip() == "1":
                return bool(MONTH_ONE_VISIBLE.search("| " + " | ".join(cells) + " |"))
    return None


def find_provenance_clutter(text: str):
    """Pull dates, source stamps and a sources appendix.

    Deliberately narrow. The word "estimated" or "approximately" on a
    third-party figure is a commitment under CLAUDE.md and must never be
    caught here — ESTIMATE_WORDS names those so a later widening of
    PROVENANCE_CLUTTER cannot quietly swallow them.
    """
    out = []
    for pattern, why in PROVENANCE_CLUTTER:
        for m in re.finditer(pattern, text, re.IGNORECASE | re.MULTILINE):
            hit = " ".join(m.group(0).split())
            # Never flag a bare hedge on a figure. Belt and braces: none of
            # the patterns above should match one, and this guarantees it.
            if ESTIMATE_WORDS.fullmatch(hit.strip()):
                continue
            out.append((hit, why))
    return out


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
    floor = AUDIT_FINDING_MIN_COUNT if len(text.split()) < 2000 else 2
    return _density_group(text, AUDIT_FINDING, AUDIT_FINDING_LIMIT, floor)


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


def find_audit_sections(text: str):
    """Sections that report the present and commit to nothing.

    This is the structural half of Rule 3, and it is the half that matters.
    A phrase list is fitted to the document in front of you: the first version
    of this check caught one finding in eight on the next document it saw. A
    section that carries three measured figures and names no month, no target
    and nothing we will do is a findings chapter, whatever words it uses.

    Returns (flagged, rows) where each row is
    (heading, figure_count, reason).
    """
    rows = []
    # Split on any heading level, keeping the heading with its body.
    parts = re.split(r"^(#{1,3})\s+(.+)$", text, flags=re.MULTILINE)
    # parts[0] is any preamble; then (hashes, heading, body) triples.
    for i in range(1, len(parts) - 2, 3):
        heading, body = parts[i + 1].strip(), parts[i + 2]
        if re.search(r"^appendix\b", heading, re.IGNORECASE):
            continue                      # an appendix is where sources live
        forward = FORWARD_MARKER.search(body)
        # What excuses a present-tense heading is a target, not a passing
        # mention of a month. "Where the firm stands today" earned a pass
        # from the single sentence "speed work is included in month 4",
        # sitting under 300 words of findings.
        has_target = bool(re.search(r"\btargets?\b", body, re.IGNORECASE)) or \
                     len(set(re.findall(r"month\s*(\d+)", body, re.IGNORECASE))) >= 2
        # A heading that announces the present is only a problem when the
        # section then commits to nothing. "Where Mira Law starts, and where
        # we are going" sits above a Today / Month 6 / Month 12 table, which
        # is exactly the shape Rule 3 asks for — failing it would be failing
        # the thing the rule wants.
        if AUDIT_HEADING.search(heading) and not has_target:
            rows.append((heading, 0, "the heading announces the current state and the section "
                                     "commits to nothing — a plan's version of this is a table "
                                     "with a Target column"))
            continue
        figures = len(MEASURED_NOW.findall(body))
        if figures >= AUDIT_SECTION_MIN_FIGURES and not forward:
            rows.append((heading, figures,
                         f"{figures} measured figures and nothing committed — no month, no target, "
                         "nothing we said we would do"))
    return bool(rows), rows


def find_audit_vocab_hard(text: str):
    """The audit's own word for its own content."""
    out = []
    for m in AUDIT_VOCAB_HARD.finditer(text):
        start = max(0, m.start() - 40)
        out.append(" ".join(text[start:m.end() + 40].split()))
    return out


def find_missing_will(text: str):
    """Future work written in the present tense."""
    return [" ".join(m.group(0).split()) for m in MISSING_WILL.finditer(text)]


def find_month_table_shorthand(text: str):
    """Our own planning vocabulary, on a page a client reads.

    Scoped to table rows, because that is where terseness turns a plan into
    notes. Prose has room to explain itself; a table cell does not.
    """
    rows = "\n".join(l for l in text.splitlines() if l.strip().startswith("|"))
    if not rows:
        # A document converted out of .docx loses its pipes, so fall back to
        # the whole text rather than silently checking nothing.
        rows = text
    out = []
    for pattern, why in MONTH_TABLE_SHORTHAND:
        for m in re.finditer(pattern, rows, re.IGNORECASE):
            out.append((m.group(0).strip(), why))
    seen, uniq = set(), []
    for term, why in out:
        k = term.lower()
        if k not in seen:
            seen.add(k); uniq.append((term, why))
    return uniq


def find_forward_objective_refs(text: str):
    """"Objective 7" cited before the reader has seen the objectives.

    A reader meeting "Objective 1 sets the target" in the introduction has
    no numbering to resolve it against; the table is 500 lines further on.
    Either state the target in words where it is used, or move the table up.
    """
    table_at = None
    for m in re.finditer(r"^#{1,3}\s+.*objectives?\b.*$", text, re.IGNORECASE | re.MULTILINE):
        table_at = m.start()
        break
    out = []
    for m in re.finditer(r"\bObjectives?\s+\d+\b", text):
        if table_at is None or m.start() < table_at:
            start = max(0, m.start() - 50)
            out.append(" ".join(text[start:m.end() + 50].split()))
    return out


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
        # Table cells as well as prose. The findings in the last strategy had
        # migrated into a "Why it is first" column, and prose_only() strips
        # table rows — so the check reported a clean document while the
        # findings sat in the first table a reader meets.
        cells = " ".join(
            c.strip() for line in text.splitlines() if line.strip().startswith("|")
            for c in line.strip("|").split("|")
        )
        over, per_k, total, rows = find_audit_findings(body + "\n" + cells)
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

        # The structural half: a section that reports the present and
        # commits to nothing is a findings chapter however it is worded.
        sec_bad, sec_rows = find_audit_sections(sections_text(text))
        if sec_bad:
            print(f"\n[FAIL] {len(sec_rows)} section(s) report the current state without a target:")
            for heading, figures, why in sec_rows:
                print(f"       \"{heading}\" — {why}")
            print("       Rule 3: a baseline belongs beside the target it is measured against.")
            print("       Move these figures into the objectives table, or give each one a month.")
            failures += 1

        vocab = find_audit_vocab_hard(body)
        if vocab:
            print(f"\n[FAIL] \"finding\" is the audit's word for the audit's content ({len(vocab)}):")
            for v in vocab[:4]:
                print(f"           \u2026{v[:88]}\u2026")
            print("       A plan has priorities, targets and months.")
            failures += 1

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

        # ── Paragraph shape ──────────────────────────────────────────────
        # Labels: a ceiling now, and no floor. See the LABEL_OPENER block.
        pct_lab, n_lab, n_par = label_share(body_md)
        if pct_lab is not None and pct_lab > LABEL_CEILING_PCT:
            print(f"\n[FAIL] {pct_lab}% of paragraphs open with a label "
                  f"({n_lab} of {n_par}, ceiling {LABEL_CEILING_PCT}%)")
            print("       Every paragraph a bolded stub is a filing system, not a document.")
            print("       A label belongs in a specification block, not on narrative prose.")
            failures += 1
        elif pct_lab is not None:
            print(f"\n[OK] Labels used sparingly ({pct_lab}% of paragraphs)")

        # The form the label check could not see: a verbless fragment, a full
        # stop, then the real sentence. 44% of ours, 3% of the benchmark's.
        stubs, pct_stub, n_par2 = find_stub_openers(body_md)
        if pct_stub is not None and pct_stub > STUB_CEILING_PCT:
            print(f"\n[FAIL] {pct_stub}% of paragraphs open with a sentence fragment "
                  f"({len(stubs)} of {n_par2}, ceiling {STUB_CEILING_PCT}%)")
            for s in stubs[:6]:
                # "no verb" would be a false claim about "Claim the
                # memberships first." — that is an imperative, grammatically
                # complete, and still the same habit: a stub standing in for
                # a heading in front of the real sentence.
                print(f"       \"{s}.\" — fold it into the sentence after it")
            print("       This is the habit the client gave up on. \"The year's totals. The plan")
            print("       delivers 4 service pages\" is one sentence: \"The plan delivers 4 service")
            print("       pages ...\" The fragment adds a heading nobody asked for.")
            failures += 1
        elif pct_stub is not None:
            print(f"\n[OK] Paragraphs open with sentences, not fragments ({pct_stub}%)")

        # One idea, one paragraph. The benchmark runs 1.1 sentences a
        # paragraph; dense paragraphs are what labels were compensating for.
        spp, n_par3 = sents_per_para(body_md)
        if spp is not None and spp > SENTS_PER_PARA_CEILING:
            print(f"\n[FAIL] {spp:.1f} sentences per paragraph across {n_par3} paragraphs "
                  f"(ceiling {SENTS_PER_PARA_CEILING})")
            print("       The benchmark runs 1.1 — nearly every sentence gets its own line.")
            print("       In a 60-page document this is what decides whether anyone finishes it.")
            print("       Break the paragraphs; do not add labels to dense ones.")
            failures += 1
        elif spp is not None:
            print(f"\n[OK] One idea per paragraph ({spp:.1f} sentences each)")

        # Connectives: informational only now. See the CONNECTIVE block.
        paras_n = len(paragraphs(body_md))
        if paras_n >= 8:
            print(f"\n[note]  {len(CONNECTIVE.findall(body))} connectives across {paras_n} paragraphs "
                  f"({len(CONNECTIVE.findall(body)) / paras_n:.2f} each) — not a gate")

        # Bare imperatives: a ceiling now, and no floor.
        imp = imperative_density(body)
        imp_n = len(IMPERATIVE_OPENER.findall(body))
        if imp > IMPERATIVE_CEILING and imp_n >= IMPERATIVE_MIN_COUNT:
            print(f"\n[FAIL] {imp:.1f} bare instructions per 1k words "
                  f"({imp_n} of them, ceiling {IMPERATIVE_CEILING})")
            print("       \"Profile the render path in month 4\" is a line from our worklist.")
            print("       Write \"we'll\" and the verb: \"We'll profile the render path in month 4.\"")
            print("       Imperatives stay right inside a specification row, not in prose.")
            failures += 1
        else:
            print(f"\n[OK] Actions have somebody attached to them ({imp:.1f} bare instructions per 1k)")

        # Contractions. The benchmark runs 24.5 per 1k; a document with none
        # reads as a notice rather than a person.
        n_words = len(body.split())
        if n_words >= CONTRACTION_MIN_WORDS:
            c_n = len(CONTRACTION.findall(body))
            c_k = c_n / (n_words / 1000)
            if c_k < CONTRACTION_MIN_PER_1K:
                print(f"\n[FAIL] {c_k:.1f} contractions per 1k words "
                      f"({c_n} in {n_words}, floor {CONTRACTION_MIN_PER_1K})")
                print("       The benchmark runs 24.5. \"we'll\", \"we've\", \"you'll\", \"isn't\".")
                print("       \"We will optimize\" twelve times and never \"we'll\" is most of what")
                print("       the client meant by \"humans don't talk like this\".")
                failures += 1
            else:
                print(f"\n[OK] Reads as a person talking ({c_k:.1f} contractions per 1k words)")

        # False precision on somebody else's estimated numbers.
        fp = find_false_precision(body)
        if not fp:
            print("\n[OK] No unhedged four-figure estimates in prose")
        else:
            fp_k = len(fp) / max(1.0, n_words / 1000)
            if fp_k > FALSE_PRECISION_LIMIT and len(fp) >= FALSE_PRECISION_MIN_COUNT:
                print(f"\n[FAIL] {len(fp)} over-precise figures in prose "
                      f"({fp_k:.1f} per 1k, limit {FALSE_PRECISION_LIMIT})")
                for hit, why in fp[:6]:
                    print(f"       \"{hit}\" — {why}")
                print("       An Ahrefs estimate carried to four figures claims an accuracy")
                print("       nobody has, and a client who checks it has caught us being")
                print("       precisely wrong instead of roughly right. Tables keep their digits.")
                failures += 1
            else:
                print(f"\n[OK] Figures in prose are rounded ({fp_k:.1f} over-precise per 1k)")

        # Provenance clutter: pull dates, source stamps, a sources appendix.
        prov = find_provenance_clutter(text)
        if len(prov) > PROVENANCE_LIMIT:
            print(f"\n[FAIL] {len(prov)} piece(s) of provenance the client did not ask for:")
            for hit, why in prov[:6]:
                print(f"       \"{hit[:60]}\" — {why}")
            print("       Keep the word \"estimated\" on any third-party figure; that one is a")
            print("       commitment. Drop the dates, the source stamps and the sources appendix.")
            failures += 1
        else:
            print("\n[OK] No pull dates or source stamps in the client's copy")

        # Future work, in the future tense.
        wills = find_missing_will(body)
        if len(wills) > MISSING_WILL_LIMIT:
            print(f"\n[FAIL] {len(wills)} sentences put future work in the present tense:")
            for w in wills[:5]:
                print(f"           \u2026{w[:80]}\u2026")
            print("       \"We will optimize the website in month one\". A reader stops on the")
            print("       present tense for something that has not happened yet.")
            failures += 1
        elif wills:
            print(f"\n[REVIEW] {len(wills)} sentence(s) put future work in the present tense")

        # Our own planning vocabulary, on a page a client reads.
        shorthand = find_month_table_shorthand(text)
        # ── The monthly plan ─────────────────────────────────────────────
        mp = find_month_plan_problems(text)
        if len(mp) > MONTH_PLAN_LIMIT:
            rep = [x for x in mp if x[2] == "reporting"]
            dep = [x for x in mp if x[2] == "client-dependent"]
            print(f"\n[FAIL] {len(mp)} row(s) put something beside a month that should not have one:")
            for hit, why, _ in mp[:8]:
                print(f"       \"{hit}\" — {why}")
            if rep:
                print("       Reporting goes in Ongoing SEO Management & Reporting.")
                print("       Re-optimising a published page is work and stays; reading a report is not.")
            if dep:
                print("       Client-dependent work gets its own section with no date. A month")
                print("       beside it promises something we do not control.")
            failures += 1
        else:
            print("\n[OK] Nothing beside a month that we cannot deliver ourselves")

        bad_cols, header_ok = find_month_plan_columns(text)
        if bad_cols:
            print(f"\n[FAIL] The monthly plan has {len(bad_cols)} column(s) that were removed:")
            for why in bad_cols:
                print(f"       {why}")
            print("       The columns are Month, Writing, What we work on — in that order.")
            failures += 1
        elif header_ok:
            print("\n[OK] Monthly plan columns: Month, Writing, What we work on")

        m1 = month_one_shows_something(text)
        if m1 is False:
            print("\n[FAIL] Month one shows the client nothing they can look at")
            print("       GMB posting starts in month one. Titles, schema and internal")
            print("       links are a month of invisible work, and month one is the month")
            print("       they are deciding whether this was worth buying.")
            failures += 1
        elif m1:
            print("\n[OK] Month one has something the client can see")

        pink = sorted(set(m.group(0) for m in PINK.finditer(text)))
        if pink:
            print(f"\n[FAIL] Pink in a client document: {', '.join(pink[:6])}")
            print("       #FF6699 is the portal's interface colour. Figures use the blue,")
            print("       grey and green in scripts/make_figures.py.")
            failures += 1

        if len(shorthand) > MONTH_TABLE_SHORTHAND_LIMIT:
            print(f"\n[FAIL] {len(shorthand)} piece(s) of internal shorthand a client will not follow:")
            for term, why in shorthand[:8]:
                print(f"       \"{term}\" \u2014 {why}")
            print("       The month table is the first thing they read.")
            failures += 1
        else:
            print("\n[OK] No internal shorthand in the tables")

        # Numbered objectives cited before the objectives exist.
        fwd = find_forward_objective_refs(text)
        if fwd:
            print(f"\n[FAIL] {len(fwd)} reference(s) to a numbered objective before the table that defines it:")
            for f in fwd[:4]:
                print(f"           \u2026{f[:84]}\u2026")
            print("       State the target in words where it is used, or move the table up.")
            failures += 1

        grade = reading_grade(body)
        if grade is not None:
            print(f"\n[note]  Reading grade {grade:.1f} — reported, not a target")
            print("       This number is noise and it used to be a gate. The benchmark the")
            print("       client asked us to write like scores 14.5; the document he gave up")
            print("       on scored 7.0. Flesch-Kincaid cannot see sentence shape, and a")
            print("       target printed next to it invited writing toward the number.")

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
