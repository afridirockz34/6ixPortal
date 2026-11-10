# Audit Methodology: Categories, Ahrefs Tool Mapping, and Severity Scoring

Work through every category below for every audit — don't skip one because it looks fine without checking; a checked-and-healthy category is a real (and useful) line in the scorecard. Call `doc` on a tool before its first use in a session; the exact parameters live there, not here. Where the mapping below says "diff yourself," Ahrefs' MCP surface doesn't expose a single call for the classic UI feature (e.g. "Content Gap") — pull both sides and compare, rather than assuming a shortcut tool exists.

**Before using any Ahrefs figure about the client's own domain, check `integrations` on their profile.** Categories 1 and 3 below are about their site, and where GA4 or Search Console is connected those are measured facts rather than estimates — their own data replaces the Ahrefs figure and the report says so. Categories 2, 4, 6 and 7 are about the world outside their domain, where Ahrefs is the only option and remains correct. `references/measured-vs-estimated.md` has the division in full; the short version is **their own domain from their own accounts, everything else from Ahrefs**.

## 1. Domain authority & visibility baseline

**Checks:** Domain Rating, organic traffic estimate, organic keyword count, keywords in positions 1-3, trend direction over the last 6-12 months (growing/flat/declining).

**Tools:** `site-explorer-domain-rating`, `site-explorer-metrics`, `site-explorer-metrics-history` (for the trend), `site-explorer-organic-keywords` (for the position breakdown).

**Where the client's own accounts are connected, they replace the estimates here.** Domain Rating is Ahrefs' own metric and stays Ahrefs either way, but with GA4 the organic traffic figure is their measured sessions, and with Search Console the position breakdown, impressions and clicks are what Google actually served rather than what a crawler sampled. Say which, in the sentence. "Their analytics show 1,120 organic visits last month" is a different and much stronger claim than "Ahrefs estimates around 430".

**Why it matters to a prospect:** this is the one-paragraph "where you stand" number that frames everything after it. A flat-or-declining trend while competitors grow is one of the strongest motivators in the whole report — lead with it if it's true.

## 2. Backlink profile quality

**Checks:** referring domain count and quality (DR distribution of referring domains, not just the count), broken backlinks (links pointing at pages that now 404 or redirect incorrectly — lost authority that's recoverable), anchor text patterns (over-optimized or spammy anchor concentration is a real risk signal, not just a technical curiosity).

**Tools:** `site-explorer-referring-domains`, `site-explorer-backlinks-stats`, `site-explorer-broken-backlinks`, `site-explorer-anchors`.

**Why it matters:** broken backlinks are a uniquely good finding for this report — they're unambiguous, quantifiable ("you have N links from sites with DR 30+ pointing at pages that no longer exist"), and the fix is clearly a service-engagement item (redirect mapping), not something the prospect can read a blog post and do themselves in ten minutes.

## 3. Organic keyword visibility & missed opportunity

**Checks:** which of the client's actual priority services/areas have no meaningful ranking at all; keywords sitting on page 2 (positions 11-20) that are close to a page-1 breakthrough; keyword cannibalization (multiple client pages competing for the same term).

**Tools:** `site-explorer-organic-keywords` (filter/sort by position to find the page-2 cluster), `keywords-explorer-overview` for volume/difficulty context on anything the questionnaire says matters that doesn't show up in the client's own ranked list at all.

**With Search Console connected, use its query list instead for what they rank for.** Ahrefs reports what it crawled; Search Console reports every query Google actually served them for, which is a larger and more accurate set, and it carries impressions, clicks, CTR and position per query. Three findings come out of it that nothing external can produce: queries they rank well for but nobody clicks (a titles-and-snippets problem, visible as high impressions with near-zero CTR); the real page-2 cluster at positions 11-20; and the gap between what they told us they want to be found for and what they are actually being found for — which is often the single most useful line in the report. Keep `keywords-explorer-overview` for volume and difficulty on terms they are *absent* from, which Search Console cannot show by definition.

**Why it matters:** "you rank for almost nothing in your actual highest-value service" lands harder than a generic "your SEO needs work."

## 4. Competitor gap

**Checks:** who actually outranks the client for their priority terms (verify against the questionnaire's assumed competitor list — the mismatch is itself a finding), and which keywords those competitors rank for that the client doesn't at all.

**Tools:** `site-explorer-organic-competitors` (finds who really competes, which may differ from who the client thinks competes), then `site-explorer-organic-keywords` for the client and for each real competitor, diffed yourself for the keywords-they-have-that-client-doesn't list (no single "content gap" call exists in the MCP tool set — this is the diff-yourself case).

**Why it matters:** naming a specific competitor by name, with a specific keyword list they own and the client doesn't, is far more concrete than an abstract "the market is competitive" line.

## 5. Technical / on-page issues

**Checks:** broken internal/external links, redirect chains, missing or duplicate title tags, missing or duplicate meta descriptions, missing or duplicate H1s, missing image alt text, slow pages / Core Web Vitals failures, crawlability/indexation blocks (robots.txt or meta-robots issues, orphaned pages).

**Tools:** `site-audit-projects` (check whether a Site Audit project already exists for this domain; if not, one needs to be created and crawled before this category can be filled — flag the wait rather than guessing), `site-audit-issues` (the actual issue list, by category and severity as Ahrefs itself scores them), `site-audit-page-explorer` and `site-audit-page-content` for digging into specific flagged pages (e.g., which exact pages are missing alt text or a meta description).

**Why it matters:** this is the category a prospect can partly verify themselves by looking at their own site, which makes it credible — but the full count ("38 pages missing a meta description, including your 6 highest-traffic pages") is something only a real crawl surfaces, which is the point.

**The fuller checklist.** Ahrefs' Site Audit covers most of this directly; a few sub-checks need a direct look at the live site (reading `robots.txt`, viewing page source, or a browser check) since they're outside what Site Audit reports. Work through all of it — don't stop at whatever Site Audit happens to surface first:

- *Crawlability* (Ahrefs): robots.txt exists and isn't blocking important paths; XML sitemap present and valid; noindex tags intentional vs. accidental; crawl depth (priority pages within ~3 clicks of the homepage); orphaned pages.
- *Indexability* (Ahrefs): canonical tags self-referencing with no noindex conflicts; duplicate/near-duplicate content; thin pages; index bloat.
- *Security* (direct site check — not in Site Audit): HTTPS enforced with a valid certificate and no mixed content; basic security headers present (HSTS, X-Frame-Options, Content-Security-Policy). A missing security header is a minor finding on its own, not a reason to lead with it — don't oversell this sub-check.
- *URL structure* (Ahrefs / direct check): clean, descriptive URLs; no redirect chains (more than one hop); consistent trailing-slash usage.
- *Mobile* (direct check): responsive layout, no horizontal scroll, readable text without zooming, equivalent content and titles/meta on mobile vs. desktop (a real but easy-to-miss gap — some sites serve a stripped-down mobile experience).
- *Core Web Vitals*: LCP ≤2.5s, INP ≤200ms, CLS ≤0.1, on real-user field data where available rather than a single lab run — a one-off lab score can be noisy. **Measure these with `scripts/fetch_speed.py`**, which runs Google PageSpeed Insights and returns the `speed` block the report renders as its own section. PageSpeed rather than our own timing run because pagespeed.web.dev is where the prospect will go to check, and a number of ours that disagrees with theirs is worse than no number. It reports real Chrome user data where the site has enough traffic for Google to hold any, and a lab run where it does not — the report says which, because they are different claims.
- *Bounce rate* lives in the client's own Google Analytics. For a prospect we do not have it, and the speed section says so and names it as an answer that would sharpen the audit. Do not substitute a plausible figure, an industry average, or a "typical" rate: it is the single easiest thing in the whole report for a prospect to catch us inventing.
- *Structured data*: JSON-LD present and valid against Google's currently-supported types — cross-check anything found or recommended against `seo-strategy-builder/references/fact-check-registry.md` before writing a finding about schema; several once-standard types are dead or restricted (see that file).
- *JavaScript rendering*: whether priority content is present in the raw HTML or requires JS to appear — a real indexing risk for JS-heavy sites, and worth a specific mention when it affects a priority page.

**Before attributing any ranking or traffic trend to a specific cause** (a Google algorithm update, a competitor's action, a technical fix) **in this category or anywhere else in the report**: check it against a dated, confirmed source of Google algorithm updates (Google's own Search Status Dashboard / Search Central blog) for that time window rather than assuming correlation is causation. A date overlap is a hypothesis to mention as a hypothesis, never stated as proven cause — this is exactly the kind of overreach that makes an otherwise solid audit easy for a skeptical prospect to dismiss.

## 6. Content gaps

**Checks:** priority service/area pages that don't exist yet at all, or exist but are thin relative to what's actually ranking for that term.

**Tools:** `site-explorer-top-pages` (what the client's best pages actually are, and whether they match the priority services from the questionnaire), `keywords-explorer-related-terms` / `keywords-explorer-matching-terms` for what a real content footprint for a priority term looks like versus what the client has.

**Why it matters:** ties the technical findings back to a business reality — "you don't have a page that could rank for your most profitable service" is a content-production pitch, not just a technical one.

## 7. AI-visibility (optional, when relevant to the client's market)

**Checks:** whether the brand is being cited/mentioned at all in AI-generated answers, and whether named competitors are, for the client's priority terms.

**Tools:** `brand-radar-mentions-overview`, `brand-radar-citations-overview-entities`, `brand-radar-sov-overview` (share of voice vs. named competitors).

**Why it matters:** genuinely new, verifiable data most prospects haven't seen about themselves — but route any claim about *how* to fix AI visibility through `seo-strategy-builder/references/fact-check-registry.md` first. Several once-standard tactics in this exact space (FAQPage schema, llms.txt, schema-as-citation-driver) are flagged there as dead or overstated; this category is the single easiest place to accidentally sell a prospect on something that no longer works.

## Severity rubric

Score each real finding, not each category — a category can contain findings at different severities.

| Severity | Definition | Example |
|---|---|---|
| **Critical** | Actively losing ranking/traffic/credibility right now, or a policy risk. Fixing it is close to table stakes before anything else helps. | Priority service page returns a 404 or redirect loop; dozens of referring domains DR 30+ pointing at dead pages; a GMB or review-policy violation already in place. |
| **High** | Clearly suppressing performance for a priority term/page, quantifiable, but not actively breaking anything. | Core priority keyword stuck at position 11-20 for 6+ months; highest-traffic pages missing meta descriptions/titles; a named real competitor ranks for 20+ keywords the client has zero visibility on. |
| **Medium** | A real gap but lower blast radius — a secondary page or term, not the client's top priority. | Missing alt text on a non-priority page; a minor content gap on a lower-volume term. |
| **Low** | Technically a finding, worth noting for completeness, not worth leading with. | A handful of low-authority broken backlinks; minor anchor text variance. |

A report that's all "Critical" findings reads as implausible (and undermines credibility); a report with no Critical or High findings at all probably means a category wasn't actually checked closely enough — re-check before concluding a domain genuinely has nothing significant wrong.

## Scoring: the SEO Health Score (0-100)

Alongside the per-finding severity ratings, compute one aggregate score for the executive summary — a single number is one of the most persuasive things in the whole report ("your site scores 41/100"), and the scorecard table alone doesn't give a skimming reader that in one glance.

Score each of the 7 categories above 0-100 on how healthy it is (100 = no real findings; subtract for each finding, roughly 25-35 points for a Critical, 10-20 for a High, 3-8 for a Medium, 1-3 for a Low — these are starting guides, not a rigid formula; a category with one severe Critical finding and nothing else should land low, not be averaged up by an otherwise-clean category). Then combine with these weights:

| Category | Weight |
|---|---|
| Organic keyword visibility & missed opportunity | 20% |
| Technical / on-page issues | 20% |
| Domain authority & visibility baseline | 15% |
| Backlink profile quality | 15% |
| Competitor gap | 15% |
| Content gaps | 10% |
| AI-visibility | 5% |

If AI-visibility wasn't run (not relevant to this client's market, or Ahrefs Brand Radar unavailable), redistribute its 5% proportionally across the other six rather than silently scoring it 0 — a category that wasn't checked should never drag the score down as if it failed. Say in the report which categories fed the score.

Report the score as "SEO Health Score: XX/100" at the top of the Executive Summary, and show the category breakdown that produced it in the Scorecard (see `report-template.md`). Score only what was actually checked — never publish a score built partly on a guess.

## Sources

The technical-checklist expansion and the scoring-weight structure in this file were adapted, with attribution, from the open-source [claude-seo](https://github.com/AgriciDaniel/claude-seo) project by Agrici Daniel (MIT licensed) — specifically its `seo-technical` and `seo-audit` skills. Re-verify anything date-sensitive against current documentation before relying on it; this file inherits the same staleness risk as `seo-strategy-builder/references/fact-check-registry.md`.
