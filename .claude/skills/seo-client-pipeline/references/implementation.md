# Implementation: From Approved Strategy to Live Site Changes

## Why this stage is different from every other one in this system

Every earlier stage produces a document — an audit, a strategy, a draft. A mistake in a document gets caught on review and costs nothing but time. This stage changes a real, client-facing WordPress site: the first point in the whole pipeline where a mistake is immediately visible to the client's actual customers, not just embarrassing in a review. Hold everything here to a higher bar for confirmation than anywhere else in this skill set, including the confirm-before-write discipline the other skills already use for Ahrefs account changes and portal writes — this is a step above that.

## 1. Get a live path to the site

This stage assumes direct server access — SSH with WP-CLI, SFTP, or equivalent. That access is **not implied by this skill being loaded**; it depends entirely on what's actually connected in this session:

- **Claude Code with configured server credentials** — an SSH key or equivalent set up for the client's server, WP-CLI available there. This is the primary path this skill is built around.
- **A Cowork session linked to a computer that can reach the server** — only if credentials and connectivity are actually set up through it; don't assume this works without checking.

If neither is live, **stop here and say so plainly.** Still produce the change-set in step 2 — it's useful on its own, handed to whoever will apply it manually — but never describe a change as made, in-progress, or scheduled when there's no live path to actually make it.

## 2. Turn the approved strategy into a change-set, not a vague instruction

Before touching anything, translate the approved strategy's concrete outputs — Phase 9's page architecture, Phase 10's internal linking plan, on-page fixes named in the audit, content from `seo-content-production` — into a literal, line-by-line, reviewable table:

| # | Page / URL | Field | Current value | New value | Source (which finding/phase this traces to) |
|---|---|---|---|---|---|

This table **is** the real deliverable of this step, not a formality on the way to the actual work. A genuine implementation pass is routinely 30-50+ rows; don't compress it into a prose summary the person running this can't check line by line against what was actually approved. Every row needs a traceable source — "Phase 9, row 4" or "Audit finding: missing meta descriptions" — never a change invented at this stage that wasn't actually part of what got approved.

## 3. Review and confirm before any write

Present the full change-set and get an explicit go-ahead before applying anything. Batch confirmation is fine for a large group of tightly related, low-risk changes (e.g., "all 18 meta description updates") — but anything structural (page architecture, redirects, navigation, anything that changes a URL that might already be indexed or linked externally) gets its own explicit confirmation, separate from the batch. **Never apply a change that wasn't in the reviewed change-set**, even one that looks like an obvious extension of something that was approved — if it's not a row in the table, it doesn't get applied without going back for a specific yes.

A URL-changing edit needs one more check before it's confirmed: does it need a 301 redirect from the old URL, and is that redirect part of this same change-set row — an architecture change that silently drops an indexed URL without a redirect is a self-inflicted version of exactly the "broken backlinks" finding `seo-audit-report` flags in a prospect's own site.

## 4. Apply, one verifiable step at a time

Work through the confirmed change-set using whatever access is live — WP-CLI commands, direct file edits via SFTP, or the WordPress REST API if that's what's actually connected instead of raw server access. After each change, or each small batch of closely related ones, **verify it actually took** — read the page or field back and confirm the new value is really there, rather than trusting that a command returning success means the change is live. A WP-CLI command can exit 0 and still not produce the intended result (caching, a conflicting plugin, a permissions issue) — the verification step is what catches that before it's discovered during the next monitoring check instead.

If a change fails or only partially applies, stop and say so immediately rather than continuing through the rest of the batch — a partial implementation pass with an unclear boundary of what's actually live is worse than stopping early with a clear one.

## 5. Log everything that actually happened

Build (or append to) an implementation log: every row from the change-set, whether it was applied, when, and the confirmed before/after values — what's actually live, not just what was planned. Format:

| # | Page / URL | Field | Before | After | Status (applied / failed / partial) | Date applied | Confirmed by |
|---|---|---|---|---|---|---|---|

This precise, dated record is what `references/monitoring-and-escalation.md` and `seo-strategy-builder`'s Phase 20 retrospective both depend on to separate "we changed X on this date" from "a Google algorithm update landed the same week" — the Google Update Correlation discipline in `seo-strategy-builder/references/fact-check-registry.md` only works when there's an actual dated record of what was touched, not a recollection of roughly when something happened.

Save it via `save_client_artifact` (`type: "implementation_log"`) if the portal connector supports writing — same write-scoped-token caution as elsewhere: if writing fails or the connector is read-only, say so rather than silently continuing as if it was filed. **Append to the existing log for this client rather than overwriting it** if one already exists — Stage 6 re-implementations extend the same record, they don't start a fresh one.

## What never happens here

No change gets applied that wasn't explicitly reviewed and confirmed as a specific row in the change-set. No change gets applied without a verified, live path to the site — nothing here gets simulated, and nothing gets described as done based on an assumption that it worked rather than a confirmed check. A failed or partially-applied change gets logged as failed or partial, exactly as it happened — never silently dropped from the record because it didn't go as planned.
