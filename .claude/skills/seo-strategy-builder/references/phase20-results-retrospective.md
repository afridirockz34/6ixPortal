# Phase 20 — Results Retrospective

Recurring, not one-time. Every other phase in this skill produces a plan; this is the only one that checks whether the plan worked and feeds that back in. Without it, the Keyword Opportunity Score is just a more sophisticated-looking guess that's never actually validated — and a strategy that never gets checked against what really happened can keep repeating the same mis-calibrated priorities quarter after quarter without anyone noticing.

## What it produces

A comparison of what the strategy predicted — Phase 5's targets and the Keyword Opportunity Scores assigned in Phase 7/8 — against what actually happened, on a recurring cadence. **Quarterly is the default cadence when this phase runs on its own**: monthly is too noisy for ranking movement to mean much on this methodology's full depth, annual is too slow to redirect effort before a lot of wasted work compounds.

That default assumes this phase is the only recurring check in place. If a session is using `seo-client-pipeline`, that skill runs a lighter biweekly pulse check and a monthly client report on top of this methodology, and invokes this phase's full depth specifically when a biweekly check surfaces something alarming or a genuine new opportunity — see `seo-client-pipeline/references/monitoring-and-escalation.md`. The two aren't in conflict: the biweekly/monthly cadence is the lightweight tripwire, this phase is what runs when the tripwire goes off, on whatever cadence that turns out to be rather than a fixed one.

Produces, either way: which keyword bets paid off, which stalled or declined, whether Phase 5's targets are on pace, and a revised priority order feeding back into Phase 7/8's keyword list and Phase 14's calendar for the next period.

## Required inputs

**If the portal connector is in this session, `check_performance` supplies the first two of these in one call** rather than needing them pasted: it returns, per tracked metric, where the client is now, where they started, where they were at the last check, and the target the strategy set for this date — because Phase 5's targets were written to the portal with `save_strategy_projections` when the strategy was approved. Call it first, then pull the keyword-level detail from Ahrefs underneath it.

Read `references/performance-tracking.md` before interpreting the result. The three traps, each of which would put a false finding in a retrospective:

- A metric reporting `no_projection` means **no target was ever set for it**, not that it missed. It belongs in neither "Paid Off" nor "Underperformed" — if a target should have existed, the finding is that the projections were never saved, which is a process gap, not a client result.
- Check each metric's `source`. An `estimate` is a modelled placeholder the dashboard shows before a data provider is connected — it is not a measurement, and a retrospective built on one is measuring the portal's own model rather than the client's traffic. If the metrics driving the retrospective are estimates, say so and get the credentials connected first.
- Read the `trend_*` fields rather than the sign of the percentage. Average position and cost per click improve as they fall, so a `+` on those is a worsening.

If the connector isn't there, or nothing was ever stored, the inputs below get pasted as this phase originally assumed — and the fix going forward is to save the targets at approval time so the next retrospective doesn't depend on someone finding the old document.

- **Phase 5's stated targets** (the 90-day / 6-month / 12-month objectives) and the date they were set.
- **The Keyword Opportunity Scored list from Phase 7/8**, so each keyword's actual movement can be checked against what its score predicted.
- **Current live data, dated** — ranking positions, organic traffic, and conversions where available, for the same keyword set, pulled fresh rather than estimated.

**If Ahrefs is connected:** `rank-tracker-overview` for tracked positions if a rank-tracker project exists for the client (set up in Phase 18); `site-explorer-organic-keywords` for the current full ranked list otherwise; `site-explorer-metrics-history` for the traffic trend over the period; `gsc-performance-history` and `gsc-positions-history` if Search Console is linked. GSC's impressions data specifically catches a keyword gaining visibility without yet cracking the top 10 — reading from rank data alone would score that as "nothing happened," which understates real, if early, progress. Pull dates that actually match the period under review; a retrospective run against stale rank-tracker data is worse than skipping it and saying so plainly.

## Prompt pattern

```
Here is [Client]'s Phase 5 targets, set on [date]: [paste].
Here is the Keyword Opportunity Scored list from Phase 7/8, with each
keyword's predicted score: [paste].
Here is current live ranking/traffic data pulled on [date]: [paste].

Compare actual movement against both the Phase 5 targets and the
per-keyword opportunity scores. For each keyword prioritized this
period: did it move (position change), and how does that compare to
what its score predicted? Group findings into:

- Paid Off — moved meaningfully, matches or beats prediction.
- Underperformed — prioritized but didn't move. Investigate why: a
  misjudged intent classification, a stronger-than-expected competitor,
  a technical blocker, or a scoring factor that didn't hold for this
  keyword.
- Too Early To Tell — moved into this period too recently for
  meaningful ranking movement yet. Do not count this as a failure.

Before attributing any movement, up or down, to a specific cause, check
it against a dated, confirmed list of Google algorithm updates for this
window (see fact-check-registry.md's Google Update Correlation section).
A date overlap is a hypothesis, never a stated cause.

Then propose a revised priority order for next period's keyword and
content work, incorporating what Underperformed found. If a factor in
the scoring formula is consistently wrong for this client specifically
(for instance, difficulty scores running systematically optimistic in
this market), say so and propose a client-specific adjustment — not a
change to the skill's default formula.
```

## Timing discipline

Ranking movement on a competitive term can genuinely take three to six months. Running a first retrospective on a keyword's priority status before at least one full quarter has passed *since it was actually targeted* (built, published, internally linked — not since the strategy document was written) produces a false signal, not useful feedback. Treat "Too Early To Tell" as the honest, correct answer for anything inside that window rather than a hedge.

## What this feeds

- **Back into Phase 7/8** — a revised keyword priority order for the next period, and, where a scoring factor proved consistently miscalibrated for this client, a noted client-specific weighting adjustment.
- **Back into Phase 14** — re-sequence the content calendar around what Underperformed found, rather than mechanically continuing the original sequence regardless of results.
- **Into Phase 19's reporting** — the retrospective's findings are what turn the recurring report in `phase17-18-19-tools-tracking-reporting.md` into "here's what moved and why" instead of a number dump with no interpretation attached.
- **Back into the portal, if targets genuinely change.** A retrospective that revises the plan can save the new targets with `save_strategy_projections`, which supersedes an earlier target for the same metric and date. Two rules on that, and the first matters more than it sounds: **never revise a target for a date that has already passed.** Moving a missed target down after the fact erases the only record that the plan was off, which is precisely the information this phase exists to produce — the miss is the finding; log it and set the *next* target more realistically. And a revised target still has to be one the revised strategy argues for in writing, not a figure chosen because it's reachable.

## Before calling a retrospective done

There's no separate script for this phase — run `python scripts/check_strategy_doc.py <retrospective_file>` the same as any other phase output; it still catches unresolved placeholders and any dead-advice language. There's no meaningful mechanical check for "did we correctly judge why a keyword underperformed" — that's real judgment a script can't verify.
