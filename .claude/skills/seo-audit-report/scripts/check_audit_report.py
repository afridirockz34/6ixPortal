#!/usr/bin/env python3
"""
check_audit_report.py — mechanical QC for an seo-audit-report deliverable.

Checks (mirrors the discipline of seo-strategy-builder/scripts/check_strategy_doc.py
and seo-content-production/scripts/check_draft.py):

1. HARD FAIL  — unresolved [NEEDS DATA: ...] / [NEEDS CLIENT INPUT: ...] placeholders.
2. REVIEW     — a weakness-category section with no severity rating attached.
3. REVIEW     — no closing / call-to-action section found.
4. REVIEW     — a section that reads like a full step-by-step fix rather than a
                one-line teaser ("show the wound, not the stitches").
5. REVIEW     — no "SEO Health Score" (a 0-100 number) found in the executive
                summary (see references/audit-methodology.md's scoring section).

Usage:
    python check_audit_report.py <report_file.md>

Exit code 1 only on the hard-fail case (unresolved placeholders). Everything
else prints as REVIEW — a human reads those, the script doesn't block on them.
"""

import json
import re
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
        print("  ('show the wound, not the stitches' — see references/report-template.md):")
        for h, why in full_fix:
            print(f"  ## {h}  —  {why}")
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
        and has_cta_section(sections)
        and score_present
    ):
        print("PASS — no unresolved placeholders, every weakness section has a severity")
        print("  rating, a closing/CTA section is present, an SEO Health Score is reported,")
        print("  the spelling is Canadian, and no section reads like a full fix instead of")
        print("  a teaser.")

    sys.exit(1 if hard_fail else 0)


if __name__ == "__main__":
    main()
