# Changelog

All notable changes to the 6ix Developers Portal are documented here.

---

## [1.0.0] — 2026-03-23 — Initial Milestone

### Added — Core Infrastructure
- WordPress child theme `6ixClaude` with 3-portal architecture
- `portal-page.php` template serving all portals
- `class-missing.php` with `Six_Roles`, `Six_Notifications`, `Six_Messaging`, `Six_Checkout`, `Six_Health_Score`
- Custom DB tables: `six_checkout_progress`, `six_assignments`, `six_client_services`, `six_metrics`, `six_messages`, `six_notifications`, `six_recommendations`, `six_reports`
- WP Admin panel (6ix Portal) with integrations settings page, client list, lead pipeline, advisor assignment
- Mobile-responsive dark theme (`portal.css`) with sidebar drawer, hamburger menu

### Added — Onboarding Flow (`/get-started/`)
- Email-first login detection (existing → password field, new → account creation)
- 4-step checkout: Business Profile → Services & Budget → Strategy Confirmation → Agreement & Payment
- Marketing Readiness Score (animated SVG ring, 0–100)
- Interactive marketing question cards (step-by-step reveal)
- Password creation field for new users
- Inline forgot password (branded reset email, no wp-login.php)
- Service selection cards with per-service budget inputs and live impact projection
- Digital signature + Stripe card setup (verify only, no charge)
- Round-robin advisor assignment
- Abandoned checkout tracking via `sendBeacon` + `visibilitychange` fallback
- Welcome email with temporary password

### Added — Customer Portal (`/portal/`)
- Overview: services status, metrics, health score banner, recommendations
- Services tab: active/pending services with budget display
- Reports tab: uploaded reports from advisor
- Messages tab: threaded messaging with assigned advisor
- Billing tab: Stripe card details (brand, last 4, expiry), invoice history, upcoming payment
- Profile tab: personal info, business info, save functionality
- Budget change request flow → advisor notification → approval/decline

### Added — Advisor Portal (`/advisor-portal/`)
- Mission Control overview: stat cards (clients, MRR, meetings today, avg health), Clients Needing Attention table, Today's Meetings panel, Upsell Opportunities
- Clients tab: full client management, metrics CRUD, recommendations, report upload, Google Ads sync
- Messages tab: two-panel messaging center with all client threads
- Alerts tab: notifications with read/unread state
- Approvals tab: service activations + budget change requests with adjust-before-approve
- Reports tab: upload by file or URL
- Revenue tab: MRR overview
- Google Ads tab: MCC setup, per-client Customer ID, manual sync with detailed error messages
- Calendar tab: Google OAuth connect, upcoming meetings grouped by date with Join Meet links

### Added — Sales Portal (`/sales-portal/`)
- Lead Pipeline: Hot (70+) / Warm (40–69) / Cold (<40) classification
- Abandoned Checkouts table with phone + email action buttons
- Call Queue: hot leads only with phone/email CTAs
- Converted Clients tab
- Direct SQL query for reliable abandoned lead detection

### Added — Integrations
- **Odoo 18 SaaS** via XML-RPC (`/xmlrpc/2/common` + `/xmlrpc/2/object`)
  - Contact creation on registration (`res.partner`)
  - CRM lead lifecycle: new → in_progress → abandoned → submitted → active
  - Abandoned onboarding task creation
  - One-time setup endpoint creates custom fields + CRM stages + tasks project
- **Stripe**
  - Setup intents for card-on-file
  - Payment method details (brand, last 4, expiry) from Stripe API
  - Invoice history from Stripe API
  - Webhook handler for 9 event types
  - REST endpoint: `/wp-json/six/v1/stripe-webhook`
- **Google Ads MCC**
  - Single MCC refresh token, per-client Customer ID
  - Google Ads API v20 GAQL metrics sync
  - Detailed per-error-type messages (404, 403, 400, manager account error)
  - Daily cron sync for all clients
- **Google Calendar**
  - Per-advisor OAuth flow with clean redirect URI (`/advisor-portal/gcal/`)
  - `template_redirect` priority-0 callback handler
  - Upcoming events with client name matching by attendee email
  - Join Meet link extraction from conference data
  - Today's meetings on Mission Control overview

### Fixed (during development)
- WordPress nonce timing issue for mid-session account creation (fresh nonce returned after login/register)
- Safari redirect loop after onboarding completion (use `window.location.replace`)
- Duplicate `six_sync_client_gads` handler in `admin-settings.php` hiding real error messages
- Google Ads API version v17 → v20 (v17/v18/v19 all sunset)
- Odoo JSON-RPC `web/dataset/call_kw` → XML-RPC (JSON-RPC requires session cookie, doesn't work on SaaS)
- `Six_Stripe::attach_payment_method()` → `save_payment_method()` (method didn't exist)
- `redirect_uri_mismatch` in Google Calendar OAuth (advisor_id moved to state param)

## [2.7.0] - 2026-07-13 — Google login overhaul

### Fixed
- Google sign-in now follows the same rules as email login in BOTH popup and
  redirect mode: new account → six_customer role + onboarding meta + advisor
  (round-robin) + Odoo contact/lead → onboarding; existing account → routed by
  role (advisor/sales/admin portals, completed customers → /portal/,
  incomplete → resume onboarding)
- New `portal/social-login.php` hooks into Nextend Social Login server-side
  (`nsl_register_roles`, `nsl_register_new_user`, `nsl_login`,
  `google_login_redirect_url`/`google_register_redirect_url`) — previously
  everything depended on a JS event that only fires in popup mode
- Infinite redirect loop on /portal/ for accounts created as 'subscriber' via
  Google (unknown-role fallback redirected /portal/ → /portal/); now routes to
  /get-started/, and get-started repairs role-less accounts automatically
- Onboarding resume for authenticated users (incl. Google redirect-mode):
  prefills name/email/phone and no longer shows the password field
- Popup-mode Google login now honors the server's redirect_url, so advisors /
  sales / admins land in their own portals instead of the onboarding flow
- Admins visiting /get-started/ are sent to wp-admin (branch was unreachable)
- Google signups now get an advisor assigned (was only done for email signups)

## [2.6.0] - 2026-07-07 — Codebase audit: security, broken features, cleanup

### Security
- Onboarding AJAX endpoints (`six_set_user_password`, `six_get_onboarding_state`, `six_save_checkout_step`, `six_complete_onboarding`, `six_generate_growth_plan`, `six_schedule_onboarding_call`) no longer accept arbitrary `user_id`s — new `six_onboarding_resolve_user()` restricts guests to fresh, incomplete customer accounts and logged-in users to themselves (advisors/admins exempt). `six_set_user_password` previously allowed password takeover of ANY account, including admins.
- Fixed broken permission checks that never denied anyone: `six_adv_save_client_profile` (operator-precedence bug), `six_advisor_complete_onboarding` (always-false condition)
- Added missing advisor/admin checks: `six_adv_set_budget`, `six_adv_edit_rec`, `six_save_client_datasource(s)`, `six_sync_odoo_client`

### Fixed
- AI Strategy step: Claude model `claude-sonnet-4-20250514` was retired 2026-06-15 — every call failed and users always got the numeric fallback. Upgraded to `claude-sonnet-5` with thinking disabled
- DB migration v7: adds `schedule_call_*`/`call_scheduled_at` to checkout_progress (missed when the functions.php inline v6 marked v6 done early — broke call scheduling) and `advisor_id`/`updated_at` to client_services (handlers wrote to these non-existent columns — likely root cause of "Approve Service" failures)
- `Six_Roles::get_portal_url()` added — was called in `six_google_login_complete` but never defined (fatal error for advisors/admins logging in via Google)
- Internal hub template moved to `portal/templates/internal-product-hub.php` — portal-page.php looked for it there and showed "Portal view not found"
- Removed duplicate `six_save_checkout_step` registration in ajax-handlers.php that conflicted with the onboarding handler on the same hook
- Notification calls in advisor profile/budget handlers used a wrong (positional) signature — notifications were silently lost
- Migrations now consolidated in `six_onboarding_db_upgrade()` with a table-exists guard; functions.php delegates instead of keeping a diverging copy

### Removed / archived
- `class-growth-engine-v2.php` → docs/archive (never loaded; would infinite-loop with the current abandon handler if wired)
- `functions-additions.php` → docs/archive (setup-instructions snippet, not loaded code)
- `test.html` (deploy pipeline verification file)

## [2.5.0] - 2026-05-26 — Final session fixes

### Fixed
- Service approval: inline onclick replaces broken event delegation; explicit nonce verify returns descriptive errors
- Onboarding: `six_complete_onboarding` now guarantees all 40+ questionnaire fields save at step 5 completion
- Onboarding: `resumeLoggedIn` fetches saved state via `six_get_onboarding_state` AJAX — all fields restored on refresh
- Multi-service Next button: added missing `getHoursVal()` function that was causing ReferenceError crash
- DB migration v6: auto-adds `ads_schedule`, `seo_competitors`, `seo_crm_tools`, `seo_reviews`, `seo_extra_info`, `gbp_hours` columns
- Advisor dashboard: WP user meta backfill for clients with empty checkout rows
- Customer dashboard: same backfill; Growth Opportunities removed from overview
- Questionnaire: empty-state now only shows when truly no data exists; shows business name when available
- Approve service: `six_adv_add_client_service` handler added; updates existing service instead of erroring "already exists"
- Functions.php: auto-run DB v6 migration on init (no manual URL trigger needed)
- PHP parse errors resolved in advisor-dashboard.php (extra endifs, stray backslash-dollar)

### Added
- `six_get_onboarding_state` AJAX handler — returns full S.q/S.svcs/S.budgets for refresh resume
- `getHoursVal(pfx)` JS function — reads qHours day/AM/PM widget values
- Drop-off funnel in Intelligence tab — shows client count per onboarding step
- Notification center: categorized, paginated, mark-read per notification
- Intelligence section: rebuilt clean — no emojis, summary cards, filter pills
- Advisor service approval: `sixApproveService()` named function with full error surfacing
