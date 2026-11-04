# Phase 9 — Page Architecture, and Phase 10 — Internal Linking

Both are derived phases — strong Claude territory once the keyword clusters exist, because the inputs are already structured data rather than raw research.

## Phase 9 — Page Architecture

**What it produces:** the URL map — which pages get built, what they're titled, what URL slug they use, and which keyword cluster each one targets.

**Required inputs:** the *scored and intent-classified* keyword clusters from Phase 7/8 (not a raw list — see `references/keyword-scoring.md`), and the foundation document's service and service-area lists (so the architecture matches how the business actually thinks about itself).

**Prompt pattern:**
```
Here is [Client]'s foundation document: [paste].
Here are the scored, intent-classified keyword clusters from Phase 7/8,
with each keyword's Keyword Opportunity Score: [paste].

Propose a page architecture: for each proposed page, its type (service
page, location page, audience page, blog post, pillar page — matched to
the classified intent of its primary keyword(s) per keyword-scoring.md's
intent-to-page-type table), a working title, a URL slug, and which
keyword cluster(s) it targets. Order the page list by the highest
Keyword Opportunity Score among each page's targeted keywords, so it's
visible which pages should get built first if architecture is phased.
Group service and location combinations sensibly rather than creating a
separate page for every possible combination.
```

**The failure mode to watch for:** AI-generated architecture proposals tend to over-fragment — a separate thin page for every city × service combination, most of which would never earn enough search volume to justify a dedicated page. A human should sanity-check the proposed page count against realistic content production capacity and realistic per-page search volume (a page targeting a keyword with near-zero monthly volume usually shouldn't exist as a standalone page — fold it into a broader page instead). This is exactly the kind of judgment call the model shouldn't make unsupervised.

## Phase 10 — Internal Linking

**What it produces:** the site's linking structure — which page types link to which others (the "silo" structure), anchor text rules, and a minimum-links-per-page-type table.

**Required inputs:** the finished page architecture from Phase 9.

**Prompt pattern:**
```
Here is the page architecture from Phase 9, including each page's
Keyword Opportunity Score: [paste].

Produce an internal linking plan: which page types should link to which
others (a silo/hub structure), anchor text guidelines (descriptive,
varied, never the exact same anchor text repeated across many pages),
and a minimum internal links per page type table. Weight the minimums
toward the highest-scored pages — they should end up with more inbound
internal links than a low-scored page, not an equal share. Flag any page
in the architecture that would end up with no inbound internal links
under this plan — an orphaned page is a real problem, not a minor one.
```

This is genuinely one of the more mechanical phases — once the architecture exists, the linking plan mostly follows from it directly. The one piece of judgment is making sure link equity actually flows toward the pages the Keyword Opportunity Score says matter most, rather than distributing links evenly out of convenience.
