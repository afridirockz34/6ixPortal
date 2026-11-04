# Keyword Opportunity Score and Search Intent Classification

Why this exists: the single biggest difference between a strategy document that reads well and one that actually drives results is how keywords get prioritized. "Pick the highest-volume terms" and "pick what feels achievable" both produce a plausible-looking document that doesn't necessarily point at what will move revenue for *this* client. A consistent, scored methodology — the same discipline `seo-audit-report`'s SEO Health Score applies to findings — turns prioritization into something you can defend to a client and re-run the same way on every account, instead of re-deriving judgment calls phase by phase.

Every keyword-driven phase downstream of Phase 7/8 (Page Architecture, Internal Linking, Blog Topics, Content Calendar, and the Phase 20 retrospective) should use this score and this intent classification rather than inventing its own priority ordering.

## Search Intent Classification

Classify every keyword into exactly one of four buckets:

- **Informational** — the searcher wants to understand something, not transact yet ("how long does a plumbing repair take").
- **Commercial investigation** — comparing options before deciding ("best plumber toronto", "[service] vs [service]", "[competitor] reviews").
- **Transactional / ready-to-act** — intent to engage now ("emergency plumber near me", "book a plumbing inspection").
- **Local / navigational** — looking for a specific business or a local result ("[client business name]", "[service] in [neighbourhood]").

**How to classify:** keyword modifiers are the first signal (best/top/vs/reviews → commercial investigation; near me/buy/book/call/emergency/same day → transactional; how to/what is/why/guide → informational; a business name or hyper-local phrase → local/navigational). Where Ahrefs' keyword data surfaces SERP features (map pack presence, shopping results, featured snippet, a dense "People Also Ask" block), use those as a second signal — a map pack on the SERP is a strong local/transactional tell regardless of the keyword's wording. Ground the final call in the client's own context from the questionnaire (`seo_scope`, `seo_locations`, `seo_priority`) — a keyword that reads informational in isolation can be transactional for a business whose entire funnel runs through a single consultation booking step.

If a keyword's intent is genuinely split, say so ("informational, leaning commercial") rather than forcing false precision — this matters more than it sounds, because intent is what Phase 9 uses to decide page type.

**Intent → page type**, used directly by `phase9-10-architecture-linking.md`:

| Intent | Typical page type |
|---|---|
| Informational | Blog post, resource/guide page, pillar page |
| Commercial investigation | Comparison page, "best X" roundup, a service page built around trust signals (credentials, reviews, case outcomes) |
| Transactional | Service page or location page, minimal friction, strong CTA |
| Local / navigational | Location page, GBP-linked landing page |

A keyword tagged Transactional resolving to "new blog post" in the architecture, or one tagged Informational resolving to "new service page," is a mismatch worth catching before the architecture is finalized — it's one of the more common reasons a page ranks but doesn't convert.

## Keyword Opportunity Score (0-100)

Four weighted components, each normalized to a 0-100 scale before weighting:

| Component | Weight | What it measures | Source |
|---|---|---|---|
| Search volume | 25% | Normalized against the client's own keyword set (log-scaled — volume is usually heavily skewed, so a handful of head terms shouldn't dominate a linear scale) | Ahrefs `keywords-explorer-overview` / `site-explorer-organic-keywords` |
| Ranking difficulty (inverted) | 20% | 100 minus the platform's own difficulty score — easier keywords score higher | Ahrefs `keywords-explorer-overview` |
| Current position gap | 25% | "Room to move," not "already winning": not ranking at all scores lower than a page-2 near-miss (positions 11-20), which scores higher than an already-secure position 1-3 — there's little left to gain from a term already won | Ahrefs `site-explorer-organic-keywords` |
| Business value | 30% | Whether the keyword maps to a service the client themselves named as most profitable (`seo_profitable_services`), a priority service area, or a term they specifically volunteered (`seo_keywords`) | Questionnaire / portal profile — never invented |

Business value carries the largest single weight on purpose. A mid-volume keyword tied to the client's highest-margin service is worth more to them than a high-volume keyword in a service they don't actually want more of — defaulting to volume as the only signal is the most common way an AI-assisted keyword list ends up optimizing for the wrong thing.

**If business-value context wasn't captured** (Route 3 in `client-context.md` — no portal profile, no returned questionnaire): don't silently score Business Value as 0 for every keyword, and don't invent one either. Redistribute that 30% proportionally across the other three components, and say so explicitly, once, in the document — "this score reflects volume, difficulty, and position gap only; business-value weighting requires questionnaire input we don't have yet." Same discipline as the Health Score's missing-category rule in `seo-audit-report/references/audit-methodology.md`.

**Never publish a score for a keyword missing real volume or difficulty data.** Mark it `[NEEDS DATA: <keyword> volume/difficulty]` instead of estimating — a guessed number renders identically to a real one on the page, which defeats the entire point of scoring over gut feel.

### Worked example (illustrative — not pulled from a real account)

| Keyword | Volume | Difficulty | Position | Business value | Approx. score | Why |
|---|---|---|---|---|---|---|
| "emergency plumber toronto" | 880/mo | 42 | not ranking | named top-3 profitable service | ~78 | Real gap, meaningful volume, named priority, achievable difficulty — leads the list. |
| "24 hour plumber near me" | 1,400/mo | 61 | position 14 | named priority | ~71 | Highest volume here and a real page-2 opportunity, but harder to win — sequence close behind the first. |
| "plumbing company history" | 90/mo | 12 | position 4 | not a named priority | ~31 | Easy and already ranking reasonably, but low volume and not a stated business priority — low on the list despite being "winnable." |

If this table (or one like it) is used as an illustrative example inside a client deliverable, label it as such — never let a worked example read as if it were that client's real numbers.

## Using the score across phases

- **Phase 7/8 (keyword lists):** sort the clustered list by score, not alphabetically or by raw volume. The score is a column in the deliverable itself, not just an internal sorting aid — a client should be able to see why keyword A was prioritized over keyword B.
- **Phase 9 (page architecture):** the highest-scored keywords get priority page slots and get built first if architecture is phased; intent classification (above) decides page type.
- **Phase 10 (internal linking):** link equity — internal link count, anchor text diversity — should flow toward the highest-scored pages; a minimum-links-per-page-type table should weight toward them rather than treating every page as equal.
- **Phase 13/14 (content calendar):** sequence content production by score first, inside the client's actual stated capacity (`content_frequency`, `content_turnaround`) — never compress the realistic pace to front-load high scorers unrealistically fast. "Highest keyword value first" in Phase 14's prompt pattern specifically means "highest Keyword Opportunity Score first."
- **Phase 20 (results retrospective):** the score is a hypothesis about what should perform. The retrospective is where that hypothesis gets checked against what actually happened once a keyword was targeted — see `phase20-results-retrospective.md`.

## What this doesn't do

A high opportunity score is a prioritization signal, not a promise. Nothing here licenses stating a ranking timeline or a specific traffic number from the score — Phase 5's restriction on traffic/ranking projections still applies in full. The score orders the backlog; it does not forecast the outcome.
