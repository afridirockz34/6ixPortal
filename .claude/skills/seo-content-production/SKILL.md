---
name: "seo-content-production"
description: Draft on-brand, structurally-correct SEO content (blog posts, service pages, location pages, audience pages) for a specific client, grounded in that client's own profile rather than generic marketing voice. Use whenever asked to write a blog post, service page, location page, or landing page that needs to match an established brand voice, hit SEO structure requirements (headings, FAQ, internal links, word count), or be produced consistently across many pages for one business. Also use to build a new client's content profile from a questionnaire before drafting starts. Especially relevant for YMYL/regulated clients (law firms, medical, financial) — the bundled pack covers legal terminology and compliance flags. Trigger on "write a service page for [client] about [topic]", "draft a blog post targeting [keyword]", "build out location pages for [cities]", "set up a content profile for a new client", or "does this draft match our style guide."
---

# SEO Content Production

## Why this exists

Generic AI-written marketing copy is easy to spot and doesn't rank well, because it doesn't answer the two things that actually matter: does it sound like *this* business, and does it satisfy what the search engine and the reader both need from *this specific page type*. Both of those come from context the model doesn't have by default — they have to be supplied once, in a durable form, and then reused every time.

That's what this skill is. It is not a content generator that produces generic SEO copy on demand. It's a way of grounding every draft in one client's actual facts, actual voice, and actual structural rules, so that after the first setup, producing the fiftieth page is as reliable as producing the first.

This skill assumes a signed client with a profile already built. For a prospect who hasn't signed on yet, `seo-audit-report` is the companion skill that produces the pre-sales evidence-backed weakness report instead. For a session working through the full client lifecycle in one place, `seo-client-pipeline` orchestrates intake through implementation and ongoing monitoring, routing to this skill for its Stage 3 content work alongside `seo-strategy-builder`.

## The model

One **client profile** (a single file) plus a small set of **page templates** produces every piece of content for that client. The profile is the expensive, one-time investment — real facts, real credentials, real voice decisions, usually developed with the client directly. The templates are reusable across every client. A draft is always: profile + template + this piece's specific topic and keyword.

```
client profile  ─┬─>  blog post template   ─┐
(voice, facts,    ├─>  service page template ├─> draft, grounded in the client's
 rules, YMYL       ├─>  location page template│   real facts and real voice
 status)           └─>  audience page template┘
```

## Setting up a new client

Before drafting anything, a client needs a profile. If one doesn't exist yet, build it first.

Read `references/client-profile-template.md` for the exact structure, then fill it from whatever the client has already supplied. That comes from one of three places, and **all three work**:

1. **The 6ix Developers portal.** If the portal connector is in this session (`list_clients`, `get_client_profile`), search for the client by business name and pull their profile. Its `questionnaire.strategy` block is most of what this file needs already answered: `brand` gives you the story, ideal client, pain points and differentiation for the voice sections; `team` gives credentials for E-E-A-T; `compliance` gives the regulatory constraints; `onpage` gives their own keywords and target market (the signup answers are under `questionnaire.audit`). If `stage` is `awaiting_strategy_answers`, those sections may still be empty — mark what's missing rather than inventing it. Check `list_client_artifacts` too — a profile may already exist from an earlier session.
2. **A document they filled in.** Many clients would rather complete a Word document over email than use a portal — read it exactly the same way, whether it's `.docx`, a PDF, a pasted email, a call transcript or an older brand document. It doesn't have to match the questionnaire's wording or order. If the portal connector is available, offer to file their answers back with `update_client_profile` so the next piece of content doesn't start from an inbox; `seo-strategy-builder/references/questionnaire-fields.md` maps each question to its key.
3. **Neither.** Build what you can and mark the unknown sections `[NEEDS CLIENT INPUT]` rather than guessing. Then tell the user exactly which answers would unblock them. A profile with honest gaps is safe; a profile with invented voice decisions is not.

See `seo-strategy-builder/references/client-context.md` for the full handling of all three routes. Ask for what's missing rather than inventing it; a wrong fact in this file corrupts every piece of content built on it afterward. If the client is in a regulated profession (law, medicine, finance, and similar), also read `references/ymyl-legal-pack.md` before drafting the profile's voice and terminology sections — regulated professions have real constraints on what marketing claims are allowed, and getting this wrong in the profile means every downstream page inherits the mistake.

Save the completed profile somewhere durable — the user's project files, not just this conversation — since it gets referenced on every future request for this client. `assets/nr-lawyers-client-profile.md` is a complete worked example (a Toronto criminal defence firm) — read it to see the level of specificity a good profile has. It is an example to learn the shape from, not a template to copy facts out of for an unrelated client.

## Drafting a piece of content

1. **Load the client's profile.** If it's not already in context, ask for it or read it from wherever it's stored.
2. **Pick the content type** — blog post, service page, location page, or audience page — and read that template's section in `references/content-templates.md`. Each template specifies section structure, word-count targets, minimum internal links, minimum FAQ count, and meta-element rules.
3. **Get the specifics for this piece**: target keyword, target URL, and (for location/audience pages) the city or audience segment. If these weren't given, ask — don't guess a keyword's search volume or a city's court name. If Ahrefs is connected (`mcp__Ahrefs__*` tools in the session's tool list), spot-check the target keyword's current volume/difficulty via `keywords-explorer-overview` before drafting rather than relying solely on whatever number `seo-strategy-builder` assigned it — campaigns run over months and keyword data drifts.

   **When the ask is "what should we write next" rather than a named piece**, and the portal connector is in this session, `check_performance` is a better answer than working down the calendar in order: it shows which metrics are behind the targets the strategy set, and the content gap usually sits under the one that's lagging. Treat it as a tie-breaker on sequence, not a licence to rewrite the calendar — and a metric marked `no_projection` simply never had a target, so it isn't evidence of anything.
4. **Draft against the template**, applying the profile's voice pillars, terminology rules, and banned-phrase list throughout. The template tells you *what* goes in each section; the profile tells you *how it sounds*.
5. **Run the quality check** before presenting the draft: `python scripts/check_draft.py <draft_file> <profile_file> <content_type>`. It checks word count against the template's range, internal link count, FAQ count, heading structure, and flags any banned phrases from the profile. Fix what it flags before calling the draft done — this catches the mechanical stuff so the conversation with the user can focus on substance.
6. **State what's grounded vs. generated.** Facts, credentials, and outcomes come from the profile — never invent a case result, a statistic, or a credential that isn't in it. If the piece needs a fact the profile doesn't have (a specific court name for a new city, a recent case outcome), say so and ask, rather than filling the gap with something plausible-sounding.

   This extends to performance figures. A client's own numbers from the portal are tempting material for a case study or a credibility line in copy — but each one carries a `source`, and an `estimate` is a modelled placeholder the dashboard shows before a data provider is connected, not a measurement. Only a `live` or `advisor` figure can go into published copy, and a published claim about results needs the client's sign-off regardless of where the number came from. See `seo-strategy-builder/references/performance-tracking.md`.

## Why the quality check matters

A style guide that lives only in your memory of the conversation gets forgotten by page thirty. A script that mechanically checks word count, link count, FAQ count, and banned phrases doesn't drift, and it means the human reviewer's attention goes to the parts that actually need judgment — does this sound right, is the claim defensible, is the case example true — instead of counting words.

## Staying current

The `references/ymyl-legal-pack.md` file's schema and AI-search guidance is a snapshot and will go stale — platform behavior around structured data and AI citation changes faster than almost anything else in SEO. Before recommending or implementing any schema type or AI-visibility tactic for a client, treat the pack as a starting list of things to re-verify, not settled fact. If you find something in it has changed, update the file — a stale domain pack that never gets corrected becomes exactly the kind of outdated advice this whole system exists to avoid repeating.

## Reference files

- `seo-strategy-builder/references/client-context.md` — how to get the client's answers from the portal, from a document they filled in, or from neither
- `seo-strategy-builder/references/performance-tracking.md` — using a performance check to decide what to write next, and which figures may appear in published copy
- `references/client-profile-template.md` — the structure for a new client profile: business identity, voice pillars, terminology rules, target audiences, YMYL status
- `references/content-templates.md` — the four page-type templates with section structure, word counts, and requirements
- `references/ymyl-legal-pack.md` — domain pack for regulated-profession clients: compliance flags, current schema guidance, terminology, what NOT to claim
- `assets/nr-lawyers-client-profile.md` — a complete worked example profile
- `scripts/check_draft.py` — mechanical quality check for word count, links, FAQ count, headings, banned phrases
