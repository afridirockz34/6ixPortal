# Phase 1 — Foundation Document (Brand Summary)

This is the spine of the entire strategy document. Every other phase opens with "here is [Client]'s foundation document" — it is the one hard prerequisite everything else depends on. Get this right before touching anything else; a wrong fact here propagates into every phase built on top of it.

## What it produces

A single document covering: Business Identity, Core Services (ranked by the client's own stated priority/profitability), Firm/Team Principals and Credentials, SEO Baseline (current metrics, with an assessment of what's healthy vs. concerning), Unique Value Proposition (2-3 pillars, each grounded in a specific verifiable fact — never a generic claim like "we care about our clients"), Target Audiences, Service Areas (tiered by current ranking status if that data exists), and Digital Goals (business goal → SEO outcome → current baseline, as a table).

## Required inputs

1. **A completed client questionnaire.** Use a two-part structure: a brand questionnaire (business story, ideal client, pain points, differentiation, who they consider competitors, sensitivities/compliance constraints) and an on-page SEO questionnaire (top keyword phrases they'd use themselves, service area, prior SEO history, content production capacity, most profitable services, sales/intake process). If a new client hasn't filled one out yet, reuse the NR Lawyers questionnaire's structure as the template.
2. **A baseline SEO-platform pull**, dated. From Ahrefs: Site Explorer overview for the domain — Domain Rating, organic traffic estimate, organic keyword count, keywords ranking in the top 3, backlink count and dofollow ratio, plus a content/technical health score if available. From Semrush: the equivalent Domain Overview metrics. This data is not optional and cannot be estimated — if it hasn't been pulled yet, stop and pull it before drafting this phase.

**If Ahrefs is connected** (check the session's tool list for `mcp__Ahrefs__*`), pull this directly instead of asking for an export: `site-explorer-domain-rating` and `site-explorer-metrics` for the headline numbers, `site-explorer-metrics-history` for the trend (is the baseline flat, growing, or declining — say so, it matters for Digital Goals), `site-explorer-organic-keywords` for the top-3 count, and `site-explorer-backlinks-stats` for the backlink/dofollow numbers. If a Site Audit project already exists for the domain, `site-audit-issues` gives a real technical-health read for that column instead of leaving it blank. Call `doc` on each tool before its first use in a session.

## Prompt pattern

```
You are building the foundation document for [Client]'s SEO strategy. This
document will be referenced by every subsequent phase, so it needs to be
complete and precise — no invented facts, no filled gaps.

Here is the completed client questionnaire: [paste]
Here is the baseline SEO data pulled from [Ahrefs/Semrush] on [date]: [paste]

Produce a Brand Summary document with these sections: Business Identity,
Core Services (ranked by stated profitability), Firm/Team Principals,
SEO Baseline (with an assessment column noting what's concerning vs.
healthy relative to a business of this size/age/market), Unique Value
Proposition (2-3 pillars max, each grounded in a specific verifiable fact
from the questionnaire — not a generic claim), Target Audiences, Service
Areas (tiered by current ranking status if the data supports it), and
Digital Goals (as a table: business goal -> SEO outcome -> current
baseline).

Where the questionnaire left something blank or unclear, write
"[NEEDS CLIENT INPUT: specific question]" rather than inventing an answer.
Where a baseline metric wasn't provided, write "[NEEDS DATA: metric name]"
rather than estimating one.
```

## What actually needs judgment here

Ranking services by "stated profitability" sometimes conflicts with what the client talks about most in the questionnaire — profitable services and heavily-discussed services aren't always the same list. When they diverge, ask which should drive priority rather than picking one silently; this decision shapes the keyword and content-calendar phases downstream.

See `assets/nr-lawyers-foundation-document.md` for a complete worked example.
