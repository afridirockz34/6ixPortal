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

## Phase 6 — GMB / Local Strategy

**What it produces:** a Google Business Profile optimization plan — profile completeness, posting cadence, review-request strategy, Q&A seeding — plus any other local-pack-relevant recommendations.

**Required inputs:** the client's current GBP status (pull it manually — log into the profile or ask the client for access) and the foundation document's service area tiers.

**Prompt pattern:**
```
Here is [Client]'s foundation document: [paste].
Here is the current state of their Google Business Profile: [paste —
category, completeness, review count/rating, posting history, current
photos].

Draft a GMB optimization plan: profile completeness gaps, category
accuracy, posting cadence recommendation, a review-request process, and
Q&A seeding. If keyword-stuffing the business name or incentivizing
reviews is being documented to match an existing example rather than
recommended fresh, include it with the labeled note format from
`fact-check-registry.md` ("Reproducing a flagged practice..." section) —
never present either as a safe, unqualified recommendation.
```

**Compliance gate — do not skip this:** before recommending any review-request process for actual use (not documentation), re-check `references/fact-check-registry.md`'s reviews/GMB entries and, for a regulated client (law, medical, financial), confirm the process doesn't cross into a professional-conduct or client-inducement rule specific to that regulator. This is one of the two phases (the other being Phase 11/12) where a stale or generic recommendation carries real risk, not just wasted effort.
