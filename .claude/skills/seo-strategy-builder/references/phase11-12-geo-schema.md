# Phase 11 — GEO/AEO Strategy, and Phase 12 — Schema Plan

Read this file in full before touching either phase. These are the two fastest-moving, most-perishable phases in the entire document — more than any other phase here, **do not template these forward from a previous client's document or from this skill's own examples.** Platform behavior around AI-generated answers and structured data changes on a timescale of months, not years.

## The rule for both phases

Before writing a single recommendation into either phase: (1) check `references/fact-check-registry.md` for anything already flagged there, (2) run a fresh search for anything not covered or anything that might have changed since the registry was last updated, (3) only then draft the phase. A phase built entirely from memory or from an old template is the single most likely place in this whole system to hand a client advice that's actively wrong.

## Phase 11 — GEO/AEO Strategy

**What it produces:** a plan for visibility inside AI-generated answers (Google AI Overviews/AI Mode, ChatGPT, Perplexity, Gemini) — distinct from classic ranking, since these systems read a handful of sources and generate an answer rather than ranking ten links.

**Required inputs:** current AI citation status for the client and its competitors, plus whatever the freshest available documentation says about how each platform selects and attributes sources.

**If Ahrefs is connected**, this stops being purely manual, exploratory research — Ahrefs' Brand Radar tools give a real, measured answer to "is the client actually getting cited" instead of a handful of spot-checked searches: `brand-radar-mentions-overview` / `brand-radar-mentions-history` for mention volume over time, `brand-radar-citations-overview-entities` / `brand-radar-cited-domains` for who's actually getting cited (the client, or named competitors), and `brand-radar-sov-overview` for share of voice against competitors directly. Use this as the data layer under the research protocol below, not a replacement for it — the protocol's manual checks still catch platform-specific nuance Brand Radar's aggregate numbers won't show.

**Research protocol:**
1. Pull the Brand Radar data above if connected; otherwise (or in addition), search the client's top 5-10 priority terms (from the keyword clusters) directly in Google (checking for an AI Overview), ChatGPT, Perplexity, and Gemini. Note who gets cited, and what kind of content those cited sources look like (depth, freshness, structure, source type).
2. Search for current guidance on AI-citation behavior from each platform's own documentation and recent (last 3-6 months) reporting from established SEO trade press — treat anything older than 6 months as likely stale in this specific area.
3. Cross-check any tactic under consideration against `references/fact-check-registry.md` — several previously-standard AI-visibility recommendations (schema as a citation driver, llms.txt, raw citation volume) have already been shown not to work as claimed.

**Prompt pattern (after the research above is done and pasted in):**
```
Here is [Client]'s foundation document: [paste].
Here is what I found researching current AI-search citation behavior for
[Client]'s priority terms, as of [date]: [paste findings].

Draft a GEO/AEO strategy grounded only in the research above — content
depth and structure recommendations, source/citation-worthiness factors,
and anything platform-specific worth acting on. Do not include a
recommendation unless it's supported by the research provided or by
`fact-check-registry.md`. Where the research is inconclusive, say so
rather than recommending a tactic on the strength of general SEO
intuition.
```

## Phase 12 — Schema Plan

**What it produces:** which structured-data (schema.org) types go on which page types.

**Required inputs:** current documentation on which schema types actually produce a visible or measurable effect (rich results, AI citation influence, entity clarity) — re-verified, not assumed.

**Baseline as of the last verification (re-check before use, per the rule above):**
- **Worth implementing:** `LegalService`/`Attorney` (or the equivalent entity type for the client's industry) with full address, phone, geo, hours; `Organization` with logo and social links; `Person` for each named professional; `BreadcrumbList`; `Article`/`BlogPosting` with a real author reference.
- **Not worth implementing, despite being commonly recommended:** `FAQPage` (rich result withdrawn from Google Search), `HowTo` (deprecated since 2023), `Speakable` (perpetual beta, scoped to news publishers). `AggregateRating` built from the business's own collected testimonials is a structured-data policy violation, not just ineffective — self-serving reviews on your own site are explicitly prohibited.
- Schema generally should not be framed as a primary AI-citation driver in the plan — a large-sample study found it moved AI citations only marginally. Recommend it for entity clarity and classic-search benefit, not as a GEO tactic.
- If a schema plan is being produced to document or mirror what an existing vendor deliverable recommended (rather than as a fresh recommendation), include the original recommendation — don't silently cut `FAQPage`/`HowTo`/`Speakable`/self-serving `AggregateRating` out of the reproduction — and attach the labeled note from `fact-check-registry.md`'s "Reproducing a flagged practice" section directly below each one.

**Prompt pattern:**
```
Here is [Client]'s foundation document and page architecture: [paste].
Here is the current schema guidance I've verified as of [date]: [paste
your re-verified version of the list above, updated if anything's
changed].

Produce a schema plan mapping each schema type to the page types in the
architecture that should carry it. For each type, note what it actually
does today (rich result / entity clarity / no confirmed effect) rather
than an assumed benefit.
```
