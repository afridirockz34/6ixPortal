# Working in this repository

A WordPress child theme (`6ixClaude`) holding 6ix Developers' client portal, plus
the `.claude/skills/` set that does the actual SEO work for clients.

Layout and file inventory are in `PROJECT_CONTEXT.md` and `README.md` — read
those for *where things are*. This file is for *what goes wrong*.

---

## Before you say it works

```bash
bash tools/tests/run_all.sh
```

Runs everything: the SEO skills' usability and drift checkers, the audit
renderer suites, and the GA4/Search Console path. It must pass before any claim
that something is fixed, and it runs from any working directory.

PHP changes also need `php -l` on each touched file — CI lints every `.php` in
the repo and a parse error takes the whole deploy down.

| Command | What it protects |
|---|---|
| `bash tools/tests/run_all.sh` | everything below, in one go |
| `python3 tools/check_seo_skills.py` | the skills still work read cold: frontmatter, every path reference, every bundled script |
| `php tools/check_skill_drift.php` | metric keys and profile stages the skills quote vs. the code that emits them |
| `php tools/generate-questionnaire-reference.php` | regenerates the questionnaire docs from the schema — run after any question change, and commit the result |

Pushing to `main` deploys to the live site over SFTP. There is no staging.

---

## The mistake this codebase keeps making

**Writes that fail while reporting success.** It has happened with the
questionnaire (a column too narrow), the KPI table (never created), and the
wizard metrics (written to the wrong table). In every case the UI said "saved".

So:

- `dbDelta` **does not ALTER an existing table** when the statement says
  `CREATE TABLE IF NOT EXISTS` — its table-name regex captures `IF`. A column
  added to an existing CREATE statement is never applied to a live database.
  Add columns explicitly, gated on a schema signature, the way
  `six_seo_questionnaire_ensure_columns()` does.
- MySQL runs in **strict mode**. An `UPDATE` naming a column that does not
  exist, or a value longer than its column, fails the *entire statement* — not
  just that field. Batch writes need a per-column retry or they lose everything
  on one bad field.
- Wrap AJAX writes in `$wpdb->suppress_errors()`. An unsuppressed `print_error`
  writes HTML into the response body and corrupts the JSON.
- When a write fails, **say so in the response**. Silently skipping it is how
  all of the above survived as long as they did.

---

## What we are not allowed to tell a client

These are commitments to clients, not style preferences. They outrank
convenience, and a plausible-looking number is worse than an absent one because
nobody can tell it apart from a real one.

- **No ROI, revenue or lead projections.** 6ix Developers does not promise
  returns. `seo_lifetime_value` and `seo_repeat_business` rank which keywords
  are worth competing for; they are never multiplied out into a figure.
- **Never state a client's own traffic as fact from Ahrefs.** Ahrefs estimates
  visits from the keywords it tracks. That is a different claim from "visits",
  and the client can check it against their analytics.
- **No invented measurements.** No bounce rate without GA4 connected, no page
  speed without a real PageSpeed run. An absent section is honest; a filled-in
  one is not. Mark gaps `[NEEDS DATA: …]`.
- **Canadian English in client-facing reports** — `-ize` and `-our` together,
  "inquiry" never "enquiry". `check_audit_report.py` enforces it.

---

## The SEO skills

`.claude/skills/seo-{audit-report,strategy-builder,content-production,client-pipeline}/`
are used in client sessions, not only here, so they must read correctly with no
knowledge of this repository. Qualify cross-skill references by full path
(`seo-strategy-builder/references/local-and-gmb.md`, not the bare filename), and
re-run `tools/check_seo_skills.py` after editing one.

Two data traps are documented at length and worth knowing before touching
anything local:

- A BrightLocal rank grid's `rank: 0` means **did not appear**, not rank zero.
  Averaging raw values scores the invisible points as the best ones.
- `summary.avg_rank` already accounts for that. Quote it; never recompute it.

---

## Conventions

- Work on the branch you were given; never push to `main` directly.
- Don't open a pull request unless asked.
- Match the surrounding style — this codebase is WordPress-flavoured PHP with
  tabs in `portal/`, and inline `<style>`/`<script>` in templates.
- Comments explain *why*, especially where the obvious approach is wrong. The
  `dbDelta` note above exists because someone will otherwise try it again.
