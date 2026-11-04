#!/usr/bin/env python3
"""
check_implementation_log.py — mechanical QC for a seo-client-pipeline
implementation log (see references/implementation.md for the expected
table format: # | Page/URL | Field | Before | After | Status | Date applied
| Confirmed by).

Two checks:

1. HARD FAIL — a row marked "applied" with no date. An applied-but-undated
   change can't be corrected after the fact (the live site has already
   moved on), and it breaks the Google Update Correlation discipline
   every monitoring check and Phase 20 retrospective depends on: there is
   no way to check a ranking move against "what we changed and when" if
   "when" was never recorded.

2. REVIEW — a row with an empty or unrecognized Status. Not a hard
   failure (the row may be a planned-but-not-yet-applied change-set entry,
   which is fine), but worth a human glance to confirm it isn't actually
   an applied change that just didn't get its status filled in.

This script does not validate that changes were CORRECTLY applied, only
that the log's own bookkeeping is internally consistent — it has no way
to check the row against the live site.

Usage:
    python check_implementation_log.py <implementation_log.md>

Exit code 1 on any hard-fail row (applied + no date). 0 otherwise.
"""

import re
import sys
from pathlib import Path

VALID_STATUSES = {"applied", "failed", "partial", "planned", "pending"}

# Matches a markdown table row: | # | Page | Field | Before | After | Status | Date | Confirmed by |
# Tolerant of extra whitespace; doesn't require an exact column count match
# beyond having at least 6 cells, since real tables sometimes get a column
# added or reordered by hand.
ROW_PATTERN = re.compile(r"^\s*\|(.+)\|\s*$", re.MULTILINE)
SEPARATOR_ROW_PATTERN = re.compile(r"^[\s|:-]+$")

DATE_HINT_PATTERN = re.compile(
    r"\b(\d{4}-\d{2}-\d{2}|\d{1,2}/\d{1,2}/\d{2,4}|"
    r"jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)",
    re.IGNORECASE,
)


def load_text(path: str) -> str:
    p = Path(path)
    if not p.exists():
        print(f"ERROR: file not found: {path}")
        sys.exit(2)
    return p.read_text(encoding="utf-8")


def parse_rows(text: str):
    """Yield (cells, raw_line) for each plausible table data row."""
    for line in ROW_PATTERN.findall(text):
        if SEPARATOR_ROW_PATTERN.match(line):
            continue
        cells = [c.strip() for c in line.split("|")]
        # Skip the header row itself (first cell literally "#" or "No.")
        if cells and cells[0].lower() in {"#", "no.", "no", "row"}:
            continue
        if len(cells) < 6:
            continue
        yield cells


def classify(cells):
    """Best-effort pull of a status-like cell and a date-like cell from a row.

    Column order isn't rigidly enforced — this looks for a cell matching a
    known status word, and separately for a cell that looks like a date,
    rather than assuming fixed positions, since real logs drift from the
    suggested template over time.
    """
    status = None
    has_date = False
    for cell in cells:
        lower = cell.lower().strip()
        if lower in VALID_STATUSES and status is None:
            status = lower
        if DATE_HINT_PATTERN.search(cell):
            has_date = True
    return status, has_date


def main():
    if len(sys.argv) != 2:
        print(__doc__)
        sys.exit(2)

    path = sys.argv[1]
    text = load_text(path)
    print(f"Checking {path}\n")

    rows = list(parse_rows(text))
    if not rows:
        print("[INFO] No table rows detected — nothing to check. "
              "Confirm this is actually the implementation log and not an "
              "empty template.")
        sys.exit(0)

    undated_applied = []
    unclear_status = []

    for i, cells in enumerate(rows, start=1):
        status, has_date = classify(cells)
        row_label = cells[0] if cells[0] else f"row {i}"
        if status == "applied" and not has_date:
            undated_applied.append(row_label)
        elif status is None:
            unclear_status.append(row_label)

    failures = 0

    if undated_applied:
        failures += 1
        print(f"[FAIL] {len(undated_applied)} row(s) marked applied with no detectable date:")
        for r in undated_applied[:20]:
            print(f"       row {r}")
        if len(undated_applied) > 20:
            print(f"       ... and {len(undated_applied) - 20} more")
        print("       An applied, undated change can't be checked against Google Update")
        print("       Correlation later — fix these before treating the log as current.")
        print()
    else:
        print("[OK] Every row marked 'applied' has a detectable date")

    if unclear_status:
        print(f"\n[REVIEW] {len(unclear_status)} row(s) with no recognized status "
              f"({sorted(VALID_STATUSES)}):")
        for r in unclear_status[:20]:
            print(f"       row {r}")
        if len(unclear_status) > 20:
            print(f"       ... and {len(unclear_status) - 20} more")
        print("       Confirm these aren't applied changes missing a status label.")
    else:
        print("\n[OK] Every row has a recognized status")

    print()
    if failures:
        print("Resolve the undated-applied rows above before filing this log or running "
              "a monitoring check against it.")
        sys.exit(1)
    else:
        print("No hard failures. Still read through any REVIEW rows above — this script "
              "only catches what's mechanically checkable from the table itself.")
        sys.exit(0)


if __name__ == "__main__":
    main()
