# Client Profile Template

One file per client. This is what every content request gets grounded in — treat gaps as things to ask about, not things to fill with a plausible guess. A wrong fact here corrupts every page built from it.

Fill every section below. Where information genuinely isn't available yet, write `[NEEDS CLIENT INPUT: specific question]` rather than leaving it blank or inventing an answer.

`house-standards.md` is the specification for the twelve tables here — what
each must contain, the house defaults, and what goes wrong without it. Read it
alongside this file. The test for a finished profile: **a competent writer who
has never met this client could produce a publishable page from this file
alone.**

---

## Business Identity

| Field | Detail |
|---|---|
| Business name | |
| Website | |
| Phone | |
| Address | |
| Business type / industry | |
| Years in operation | |
| YMYL status | Yes/No — legal, medical, financial, safety-related services are YMYL. If yes, read `ymyl-legal-pack.md` (or the equivalent domain pack) before completing the rest of this profile. |
| Languages served | |
| Marketing contact | Who reviews and approves content before publish |

## Core Services

List, ranked by the client's own stated priority (usually profitability or strategic importance — ask, don't assume). For each: what it is, and why it matters to the client's business.

## Credentials and Differentiators

Every claim here needs to be independently verifiable — this is the raw material for E-E-A-T (Experience, Expertise, Authoritativeness, Trust), which search engines and readers both weigh heavily for anything consequential. Named credentials, years of experience, notable outcomes, professional memberships, media mentions, published work. Not "years of experience" as a vague number — the actual number, and what it's years of experience *in*.

## Target Audiences

For each distinct audience the client serves: who they are, what they're anxious about or trying to achieve, and what specifically addresses that. A single "everyone" audience produces generic content; two or three sharply distinct audiences produce content that actually speaks to someone.

## Service Areas

Geographic scope, tiered if relevant (primary market, secondary markets, broader region). Note current SEO status per tier if known — already ranking, or a gap to close.

## Voice Pillars

4-6 pillars, each with: what it means in practice, a real example sentence that could ship as written, and what to avoid. Don't write abstract adjectives ("professional," "friendly") without the example and the avoid column — those are the parts that actually change what gets written. An invented placeholder example makes every page built on this profile vague.

| Pillar | What it means | Example | Avoid |
|---|---|---|---|

## Core Messages

Five numbered messages, each a complete positioning statement **with its proof attached**, written so it can be dropped into a page nearly as-is. Fewer than five and the pages repeat themselves; more and nobody remembers which message a page is making.

> **Message 1 — {name}.** {The claim.} {The proof: the credential, the figure, the named fact that supports it.}

## Terminology — Say This, Not That

Any words or phrases with a required or forbidden form. Common categories: legally/medically precise terms vs. casual equivalents, spelling conventions (regional), neutral vs. loaded language for describing people involved in the client's work.

| Say this | Not that | Why |
|---|---|---|

## Voice Examples — one good, one bad, both explained

Exactly two, at paragraph length, each followed by the reason. This aligns a writer faster than every table above it combined, because it shows the pillars operating together.

**GOOD:**

> {A real paragraph that could ship.}

*Why it works:* {name the specific things — leads with the reader's situation, credentials as facts, checkable proof, clear call to action}

**BAD — avoid:**

> {A real paragraph, ideally lifted from the client's current site.}

*Why it fails:* {name the specific failures — which phrases say nothing, what is missing, why it could be any business in any city}

## Words and Phrases to Avoid Entirely

A literal blocklist, 10-20 entries, enforced by `check_draft.py`. Two kinds of entry belong here and they go in one list, because a writer doesn't need to know which is which to obey it: phrases that are legally or professionally dangerous for this client, and phrases that are simply empty. Ask specifically — clients in regulated fields usually have a list, even if informal.

## Sensitivities

Topics to handle carefully, topics to avoid entirely, anything the client has flagged as off-limits or requiring special care.

## Content Standards

If the client doesn't have established standards yet, `content-templates.md` has reasonable per-page-type defaults — copy those in here rather than leaving this section to be improvised per piece. Once set, this is what `check_draft.py` validates against.

| Content type | Target words | Range | Min internal links | Min FAQ |
|---|---|---|---|---|
| Service page | 2,000 | 1,500-2,500 | 4 | 6 |
| Service hub page | 2,000 | 1,500-2,500 | 4 | 6 |
| Location page | 1,500 | 1,200-1,800 | 3 | 5 |
| Blog post | 1,500 | 1,200-2,000 | 3 | 5 |
| Audience page | 1,500 | 1,200-1,800 | 4 | 5 |
| Resource guide | 2,500 | 2,000-3,000 | 4 | 8 |

The content type string here must match what gets passed to `check_draft.py` exactly — `"Service page"`, not `service_page` — or those checks are skipped and the gate refuses to clear a draft it could not check enough of.

## Claims Policy

**Set by the person who will answer for these pages, not inferred.** This decides how hard the copy pushes. House defaults are shown; the client's answer overrides them, and a regulator's rule overrides both.

| Switch | On / off | What it allows when on |
|---|---|---|
| `superlatives` | on | "best", "top", "leading", "premier", "most experienced" about the client |
| `comparative_claims` | on | Claims positioning the client above competitors |
| `aggregate_outcomes` | on | "hundreds of acquittals", "a documented success rate", volume-of-results phrasing |
| `urgency_language` | on | "act immediately", "the clock is running", "every hour matters" |
| `free_consultation` | on | Promoting a free or no-obligation first consultation |
| `keyword_density` | on | 1-1.5% primary, 0.5-0.75% per secondary, counting natural variations |
| `risk_schema` | on | `AggregateRating`, `HowTo`, `Speakable`, `FAQPage` regardless of current rich-result status |
| `outcome_guarantees` | off | Promising or implying a specific result |

**Past-results statement** (travels with every outcome claim, in the client's own words):

> {e.g. "Past results are not necessarily indicative of future results; outcomes depend on the facts of each case."}

**Regulator constraint, with its rule number** (if any):

> {e.g. "LSO rule 4.2-1 — a lawyer may not claim to be the best or imply superiority that cannot be substantiated. Overrides `superlatives` and `comparative_claims` for this client."}

Three rules apply whatever the switches say: a specific number or credential with no source is written `[VERIFY: what to confirm]` for the approver rather than generated; the past-results statement travels with outcome claims; and `AggregateRating` markup needs the same rating, count and date visible on the page.

## Current SEO Baseline (if known)

Organic traffic, keyword rankings, domain authority, whatever the client or an SEO platform export provides. Useful context, not something to draft content from directly.
