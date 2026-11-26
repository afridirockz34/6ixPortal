#!/usr/bin/env python3
"""
check_audit_report.py — mechanical QC for an seo-audit-report deliverable.

Checks (mirrors the discipline of seo-strategy-builder/scripts/check_strategy_doc.py
and seo-content-production/scripts/check_draft.py):

1. HARD FAIL  — unresolved [NEEDS DATA: ...] / [NEEDS CLIENT INPUT: ...] placeholders.
2. REVIEW     — a weakness-category section with no severity rating attached.
3. REVIEW     — no closing / call-to-action section found.
4. REVIEW     — a section that reads like a full step-by-step fix rather than a
                one-line teaser ("name it, don't fix it").
5. REVIEW     — no "SEO Health Score" (a 0-100 number) found in the executive
                summary (see references/audit-methodology.md's scoring section).

Usage:
    python check_audit_report.py <report_file.md>

Exit code 1 only on the hard-fail case (unresolved placeholders). Everything
else prints as REVIEW — a human reads those, the script doesn't block on them.
"""

import json
import re
from collections import Counter
import sys
from pathlib import Path

PLACEHOLDER_PATTERN = re.compile(r"\[NEEDS (DATA|CLIENT INPUT)[^\]]*\]", re.IGNORECASE)

MARKDOWN_NOISE_PATTERN = re.compile(r"[`*_]")

HEADING_PATTERN = re.compile(r"^(#{1,3})\s+(.*)$", re.MULTILINE)

SEVERITY_WORDS = {"critical", "high", "medium", "low", "healthy"}

# Headings that are structural, not weakness-category sections, and so are
# exempt from the "needs a severity rating" check.
STRUCTURAL_HEADING_HINTS = (
    "cover",
    "executive summary",
    "scorecard",
    "closing",
    "what we'd do",
    "what we would do",
    "next step",
    "call to action",
    "competitor callout",
    # Structural sections the rendered report adds. They present measurements
    # rather than a graded weakness, so a missing severity is correct there.
    "website speed",
    "competitor analysis",
    "google business profile",
    "how you appear in search",
    "how other sites link to you",
    # The titles these two sections used to carry. Kept so a report generated
    # before the rename still checks out rather than being flagged for a
    # heading that was correct when it was written.
    "where the traffic is going",
    "where you show up on the map",
    "executive summary",
)

CTA_HEADING_HINTS = (
    "closing",
    "what we'd do",
    "what we would do",
    "next step",
    "call to action",
    "get started",
    "proposal",
)

# Signals that a section drifted from a one-line teaser into a full how-to.
FULL_FIX_SIGNALS = [
    re.compile(r"^\s*(?:step\s*)?[1-9]\.\s", re.IGNORECASE | re.MULTILINE),
    re.compile(r"^\s*[1-9]\)\s", re.MULTILINE),
    re.compile(r"\bfirst,.*then,.*(?:finally|then)\b", re.IGNORECASE | re.DOTALL),
]
FULL_FIX_TEASER_SENTENCE_LIMIT = 2  # a teaser should be ~1 sentence, allow a little slack

# ── Tone: the client is the expert's client, not the auditor's subject ──────
# A report can be entirely accurate and still open by telling somebody their
# business is losing. The facts do not change; what the sentence points at
# does. "Three competitors already outrank you" and "those three positions are
# what this engagement goes after" are the same finding.
#
# REVIEW, never a hard fail. Some of these phrasings are right in context — a
# quoted client complaint, a finding about a competitor — and a gate that
# fails good work gets switched off.
DEFEATIST_PHRASES = [
    (re.compile(r"\byou(?:'re| are) (?:losing|missing out|falling behind|behind|invisible)\b", re.I),
     "say where the opportunity is, not where they stand"),
    (re.compile(r"\b(?:costing|losing) (?:you|them|the (?:client|business))\b", re.I),
     "state the demand that exists rather than a loss"),
    (re.compile(r"\byou(?:'re| are) (?:not|nowhere)\b[^.]{0,40}\b(?:ranking|visible|showing up)\b", re.I),
     "name the terms worth winning instead"),
    (re.compile(r"\b(?:bleeding|haemorrhaging|hemorrhaging)\b", re.I),
     "no injury metaphors about a client's business"),
    (re.compile(r"\byour (?:site|website|profile|business) (?:is )?fail(?:s|ing)?\b", re.I),
     "describe what it does not yet cover, as work we would do"),
    (re.compile(r"\balmost none of (?:it|that|this) (?:is )?reach", re.I),
     "that traffic is demand in their market — say so that way"),
    # "Your competitors rank above you today — those positions are the target"
    # is the finding stated well. "Three competitors ALREADY outrank you" is
    # the same fact with a verdict attached, so it is the "already" that is
    # flagged, not the comparison.
    (re.compile(r"\balready (?:outranks?|outranking|beats?|beating|ahead of|winning)\b", re.I),
     "drop 'already' — state the position and that it is the target"),
]

# A domain whose label is an acronym — tps.ca, cbc.ca, bmo.com — tells the
# reader nothing. A client who cannot identify a rival cannot judge the number
# printed next to it, which is the whole point of naming one. Domains that
# spell words (neubergerlaw.com) at least read, so they are left alone.
# ── Voice: the same two rules every client-facing document follows ─────────
# The long version is seo-strategy-builder/references/plain-language.md, Rules
# 1 and 2. They are repeated here rather than imported, because these skills
# have to work in a client session with no sight of each other.
#
# An audit is allowed its findings — that is the whole document. It is not
# allowed to argue with its own reviewer, and it is not allowed to jab "you"
# at the reader 23 times per 1,000 words while never naming the firm.
META_COMMENTARY = [
    (r"\b(?:worth (?:your knowing|being plain about|noting)|needs explaining"
     r"|one thing (?:we checked|to be straight about)|to be (?:straight|clear) about"
     r"|the honest answer|this (?:appendix|section|table) is here)\b",
     "the document announcing its own work — state the finding, not that you looked"),
    (r"\bread (?:the|that|those)\s+[\w ]{0,24}?(?:again|together)\b"
     r"|\bwhat (?:those|these)\s+[\w ]{0,24}?add up to\b",
     "telling the reader how to read our own table"),
    (r"\b(?:we would rather|we state it as|anyone quoting|rather than overlooked"
     r"|not a gap in it|looks like a strength and is not|is not decoration)\b",
     "defending the draft against an objection the client never raised"),
]
SECOND_PERSON = re.compile(r"\b(?:you|your|you're|yours)\b", re.IGNORECASE)
# Raised from 8.0 to match the strategy. The client-letter benchmark runs 3.0
# and uses "you" in its warmest sentences — "you'll receive a monthly report".
# A limit tight enough to push a writer off those works against the voice.
SECOND_PERSON_LIMIT = 14.0    # per 1,000 words
# Two "you"s in a short section is 29 per 1,000 words. Draw a rate only from a
# sample worth drawing one from.
SECOND_PERSON_MIN_COUNT = 8
YOUR_BUSINESS = re.compile(
    r"\byour (?:company|business|firm|practice|organisation|organization)\b", re.IGNORECASE)

# ── The rest of the house voice ───────────────────────────────────────────
# Everything above predates the voice recalibration. These are the rules the
# strategy skill was rebuilt around after four drafts were rejected, and they
# apply to the audit for the reason the client gave: it is a client-facing
# report and it should sound like the other one.
#
# The long version, with the measured comparison that produced each number,
# is seo-strategy-builder/references/plain-language.md and
# assets/client-letter-benchmark.md. Repeated here rather than imported,
# because these skills run in client sessions with no sight of each other.
#
# TWO STRATEGY RULES DELIBERATELY DO NOT APPLY HERE:
#
#   - **Provenance.** The strategy strips pull dates and source stamps; the
#     audit is the document that exists to show its evidence, and
#     references/measured-vs-estimated.md is a core part of this skill. An
#     audit that does not say where a figure came from is the fault, not the
#     fix.
#   - **"A strategy is not an audit."** Findings are what this document is
#     for.
#
# Everything else is shared.

# The benchmark runs 24.5 contractions per 1,000 words. A document with none
# reads as a notice, and that was most of what the client meant by "humans
# don't talk like this".
CONTRACTION = re.compile(r"\b\w+(?:'|’)(?:ll|ve|re|s|t|d|m)\b", re.IGNORECASE)
CONTRACTION_MIN_PER_1K = 10.0
CONTRACTION_MIN_WORDS = 400

# A bare imperative in a client report is a line from our worklist. The
# benchmark uses zero and writes "we'll rewrite the titles".
IMPERATIVE_OPENER = re.compile(
    r"(?:^|(?<=[.!?]\s)|(?<=[.!?]\n))(?:Build|Add|Create|Target|Publish|Use|Write|Rewrite|"
    r"Expand|Launch|Set|Run|Match|Claim|Fix|Replace|Submit|Monitor|Track|Optimize|Link|"
    r"Name|List|Keep|Start|Send|Draft|Remove|Correct|Confirm|Ask|Check|Report|"
    r"Profile|Rotate|Join|Delete|Resolve|Hold|Leave|Cut|Take|Make|Read|Say|Measure)\b",
    re.MULTILINE)
IMPERATIVE_CEILING = 2.0      # per 1,000 words of prose
IMPERATIVE_MIN_COUNT = 4

# A verbless fragment standing in for a heading: "The year's totals. The plan
# delivers ..." 44% of the paragraphs in the rejected strategy, 3% of the
# benchmark's. Length does most of the work here; see the long note in
# check_strategy_doc.py for why a verb whitelist was the wrong instrument.
STUB_OPENER = re.compile(r"^(?:\*\*)?([A-Z][^.!?:\n]{2,70}?)(?:\*\*)?\.\s+[A-Z]")
STUB_MAX_WORDS = 6
STUB_VERB = re.compile(
    r"\b(?:is|are|was|were|be|been|am|has|have|had|will|would|shall|should|"
    r"can|could|may|might|must|do|does|did|goes|go|went)\b"
    r"|\b(?:it|that|there|here|what|who|he|she|this|one|everything|nothing|"
    r"something|someone|nobody)(?:'|’)s\b"
    r"|\b\w+(?:'|’)(?:re|ve|ll|d)\b"
    r"|\b(?:is|are|was|were|has|have|had|does|do|did|wo|ca|could|should|"
    r"would|must|need|might)(?:n(?:'|’)?t)\b"
    r"|\b\w{3,}(?:ed|ing)\b",
    re.IGNORECASE)
STUB_CEILING_PCT = 10
STUB_MIN_PARAS = 8

# An Ahrefs estimate carried to four significant figures claims an accuracy
# nobody has, and this document is mostly third-party estimates. A hedge
# nearby makes it honest: "approximately 4,500" is fine, "4,501" is not.
FALSE_PRECISION = [
    (r"(?<![\d.,])(?:\d,\d{3}|\d{2},\d{3})(?![\d.,])",
     "a four-figure estimate — round it (4,501 -> about 4,500)"),
    (r"\$\s?\d{1,3},\d{3}\b",
     "an exact dollar figure for an estimate — round it"),
    (r"\b\d{1,3}\.\d\s?%", "a percentage to one decimal — whole numbers read better"),
]
ESTIMATE_WORDS = re.compile(
    r"\b(?:estimated?|approximately|about|roughly|around)\b", re.IGNORECASE)
FALSE_PRECISION_LIMIT = 1.5
FALSE_PRECISION_MIN_COUNT = 3

BARE_ACRONYM_DOMAIN = re.compile(
    r"(?<![\w./@-])([a-z]{2,4})\.(?:ca|com|net|org|co)\b(?![\w/])", re.IGNORECASE)

# Matches both the written form ("SEO Health Score: 41/100") and the one the
# rendered report produces, where the number lives in the gauge's accessible
# name ("SEO Health Score: 41 out of 100").
HEALTH_SCORE_PATTERN = re.compile(
    r"\bseo health score\b[^\d]{0,20}(\d{1,3})\s*(?:/|out of)\s*100", re.IGNORECASE)

# ── Canadian English ──────────────────────────────────────────────────────
# Canadian spelling is its own thing, not a halfway house: -ize and -ization
# like the US, -our and -re like the UK, and "inquiry" rather than "enquiry".
# Only unambiguous cases are listed. A word whose -ise spelling is correct in
# every variety (advertise, exercise, franchise, surprise) is NOT an -ize word
# and is deliberately absent; flagging those would train the reader to ignore
# this check.
_IZE_STEMS = (
    "optim", "special", "organ", "priorit", "real", "recogn", "maxim", "minim",
    "util", "summar", "categor", "custom", "monet", "personal", "local",
    "capital", "emphas", "author", "legitim", "modern", "standard", "visual",
    "apolog", "critic", "analy", "central", "normal", "social", "formal",
)
_IZE_SUFFIXES = ("ise", "ises", "ised", "ising", "isation", "isations")

CANADIAN_FIXES = {}
for _stem in _IZE_STEMS:
    for _suf in _IZE_SUFFIXES:
        CANADIAN_FIXES[_stem + _suf] = _stem + _suf.replace("is", "iz", 1)
CANADIAN_FIXES.update({
    # "Enquiry" is the one the client called out by name.
    "enquiry": "inquiry", "enquiries": "inquiries", "enquire": "inquire",
    "enquired": "inquired", "enquiring": "inquiring",
    # American -or where Canadian keeps -our. Only words that are unambiguous;
    # "doctor", "author", "editor" and the rest of the -or family are correct.
    "color": "colour", "colors": "colours", "colored": "coloured", "coloring": "colouring",
    "behavior": "behaviour", "behaviors": "behaviours", "behavioral": "behavioural",
    "favorite": "favourite", "favorites": "favourites", "favor": "favour", "favors": "favours",
    "honor": "honour", "labor": "labour", "neighbor": "neighbour", "neighbors": "neighbours",
    "humor": "humour", "rumor": "rumour", "flavor": "flavour", "endeavor": "endeavour",
    "harbor": "harbour", "vapor": "vapour", "armor": "armour", "odor": "odour",
    "savior": "saviour", "splendor": "splendour", "valor": "valour",
    # American -er where Canadian keeps -re. "meter" is excluded on purpose: a
    # water meter and a parking meter are both spelled that way here.
    "center": "centre", "centers": "centres", "centered": "centred", "centering": "centring",
    "theater": "theatre", "theaters": "theatres",
    # Miscellaneous
    "defense": "defence", "offense": "offence", "pretense": "pretence",
    "catalog": "catalogue", "catalogs": "catalogues",
    "traveled": "travelled", "traveling": "travelling", "traveler": "traveller",
    "canceled": "cancelled", "canceling": "cancelling",
    "fulfill": "fulfil", "enrollment": "enrolment", "installment": "instalment",
    "judgement": "judgment",
})

_CANADIAN_PATTERN = re.compile(
    r"\b(" + "|".join(sorted(CANADIAN_FIXES, key=len, reverse=True)) + r")\b",
    re.IGNORECASE,
)


def find_spelling_slips(text: str):
    """Words in the report that are not the Canadian spelling, with the fix."""
    seen = {}
    for m in _CANADIAN_PATTERN.finditer(text):
        found = m.group(1)
        want = CANADIAN_FIXES[found.lower()]
        if found[:1].isupper():
            want = want[:1].upper() + want[1:]
        seen.setdefault(found, want)
    return sorted(seen.items())


def strip_markup(text: str) -> str:
    """
    HTML to the words a reader actually sees.

    Headings become Markdown headings first, so the section checks work on a
    rendered report exactly as they do on a draft — otherwise stripping the
    tags takes the structure with it and every section check silently passes.
    An SVG title/aria-label is kept too: in the rendered report the health
    score lives in the gauge's accessible name, not in the prose.
    """
    text = re.sub(r"(?is)<(script|style)[^>]*>.*?</\1>", " ", text)
    text = re.sub(r"(?s)<!--.*?-->", " ", text)
    text = re.sub(r'(?is)<[^>]+aria-label="([^"]+)"[^>]*>', r" \1 ", text)
    for level in (1, 2, 3):
        text = re.sub(rf"(?is)<h{level}[^>]*>(.*?)</h{level}>",
                      lambda m, l=level: "\n" + "#" * l + " " + m.group(1) + "\n", text)
    text = re.sub(r"(?s)<[^>]+>", " ", text)
    text = re.sub(r"&amp;", "&", text)
    text = re.sub(r"&(?:nbsp|#160);", " ", text)
    text = re.sub(r"&(?:mdash|#8212);", "—", text)
    # Collapse runs of spaces but keep the newlines the headings just gained.
    text = re.sub(r"[ \t]+", " ", text)
    return re.sub(r"\n{2,}", "\n", text)


def sections_from_audit(data):
    """
    The same (heading, level, body) shape, read out of an audit JSON file.

    Checking the source rather than the rendered page is more precise: a
    missing severity is a missing field, not a word the regex failed to find.
    """
    out = []
    summary = data.get("summary") or []
    if isinstance(summary, str):
        summary = [summary]
    out.append(("Executive summary", 2, " ".join(str(s) for s in summary)))
    for row in data.get("scorecard") or []:
        out.append((f"Scorecard: {row.get('category', '')}", 2,
                    f"{row.get('status', '')} {row.get('verdict', '')}"))
    for sec in data.get("sections") or []:
        body = " ".join(str(sec.get(k, "")) for k in ("severity", "finding", "why", "teaser"))
        out.append((str(sec.get("title", "")), 2, body))
    closing = data.get("closing") or {}
    if closing:
        out.append((str(closing.get("heading") or "Closing"), 2,
                    " ".join(str(closing.get(k, "")) for k in ("body", "service", "next_step"))))
    return out


def find_placeholders(text: str):
    return sorted(set(m.group(0) for m in PLACEHOLDER_PATTERN.finditer(text)))


def split_sections(text: str):
    """Return list of (heading_text, heading_level, body_text) for each heading."""
    matches = list(HEADING_PATTERN.finditer(text))
    sections = []
    for i, m in enumerate(matches):
        level = len(m.group(1))
        heading = m.group(2).strip()
        start = m.end()
        end = matches[i + 1].start() if i + 1 < len(matches) else len(text)
        body = text[start:end]
        sections.append((heading, level, body))
    return sections


def is_structural(heading: str) -> bool:
    lower = MARKDOWN_NOISE_PATTERN.sub("", heading.lower())
    return any(hint in lower for hint in STRUCTURAL_HEADING_HINTS)


def has_severity(heading: str, body: str) -> bool:
    combined = MARKDOWN_NOISE_PATTERN.sub("", f"{heading}\n{body[:400]}".lower())
    return any(re.search(rf"\b{w}\b", combined) for w in SEVERITY_WORDS)


def find_missing_severity_sections(sections):
    missing = []
    for heading, level, body in sections:
        if level != 2:
            continue
        if is_structural(heading):
            continue
        if not body.strip():
            continue
        if not has_severity(heading, body):
            missing.append(heading)
    return missing


def find_executive_summary_body(sections) -> str:
    for heading, level, body in sections:
        if level == 2 and "executive summary" in MARKDOWN_NOISE_PATTERN.sub("", heading.lower()):
            return body
    return ""


def has_health_score(text: str, sections) -> bool:
    exec_body = find_executive_summary_body(sections)
    # Prefer checking the executive summary specifically, but fall back to a
    # whole-document search in case the report uses a different heading.
    return bool(HEALTH_SCORE_PATTERN.search(exec_body)) or bool(HEALTH_SCORE_PATTERN.search(text))


def has_cta_section(sections) -> bool:
    for heading, _level, _body in sections:
        lower = MARKDOWN_NOISE_PATTERN.sub("", heading.lower())
        if any(hint in lower for hint in CTA_HEADING_HINTS):
            return True
    return False


def find_full_fix_sections(sections):
    flagged = []
    for heading, level, body in sections:
        if level != 2 or is_structural(heading):
            continue
        if not body.strip():
            continue
        for pattern in FULL_FIX_SIGNALS:
            if pattern.search(body):
                flagged.append((heading, "numbered how-to steps found"))
                break
        else:
            # Fallback heuristic: a long run of sentences after a "what addressing
            # it involves" / "to fix" cue reads as a how-to rather than a teaser.
            cue = re.search(
                r"(what addressing it involves|to fix this|how to fix)[:\s]",
                body,
                re.IGNORECASE,
            )
            if cue:
                tail = body[cue.end():]
                sentence_count = len(re.findall(r"[.!?](?:\s|$)", tail.strip()))
                if sentence_count > FULL_FIX_TEASER_SENTENCE_LIMIT:
                    flagged.append((heading, f"~{sentence_count} sentences after the fix cue — expected a 1-sentence teaser"))
    return flagged


def find_defeatist_phrases(text: str):
    """Lines that tell the client they are doing badly rather than what we would do."""
    out = []
    for pattern, advice in DEFEATIST_PHRASES:
        for m in pattern.finditer(text):
            start = max(0, m.start() - 40)
            snippet = " ".join(text[start:m.end() + 40].split())
            out.append((snippet, advice))
    return out


def find_meta_commentary(text: str):
    """Sentences aimed at the reviewer instead of the client."""
    out = []
    for pattern, why in META_COMMENTARY:
        for m in re.finditer(pattern, text, re.I):
            start = max(0, m.start() - 35)
            out.append((" ".join(text[start:m.end() + 35].split()), why))
    return out


def pronoun_balance(text: str):
    """How often we address the reader, against how often we name their firm."""
    n_words = max(1, len(text.split()))
    you = len(SECOND_PERSON.findall(text))
    caps = re.findall(r"\b[A-Z][a-z]+ (?:[A-Z][a-z]+|LLP|Law)\b", text)
    named = max(Counter(caps).values()) if caps else 0
    return you * 1000 / n_words, named * 1000 / n_words, you, len(YOUR_BUSINESS.findall(text))


def prose_only(sections):
    """The report's writing, without its specification rows.

    A scorecard row is a spec — "Backlinks · Weak · 41 referring domains" —
    and holding it to a prose rule would fail every report for having a
    scorecard in it. Findings, the executive summary and the closing are the
    writing, and they are what the voice checks read.
    """
    return "\n\n".join(
        body for heading, _lvl, body in sections
        if not heading.lower().startswith("scorecard:") and body.strip()
    )


def contraction_rate(text: str):
    n_words = len(text.split())
    if n_words < CONTRACTION_MIN_WORDS:
        return None, n_words
    return len(CONTRACTION.findall(text)) / (n_words / 1000), n_words


def imperative_density(text: str):
    n_words = max(1, len(text.split()))
    hits = IMPERATIVE_OPENER.findall(text)
    return len(hits) * 1000 / n_words, len(hits)


def find_stub_openers(blocks):
    """Paragraphs opening with a verbless fragment standing in for a heading."""
    paras = [" ".join(b.split()) for b in blocks if len(b.split()) >= 6]
    if len(paras) < STUB_MIN_PARAS:
        return [], None, len(paras)
    stubs = []
    for p in paras:
        m = STUB_OPENER.match(p)
        if not m:
            continue
        frag = m.group(1).strip()
        if len(frag.split()) > STUB_MAX_WORDS or STUB_VERB.search(frag):
            continue
        stubs.append(frag)
    return stubs, len(stubs) * 100 // len(paras), len(paras)


def find_false_precision(text: str):
    """Unhedged four-figure estimates.

    A density threshold could not separate the good document from the bad one
    — they sat 3.0 against 3.9 per 1,000 words. What separates them is
    whether a rounding word sits beside the figure, so that is what is
    measured.
    """
    out = []
    for pattern, why in FALSE_PRECISION:
        for m in re.finditer(pattern, text):
            lo = max(0, m.start() - 60)
            if ESTIMATE_WORDS.search(text[lo:m.start()]):
                continue
            out.append((m.group(0).strip(), why))
    return out


def check_local_grid(data):
    """Is the rank grid there, and is there a map under it?

    Two separate failures, and the second is the one that keeps happening.
    `fetch_map.py` exists and the skill tells a session to run it, but it is
    a separate step before rendering and a skipped step leaves a grid of
    coloured squares floating on nothing. The finding a grid exists to show
    is which direction visibility falls off in, and without the ground under
    it nobody can tell whether that is three blocks or thirty kilometres.

    Returns (blocking, advisory). Both empty for a report with no `local`
    block at all, because a national or e-commerce prospect has no catchment
    and the whole section is correctly absent.

    A MISSING GRID BLOCKS. That is the thing worth enforcing: a local client
    handed a report with no grid has been audited on the half of their
    business they can check from their own phone in ten seconds.

    A MISSING MAP DOES NOT BLOCK, and the reason is practical rather than
    principled. `fetch_map.py` needs Pillow to stitch the tiles, and a client
    session may not have it — so making the map a hard requirement would fail
    every report produced on a machine without it, which is how a gate ends
    up switched off. The renderer already draws the grid unmapped. It is
    worse, and it is said so, loudly, every time.
    """
    blocking, advisory = [], []
    local = data.get("local")
    if not isinstance(local, dict):
        return blocking, advisory
    kws = local.get("keywords") or []
    if not kws:
        blocking.append(("the report has a local section with no keyword grids in it",
                         "pull one with get_lsg_latest_run and paste grid_points and "
                         "summary into local.keywords"))
        return blocking, advisory
    top_map = local.get("map_image")
    for kw in kws:
        if not isinstance(kw, dict):
            continue
        name = str(kw.get("keyword") or "(unnamed keyword)")
        if not (kw.get("grid_points") or kw.get("points")):
            blocking.append((f'"{name}" has no grid points',
                             "paste the run's grid_points in unchanged"))
            continue
        if not (kw.get("map_image") or top_map):
            advisory.append((f'"{name}" has a grid with no map behind it',
                             "python scripts/fetch_map.py <run.json> --into audit.json "
                             "(needs Pillow; without the ground under it nobody can tell "
                             "whether the falloff is three blocks or thirty kilometres)"))
        if not str(kw.get("shape") or "").strip():
            advisory.append((f'"{name}" has no shape line',
                             "the finding is which direction the visibility falls off in, "
                             "not the average — write that sentence yourself"))
    return blocking, advisory


def find_bare_acronym_domains(text: str):
    """Acronym domains printed as if they were an organization's name."""
    seen, out = set(), []
    for m in BARE_ACRONYM_DOMAIN.finditer(text):
        d = m.group(0).lower()
        if d in seen:
            continue
        seen.add(d)
        out.append(d)
    return out


def main():
    # --help is the first thing anyone tries on an unfamiliar script. Without
    # this it is read as a filename and answered with "file not found".
    if len(sys.argv) > 1 and sys.argv[1] in ("-h", "--help", "help"):
        print(__doc__.strip())
        sys.exit(0)
    if len(sys.argv) != 2:
        print("Usage: python check_audit_report.py <report.json | report.html | report.md>")
        sys.exit(1)

    path = Path(sys.argv[1])
    if not path.exists():
        print(f"File not found: {path}")
        sys.exit(1)

    raw = path.read_text(encoding="utf-8", errors="replace")
    suffix = path.suffix.lower()
    # Defined for every input kind: the grid check reads it, and only the
    # JSON branch fills it in.
    data = {}

    # The audit JSON is the source the report is rendered from, so checking it
    # catches a missing severity as a missing field rather than as a word a
    # regex failed to find. HTML and Markdown are still accepted, because a
    # report can be reviewed after it has been rendered.
    if suffix == ".json":
        try:
            data = json.loads(raw)
        except json.JSONDecodeError as exc:
            print(f"FAIL — {path} is not valid JSON: {exc}")
            sys.exit(1)
        sections = sections_from_audit(data)
        text = "\n".join(f"## {h}\n{body}" for h, _lvl, body in sections)
        if data.get("health", {}).get("score") is not None:
            text += f"\nSEO Health Score: {data['health']['score']}/100\n"
    elif suffix in (".html", ".htm"):
        text = strip_markup(raw)
        sections = split_sections(text)
    else:
        text = raw
        sections = split_sections(text)

    hard_fail = False

    placeholders = find_placeholders(text)
    if placeholders:
        hard_fail = True
        print("FAIL — unresolved placeholders found (these must be resolved before delivery):")
        for p in placeholders:
            print(f"  {p}")
        print()

    missing_severity = find_missing_severity_sections(sections)
    if missing_severity:
        print("REVIEW — section(s) with no severity rating (Critical/High/Medium/Low/Healthy):")
        for h in missing_severity:
            print(f"  ## {h}")
        print()

    if not has_cta_section(sections):
        print("REVIEW — no closing / call-to-action section found (expected something like")
        print("  'What We'd Do About It', 'Next Steps', or 'Closing').")
        print()

    full_fix = find_full_fix_sections(sections)
    if full_fix:
        print("REVIEW — section(s) that may have drifted from a teaser into a full fix")
        print("  ('name it, don\u2019t fix it' \u2014 see references/report-template.md):")
        for h, why in full_fix:
            print(f"  ## {h}  —  {why}")
        print()

    defeatist = find_defeatist_phrases(text)
    if defeatist:
        print("REVIEW — written as a verdict on the client rather than as the opportunity")
        print("  (see references/report-template.md, 'How we talk to a client'):")
        for snippet, advice in defeatist:
            print(f"  …{snippet}…")
            print(f"      → {advice}")
        print()

    bare_domains = find_bare_acronym_domains(text)
    if bare_domains:
        print("REVIEW — domain(s) printed where a name belongs. Nobody knows who")
        print("  'tps.ca' is; write 'Toronto Police Service (tps.ca)' or drop the domain:")
        for d in bare_domains:
            print(f"  {d}")
        print()

    meta = find_meta_commentary(text)
    if meta:
        print("REVIEW \u2014 written for the reviewer rather than the client")
        print("  (see seo-strategy-builder/references/plain-language.md, Rule 1):")
        for snippet, why in meta:
            print(f"  \u2026{snippet}\u2026")
            print(f"      \u2192 {why}")
        print()

    you_k, named_k, you_n, your_biz = pronoun_balance(text)
    many = you_n >= SECOND_PERSON_MIN_COUNT
    pronoun_bad = your_biz > 0 or (many and you_k > SECOND_PERSON_LIMIT)
    if your_biz:
        print(f"REVIEW \u2014 \"your company\" / \"your business\" \u00d7 {your_biz}. Use the firm's name.")
        print()
    if many and you_k > SECOND_PERSON_LIMIT:
        print(f"REVIEW \u2014 \"you\" / \"your\" {you_k:.1f} per 1k words ({you_n} uses, "
              f"limit {SECOND_PERSON_LIMIT}; the benchmark document runs 5.3).")
        print("  \"We\" for the work. The firm's name for facts. \"You\" only for what is theirs.")
        print()

    # ── The rest of the house voice ──────────────────────────────────────
    # The JSON only, and the reason is that prose has to be separable from
    # specification. In the audit file it is: `finding`, `why`, `teaser` and
    # the summary are writing, and a scorecard row is not. Once rendered,
    # that distinction is gone — stripping the HTML returns the scorecard,
    # the chart labels and every axis number as one block of text, and the
    # voice checks then read "4,120" off a chart as an over-precise figure
    # in the prose. The JSON is the source the skill tells you to write, so
    # it is the thing to check.
    prose = prose_only(sections) if suffix == ".json" else ""
    voice_bad = []

    c_k, c_words = contraction_rate(prose) if prose else (None, 0)
    if c_k is not None and c_k < CONTRACTION_MIN_PER_1K:
        voice_bad.append("contractions")
        print(f"REVIEW — {c_k:.1f} contractions per 1k words ({c_words} words, "
              f"floor {CONTRACTION_MIN_PER_1K}).")
        print("  The benchmark runs 24.5. \"we'll\", \"we've\", \"you'll\", \"isn't\".")
        print("  A report with none reads as a notice rather than a person.")
        print()

    imp_k, imp_n = imperative_density(prose) if prose else (0.0, 0)
    if imp_k > IMPERATIVE_CEILING and imp_n >= IMPERATIVE_MIN_COUNT:
        voice_bad.append("imperatives")
        print(f"REVIEW — {imp_k:.1f} bare instructions per 1k words ({imp_n} of them, "
              f"ceiling {IMPERATIVE_CEILING}).")
        print("  \"Rewrite the titles\" is a line from our worklist. Write \"we'll\"")
        print("  and the verb. Imperatives stay right inside a specification row.")
        print()

    stubs, stub_pct, _n_par = find_stub_openers(
        [b for _h, _l, b in sections if b.strip()]) if prose else ([], None, 0)
    if stub_pct is not None and stub_pct > STUB_CEILING_PCT:
        voice_bad.append("fragments")
        print(f"REVIEW — {stub_pct}% of paragraphs open with a sentence fragment "
              f"(ceiling {STUB_CEILING_PCT}%):")
        for sfrag in stubs[:5]:
            print(f"  \"{sfrag}.\" — fold it into the sentence after it")
        print()

    fp = find_false_precision(prose) if prose else []
    if fp:
        fp_k = len(fp) / max(1.0, len(prose.split()) / 1000)
        if fp_k > FALSE_PRECISION_LIMIT and len(fp) >= FALSE_PRECISION_MIN_COUNT:
            voice_bad.append("false precision")
            print(f"REVIEW — {len(fp)} over-precise figures ({fp_k:.1f} per 1k):")
            for hit, why in fp[:5]:
                print(f"  \"{hit}\" — {why}")
            print("  Most of this report is third-party estimates. A client who checks")
            print("  4,501 against a real 4,120 has caught us being precisely wrong")
            print("  rather than roughly right. Keep the word \"estimated\".")
            print()

    # ── The rank grid ────────────────────────────────────────────────────
    grid_bad, grid_soft = check_local_grid(data) if suffix == ".json" else ([], [])
    for rows, label in ((grid_bad, "the local section is missing its rank grid"),
                        (grid_soft, "the rank grid is not as readable as it should be")):
        if not rows:
            continue
        print(f"REVIEW — {label}:")
        for problem, remedy in rows:
            print(f"  {problem}")
            print(f"    → {remedy}")
        print()

    slips = find_spelling_slips(text)
    if slips:
        print("REVIEW — spellings that are not Canadian English:")
        for found, want in slips:
            print(f"  {found} → {want}")
        print()

    score_present = has_health_score(text, sections)
    if not score_present:
        print("REVIEW — no 'SEO Health Score: XX/100' found (expected near the top of the")
        print("  executive summary — see references/audit-methodology.md's scoring section).")
        print()

    if (
        not placeholders
        and not missing_severity
        and not full_fix
        and not slips
        and not meta
        and not pronoun_bad
        and not defeatist
        and not bare_domains
        and has_cta_section(sections)
        and score_present
        and not voice_bad
        and not grid_bad
    ):
        print("PASS — no unresolved placeholders, every weakness section has a severity")
        print("  rating, a closing/CTA section is present, an SEO Health Score is reported,")
        print("  the spelling is Canadian, the voice is the client\'s not the reviewer\'s,")
        print("  every competitor is named rather than left as a")
        print("  domain, the tone states the opportunity rather than the client's shortfall,")
        print("  and no section reads like a full fix instead of a teaser.")
        print("  It also reads in the house voice, and where the client has a")
        print("  catchment the rank grid is there. Read any REVIEW lines above:")
        print("  a grid with no map under it still renders, and still reads worse.")

    sys.exit(1 if hard_fail else 0)


if __name__ == "__main__":
    main()
