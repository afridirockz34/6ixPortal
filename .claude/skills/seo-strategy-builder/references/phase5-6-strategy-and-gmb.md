# Phase 5 — SEO Strategy & Targets, and Phase 6 — GMB/Local Strategy

## Phase 5 — SEO Strategy

**What it produces:** stated objectives, on-page priorities, a technical SEO checklist, and a phased timeline (typically 90-day / 6-month / 12-month horizons).

**Required inputs:** the foundation document's baseline metrics, plus the client's actual goals — a real conversation, not an assumption. "We want more traffic" is not specific enough to build a plan against; get a number, a timeframe, or at minimum a comparison ("we want to look like [competitor]").

**Prompt pattern:**
```
Here is [Client]'s foundation document: [paste].
Here is what the client said about their goals: [paste from the
questionnaire or call notes].

Turn this into a Phase 5 SEO Strategy: specific objectives (numeric where
the baseline supports setting a number, directional where it doesn't),
on-page priorities ranked by expected impact, a technical SEO checklist
(crawlability, Core Web Vitals, mobile, HTTPS, indexation — flag any of
these not yet verified rather than assuming they're fine), and a phased
timeline (90-day / 6-month / 12-month). Do not project specific traffic
or ranking numbers you can't support from the baseline data or realistic
industry pacing — vague optimism reads as credible in a document and
falls apart in a client meeting six months later.
```

**Where agency documents oversell, and how to avoid it here:** a specific month-by-month traffic projection ("+340% organic traffic by month 6") is the single most common way these documents overpromise. Unless there's a comparable, verifiable case study backing a specific number, state directional expectations and the *conditions* that would produce them, not a number invented to look confident.

**Recording the targets once the strategy is approved.** Phase 5's numeric objectives are the only part of the strategy a later review can be measured against, so once the client signs off, write them to the portal with `save_strategy_projections` — metric key, target date, value, and a one-line note on what the target assumes. Then `capture_performance_snapshot` so the baseline is the day the work started.

The honesty rule above is what decides *which* objectives get saved, and it gets stricter here rather than looser. An objective stated directionally in the document ("organic traffic should grow meaningfully") does not become a number just because the field wants one — leave it out, and the metric reports as `no_projection`, which is an honest state. Only an objective the document commits to in writing, with a date, gets saved. A target invented to fill a row outlives the conversation that produced it, becomes what every future check measures against, and makes a client who is genuinely doing well read as behind.

`references/performance-tracking.md` has the tracked metric keys (only those twelve are compared), which direction each improves in, and the rest of the rules.

## Phase 6 — GMB / Local Strategy

**What it produces:** a Google Business Profile optimization plan — profile completeness, posting cadence, review-request strategy, Q&A seeding — plus any other local-pack-relevant recommendations.

**Required inputs:** the client's current local visibility and the foundation document's service area tiers.

**Read `references/local-and-gmb.md` first.** With the BrightLocal connector in the session this phase stops being the one phase with no data behind it: a rank grid shows where the business actually appears across the area it sells into, point by point, and the same grid shows a named competitor on top of it. That file covers which tool answers what, the credit discipline (a single grid run costs 200–400 credits and balances are nothing like that), and the two ways a grid gets read wrong — chiefly that `rank: 0` means "did not appear" and averaging it yourself makes a grid look ~20% better than it is.

Without the connector, fall back to the live profile and a manual search from the client's city, and say in the deliverable that is what you did.

**Prompt pattern:**
```
Here is [Client]'s foundation document: [paste].
Here is the current state of their Google Business Profile: [paste —
category, completeness, review count/rating, posting history, current
photos — `get_location` returns most of this directly].
Here is their local visibility: [paste the rank-grid summary and the
shape of it, the competitor comparison on the same grid, and the real
review counts from Reputation Manager].

Draft a GMB optimization plan: profile completeness gaps, category
accuracy, posting cadence recommendation, a review-request process, and
Q&A seeding. If keyword-stuffing the business name or incentivizing
reviews is being documented to match an existing example rather than
recommended fresh, include it with the labeled note format from
`fact-check-registry.md` ("Reproducing a flagged practice..." section) —
never present either as a safe, unqualified recommendation.
```

**Compliance gate — do not skip this:** before recommending any review-request process for actual use (not documentation), re-check `references/fact-check-registry.md`'s reviews/GMB entries and, for a regulated client (law, medical, financial), confirm the process doesn't cross into a professional-conduct or client-inducement rule specific to that regulator. This is one of the two phases (the other being Phase 11/12) where a stale or generic recommendation carries real risk, not just wasted effort.
