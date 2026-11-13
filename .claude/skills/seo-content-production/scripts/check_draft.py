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
    want = len(level_pattern)
    return [h[1] for h in (_heading(l) for l in text.splitlines())
            if h and h[0] == want]


def _heading(line: str):
    """(level, text) for a heading line, else None.

    Two notations, both real. Markdown "## Heading", and the explicit
    "[H2] Heading" our page documents use because whoever builds the page is
    not whoever wrote it, and a markdown "##" does not tell them whether an H2
    or an H3 inside a collapsed section was meant.
    """
    s = line.strip()
    m = re.match(r"^(#{1,6})\s*(?:\[H([1-6])\]\s*)?(.+)$", s)
    if m:
        lvl = int(m.group(2)) if m.group(2) else len(m.group(1))
        return lvl, m.group(3).strip()
    m = re.match(r"^\[H([1-6])\]\s*(.+)$", s, re.I)
    if m:
        return int(m.group(1)), m.group(2).strip()
    return None


def count_faq_questions(text: str) -> int:
    """Questions inside the FAQ section, at whatever heading level.

    The level is deliberately not fixed. Real documents write FAQ questions as
    H3 under the FAQ heading, and real documents also write them as H2 — a page
    with six questions has six questions either way, and failing it for the
    level would be the checker marking a page down for its own convenience.

    So the FAQ section runs from its heading to the next heading that is NOT
    itself a question, rather than to the next heading of any kind.
    """
    lines = text.splitlines()
    start = None
    for i, line in enumerate(lines):
        h = _heading(line)
        if h and re.search(r"\bfaqs?\b|frequently asked|common questions", h[1], re.I):
            start = i
            break
    if start is None:
        scope = lines                      # no FAQ heading: count the whole page
    else:
        end = len(lines)
        for j in range(start + 1, len(lines)):
            h = _heading(lines[j])
            if h and h[0] <= 2 and not h[1].rstrip().endswith("?"):
                end = j
                break
        scope = lines[start + 1:end]

    qs = set()
    for line in scope:
        s = line.strip()
        h = _heading(line)
        if h and h[1].rstrip().endswith("?"):
            qs.add(h[1].strip())
        elif re.match(r"^\*\*.*\?\*\*$", s):
            qs.add(s)
        elif re.match(r"^(?:Q[:.]|\d+[.)])\s*.*\?$", s):
            qs.add(s)
    return len(qs)


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
    "meta": 15, "words": 15, "links": 10, "faq": 10,
    "h1": 5, "headings": 5, "banned": 15,
    "sections": 15, "blocks": 10,
}
PASS_MARK = 70

# ---------------------------------------------------------------------------
# The section contract, from references/content-templates.md.
#
# A set of seventy pages reads as one body of work because every page of a
# type answers the same questions in the same order. That is also the only way
# the set stays reviewable at volume: the approver checks each section did its
# job instead of reverse-engineering what the writer was attempting.
#
# Patterns match the section's JOB, not its exact wording, because the heading
# formulas carry the client's own service and city names. Each entry is
# (pattern, human name, required).
SECTION_CONTRACTS = {
    "service page": [
        (r"who\s+(needs|should|is this)", "Who needs this service", True),
        (r"what\s+.{0,40}\bprovide", "What the client provides", True),
        (r"\bprocess\b|\bhow\s+.{0,30}works\b|\bwhat to expect\b", "How the process works", True),
        (r"\bdeliver|\bwhat\s+.{0,40}\b(means|changes)\b", "What it delivers", False),
        (r"\bwhy\b.{0,50}\bchoose\b|\bwhy\b.{0,30}\bus\b", "Why choose the client", True),
        (r"\brelated\b|\bother\s+services\b|\balso\s+handle\b", "Related services", False),
        (r"\bacross\b|\bareas?\s+(we\s+)?serve|\bserving\b|\bthroughout\b", "Areas served", False),
        (r"frequently asked|\bfaqs?\b|common questions", "FAQ", True),
        (r"\b(contact|request|speak to|schedule|book|call)\b", "Call to action", True),
    ],
    "service hub page": [
        (r"\bwe\s+(handle|defend|cover)\b|\bcharges\b|\bservices\b", "The directory of child pages", True),
        (r"first\s+\d+|\bfirst\s+(hours|days|steps)\b|\bwhat to do\b", "The urgent reader's steps", False),
        (r"what happens\b|\bprocess\b", "What happens next", True),
        (r"how\s+we\s+(build|approach|handle)", "The client's method", False),
        (r"how to choose", "How to choose a provider", False),
        (r"who will\b|\bwho handles\b|\byour team\b", "Who is accountable", False),
        (r"\bacross\b|\bareas?\s+(we\s+)?serve|\bserving\b", "Geography", False),
        (r"\bresults?\b|\boutcomes\b|\bsuccesses\b", "Proof", False),
        (r"frequently asked|\bfaqs?\b", "FAQ", True),
        (r"\b(contact|speak to|request|call)\b", "Call to action", True),
    ],
    "location page": [
        (r"\bservices\b.{0,40}\bin\b|\bwhat we (do|offer)\b", "Services offered here", True),
        (r"\babout\b", "About this place — the locally specific section", True),
        (r"who we serve|\bwho\b.{0,30}\bserve\b|\bclients\b", "Who we serve here", False),
        (r"\bwhy\b.{0,50}\bchoose\b", "Why choose the client here", True),
        (r"what happens\b|\bprocess\b", "The local process", False),
        (r"nearby|\bsurrounding\b|\balso serve\b", "Nearby areas", True),
        (r"frequently asked|\bfaqs?\b", "FAQ", True),
        (r"\b(contact|request|call|speak)\b", "Call to action", True),
        (r"entity summary", "Entity summary", False),
    ],
    "blog post": [
        (r"frequently asked|\bfaqs?\b", "FAQ", True),
    ],
    "audience page": [
        (r"\bfacing\b|\bchallenges\b|\bwhat\b.{0,40}\bface\b", "What this audience faces", True),
        (r"how\s+.{0,40}\bwork(s)?\s+with\b|\bfor\b.{0,30}\bclients\b", "How the client works with them", True),
        (r"\bwhy\b.{0,50}\bchoose\b", "Why they choose the client", True),
        (r"\bprocess\b", "The process for this audience", False),
        (r"frequently asked|\bfaqs?\b", "FAQ", True),
        (r"\b(first step|contact|request|call)\b", "Call to action", True),
    ],
    "resource guide": [
        (r"\bcontents\b|\bin this guide\b|\bon this page\b", "Table of contents", False),
        (r"what to do next|\bnext steps\b|\bwhere to start\b", "What to do next", False),
        (r"frequently asked|\bfaqs?\b", "FAQ", True),
    ],
}
# Aliases, so a profile row reading "Service Pages (core)" or "Blog" still
# finds its contract rather than silently skipping the check.
CONTRACT_ALIASES = {
    "service": "service page", "service pages": "service page",
    "service pages (core)": "service page", "hub": "service hub page",
    "hub page": "service hub page", "service hub": "service hub page",
    "location": "location page", "location pages": "location page",
    "blog": "blog post", "blog posts": "blog post",
    "standard blog post": "blog post", "article": "blog post",
    "audience": "audience page", "audience pages": "audience page",
    "audience / industry pages": "audience page",
    "resource": "resource guide", "pillar": "resource guide",
    "resource / pillar guide": "resource guide",
    "pillar guide": "resource guide", "resource guides": "resource guide",
}

# The two blocks every page type ends with, whatever it is.
UNIVERSAL_BLOCKS = [
    (r"key takeaways", "Key Takeaways"),
    (r"schema\s+(recommendations?|plan|markup)", "Schema Recommendations"),
]

# Markers that are a normal, useful part of a draft and a failure in a
# finished page. [VERIFY:] comes from the claims policy's standing rule: a
# specific number or credential with no source is flagged for the approver,
# never generated. Nothing reaches a page by being forgotten.
OPEN_MARKER = re.compile(r"\[(?:VERIFY|NEEDS CLIENT INPUT|NEEDS DATA|TODO)\b[^\]]*\]",
                         re.IGNORECASE)


def heading_lines(text: str, max_level: int = 6) -> list:
    """Heading text, down to max_level."""
    return [h[1] for h in (_heading(l) for l in text.splitlines())
            if h and h[0] <= max_level]


def resolve_contract(content_type: str):
    key = re.sub(r"\s+", " ", content_type.strip().lower())
    if key in SECTION_CONTRACTS:
        return key, SECTION_CONTRACTS[key]
    if key in CONTRACT_ALIASES:
        k = CONTRACT_ALIASES[key]
        return k, SECTION_CONTRACTS[k]
    return None, None


def check_sections(text: str, contract: list):
    """Required sections present, and in the contract's order.

    Absence is a failure; order is a warning. A page missing "Who needs this"
    is missing the section that tells the reader the page is for them, which is
    a different kind of problem from having written it in the wrong place.
    """
    # The contract describes the page's H2 sections. An H3 inside one is not a
    # section — without this, a "Step 1: Immediate First Contact" sub-step
    # matches the call-to-action pattern and the page is reported out of order
    # for a heading that was never a section. Nor is the H1: it is the page's
    # title, and a title like "Sex Crimes Lawyers in Toronto and Across
    # Ontario" otherwise matches the geography section at position zero.
    found = [h[1] for h in (_heading(l) for l in text.splitlines())
             if h and h[0] == 2]
    missing, positions = [], []
    for pattern, name, required in contract:
        at = None
        for i, h in enumerate(found):
            if re.search(pattern, h, re.IGNORECASE):
                at = i
                break
        if at is None:
            if required:
                missing.append(name)
        else:
            positions.append((name, at))
    out_of_order = [positions[i][0] for i in range(1, len(positions))
                    if positions[i][1] < positions[i - 1][1]]
    return missing, out_of_order, len(positions)


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
    # Length limits, where the values can be read. content-templates.md asks
    # for the character count in brackets after the value while the page is in
    # review, so the approver can see the limit was met without counting — so
    # that annotation is stripped before measuring, or the checker reports the
    # length of its own annotation.
    def value(label):
        m = re.search(rf"{label}[^\n:]*:\s*(.+)", text[:1500], re.I)
        if not m:
            return None
        v = m.group(1).strip()
        v = re.sub(r"\s*\((?:\d+\s*(?:chars?|characters)|[\d,]+)\)\s*$", "", v, flags=re.I)
        return v.strip()

    v = value("meta title")
    if v and len(v) > 60:
        problems.append(f"meta title is {len(v)} characters (max 60)")
    v = value("meta description")
    if v:
        d = len(v)
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

    # Section contract for this page type
    contract_name, contract = resolve_contract(content_type)
    if contract:
        possible += WEIGHTS["sections"]
        missing, disordered, present = check_sections(draft, contract)
        if missing:
            print(f"[FAIL] Section contract ({contract_name}): missing "
                  f"{len(missing)} required section(s) — " + "; ".join(missing))
            print("       content-templates.md has the heading formula for each. A "
                  "page missing these is a different page type or an oversight.")
            failures += 1
        else:
            print(f"[OK] Section contract ({contract_name}): every required section "
                  f"present, {present} of {len(contract)} total")
            earned += WEIGHTS["sections"]
        if disordered:
            print(f"[REVIEW] Out of contract order: {', '.join(disordered)} — the "
                  "order is what lets a reader who has seen one page navigate the next")
    else:
        print(f"[SKIP] Section contract: no contract for content type "
              f"'{content_type}'. Use one of: "
              + ", ".join(sorted(SECTION_CONTRACTS)))

    # Key Takeaways and Schema Recommendations
    possible += WEIGHTS["blocks"]
    heads = heading_lines(draft)
    missing_blocks = [name for pattern, name in UNIVERSAL_BLOCKS
                      if not any(re.search(pattern, h, re.I) for h in heads)]
    if missing_blocks:
        print(f"[FAIL] Missing: {', '.join(missing_blocks)}. Every page type ends "
              "with both — Key Takeaways is what an AI answer quotes and a skimming "
              "reader reads; Schema Recommendations is what the developer builds from")
        failures += 1
    else:
        print("[OK] Key Takeaways and Schema Recommendations both present")
        earned += WEIGHTS["blocks"]

    # Unresolved markers. Not scored — a hard gate either way.
    open_markers = OPEN_MARKER.findall(draft)
    if open_markers:
        print(f"[FAIL] {len(open_markers)} unresolved marker(s) — these are correct in "
              "a draft and a failure in a finished page:")
        for m in open_markers[:8]:
            print(f"       {m}")
        if len(open_markers) > 8:
            print(f"       ... and {len(open_markers) - 8} more")
        failures += 1
    else:
        print("[OK] No unresolved [VERIFY] / [NEEDS CLIENT INPUT] markers")

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
