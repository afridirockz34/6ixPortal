# Phase 2 — Competitor Analysis, and Phase 7/8 — Keyword Lists

These three phases share a trait: none of them can be produced by prompting alone. They require raw numbers from an SEO platform, and the model's job is interpretation and organization of real data — not generation of plausible-looking numbers. Treat any keyword volume, difficulty score, or competitor traffic figure that didn't come from an actual export as fabricated, regardless of how reasonable it looks.

## Phase 2 — Competitor Analysis

**What it produces:** for each of 3-5 real competitors, an assessment of market position, SEO strengths, SEO weaknesses, and content gaps versus the client — assembled into a "Content Opportunity Matrix" grouping findings into High Opportunity (low competition + high value), Medium Opportunity, and Long-Term Play.

**Required inputs:**
- 3-5 real competitors. Ask the client who they consider competitors, then separately verify with the SEO platform who actually ranks for the client's target terms — these two lists frequently don't match, and the mismatch is itself useful (it usually means the client is competing against the wrong mental model of their market).
- For each: pulled from Ahrefs Site Explorer (or the Semrush equivalent) — Domain Rating, organic traffic, organic keyword count, top pages by traffic. From Ahrefs Site Explorer's **Content Gap** tool specifically: keywords the competitor(s) rank for that the client doesn't.

**If Ahrefs is connected**, use `site-explorer-organic-competitors` to verify the "who actually ranks for the client's target terms" list — this is the tool that catches the client-assumed-competitor mismatch. Pull `site-explorer-domain-rating`, `site-explorer-metrics`, and `site-explorer-top-pages` for each real competitor. There's no single MCP call matching the classic Content Gap UI feature — pull `site-explorer-organic-keywords` for the client and for each competitor separately and diff them yourself for the "keywords they rank for that the client doesn't" list.

**Prompt pattern:**
```
Here is [Client]'s foundation document: [paste].
Here is raw competitor data for [3-5 competitors] pulled from [Ahrefs/Semrush]
on [date]: [paste — include the Content Gap export if available].

For each competitor: assess their market position, SEO strengths, SEO
weaknesses, and content gaps versus [Client]. Then produce a "Content
Opportunity Matrix" grouping findings into High Opportunity (low
competition + high value), Medium Opportunity, and Long-Term Play.
Ground every claim in the data provided — flag anything you can't
support from the data rather than guessing.
```

## Phase 7/8 — Keyword Lists (site keywords + local/GMB keywords)

**What it produces:** a clustered keyword list (roughly 100 site keywords, 40 local/GMB-specific keywords is the source document's scale — adjust to what the client's market actually supports) with volume, difficulty, a classified search intent, a Keyword Opportunity Score, and a target page assigned to each.

**Required inputs:** a raw keyword export from Ahrefs Keywords Explorer (seeded from terms in the questionnaire, then expanded using the tool's related-terms/questions/also-rank-for features) or the Semrush equivalent, including volume and difficulty columns exactly as exported.

**If Ahrefs is connected**, do this live instead of exporting a CSV: `keywords-explorer-overview` for the seed terms from the questionnaire, then `keywords-explorer-related-terms`, `keywords-explorer-matching-terms`, and `keywords-explorer-search-suggestions` to actually do the expansion the paragraph below asks for — these are the tool-level equivalents of "also rank for" and "having same terms." `keywords-explorer-volume-by-country` matters if the client serves a specific region rather than a whole country's volume number.

**Score and classify as you cluster, not as a pass afterward.** Read `references/keyword-scoring.md` before building the list — it defines the four-bucket Search Intent Classification and the four-component Keyword Opportunity Score (volume, inverted difficulty, position gap, business value from the questionnaire's `seo_profitable_services`/`seo_keywords`). Both get assigned to every keyword at the same time it's clustered, using the same pull, not derived later from the finished list.

**Prompt pattern:**
```
Here is [Client]'s foundation document: [paste].
Here is the raw keyword export from [Ahrefs/Semrush], [N] keywords with
volume, difficulty, and current position: [paste].
Here is [Client]'s stated most-profitable services and self-named
keywords from the questionnaire: [paste].

Cluster these into thematic groups relevant to [Client]'s services. For
each keyword: classify search intent (informational / commercial
investigation / transactional / local-navigational) per
keyword-scoring.md's definitions, compute a Keyword Opportunity Score
(0-100) per keyword-scoring.md's formula, and assign a target page type
(existing page, new service page, new location page, blog post) that
matches the classified intent. Do not alter or estimate volume or
difficulty figures — use exactly what's in the export. If a keyword's
intent is genuinely ambiguous, say so rather than forcing a category.
Sort the final list by Keyword Opportunity Score, highest first.
```

## The one step that determines everything downstream

Keyword research quality here determines the quality of Phase 9 (page architecture), Phase 10 (internal linking), Phase 13 (blog topics), Phase 14 (the content calendar), and eventually Phase 20 (the results retrospective that checks whether any of this actually worked) — all are direct derivations of the keyword clusters and the scores assigned to them. A shallow keyword export produces a shallow architecture no matter how good the prompting is afterward, and a sloppy or skipped score produces a plan that prioritizes by gut feel while looking like it prioritized by data. Spend real time on the Ahrefs side before moving on: seed with the questionnaire's stated terms, then genuinely explore the tool's "also rank for" and "having same terms" suggestion features rather than stopping at the first export.
