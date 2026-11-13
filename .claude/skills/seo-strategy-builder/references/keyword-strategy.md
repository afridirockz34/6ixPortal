# The three keyword deliverables, and the shape they come in

Phases 7, 8 and 13 produce three lists: about 100 website keywords, about 40
GMB keywords, and about 100 blog topics. This file says exactly what each one
looks like when it is finished.

The format is not decoration. A keyword list that is a flat sorted dump is
unreadable and unactionable even when every number in it is right — nobody can
see the site in it, nobody can tell what to build first, and the client reads
three pages and gives up. The three tables below are the fix, and they are the
difference between a research exercise and a plan somebody can execute.

**One rule above all the others: every keyword belongs to a named cluster, and
every keyword has a target page.** A keyword with no cluster is not research,
it is a leftover. A keyword with no target page is a keyword nobody will ever
act on.

---

## 1. The website keyword set (~100)

### Shape

Ten named clusters of about ten keywords each. Not a hundred rows sorted by
score — ten groups a person can hold in their head, each of which maps to a
section of the site you are about to build.

```
Core [Service] Terms                    10
[Primary Service] Defence               10
[Second Service]                        10
[Urgent / High-Intent Terms]            10
Location Pages — [Metro] Boroughs       10
Location Pages — [Region] Cities        10
Other Services & Sub-Types              10
Informational / What To Do If…          10
Comparison & Hire Intent                10
Authority, AI Visibility & Long-Tail    10
```

That is the shape, not the content. The cluster names come from the client's
actual services and market, never from this template — a plumber's clusters are
not a law firm's. What carries across is the *pattern*: core terms first, then
each major service, then urgency, then geography, then the long tail, then
informational, then comparison, then authority.

### Columns

| # | Keyword | Intent | Est. MSV | Difficulty | Priority | Target Page |
|---|---|---|---|---|---|---|
| 1 | criminal lawyer toronto | Commercial | 2,400 | High | 1 | Homepage / Service Page |
| 31 | bail hearing lawyer toronto | Transactional | 590 | High | 1 | Service Page — URGENT |
| 71 | what to do if charged with sexual assault ontario | Informational | 390 | Low | 1 | Resource Guide |

Every column, every row. No blanks.

**Intent** — one of: `Commercial`, `Transactional`, `Informational`,
`Navigational`. Where a term genuinely sits across two, write `Info/Comm` or
`Info/Trans` rather than picking one and being wrong. Definitions are in
`keyword-scoring.md`; what matters here is that intent and target page agree —
an informational keyword pointed at a service page is a mismatch that shows up
later as a page nobody converts on.

**Est. MSV** — monthly search volume, exactly as the tool reported it, for the
client's country or region. Never rounded to look tidier, never estimated when
the tool had no figure. A keyword with no volume data gets `—` and a note, not
a guess.

**Difficulty** — `High` / `Med` / `Low`, not a raw number. Map from the tool's
0–100: 0–20 Low, 21–45 Med, 46+ High. Three buckets because the decision this
feeds is three-way (go now / go later / needs authority first), and a reader
who sees "KD 43" has to do that mapping in their head anyway.

**Priority** — `1`, `2` or `3`:

- **1 — build in the first 90 days.** High business value, and either winnable
  now or important enough to start regardless.
- **2 — months 4–9.** Real value, but it waits on authority, or on a Priority 1
  page existing first.
- **3 — opportunistic.** Worth having on the list, not worth planning around.

Priority is a judgement, and it is the one column where the client's own
answers outrank the tool. A 90-volume term for their most profitable service
beats a 2,400-volume term for work they do not want. `seo_profitable_services`
and `seo_lifetime_value` from the questionnaire are what make that call — and
they rank the list, they never get multiplied into a revenue figure.

**Target Page** — one of: `Homepage`, `Service Page`, `Location Page`,
`Blog Post`, `Resource Guide`, `Bio / About Page`, `FAQ Page`, or a named
existing URL. Add a qualifier where it changes the brief: `Service Page —
URGENT` tells the writer this page is read by somebody in trouble at 2am.

### What this list is for

Read down the Target Page column and you have the build order: how many service
pages, how many location pages, which blog posts, what already exists. Phase 9
(architecture) is then a formality rather than a fresh exercise, and Phase 14
(the calendar) is the same list in date order.

If the Target Page column cannot be filled in for a keyword, that keyword does
not belong on the list.

### When the market does not support 100

Say so and ship fewer. A small local business may genuinely have 40 keywords
worth pursuing, and padding to 100 produces rows nobody will ever build a page
for — which is worse than a short list, because it hides the real ones. Write
in the document how many the market supported and why.

---

## 2. The GMB keyword set (~40)

Two tables, because a GMB keyword is only useful once you know *where it goes*.

### (a) Placement by profile element

| GMB Element | Keyword Focus | Implementation Notes |
|---|---|---|
| Business Description (750 chars) | Primary service + city, top two secondary services, any specialist credential | Woven in naturally. Refresh every 6 months. |
| Services List | Every practice-area or service term | One entry per service, so each supports a cluster. |
| Google Posts — weekly slots | Rotate: results / know-your-rights / credentials | Each post naturally carries a service term and the city. |
| Q&A | "near me" and urgent queries | Pre-populate, owner-answered, so every high-intent query has an answer. |
| Review Responses | Service type + location | "Thank you for trusting us with your … case in …" |
| Service Area | Every borough and city served | This is what decides which "near me" searches the profile can appear for. |

### (b) The keywords themselves

| # | Keyword | Est. MSV | Local Priority | GMB Placement |
|---|---|---|---|---|
| 3 | criminal lawyer near me | 1,900 | Critical | Services list, Q&A, Review responses |

`Local Priority` is `Critical` / `High` / `Med`. **"Near me" terms belong in
this list and not the website list** — they are answered by the profile and the
service area, not by a page, and putting them in the website set produces pages
that cannot rank for them.

---

## 3. The blog topic set (~100)

### Columns

| # | Blog Title | Target Keyword | Funnel Stage | Angle | Priority |
|---|---|---|---|---|---|
| 1 | What to Do If You Are Charged with Sexual Assault in Ontario | what to do if charged with sexual assault ontario | Top | Emergency Scenario | 1 |
| 7 | The Difference Between Sexual Assault, Sexual Interference and Sexual Exploitation | sexual interference vs sexual assault canada | Top | Comparison | 1 |

Clustered the same way — ten named clusters of ten, by subject rather than by
funnel stage, so the set reads as coverage of a topic area.

**Blog Title** is the real headline, written out. Not "post about bail" —
the title the page will actually carry. If it is not good enough to publish, it
is not good enough to put in the plan.

**Funnel Stage** — `Top` (they have just discovered the problem), `Middle`
(they are working out their options), `Bottom` (they are choosing who to hire).

### Angle — the controlled vocabulary

This column is the one that stops 100 topics turning into 100 restatements of
the same article, which is the usual failure and the reason a long topic list
reads as filler. Pick from:

| Angle | The reader's situation | Looks like |
|---|---|---|
| `Emergency Scenario` | Something just happened | "What to Do If You Are Charged with…" |
| `Beginner's Guide` | They know nothing yet | "How Does a Bail Hearing Work in Ontario?" |
| `Problem-Solution` | They have a specific obstacle | "How Domestic Assault Charges Get Dropped" |
| `Comparison` | Two things they cannot tell apart | "Preliminary Hearing vs. Trial" |
| `Myth-Busting` | They believe something false | "Why You Should Never Speak to Police First" |
| `Case Study` | They want proof it can be done | "How These Cases Are Won and Lost" |
| `Cost-Focused` | They want to know what it takes | "How Much Does a Criminal Lawyer Cost?" |
| `Checklist` | They want to do something in order | "What to Bring to Your First Meeting" |
| `Local Spotlight` | They want the local specifics | "How the Toronto Courts Handle These Cases" |
| `Trend Analysis` | Something has changed | "What the 2026 Amendments Mean for You" |

**One angle must not dominate a cluster.** Past four of ten it is worth a
second look; past six, or past 60% of the cluster, the cluster is thin and the
rest is padding — cut it and say in the document that this area supported six
topics rather than ten.

The exception is a cluster that exists *for* an angle. A cluster called
"Myth-Busting & Common Misconceptions" is supposed to be myth-busting all the
way down, and so is a "Case Studies" or "Local" cluster. Name the cluster for
the angle and the cap does not apply — the checker understands this.

### Topics must trace to keywords

Every topic's Target Keyword comes from the keyword research, and informational
terms already assigned to a service or location page in the architecture do not
get a second home as a blog post. A topic that traces to no keyword is a topic
somebody made up.

---

## Checking the set before it ships

```bash
python scripts/check_keyword_set.py keywords.csv --kind website
python scripts/check_keyword_set.py topics.csv   --kind topics
python scripts/check_keyword_set.py gmb.csv      --kind gmb
```

It checks what can be checked mechanically: every row has every column, every
keyword has a cluster and a target page, the vocabularies are the ones above,
no keyword appears twice, clusters are roughly even, and no cluster leans on
one angle. It cannot tell you the keywords are the right keywords — that is
what reading them is for.
