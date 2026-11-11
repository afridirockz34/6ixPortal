# Monitoring: Biweekly Checks, Monthly Reports, and When to Re-strategize

## The cadences, and one underlying pull

Three things go *out* — a weekly check-in, a monthly report, a quarterly review
(see "The three reporting tiers" below). Two things get *pulled* — the biweekly
check and the month's roll-up. The weekly note is written from whatever the last
check and the implementation log already say; it is not another data pull.

- **Biweekly — "check current performance."** A lightweight pulse check: is anything moving, is anything alarming, has a new opportunity opened up. Not a full client-facing report on its own — an internal check that most of the time produces "on track, nothing to report" and occasionally produces an escalation.
- **Monthly — the client-facing report.** Rolls up that month's biweekly checks into the reporting format `seo-strategy-builder/references/phase17-18-19-tools-tracking-reporting.md` already defines (impressions, branded search volume, and conversions alongside sessions — never organic-sessions-only, for the reasons that file gives).

The monthly report isn't a separate data pull — it's the biweekly checks from that month assembled and written up for the client, with the internal "on track" notes turned into an actual narrative and anything escalated during the month called out explicitly.

## Running a biweekly check

1. **Start with the portal's own check, then fill in around it.** `check_performance` returns, in one call, where the client stands on every metric the portal tracks for them, against where they started, where they were at the last check, and the target the strategy committed to for this date — so step 2's first two comparisons come back already assembled rather than needing to be reconstructed. Read `seo-strategy-builder/references/performance-tracking.md` before acting on the result; the three things that will otherwise produce a false escalation are:

   - `no_projection` means **no target was ever set** for that metric, not that it missed. Never escalate on one.
   - An `estimate` `source` is a modelled placeholder shown before a data provider is connected — not a measurement. A drop or jump in an estimated metric is not a client event, and getting the credentials connected is the real finding.
   - Read the `trend_*` fields, not the sign of the percentage: average position and cost per click improve as they fall.

   It covers the portal's tracked metrics only, not Search Console, Ahrefs or plugin data, so pull those separately for the ranking and keyword-level detail:
   - **Ahrefs**: `site-explorer-metrics-history` and `rank-tracker-overview` (if a rank-tracker project exists for the client — set up in Phase 18) for rankings and the traffic trend.
   - **Google Search Console**, if linked through Ahrefs: `gsc-performance-history` / `gsc-positions-history` — this catches a keyword gaining impressions without yet reaching the top 10, which the rank/traffic tools alone would read as "nothing happened."
   - **Whatever the client's WordPress SEO/analytics plugin shows**, if that data isn't otherwise reachable — ask directly rather than guessing at a plugin's numbers, and say plainly that this part came from a manual check rather than a live pull.
2. **Compare against three things, not just "did the number go up":**
   - **The prior check** — trend since two weeks ago, not just the current snapshot in isolation. `check_performance` returns this as `previous` and `change_since_previous`; a snapshot is kept per week, so there's a real prior figure rather than a remembered one.
   - **Phase 5's targets and the Keyword Opportunity Scores** from `seo-strategy-builder`'s Phase 7/8 — is movement tracking what the strategy actually predicted, ahead of it, or behind it. The targets come back in the same call as `projected` and a `status` of ahead / on_track / behind, because they were written to the portal when the strategy was approved. If every metric reports `no_projection`, they never were — say so, and get them saved, rather than reading the absence as "nothing to compare".
   - **The implementation log** (`references/implementation.md`) — did a specific dated change coincide with a specific dated movement. Before stating that connection as real, check it against `seo-strategy-builder/references/fact-check-registry.md`'s Google Update Correlation section — a confirmed algorithm update in the same window is at least as plausible an explanation as the implementation, and both need to be weighed, not whichever is more convenient to report.
3. **Classify what's found:**
   - **On track** — log it, no action, nothing goes to the client beyond the monthly roll-up.
   - **Worth a note, not alarming** — mention in the next monthly report; still no escalation.
   - **Alarming, or a genuine new opportunity** — escalate (below), on its own timeline, not held until the next monthly report if it's time-sensitive.

## Escalating to a re-strategize round

An alarming finding (a real ranking or traffic drop that isn't explained by normal noise or a confirmed algorithm update, a technical issue the last implementation pass may have caused, a competitor move into the client's priority terms) or a genuine new opportunity (a keyword that opened up, a SERP feature newly available, a competitor gap that wasn't there before) doesn't get fixed from inside a biweekly check — it gets *proposed*.

1. Recommend running `seo-strategy-builder`'s Phase 20 retrospective, or — if the finding is narrow — a scoped re-run of just the affected phase (Phase 7/8 for a keyword-priority shift, Phase 9/10 for an architecture or technical problem, Phase 11/12 if it's AI-visibility or schema related).
2. Say specifically why, citing the actual data from the check — not "performance seems off," but "keyword X dropped from position 6 to 19 over the last two checks, no confirmed algorithm update in that window, and the last implementation pass didn't touch that page — worth investigating."
3. **Stop and get explicit approval before implementing anything.** A recommendation surfaced from a biweekly check carries exactly the same weight as the original strategy did — it's a proposal, not an automatic action, no matter how clear-cut the data looks.

Once approved, the resulting changes go back through `references/implementation.md`'s change-set → confirm → apply → log discipline, **appending to the same implementation log** rather than starting a new one — this is Stage 6 of the pipeline, not a separate process from Stage 3/4.

## The three reporting tiers

One cadence is not enough. A client who hears from us once a month hears from
us only when there is a document to send, and a month of silence is where the
doubt grows that the monthly report then has to argue against.

| Tier | To | When | Form |
|---|---|---|---|
| **Weekly check-in** | the advisor, or the client where they want it | weekly | Three bullets in the body of an email. Rankings movement, what went live, anything broken. Five-minute read, no attachment. |
| **Monthly report** | the client | monthly | Three wins, three focus areas, the numbers, what we did, what is next. |
| **Quarterly review** | the client, on a call | quarterly | Competitor gap re-run, best and worst performing pages, and whether the targets still hold. |

**The weekly one matters more than its size suggests.** It is three sentences
and it is the cheapest thing in this whole system, and it is what stops a
client wondering. Write it even in a week when nothing moved — *"rankings flat,
two pages published, no issues"* is a perfectly good week and saying so is the
point. A week with nothing to report is not a week to skip.

**The quarterly one is where targets get revised**, and the only place they
should be. A target moved quietly in a monthly report is a target nobody is
held to. If the Phase 5 commitments are wrong — because the market moved, or
because we set them badly — say so on a call, say why, and agree new ones.

## Writing the monthly report

Assemble the month's biweekly checks into the Phase 19 reporting format. Lead
with **three wins and three focus areas**, then the numbers, then the work.
Call out explicitly: what was actually implemented that month (pulled from the
implementation log, not from memory of what was planned), what moved as a
result — with the Google Update Correlation caveat applied to any causal claim
— and what's being proposed or already approved for next month. Save via
`save_client_artifact` (`type: "monthly_report"`) if the portal connector
supports writing; say plainly if it doesn't rather than treating the report as
filed when it wasn't.

Translate every metric into something a business owner recognises. "Average
position improved 2.2" is our language; "you now show up higher for the
searches that bring in work, and here is which ones" is theirs.

### What we did this month — with counts

Every monthly report carries a plain list of the work completed, with
quantities, built from the implementation log:

> - Content optimised and re-indexed — 5 pages
> - New pages published from the content calendar — 2
> - Google Business Profile posts — 4
> - Citations submitted — 5
> - Backlinks earned — 2
> - Technical fixes applied — 3
> - Position tracking and KPI update — done

This answers a different question from the rest of the report. The numbers
answer *what happened*; this answers *what did I pay for*, and that is the
question a renewal actually turns on. A month where the rankings did not move
but eleven things shipped is a defensible month. The same month with no such
list looks like a month where nothing happened.

Three rules:

- **It comes from the log, never from the plan.** `check_implementation_log.py`
  exists because an undated or unstatused entry breaks exactly this. If
  something was planned and not done, it is not on the list.
- **Counts, not adjectives.** "Five pages optimised" rather than "ongoing
  content optimisation".
- **A thin month is reported thin.** Padding the list with "monitoring" and
  "analysis" to make it look fuller is the same failure as padding an audit
  with nineteen recommendations: anybody who reads carefully can tell, and
  then the rest of the document is in question too.
