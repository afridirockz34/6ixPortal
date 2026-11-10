# Measured, or estimated

An audit is a document about somebody's own website, handed to the person who
owns it. They can check it against their own analytics while reading. So the
first question on any number in it is not "is this roughly right" but **"did we
measure this, or did a third party estimate it?"**

Those are different claims, and presenting the second as the first is the one
mistake that costs the whole report: the moment a prospect finds one figure
they can disprove, every other figure becomes a claim rather than a fact.

---

## The tools that serve this

The portal connector reads the client's connected accounts directly. These are
the calls, and they are the reason the distinction below is actionable rather
than theoretical:

| Tool | Returns | Takes |
|---|---|---|
| `get_live_metrics` | GA4 sessions and channel split, Search Console totals, Google Ads, Meta — each with its own `status` | `client_id`, optional `days`, optional `sources` |
| `get_search_console_queries` | The real query list with impressions, clicks, CTR and position | `client_id`, optional `days`, `limit` |
| `get_pagespeed` | Google's own performance score and Core Web Vitals | a `url` — no client needed, so it works for a cold prospect |

`get_live_metrics` reports **three different outcomes per source**, and they
must not be collapsed into each other:

- `ok` — a measured figure. State it as fact and name the period.
- `not_connected` — the account was never linked. Not a zero. Use an estimate
  and say so, and note that connecting it is itself worth doing.
- `error` — connected but Google or Meta refused, with the reason attached.
  Not a zero either. Say which source failed rather than reporting the gap as
  a result.

A client with no analytics connected and a client whose analytics broke this
morning look identical in a report that prints "0" for both. They are not the
same, and neither of them is zero.

## Step one, before any data is pulled

`get_client_profile` returns an `integrations` block:

```json
"integrations": {
  "ga4": true,
  "search_console": true,
  "traffic_source": "ga4_measured",
  "ranking_source": "gsc_measured",
  "own_domain_data": "full",
  "notes": "…"
}
```

Two **separate** verdicts, because a client can easily have one and not the
other:

| Field | Answers | `*_measured` means | `ahrefs_estimate_only` means |
|---|---|---|---|
| `traffic_source` | How many people came | GA4 is connected — use it | Ahrefs' model of visits from keywords it tracks |
| `ranking_source` | What Google showed them for | Search Console is connected — use it | Ahrefs' crawl of where it thinks they rank |

`own_domain_data` is the quick version: `full`, `partial` or `none`.

If there is no `integrations` block at all, the portal predates this field —
treat both as `ahrefs_estimate_only` rather than assuming the connection is
there.

Do this **before** the data pull in step 2, not after. It changes which tool
each category is pulled from, so finding out afterwards means pulling twice.

---

## When Search Console is connected

Search Console is not a better estimate of rankings. It is **what Google
recorded**: every impression it served, what it served, and where. Nothing
third-party can outrank that for this domain.

Take from it:

- **Impressions, clicks and CTR** — the real demand, and the real share of it
  they capture.
- **Average position** — their actual average, not a crawler's sample.
- **The query list** — what they *actually* get shown for, which is routinely
  not what they told us in the questionnaire. That gap is frequently the single
  most useful finding in the whole audit: they are optimising for one phrase and
  being found for another.

The query list also gives you findings nothing external can: queries with high
impressions and near-zero clicks (they rank but the title is not earning the
click), and queries ranking 11–20 (one page of effort from real traffic).

## When GA4 is connected

- **Organic visitors** — sessions where GA4's own channel grouping says
  `Organic Search`, never total sessions, which includes paid, direct and
  referral.
- **Total sessions** — when the report wants the whole picture.
- **Engagement and bounce** — only from here. `report-template.md` forbids a
  bounce rate without GA4, and this is the connection that lifts that.

**Compare equal periods.** GA4 reads `30daysAgo..today` as 31 days, so a
previous period built by subtracting that from a 60-day window is only 30 days
— and every client appears to have grown about 3% for free. Ask for 61 and
subtract the same 31.

---

## What Ahrefs is still for

This is not "stop using Ahrefs". Ahrefs remains the only option for the things
the client's own accounts cannot see, and those are a large part of an audit:

| Still Ahrefs | Why |
|---|---|
| **Competitors** — traffic, keywords, growth | You will never have a competitor's analytics. Measured from outside is the only way it can be done. |
| **Backlinks and referring domains** | Search Console's link report is partial and not queryable the same way. |
| **Keyword volume and difficulty** | Search Console reports what they already get shown for, not the size of a market they are absent from. |
| **Content gaps** | By definition about pages that do not exist yet. |

So the division is by *subject*, not by tool preference: **their own domain
from their own accounts; everything outside it from Ahrefs.**

---

## The trap when both are in the report

Do not put a measured figure and an estimated figure on the same axis.

A competitor chart plotting the client's GA4 sessions against competitors'
Ahrefs estimates is not a comparison — the client's line is everyone who
arrived, and each competitor's line is a model of visits from tracked keywords.
Those routinely differ by multiples in either direction, so the chart's shape
is an artifact of the sources, and the one series that is most accurate is the
one that looks least like the others.

**One source per comparison.** For the competitor chart that means Ahrefs for
every series, the client included, labelled as estimates — which is honest and
still makes the point, because the series are then like for like. Report the
measured figures separately, in the executive summary or a stat tile, where
they stand on their own and say where they came from.

---

## When the two disagree about the same thing

They will, often by a lot. Ahrefs sees what it crawled; GA4 and Search Console
see what happened. Neither is lying.

**Do not reconcile them, average them, or quote whichever is more flattering.**
If both appear, say in one clause each what they measure. The gap is frequently
itself the finding:

- Ahrefs estimate far **below** measured organic traffic → they rank for a long
  tail nobody is tracking, and the audit has been understating them.
- Ahrefs estimate far **above** it → the tracked keywords are not converting
  into visits at the rate the model assumes, usually a titles-and-snippets
  problem that Search Console's CTR column will confirm.

---

## If neither is connected

The audit still runs — it is a pre-sales document and most prospects will not
have granted us anything. What changes:

1. Label every traffic and ranking figure as an Ahrefs estimate, in the
   sentence itself, not a footnote.
2. Leave the bounce rate out entirely.
3. **Say that connecting analytics is itself a finding.** A business that
   cannot see its own organic traffic and query data cannot tell whether
   anything anyone does for them works. Naming that is worth more than a
   confident-sounding number, and it is true.

---

## The rule underneath all of it

A figure in a client document is either something we measured, something a
named tool estimated and we said so, or absent. There is no fourth option, and
a plausible number is worse than a missing one, because nobody reading it can
tell which of the three it was.
