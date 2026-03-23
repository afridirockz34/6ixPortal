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
