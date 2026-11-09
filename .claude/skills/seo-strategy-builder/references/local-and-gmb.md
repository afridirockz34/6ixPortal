# Local visibility and Google Business Profile — the data layer

Shared by `seo-strategy-builder` (Phase 6), `seo-audit-report`, and
`seo-client-pipeline`. Read this before writing anything about a client's
local presence.

Until recently the GMB phase said "pull it manually — log into the profile or
ask the client for access", which meant it was the one phase with no data
behind it. With the BrightLocal connector in the session it has the best data
of any phase: an actual map of where the business ranks, point by point,
across the area it sells into.

## First: does this client even have local work?

**Check the client's active services before deciding what to assess.** A
client's dashboard has a row per active service, and `get_client_profile`
returns them. The three cases are genuinely different pieces of work:

| What they bought | What gets assessed |
|---|---|
| **GMB only** | Local pack and map visibility, the profile itself, reviews, citations. Organic rankings are context, not the deliverable — do not build them a content strategy they are not paying for. |
| **SEO + GMB** | Both, and **the local half is not optional**. An SEO+GMB client whose report covers only organic has been assessed on half of what they bought. |
| **SEO only** | Organic. Mention local only where the client is plainly a local business and the local gap is a real finding worth selling them — flag it as an opportunity, not as work already in scope. |

The failure this table exists to prevent is the middle row. A client paying
for both, handed a report about keywords and backlinks, is reading a document
that ignores the service they can most easily check for themselves.

## Is BrightLocal connected?

Look for `mcp__BrightLocal__*` in the session's tool list (search for them if
deferred). If they are not there, say so and fall back to what is checkable
without them — the live profile, a manual search from the client's city — and
do not present that as equivalent.

## The shape of the account

Two separate things, loosely linked:

- **Clients** (`find_clients`) — the agency's own client records.
- **Locations** (`find_locations`) — the business locations that reports
  actually attach to. `location_id` is the key for nearly everything.

A location's `client_id` is often `0`, meaning it was created without being
filed under a client. **Do not assume a location belongs to the client whose
name it resembles** — match on business name, address and website, and if two
locations look like the same business (a live one and an older ad-hoc one),
check which has the recent reports before reading either.

## Credits: read this before running anything

A Local Search Grid run costs **one credit per grid point per keyword**, so a
single run is routinely 200–400 credits. Account balances are not in that
range: at the time of writing this account had **3**.

- **`get_lsg_credits` before any run, every time.** It returns
  `{"credits": N, "type": "adhoc_run"}`.
- **Never trigger `run_lsg_report`, `run_lrt_report`, `run_rm_report` or
  `run_aiv_report` to satisfy curiosity.** Reading an existing run is free;
  starting a new one spends real money and is a persistent account action.
  Confirm with the person running the session first, exactly as this skill set
  requires for any other account-changing call.
- `create_lsg_report`, `create_location`, `create_client` and the rest of the
  `create_*` family are the same: real, persistent, confirm first.
- Everything named `find_*`, `get_*` is a read. Use those freely.

## Reading a rank grid — and the two ways to read it wrong

`find_lsg_reports` → a report's `report_id`, its `keywords[]` (each with an
`id`), its grid geometry and its `last_run_id`.
`get_lsg_latest_run(report_id, keyword_id)` → the most recent result for one
keyword, free to read.

What comes back:

```json
{ "keyword": "criminal lawyer toronto", "status": "finished",
  "grid_points": [ {"latitude": 43.698, "longitude": -79.469, "rank": 2}, … ],
  "summary": {"avg_rank": 31, "num_points": 49,
              "num_high_ranking_points": 2, "num_med_ranking_points": 13,
              "num_low_ranking_points": 34},
  "grid_url": "https://www.local-marketing-reports.com/…/map-points" }
```

**`rank: 0` does not mean rank zero. It means the business did not appear at
that point at all.** Treating it as a number is the first way to read this
wrong, and it fails in the flattering direction: on a real 49-point grid with
three unranked points, averaging the raw values gives 24.8 where the truth is
31 — a grid made to look 20% better than it is by counting its worst points as
its best.

**So do not recompute the average.** `summary.avg_rank` already handles it:
verified against a real run, BrightLocal substitutes ~100 for an unranked
point before averaging. Quote `summary.avg_rank` and leave it alone.

The buckets, also verified against that run, are:

| Field | What it counts |
|---|---|
| `num_high_ranking_points` | rank 1–3 — the local pack |
| `num_med_ranking_points` | rank 4–10 |
| `num_low_ranking_points` | rank 11+ **and every unranked point** |

So "34 of 49 points are low" includes the places the business is invisible.
Say it that way.

The second way to read it wrong is to report the average and stop. **The
average is the least interesting number on a rank grid.** The finding is the
shape: a business that ranks 2 at its own address and 87 four kilometres east
has a distance-decay problem, which is a specific, fixable, sellable thing.
A flat grid at 30 everywhere is a different problem entirely — the profile is
not competitive anywhere, and moving the pin will not help. Read the points,
describe the shape, and name the direction the visibility falls off in.

`grid_url` is a shareable white-label map of exactly that. For a client-facing
document it is worth more than any sentence describing it.

## Competitors on the same grid

`get_lsg_competitors(report_id)` lists who else appears; `
get_lsg_latest_competitor_rankings(report_id, keyword_id, cid)` returns their
positions across the same points. Pass `cid` as a **string** — Google CIDs are
unsigned 64-bit and will lose precision as a number.

This is the most persuasive local finding there is: the same map, the same
keyword, their pin and the competitor's. Where a competitor is top-3 across an
area the client is invisible in, that is a named, checkable gap.

## The rest of the local picture

| Tool family | What it answers | Notes |
|---|---|---|
| `*_lrt_*` Local Rank Tracker | Straight keyword ranks over time, by search engine | Cheaper than a grid; use for tracking, use the grid for diagnosis |
| `*_rm_*` Reputation Manager | Reviews across directories, with `find_rm_reviews` | Feeds the review half of a GMB plan with real counts and ratings instead of "they should get more reviews" |
| `*_ct_*` Citation Tracker | Which directories list them, and whether the details match | NAP consistency as a measured fact |
| `*_cb_*` Citation Builder | **Builds and submits** citations | Changes the live web on the client's behalf. Never without explicit confirmation |
| `get_location` | The full profile record: categories, hours, services, photos, social, Active Sync | The profile-completeness half of a GMB plan |
| `get_brain_recommendations(location_id)` | BrightLocal's own AI recommendations for that location | Useful as a cross-check on your own findings; it is another model's opinion, not a measurement, so never quote it to a client as data |

## Writing it up

Everything in `report-template.md`'s honesty rules applies here unchanged, plus
one specific to this data:

**A rank grid is a snapshot of one moment.** It says where the business stood
when the run finished — `end_date` is in the result, so quote it. Local pack
results move with the searcher, the device and the day; a grid from three
weeks ago described as "where you rank" is overclaiming. Say "on 4 November,
across 49 points in the GTA".
