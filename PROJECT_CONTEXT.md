# 6ix Developers Marketing Operating System — Project Context

## Quick reference for new chat sessions

### Server paths
- **Theme root:** `/home/ydd4a6sau0o9/public_html/6ix-redesign/wp-content/themes/6ixClaude/`
- **Portal:** `theme-root/portal/`
- **Templates:** `theme-root/portal/templates/`

### Key files
| File | Purpose |
|------|---------|
| `portal/templates/onboarding.php` | 5-step onboarding flow (2046L) |
| `portal/templates/advisor-dashboard.php` | Advisor portal (3186L) |
| `portal/templates/customer-dashboard.php` | Customer portal (2549L) |
| `portal/ajax-onboarding.php` | Onboarding AJAX handlers + DB save (1701L) |
| `portal/ajax-handlers.php` | All other AJAX handlers (1573L) |
| `portal/class-estimate-engine.php` | Claude AI strategy prompt builder |
| `portal/class-odoo.php` | Odoo CRM integration |
| `portal/class-missing.php` | Six_Roles, Six_Notifications, Six_Messaging |
| `functions.php` | Theme bootstrap, page routing, auto-migration |

### Database
- **Main table:** `wp_six_checkout_progress` — one row per user, all onboarding data
- **Services:** `wp_six_client_services` — status: pending/active
- **Assignments:** `wp_six_assignments` — advisor_id ↔ client_id
- **Recommendations:** `wp_six_recommendations`
- **KPIs:** `wp_six_kpi_targets`
- **DB migration:** runs automatically on `init` hook (v6 adds ads_schedule, seo_competitors, gbp_hours etc.)

### Onboarding flow
```
Step 1 → User Info (name/email/phone/password)
Step 2 → Services Selection
Step 3a → Business Info
Step 3b → Per-service questionnaire (Google Ads / SEO / GBP / Website)
Step 3c → Competitors
Step 4 → AI Strategy (Claude)
Step 5 → Agreement + Complete
```
- Back from Step 3a → goes to Step 2 (services)
- `S.q` = questionnaire state object
- `six_complete_onboarding` AJAX saves ALL fields at step 5 (guaranteed save)
- `six_get_onboarding_state` AJAX restores full state on page refresh

### Auth & AJAX
- **Nonce:** `wp_create_nonce('six_nonce')` / `wp_verify_nonce($_POST['nonce'], 'six_nonce')`
- **AJAX URL:** `admin_url('admin-ajax.php')`
- **JS globals:** `AJAX`, `NONCE`, `INI` (defined in advisor/customer dashboard script blocks)
- **Roles:** `administrator`, `six_advisor`, `six_customer`

### Odoo CRM
- Stages: Abandoned (ID:22), Onboarding Submitted (ID:17), Customer
- Default advisor: musab@6ixdevelopers.com (UID 2)
- WP option: `six_default_advisor_email`

### Theme system
- Dark/light: `data-theme="dark"` on `#six-portal-root`
- Theme stored: `localStorage.getItem('six_theme')`
- Theme event: `six-theme-changed` custom DOM event

### Claude AI (strategy)
- WP option: `six_anthropic_api_key`
- Prompt built in: `portal/class-estimate-engine.php`
- Output: JSON with headline, sub, kpis[], insight, insights[], roadmap[]

### Known issues / pending work
- Existing clients (pre May 2026) have empty business info — advisor must fill from Client Profile tab once
- Approve Service: if still failing, check browser console for exact error from server
- Google Ads / GA4 integration in customer chart: `fetchAnalytics()` stub in customer-dashboard.php

### GitHub
- Repo: https://github.com/afridirockz34/6ixPortal
- Branch: main

### How to start a new session
1. Open fresh Claude chat
2. Paste this file's content as context
3. State the specific issue/feature with file names and line numbers if known
4. For JS bugs: paste browser console error
5. For PHP bugs: paste the full error message (file + line number)
