#!/usr/bin/env python3
"""
Mechanical quality check for a content draft against a client profile's
content standards and banned-phrase list.

This catches what's cheap and reliable to catch by machine — word count,
link count, heading structure, banned phrases — so a human reviewer's
attention goes to what actually needs judgment: does this sound right,
is the claim defensible, is the example true.

Usage:
    python check_draft.py <draft_file.md> <profile_file.md> <content_type>

    content_type must match a row in the profile's "Content Standards"
    table, e.g. "Blog post", "Service page", "Location page", "Audience page".
    Matching is case-insensitive and ignores extra whitespace.

Exits 0 if all checks pass, 1 if any check fails (so it's usable in a
loop/CI-style workflow, not just interactively).
"""

import re
import sys
from pathlib import Path


def load_text(path: str) -> str:
    p = Path(path)
    if not p.exists():
        print(f"ERROR: file not found: {path}")
        sys.exit(2)
    return p.read_text(encoding="utf-8")


def word_count(text: str) -> int:
    # Strip markdown structural characters before counting so headings,
    # link syntax, and table pipes don't skew the count.
    stripped = re.sub(r"```.*?```", " ", text, flags=re.DOTALL)  # code blocks
    stripped = re.sub(r"!\[.*?\]\(.*?\)", " ", stripped)  # images
    stripped = re.sub(r"\[([^\]]*)\]\([^)]*\)", r"\1", stripped)  # links -> link text
    stripped = re.sub(r"[#*_`|>-]", " ", stripped)
    words = [w for w in stripped.split() if any(c.isalnum() for c in w)]
    return len(words)


def count_internal_links(text: str) -> int:
    # Counts markdown links that look internal: relative paths, or paths
    # starting with "/". Links to http(s) external domains are excluded.
    links = re.findall(r"\[[^\]]*\]\(([^)]+)\)", text)
    internal = [
        l for l in links
        if not re.match(r"^https?://", l.strip()) or "nrlawyers.com" in l  # adjust per-project if the client's own domain differs
    ]
    return len(internal)


def count_headings(text: str, level_pattern: str) -> list:
    return re.findall(rf"^{level_pattern}\s+(.+)$", text, flags=re.MULTILINE)


def count_faq_questions(text: str) -> int:
    # Look inside anything resembling an FAQ section for heading lines
    # ending in "?", or bold "question" lines ending in "?".
    faq_section_match = re.search(
        r"(##+\s*(?:FAQ|Frequently Asked Questions).*?)(?=\n##[^#]|\Z)",
        text, flags=re.IGNORECASE | re.DOTALL,
    )
    scope = faq_section_match.group(1) if faq_section_match else text
    heading_qs = re.findall(r"^#{2,4}\s+.*\?\s*$", scope, flags=re.MULTILINE)
    bold_qs = re.findall(r"^\*\*.*\?\*\*\s*$", scope, flags=re.MULTILINE)
    q_marks_lines = re.findall(r"^(?:Q[:.]|\d+\.)\s*.*\?\s*$", scope, flags=re.MULTILINE)
    return len(set(heading_qs) | set(bold_qs) | set(q_marks_lines))


def parse_content_standards(profile_text: str, content_type: str) -> dict | None:
    table_match = re.search(
        r"##\s*Content Standards.*?\n(\|.+?\n)+", profile_text, flags=re.IGNORECASE | re.DOTALL
    )
    if not table_match:
        return None
    table = table_match.group(0)
    rows = [r for r in table.splitlines() if r.strip().startswith("|")]
    target = content_type.strip().lower()
    for row in rows:
        cells = [c.strip() for c in row.strip("|").split("|")]
        if not cells or "---" in cells[0]:
            continue
        if cells[0].strip().lower() == target:
            # Expect: Content type | Target words | Range | Min internal links | Min FAQ
            try:
                def nums(cell: str) -> list:
                    # Match numbers that may include comma thousands separators
                    # (e.g. "1,200"), then strip the commas before converting.
                    return [int(n.replace(",", "")) for n in re.findall(r"\d[\d,]*", cell)]

                range_nums = nums(cells[2])
                low, high = range_nums[0], range_nums[1]
                link_nums = nums(cells[3]) if len(cells) > 3 else []
                min_links = link_nums[0] if link_nums else 0
                faq_nums = nums(cells[4]) if len(cells) > 4 else []
                min_faq = faq_nums[0] if faq_nums else 0
                return {"min_words": low, "max_words": high, "min_links": min_links, "min_faq": min_faq}
            except (IndexError, ValueError):
                return None
    return None


def parse_banned_phrases(profile_text: str) -> list:
    section = re.search(
        r"##\s*Words and Phrases to Avoid Entirely\s*\n(.+?)(?=\n##|\Z)",
        profile_text, flags=re.IGNORECASE | re.DOTALL,
    )
    if not section:
        return []
    raw = section.group(1)
    # Split on the middle-dot bullet style used in these profiles, or newlines/commas as fallback
    parts = re.split(r"·|\n|,", raw)
    return [p.strip().strip('"').lower() for p in parts if p.strip() and len(p.strip()) > 2]


def main():
    # --help is the first thing anyone tries on an unfamiliar script, and it
    # should not be reported as a failure.
    if len(sys.argv) > 1 and sys.argv[1] in ("-h", "--help", "help"):
        print(__doc__.strip())
        sys.exit(0)
    if len(sys.argv) != 4:
        print(__doc__)
        sys.exit(2)

    draft_path, profile_path, content_type = sys.argv[1], sys.argv[2], sys.argv[3]
    draft = load_text(draft_path)
    profile = load_text(profile_path)

    print(f"Checking {draft_path} as content type '{content_type}' against {profile_path}\n")

    failures = 0

    # Word count
    standards = parse_content_standards(profile, content_type)
    wc = word_count(draft)
    if standards:
        lo, hi = standards["min_words"], standards["max_words"]
        status = "OK" if lo <= wc <= hi else "FAIL"
        if status == "FAIL":
            failures += 1
        print(f"[{status}] Word count: {wc} (target range {lo}-{hi})")
    else:
        print(f"[SKIP] Word count: {wc} words — no matching row for '{content_type}' found in profile's Content Standards table; add one or check manually")

    # Internal links
    links = count_internal_links(draft)
    if standards:
        status = "OK" if links >= standards["min_links"] else "FAIL"
        if status == "FAIL":
            failures += 1
        print(f"[{status}] Internal links: {links} (minimum {standards['min_links']})")
    else:
        print(f"[SKIP] Internal links: {links} found — no standard to check against")

    # FAQ count
    faqs = count_faq_questions(draft)
    if standards:
        status = "OK" if faqs >= standards["min_faq"] else "FAIL"
        if status == "FAIL" and standards["min_faq"] > 0:
            failures += 1
        print(f"[{status if standards['min_faq'] else 'INFO'}] FAQ questions detected: {faqs} (minimum {standards['min_faq']})")
    else:
        print(f"[INFO] FAQ questions detected: {faqs}")

    # Heading structure
    h1s = count_headings(draft, "#")
    h2s = count_headings(draft, "##")
    if len(h1s) != 1:
        print(f"[FAIL] Found {len(h1s)} H1 heading(s) — a page should have exactly 1")
        failures += 1
    else:
        print(f"[OK] Exactly 1 H1 heading: \"{h1s[0]}\"")
    print(f"[INFO] {len(h2s)} H2 heading(s) found")

    # Banned phrases
    banned = parse_banned_phrases(profile)
    draft_lower = draft.lower()
    hits = [b for b in banned if b in draft_lower]
    if hits:
        print(f"[FAIL] Banned phrase(s) found: {', '.join(hits)}")
        failures += 1
    else:
        print(f"[OK] No banned phrases found ({len(banned)} checked)")

    print()
    if failures:
        print(f"{failures} check(s) failed. Fix these before treating the draft as done — "
              f"then still review it for voice, accuracy, and whether every specific "
              f"claim is actually grounded in the client profile.")
        sys.exit(1)
    else:
        print("All mechanical checks passed. This does not mean the draft is done — "
              "still review it for voice, factual accuracy, and whether every claim "
              "is grounded in the client profile before publishing.")
        sys.exit(0)


if __name__ == "__main__":
    main()
