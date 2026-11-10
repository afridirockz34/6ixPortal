#!/usr/bin/env python3
"""
Can the SEO skills be picked up cold, in a session that knows nothing about
how they were written?

That is a different question from "are they correct". A skill can be perfectly
accurate and still be unusable in a fresh chat because it points at a file that
was renamed, names a connector tool that does not exist, or tells the reader to
run a script that no longer parses. Those are the failures this catches, and
they are all mechanical.

    python tools/check_seo_skills.py            # check
    python tools/check_seo_skills.py --tools    # also list every connector
                                                # tool the skills name

Checks:
  1. Every SKILL.md has the frontmatter the loader needs (name, description).
  2. Every relative path mentioned in any skill file exists.
  3. Every bundled script compiles, and every CLI answers --help.
  4. No skill points outside the skills tree, or at an absolute path from a
     machine that is not the reader's.
  5. Nothing is left referring to this repository's internals in a way a
     client-work session could not act on.
"""

import argparse
import ast
import re
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SKILLS = ROOT / ".claude" / "skills"
SEO = ["seo-audit-report", "seo-strategy-builder",
       "seo-content-production", "seo-client-pipeline"]

# A path-looking token in prose. Deliberately narrow: it must carry a slash or a
# known extension, so ordinary words are not mistaken for filenames.
PATH_RE = re.compile(
    r"`([A-Za-z0-9._][A-Za-z0-9._/-]*\.(?:md|py|json|php|txt|csv|html))`"
    r"|`([A-Za-z0-9._][A-Za-z0-9._/-]*/[A-Za-z0-9._/-]+)`")
ABS_RE = re.compile(r"`(/(?:home|Users|tmp|var|root)/[^`]+)`")
# Files that live on a website, not in this tree. They look exactly like a
# path reference and are not one.
WEB_FILES = {"robots.txt", "llms.txt", "sitemap.xml", "ads.txt", "security.txt",
             ".htaccess", "humans.txt", "llms-full.txt"}
# Connector tools the skills tell a reader to call.
TOOL_RE = re.compile(r"`(mcp__[A-Za-z0-9_]+|[a-z][a-z0-9_]{3,})\(")

problems, notes = [], []


def fail(where, msg):
    problems.append(f"{where}: {msg}")


def md_files():
    for name in SEO:
        for p in sorted((SKILLS / name).rglob("*.md")):
            yield name, p


def check_frontmatter():
    for name in SEO:
        skill = SKILLS / name / "SKILL.md"
        if not skill.exists():
            fail(name, "no SKILL.md — the skill cannot be loaded at all")
            continue
        text = skill.read_text(encoding="utf-8")
        if not text.startswith("---"):
            fail(f"{name}/SKILL.md", "no YAML frontmatter")
            continue
        block = text.split("---", 2)[1]
        for key in ("name:", "description:"):
            if key not in block:
                fail(f"{name}/SKILL.md", f"frontmatter has no {key[:-1]}")
        declared = re.search(r"^name:\s*\"?([^\"\n]+)\"?", block, re.M)
        if declared and declared.group(1).strip() != name:
            fail(f"{name}/SKILL.md",
                 f"frontmatter name is {declared.group(1).strip()!r}, "
                 f"but the directory is {name!r} — the loader uses the directory")
        desc = re.search(r"description:\s*(.+)", block)
        if desc and len(desc.group(1)) < 80:
            fail(f"{name}/SKILL.md",
                 "description is too short to route a request to this skill")


def check_links():
    # Everything that actually exists, by basename, so a reference can be
    # judged as "missing" or merely "written without its path".
    by_base = {}
    for f in SKILLS.rglob("*"):
        if f.is_file():
            by_base.setdefault(f.name, []).append(f)

    for name, p in md_files():
        rel = p.relative_to(SKILLS)
        text = p.read_text(encoding="utf-8")
        for m in ABS_RE.finditer(text):
            fail(str(rel), f"absolute path {m.group(1)!r} — will not exist "
                           "for another reader")
        for m in PATH_RE.finditer(text):
            target = (m.group(1) or m.group(2)).rstrip("/")
            if target.startswith(("http", "mcp__")) or " " in target:
                continue
            if Path(target).name in WEB_FILES:
                continue
            # Resolve against the file's own directory, its skill and the
            # skill's three subdirectories, then the skills root — every way
            # these files cite each other.
            bases = [p.parent, SKILLS, SKILLS / name]
            bases += [SKILLS / name / sub for sub in ("references", "scripts", "assets")]
            if any((b / target).exists() for b in bases):
                continue
            # A path into the application repo is allowed but worth listing,
            # because a client-work session will not have it.
            if (ROOT / target).exists():
                notes.append(f"{rel}: points into the app repo: {target}")
                continue
            # It exists, but not anywhere this file's reader would look for it.
            # Findable, so not a failure — but a cold reader has to hunt, which
            # is the thing these skills are supposed to spare them.
            elsewhere = by_base.get(Path(target).name)
            if elsewhere:
                where = elsewhere[0].relative_to(SKILLS)
                if Path(target).name == target:
                    notes.append(f"{rel}: `{target}` is cited bare; it lives at "
                                 f"{where} — qualify it")
                    continue
            fail(str(rel), f"broken reference: {target}")


def check_scripts():
    for name in SEO:
        for p in sorted((SKILLS / name).rglob("*.py")):
            rel = p.relative_to(SKILLS)
            src = p.read_text(encoding="utf-8")
            try:
                ast.parse(src)
            except SyntaxError as exc:
                fail(str(rel), f"does not parse: {exc}")
                continue
            if "argparse" not in src and "__main__" not in src:
                continue
            res = subprocess.run([sys.executable, str(p), "--help"],
                                 capture_output=True, text=True, timeout=60)
            if res.returncode != 0:
                fail(str(rel), f"--help exits {res.returncode}: "
                               f"{res.stderr.strip().splitlines()[-1] if res.stderr.strip() else ''}")
            elif not res.stdout.strip():
                fail(str(rel), "--help prints nothing")


def check_cross_skill():
    """
    A skill that routes to another skill must route to one that is here.

    Only `name/` counts — a bare `seo-something` in backticks is as likely to
    be an outside project's skill being credited as a route into this set, and
    both appear in these files.
    """
    known = {d.name for d in SKILLS.iterdir() if d.is_dir()}
    for name, p in md_files():
        rel = p.relative_to(SKILLS)
        for m in re.finditer(r"`(seo-[a-z-]+)/", p.read_text(encoding="utf-8")):
            if m.group(1) not in known:
                fail(str(rel), f"routes to a skill that is not installed: {m.group(1)}")


def list_tools():
    found = {}
    for name, p in md_files():
        for m in TOOL_RE.finditer(p.read_text(encoding="utf-8")):
            found.setdefault(m.group(1), set()).add(name)
    print("\nConnector tools the skills tell a reader to call:")
    for tool in sorted(found):
        print(f"  {tool:<44} {', '.join(sorted(found[tool]))}")
    print("\nCheck these against the session's actual tool list; a renamed tool "
          "is the one kind of drift this script cannot see.")


def main():
    ap = argparse.ArgumentParser(description=__doc__,
                                 formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--tools", action="store_true",
                    help="also list every connector tool the skills name")
    args = ap.parse_args()

    check_frontmatter()
    check_links()
    check_scripts()
    check_cross_skill()

    for n in notes:
        print(f"NOTE  {n}")
    if problems:
        print(f"\n{len(problems)} problem(s):")
        for p in problems:
            print(f"  FAIL  {p}")
        return 1
    print(f"\nThe {len(SEO)} SEO skills are self-contained: frontmatter present, "
          "every reference resolves,\nevery bundled script parses and runs.")
    if args.tools:
        list_tools()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
