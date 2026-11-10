# Report Template and the "Show the Wound, Not the Stitches" Pattern

## How the report is produced

The audit is written as a **JSON file of findings**, and `scripts/render_report.py`
turns it into one self-contained `.html` file:

```bash
python scripts/render_report.py audit.json -o northside-audit.html
```

That file opens in any browser, survives being emailed, and prints to PDF with
Ctrl+P. There is nothing to install and no images to lose.

**Write the JSON, not the HTML.** The numbers are the product, and hand-written
SVG drifts: an arc 3% short, a y-axis that starts somewhere other than zero, a
bar encoding the wrong field. Each of those misstates a measurement to a client
who can check it. In the JSON the facts are stated once and the geometry is
computed from them.

`assets/example-audit.json` is a complete worked example — read it alongside
this file.

## The JSON contract

Only `client` is required. **Every other key is optional, and an absent key
means an absent section.** That is the point: an audit that could not measure
page speed omits `speed`, and no speed section appears. Never fill a key with a
plausible figure to make the report look complete.

| Key | What it renders | Notes |
|---|---|---|
| `client` | Cover: name, domain, market, date, prepared_by | Required |
| `health` | The arc gauge and the lead score | `score` 0-100, `note` for which categories fed it |
| `summary` | Executive summary prose | A list of paragraphs |
| `headlines` | The stat tiles under the summary | `value`, `label`, `note`, `direction` (`up`/`down`), `good` (bool) |
| `scorecard` | One meter row per category | `category`, `score` (or `null` if not checked), `weight`, `status`, `verdict` |
| `competitors` | The client-vs-competitors line chart | See below |
| `local` | The rank grid — where they show up on the map | Local/GMB clients only. See below |
| `speed` | The website speed section | See below |
| `sections` | One block per weakness finding | `title`, `severity`, `finding`, `why`, `teaser` |
| `closing` | The closing block | `heading`, `body`, `service`, `next_step` |

### `competitors`

```json
{ "metric": "Estimated monthly organic visits",
  "source": "Ahrefs, Canada — estimated visits from the keywords Ahrefs tracks, not analytics",
  "lede": "The same twelve months, the same market, the same searches.",
  "labels": ["Nov","Dec","…"],
  "series": [ {"name":"theirsite.ca","you":true,"values":[…]},
              {"name":"competitor.ca","values":[…]} ],
  "verdict": "One sentence on what the shape of it means." }
```

- **The client's series carries `"you": true`.** It is drawn heavier and labelled
  as theirs.
- **At most four series are plotted** — the client plus the three largest
  competitors. A fifth cannot be told from a fourth by colour; the renderer drops
  the tail and says so under the chart. Pick the three that make the point.
- `values` may contain `null` for a month with no data. A gap stays a gap; it is
  never interpolated.
- **`source` is not optional in practice.** The chart's whole value is that the
  client can check it, and "estimated visits from the keywords Ahrefs tracks" is
  not the same claim as "visits", which is the distinction the report lives or
  dies on.

### `local`

Only for a prospect whose visibility is local — a business with a Google
Business Profile and a service area. Omit it entirely for a national or
e-commerce client; a rank grid for a business with no catchment says nothing.

Feed it straight from BrightLocal's Local Search Grid.
`get_lsg_latest_run(report_id, keyword_id)` returns everything this needs,
and `seo-strategy-builder/references/local-and-gmb.md` covers getting there
(including the credit discipline — a grid run spends real money).

```json
{ "business": "Acme Plumbing",
  "area": "Toronto, 6 km grid",
  "fetched": "10 November 2026",
  "keywords": [
    { "keyword": "emergency plumber toronto",
      "grid_points": [ {"latitude":43.70,"longitude":-79.50,"rank":2}, … ],
      "summary": {"avg_rank":15.4,"num_points":49,
                  "num_high_ranking_points":7,"num_med_ranking_points":22,
                  "num_low_ranking_points":20},
      "shape": "Strong at the shop, gone past Victoria Park.",
      "grid_url": "https://www.local-marketing-reports.com/…/map-points" }
  ],
  "verdict": "The pattern is distance decay, not weak ranking." }
```

Paste `grid_points` and `summary` across unchanged — both come from the run.
The renderer:

- **Lays the points out as a map**, north at the top and west on the left,
  derived from the coordinates rather than the order they arrive in. If they do
  not form a clean lattice it chunks them in order and the caption says the
  layout is not a map, so no compass direction is implied that isn't there.
- **Quotes `summary.avg_rank` and never recomputes it.** This is the trap in
  `seo-strategy-builder/references/local-and-gmb.md`: `rank: 0` means *did not appear*, and averaging the raw
  values counts the invisible points as the best ones. On the grid in
  `assets/example-audit.json` that produces **5.8 against a true 26.2** — ten
  invisible points scored as if they were the ten best, in a document whose only
  value is being checkable. Omit `summary`
  and no average is printed at all; none is invented.
- **Counts the buckets BrightLocal's way**, with `11+` including every unranked
  point, and labels that row "Position 11+ or not showing" so the client can see
  why 17 salmon squares and 3 pale ones add up to 20. Omit the counts and the
  renderer computes them by the same rule.
- Shows unranked points as a pale square with a dash, so the *shape* stays
  readable even though the count merges them with 11+.

`shape` is the finding and the reason this section earns its place — name the
direction the visibility falls off in. `grid_url` is BrightLocal's white-label
map and is safe to put in a client-facing document; the renderer links it only
if it is an `http(s)` URL. At most six keywords are drawn; the rest are named in
a line underneath.

#### `rivals` — the same map, twice

A keyword may carry up to two `rivals`, each the same shape as the keyword
itself. `get_lsg_competitors(report_id, run_id, keyword_id)` names who else
appears on the grid; `get_lsg_latest_competitor_rankings(report_id, keyword_id,
cid)` returns one of them across the same points. **Pass `cid` as a string** —
Google CIDs are unsigned 64-bit and lose precision as a number.

```json
"rivals": [
  { "name": "First Rank Plumbing",
    "grid_points": [ … ],
    "summary": {"avg_rank": 4.6, "num_points": 49} }
]
```

The keyword then renders full width: your map, their map, same cell size, same
scale, under one heading. Beneath it the renderer prints a count, and only a
count:

> First Rank Plumbing is in the top three at **23** of the 49 points, against
> your **12**. At 8 of those points you do not come up at all.

- **The two grids are matched by coordinate, not by list order.** A rival
  measured on a different set of points is still drawn beside yours, but no
  comparison sentence is printed and the report says the points were not the
  same — a sentence claiming "they beat you at 23 places" is only true if those
  are the same places.
- **Nothing is modelled from the comparison.** Who is in the pack where is a
  fact the prospect can check; what that is worth to them is not, and a figure
  for it is the kind of thing that loses the account. No lead counts, no
  revenue, no "you are missing N calls a month".
- Two rivals is the cap. A third column shrinks every cell below readable, and
  the point of this section is that the shape is legible at a glance.

Pick the rival the prospect would name themselves. The persuasive case is a
mirror image — strongest exactly where they are weakest — not simply a
competitor who is bigger everywhere.

### `speed`

Produced by `scripts/fetch_speed.py`, which measures the page with Google
PageSpeed Insights and emits this object ready to paste in:

```bash
python scripts/fetch_speed.py https://theirsite.ca --key $PAGESPEED_API_KEY -o speed.json
```

PageSpeed on purpose, rather than timing the page ourselves: pagespeed.web.dev
is where a client will go to check, and a number of ours that disagrees with
the number they get there is worse than no number, however defensible our own
measurement was.

An **API key is required in practice** — the anonymous quota is shared across
the internet and is normally exhausted, so a keyless run returns HTTP 429. Keys
are free (Google Cloud Console → APIs & Services → Credentials → API key, then
enable the PageSpeed Insights API). The portal keeps one under
**6ix Portal → Integrations → Google PageSpeed Insights**.

**Bounce rate is only ever shown when we actually have it.** It lives in the
client's own Google Analytics, so for a prospect we have no access to it. The
fetcher fills `bounce_rate_unavailable` with a line saying so and naming it as
one of the answers that would sharpen the audit — which is a stronger close
than a fabricated percentage. Where GA4 *is* connected (an existing client), set
`bounce_rate` to `{"value": "62%", "source": "Google Analytics, last 28 days"}`
and the tile appears.

## Structure

1. **Cover** — client name, domain, market, audit date, prepared-by line. This is
   a sales document representing the agency; it should look like one.
2. **Executive summary** — the arc gauge carries "SEO Health Score: XX/100" (see
   `audit-methodology.md`'s scoring section) beside 3-5 sentences. Lead with the
   single biggest finding, not a generic opener. State plainly, in business
   terms, what it is costing them; save the detailed numbers for the sections.
3. **Headline stat tiles** — two to four numbers a skimming reader takes away.
   One of them should be something they can be pleased about if the data
   supports it; a wall of red reads as a sales trick.
4. **Scorecard** — one meter row per category from `audit-methodology.md`, each
   with its 0-100 sub-score, its weight, and a one-line verdict that stands on
   its own. A category that was not run has `"score": null` and says so, rather
   than scoring zero.
5. **The competitor chart** — usually the most persuasive thing in the report.
   Do not bury it.
6. **The rank grid**, for a local client — the one finding a prospect can check
   against their own phone in ten seconds, which is exactly what makes it
   persuasive. Omit it for anyone whose customers do not come from a catchment.
7. **Website speed** — what the site does when somebody opens it.
8. **One section per weakness** with a real finding. Finding, why it matters in
   business terms, severity, and a one-sentence teaser of what addressing it
   involves.
9. **Closing** — names the service that addresses the findings (not a generic
   "contact us for SEO help") and a clear, specific next step. The only place
   the report explicitly pitches.

## Which visual, and when

The forms below are chosen, not decorative. Swapping one for another usually
makes the report worse in a specific way.

| The data is | Use | Not |
|---|---|---|
| The one score the report leads with | the `health` arc gauge | a bar |
| A headline number ("down 68%", "4 of 87") | a `headlines` stat tile | a one-bar chart |
| Several categories, each scored out of 100 | the `scorecard` meter rows | a gauge per category — four dials take four readings where one aligned column takes one |
| The client against named competitors over time | the `competitors` line chart | a bar chart per month, or a dual axis |
| Page-speed metrics, each against its own threshold | `speed.metrics` meter rows | a pie, or a dial per metric |
| Rank at each point of a local search grid | the `local` grid of cells, laid out as a map | a single average, which is the least interesting number on a rank grid |

Two rules the renderer enforces and the audit must not fight:

- **The y-axis starts at zero.** The gap between the client and a competitor is
  the real gap, not a cropped one. A chart that starts at 4,000 to make a
  difference look bigger is the kind of thing that loses the account when
  somebody notices.
- **Every series is direct-labelled and the figures ship as a table under the
  chart.** Two of the four series colours sit below 3:1 contrast on white; that
  is permitted only because nothing in the chart is readable by colour alone.

## The pattern: show the wound, not the stitches

Every weakness section follows the same shape. `finding` and `why` are written
with full rigour and specificity — that is what makes the report credible.
`teaser` is one sentence, names the category of fix, and stops. It never becomes
a how-to.

**Good — specific finding, one-line teaser:**

> **Broken backlinks (High)**
> 47 referring domains — 12 of them DR 40+ — currently link to pages on this
> domain that return a 404. That is authority the site has already earned and is
> actively losing because nothing is catching it. A targeted redirect-mapping
> pass (part of the technical SEO engagement) recovers this without waiting for
> new links to replace it.

**Bad — same finding, written as a free how-to (never do this):**

> **Broken backlinks (High)**
> 47 referring domains link to 404 pages. To fix this: export the full backlink
> list from Site Explorer, cross-reference each target URL against current site
> structure, build a 301 redirect map prioritizing the highest-DR referring
> domains first, implement via .htaccess or the CMS's redirect manager, then
> re-crawl in Site Audit to confirm the chain is resolved and monitor Search
> Console for a pickup in referring-domain-attributed impressions over the
> following 4-6 weeks.

The bad version is not wrong — it is a free deliverable instead of a reason to
hire someone. The line to watch while writing: if a section is explaining *how*
to do something rather than *what is wrong and that it is fixable*, it crossed
from the pitch into the service.

## Canadian English

The report is written in Canadian English, which is its own thing rather than a
halfway house between the other two:

- **-ize and -ization**, like the US: optimize, specialize, prioritize,
  organization. Not optimise.
- **-our and -re**, like the UK: colour, behaviour, favour, centre, theatre.
- **"inquiry", never "enquiry."**
- **licence** (the noun), **defence**, **catalogue**, **travelled**, **cancelled**.

`scripts/check_audit_report.py` flags anything that slips. Run it on the audit
JSON before rendering — that is where the prose actually lives.

## A note on tone

Specific and evidence-backed does not mean alarmist. A report that oversells
every finding as catastrophic reads as a sales trick, not an audit, and
undermines the credible findings along with the weak ones. Calibrate severity
honestly using the rubric in `audit-methodology.md` — a Medium finding stated
plainly is more persuasive than a Medium finding inflated to sound Critical.
