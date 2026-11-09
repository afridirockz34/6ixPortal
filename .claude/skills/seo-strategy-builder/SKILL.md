---
name: "seo-strategy-builder"
description: Build the research-driven phases of a client SEO plan — foundation/brand summary, competitor analysis, scored & intent-classified keyword clustering, page architecture, internal linking, GMB strategy, GEO/AEO strategy, schema plan, content calendar, tooling/tracking, reporting/KPIs, and a recurring retrospective checking outcomes against the targets the strategy committed to — from a client questionnaire plus SEO-platform data (Ahrefs, Semrush). Use for any of these phases individually, the full strategy document/PDF, or a review of how a live strategy is tracking against plan. Companion to seo-content-production (voice, style, drafting). Trigger on "build the SEO strategy for [client]", "create the foundation document", "cluster these keywords", "competitor analysis for [client]", "build a content calendar", "GEO strategy for [client]", "schema plan for [client]", "put together the full SEO plan/PDF", "quarterly retrospective for [client]", or "how is [client] tracking against the plan".
---

# SEO Strategy Builder

## Why this exists

A real SEO strategy document is not one piece of writing — it's a chain of smaller documents, each one feeding the next, built from real data rather than invented from general SEO knowledge. The 100-page agency deliverables clients pay thousands of dollars for are built this exact way: one foundation document, a set of research-driven phases branching off it, and a fact-check discipline that keeps dead or dying SEO advice from getting reproduced deliverable after deliverable.

This skill packages that chain: what each phase needs, where the input comes from, the exact prompt pattern to produce it, and — critically — a live registry of which common SEO/schema/AI-search recommendations are actually still true, so the document this produces doesn't quietly repeat outdated tactics the way most vendor templates do.

This skill covers the **research and strategy** phases. It does not draft blog posts, service pages, or other client-facing content — that's `seo-content-production`, its companion skill. Used together, the two reproduce a complete multi-phase agency SEO strategy document: this skill for everything data-driven and structural, that one for everything voice-and-copy. A third companion, `seo-audit-report`, sits upstream of both — it's for a prospect who hasn't signed on yet, producing the evidence-backed weakness report that's meant to get them to. This skill and `seo-content-production` assume the engagement already exists. A fourth, `seo-client-pipeline`, sits above all three — it orchestrates the whole client lifecycle in one session, from intake through this skill's phases, into implementing the approved strategy on the live site, and the recurring performance checks that can send work back through this skill's Phase 20.

## The model

```
questionnaire + SEO platform exports (Ahrefs/Semrush)
        │
        v
Phase 1 — Foundation document  ─── every later phase references this by name
        │
        ├──> Phase 2 — Competitor analysis  ─┐
        ├──> Phase 7/8 — Keyword clusters,  ─┴──> Phase 9 — Page architecture ──> Phase 10 — Internal linking
        │    scored + intent-classified                                       └──> Phase 13 — Blog topics ──> Phase 14 — Content calendar
        ├──> Phase 5 — SEO strategy & targets
        ├──> Phase 6 — GMB / local strategy
        ├──> Phase 11 — GEO/AEO strategy   (re-research every time, never templated)
        ├──> Phase 12 — Schema plan        (re-research every time, never templated)
        └──> Phase 17/18/19 — Tooling, tracking, reporting/KPIs
                   │
                   v
     approved ──> targets saved to the portal + baseline captured
                   │
                   v
     Phase 20 — Results retrospective (recurring, quarterly default)
                   │
                   └──> feeds revised priorities back into Phase 7/8 and Phase 14
```

Phases 2, 5, 6, 11, and 12 don't depend on each other — they can all be produced in parallel once Phase 1 exists. Nothing here needs to happen in strict numeric order; Phase 1 is the only hard prerequisite. Phase 20 is the one phase that's structurally different from the rest: it doesn't run once at setup, it runs again on a cadence for the life of the engagement, checking the earlier phases against what actually happened and revising them.

## Before starting anything: the two things that must come from real data

Two categories of phase cannot be produced by prompting alone, no matter how good the prompt is — using a plausible-sounding number instead of a real one is the single most common way an AI-assisted strategy document loses credibility:

- **Anything with search volume, difficulty, traffic, or ranking position** (Phase 1's baseline, Phase 2's competitor stats, Phase 7/8's keyword data) must come from an actual SEO platform export (Ahrefs Site Explorer, Keywords Explorer, Content Gap, or the Semrush equivalents). Never estimate or generate a plausible-looking number — if the data isn't provided, the output must say `[NEEDS DATA: metric name]`, not fill in a guess.
- **Anything about current AI-search behavior or schema effectiveness** (Phase 11, Phase 12) moves faster than this skill's own reference files can stay current. Read `references/fact-check-registry.md` first, then verify anything not covered there against a fresh search before writing it into the document — see "Staying current" below.

## If Ahrefs is connected, use it directly instead of asking for manual exports

Check this session's tool list for tools named `mcp__Ahrefs__*` (search for them if they're deferred). If they're there, Ahrefs is connected for this session and every phase below that says "pulled from Ahrefs/Semrush" should be pulled by calling those tools directly — don't ask the client or the person running this for a manual CSV export unless Ahrefs genuinely isn't connected, or the specific data needed isn't exposed by any Ahrefs tool.

A few operating rules that apply everywhere this skill touches Ahrefs:

- Call the `doc` tool for a given Ahrefs tool before calling it for the first time in a session — the MCP server requires this, and the exact parameters live there, not in this skill's reference files.
- Monetary fields (`value`, `org_cost`, `paid_cost`, `traffic_value`) come back in **USD cents** — divide by 100 before writing a dollar figure anywhere.
- When a tool's response carries `render_with` metadata, call that render tool on the data — don't summarize or hand-transcribe raw numbers past it.
- Several Ahrefs tools **create or change something in the live account** — a new Site Audit project, a new tracked rank-tracker project, added competitors or keywords, a deleted keyword list. Treat those as real, persistent actions on the client's paid account, not reads, and confirm with the person running this before taking one — especially before creating a new tracked project just to answer a single phase's question, when a one-off lookup would do.
- If Ahrefs isn't connected: fall back to the manual-export pattern each reference file already describes, and say plainly that the data came from a manual export rather than a live pull.
- There's no single Ahrefs tool matching the classic "Content Gap" UI feature (used in Phase 2) — pull the client's and each competitor's organic keywords separately and diff them, rather than assuming a one-call shortcut exists.

## Where the client's answers come from

Phase 1 is fed by the client questionnaire, and everything downstream inherits
whatever Phase 1 got wrong — so this is worth getting right before any phase
starts. Their answers can arrive three ways, and **all three are workable**:

1. **The 6ix Developers portal.** If the portal connector is in this session
   (`list_clients`, `get_client_profile`), search for them by business name and
   pull the profile. There are two questionnaires on it, answered at different
   points: `questionnaire.audit` (collected at signup — keywords, market,
   competitors, regulatory status) and `questionnaire.strategy` (everything
   else, and what this skill actually runs on). Read `stage` to see where they
   are. If it says `awaiting_strategy_answers`, the strategy questionnaire has
   been issued but not finished — say what's outstanding rather than building
   on gaps. If it hasn't been issued at all, `issue_strategy_questionnaire`
   returns a link to send them.

   **Answered and handed in are different facts.** `stage` goes to
   `ready_for_strategy` as soon as every required question has a value, but the
   client presses Submit separately, and `questionnaire.strategy.submitted`
   (with `submitted_at`) is the one that says they consider themselves done.
   Complete but unsubmitted usually means they are still editing — their last
   few answers may be half-finished thoughts rather than final ones. It is not
   a reason to refuse to start, but it is a reason to say so before building on
   those answers, and to re-read the profile before the strategy is finalised.
2. **A document they filled in.** Plenty of clients would rather complete a Word
   document over email than log into a portal. Read it the same way — `.docx`,
   PDF, a pasted email, a call transcript, or an older brand document covering
   the same ground. It doesn't need to match the official questionnaire's
   wording. If the portal connector is available, offer to file their answers
   with `update_client_profile` so the next phase doesn't start from an inbox.
3. **Neither.** Build Phase 1 from what's externally measurable, state in the
   document that it reflects what's visible from outside, and name the two or
   three unanswered questions that would most change the strategy.

Read `references/client-context.md` for the full handling of all three, and
`references/questionnaire-fields.md` for the key each answer maps to when
filing a returned document back to the portal, and
`assets/blank-strategy-questionnaire.md` if they'd rather fill one in by email.

Never invent a target market, a competitor set, or a compliance position to
fill a gap. A wrong fact in Phase 1 is reproduced by every phase after it.

## Building the document, phase by phase

1. **Confirm Phase 1 exists first.** If the client doesn't have a foundation document yet, build it — read `references/phase1-foundation.md` for the exact structure and prompt pattern, fed by the client's answers (see "Where the client's answers come from" above — portal, returned document, or neither) and a baseline SEO-platform pull. Every phase after this one opens by restating "here is [Client]'s foundation document" — that's what keeps nineteen phases coherent instead of disconnected.
2. **Pick the phase(s) being requested** and read the matching reference file — each one has the phase's required inputs, where they come from, and the exact prompt pattern:
   - `references/phase2-competitors-and-keywords.md` — Competitor Analysis, Keyword Lists (site + local/GMB)
   - `references/phase5-6-strategy-and-gmb.md` — SEO Strategy & targets, GMB/local strategy
   - `references/phase9-10-architecture-linking.md` — Page Architecture, Internal Linking
   - `references/phase11-12-geo-schema.md` — GEO/AEO Strategy, Schema Plan
   - `references/phase13-14-content-calendar.md` — Blog Topics, Content Calendar
   - `references/phase17-18-19-tools-tracking-reporting.md` — Tech Stack Integration, Local Tracking, Reporting/KPIs
   - `references/phase20-results-retrospective.md` — Results Retrospective (recurring — only once there's something live to check against)
3. **Score and classify every keyword, not just list it.** Read `references/keyword-scoring.md` before Phase 7/8 and apply it there — a Keyword Opportunity Score (0-100, weighted on volume, difficulty, position gap, and the client's own stated business value) and a Search Intent Classification for every keyword. Carry both through Phase 9, 10, 13, and 14 rather than re-deriving priority order by feel in each one; this is what keeps "build a strategy" from meaning "produce a plausible-looking document" instead of "produce a prioritized, defensible plan."
4. **Ground every claim in supplied data**, and flag rather than invent whatever's missing. A gap flagged as `[NEEDS CLIENT INPUT: ...]` or `[NEEDS DATA: ...]` is a normal, useful output — a filled-in guess dressed up as fact is not.
5. **Run the fact-check before finalizing any phase touching GEO/AEO, schema, GMB, citations, or reporting metrics** — these are exactly the areas where vendor templates go stale fastest. `references/fact-check-registry.md` is a snapshot; treat it as a starting checklist to re-verify, not a substitute for checking current documentation.
6. **Assemble the finished phases into one document** once all requested phases are built — see `references/document-assembly.md` for how the pieces fit into a single ordered deliverable (and where seo-content-production's phases slot in if the full 19-phase equivalent is being built). Phase 20 is never part of this initial assembly — see that file for why.
7. **Run the completeness and fact-check scan** before calling the document done: `python scripts/check_strategy_doc.py <document_file>`. It flags unresolved `[NEEDS...]` placeholders and any dead-advice language that snuck in from the fact-check registry — the same mechanical safety net `seo-content-production`'s `check_draft.py` provides for content, applied to strategy claims instead.
8. **Once the strategy is approved, record its targets and take a baseline.** A strategy whose targets live only in a PDF can't be checked against later — the review two months on can only say the number moved, not whether it moved as far as we said it would, and Phase 20 then has nothing to retrospect against but whatever someone remembers to paste in. If the portal connector is in this session: `save_client_artifact` the document, `save_strategy_projections` for the targets Phase 5 actually commits to, and `capture_performance_snapshot` so the baseline is where they stood the day the work started. Read `references/performance-tracking.md` first — it covers the metric keys, and the rule that only targets the strategy argues for in writing get saved. A target invented to fill a row makes every later comparison meaningless.
9. **Once content is live, run Phase 20 on a recurring cadence** (quarterly by default) to check whether the scored priorities actually performed, and feed what's learned back into the next period's Phase 7/8 and Phase 14. A strategy that's never checked against results is a one-shot guess no matter how well-sourced its first draft was.

## When the request is a check rather than a new document

"How are they tracking", a retrospective, a monthly report — none of these is a
request to rebuild the strategy. They're reads against what the last one
committed to, and they run off stored targets rather than pasted ones.

Start with **`check_performance`** — it returns current, baseline, previous and
projected for every tracked metric in one call, with a status per metric. That
covers what Phase 20 and Phase 19 both otherwise ask to be pasted in: the
targets Phase 5 set and the prior check to compare against. Then pull the live
Ahrefs side for the ranking and keyword detail the portal doesn't hold, and
write it up in the format Phase 19 established.

Which depth to run:

- **A full retrospective** — `references/phase20-results-retrospective.md`.
  That's where the judgment lives: which keyword bets paid off, which stalled,
  and what the next period's priorities should be. Quarterly by default.
- **A lighter pulse check**, if `seo-client-pipeline` is driving the session —
  that skill runs the biweekly check and monthly report and escalates into
  Phase 20 when something warrants it. Don't run a full retrospective for a
  routine biweekly.

`references/performance-tracking.md` covers how to read the result. Three
things to get right, because each is a way a report goes wrong:

- A metric reporting `no_projection` means **no target was ever set**, not that
  the client is failing. Never count it as a miss.
- Check `source` before quoting a number. An `estimate` is a modelled
  placeholder, not a measurement, and must not be presented to a client as
  their actual traffic.
- Use the `trend_*` fields rather than the sign of the percentage. Average
  position and cost per click improve as they fall.

If the check shows metrics genuinely behind, that's the input to step 6 of the
engagement — another round of changes — not a reason to quietly revise the old
targets so they look met.

## Why the fact-check matters

An AI-assisted strategy document is only as good as its most out-of-date claim. Schema recommendations, AI-search citation tactics, and Google Business Profile rules change faster than almost anything else in SEO — a document that confidently recommends something that stopped working (or started violating policy) eighteen months ago costs a client real money and, in a regulated field, potentially real compliance exposure. `references/fact-check-registry.md` exists so that risk gets checked mechanically instead of relying on memory.

## Staying current

Every reference file here — especially `phase11-12-geo-schema.md` and `fact-check-registry.md` — is a snapshot as of when it was written and will go stale. Before recommending or writing into a live client document anything about AI-search visibility, schema markup, or platform-specific policy (Google Business Profile rules, review policy, structured data policy), re-verify against current documentation or a fresh search rather than trusting the file as settled fact. If something in these files has changed, update the file — a stale fact-check registry that never gets corrected becomes exactly the kind of outdated advice this skill exists to catch.

## Reference files

- `references/phase1-foundation.md` — the foundation/brand summary document: structure, inputs, prompt pattern
- `references/phase2-competitors-and-keywords.md` — competitor analysis and keyword clustering (both require real platform data)
- `references/keyword-scoring.md` — the Keyword Opportunity Score (0-100) and Search Intent Classification applied in Phase 7/8 and carried through Phases 9, 10, 13, and 14
- `references/phase5-6-strategy-and-gmb.md` — SEO strategy/targets and Google Business Profile strategy
- `references/phase9-10-architecture-linking.md` — page architecture (URL map) and internal linking rules
- `references/phase11-12-geo-schema.md` — GEO/AEO strategy and schema plan — the two fastest-moving, must-re-verify phases
- `references/phase13-14-content-calendar.md` — blog topic list and content calendar
- `references/phase17-18-19-tools-tracking-reporting.md` — tech stack documentation, local rank tracking, reporting/KPI framework
- `references/phase20-results-retrospective.md` — the recurring retrospective that checks actual results against Phase 5's targets and the Keyword Opportunity Scores, and revises priorities going forward
- `references/client-context.md` — how to get the client's answers from the portal, from a document they filled in, or from neither
- `references/performance-tracking.md` — snapshots and projections: the tracked metric keys, how to read a `check_performance` result, and the rules for saving a target you can stand behind
- `references/local-and-gmb.md` — what to assess from the client's active services, and the BrightLocal data behind Phase 6: rank grids, competitors on the same grid, reviews, citations, and the credit discipline a 200–400-credit run demands
- `references/ai-visibility.md` — Ahrefs Brand Radar and BrightLocal AI Visibility, which answers which question, and the honesty rules for the least settled area in this skill set
- `references/questionnaire-fields.md` — generated map of every questionnaire question to the key it's stored under, for filing a returned document back to the portal
- `assets/blank-strategy-questionnaire.md` — the strategy questionnaire as a fillable document, for clients who'd rather answer by email
- `assets/blank-audit-questionnaire.md` — the short audit intake sheet, for a prospect who isn't in the portal yet
- `references/fact-check-registry.md` — current status of common SEO/schema/AI-search claims: what's dead, what's still real
- `references/document-assembly.md` — how to combine every phase into one ordered final document, and how this composes with seo-content-production
- `assets/nr-lawyers-foundation-document.md` — a complete worked example of a Phase 1 output
- `scripts/check_strategy_doc.py` — completeness check (unresolved placeholders) and fact-check scan (dead-advice language) for an assembled strategy document
