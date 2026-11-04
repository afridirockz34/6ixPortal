---
name: "seo-audit-report"
description: Generate a pre-sales SEO weakness/audit report for a prospect — pulls live Ahrefs data (domain authority, backlinks, organic keywords, technical/on-page issues via Site Audit, content gaps, AI-visibility) plus the prospect's own profile pulled live from the 6ix Developers portal (their questionnaire answers, target keywords, named competitors, team credentials and any regulatory constraints), scores the findings by severity, and writes a persuasive, evidence-backed report naming what's wrong and what it's costing them — without handing over the full fix. Companion to seo-strategy-builder and seo-content-production — this is the pre-signature pitch artifact, while those two are what gets built once the prospect becomes a client. Trigger on "audit [domain/client] for a proposal", "build a weakness report for [prospect]", "show [prospect] what's wrong with their SEO", "pre-sales SEO audit", or "what would we pitch [prospect] on".
---

# SEO Audit Report

## Why this exists

`seo-strategy-builder` and `seo-content-production` assume someone has already signed on as a client — they build the real, full-depth strategy and content once there's an engagement. This skill is upstream of that: it produces the document that *gets* someone to sign on. The job is different in a specific way that shapes everything below: it has to be rigorous and evidence-backed (never invent a number, never pad a weakness that isn't real), but it is deliberately not a free consulting engagement. A prospect should finish reading it convinced something is costing them business and that 6ix Developers is the right people to fix it — not holding a ready-to-execute fix list they can hand to anyone.

That tension — be completely honest about what's broken, but don't give away how to fix it — is the one piece of judgment this skill exists to encode. Everything else (what to check, how to score it, how to structure the report) is mechanical once that line is held.

## Inputs

At minimum: the prospect's domain. Everything else improves the report's relevance but isn't required to run it:

- **Their profile in the 6ix Developers portal**, via the portal connector (see the next section). This is the richest input by far and the first place to look: it holds the questionnaire they filled in — brand story, ideal client, target keywords and market, competitors, team credentials, content capacity, and crucially any compliance constraints. Regulated professions change what's safe to say about a "fix".
- **A filled client questionnaire document**, if they sent one back instead of filling the portal form. Same content, different delivery — read it the same way.
- If neither exists, the audit still runs as a pure data-driven opportunity scan off Ahrefs alone. Say so explicitly in the report's framing (something like "this audit reflects what's publicly measurable; a full strategy would also account for \[business specifics]") rather than presenting a data-only scan as if it had the same depth as one informed by the client's own answers.

## Before starting anything: check whether Ahrefs is connected

This skill leans entirely on live Ahrefs data — a persuasive audit is a data-backed one. Check this session's tool list for tools named `mcp__Ahrefs__*` (or search for them if deferred). If they're there:

- Call the `doc` tool for a given Ahrefs tool before calling it for the first time — the MCP server requires this and it's where the exact parameters live.
- Monetary fields (`value`, `org_cost`, `paid_cost`, `traffic_value`) come back in **USD cents** — divide by 100 before writing a dollar figure into the report.
- When a tool's response carries `render_with` metadata, call that render tool on the data — don't summarize or hand-transcribe raw numbers past it.
- Read `references/audit-methodology.md` for which Ahrefs tool group covers which audit category — don't improvise tool choice category by category.
- Some things this audit needs (a fresh Site Audit crawl, in particular) take real time to run if one doesn't already exist for the domain. If a crawl needs to be triggered, say so and wait for it rather than writing findings from a stale or partial crawl.
- Anything that **creates or changes something in the live Ahrefs account** (starting a new Site Audit project, adding a tracked project, adding competitors to a project) is a real, persistent action on the account, not a read. Confirm with the person running this before doing it — don't spin up a new tracked project just to answer a one-off audit question if a one-off crawl/lookup will do.

If Ahrefs isn't connected in this session: say so, and either ask for exported data (Site Explorer overview, a recent Site Audit export) or produce a narrower report clearly scoped to what's checkable without it (on-page issues reachable by reading the live site directly, for instance) — never fill the gap with a plausible-sounding number.

## Then: get whatever context exists on the prospect

An audit informed by the prospect's own answers beats a data-only scan every time — it tells you which findings will actually land. That context arrives three ways, and **the audit runs regardless of which one you get**. `seo-strategy-builder/references/client-context.md` covers all three in full; the short version:

**Route 1 — the portal.** Look for the connector in this session's tool list (`list_clients`, `get_client_profile`; search for them if deferred). If it's there:

1. **`list_clients` with `search`** — resolve the business or person's name to a `client_id`. You will almost never be handed an id; you'll be handed "audit Acme Plumbing". Search by business name, person's name, or email.
2. **`get_client_profile`** with that id. Read it before pulling a single Ahrefs number — it tells you which findings will actually matter to this business.
3. **`list_client_artifacts`** — check whether a recent audit already exists for them before regenerating an expensive one.

What to take from the profile, and what it changes:

- **`questionnaire.compliance`** — read this first and read it properly. The regulator, the claims they're not permitted to make, rules on testimonials and reviews, required disclaimers. This is a hard constraint on the report, not colour: if they can't advertise guaranteed results, the report must not imply we'd deliver them, and a "fix teaser" must not promise a protected term.
- **`questionnaire.onpage`** — their own target keywords, whether they're local or national, which cities, whether they want maps or organic prioritised, previous SEO work, how enquiries actually arrive, and their most profitable services. A weakness on a keyword that drives their profitable work outranks a bigger weakness on one that doesn't. `seo_lifetime_value` and `seo_repeat_business` are context for which keywords are worth competing for — **never multiply them out into a projected return or ROI figure.** 6ix Developers does not make revenue projections, and a report that does is a report we can't stand behind.
- **`questionnaire.brand`** — their story, ideal client, pain points and how they differ. Use this for the report's voice and for framing why a weakness costs *them* specifically, rather than a generic business.
- **`questionnaire.team`** — credentials, licences, media. Directly feeds the E-E-A-T category: a practice with three licensed specialists and no author bios on the site is a genuine, specific finding.
- **`positioning.competitors`** — benchmark against the competitors *they* named, not just whoever Ahrefs returns as organic competitors. Do both, and say when the two disagree; that gap is often itself the most interesting finding in the report.
- **`questionnaire_progress`** — if large parts are unanswered, the audit is thinner than it could be. Say so in the framing, and tell the person running this which sections to chase.

**Route 2 — a questionnaire they filled in and sent back.** Prospects often prefer a Word document over email to filling in a portal form, especially before they've signed anything. Read it the same way and pull the same facts — `.docx`, PDF, a pasted email, or a call transcript all count, and it doesn't need to match the questionnaire's wording. If the portal connector is available and they already exist there as a lead, offer to file their answers with `update_client_profile` so the information isn't stranded in an inbox; `seo-strategy-builder/references/questionnaire-fields.md` maps each question to its key.

**Route 3 — neither.** Entirely normal for a cold prospect. Run the audit on Ahrefs and the live site alone, and say so in the framing: that it reflects what's publicly measurable, and that a full picture would also account for their margins, constraints and which services they actually want to grow. Then name the two or three answers that would most change the conclusions — that's a stronger close than pretending the gap isn't there.

Never invent a profile, and never treat a missing one as a blocker.

**When the report is finished**, if they exist in the portal, save it with **`save_client_artifact`** (`type: "audit_report"`) so it lands on their record and the next session can see it was already done. Writing needs a write-scoped token; if the connector is read-only, say so rather than silently skipping it.

## Building the report

1. **Gather context.** Pull their portal profile as above (or read the questionnaire document if that's what you have). Confirm the domain. If they're in a regulated field, write the constraint down now, before any data — it governs what every "what to do about it" line is allowed to promise.
2. **Pull the data.** Work through `references/audit-methodology.md`'s category list, pulling each via its mapped Ahrefs tool group. Don't skip a category because it looks fine at a glance — a category with no real finding just gets a short "healthy" line in the scorecard, which is itself useful signal (it tells the reader this wasn't a rubber-stamp report).
3. **Score each real finding's severity** using `references/audit-methodology.md`'s rubric (Critical / High / Medium / Low) — grounded in the actual numbers pulled, not a gut feel. A finding with no supporting number isn't a finding yet; go get the number or drop it. Then compute the aggregate **SEO Health Score (0-100)** per that same file's scoring section — one weighted number across all categories, redistributing the weight of any category that wasn't run. This becomes the lead line of the executive summary and a column in the scorecard; never publish it built partly on a guess.
4. **Route anything about schema, AI-citation tactics, GMB, or review policy through `seo-strategy-builder/references/fact-check-registry.md`** before writing a recommendation sentence about it — this skill doesn't keep its own copy of that registry; reuse the one that's already kept current.
5. **Write the report** following `references/report-template.md` — executive summary, scorecard, one section per weakness category (what's wrong, why it costs them business, a one-sentence teaser of the fix, never the full fix), then a closing section naming the service that would address it and a clear next step. Keep every teaser to the "show the wound, not the stitches" line the template demonstrates.
6. **Run the check before calling it done**: `python scripts/check_audit_report.py <report_file>`. It flags unresolved `[NEEDS DATA]` placeholders, any weakness section missing a severity rating, a missing closing CTA, and any section that reads like it drifted into a full step-by-step fix instead of a one-line teaser.
7. **File it against their record.** If they're in the portal, `save_client_artifact` with `type: "audit_report"` — the finished report, the SEO Health Score, and the date. It then shows on their profile, and the next person to open them sees the audit already exists instead of commissioning it twice.

## Why the "show the wound, not the stitches" line matters

It's tempting, especially when the data makes a fix obvious, to just write the fix — it feels more helpful and it's not hard to do. It's also the single easiest way to turn a sales document into a free consulting engagement. The discipline isn't "be vague" — findings should be as specific and well-evidenced as anything in a paid strategy document. The discipline is stopping at *what's wrong and what it's costing them*, and naming *that fixing it is part of the SEO engagement* instead of writing the fix itself. See `references/report-template.md` for a worked good-vs-bad example of exactly where that line sits.

## Reference files

- `seo-strategy-builder/references/client-context.md` — getting the prospect's answers from the portal, a returned document, or neither
- `references/audit-methodology.md` — the audit category checklist, which Ahrefs tool group covers each one, and the severity scoring rubric
- `references/report-template.md` — report structure and the show-the-wound-not-the-stitches pattern, with a good/bad example
- `assets/sample-weakness-section.md` — one fully worked example category section, for calibrating tone and length
- `scripts/check_audit_report.py` — checks for unresolved placeholders, missing severity ratings, a missing CTA, and sections that read like a full fix instead of a teaser
