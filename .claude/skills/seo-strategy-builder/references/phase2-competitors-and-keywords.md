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

**What it produces:** two deliverables in the exact shape
`references/keyword-strategy.md` specifies — about 100 website keywords in ten
**named clusters** of about ten, and about 40 GMB keywords in two tables (one
mapping profile element to keyword focus, one listing the keywords with their
placement). **Read `references/keyword-strategy.md` before starting.** It is
the format contract: the columns, the controlled vocabularies, the cluster
rule, and what to do when the market does not support 100.

The thing it insists on, because this is where our keyword lists have gone
wrong: **a hundred rows sorted by score is not a deliverable.** Every keyword
belongs to a named cluster, and every keyword has a target page. A flat ranked
dump is readable by the person who built it and by nobody else, and the client
cannot see their own site in it.

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

Produce the website keyword table exactly as keyword-strategy.md
specifies: ten named clusters of about ten keywords each, columns
# | Keyword | Intent | Est. MSV | Difficulty | Priority | Target Page.

Name each cluster from [Client]'s own services and market, not from a
template. Classify search intent per keyword-scoring.md. Compute the
Keyword Opportunity Score for every keyword, then band it into Priority
1/2/3 and band difficulty into High/Med/Low per keyword-scoring.md's
fixed mapping — show the bands in the table and keep the computed
scores in the working sheet. Assign every keyword a target page that
agrees with its intent. Put "near me" terms in the GMB list, not this
one.

Do not alter or estimate volume or difficulty figures — use exactly
what's in the export; a keyword with no volume data gets an em dash and
a note. If a keyword's intent is genuinely ambiguous, write Info/Comm
rather than forcing a category. If the market does not support 100
keywords worth pursuing, ship fewer and say how many it supported.
```

Then check the shape before it goes anywhere:

```bash
python scripts/check_keyword_set.py keywords.csv --kind website
python scripts/check_keyword_set.py gmb.csv      --kind gmb
```

It catches the mechanical failures — a missing cluster or target page, a
value outside the controlled vocabulary, a duplicate keyword, wildly uneven
clusters. It cannot tell you these are the right keywords; that is what
reading them is for.

## The one step that determines everything downstream

Keyword research quality here determines the quality of Phase 9 (page architecture), Phase 10 (internal linking), Phase 13 (blog topics), Phase 14 (the content calendar), and eventually Phase 20 (the results retrospective that checks whether any of this actually worked) — all are direct derivations of the keyword clusters and the scores assigned to them. A shallow keyword export produces a shallow architecture no matter how good the prompting is afterward, and a sloppy or skipped score produces a plan that prioritizes by gut feel while looking like it prioritized by data. Spend real time on the Ahrefs side before moving on: seed with the questionnaire's stated terms, then genuinely explore the tool's "also rank for" and "having same terms" suggestion features rather than stopping at the first export.
