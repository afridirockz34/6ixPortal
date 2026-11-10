# Fact-Check Registry: SEO, Schema, and AI-Search Claims

**This file is a snapshot, checked as of August 2026, and it will go stale.** Treat every row as a starting checklist to re-verify against current documentation or a fresh search before it goes into a live client deliverable — not as settled fact indefinitely. Update this file when something changes; a fact-check registry that never gets corrected becomes exactly the kind of outdated advice this skill exists to catch.

## Dead or dying — do not recommend as written

| Claim / recommendation | Current reality | What to do instead |
|---|---|---|
| Implement **FAQPage schema** for rich results / as a top AI-citation lever | Restricted to gov/health sites Aug 2023, then rich results **fully retired for all sites May 7, 2026** (docs removed 2026-06-15). | Keep FAQ content for readability and possible featured-snippet eligibility — don't sell or plan it as a rich-result or AI-citation tactic. For a genuine user-submitted Q&A page, `QAPage` is the type to reach for instead — not a rich-result lever either, but at least the semantically correct type. |
| **HowTo schema** on process pages | Rich result removed from **both desktop and mobile** since September 2023. The vocabulary still parses, it just produces no SERP effect. | Skip for SERP purposes. If the goal is comprehension (not ranking), use plain article structure with clear `<h2>` step headings instead — don't imply there's still a ranking benefit from the schema itself. |
| **Speakable schema** as a priority item | Perpetual BETA, scoped to news publishers for voice-assistant surfacing — never applicable to a general local business. | Skip. |
| **AggregateRating schema** from the business's own collected testimonials | Self-serving reviews marked up on the entity's own site are explicitly prohibited by structured data policy (since 2019) — this risks a manual action, not just zero benefit. | Star ratings for a local business come from the Google Business Profile only; never mark up your own testimonials this way. |
| **VehicleListing** / **Vehicle** schema for dealer inventory | Retired June 2025. No replacement — Google no longer renders dealer inventory rich cards. | If the listing is actually sold online, use regular `Product` schema with vehicle-specific properties; otherwise skip. |
| **ClaimReview** schema for fact-checking content | Retired June 2025. No Search rich-result replacement — the fact-check card is gone from Search (Google's Fact Check Explorer is a separate, non-Search surface and may still consume it). | Don't sell it for a Search rich-result benefit. Structure fact-checking content as a normal, well-sourced article instead. |
| **EstimatedSalary** / **OccupationalAggregateRating** schema | Retired June 2025. No replacement. | For an individual job listing, `JobPosting` with `baseSalary` is still live and is the correct type to use. |
| **LearningVideo** schema | Retired June 2025. No replacement as its own type. | Use the generic `VideoObject` schema, which still produces a rich result. |
| **Course Info** (the detailed single-course rich result) | Retired June 2025; docs removed 2025-09-09. | The separate **Course list** rich result (`Course` + `ItemList` carousel) is still supported — redirect any "Course Info" request to that format instead. |
| **SpecialAnnouncement** schema | Retired July 31, 2025 (the COVID-era emergency-info card). No replacement. | Use `Event` if the announcement is genuinely time-bounded, otherwise a plain `Article`/`WebPage`. |
| Publish **llms.txt** as "standard practice for AI visibility" | A Google representative directly declined to endorse it (Jan 2026: "to be direct, no"). No major AI product has been shown to consume it. | Harmless to publish, but don't bill it as a deliverable with an expected effect. |
| Schema markup framed as a **primary driver of AI assistant citations** | A large-sample study (1,885 pages that added schema) found it barely moved AI citations. | Recommend schema for entity clarity and classic SEO value, not as a GEO/AEO tactic. |
| **Keyword density** target (e.g. 1-3%) | No primary source supports a specific density number — long-running SEO folklore. | Write for the reader; use the keyword and natural variants where they belong. Don't count percentages. |
| **"Recover from the Helpful Content Update"** framing, or an "HCU audit" | HCU stopped existing as a standalone system in 2024 — folded into core ranking systems. | Follow general people-first-content guidance; don't chase a named system that no longer exists. |
| **"AI-referred traffic can't be measured"** | False as of 2026 — Search Console added generative-AI performance reporting, and Ahrefs' Brand Radar (available via its MCP tools when connected: `brand-radar-mentions-overview`, `brand-radar-citations-overview-entities`, `brand-radar-sov-overview`) gives a direct, measured read on AI-assistant mentions, citations, and share of voice. | Track it; not tracking it is now a choice. |
| Citation volume as **"the primary constraint"** on local ranking, sold as a large paid package | Citations are a hygiene floor, not a growth lever — declining in importance for years. Proximity and GBP signals dominate local ranking far more. | Fix the handful of major citations (industry-specific directories, Google, major aggregators) for consistency, then stop. Don't sell volume packages. |
| Appending a keyword to the **Google Business Profile name** | Can influence ranking but is a direct policy violation risking profile suspension — which means disappearing from the map pack entirely. | Never do this. Reporting a competitor doing it is the legitimate move. |
| **Incentivizing reviews** (discount/credit in exchange) | Violates review-platform policy outright; for regulated professions, likely also violates rules on client inducements. | Ask permission to request a review; never attach an incentive. |
| **"Organic sessions"** as the sole headline reporting metric | AI answers absorbing informational queries means impressions can hold/grow while clicks fall — sessions-only reporting can show "failure" during a genuinely successful campaign. | Report impressions, branded search volume, and conversions alongside sessions. |

## Reproducing a flagged practice when matching an existing document's format

Sometimes the job is explicitly to mirror an existing document — reproduce the NR Lawyers-style deliverable format, match a competitor's structure, or show a client what a previous vendor recommended. In that case, don't silently drop a row from this table out of the output. Include the practice the way the source material states it, directly followed by a clearly labeled note using this exact pattern:

```
> **Not recommended:** [current reality from the table above, in one sentence].
> [what to do instead / the actual risk, in one sentence].
```

The note is not optional and never gets cut for length or flow — a document that reproduces a risky or policy-violating recommendation without the attached note is actively worse than one that omits the practice entirely, because it reads as an endorsement. This applies to teardown/comparison/analysis documents. It does not relax the compliance gate elsewhere in this skill or in `seo-content-production`'s `seo-content-production/references/ymyl-legal-pack.md`: actual client-facing marketing copy being drafted for publication should still follow the "what to do instead" column directly, not the flagged practice — the reproduce-with-note pattern is for documenting or analyzing a recommendation, not for writing it into a live page.

## Still real — don't skip these under the assumption they're outdated

Crawlability and indexation basics. Core Web Vitals as a real (if weak) ranking factor, unchanged thresholds (LCP ≤2.5s, INP ≤200ms, CLS ≤0.1, measured on real-user field data, not lab scores). Named-author bylines with real, verifiable credentials. `LegalService`/`Attorney`/`Person`/`Organization`/`BreadcrumbList`-class schema for entity clarity (adjust entity types to the client's industry). Physical address and phone number consistency across the web. HTTPS. Genuine, documented case outcomes or results (never fabricated). Citations and links from other trusted, relevant entities (industry associations, directories, press).

**Dataset schema is not discontinued** — don't lump it in with the retired types above just because it's in the same family of "structured data that doesn't drive a Search rich result." It was never a Search rich-result type to begin with: it's consumed by **Dataset Search** (a separate, still-live Google surface), not by Search itself (clarified by Google 2025-11-05). If a client has `Dataset` markup, there's no reason to recommend removing it.

## Google Update Correlation — don't claim cause without checking the calendar first

Before writing a sentence that attributes a ranking or traffic change to anything specific — a Google algorithm update, a competitor's action, a technical fix just shipped — check that date range against a dated, confirmed list of Google's own algorithm updates (Google's Search Status Dashboard / Search Central blog) rather than assuming the timing alone proves the cause. This applies everywhere in a strategy document or report that a "why did this move" question comes up, not just in a dedicated technical section.

A date overlap is a hypothesis, stated as a hypothesis ("this drop lines up with the [month] [year] core update, though [client]'s own technical change the same month is also a plausible factor") — never written as a settled cause. This is exactly the kind of overreach a skeptical reader (a client questioning a report, or a prospect reading an audit) catches immediately, and it undermines every other, better-supported claim in the same document once they catch one overreach.

## Compliance flag, not just a fact-check

Anything involving "specialist"/certification language, testimonials, result-based claims, or review solicitation for a regulated profession (law, medicine, finance) needs sign-off against that profession's actual current regulator rules before it goes live — not against this registry, and not against general SEO advice. This registry can tell you a tactic is technically effective; it cannot tell you it's compliant for a specific regulated client. Route it to the client's own compliance process.

## How to re-verify a claim not covered here

1. Search for the claim plus the current year and "Google" or the relevant platform name (e.g. "AggregateRating schema policy 2026").
2. Prefer the platform's own documentation over SEO blog commentary where available.
3. If recent (last 3-6 months) reporting from multiple established SEO trade sources agrees, treat it as reasonably current — a single source, especially an older one, is not enough for anything going into a client deliverable.
4. If genuinely uncertain after checking, say so in the deliverable rather than presenting an unverified claim as settled — "worth testing, not yet confirmed" is an honest and useful thing to write.

## Sources

The deprecated-schema rows added for VehicleListing, ClaimReview, EstimatedSalary, LearningVideo, Course Info, SpecialAnnouncement, and the Dataset clarification, along with the Google Update Correlation discipline, were adapted with attribution from the open-source [claude-seo](https://github.com/AgriciDaniel/claude-seo) project by Agrici Daniel (MIT licensed) — specifically its `seo-schema` skill's deprecated-types reference and its `seo-audit` skill's update-correlation guidance. This file carries the same staleness warning at the top as everything else here — re-verify date-sensitive claims before relying on them.
