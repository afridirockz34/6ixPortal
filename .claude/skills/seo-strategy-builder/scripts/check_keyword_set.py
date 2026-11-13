#!/usr/bin/env python3
"""
check_keyword_set.py — is this keyword list a plan, or a pile?

A keyword set can have every number right and still be unusable: no clusters,
so nobody can see the site in it; no target pages, so nobody knows what to
build; the same angle on every blog topic, so a hundred rows say one thing.
Those failures are mechanical and this catches them.

    python check_keyword_set.py keywords.csv --kind website
    python check_keyword_set.py topics.csv   --kind topics
    python check_keyword_set.py gmb.csv      --kind gmb

Input is CSV or JSON. A `Cluster` column names the group each row belongs to;
alternatively, the CSV may use the spreadsheet convention of a bare cluster
name on its own line followed by a header row, which is how these sets are
usually pasted out of a sheet — both are understood.

What it cannot do is tell you the keywords are the right keywords. Read them.
The contract this enforces is in references/keyword-strategy.md.
"""

import argparse
import csv
import io
import json
import re
import sys
from collections import Counter, defaultdict
from pathlib import Path

INTENTS = {"commercial", "transactional", "informational", "navigational",
           "info/comm", "info/trans"}
DIFFICULTY = {"high", "med", "low"}
PRIORITY = {"1", "2", "3"}
LOCAL_PRIORITY = {"critical", "high", "med"}
FUNNEL = {"top", "middle", "bottom"}
ANGLES = {"emergency scenario", "beginner's guide", "problem-solution",
          "comparison", "myth-busting", "case study", "cost-focused",
          "checklist", "local spotlight", "trend analysis"}
# The word in each angle that a cluster would be named after if the cluster
# exists *for* that angle — see the cap below.
ANGLE_KEY = {"myth-busting": "myth", "case study": "case stud",
             "comparison": "compar", "local spotlight": "local",
             "trend analysis": "trend", "cost-focused": "cost",
             "checklist": "checklist", "emergency scenario": "emergency"}
TARGET_PAGES = {"homepage", "service page", "location page", "blog post",
                "resource guide", "bio / about page", "faq page"}

SPEC = {
    "website": {"cols": ["Keyword", "Intent", "Est. MSV", "Difficulty",
                         "Priority", "Target Page"], "ideal": 100},
    "topics":  {"cols": ["Blog Title", "Target Keyword", "Funnel Stage",
                         "Angle", "Priority"], "ideal": 100},
    "gmb":     {"cols": ["Keyword", "Est. MSV", "Local Priority",
                         "GMB Placement"], "ideal": 40},
}

problems, warnings, notes = [], [], []


def fail(msg):
    problems.append(msg)


def warn(msg):
    warnings.append(msg)


def norm(s):
    return re.sub(r"\s+", " ", str(s or "")).strip()


def read_rows(path):
    """
    CSV or JSON to a list of dicts, carrying a Cluster on every row.

    Handles the shape these sets arrive in from a spreadsheet: a line holding
    only a cluster name, then a header row, then that cluster's rows, repeated.
    """
    raw = Path(path).read_text(encoding="utf-8-sig")
    if raw.lstrip().startswith(("[", "{")):
        data = json.loads(raw)
        if isinstance(data, dict):
            # {"Cluster name": [ {...}, ... ], ...}
            out = []
            for cluster, rows in data.items():
                for r in rows:
                    r = dict(r)
                    r.setdefault("Cluster", cluster)
                    out.append(r)
            return out
        return [dict(r) for r in data]

    rows, header, cluster = [], None, ""
    for cells in csv.reader(io.StringIO(raw)):
        cells = [norm(c) for c in cells]
        if not any(cells):
            continue
        filled = [c for c in cells if c]
        # A line with one value and no header yet, or one value that is not a
        # number, is a cluster heading.
        if len(filled) == 1 and not filled[0].replace(".", "").isdigit():
            if header and filled[0].lower() in {h.lower() for h in header}:
                continue
            cluster = filled[0]
            continue
        low = [c.lower() for c in cells]
        if "keyword" in low or "blog title" in low or "target keyword" in low:
            if not any(ch.isdigit() for ch in "".join(cells)):
                header = cells
                continue
        if header is None:
            continue
        row = {header[i]: cells[i] for i in range(min(len(header), len(cells)))}
        row.setdefault("Cluster", cluster)
        if not row.get("Cluster"):
            row["Cluster"] = cluster
        rows.append(row)
    return rows


def get(row, *names):
    for n in names:
        for k, v in row.items():
            if norm(k).lower() == n.lower():
                return norm(v)
    return ""


def check_common(rows, kind):
    spec = SPEC[kind]
    ideal = spec["ideal"]

    if not rows:
        fail("No data rows found. Is this the right file?")
        return

    n = len(rows)
    if n < ideal * 0.4:
        fail(f"{n} rows against a target of about {ideal}. That is not a short "
             "list, it is an unfinished one.")
    elif n < ideal * 0.8:
        notes.append(f"{n} rows against about {ideal}. Fine if the market really "
                     "is that size — say so in the document, so nobody reads it "
                     "as an oversight.")

    # Every column present on every row.
    for i, r in enumerate(rows, 1):
        missing = [c for c in spec["cols"] if not get(r, c)]
        if missing:
            fail(f"Row {i} ({get(r, 'Keyword', 'Blog Title') or '?'}) is missing: "
                 + ", ".join(missing))
            if len(problems) > 40:
                fail("… stopping; fix these first.")
                return

    # Clusters.
    clusters = [get(r, "Cluster") for r in rows]
    if not all(clusters):
        fail(f"{sum(1 for c in clusters if not c)} rows have no cluster. A keyword "
             "with no cluster is a leftover, not research.")
        return
    counts = Counter(clusters)
    if len(counts) < 3:
        fail(f"Only {len(counts)} cluster(s). The point of clustering is that a "
             "reader can see the site in the list; three groups cannot do that.")
    biggest = max(counts.values())
    smallest = min(counts.values())
    if biggest > smallest * 4 and biggest >= 8:
        warn(f"Clusters are very uneven: '{counts.most_common(1)[0][0]}' has "
             f"{biggest} and the smallest has {smallest}. Usually means one "
             "cluster is really two, or a thin one is padding.")

    # Duplicates.
    key = "Blog Title" if kind == "topics" else "Keyword"
    seen = defaultdict(list)
    for i, r in enumerate(rows, 1):
        v = get(r, key).lower()
        if v:
            seen[v].append(i)
    dupes = {k: v for k, v in seen.items() if len(v) > 1}
    for k, where in list(dupes.items())[:8]:
        fail(f"'{k}' appears {len(where)} times (rows {', '.join(map(str, where))}).")

    if kind == "topics":
        tk = defaultdict(list)
        for i, r in enumerate(rows, 1):
            v = get(r, "Target Keyword").lower()
            if v:
                tk[v].append(i)
        for k, where in [(k, v) for k, v in tk.items() if len(v) > 1][:5]:
            warn(f"Target keyword '{k}' is used by {len(where)} topics "
                 f"(rows {', '.join(map(str, where))}). Two posts competing for "
                 "one term is cannibalisation by design.")


def check_vocab(rows, kind):
    def bad(col, allowed, label):
        wrong = Counter()
        for r in rows:
            v = get(r, col).lower()
            if v and v not in allowed:
                wrong[get(r, col)] += 1
        for v, c in wrong.most_common(6):
            fail(f"{label} '{v}' ({c}×) is not one of: "
                 + ", ".join(sorted(allowed)))

    if kind == "website":
        bad("Intent", INTENTS, "Intent")
        bad("Difficulty", DIFFICULTY, "Difficulty")
        bad("Priority", PRIORITY, "Priority")
        # The requirement is that a keyword HAS a destination, not that the
        # destination comes from a fixed list — every client has page types of
        # their own ("Recent Successes Page", "Multilingual Page"), and a
        # keyword can legitimately target either of two ("Homepage / Service
        # Page"). So accept anything that names a page, and only object to
        # something that plainly is not one.
        for i, r in enumerate(rows, 1):
            tp = get(r, "Target Page")
            if not tp or tp.startswith(("http", "/")):
                continue
            parts = [x.strip().lower() for x in re.split(r"[/|]|—|--", tp) if x.strip()]
            if any(x in TARGET_PAGES or re.search(r"\b(page|guide|post|blog|service|resource|homepage|url)\b", x)
                   for x in parts):
                continue
            warn(f"Row {i}: target page '{tp}' does not name a page. Every keyword "
                 "needs somewhere to land.")
        p1 = sum(1 for r in rows if get(r, "Priority") == "1")
        if rows and p1 > len(rows) * 0.75:
            warn(f"{p1} of {len(rows)} keywords are Priority 1. If nearly "
                 "everything is first, nothing is — the first 90 days cannot "
                 "hold it.")
        if rows and p1 == 0:
            fail("No Priority 1 keywords. Nothing is scheduled to be built first.")

    if kind == "topics":
        bad("Funnel Stage", FUNNEL, "Funnel stage")
        bad("Angle", ANGLES, "Angle")
        bad("Priority", PRIORITY, "Priority")
        # One angle dominating a cluster usually means the cluster is thin and
        # the extra rows are padding. The exception is a cluster that exists
        # *for* that angle — a "Myth-Busting" cluster is supposed to be
        # myth-busting all the way down, and flagging it would be noise.
        per = defaultdict(Counter)
        for r in rows:
            per[get(r, "Cluster")][get(r, "Angle").lower()] += 1
        for cluster, angles in per.items():
            size = sum(angles.values())
            for angle, c in angles.items():
                if not angle or c < 4:
                    continue
                key = ANGLE_KEY.get(angle, angle.split()[0])
                if key and key in cluster.lower():
                    continue                       # the cluster is that angle
                if c > 5 or (size and c > size * 0.6):
                    fail(f"Cluster '{cluster}' uses the angle '{angle}' {c} times "
                         f"out of {size}. The cluster is thin and the rest is "
                         "padding — cut it and say the area supported fewer.")
                else:
                    warn(f"Cluster '{cluster}' leans on '{angle}' ({c} of {size}). "
                         "Worth a look before it ships.")
        allangles = Counter(get(r, "Angle").lower() for r in rows if get(r, "Angle"))
        if allangles and len(allangles) < 4:
            warn(f"Only {len(allangles)} distinct angles across the whole set. "
                 "A hundred topics written from three angles is one article "
                 "written a hundred times.")

    if kind == "gmb":
        bad("Local Priority", LOCAL_PRIORITY, "Local priority")
        near = sum(1 for r in rows if "near me" in get(r, "Keyword").lower())
        if near == 0:
            warn("No 'near me' keywords. They are the highest-intent local "
                 "queries there are, and the profile is the only thing that can "
                 "answer them.")

    # Volume must be a number or an explicit gap.
    for i, r in enumerate(rows, 1):
        v = get(r, "Est. MSV", "Est.MSV", "MSV")
        if not v:
            continue
        cleaned = v.replace(",", "").replace(".0", "")
        if cleaned in {"—", "-", "n/a", "N/A"}:
            continue
        if not cleaned.isdigit():
            warn(f"Row {i}: volume '{v}' is not a number or an explicit gap. "
                 "An estimate dressed as a measurement is worse than a blank.")


def main():
    ap = argparse.ArgumentParser(description=__doc__,
                                 formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("path", nargs="?", help="CSV or JSON keyword/topic set.")
    ap.add_argument("--kind", choices=sorted(SPEC), default="website")
    args = ap.parse_args()
    if not args.path:
        print(__doc__.strip())
        return 0

    p = Path(args.path)
    if not p.exists():
        print(f"No such file: {p}", file=sys.stderr)
        return 2

    rows = read_rows(p)
    print(f"Checking {p.name} as a '{args.kind}' set — {len(rows)} rows, "
          f"{len({r.get('Cluster', '') for r in rows})} clusters\n")

    check_common(rows, args.kind)
    if rows:
        check_vocab(rows, args.kind)

    for n in notes:
        print(f"NOTE  {n}")
    for w in warnings:
        print(f"WARN  {w}")
    for pr in problems:
        print(f"FAIL  {pr}")

    print()
    if problems:
        print(f"{len(problems)} problem(s). This set is not ready to go into the "
              "strategy document.")
        return 1
    if warnings:
        print("No hard failures. Read the warnings — they are usually right.")
    else:
        print("The set is well-formed. That is not the same as the keywords being "
              "the right keywords; read them.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
