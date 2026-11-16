#!/usr/bin/env python3
"""
Does each page template's section budget fit inside its word limit?

This exists because content-templates.md shipped with service-page section
minimums summing to 2,470 against a 2,500 ceiling. Every individual range
looked generous and reasonable; nobody added them up. A writer who hit every
minimum was already at the limit, and the only way to produce a page in range
was to write four sections short — which is exactly what happened, and the
report came back as "the skill has a conflict".

So the arithmetic is checked mechanically, on real files, every run:

  * every section minimum is parsed out of each template's table in
    references/content-templates.md;
  * the stated **Budget:** line must equal that sum, so the documented figure
    cannot drift from the table above it;
  * the sum must sit below the page type's floor in the client profile's
    Content Standards table, so a page that hits the floor got there by
    expanding sections rather than by having nowhere to go;
  * and the maximum sum must reach the ceiling, or the page type cannot
    legitimately use its own allowance.

    python tools/tests/test_section_budget.py
"""
import re
import sys
from pathlib import Path

REPO = Path(__file__).resolve().parent.parent.parent
SKILL = REPO / ".claude/skills/seo-content-production"
TEMPLATES = SKILL / "references/content-templates.md"
PROFILE = SKILL / "assets/nr-lawyers-client-profile.md"

passed = failed = 0


def check(name, ok, detail=""):
    global passed, failed
    if ok:
        passed += 1
        print(f"PASS  {name}")
    else:
        failed += 1
        print(f"FAIL  {name}" + (f"\n        {detail}" if detail else ""))


# Template heading in content-templates.md -> content type in the profile's
# Content Standards table. The names have to agree or check_draft.py skips the
# word-count check entirely, which is its own silent failure.
TEMPLATE_TO_TYPE = {
    "Template 1: Service page": "Service page",
    "Template 2: Service hub page": "Service hub page",
    "Template 3: Location page": "Location page",
    "Template 4: Blog post": "Blog post",
    "Template 5: Audience page": "Audience page",
    "Template 6: Resource / pillar guide": "Resource guide",
}

RANGE_RE = re.compile(r"(\d[\d,]*)\s*[–-]\s*(\d[\d,]*)")
BUDGET_RE = re.compile(r"\*\*Budget:\s*([\d,]+)", re.IGNORECASE)
# "| 1–N | ... | 180–300 each | ... Five to eight of them"
EACH_RE = re.compile(r"(\d[\d,]*)\s*[–-]\s*(\d[\d,]*)\s*each", re.IGNORECASE)
COUNT_WORDS = {"three": 3, "four": 4, "five": 5, "six": 6, "seven": 7,
               "eight": 8, "nine": 9, "ten": 10, "eleven": 11, "twelve": 12}


def num(s):
    return int(str(s).replace(",", ""))


def sections_of(block: str):
    """(min, max) per section row, expanding a repeated '... each' row."""
    out = []
    for line in block.splitlines():
        if not line.strip().startswith("|"):
            continue
        cells = [c.strip() for c in line.strip().strip("|").split("|")]
        if len(cells) < 3:
            continue
        words = cells[2]
        if words in ("Words", "—", "not counted", ""):
            continue
        m = EACH_RE.search(words)
        if m:
            # How many of them? The row's own text says, in words.
            lo = hi = None
            for w, n in COUNT_WORDS.items():
                mm = re.search(rf"\b{w}\s+to\s+(\w+)\b", line, re.IGNORECASE)
                if mm and mm.group(1).lower() in COUNT_WORDS:
                    lo, hi = n, COUNT_WORDS[mm.group(1).lower()]
                    break
            if lo is None:
                continue
            out.append((num(m.group(1)) * lo, num(m.group(2)) * hi))
            continue
        m = RANGE_RE.search(words)
        if m:
            out.append((num(m.group(1)), num(m.group(2))))
    return out


def profile_range(content_type: str):
    text = PROFILE.read_text(encoding="utf-8")
    for line in text.splitlines():
        if not line.strip().startswith("|"):
            continue
        cells = [c.strip() for c in line.strip().strip("|").split("|")]
        if len(cells) >= 3 and cells[0].lower() == content_type.lower():
            m = RANGE_RE.search(cells[2])
            if m:
                return num(m.group(1)), num(m.group(2))
    return None


text = TEMPLATES.read_text(encoding="utf-8")
# Split into per-template blocks on the "## Template N:" headings.
blocks = {}
cur = None
for line in text.splitlines():
    m = re.match(r"^##\s+(Template\s+\d+:.*)$", line.strip())
    if m:
        cur = m.group(1).strip()
        blocks[cur] = []
    elif cur:
        blocks[cur].append(line)
blocks = {k: "\n".join(v) for k, v in blocks.items()}

check("every template in the map was found in content-templates.md",
      set(TEMPLATE_TO_TYPE) <= set(blocks),
      f"missing: {sorted(set(TEMPLATE_TO_TYPE) - set(blocks))}")

for tname, ctype in TEMPLATE_TO_TYPE.items():
    block = blocks.get(tname)
    if block is None:
        continue
    secs = sections_of(block)
    rng = profile_range(ctype)

    check(f"{ctype}: has a row-level word budget",
          len(secs) >= 4, f"parsed only {len(secs)} section ranges")
    check(f"{ctype}: the profile's Content Standards table has a row",
          rng is not None,
          "check_draft.py skips the word count entirely without one")
    if not secs or not rng:
        continue

    lo_sum = sum(s[0] for s in secs)
    hi_sum = sum(s[1] for s in secs)
    floor, ceiling = rng

    # The one that was actually broken.
    check(f"{ctype}: minimums ({lo_sum}) fit under the {floor}-word floor",
          lo_sum <= floor,
          f"{len(secs)} sections at their minimum come to {lo_sum}, which is "
          f"{lo_sum - floor} words past the floor and {max(0, lo_sum - ceiling)} "
          f"past the {ceiling} ceiling. Either trim the section minimums or the "
          f"contract cannot be satisfied.")

    check(f"{ctype}: maximums ({hi_sum}) reach the {ceiling}-word ceiling",
          hi_sum >= ceiling,
          f"every section at its maximum only reaches {hi_sum}, so this page "
          f"type cannot use its own allowance")

    # The documented figure must match the table it sits under, or the prose
    # goes stale the first time a range is edited.
    m = BUDGET_RE.search(block)
    check(f"{ctype}: states its budget", m is not None,
          "add a '**Budget: N at every minimum**' line under the table")
    if m:
        check(f"{ctype}: the stated budget ({m.group(1)}) matches the table ({lo_sum})",
              num(m.group(1)) == lo_sum,
              "the stated figure has drifted from the section ranges above it")

# The two blocks that must not be counted are declared as such, so a reader of
# the table does not have to infer it from the checker's behaviour.
for tname in TEMPLATE_TO_TYPE:
    block = blocks.get(tname, "")
    if "Schema Recommendations" in block:
        row = next((l for l in block.splitlines()
                    if "Schema Recommendations" in l and l.strip().startswith("|")), "")
        check(f"{tname}: the schema row is marked as not counted",
              "not counted" in row, f"row reads: {row.strip()[:90]}")

print()
print(f"Passed: {passed}  Failed: {failed}")
sys.exit(1 if failed else 0)
