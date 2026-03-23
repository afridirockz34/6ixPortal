# 6ix Developers — Marketing Operating System
## Project Journal — Milestone Save
### Date: March 23, 2026

---

## SITE DETAILS

| Item | Value |
|---|---|
| Live URL | https://6ixdevelopers.com/6ix-redesign/ |
| WordPress subfolder | `/6ix-redesign/` |
| Theme | `6ixClaude` (child of Divi) |
| Theme path | `/wp-content/themes/6ixClaude/` |
| Portal path | `/wp-content/themes/6ixClaude/portal/` |

---

## COMPLETE FILE MAP

### Theme Root
| File | Purpose |
|---|---|
| `portal-page.php` | WordPress template for all 3 portal pages |
| `onboarding-page.php` | Deprecated — replaced by `six_serve_onboarding_page()` hook |
| `functions.php` | Paste `functions-additions.php` contents here |

### Portal Directory (`/portal/`)
| File | Purpose |
|---|---|
| `class-missing.php` | Core classes: `Six_Roles`, `Six_Notifications`, `Six_Messaging`, `Six_Checkout`, `Six_Health_Score`, shortcodes |
| `class-odoo.php` | Odoo 18 SaaS integration via XML-RPC (correct auth method) |
| `class-stripe.php` | Stripe setup intents, invoices, payment method details |
| `class-google-ads-calendar.php` | Google Ads MCC + Google Calendar OAuth |
| `admin-settings.php` | WP Admin panel + Google Calendar OAuth callback handler |
| `ajax-handlers.php` | All portal `wp_ajax_` hooks |
| `ajax-onboarding.php` | Onboarding-specific AJAX + password handlers |

### Templates (`/portal/templates/`)
| File | Purpose |
|---|---|
| `onboarding.php` | Full onboarding flow (4 steps, score, services, payment) |
| `customer-dashboard.php` | Customer portal (Overview, Services, Reports, Messages, Billing, Profile) |
| `advisor-dashboard.php` | Advisor portal (Mission Control, Clients, Calendar, Google Ads, etc.) |
| `sales-dashboard.php` | Sales pipeline (Hot/Warm/Cold leads, Abandoned Checkouts, Call Queue) |

### Assets (`/portal/assets/`)
| File | Purpose |
|---|---|
| `portal.css` | All portal CSS including dark theme, mobile responsive |

---

## WORDPRESS SETUP

### Pages Required
| Slug | Template | Notes |
|---|---|---|
| `portal` | Portal Page | Customer dashboard |
| `advisor-portal` | Portal Page | Advisor dashboard |
| `sales-portal` | Portal Page | Sales dashboard |
| `get-started` | Portal Page | Onboarding (served by hook, template doesn't matter) |

### User Roles
- `six_customer` — portal customers
- `six_advisor` — advisor portal access
- `six_sales` — sales portal access

### Custom DB Tables
All created by visiting `/wp-admin/?six_install=1`:
- `six_checkout_progress`
- `six_assignments`
- `six_client_services`
- `six_metrics`
- `six_messages`
- `six_notifications`
- `six_recommendations`
- `six_reports`

---

## INTEGRATIONS

### Odoo (XML-RPC — works on Odoo 18 SaaS)
| WP Option | Value |
|---|---|
| `six_odoo_url` | https://yourcompany.odoo.com (no trailing slash) |
| `six_odoo_db` | database name (subdomain on Odoo.com) |
| `six_odoo_username` | Odoo login email |
| `six_odoo_api_key` | From Odoo → name → Preferences → Account Security → API Keys |
| `six_odoo_stage_new` | Auto-set by `/wp-admin/?six_odoo_setup=1` |
| `six_odoo_stage_inprogress` | Auto-set by setup |
| `six_odoo_stage_submitted` | Auto-set by setup |
| `six_odoo_stage_active` | Auto-set by setup |
| `six_odoo_project_id` | Auto-set by setup |

**One-time setup:** `/wp-admin/?six_odoo_setup=1`
Creates all custom fields on `crm.lead`, 4 CRM stages, tasks project.

**Lead status flow:**
1. User registers → contact created in `res.partner` + lead status `new`
2. Step 1 complete → lead status `in_progress`
3. User abandons → lead status `abandoned` + task "Abandoned Onboarding Process" created
4. Checkout complete → lead status `submitted`
5. Advisor activates → lead status `active`

### Stripe
| WP Option | Value |
|---|---|
| `six_stripe_publishable_key` | `pk_live_...` |
| `six_stripe_secret_key` | `sk_live_...` |
| `six_stripe_webhook_secret` | `whsec_...` |

**Webhook URL:** `https://6ixdevelopers.com/6ix-redesign/wp-json/six/v1/stripe-webhook`
**Events to enable:** `setup_intent.succeeded`, `payment_intent.succeeded`, `payment_intent.created`, `invoice.created`, `invoice.paid`, `checkout.session.completed`, `checkout.session.expired`, `checkout.session.async_payment_failed`, `checkout.session.async_payment_succeeded`

### Google Ads (MCC)
| WP Option | Value |
|---|---|
| `six_gads_developer_token` | From Google Ads → API Center |
| `six_gads_manager_id` | MCC account ID (digits only) |
| `six_gads_client_id` | OAuth Client ID from Google Cloud Console |
| `six_gads_client_secret` | OAuth Client Secret |
| `six_gads_refresh_token` | MCC refresh token (single token for all clients) |

**Per-client:** Advisors set `six_gads_customer_id` user meta (client's individual account ID, NOT MCC ID)
**API version:** v20 (update periodically — v17/v18/v19 are all sunset)

### Google Calendar (per-advisor OAuth)
| WP Option | Value |
|---|---|
| `six_google_client_id` | OAuth Client ID (Calendar scope) |
| `six_google_client_secret` | OAuth Client Secret |

**Redirect URI** (register in Google Cloud Console → Credentials → Authorized Redirect URIs):
```
https://6ixdevelopers.com/6ix-redesign/advisor-portal/gcal/
```

**Per-advisor user meta:**
- `six_gcal_refresh_token` — stored by OAuth callback
- `six_gcal_access_token` — cached, auto-refreshed
- `six_gcal_token_expires` — expiry timestamp
- `six_gcal_email` — connected Google email for display

---

## KEY ARCHITECTURAL DECISIONS

### Why template_redirect for get-started and gcal callback
Both `/get-started/` and `/advisor-portal/gcal/` are served by `add_action('template_redirect', ..., 0)` hooks rather than WordPress page templates. This fires before Divi, before any plugin, before any auth redirect can interfere. WordPress never loads a template — the hook outputs HTML and calls `exit`.

### Why XML-RPC for Odoo (not JSON-RPC)
Odoo 18 SaaS uses Odoo Online authentication — users have no local password for `/web/dataset/call_kw`. That endpoint requires a live browser session cookie. XML-RPC at `/xmlrpc/2/common` and `/xmlrpc/2/object` accepts the API key as the password parameter and works stateless on every request.

### Why single MCC token for Google Ads
One MCC refresh token authenticates as the manager account. Per-client access is granted via the `login-customer-id` header (MCC ID) + the customer ID in the URL path. Advisors only need to enter the client's 10-digit Customer ID — no per-client OAuth needed.

### Nonce timing issue in onboarding
WordPress nonces are tied to the current user. When a new user is created mid-session via `wp_set_auth_cookie()`, subsequent AJAX calls fire as a logged-in user but the nonce was minted when they were a guest. The fix: `six_create_partial_account` and `six_portal_login` both return a fresh `nonce` in their JSON response, and the JS updates `S.nonce` immediately.

---

## PENDING / FUTURE WORK

- [ ] Google Ads API version — update from v20 to latest when new versions release
- [ ] Odoo task generation enhancements — client journey monitoring, milestone tasks
- [ ] `portal/assets/portal.js` — extract from portal HTML and host separately
- [ ] REST endpoints file (`rest-api.php`) — not yet created
- [ ] Readiness Assessment quiz frontend form
- [ ] Multi-step checkout page UI (`portal/templates/checkout.php`)
- [ ] Google Calendar — booking flow for clients to book meetings with advisors
- [ ] Google Ads — automated weekly performance report generation
- [ ] Odoo — sync active client status when advisor approves services
- [ ] Customer dashboard — password change form in profile tab
- [ ] Mobile menu — add swipe-to-close gesture on mobile

---

## FUNCTIONS.PHP REQUIRE BLOCK

```php
define('SIX_PLUGIN_DIR', get_stylesheet_directory() . '/portal/');
define('SIX_PLUGIN_URL', get_stylesheet_directory_uri() . '/portal/');
require_once SIX_PLUGIN_DIR . 'class-missing.php';
foreach ([
    'class-odoo.php',
    'class-stripe.php',
    'class-google-ads-calendar.php',
    'ajax-handlers.php',
    'admin-settings.php',
    'ajax-onboarding.php',
] as $file) {
    if (file_exists(SIX_PLUGIN_DIR . $file)) require_once SIX_PLUGIN_DIR . $file;
}
```

---

## BRAND

- **Pink:** `#FF6699`
- **Blue:** `#3C6478`
- **Cyan:** `#83C5ED`
- **Dark BG:** `#0D1117`
- **Font Headings:** Syne (700/800)
- **Font Body:** DM Sans (400/500)
