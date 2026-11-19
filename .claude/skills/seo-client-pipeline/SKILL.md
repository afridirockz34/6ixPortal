---
name: "seo-client-pipeline"
description: Orchestrates the full client lifecycle in one session — intake (an audit-request doc or a 6ix Portal profile), audit report, strategy build, approved changes implemented directly on the client's live WordPress site, then a recurring biweekly performance check and monthly report that can trigger another round of strategy and implementation. One Cowork or Claude Code session represents one client; this skill detects where that client currently sits in the pipeline from the 6ix Portal and routes to seo-audit-report, seo-strategy-builder, and seo-content-production rather than duplicating their work. Trigger on "start working on [client]", "new client session for [client]", "where are we with [client]", "check current performance for [client]", "implement the approved strategy for [client]", or "what's next for [client]".
---

# SEO Client Pipeline

## Why this exists

The other three skills each do one job well, but none of them knows what came before it or what comes after. `seo-audit-report` builds a pitch document. `seo-strategy-builder` and `seo-content-production` build a plan once someone's signed. None of them touches the live site, and none of them checks whether any of it actually worked. This skill is the layer on top: one session, one client, a clear sense of which of the six stages below that client is in right now, and a safe handoff into and out of the other three skills plus the two stages they don't cover — implementing changes on the real site, and the recurring check that feeds a strategy revision back in.

## The six stages

```
1. Intake  ──>  2. Audit  ──>  3. Strategy  ──>  4. Implementation
                                                        │
                                                        v
                                            5. Monitoring (biweekly check
                                               / monthly report)
                                                        │
                                            alarming finding or new
                                            opportunity, approved
                                                        │
                                                        v
                                     6. Re-strategize  ──>  back to 3/4
```

Stages 1-3 are each a human checkpoint before the next one starts — generating an audit or a strategy is not the same as it being *approved*, and this skill never advances past a checkpoint on its own say-so. Stage 4 is the first stage that touches a real, live, client-facing website, and gets a correspondingly higher bar for confirmation (see `references/implementation.md`). Stage 5 runs on a cadence, not on request alone — a biweekly pulse check rolling into a monthly report. Stage 6 isn't really a separate stage; it's stages 3-4 run again, scoped to whatever the monitoring stage surfaced, with its own approval checkpoint like the first time through.

## Starting — or resuming — a session for a client

One session represents one client for the life of the engagement, but sessions get closed and reopened over weeks or months, so never trust what stage a client is at from conversation memory alone once a session resumes after a gap. Re-derive it from the portal every time work on a client starts.

1. **Check what's actually connected.** The portal connector — profile and artifacts (`list_clients`, `get_client_profile`, `list_client_artifacts`, `save_client_artifact`, `update_client_profile`, `issue_strategy_questionnaire`), performance (`check_performance`, `capture_performance_snapshot`, `save_strategy_projections`), the work log (`log_work`, `list_work_log` — what we changed, which is what the customer's optimization-proof section is built from, and which is not SEO-only), the task board (`create_tasks`, `list_tasks`, `update_task` — the approved plan as rows the advisor works from, and the one place that says what has shipped and what is sitting with the client), figures for the advisor to confirm (`propose_metrics`) and per-page or per-keyword readings (`record_entity_metrics`), and **the client's own live accounts** (`get_live_metrics` for GA4 / Search Console / Google Ads / Meta, `get_search_console_queries` for the query list, `get_pagespeed` for any URL). That last group is what makes this an operating system rather than a filing cabinet: the profile says what the client told us, the live tools say what is actually happening. Then Ahrefs (`mcp__Ahrefs__*`), BrightLocal (`mcp__BrightLocal__*`, which is what makes a local/GMB assessment measured rather than asserted — see `seo-strategy-builder/references/local-and-gmb.md`), and — only needed from Stage 4 onward — a live path to the client's server (direct SSH/WP-CLI/SFTP access, whether that's Claude Code with configured server credentials or a Cowork session linked to a computer that can reach it). Say plainly what's missing rather than silently working around a gap; a session missing the portal connector can still do Stages 1-3 reading from a document, it just can't check artifacts or file anything back.
2. **Establish the client's identity.** Ask for a 6ix Portal profile (business name, person's name, or email to search with `list_clients`) or an audit-request/questionnaire document — exactly the three routes `seo-strategy-builder/references/client-context.md` already defines. Don't make the person choose between "portal" and "document" as if they're different processes; both lead to the same place.
2b. **Read their active services before anything else.** They are the scope of the whole engagement, and the three cases produce different work: SEO only, Google Business Profile only, or both — in which case the local half is not optional. A client paying for both who gets a strategy about keywords and backlinks has been served half of what they bought. `seo-strategy-builder/references/client-context.md` has the table.

3. **Detect the current stage** from three signals, not one. The most advanced thing any of them shows sets the floor for where this session picks up — not the earliest.

   - **The profile's own `stage`**, from `get_client_profile`. `audit_intake_incomplete` means the signup questionnaire has gaps — say which, and carry on with what's there. `ready_for_audit` means the intake is complete and Stage 2 can run. `awaiting_strategy_answers` means the strategy questionnaire has been issued but not returned, which is a Stage 3 blocked on the client, not on us — chase the answers rather than building on gaps. If it was never issued, `issue_strategy_questionnaire` returns a link to send them. `ready_for_strategy` means every required question has a value — but the client presses Submit separately, so check `questionnaire.strategy.submitted` too: complete-but-unsubmitted usually means they are still editing, and is worth saying out loud before Stage 3 builds on those answers.
   - **`list_client_artifacts`** for the most advanced artifact type that exists: `audit_report` → at least Stage 2 done; `strategy_document` → at least Stage 3 done; `implementation_log` → at least Stage 4 started; recent `performance_check`/`monthly_report` entries → Stage 5 is active.
   - **`check_performance`** — `summary.snapshots_held` says how long the client has actually been measured for, which is the most reliable sign Stage 5 is genuinely running rather than merely due. `has: false` with `reason: no_snapshots` means nothing has ever been captured; if the strategy is live, that's a gap to close now, not evidence the engagement is early.

   The signals can disagree, and the disagreement is itself useful: a filed `strategy_document` with every metric reporting `no_projection` means a strategy was approved but its targets were never saved, so nothing downstream can check it. Say so and offer to fix it.
4. **State the stage plainly and confirm the next action.** "This looks like Stage 3 — the audit's filed and the client hasn't got a strategy yet. Want to start that?" Never silently jump ahead a stage because the data implies it's ready; the person running this may know something the portal doesn't (the client went quiet, the audit needs a redo, whatever).

## Stage 1-2: Intake and Audit

Route directly to `seo-audit-report` for both — its own SKILL.md already covers getting the prospect's context (portal, returned document, or neither) and building the report. Once the report's generated and filed (`save_client_artifact`, `type: "audit_report"`, already built into that skill), this is a checkpoint: ask whether the client has actually reviewed it and wants to move forward before treating Stage 3 as unlocked. A generated report sitting unopened in an inbox is not an approved one.

## Stage 3: Strategy

Once the audit's approved, get the strategy questionnaire to the client. Three ways, all fine: `issue_strategy_questionnaire` returns a link they can open and fill in themselves (the portal keeps their answers, so nothing has to be transcribed afterwards), a blank document to complete by email (`seo-strategy-builder/assets/blank-strategy-questionnaire.md`), or straight through in conversation. Same three-route logic as `seo-strategy-builder`'s side of `seo-strategy-builder/references/client-context.md`; if they send a document back, offer to file the answers with `update_client_profile` so the next stage doesn't start from an inbox.

Route the actual building to `seo-strategy-builder` for the research/data phases and `seo-content-production` for voice/content drafting, same as those two skills already describe working together. Save the assembled result via `save_client_artifact` (`type: "strategy_document"`).

Another checkpoint: strategy *approval*, not just a finished draft, is what unlocks Stage 4 — a strategy document is cheap to revise before anything's live; changes after Stage 4 starts are not.

**Read the plan length before you build anything.** `get_client_profile` returns `commercial.plan_terms` — months per service, set by the advisor when they activated it. A six-month plan is not a twelve-month plan with the second half deleted: the monthly rate is lower too, so the per-month volumes drop as well as the total, the topic set is fifty rather than a hundred, and three deliverables are twelve-month only: the quarterly content refresh, the monthly competitor watch, and the twelve to sixteen neighbourhood pages. An absent value means twelve, because over-delivering against a six-month contract is an awkward conversation and under-delivering against a twelve-month one is a refund. `seo-strategy-builder/references/engagement-plans.md` has both plans side by side.

**On approval, turn the strategy into a task board.** `create_tasks` writes one row per thing that has to be done, across the whole engagement — content, technical fixes, URL changes on pages that already exist, backlinks and digital PR, citations, Google Business Profile work, reporting. It arrives as a **draft** under the service's own tab in the advisor dashboard: the advisor fixes the dates and the order and publishes it, and only then does the client see a timeline. Give every task a `month_no` across the engagement's full length — twelve months, or six on the shorter plan — even when the content itself is produced in four-month phases; the board shows the whole term and the writing arrives in batches.

**One task per thing, with a category on every one.** Every article, every page, every link target, every technical fix gets its own row, because the board is what an advisor ticks off and what a later session reads to know what shipped. But an uncategorised row is the problem the categories solve: the client's dashboard groups the plan into tabs by `category`, and the advisor's service panel does the same, so a task with no category lands under "Other" where nobody looks for it. Use the same handful across a client — Content, Technical, On-Page, Backlinks, Digital PR, Citations, GMB, Local, Reporting — rather than inventing one per task.

Four states, and they are the whole vocabulary the engagement is reported in:

| State | What it means |
|---|---|
| `todo` | Not started. |
| `client_review` | The copy or the plan is with the client, waiting on their approval. |
| `implementation` | Approved and being built. |
| `complete` | Done and verified, with a date on it. |

`client_review` exists because copy sitting with a client is not the same as work not started, and reporting the two as one makes a month look idle when it is not.

**Read the board before you build anything.** `list_tasks` is how a session that resumes weeks later knows what already shipped, what is waiting on the client, and what is next — before `list_client_artifacts`, which says what was produced but not what happened to it.

**On approval, before any implementation starts, write the plan's targets down.** `save_strategy_projections` for the numeric objectives Phase 5 actually commits to, and `capture_performance_snapshot` so the baseline is where the client stood the day work began rather than wherever they happened to be the first time a scheduled capture ran. This is the step that makes Stage 5 possible at all: skip it and every later check can only report that a number moved, never whether it moved as far as we told the client it would. `seo-strategy-builder/references/performance-tracking.md` has the rules — chiefly that only a target the strategy argues for in writing gets saved, because an invented one quietly makes every future comparison meaningless in both directions.

## Stage 4: Implementation

The first stage that writes to the real site. Full discipline — getting a live path to the server, turning the approved strategy into a reviewable change-set, confirming before any write, applying, and logging what actually happened — is in `references/implementation.md`. Work from the board: `list_tasks` for what is due, `update_task` to `implementation` when you start one, and to `complete` when the change is live and verified, with the `work_log_id` from `log_work` attached so the row carries its own evidence rather than only a tick. Read it before touching anything here; this is the highest-stakes stage in the whole pipeline and the one place a shortcut is least forgivable.

## Stage 5: Monitoring

Once changes are live, this skill is what gets invoked by "check current performance" — a biweekly pulse check against the prior check, against Phase 5's targets and the Keyword Opportunity Scores from `seo-strategy-builder`, and against the implementation log, rolling up into a monthly client-facing report.

Start it with `check_performance`: the prior check and the strategy's targets both come back in that one call, because Stage 3's approval step saved them. Then `get_live_metrics` and `get_search_console_queries` for what is actually happening right now in the client's own accounts — snapshots tell you the shape of the trend, live data tells you where it stands today and which queries moved. Use Ahrefs for the part neither can see: what competitors did over the same period. Full detail, including how findings get classified, what triggers an escalation, and the three ways a `check_performance` result gets misread into a false escalation, is in `references/monitoring-and-escalation.md`.

Two things worth holding on to here. A metric that moved is not the same as a metric that moved *because of us* — a live number is evidence, not attribution, and the implementation log is what connects the two. And a source reporting `error` in `get_live_metrics` is not a client whose numbers fell; say which source failed rather than reporting the gap as a result.

## Stage 6: Re-strategize

Not a separate process — a monitoring finding that's alarming or a genuine new opportunity gets proposed (never auto-implemented) per `monitoring-and-escalation.md`'s escalation section. Once approved, it's Stage 3 and 4 again, scoped to what was approved, appending to the same implementation log rather than starting a new one.

## Reference files

- `references/implementation.md` — change-set → confirm → apply → log discipline for making approved strategy changes on the live site
- `references/monitoring-and-escalation.md` — the biweekly check, the monthly report, and when (and how) a finding escalates into another strategy/implementation round
- `scripts/check_implementation_log.py` — mechanical check that every applied change in the log is dated and has a clear status; an undated applied change breaks the Google Update Correlation discipline everything downstream relies on
- `seo-audit-report/SKILL.md` — Stage 2
- `seo-strategy-builder/SKILL.md` and `seo-strategy-builder/references/client-context.md` — Stage 3's data/research phases and the three intake routes
- `seo-content-production/SKILL.md` — Stage 3's voice/content drafting
- `seo-strategy-builder/references/phase20-results-retrospective.md` — the methodology Stage 5/6's escalation path runs
- `seo-strategy-builder/references/performance-tracking.md` — the portal's snapshots and projections: what Stage 3's approval step saves, what Stage 5 reads, and how to read a result without producing a false escalation
