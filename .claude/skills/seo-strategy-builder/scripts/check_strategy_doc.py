#!/usr/bin/env python3
"""
Mechanical completeness and fact-check scan for an assembled SEO strategy
document (Phase 1, 2, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 17, 18, or 19
content, or any combination).

Two kinds of thing get checked:

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

Usage:
    python check_strategy_doc.py <document_file.md> [--phases 1,2,5,7,8,9]

    --phases is optional. If given, the script reports which of those
    phase numbers it found a heading for, so you can confirm an assembled
    document actually contains everything that was requested.

Exits 0 if no unresolved placeholders are found (dead-advice warnings alone
don't fail the run — they're a prompt to review, not a blocker). Exits 1 if
any unresolved placeholder remains.
"""

import re
import sys
from pathlib import Path

PLACEHOLDER_PATTERN = re.compile(r"\[NEEDS (?:CLIENT INPUT|DATA)\s*:?[^\]]*\]", re.IGNORECASE)

# Heading lines that either mention "Phase" or start with a digit — real
# documents phrase phase headings inconsistently ("## Phase 1: ...",
# "# Foundation Document (Phase 1): ...", "# Phase 7/8: Keyword Clusters"),
# so this pulls every 1-2 digit number out of a candidate heading line
# rather than requiring one rigid position/separator. Capped at 19 (the
# highest phase number this system uses) to avoid picking up unrelated
# numbers like a year.
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
                if 1 <= num <= 19:
                    phases.add(num)
    return phases


def main():
    raw = sys.argv[1:]
    positional = []
    expected_phases = None
    i = 0
    while i < len(raw):
        a = raw[i]
        if a.startswith("--phases"):
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
        print(f"{failures} check(s) failed. Resolve unresolved placeholders before calling this document done. "
              f"Also read through any REVIEW items above — this script cannot tell context from content.")
        sys.exit(1)
    else:
        print("No unresolved placeholders. Still read through any REVIEW items above, confirm phase coverage "
              "matches what was requested, and get a human review — this script only catches what's mechanically catchable.")
        sys.exit(0)


if __name__ == "__main__":
    main()
