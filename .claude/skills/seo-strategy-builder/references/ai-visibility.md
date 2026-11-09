# AI visibility — being the answer, not the tenth link

Shared by `seo-strategy-builder` (Phase 11), `seo-content-production`, and
`seo-audit-report`. Read this before writing a single sentence claiming
anything about how a client appears in AI answers.

AI answers do not rank ten links; they read a handful of sources and write a
reply. A business can rank third for a term and be absent from the answer
most people now read, or rank nowhere and be the source the answer cites.
Those are different facts about the same business, and until recently the
second one was guessed at. Two connectors measure it, and they measure
different things.

## Which tool answers which question

| Question | Tool | Scope |
|---|---|---|
| Is this **brand** mentioned and cited in AI answers, and how does that compare with named competitors over time? | **Ahrefs Brand Radar** (`mcp__Ahrefs__brand-radar-*`) | Brand and domain level, across the web, with history |
| For **this location**, what do AI platforms actually say when somebody asks the questions its customers ask? | **BrightLocal AI Visibility** (`mcp__BrightLocal__*_aiv_*`) | One location, specific prompts, specific platforms |

They are complements, not alternatives. Brand Radar tells you whether a brand
registers at all. BrightLocal AIV tells you what a prospective customer in
that city actually gets shown when they ask. For a local service business the
second is usually the one that lands, because the prompts are the questions
their customers really type.

## Ahrefs Brand Radar

- `brand-radar-mentions-overview` / `brand-radar-mentions-history` — mention
  volume, and whether it is moving.
- `brand-radar-citations-overview-entities` / `brand-radar-cited-domains` —
  who is actually being cited: the client, or named competitors.
- `brand-radar-sov-overview` — share of voice against competitors directly.
- `site-explorer-ai-responses-count` — how often a domain surfaces in AI
  responses at all.

Call the `doc` tool for any Ahrefs tool before its first use, and remember
monetary fields come back in USD cents.

## BrightLocal AI Visibility

An **AI Visibility report** is a saved configuration: a set of prompts run
against a set of platforms (`google_ai_overview`, `google_ai_mode`, `chatgpt`)
for one location, on a schedule. A location has at most one.

1. **`find_aiv_reports(location_id)`** — this is how you answer "does this
   location have AI Visibility set up at all". Report ids are UUID strings,
   not numbers. At the time of writing, **no location in this account has one**,
   so expect an empty list and treat setting one up as a decision to raise with
   the person running the session, not something to do silently.
2. **`get_aiv_report(report_id)`** — the prompts, platforms and competitors
   that report is configured with.
3. **`get_aiv_latest_run` / `get_aiv_report_runs`** — results.
4. **`get_aiv_run_comparison` / `get_aiv_runs_comparison`** — movement between
   runs, which is the number worth reporting. A single run says where they
   stand; two say whether anything we did worked.

`create_aiv_report`, `update_aiv_report` and `run_aiv_report` all change the
account. Confirm first, every time.

**The prompts are the whole report.** A report configured with prompts nobody
would type measures nothing. Write them from the client's own questionnaire —
their services, their cities, the way they describe what they do — not from a
generic template. "Who is the best criminal lawyer in Toronto for a domestic
assault charge" is a prompt one of their clients would actually type; "legal
services" is not.

## The honesty rules, which matter more here than anywhere else

This is the newest and least settled area in the whole skill set, and it is
the easiest place to write something confident and wrong.

1. **Never claim an AI-visibility result you have not pulled.** "You do not
   appear in AI Overviews" without having checked is exactly as unsupported as
   inventing a traffic figure, and more embarrassing, because the client can
   check it in ten seconds on their phone.
2. **AI answers are not stable.** The same prompt returns different answers to
   different people, on different days, in different places. Report what the
   run returned and when it ran. Never present a single observation as "how
   you appear"; present it as what that platform answered on that date.
3. **Absence of a citation is not proof of absence.** A result that shows no
   mention means the prompts that were run did not surface them — which may be
   a finding about the prompts.
4. **Check `fact-check-registry.md` before recommending any tactic.** Several
   once-standard AI-visibility recommendations — schema as a citation driver,
   `llms.txt`, raw citation volume as a goal — have already been shown not to
   work as claimed. This area turns over on a timescale of months. A
   recommendation written from memory is the single most likely thing in this
   skill set to be actively wrong.
5. **Do not promise a citation.** We can make a page more likely to be used as
   a source. Nobody controls what a model writes. The deliverable is the work
   and the measurement, not the placement.

## What this means for content (`seo-content-production`)

Content that gets used as a source and content that ranks overlap, but not
completely. The things that distinguish a source worth citing, and which
belong in a brief:

- **It answers the question directly, early, in a sentence that stands alone.**
  A model lifting a sentence needs one that still makes sense out of context.
  A paragraph that builds to the answer over 200 words gives it nothing to use.
- **It carries something only this business has.** A real figure, a named
  process, a case they handled, a credential. Generic content is interchangeable
  with every other result, so there is no reason to cite this one.
- **It is attributable.** A named author with real credentials on the page,
  which is the same E-E-A-T work the strategy already calls for, and is
  separately worth doing.
- **It is current and says so.** A visible, accurate date.

What does **not** belong in a brief on the strength of AI visibility: keyword
density, `llms.txt`, schema added for citation purposes, or any instruction to
write "for AI". Check `fact-check-registry.md` before adding a sixth item to
the list above.

Where a client has a BrightLocal AIV report with results, read which sources
the platforms actually cited for their prompts, and brief against those — a
real answer about what is being used beats any general principle on this page.
