# Assembling the deliverable

The strategy document is the thing the client actually judges us on. Two
structural mistakes make an accurate, well-researched document unreadable, and
both have shipped:

1. **Indexing it by our phase numbers.** A reader looking for how long a
   service page should be should find a section called *Content Length
   Standards*. In a document organised as "Phases 15 & 16 — Writing prompt and
   content templates", they have to already know our internal build order to
   find anything. Phase numbers are *our* sequence for producing the work. They
   are not a table of contents.
2. **Writing it as a diff against somebody else's document.** A section that
   reads "Proximity's four templates are kept, with these changes:" followed by
   a `Change` column is unreadable to anyone without the predecessor's document
   open beside it. The client has one document — ours — and every sentence has
   to make sense from inside it.

Both are easy to commit, because both reflect how the work genuinely happened.
Neither is how it gets read.

---

## The rule

**Name every section after the asset it specifies. Write every section as
though no previous vendor existed.**

Where we are replacing a predecessor's plan, all of the comparison goes into
one appendix, titled for what it is — *Appendix: decisions on the previous
plan* — and the body of the document simply states what we are doing. The
appendix is where "we kept this, we changed that, here is why" belongs, and
where it is genuinely valuable. In the body it is noise the client has to
decode.

---

## The client-facing table of contents

Every title below is the section name. None of them contains a phase number.

**Front matter**

| Section | What it is |
|---|---|
| Summary and decisions needed | One page. What we found, what we are doing, and the three to five decisions we need from the client, each with a recommendation. Written last |
| How to read the figures | Which numbers are measured (their GA4, Search Console) and which are estimated (Ahrefs), with the period and pull date for each. Two sentences per source |

**Part 1 — Where the business stands**

| Section | Built from |
|---|---|
| The business, in its own words | `phase1-foundation.md` |
| Services, ranked by what the client says is profitable | `phase1-foundation.md` |
| Who we are writing for | `phase1-foundation.md` |
| Where the business serves | `phase1-foundation.md` |
| What the numbers show today | `phase1-foundation.md` baseline + `performance-tracking.md` |
| Who is ahead, and on what | `phase2-competitors-and-keywords.md` |
| Where the openings are | `phase2-competitors-and-keywords.md` content opportunity matrix |

**Part 2 — How it will sound**

| Section | Built from |
|---|---|
| Brand voice pillars | `seo-content-production/references/house-standards.md` §1 |
| Core messages | `house-standards.md` §2 |
| Terminology — say this, not that | `house-standards.md` §3 |
| Voice examples: one good, one bad | `house-standards.md` §4 |
| Words and phrases to avoid entirely | `house-standards.md` §5 |
| Claims policy | `house-standards.md` §12 |

**Part 3 — What gets built**

| Section | Built from |
|---|---|
| The 100 website keywords, by cluster | `keyword-strategy.md` §1 |
| The 40 Google Business Profile keywords, and where each one goes | `keyword-strategy.md` §2, `local-and-gmb.md` |
| One owner for every search | `keyword-strategy.md` — the keyword map |
| The page plan | `phase9-10-architecture-linking.md` |
| Internal linking rules | `house-standards.md` §10 |
| The 100 blog topics, by cluster | `keyword-strategy.md` §3 |
| The publishing schedule | `phase13-14-content-calendar.md` |

**Part 4 — How it gets written**

| Section | Built from |
|---|---|
| Content length standards | `house-standards.md` §6 |
| Critical keyword placements | `house-standards.md` §7 |
| Meta element standards | `house-standards.md` §8 |
| Grammar and mechanics | `house-standards.md` §9 |
| Readability standards | `house-standards.md` §11 |
| The section contract for every page type | `seo-content-production/references/content-templates.md` |
| The writing brief | the client profile, reproduced in full |

**Part 5 — The technical and local work**

| Section | Built from |
|---|---|
| Google Business Profile strategy | `phase5-6-strategy-and-gmb.md`, `local-and-gmb.md` |
| The one NAP string, used everywhere | `local-and-gmb.md` |
| Citations, in tiers, with an order | `local-and-gmb.md` |
| Review strategy | `local-and-gmb.md` |
| Schema plan, page type by page type | `phase11-12-geo-schema.md` |
| Appearing in AI answers | `ai-visibility.md`, `phase11-12-geo-schema.md` |
| Links and off-page work | `off-page.md` |

**Part 6 — How we will know it worked**

| Section | Built from |
|---|---|
| Objectives, with baselines and timelines | `phase5-6-strategy-and-gmb.md` |
| The tools, and what each one is for | `phase17-18-19-tools-tracking-reporting.md` |
| Rank tracking setup | `phase17-18-19-tools-tracking-reporting.md` |
| What the monthly report contains | `phase17-18-19-tools-tracking-reporting.md` |
| The quality gate every page passes | `seo-content-production` — the three conditions |
| Who approves what | the client profile's approvers |

**Appendices**

| Section | What it is |
|---|---|
| A — Full keyword list with scores | The working sheet, including the computed Opportunity Scores behind the Priority bands |
| B — Decisions on the previous plan | *Only where a predecessor exists.* Every page or recommendation of theirs, and what we are doing with it |
| C — Open questions for the client | Everything still marked `[NEEDS CLIENT INPUT]`, as a list they can answer in one sitting |
| D — Sources and pull dates | Every figure's source and the date it was pulled |

### The objectives table

Part 6's first section is a table, not prose, and it is the section the client
returns to:

| # | Objective | Baseline | Target | By |
|---|---|---|---|---|
| 1 | Organic sessions | 2,800/month (GA4, Oct 2026) | 5,000/month | 6 months |
| 2 | Keywords in positions 1–3 | 107 (Ahrefs, 9 Nov) | 200 | 6 months |

Every row carries its baseline **with the source and date**, because a target
with no baseline cannot be checked and a baseline with no date cannot be
reproduced. These are also the rows that go to `save_strategy_projections`, so
the portal can check them later — a target that lives only in the PDF is a
target nobody will ever be held to.

---

## What to leave out

- **Phase numbers**, anywhere a client can see them. Keep them in our working
  notes and in this skill; strip them from the deliverable.
- **Our process.** How we built it is not a section. The client bought the
  conclusions.
- **A `Change` column**, unless the thing being changed is on the client's own
  site. Changes relative to a predecessor's document live in Appendix B.
- **A section with no owner and no date.** Every recommendation names who does
  it and when.

---

## If only part of it is requested

Most requests are not the whole document — a keyword refresh, a new content
calendar, a schema plan. Build only what was asked for, use the section names
above for whatever you build, and say plainly at the top which parts of the
full structure this covers and which it does not. Do not pad a narrow request
into a full document.

## Retrospectives are appended, never merged

`phase20-results-retrospective.md` does not slot into the structure above — it
cannot exist until content has been live long enough to have results. Each one
is its own dated section appended to the document (or its own running file,
once re-sending the whole document gets unwieldy): *"Q2 2027 results"*. Never
a rewrite of the original. The value is in being able to see what was predicted
next to what happened, and a merged revision destroys exactly that.

## Before calling it done

```bash
python scripts/check_strategy_doc.py <assembled_document>
python scripts/check_keyword_set.py <keywords.csv> --kind website
```

The first checks unresolved placeholders, dead-advice language from
`fact-check-registry.md`, and the readability failures in `plain-language.md`.
The second checks the keyword tables' shape. Neither replaces a human read for
voice, accuracy and — for a regulated client — compliance sign-off.

The last check is not mechanical: **hand the document to somebody who was not
in the research and ask them what we are going to do.** If they cannot answer,
the structure is still wrong, however many checks passed.
