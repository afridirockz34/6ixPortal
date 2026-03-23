# 6ix Developers — Marketing Operating System Portal

A full-stack WordPress client portal built as a child theme on top of Divi.

## Three Portals

| Portal | URL Slug | Role |
|---|---|---|
| Customer Portal | `/portal/` | `six_customer` |
| Advisor Portal | `/advisor-portal/` | `six_advisor` |
| Sales Portal | `/sales-portal/` | `six_sales` |
| Onboarding | `/get-started/` | Public |

## Stack

- **CMS:** WordPress + Divi (child theme `6ixClaude`)
- **Server:** `/home/ydd4a6sau0o9/public_html/6ix-redesign/`
- **Live URL:** https://6ixdevelopers.com/6ix-redesign/
- **Brand:** Pink `#FF6699` · Blue `#3C6478` · Cyan `#83C5ED` · Dark `#0D1117`
- **Fonts:** Syne (headings) · DM Sans (body)

## Integrations

- **Odoo 18 SaaS** — XML-RPC, contacts, CRM leads, tasks
- **Stripe** — setup intents, invoices, webhooks
- **Google Ads MCC** — v20 API, campaign metrics per client
- **Google Calendar** — per-advisor OAuth, meeting display

## File Structure

```
6ixPortal/
├── functions-additions.php      ← paste into functions.php
├── portal-page.php              ← theme root (handles all 3 portals)
├── portal/
│   ├── class-missing.php        ← Six_Roles, Six_Notifications, Six_Messaging, etc.
│   ├── class-odoo.php           ← Odoo XML-RPC integration
│   ├── class-stripe.php         ← Stripe API
│   ├── class-google-ads-calendar.php  ← Google Ads MCC + Calendar OAuth
│   ├── admin-settings.php       ← WP Admin panel + GCal OAuth callback
│   ├── ajax-handlers.php        ← All wp_ajax_ hooks
│   ├── ajax-onboarding.php      ← Onboarding AJAX + password handlers
│   ├── assets/
│   │   └── portal.css           ← Dark theme, mobile responsive
│   └── templates/
│       ├── onboarding.php       ← 4-step onboarding flow
│       ├── customer-dashboard.php
│       ├── advisor-dashboard.php
│       └── sales-dashboard.php
└── docs/
    └── PROJECT_JOURNAL.md       ← Full technical documentation
```

## Setup

### 1. Install files
Upload all files to `/wp-content/themes/6ixClaude/` maintaining the folder structure above.

### 2. functions.php
Add to the top of your child theme `functions.php`:
```php
define('SIX_PLUGIN_DIR', get_stylesheet_directory() . '/portal/');
define('SIX_PLUGIN_URL', get_stylesheet_directory_uri() . '/portal/');
require_once SIX_PLUGIN_DIR . 'class-missing.php';
foreach ([
    'class-odoo.php', 'class-stripe.php', 'class-google-ads-calendar.php',
    'ajax-handlers.php', 'admin-settings.php', 'ajax-onboarding.php',
] as $file) {
    if (file_exists(SIX_PLUGIN_DIR . $file)) require_once SIX_PLUGIN_DIR . $file;
}
```
Then paste the full contents of `functions-additions.php` below that block.

### 3. WordPress pages
Create 4 pages, all using the **Portal Page** template:
- slug: `portal`
- slug: `advisor-portal`
- slug: `sales-portal`
- slug: `get-started`

### 4. Install DB tables
Visit: `https://yoursite.com/wp-admin/?six_install=1`

### 5. Configure integrations
Go to **WP Admin → 6ix Portal → Integrations** and fill in all API keys.

### 6. Odoo one-time setup
Visit: `https://yoursite.com/wp-admin/?six_odoo_setup=1`
This creates all custom fields and CRM stages automatically.

### 7. Flush permalinks
WP Admin → Settings → Permalinks → Save Changes.

## Webhook URLs

| Service | URL |
|---|---|
| Stripe | `https://6ixdevelopers.com/6ix-redesign/wp-json/six/v1/stripe-webhook` |
| Google Calendar OAuth | `https://6ixdevelopers.com/6ix-redesign/advisor-portal/gcal/` |
