# Proximity Ranking vs. our system — what to take, what not to

Read from the `Proximity SEO Services` folder on 2026-11-11: an SEO audit for
Adhillon Law, NR Lawyers' 101-page questionnaire + full SEO strategy, their
June monthly performance report, four results screenshots, and all 72 content
deliverables (40 blog posts, 16 location pages, 16 service pages).

---

## First, the thing that frames everything else

**Our 20-phase strategy is their 19 phases plus one.** Their contents page runs
Brand Summary → Competitor Analysis → Brand Voice → Content & Style →
SEO Strategy → GMB → 100 Website Keywords → 20 GMB Keywords → Post Types &
URLs → Internal Linking → GEO/AEO → Schema → 100 Blog Topics → Content Calendar
→ Custom Prompt → Content Templates → Tech Stack → BrightLocal Tracking →
Reporting Dashboard + KPIs. Ours is the same nineteen in the same order, with
Phase 20 (results retrospective) added on the end.

Their workflow also routes content approval to `musab@6ixdevelopers.com`, and
their strategy's worked client is NR Lawyers — the same firm our skills ship as
their worked example.

So this is not a competitor to reverse-engineer. It is the system ours was
modelled on, and the useful question is narrower: **in the two years of
operating detail they have and we don't, what is worth copying?**

---

## Their results, stated plainly

From their own screenshots, NR Lawyers:

| | May 2026 | Jun 2026 | Sep 2026 |
|---|---|---|---|
| Organic traffic | 2,805 | 2,802 | **6,524** |
| Organic keywords | 1,581 | 1,393 | **2,296** |
| Traffic value | — | — | $24,165/mo |

Their own 6-month target was 5,000+ traffic and 2,500+ keywords. They passed
the traffic target in four months and were close on keywords. That is a real
result on a competitive Toronto legal term set, and it is worth taking
seriously.

Two caveats worth holding at the same time. The June report shows traffic
*flat* and Top-3 positions *falling* (107 → 75) three months in — the curve
only bends in July. And these are Ahrefs-style estimates, not the client's GA4.
The shape is convincing; the absolute numbers are a tool's model.

---

## Where they are clearly ahead of us

### 1. The KPI table is a real forecast, and ours is not

Their Phase 19 is a 15-row table: Baseline (May 2026) → 30-day → 90-day →
6-month → 12-month, for organic traffic, keywords, Top-3 positions, traffic
value, content pillar score, authority score, DR, location pages live, blog
posts published, AI platform visibility, organic phone calls, form
submissions, GMB reviews, citation accuracy, dofollow ratio.

We have `save_strategy_projections` and `check_performance`, which is the right
*machinery* — but nothing tells a strategy author what a complete target set
looks like. Theirs does, and several rows are output metrics (pages live, posts
published, citation accuracy) that a client can verify independently of
rankings. Those are the ones that keep a client calm in month three when
rankings have not moved yet.

Note what they do **not** promise: no revenue, no lead counts in dollars, no
ROI multiple. Phone calls and form submissions are given as *percentage change
off a baseline to be set*, not absolute counts. That is consistent with our own
no-ROI rule, and it is a useful demonstration that a confident forecast does
not require inventing money.

### 2. A named tool stack with configuration and cadence

Phase 17 names SearchAtlas, BrightLocal, AiClicks, GSC and GTM, and for each
gives initial setup, an ongoing weekly/monthly/quarterly workflow, and who does
what. Ours says "Ahrefs, BrightLocal, the portal" and leaves the operating
rhythm to whoever is holding the session.

The single sharpest thing in it: **a content quality gate.** Nothing goes live
without (a) SearchAtlas Content Optimizer score 70+, (b) passing the Phase 4
checklist, (c) named-person approval. We have `check_draft.py`, which is the
(b) of that list, and no (a) or (c).

### 3. Hyperlocal location pages

Their 16 location pages are Toronto *neighbourhoods* — Humewood, Wychwood,
Casa Loma, Corso Italia, Forest Hill North and South, Cedarvale, Seaton
Village. Not cities. For a service where someone searches under stress and
close to home, neighbourhood pages are a much finer net than "criminal lawyer
Toronto", and our content skill talks about location pages only at city level.

### 4. The monthly report shows the work, not just the numbers

Their June report ends with **SEO Tasks Completed** — content optimizations (5),
new content from the calendar (2), GMB optimization, posts (2), GMB calendar
(4 posts), citation submissions (5), backlinks (2), monthly report, KPI
updates, position tracking.

That page answers "what did I pay for this month", which is a different
question from "what happened to my rankings" and the one that actually governs
renewal. Our implementation log holds this data and never shows it to the
client.

### 5. Things their audit measures that ours does not

Their audit is an automated single-page tool (SEOptimer-class, A+ to F- grades)
and most of it is weaker than ours — but five pieces are genuinely good:

- **A SERP snippet preview.** Shows the client their actual Google result.
  Costs nothing, lands immediately.
- **Keyword position distribution** — Position 1: 6, 2–3: 5, 4–10: 27, 11–20:
  11, 21–30: 7, 31–100: 4. One table that frames the whole opportunity.
- **Top anchors and referring-domain geography.** We report backlink counts;
  they show the anchor text and where links come from.
- **Letter grades per category.** A client reads "C" faster than "52/100".
- **GEO as a graded category**, including a "rendered content LLM readability"
  percentage.

### 6. Operational specifics we simply don't carry

- A single **NAP standard string** to be used verbatim everywhere.
- **Citation tiers** (Tier 1 critical: Law Society, CBA, Martindale-Hubbell…)
  with per-tier timelines.
- **Review velocity target** (3–5/month) and a 24–48h response rule.
- **Disavow audit** on the top 50 referring domains, named spam sources.
- **Anchor text diversification** as an explicit workstream.
- A **Month 1–3 / 4–6 / 7–12 phased timeline** with concrete counts.
- **Three reporting tiers**: weekly 3-bullet email (5-minute read, no
  attachment), monthly full report, quarterly strategy call.

---

## Where we are ahead of them

Said without flattering ourselves — these are real and they matter.

### 1. We measure the client's own data; they estimate

Their audit and their monthly report run on third-party estimates. Their report
says "Organic traffic held roughly flat at 2,802" — that is an Ahrefs model,
not GA4 sessions. We now read the client's GA4 and Search Console directly, and
the skills refuse to state an estimate as measurement. For a client who can
open their own analytics, that difference is the whole credibility of the
document.

### 2. Their audit is one page; ours is the site

The Adhillon audit crawls the homepage. Nineteen recommendations, and the tail
is padding: "Create and link your X Profile", "Install a Facebook Pixel", "Add
a DMARC Mail Record", "Remove Clear Text Email Addresses". A prospect who knows
their trade sees filler.

Worse, its "Top Backlinks — the highest value external pages linking to your
site" lists `bitcoinmix.biz`, `articleted.com` and `thebraggingmommy.com`.
Those are the links their own strategy doc would tell you to **disavow**,
presented to a prospect as an asset. That is the kind of error that costs an
account, and it is a direct argument for our rule that every figure names its
source and its meaning.

### 3. No competitor comparison in the audit at all

Their audit never mentions a competitor. Ours builds a competitor chart and, for
local clients, puts a rival's rank grid beside the client's on the same map.

### 4. Nothing checks their work mechanically

We have `check_audit_report.py`, `check_strategy_doc.py`, `check_draft.py`,
`check_implementation_log.py`, the fact-check registry, and a drift checker that
holds the skills to the portal's schema. Their quality gate is a human reading
a checklist. Ours catches unresolved placeholders, dead advice and British
spelling before a client sees them.

### 5. Phase 20

Their Phase 19 ends at "here is the dashboard". Nothing in their system asks,
six months later, whether the targets in that KPI table were met. Ours does,
and that is the phase that turns a plan into an account you can defend.

### 6. Honest-gap discipline

`[NEEDS DATA: …]`, the measured-vs-estimated rule, the refusal to average a
rank grid, the no-ROI rule. Their strategy is excellent but asserts throughout;
ours is built to mark what it does not know.

---

## What I would actually change, in order

**1. Ship a KPI target template.** (Highest value, lowest effort.) Add their
15-row baseline/30/90/180/365 structure to
`seo-strategy-builder/references/performance-tracking.md` as the shape a Phase 5
target set should take, including the output metrics — pages live, posts
published, citation accuracy — and keep conversions as percentage change off a
measured baseline. Feeds `save_strategy_projections` directly.

**2. Put "what we did this month" in the monthly report.** The data is already
in the implementation log. `monitoring-and-escalation.md` should require a
tasks-completed section with counts.

**3. Add the three reporting tiers.** The weekly 3-bullet email is the cheapest
retention mechanism in their whole system and we have no equivalent.

**4. Add a content quality gate to `seo-content-production`.** A numeric
pre-publish score plus a named approver, not just the banned-phrase checker.

**5. Add to the audit:** SERP snippet preview, keyword position distribution,
top anchor text. All three come from data we already pull; they are renderer
and template work, not new integrations.

**6. Write the operational playbook we don't have.** NAP standard, citation
tiers with timelines, review velocity targets, disavow audit, anchor
diversification. This belongs in `local-and-gmb.md` and a new off-page
reference.

**7. Hyperlocal location pages** as a named tactic in
`seo-content-production`, with the caution that a neighbourhood page needs
genuinely local substance or it is a doorway page.

**8. Consider letter grades** alongside the 0–100 Health Score in the audit.
Cosmetic, but it reads faster.

### What I would not copy

- Their single-page audit, or anything that pads a recommendation count.
- Presenting a raw backlink list as "highest value" without a spam screen.
- Reporting estimates where the client's own data is available.
- SearchAtlas/AiClicks as a dependency. The content-score gate is the good
  idea; it does not have to be that vendor, and our stack already reads GA4,
  GSC, Ahrefs and BrightLocal directly.

---

## One honest caveat

I read one real audit, one strategy, one monthly report and the content set
for a single client. I have not seen their intake process, their internal
checklists, how the strategy is built, or what the client was told verbally.
A 101-page strategy document is strong evidence of rigour, but the NR Lawyers
result also had an experienced criminal firm with 3,200 existing backlinks and
a 30-year principal behind it. How much of the lift is the system and how much
is the client is not answerable from this folder.
