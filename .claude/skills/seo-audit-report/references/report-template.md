# Report Template and the "Show the Wound, Not the Stitches" Pattern

## Structure

1. **Cover / header** — client name, domain, audit date, prepared-by line. This is a sales document representing the agency; it should look like one.
2. **Executive summary** (3-5 sentences). Open with the line "SEO Health Score: XX/100" (see `audit-methodology.md`'s scoring section) before anything else — it's the single most persuasive, most skimmable number in the whole report. Then lead with the single biggest finding, not a generic opener. State plainly, in business terms, what it's costing them (lost visibility, lost leads, lost ground to a named competitor) — save the detailed numbers for the sections below.
3. **Scorecard** — one table, one row per category from `audit-methodology.md`: category, status (Critical/High/Medium/Low/Healthy), one-line verdict, and that category's own 0-100 sub-score (the input to the overall Health Score). This is the part a skimming reader actually reads in full; make every verdict line stand on its own. If a category wasn't run (and its weight was redistributed per `audit-methodology.md`), say so in that row rather than leaving it blank.
4. **One section per category with a real finding** (skip or compress to a single "healthy" line for categories with nothing significant). Each section: the finding (specific, numbers-backed), why it matters in plain business terms, severity, and a one-sentence teaser of what addressing it involves. See the pattern below for exactly how much to say in that last part.
5. **Competitor callout** — at least one named real competitor, with a specific gap, pulled from the competitor-gap category. This is usually the most persuasive single section; don't bury it.
6. **Closing / what we'd do about it** — names the service (not a generic "contact us for SEO help") that addresses the findings above, and a clear, specific next step (a call, a proposal, a scoped engagement). This is the only place the report should explicitly pitch.

## The pattern: show the wound, not the stitches

Every weakness-category section follows the same shape. The finding and the "why it matters" half are written with full rigor and specificity — that's what makes the report credible. The "what we'd do" half is one sentence, names the category of fix, and stops. It never becomes a how-to.

**Good — specific finding, one-line teaser:**

> **Broken backlinks (High)**
> 47 referring domains — 12 of them DR 40+ — currently link to pages on this domain that return a 404. That's authority the site has already earned and is actively losing because nothing is catching it. A targeted redirect-mapping pass (part of the technical SEO engagement) recovers this without waiting for new links to replace it.

**Bad — same finding, written as a free how-to (never do this):**

> **Broken backlinks (High)**
> 47 referring domains link to 404 pages. To fix this: export the full backlink list from Site Explorer, cross-reference each target URL against current site structure, build a 301 redirect map prioritizing the highest-DR referring domains first, implement via .htaccess or the CMS's redirect manager, then re-crawl in Site Audit to confirm the chain is resolved and monitor Search Console for a pickup in referring-domain-attributed impressions over the following 4-6 weeks.

The bad version isn't wrong — it's just a free deliverable instead of a reason to hire someone. The line to watch for while writing: if a section is explaining *how* to do something rather than *what's wrong and that it's fixable*, it crossed from the pitch into the service.

## A note on tone

Specific and evidence-backed does not mean alarmist. A report that oversells every finding as catastrophic reads as a sales trick, not an audit, and undermines the credible findings along with the weak ones. Calibrate severity honestly using the rubric in `audit-methodology.md` — a Medium finding stated plainly is more persuasive than a Medium finding inflated to sound Critical.
