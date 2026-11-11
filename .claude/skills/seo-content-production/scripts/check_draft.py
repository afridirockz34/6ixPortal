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


# What each check is worth. The weights say what we actually care about: a
# draft can be a little short and still be publishable, but one that makes a
# banned claim or has no meta block is not, whatever else it gets right.
WEIGHTS = {
    "meta": 15, "words": 20, "links": 15, "faq": 10,
    "h1": 10, "headings": 10, "banned": 20,
}
PASS_MARK = 70


def check_meta_block(text):
    """
    Title, description, slug and target keyword, stated at the top.

    Not decoration: a draft handed over without them gets its title written by
    whoever pastes it into the CMS, which is how a page ends up optimised for
    nothing. Returns (ok, [problems]).
    """
    head = text[:1200].lower()
    problems = []
    for label, keys in (("meta title", ("meta title", "title tag")),
                        ("meta description", ("meta description",)),
                        ("slug", ("slug", "url")),
                        ("primary keyword", ("primary keyword", "target keyword"))):
        if not any(k in head for k in keys):
            problems.append(label)
    # Length limits, where the values can be read.
    m = re.search(r"meta title[^\n:]*:\s*(.+)", text[:1200], re.I)
    if m and len(m.group(1).strip()) > 60:
        problems.append(f"meta title is {len(m.group(1).strip())} characters (max 60)")
    m = re.search(r"meta description[^\n:]*:\s*(.+)", text[:1200], re.I)
    if m:
        d = len(m.group(1).strip())
        if d > 160 or d < 110:
            problems.append(f"meta description is {d} characters (want 110-160)")
    return (not problems), problems


def main():
    # --help is the first thing anyone tries on an unfamiliar script, and it
    # should not be reported as a failure.
    if len(sys.argv) > 1 and sys.argv[1] in ("-h", "--help", "help"):
        print(__doc__.strip())
        sys.exit(0)
    args = [a for a in sys.argv[1:] if not a.startswith("--")]
    approver = ""
    for a in sys.argv[1:]:
        if a.startswith("--approver"):
            approver = a.partition("=")[2].strip() or ""
    if len(args) != 3:
        print(__doc__)
        sys.exit(2)
    draft_path, profile_path, content_type = args[0], args[1], args[2]
    draft = load_text(draft_path)
    profile = load_text(profile_path)

    print(f"Checking {draft_path} as content type '{content_type}' against {profile_path}\n")

    failures = 0
    earned = 0
    possible = 0

    # Meta block
    possible += WEIGHTS["meta"]
    meta_ok, meta_problems = check_meta_block(draft)
    if meta_ok:
        print("[OK] Meta block present: title, description, slug and primary keyword")
        earned += WEIGHTS["meta"]
    else:
        print(f"[FAIL] Meta block: {'; '.join(meta_problems)}")
        failures += 1

    # Word count
    standards = parse_content_standards(profile, content_type)
    wc = word_count(draft)
    if standards:
        possible += WEIGHTS["words"]
        lo, hi = standards["min_words"], standards["max_words"]
        status = "OK" if lo <= wc <= hi else "FAIL"
        if status == "OK":
            earned += WEIGHTS["words"]
        if status == "FAIL":
            failures += 1
        print(f"[{status}] Word count: {wc} (target range {lo}-{hi})")
    else:
        print(f"[SKIP] Word count: {wc} words — no matching row for '{content_type}' found in profile's Content Standards table; add one or check manually")

    # Internal links
    links = count_internal_links(draft)
    if standards:
        possible += WEIGHTS["links"]
        status = "OK" if links >= standards["min_links"] else "FAIL"
        if status == "OK":
            earned += WEIGHTS["links"]
        if status == "FAIL":
            failures += 1
        print(f"[{status}] Internal links: {links} (minimum {standards['min_links']})")
    else:
        print(f"[SKIP] Internal links: {links} found — no standard to check against")

    # FAQ count
    faqs = count_faq_questions(draft)
    if standards:
        possible += WEIGHTS["faq"]
        status = "OK" if faqs >= standards["min_faq"] else "FAIL"
        if status == "OK":
            earned += WEIGHTS["faq"]
        if status == "FAIL" and standards["min_faq"] > 0:
            failures += 1
        print(f"[{status if standards['min_faq'] else 'INFO'}] FAQ questions detected: {faqs} (minimum {standards['min_faq']})")
    else:
        print(f"[INFO] FAQ questions detected: {faqs}")

    # Heading structure
    possible += WEIGHTS["h1"] + WEIGHTS["headings"]
    h1s = count_headings(draft, "#")
    h2s = count_headings(draft, "##")
    if len(h1s) != 1:
        print(f"[FAIL] Found {len(h1s)} H1 heading(s) — a page should have exactly 1")
        failures += 1
    else:
        print(f"[OK] Exactly 1 H1 heading: \"{h1s[0]}\"")
        earned += WEIGHTS["h1"]
    if len(h2s) >= 3:
        print(f"[OK] {len(h2s)} H2 headings — the page is navigable")
        earned += WEIGHTS["headings"]
    else:
        print(f"[FAIL] Only {len(h2s)} H2 heading(s) — a page this length needs at "
              "least 3 so a reader can skim it")
        failures += 1

    # Banned phrases
    possible += WEIGHTS["banned"]
    banned = parse_banned_phrases(profile)
    draft_lower = draft.lower()
    hits = [b for b in banned if b in draft_lower]
    if hits:
        print(f"[FAIL] Banned phrase(s) found: {', '.join(hits)}")
        failures += 1
    else:
        print(f"[OK] No banned phrases found ({len(banned)} checked)")
        earned += WEIGHTS["banned"]

    # ── Readiness score and the publish gate ─────────────────────────────
    score = round(100 * earned / possible) if possible else 0
    print()
    print(f"Readiness score: {score}/100  (pass mark {PASS_MARK})")
    if possible < sum(WEIGHTS.values()):
        missed = sum(WEIGHTS.values()) - possible
        print(f"  Scored out of the {possible} points this profile gave something to "
              f"check against; {missed} points' worth was skipped. A skipped check is "
              "a gap in the profile, not a fault in the draft — but it also means the "
              "score is answering a smaller question than usual.")

    # Enough of the draft has to have been checkable for the score to mean
    # anything. 100/100 out of 55 points is not a pass, it is a profile with
    # rows missing, and clearing on it would be the same as inventing a figure.
    MIN_COVERAGE = 70
    enough = possible >= MIN_COVERAGE
    cleared = score >= PASS_MARK and failures == 0 and bool(approver) and enough
    if not enough:
        print(f"Only {possible} points of checks could run, below the {MIN_COVERAGE} "
              "needed for the score to mean anything. Add the missing rows to the "
              "profile's Content Standards table and run it again.")
    if failures:
        print(f"{failures} check(s) failed.")
    if not approver:
        print("No approver named. Pass --approver=\"Name <email>\" — the person who "
              "read this draft and is accountable for it going live.")

    print()
    if cleared:
        print(f"CLEARED TO PUBLISH — {score}/100, approved by {approver}.")
        print("The score is mechanical: it says this draft is the right shape, not "
              "that it is true or that it sounds like the client. "
              f"{approver} is on record as having judged that.")
        print("Record the approver and the score in the implementation log alongside "
              "the URL, so the monthly report can show what shipped and who signed it off.")
        sys.exit(0)

    print("NOT CLEARED TO PUBLISH.")
    print("A draft publishes when three things are true: it scores "
          f"{PASS_MARK} or better, no check has failed, and a named person has "
          "approved it. The score is mechanical and cannot read for voice, factual "
          "accuracy, or whether a claim is grounded in the client profile — that is "
          "what the named approver is for, and it is why a high score alone does not "
          "clear anything.")
    sys.exit(1)


if __name__ == "__main__":
    main()
