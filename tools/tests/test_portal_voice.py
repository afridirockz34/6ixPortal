#!/usr/bin/env python3
"""
The portal reads in the same voice as the documents.

A client meets 6ix in three places: the onboarding and checkout flow, the
dashboard they live in afterwards, and the documents we hand them. Those were
written at different times by different hands, and only the documents had a
standard. So the strategy said "we do not use the word dominate" while the
first screen of checkout said "Dominate local search".

This runs the client-facing half of
seo-strategy-builder/references/plain-language.md over the copy in the
templates. Three rules carry over to an interface:

  * Rule 1 — the page is for the client. It does not explain or defend itself.
  * Rule 3's cousin — no banned marketing words, from the house blocklist.
  * Sentence shape — no clefts, no fronted subordinate clauses.

Rule 2 does NOT carry over. "Your Business Name" on a form label is correct,
and driving "you" out of an interface would make it worse, not better. A
density that is right for a 10,000-word document is wrong for a button.

    python3 tools/tests/test_portal_voice.py
"""
import html
import re
import sys
from pathlib import Path

REPO = Path(__file__).resolve().parent.parent.parent
TEMPLATES = [
    REPO / "portal/templates/onboarding.php",
    REPO / "portal/templates/customer-dashboard.php",
    REPO / "portal/questionnaire-page.php",
]

passed = failed = 0


def check(name, ok, detail=""):
    global passed, failed
    if ok:
        passed += 1
        print(f"PASS  {name}")
    else:
        failed += 1
        print(f"FAIL  {name}" + (f"\n        {detail}" if detail else ""))


def client_copy(path: Path) -> list:
    """The strings a customer actually reads.

    Styles, scripts, comments and anything that still looks like code are
    dropped. A check that chases our own notes to ourselves is a check that
    gets switched off.
    """
    t = path.read_text(encoding="utf-8", errors="replace")
    t = re.sub(r"<style\b.*?</style>", " ", t, flags=re.S | re.I)
    t = re.sub(r"<script\b.*?</script>", " ", t, flags=re.S | re.I)
    t = re.sub(r"/\*.*?\*/", " ", t, flags=re.S)
    t = re.sub(r"^[ \t]*(?://|\*|#)[^\n]*$", " ", t, flags=re.M)

    out = []
    for seg in re.split(r"<[^>]*>", t):
        seg = re.sub(r"<\?(?:php|=).*?\?>", " ", seg, flags=re.S)
        seg = " ".join(html.unescape(seg).split())
        if len(seg.split()) >= 5 and re.search(r"[a-z]{3}", seg):
            out.append(seg)
    for m in re.finditer(r"""(?<![\\\w])(['"])((?:[^'"\\\n]|\\.){25,400}?)\1""", t):
        s = " ".join(html.unescape(m.group(2)).split())
        if (len(s.split()) >= 6 and re.search(r"[a-z]{3}\s+[a-z]{3}", s)
                and s[0].isupper()):
            out.append(s)
    return [s for s in out if not re.search(r"[;{}$]|->|\barray\(|::", s)]


ALL = {p.name: "\n\n".join(client_copy(p)) for p in TEMPLATES}
EVERYTHING = "\n\n".join(ALL.values())

# ── The house blocklist, in the interface ────────────────────────────────
# These live in seo-content-production/references/house-standards.md and in
# the growth-plan prompt. They were never applied to the portal, which is the
# first place a prospect meets the writing.
print("-- Banned marketing words --")
BANNED = [
    ("dominate", "on the house blocklist, and we told the model never to use it"),
    ("unlock", "on the house blocklist"),
    ("supercharge", "on the house blocklist"),
    ("game-chang", "on the house blocklist"),
    ("leverage", "say what we actually do with it"),
    ("best-in-class", "could describe any firm anywhere"),
    ("top-notch", "could describe any firm anywhere"),
    ("rest assured", "empty"),
    ("don't hesitate to", "buries the call to action"),
    ("cutting-edge", "empty"),
    ("world-class", "empty"),
    ("seamless", "empty"),
]
for word, why in BANNED:
    hits = [(n, s) for n, body in ALL.items() for s in body.split("\n\n")
            if re.search(r"\b" + re.escape(word), s, re.I)]
    check(f'"{word}" does not appear in client-facing copy — {why}',
          not hits,
          "; ".join(f"{n}: {s[:90]}" for n, s in hits[:3]))

# ── Rule 1: the page does not explain or defend itself ───────────────────
print("\n-- The page is for the client, not for its reviewer --")
META = [
    r"\bworth (?:your knowing|noting|being plain about)\b",
    r"\bthe (?:estimate|table|figure) above exists\b",
    r"\bone thing (?:we checked|to be straight about)\b",
    r"\bwe would rather\b",
    r"\bread (?:the|these|those)\s+[\w ]{0,20}(?:again|together)\b",
]
hits = [(n, s) for n, body in ALL.items() for s in body.split("\n\n")
        for p in META if re.search(p, s, re.I)]
check("no copy explains why the page is built the way it is",
      not hits, "; ".join(f"{n}: {s[:100]}" for n, s in hits[:3]))

# ── Sentence shape ───────────────────────────────────────────────────────
print("\n-- Sentence shape --")
SHAPES = [
    (r"(?:^|(?<=[.!?]\s))(?:Where|When|Because|If|Once|While|Though|Although|So)\s+[^,.]{10,70},",
     "fronted subordinate clause — start the sentence with its subject"),
    (r"\b(?:The|That|What|It)\s+\w+(?:\s+\w+)?\s+is\s+(?:not|what|how|that)\b",
     "cleft — the subject arrives late"),
]
for pattern, why in SHAPES:
    hits = [(n, m.group(0)) for n, body in ALL.items()
            for m in re.finditer(pattern, body, re.M)]
    check(f"no {why.split(' — ')[0]}",
          not hits, "; ".join(f"{n}: {s[:80]}" for n, s in hits[:4]))

# ── One service, one description, in both places ─────────────────────────
# The dashboard and the checkout flow each carry their own copy for the same
# four services. They drifted: checkout said "Dominate local search" while the
# dashboard said something else entirely for the same product.
print("\n-- The same service reads the same in both places --")
for phrase in ("Paid ads that reach people searching for what you sell",
               "Rankings that build month over month",
               "Show up on the map when people nearby search",
               "A site built to turn visitors into enquiries"):
    in_onb = phrase in ALL["onboarding.php"]
    in_dash = phrase in ALL["customer-dashboard.php"]
    check(f'"{phrase[:46]}…" appears in both',
          in_onb and in_dash,
          f"onboarding={in_onb} dashboard={in_dash}")

# ── The sentence that was broken in front of customers ───────────────────
print("\n-- Regressions --")
check("the competitor empty-state is one sentence, not two welded together",
      "for you.ebsite" not in EVERYTHING,
      "a botched edit left half of the replaced sentence on the end of its replacement")

print()
print(f"Passed: {passed}  Failed: {failed}")
sys.exit(1 if failed else 0)
