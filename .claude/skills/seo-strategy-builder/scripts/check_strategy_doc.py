#!/usr/bin/env python3
"""
Mechanical completeness, fact-check and readability scan for an assembled SEO
strategy document (Phase 1, 2, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 17, 18, 19,
or 20 content, or any combination).

Three kinds of thing get checked:

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
   on first sight — see LANGUAGE_LIMITS. Below the threshold they are
   reported for review.

Usage:
    python check_strategy_doc.py <document_file.md> [--phases 1,2,5,7,8,9]
                                                    [--no-language]

    --phases is optional. If given, the script reports which of those
    phase numbers it found a heading for, so you can confirm an assembled
    document actually contains everything that was requested.

    --no-language skips check 3. For an internal working draft, not for
    anything going to a client.

Exits 0 if no unresolved placeholders are found and no language count is over
its threshold. Dead-advice warnings alone don't fail the run — they're a prompt
to review, not a blocker.
"""

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
    "long_sentences": 5,
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

LONG_SENTENCE_WORDS = 45

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


def prose_only(text: str) -> str:
    """Body prose, with the parts these checks would misread removed.

    Code blocks are not prose. Table rows are deliberately terse and would
    trip the sentence-length and period checks on every line. Headings get
    their own guidance in plain-language.md and are not sentences.
    """
    t = FENCED_BLOCK.sub("\n", text)
    t = TABLE_LINE.sub("", t)
    t = HEADING_ONLY.sub("", t)
    t = PLACEHOLDER_FOR_PROSE.sub("", t)
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
    check_language = True
    i = 0
    while i < len(raw):
        a = raw[i]
        if a == "--no-language":
            check_language = False
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

        longs = find_long_sentences(body)
        limit = LANGUAGE_LIMITS["long_sentences"]
        if longs:
            over = len(longs) > limit
            print(f"\n[{'FAIL' if over else 'REVIEW'}] Sentences over {LONG_SENTENCE_WORDS} words: "
                  f"{len(longs)} (threshold {limit})")
            for n, snippet in longs[:6]:
                print(f"       {n} words: {snippet}")
            if len(longs) > 6:
                print(f"       ... and {len(longs) - 6} more")
            if over:
                failures += 1
        else:
            print(f"\n[OK] No sentence over {LONG_SENTENCE_WORDS} words")

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
