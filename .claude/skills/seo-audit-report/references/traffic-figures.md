# Traffic figures: measured, or estimated

Every audit contains a number for how many people visit the prospect's site.
It is usually the first thing they look at, and it is the easiest thing in the
whole report for them to check, because they can open their own analytics while
reading it.

There are two places that number can come from, and **they are different
claims**. Getting this wrong does not produce a slightly-off report; it
produces a report the prospect can disprove in ten seconds, which costs the
pitch and the credibility of every other figure in the document.

---

## Which one applies

`get_client_profile` returns an `integrations` block:

```json
"integrations": {
  "ga4": true,
  "search_console": true,
  "google_ads": false,
  "traffic_source": "ga4_measured",
  "notes": "GA4 is connected: use it for visitor and session figures …"
}
```

`traffic_source` is the whole decision and takes one of two values.

| `traffic_source` | What you have | How the report may word it |
|---|---|---|
| `ga4_measured` | The client's own Google Analytics | "**1,120** organic visits last month" — stated plainly, as fact, with the period and "from Google Analytics" beside it |
| `ahrefs_estimate_only` | Ahrefs' model of traffic from the keywords it tracks | "Ahrefs estimates around **430** monthly visits from the keywords it can see" — always *estimates*, always *from the keywords it tracks* |

Not a style preference. The second sentence is the only honest form of the
second claim, and shortening it to "430 monthly visits" makes it the first
claim, which we cannot support.

If the profile has no `integrations` block at all, the portal predates this
field — treat it as `ahrefs_estimate_only` and say so, rather than assuming
GA4 is there.

---

## When GA4 is connected

Use it, for all of it:

- **Organic visitors** — sessions where GA4's own channel grouping says
  `Organic Search`, not total sessions. Total sessions includes paid, direct
  and referral, and quoting it as organic overstates the thing the audit is
  about.
- **Total visitors / sessions** — the all-channel figure, when the report wants
  the whole picture.
- **Engagement and bounce** — only from here. `report-template.md` already
  forbids a bounce rate without GA4, and this is the connection that lifts it.

Say the source in the report. "From their Google Analytics, 1 Oct – 31 Oct"
is both more persuasive than an estimate and impossible to argue with.

**Compare equal periods.** GA4 reads `30daysAgo..today` as 31 days. A previous
period built by subtracting that from a 60-day window is 30 days, so every
client appears to have grown by about 3% for free. Ask for 61 days and subtract
the same 31. The portal's metrics reader does exactly this, for exactly this
reason.

---

## When GA4 is not connected

The report still gets written. What changes is the wording and what you do
about it:

1. Label every traffic figure as an Ahrefs estimate, in the sentence itself,
   not in a footnote.
2. **Say that connecting analytics is the fix.** This is a genuinely useful
   finding rather than a hedge: a prospect who cannot see their own organic
   traffic cannot tell whether anything anyone does for them works. Naming that
   is worth more than a confident-sounding number.
3. Leave the bounce rate out entirely.

---

## When the two disagree

They will, and usually by a lot. Ahrefs sees the keywords it has crawled;
GA4 sees everyone who arrived. Neither is wrong.

**Do not reconcile them, average them, or present the flattering one.** If both
are in the report, say what each measures in one clause each. The gap is often
itself the finding: an Ahrefs estimate far below measured organic traffic means
they rank for a long tail nobody is tracking, and far above it usually means
the tracked keywords are not converting into visits at the rate the model
assumes.

---

## The rule underneath all of this

A figure in a client document is either something we measured, something a
named tool estimated and we said so, or absent. There is no fourth option, and
a plausible number is worse than a missing one, because nobody reading it can
tell which of the three it was.
