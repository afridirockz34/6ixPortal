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

**This is the running order of the document the client approved**, after
four drafts were rejected and one was rebuilt by hand over several hours.
Sixteen sections, in this order, and nothing else.

| # | Section | What goes in it |
|---|---|---|
| 1 | Introduction | Three or four single-sentence paragraphs. Where the site stands in one rounded number, what the opportunity is, what this covers |
| 2 | Monthly Plan | The twelve-month table. See *The monthly plan* below — it has its own rules and most of them are prohibitions |
| 3 | Website Optimization & Technical SEO | What changes on the pages that already exist, with the change table. Page speed is a row in that table, never its own section |
| 4 | Keyword Research & Targeting Strategy | The cluster table: group, keyword count, monthly searches, where they are targeted |
| 5 | Service Pages & Location-Based SEO | What gets built and why each page has a search behind it |
| 6 | The page plan | The build table: what, how many, which months |
| 7 | Google Business Profile and Local Search | The profile work, and the profile table |
| 8 | Current GMB Ranking | The grids. See *The GMB grids* below |
| 9 | Links and Authority | Where links come from. **No month column** — see *Nothing client-dependent gets a date* |
| 10 | AI Optimization | How we write for AI answers |
| 11 | Content Development & SEO Authority | The article programme |
| 12 | Content Voice & Tone | Who is reading and what they are worried about |
| 13 | What this sounds like on the page | A worked passage of real page copy, quoted |
| 14 | Our 12-Month SEO Objectives | The objectives table, with the `goal` figure above it |
| 15 | Ongoing SEO Management & Reporting | Monitoring, monthly reports, quarterly reviews, the monthly map-grid re-check |
| 16 | Closing Thoughts | Three paragraphs |

### Four sections that were cut, and stay cut

Each of these was in the generated document and taken out by hand.

| Cut | Why |
|---|---|
| **What We Will Do First** — a numbered priorities table | It restated the first three months of the monthly plan one page earlier. Two tables of the same work, and the client has to reconcile them |
| **Current SEO Performance & Opportunities** | Competitor traffic estimates and a comparison of link profiles. This is audit content. Rule 3 in `plain-language.md` |
| **Page speed**, as its own section | It is one row in the website changes table: *Further optimization of website speed · All · 4* |
| **What We Need From You** | Access, photographs, approvals. All of it client-dependent, and giving it a month promises a date we do not control |

There are no appendices. There is no *How to read the figures* section.

---

## The monthly plan

The first table the client reads, and the one they came back on hardest.
Three columns, in this order:

| Month | Writing | What we work on |
|---|---|---|
| 1 | 3 hub pages and content optimization | **SEO:** Write a title and a description for all 23 pages. Schema, address and phone correction. **GMB:** Fill in the services list and set the service area. Rewrite the description to cover every service and city. Start posting, 1 post this month. **Listings:** Begin building and correcting the business listings across the main directories. **New Content:** Brampton, Belleville and Owen Sound hub pages |
| 2 | 1 service page, 3 sub-service pages, 8 articles | **SEO:** Rebuild the links between pages. Delete the two unfinished pages. Remove 8 duplicate blogs. **GMB:** 2 posts. **New Content:** The impaired driving page, and 3 Brampton sub-service pages |

**The column order is Month, Writing, What we work on.** Writing sits beside
the month because the count of pages and articles is the thing a client
checks first.

Inside the third column, group with inline bold labels — **SEO:**, **GMB:**,
**Listings:**, **New Content:**. A table cell is a specification, so the
terse style is right here and only here; `plain-language.md` governs the
prose, not these.

### Month one has to show them something

A plan whose first month is titles, schema and internal links reads as a
month of invisible work. **GMB posting starts in month one**, because it is
the one thing the client can open their phone and see.

The same principle sets the rest of month one: whatever the client named as
their priority gets work against it in month one, even if the bulk of that
build lands later. Hub pages for the priority cities belong here.

### What never goes in this table

Six prohibitions, every one of them from the rejected drafts.

| Never | Why | Where it goes |
|---|---|---|
| A **Links and listings** column | Directory and membership work is not a parallel track; it is part of the work | Folded into *What we work on* as **Listings:** |
| A separate **Website and Google profile** column | Those *are* what we work on | Same column, under **SEO:** and **GMB:** |
| Reviews, reports, retrospectives | *Half-year review*, *Quarterly strategy review*, *the year review*, *Set the priorities for year two* | Section 15, *Ongoing SEO Management & Reporting* |
| **Re-running the map grid** on a single month | It is re-run every month, so putting it on month 6 says it happens once | Section 15, as a standing monthly item |
| Anything the **client** has to supply | Photographs, logins, approvals, memberships | Its own section, with no date |
| Who reviews a draft, and how long they take | The client did not ask and it is our internal process | Nowhere |

**Re-optimising pages we have already published does stay.** It is
production work, not reporting: *Re-optimize the pages published in months 1
to 4* is a month of somebody's time. The test is whether a person does
something to the site that month. Re-writing a page passes. Reading a report
does not.

---

## Nothing client-dependent gets a date

A month next to a row is a promise. We can only promise the things we
control.

Photographs for the profile, the firm's own memberships, logins, sign-offs —
every one of those waits on the client, and dating them means the plan is
late in month two through nobody's fault. The *Links and Authority* table
therefore has **no Month column at all**; it lists where the links will come
from and stops.

Write the client-dependent work as its own short section with no table:

> Four of the strongest links available are ones the firm already qualifies
> for through its memberships. We will claim the Criminal Lawyers'
> Association directory listing, the Law Society of Ontario directory entry
> and the Toronto Lawyers Association profile as soon as we have the login
> details for each.

That names the work, says what it depends on, and promises no date.

---

## The GMB grids

Section 8 is the one the client asked for in these words: *"simple grids are
not understandable."* A table of average map ranks is a column of numbers
with no geography in it. The same data on a map of their city is instantly
readable — this is where the profile shows, and this is where it does not.

One grid per target keyword, named in the caption: *criminal defence
lawyer*, *sexual assault lawyer*, and whatever else the strategy is
targeting locally.

**Preferred: the BrightLocal export.** Open the Local Search Grid report for
the location, pick the keyword, and use the map BrightLocal draws. It has
Google's own street map underneath, which is what makes it legible.

**Fallback: generate it.** When BrightLocal is unreachable, or the report has
not been run yet, `scripts/make_figures.py` draws a `grid` figure from the
point results — each of the points positioned where it actually sits, shaded
by rank band, with the office marked. It has no street map under it, so it
is the second choice, but the section is never empty and the geography is
still there.

Either way, say underneath what the grid shows in one sentence: *the profile
appears at none of the 49 points we track for this search.*

---

## Type and colour

Two rules, both from the approved document, both measured out of it.

- **Body text 13pt. Table text 9pt.** The generated document set everything
  at 9pt, including the prose, and it was retyped by hand. In the approved
  file every one of the 125 paragraph runs is 13pt and all 302 table runs
  are 9pt.
- **No pink anywhere.** `#FF6699` is the portal's interface colour and it
  does not belong in a client document. Figures use the blue, grey and green
  in `scripts/make_figures.py`; headings and rules are the text colour.

### Saying how long it is, and what the year contains

The monthly plan opens with one sentence that states the term, the whole
inventory, and that the optimizing continues through it. From the approved
document, verbatim:

> In the first 12 months, we will publish and continuously optimize; 5 city
> hub landing pages, 4 service pages, 28 city pages, 12 Toronto
> neighbourhood pages, one guide and 86 articles, alongside monthly reports
> and quarterly strategy reviews.

Two jobs in one sentence. Without the inventory a client reads eighty-six
article topics as a list of what is about to happen and is surprised in
month two that eight exist. Without *continuously optimize* they read the
engagement as a delivery project that ends when the last page ships.

Every number in that sentence has to reconcile with the page plan table and
with the month rows. A client who adds up the Writing column and gets a
different answer has found the one error that costs us the most trust.
`engagement-plans.md` has the six-month wording.

### There is no priorities table

A numbered *What We Will Do First* table used to sit in the front matter.
It was cut from the approved document and it stays cut: it restated the
first three months of the monthly plan one page earlier, so the client had
two tables of the same work to reconcile and no way to tell which one was
authoritative.

The monthly plan is the priority list. It is ordered by month, which is the
order the work happens in, which is what a priority list is.

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
