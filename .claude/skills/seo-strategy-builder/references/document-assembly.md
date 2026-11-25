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
3. **Letting the audit leak into it.** The strategy and the audit are two
   documents with two jobs. The audit says what is wrong; the strategy says
   what we are going to do about it. A strategy carrying crawl-error counts,
   severity scores, a technical findings table and a list of what the previous
   agency got wrong is not a document the client can forward to anybody — and
   forwarding it is most of what it is for. See *This is a client document*
   below.

All three are easy to commit, because all three reflect how the work genuinely
happened. None of them is how it gets read.

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

## This is a client document

The test: **the client should be able to send this to their partner, their
board or their own marketing person without editing it.** Everything below
fails that test, and every one of them has appeared in a strategy document we
sent.

| Does not belong here | Where it belongs |
|---|---|
| Crawl-error counts, 404 lists, redirect-chain tables | The audit report |
| Severity scores, health scores, "27/100" | The audit report |
| A findings table of everything wrong with the site | The audit report |
| What the previous agency got wrong | Appendix B, if anywhere |
| Our phase numbers, our build order, our tooling costs | Nowhere the client sees |
| Raw exports — backlink dumps, keyword lists with 40 columns | An appendix or a separate sheet |

The strategy may *refer* to what the audit found — "the site has 180 pages
with no meta description; fixing them is month one" — in one sentence, in
plain words, where it explains what we are doing. One sentence of finding, a
paragraph of plan. Not the other way round.

**Say it in the fewest technical words that are still true.** Not *canonical
tag implementation across duplicate URL variants*; rather *several pages exist
at more than one address, so we will tell Google which address is the real one
for each.* `plain-language.md` is the standard and `check_strategy_doc.py`
counts the failures.

**Compare every number to something.** A figure on its own means nothing.
610 referring domains says nothing; 610 against a competitor's roughly 2,100
is an argument. Every baseline carries either a competitor's equivalent or
the target it is being measured against.

**Round it, and keep the hedge.** `approximately 4,500 visits`, not `4,501`.
`about $23,600`, not `$23,655`. The rounding is both honesty and readability
— see *Round every estimate* in `plain-language.md`. Tables keep their
digits; prose does not.

---

## Figures, not tables, wherever a number has to land with a non-specialist

The client's instruction: *"It is also a client facing doc, it should have
more content in design format like graphs and progress bars/circles for the
customer who knows nothing about technical terms can see and understand."*

`scripts/make_figures.py` generates them from a small JSON spec. Plain SVG,
no dependencies, embeds directly into Word, Pages and Google Docs.

| Section | Figure | Instead of |
|---|---|---|
| Objectives | `goal` — today against target, two bars a row | The ten-row objectives table |
| What happens when | `journey` — a twelve-month track with the work on it | Reading the month table to find out roughly when things land |
| Google Business Profile | `compare` — the firm's bar in colour against competitors | The grid table of average positions and review counts |
| Monthly report | `ring` — a progress circle per objective | A column of numbers |

**Use `goal`, not `ring`, in a strategy document.** A ring shows how far
through the work we are, which is the right question in a monthly report and
a useless one at kickoff: every ring reads 0% and a page of empty circles
says only that nothing has happened yet. Two bars show the jump the plan is
for, which is the thing the client is buying.

**The table stays underneath the figure where the detail matters.** A figure
is for the partner skimming it; the table is for the person who has to build
from it. The figure does not replace the table, it goes above it — and on a
section where the table is the deliverable, like the page plan or the keyword
clusters, there is no figure at all.

**Every figure's label follows the same language rules as the prose.** No
`Tier 1`, no `Q&A seeded`, no `Speed Index`. A figure a client cannot read is
worse than a table, because it looks like it was meant to be easy.

---

## Speed optimization is part of the plan, never a discount

Page speed work is included in the SEO service. It is not an upsell, it is not
a saving, and it is **never** written up as something the client is being
spared because their site is already fast.

That is a commitment, so it holds even when it would make a nice line in a
proposal. Two reasons it keeps getting broken: a fast site makes "we are not
charging you for this" sound generous, and speed is easy to price separately
because it is easy to measure.

What to write instead: speed appears in the technical section as work with an
owner and a month, like everything else. If the site is already fast, the
section says what is measured, what the targets are, and that the work is
keeping it there as pages get added — because a site that passes Core Web
Vitals today fails them three months into a content programme if nobody is
watching. `check_strategy_doc.py` fails a document that offers speed work as a
saving.

---

## The client-facing table of contents

Every title below is the section name. None of them contains a phase number.

**Front matter**

| Section | What it is |
|---|---|
| Introduction | Three or four single-sentence paragraphs. What this covers, over what term, and what it is for. The benchmark's is reproduced in `assets/client-letter-benchmark.md` |
| Summary and decisions needed | One page. What we are doing, and the three to five decisions we need from the client, each with a recommendation. Written last |
| What we will do first | The numbered priorities table — see below. The one page a client who reads nothing else will read |
| What happens when | The twelve-row month table from `engagement-plans.md`, **with the `journey` figure above it.** One row per month, counts rather than names. On a six-month plan it is six rows and says so |

There is no *How to read the figures* section any more. It named every
source and its pull date, which is the audit trail the client asked us to
drop. The honesty it was protecting now lives in the sentence beside each
number — `an estimated 4,500 visits a month` — which is where a client
actually reads it.

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
| Changes to pages you already have | `phase9-10-architecture-linking.md` — see below |
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
| Page speed and Core Web Vitals | `phase17-18-19-tools-tracking-reporting.md` — included work, never a saving |
| Links and authority — the plan | `backlink-plan.md`, judgement from `off-page.md` |
| Who we will approach for links, in tiers | `backlink-plan.md` §3 — the prospect sheet |
| Anchor text, page by page | `backlink-plan.md` §4 |
| Links to disown, and why | `backlink-plan.md` §2 |

**Part 6 — How we will know it worked**

| Section | Built from |
|---|---|
| Objectives, with baselines and timelines | `phase5-6-strategy-and-gmb.md` |
| The tools, and what each one is for | `phase17-18-19-tools-tracking-reporting.md` |
| Rank tracking setup | `phase17-18-19-tools-tracking-reporting.md` |
| What the monthly report contains | `phase17-18-19-tools-tracking-reporting.md` |
| The quality gate every page passes | `seo-content-production` — the three conditions |
| Who approves what | the client profile's approvers |

**Appendices — there are none**

The client asked for all of them out, in these words: *"We don't need
appendixes. We don't need to convince the client here. We have already
onboarded the client and this is a strategy document."*

He is right, and the reason is worth keeping. An appendix is something you
attach when you expect to be challenged. We are past that point — the
contract is signed — and a document that still carries its evidence locker
reads as a document that is still arguing.

| Was Appendix | Where it goes now |
|---|---|
| A — Full keyword list with scores | The CSV. `keywords-website.csv` ships beside the document and is referenced in one sentence |
| B — Decisions on the previous plan | Nowhere the client sees. Our own notes |
| C — Open questions for the client | A short section in the front matter, before anything else, so they can answer it in one sitting. Not buried at the back |
| D — Sources and pull dates | Nowhere. See *No provenance* in `plain-language.md` — the word "estimated" stays on the figure, the citation does not |

### Saying how long it is

The summary's first paragraph states the term and that the optimizing
continues through it:

> This plan covers twelve months. The content and every other deliverable
> below is spread across that year, and we keep optimizing what is already
> live for the whole of it — the work does not stop once the pages are
> published.

Without it a client reads a hundred blog topics as a list of what is about to
happen, is surprised in month two that four posts exist, and surprised again
in month nine that there is work left. `engagement-plans.md` has the
six-month wording and the comparison section a six-month document carries.

### The priorities table

Front matter, straight after the summary, and the section most clients read
twice. Numbered, ordered by what we do first, with the starting point on every
row so the client can see the size of the gap rather than being told it is
large:

| # | What we are going to do | Where it stands today | By when |
|---|---|---|---|
| 1 | Get the bail hearing page built and ranking | No page exists | Month 2 |
| 2 | Take keywords in positions 1–3 from 107 to 200 | 107 (Ahrefs, 9 Nov 2026) | 6 months |
| 3 | Move the link profile from 18% dofollow to 40% | 18% (Ahrefs, 9 Nov 2026) | 12 months |

Eight rows at most. A priority list of twenty is a task list, and a client
cannot tell from it what matters. The rest of the work belongs in the task
board, which is where a long list is useful.

### Changes to pages you already have

The strategy is mostly about pages that do not exist yet, which is why the
pages that *do* exist get skipped — and they are the ones already earning
something. One table, one row per existing page we are changing:

| Page today | What changes | New URL, if it moves | Why |
|---|---|---|---|
| `/criminal-law/sexual-assault.html` | New URL, retitled, expanded to 1,800 words | `/sexual-assault-lawyer-toronto/` | The address says nothing about the service or the city; the page already ranks on page 2 and the words it is missing are the ones people search |
| `/about-us/` | Title and meta description only | — | Title is the firm name alone |

**A URL change is a real change with a real risk**, so every row that moves an
address says so plainly, and the plan says a 301 redirect goes in at the same
moment, old address to new. A moved page with no redirect loses whatever it
had earned. Where a page ranks well already and the gain is small, say that
and leave it: *this page is already working; we are changing its title and
nothing else.* A client reading a plan that moves every URL on their site is
right to be nervous.

### The objectives table

Part 6's first section is a table, not prose, and it is the section the client
returns to:

| # | Objective | Baseline | Target | By |
|---|---|---|---|---|
| 1 | Organic sessions | 2,800/month (GA4, Oct 2026) | 5,000/month | 6 months |
| 2 | Keywords in positions 1–3 | 107 (Ahrefs, 9 Nov) | 200 | 6 months |

Every row carries its baseline, because a target with no baseline cannot be
checked. The **source and date come out** of the client's copy — they go to
`save_strategy_projections` instead, where the portal keeps them and can
check the target later. A target that lives only in the PDF is a target
nobody will ever be held to, and a pull date printed beside it was never what
made it checkable.

**Put the `goal` figure above this table.** Five or six rows of today-against-
target bars is the single most useful picture in the document, and the table
underneath carries the dates for whoever wants them.

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
python scripts/check_strategy_doc.py <assembled_document> --full
python scripts/check_keyword_set.py <keywords.csv> --kind website --plan 12
python scripts/make_figures.py figures.json --out figures/
```

The first checks unresolved placeholders, dead-advice language from
`fact-check-registry.md`, the readability failures in `plain-language.md`,
audit content that has leaked in, page speed written up as a saving, and —
with `--full` — that every section above has a heading. Drop `--full` when
the request was a slice rather than the whole document; a keyword refresh is
not missing an off-page section, it was never asked for one.
The second checks the keyword tables' shape — pass `--plan 6` on a six-month
engagement, whose set is fifty rather than a hundred. Neither replaces a human read for
voice, accuracy and — for a regulated client — compliance sign-off.

The last check is not mechanical: **hand the document to somebody who was not
in the research and ask them what we are going to do.** If they cannot answer,
the structure is still wrong, however many checks passed.
