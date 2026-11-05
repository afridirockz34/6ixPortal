# Performance tracking: snapshots and projections

The engagement doesn't end when the strategy is delivered. Step 5 is a
recurring check — bi-weekly monitoring, a monthly report — and step 6 is
another round of changes based on what it found. That check is only worth
anything if there's something to compare against, which is what this part of
the portal stores.

Two kinds of record, and the distinction matters:

- A **snapshot** is what the client's numbers actually were at a point in time.
  One per client per week, captured automatically.
- A **projection** is what a strategy said those numbers would be by a given
  date. Written when a strategy is approved.

Without projections, a check can only say the number moved. With them, it can
say whether we're where we told the client we'd be — which is the question they
actually have.

---

## The tools

Look for these in the session's tool list alongside the rest of the portal
connector (search for them if they're deferred). All three take a `client_id`
from `list_clients`.

### `check_performance` — the read for any review

Returns the whole comparison in one call: current value, baseline, previous
check, the projection that was due, percentage changes, and a per-metric
status. This is the first call for "check current performance for [client]",
"how are they doing", a bi-weekly review, or a monthly report.

It takes an optional `as_of` (`YYYY-MM-DD`) to judge projections against an
earlier date — useful for writing a report covering last month rather than
today.

### `capture_performance_snapshot` — take one on demand

Snapshots happen on their own each period, so most of the time you don't need
this. Call it in one specific situation that matters: **at the moment a
strategy is approved**, so the baseline is genuinely "where they were when we
started" rather than wherever they happened to be the first time a scheduled
capture ran.

Calling it twice in the same week refreshes that week instead of adding a
duplicate, so there's no harm in an extra call. Needs a write-scoped token.

If it returns `nothing_to_capture`, the client has no active services — normal
for a prospect who hasn't signed yet. There's nothing to measure, so don't
treat it as an error to work around.

### `save_strategy_projections` — record what the strategy commits to

Call this when a strategy is approved, with the targets the strategy actually
argues for. Each row is a `metric_key`, a `target_date` (`YYYY-MM-DD`) and a
`projected_value`, plus an optional one-line `note` on what drives it. Pass
`artifact_id` if the strategy was saved with `save_client_artifact`, so a later
check can point at the document the target came from.

Re-running the strategy supersedes an earlier target for the same metric and
date rather than stacking a second one, so a revised strategy can be saved
without cleaning up after the old one.

Malformed rows come back in `rejected` with a reason instead of being guessed
at. If something is rejected, fix it or drop it — don't substitute a round
number to make the call succeed.

---

## Metric keys

Only these keys are tracked, and only for services the client actually has. A
projection against any other key will store but never be compared, because
nothing captures it.

| Key | What it is | Better when |
|---|---|---|
| `organic_visitors` | Organic visitors per month | higher |
| `keywords_page1` | Keywords ranking on page 1 | higher |
| `keywords_tracked` | Keywords under tracking | higher |
| `seo_avg_position` | Average ranking position | **lower** |
| `paid_clicks` | Ad clicks per month | higher |
| `ads_leads` | Leads from ads per month | higher |
| `ads_impressions` | Ad impressions per month | higher |
| `ads_cpc` | Average cost per click | **lower** |
| `gmb_views` | Map views per month | higher |
| `gmb_calls` | Calls from maps per month | higher |
| `gmb_directions` | Direction requests per month | higher |
| `gmb_posts` | Posts this month | higher |

Each metric in the result carries `better_when` so you never have to remember
which way round it goes, and each change carries a `trend` of `better`,
`worse` or `flat` alongside the raw percentage. Use the trend. An average
position moving from 3 to 8 is `change_since_baseline: +166.7` with
`trend_since_baseline: worse` — writing "up 167%" in a client report because
the number has a plus sign in front of it is the exact mistake those fields
exist to prevent.

---

## Reading the result

Per metric: `current`, `baseline`, `previous`, `projected`, `target_date`,
`change_since_baseline`, `change_since_previous`, `vs_plan`, the two `trend_*`
fields, `better_when`, `source`, and `status`.

**`status` is one of four**, and the fourth is not a failure:

- `ahead` — at or past the target.
- `on_track` — short of it but within 15%.
- `behind` — more than 15% short. Worth explaining in the report.
- `no_projection` — **nobody ever set a target for this metric.** `projected`
  is `null`. This is not a miss and must never be reported as one. It means
  either the strategy didn't commit to this metric, or the projections were
  never saved. Say which, and if targets ought to exist, that's the thing to
  fix — not the client's performance.

**`source` says where the number came from**, and it governs what you're
allowed to claim:

- `live` — pulled from a connected data provider. Report it as measured.
- `advisor` — a figure an advisor entered by hand. Report it, and attribute it.
- `estimate` — a modelled placeholder the portal generates so a new dashboard
  isn't blank. **It is not a measurement.** Never present an estimate to a
  client as their actual traffic, and never build a report's headline on one.
  If the metrics driving a report are estimates, say so plainly and get the
  provider credentials connected — that's the real finding.

**`has: false` with `reason: no_snapshots`** means nothing has ever been
captured for this client. Capture one, note that this first check establishes
the baseline and has nothing to compare against yet, and move on. Don't
present a first snapshot as though it showed a trend.

A client with exactly one snapshot has `baseline: null` for every metric — the
only snapshot *is* the current one. That's correct, not missing data.

---

## Setting projections honestly

This is the part that can quietly damage the engagement, so it's worth being
deliberate about.

Phase 5's rule — don't publish a traffic projection you can't support — applies
with more force here, because a saved projection outlives the conversation that
produced it. It becomes the thing every future check measures against and the
thing a client quotes back in month six. A target invented to look confident
doesn't just overpromise once; it makes every subsequent comparison
meaningless, in both directions. A client genuinely ahead of a realistic plan
reads as "behind" against a fantasy one.

So:

- **Only save what the strategy argues for in writing.** If the document says
  "we expect to reach page 1 for the eight priority local terms within six
  months", that's a projection: `keywords_page1`, a date six months out, value
  8. If the document says "organic traffic should grow meaningfully", that is
  not a projection. Leave it out. A metric with no target reports as
  `no_projection`, which is an honest state.
- **Prefer fewer, defensible targets** over covering every metric. Three
  projections the strategy can stand behind beat twelve that were
  back-filled.
- **Date them to what the strategy actually says.** The 90-day / 6-month /
  12-month horizons from Phase 5 are the natural target dates.
- **Never derive a projection from `seo_lifetime_value` or
  `seo_repeat_business`.** Those answers rank which keywords are worth
  competing for. 6ix Developers does not promise returns, and multiplying a
  lifetime value by a projected visitor count to produce a revenue figure is
  exactly the promise we don't make.
- **Say what each target assumes.** The `note` field is one line; use it for the
  condition the target depends on ("assumes the 14 planned location pages
  publish on schedule"). Six months later, that line is what explains a miss.
- **One exception, for the gap before a strategy exists.** A client's chart has
  no target line until something saves one, and that can be weeks. An audit may
  save a *provisional* figure — grounded in the competitor benchmark it
  measured, never a percentage uplift chosen for effect — so the dashboard has
  something real to plot. It must say "provisional, from the audit" in the
  note, and the strategy supersedes it on approval. The reason for the strict
  framing is that a check reading "behind" against a number nobody committed to
  is worse than showing no line at all.

---

## Attributing a change

Every check invites a "why did this move" explanation, and that's where a
report goes wrong fastest. The same discipline as Phase 19 applies: a date
window overlapping a Google update does not prove the update caused the move,
especially when the client's own redesign, content push or technical fix
happened in the same window and is at least as plausible. Check dated,
confirmed update records rather than assuming, and state an overlap as a
hypothesis. `references/fact-check-registry.md`'s "Google Update Correlation"
section covers this.

Two portal-specific traps on top of that:

- A metric whose `source` changed between snapshots — `estimate` one period,
  `live` the next — can jump sharply for no real-world reason at all. Check
  `source` before explaining a step change as performance.
- A missing metric isn't a zero. If a metric appears in the current snapshot
  but not the baseline, its `baseline` is `null` and the change is `null`.
  Don't treat the absence as "it was nothing before".

---

## When there's no portal

The same loop still runs, it just has a weaker memory. Compare against the
figures in the last report or strategy document you've been given, say in the
deliverable that the comparison came from that document rather than from stored
snapshots, and name what a stored history would add. If the client is in the
portal, offer to start capturing — one call to
`capture_performance_snapshot` means the next check has a real baseline.

What doesn't happen is reconstructing a baseline from memory or inference. An
invented "where they started" figure makes every improvement claim built on it
unverifiable, and it is very hard to catch later.
