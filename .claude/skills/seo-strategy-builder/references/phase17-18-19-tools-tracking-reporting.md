# Phase 17 — Tech Stack Integration, Phase 18 — Local Tracking Setup, and Phase 19 — Reporting/KPIs

These three are lower-leverage for Claude than most other phases — they're closer to documentation and configuration than to research or writing. Don't over-invest prompt effort here; the value is in getting them right and current, not elaborate.

## Phase 17 — Tech Stack Integration

**What it produces:** documentation of which tool does what, and how they connect (SEO platform → keyword/content planning, SEO plugin → on-page/schema, CMS → publishing, rank tracker → monitoring, reporting tool → client-facing dashboards).

**Required inputs:** the actual tools chosen for this client — Claude can document a workflow once tools are chosen, but shouldn't be asked to pick a paid platform; that's a budget and existing-stack decision for the person running the engagement.

**Prompt pattern:**
```
[Client]'s tech stack: SEO data platform: [e.g. Ahrefs]. SEO plugin:
[e.g. Rank Math]. CMS: [e.g. WordPress]. Rank tracker: [tool or "same as
SEO platform"]. Reporting: [tool].

Document the workflow: where each piece of content and data originates,
what tool it moves through, and where it ends up. Flag any step in the
workflow that currently requires manual work versus one that's connected
via an API/integration, based on what's actually configured — don't
assume a connection exists without confirming it.
```

## Phase 18 — Local Tracking Setup

**What it produces:** a local rank-tracking configuration — which keywords, which locations (if the business serves multiple cities), tracked at what cadence.

**Required inputs:** the service area tiers from the foundation document and the priority local keywords from Phase 7/8. If the chosen rank tracker isn't Ahrefs, or Ahrefs isn't connected, the actual setup happens by clicking through the chosen tool's settings UI — this is mostly manual, low-Claude-leverage work, and the output below is a configuration list for a human to enter.

**If Ahrefs is connected and is the chosen rank tracker**, this phase can go further than a list for a human to enter: `management-projects-create` to set up the tracked project, `management-project-keywords-add` for the priority local keywords, `management-locations` to scope tracking to the right service areas. This writes a real, persistent project into the client's Ahrefs account — confirm with the person running this before creating it, the same way any other account-changing Ahrefs action in this skill needs confirmation first.

**Prompt pattern:**
```
Here is [Client]'s service area tiers and priority local keywords: [paste].

Produce a local tracking configuration list: which keywords to track,
tracked from which locations (matching the service area tiers), and a
recommended check-in cadence (weekly is standard for local SEO). This is
a configuration list for a human to enter into the tracking tool, not a
tool that gets configured by this output alone.
```

## Phase 19 — Reporting + KPIs

**What it produces:** the reporting framework — which metrics, what cadence, what format — built from the baseline in the foundation document and the targets from Phase 5.

**Required inputs:** Phase 1's baseline metrics, Phase 5's targets.

**Prompt pattern:**
```
Here is [Client]'s foundation document (baseline) and Phase 5 targets:
[paste].

Produce a reporting framework: which metrics to report, at what cadence
(monthly is standard), and in what format. Report impressions, branded
search volume, and conversions (calls/form submissions) alongside
sessions — do not build the framework around organic sessions as the
sole headline metric. With AI-generated answers absorbing a growing
share of informational search, impressions can hold or grow while
clicks fall; a sessions-only report can misread a genuinely successful
campaign as a failing one.
```

**Why the multi-metric framework matters more than it looks:** this is one of the clearest places a stale reporting template actively misleads a client, rather than just under-delivering. Check `references/fact-check-registry.md`'s reporting entry before finalizing this phase for any client.

**If Ahrefs is connected**, this phase stops being a one-time framework document and becomes something that can actually run on a recurring cadence: pull `rank-tracker-overview` and `site-explorer-metrics-history` for the organic/ranking side, `web-analytics-*` tools if Ahrefs Web Analytics is set up for the client, and `gsc-*` tools if the client's Search Console is linked in Ahrefs (this is specifically where the impressions metric this phase requires comes from — don't skip it just because it's an extra tool group). Brand Radar tools (`brand-radar-mentions-overview`, `brand-radar-impressions-overview`) cover the AI-visibility side if Phase 11 set that up. When asked to produce "this month's report" for an existing client rather than the initial framework, treat it as: pull all of the above fresh, compare against last period and against Phase 5's targets, and write the findings into the framework's format — don't re-derive the framework itself each time.

**Before attributing a move in any of these metrics to a specific cause** — "traffic dropped because of X," "the core update caused the dip," "the June content push drove this jump" — check that date window against a dated, confirmed list of Google algorithm updates (Google's Search Status Dashboard / Search Central blog) rather than assuming a date overlap proves causation. This comes up constantly in recurring reporting specifically, because every report invites a "why did this change" explanation. See `references/fact-check-registry.md`'s "Google Update Correlation" section — state a plausible overlap as a hypothesis, never as the report's stated cause, especially when the client's own change (a redesign, a content push, a technical fix) happened in the same window and is at least as plausible an explanation.
