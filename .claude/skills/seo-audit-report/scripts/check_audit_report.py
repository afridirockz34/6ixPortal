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

HEALTH_SCORE_PATTERN = re.compile(r"\bseo health score\b[^\d]{0,20}(\d{1,3})\s*/\s*100", re.IGNORECASE)


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
    if len(sys.argv) != 2:
        print("Usage: python check_audit_report.py <report_file.md>")
        sys.exit(1)

    path = Path(sys.argv[1])
    if not path.exists():
        print(f"File not found: {path}")
        sys.exit(1)

    text = path.read_text(encoding="utf-8", errors="replace")
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

    score_present = has_health_score(text, sections)
    if not score_present:
        print("REVIEW — no 'SEO Health Score: XX/100' found (expected near the top of the")
        print("  executive summary — see references/audit-methodology.md's scoring section).")
        print()

    if (
        not placeholders
        and not missing_severity
        and not full_fix
        and has_cta_section(sections)
        and score_present
    ):
        print("PASS — no unresolved placeholders, every weakness section has a severity")
        print("  rating, a closing/CTA section is present, an SEO Health Score is reported,")
        print("  and no section reads like a full fix instead of a teaser.")

    sys.exit(1 if hard_fail else 0)


if __name__ == "__main__":
    main()
