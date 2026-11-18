<?php
/**
 * 6ixClaude — functions.php
 * Portal: Customer | Advisor | Sales
 * Integrations: Odoo CRM | Stripe | Google Ads | Google Calendar
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── STANDALONE THEME MIGRATION ───────────────────────────────────────────────
// This theme used to be a Divi child. Once style.css stops declaring a parent,
// WordPress still has the old template option cached — re-activate once so
// get_template() points at this theme instead of Divi.
add_action( 'init', function () {
    if ( get_option( 'six_theme_standalone_v1' ) ) return;
    $stylesheet = get_option( 'stylesheet' );
    if ( $stylesheet && get_option( 'template' ) !== $stylesheet && ! wp_get_theme( $stylesheet )->parent() ) {
        switch_theme( $stylesheet );
    }
    update_option( 'six_theme_standalone_v1', 1 );
}, 1 );

// ── THEME SUPPORT ─────────────────────────────────────────────────────────────
// Never declared before, on this theme or its Divi-child predecessor — so the
// native "Featured Image" box has never appeared for ANY post type, including
// Case Studies, Client Success and Testimonials (each of those declares
// 'thumbnail' support, but that's a no-op without this). Fixes it site-wide.
add_action( 'after_setup_theme', function () {
    add_theme_support( 'post-thumbnails' );
} );

// ── FAVICON ──────────────────────────────────────────────────────────────────
// No Site Icon has ever been set under Settings → General, so no page —
// onboarding included, since it builds its own <head> and only inherits
// whatever wp_head() emits — has ever had a browser-tab icon. Emit explicit
// <link rel="icon"> tags site-wide instead of depending on that admin
// setting, using the theme's own real favicon files.
add_action( 'wp_head', function () {
    $dir = get_stylesheet_directory() . '/marketing/assets/img/favicons/';
    $url = get_stylesheet_directory_uri() . '/marketing/assets/img/favicons/';
    if ( ! file_exists( $dir . '192.png' ) ) return;
    $v = filemtime( $dir . '192.png' );
    echo '<link rel="icon" type="image/png" sizes="16x16" href="' . esc_url( $url . '16.png?v=' . $v ) . '">' . "\n";
    echo '<link rel="icon" type="image/png" sizes="96x96" href="' . esc_url( $url . '96.png?v=' . $v ) . '">' . "\n";
    echo '<link rel="icon" type="image/png" sizes="192x192" href="' . esc_url( $url . '192.png?v=' . $v ) . '">' . "\n";
    echo '<link rel="shortcut icon" href="' . esc_url( $url . '192.png?v=' . $v ) . '">' . "\n";
    echo '<link rel="apple-touch-icon" sizes="180x180" href="' . esc_url( $url . '180.png?v=' . $v ) . '">' . "\n";
}, 2 );

// ── CONSTANTS ───────────────────────────────────────────────────────────────
define( 'SIX_PORTAL_VERSION', '1.0.0' );
define( 'SIX_PLUGIN_DIR',     get_stylesheet_directory() . '/portal/' );
define( 'SIX_PLUGIN_URL',     get_stylesheet_directory_uri() . '/portal/' );

// ── LOAD PORTAL MODULES ─────────────────────────────────────────────────────
require_once SIX_PLUGIN_DIR . 'class-missing.php';
require_once SIX_PLUGIN_DIR . 'class-icons.php';      // SVG icon library
require_once SIX_PLUGIN_DIR . 'class-questionnaire.php';
require_once SIX_PLUGIN_DIR . 'ajax-onboarding.php';

// Branded HTML email shell + the system-generated notification templates
// (onboarding abandonment/completion, budget change, service request/
// activation) — loaded before the forms system since class-forms-submit.php
// uses both to send its owner/customer emails.
require_once SIX_PLUGIN_DIR . 'class-email-chrome.php';
require_once SIX_PLUGIN_DIR . 'class-system-emails.php';

// Forms system — every lead-capture form on the site (custom post type +
// submission log + admin builder + AJAX handler), replacing the old
// marketing/forms.php + ninja-forms.php. See class-forms.php's docblock.
require_once SIX_PLUGIN_DIR . 'class-forms.php';
require_once SIX_PLUGIN_DIR . 'class-forms-submit.php';
require_once SIX_PLUGIN_DIR . 'class-forms-admin.php';
require_once SIX_PLUGIN_DIR . 'data-forms-seed.php';

$optional_files = array(
    'class-odoo.php',          // must load before growth engine
    'class-forms-integrations.php', // forms → Odoo + advisor notifications (needs Six_Odoo)
    'class-lead-pipeline.php', // Lead Automation Flow — response window, recovery sequence, win-back (needs Six_Odoo)
    'class-estimate-engine.php', // Estimate Engine — real data growth plans
    'class-dashboard-metrics.php', // service-aware dashboard stats + connector seam
    'class-unread.php',          // "something new in here" dots for the customer sidebar
    'class-performance.php',     // performance snapshots over time + strategy projections
    'class-tracking.php',        // work log, per-entity metrics, daily series, metric proposals
    'class-tasks.php',           // the approved strategy as a task board the advisor works from
    'class-website-project.php', // website build milestones + maintenance add-on
    'class-profile-api.php',     // REST profile contract for external tools/skills
    'class-mcp-server.php',      // same contract as MCP, for claude.ai custom connectors
    'data-seo-questionnaire.php',// SEO questionnaire schema (form + columns + dedup rules)
    'questionnaire-page.php',    // public /seo-questionnaire/ page opened by the shareable link
    'class-growth-engine.php', // Growth Engine
    'class-stripe.php',
    'class-google-ads-calendar.php',
    'class-appointments.php',   // unified calls/meetings store (after calendar + odoo)
    'class-ahrefs.php',         // Ahrefs API v3 client — the SEO data provider
    'class-brightlocal.php',    // BrightLocal API client — local pack / GBP data
    'class-dataforseo.php',     // legacy DataForSEO client, kept as a fallback
    'class-analytics.php',      // GA4 + Search Console client (service account)
    'class-meta.php',           // Meta (Facebook/Instagram) Ads insights client
    'class-ai-strategist.php',  // advisor AI strategy agent (needs dataforseo + odoo)
    'ajax-ai-strategist.php',   // AI strategist AJAX endpoints
    'ajax-handlers.php',
    'admin-settings.php',
    'social-login.php',        // Nextend Social Login (Google) integration
);
// ── MARKETING SITE (public website redesign) ────────────────────────────────
$marketing_loader = get_stylesheet_directory() . '/marketing/marketing.php';
if ( file_exists( $marketing_loader ) ) require_once $marketing_loader;
foreach ( $optional_files as $file ) {
    $path = SIX_PLUGIN_DIR . $file;
    if ( file_exists( $path ) ) {
        require_once $path;
    }
}

/**
 * Which SEO data provider is live.
 *
 * Ahrefs is what we use now. DataForSEO is left in place so a site whose
 * Ahrefs token hasn't been added yet keeps working instead of silently losing
 * every keyword and competitor figure, and so there's a way back if an Ahrefs
 * outage ever matters. Returns a class name to call statically, or '' when
 * neither is configured — callers must handle '' rather than assume data.
 *
 * Both classes expose the identical method set (see class-ahrefs.php), which
 * is what makes calling through a variable class name safe here.
 */
function six_seo_provider() {
    if ( class_exists( 'Six_Ahrefs' ) && Six_Ahrefs::configured() )         return 'Six_Ahrefs';
    if ( class_exists( 'Six_DataForSEO' ) && Six_DataForSEO::configured() ) return 'Six_DataForSEO';
    return '';
}

// ── ENQUEUE ASSETS ──────────────────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', 'six_enqueue_assets' );
function six_enqueue_assets() {
    if ( ! is_user_logged_in() ) return;

    $portal_slugs = array( 'portal', 'advisor-portal', 'sales-portal', 'get-started', 'internal-hub' );
    $current_slug = get_post_field( 'post_name', get_the_ID() );
    if ( ! in_array( $current_slug, $portal_slugs, true ) ) return;

    // Fonts — Bricolage Grotesque (display) + Onest (text/numbers)
    wp_enqueue_style(
        'six-fonts',
        'https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,600;12..96,700;12..96,800&family=Onest:wght@400;500;600;700;800&display=swap',
        array(),
        null
    );

    // Portal CSS
    $css_path = SIX_PLUGIN_DIR . 'assets/portal.css';
    if ( file_exists( $css_path ) ) {
        wp_enqueue_style(
            'six-portal',
            SIX_PLUGIN_URL . 'assets/portal.css',
            array(),
            filemtime( $css_path )  // bust cache on every file change
        );
    }

    // Portal JS
    wp_enqueue_script( 'chartjs', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js', array(), '4.4.1', true );
    $js_path = SIX_PLUGIN_DIR . 'assets/portal.js';
    if ( file_exists( $js_path ) ) {
        wp_enqueue_script(
            'six-portal',
            SIX_PLUGIN_URL . 'assets/portal.js',
            array( 'jquery' ),
            SIX_PORTAL_VERSION,
            true
        );
    }

    wp_localize_script( 'six-portal', 'sixPortal', array(
        'ajax_url'      => admin_url( 'admin-ajax.php' ),
        'rest_url'      => rest_url( 'six/v1/' ),
        'nonce'         => wp_create_nonce( 'six_nonce' ),
        'stripe_key'    => get_option( 'six_stripe_publishable_key' ),
        'user_id'       => get_current_user_id(),
        'user_role'     => class_exists( 'Six_Roles' ) ? Six_Roles::get_portal_role() : '',
        'user_name'     => wp_get_current_user()->display_name,
        'user_initials' => six_get_initials( wp_get_current_user()->display_name ),
    ) );
}

// ── HELPERS ─────────────────────────────────────────────────────────────────
function six_get_initials( $name ) {
    $parts    = explode( ' ', trim( $name ) );
    $initials = '';
    foreach ( array_slice( $parts, 0, 2 ) as $p ) {
        $initials .= strtoupper( mb_substr( $p, 0, 1 ) );
    }
    return $initials ?: 'U';
}

// ── CUSTOM ROLES ─────────────────────────────────────────────────────────────
add_action( 'init', 'six_register_roles' );
function six_register_roles() {
    if ( ! get_role( 'six_customer' ) ) {
        add_role( 'six_customer', 'Portal Customer', array(
            'read'         => true,
            'six_view_own' => true,
        ) );
    }
    if ( ! get_role( 'six_advisor' ) ) {
        add_role( 'six_advisor', 'Portal Advisor', array(
            'read'                 => true,
            'six_manage_clients'   => true,
            'six_upload_reports'   => true,
            'six_approve_services' => true,
        ) );
    }
    if ( ! get_role( 'six_sales' ) ) {
        add_role( 'six_sales', 'Sales Representative', array(
            'read'           => true,
            'six_view_leads' => true,
        ) );
    }
}

// ── DATABASE TABLES ──────────────────────────────────────────────────────────
function six_create_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $tables = array(
        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}six_checkout_progress (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            score int(3) DEFAULT 0,
            step varchar(50) DEFAULT 'account_created',
            business_name varchar(255) DEFAULT '',
            industry varchar(100) DEFAULT '',
            monthly_revenue varchar(50) DEFAULT '',
            services_selected text,
            budget decimal(10,2) DEFAULT 0,
            contract_signed tinyint(1) DEFAULT 0,
            card_saved tinyint(1) DEFAULT 0,
            odoo_lead_id varchar(100) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id)
        ) $charset",

        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}six_assignments (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            client_id bigint(20) NOT NULL,
            advisor_id bigint(20) NOT NULL,
            service_role varchar(20) NOT NULL DEFAULT '',
            assigned_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY client_service_role (client_id, service_role)
        ) $charset",

        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}six_client_services (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            client_id bigint(20) NOT NULL,
            service_slug varchar(50) NOT NULL,
            service_name varchar(100) NOT NULL,
            status varchar(20) DEFAULT 'pending',
            budget decimal(10,2) DEFAULT 0,
            approved_at datetime,
            approved_by bigint(20),
            odoo_project_id varchar(100) DEFAULT '',
            PRIMARY KEY (id),
            UNIQUE KEY client_service (client_id, service_slug)
        ) $charset",

        // Website builds are one-off projects, not monthly retainers, so they
        // are tracked by delivery milestones an advisor sets rather than by
        // performance metrics. Ordered by sort_order for display.
        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}six_website_milestones (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            client_id bigint(20) NOT NULL,
            title varchar(200) NOT NULL,
            status varchar(20) DEFAULT 'pending',
            sort_order int(11) DEFAULT 0,
            completed_at datetime NULL,
            updated_by bigint(20) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY client_id (client_id)
        ) $charset",

        // Results produced by external tools and Claude skills against a
        // client profile — keyword analyses, post schedules, reports. Payload
        // shape is the producer's business; the dashboard renders the types it
        // knows and ignores the rest.
        // What actually happened, captured over time. Without a stored
        // history there is nothing to compare today's numbers against —
        // Six_Dash_Metrics computes the present live and keeps no record.
        // One row per client per period: the unique key makes a capture
        // idempotent, so a cron that fires late (or twice) updates that
        // period's row instead of duplicating it.
        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}six_performance_snapshots (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            client_id bigint(20) NOT NULL,
            period_key varchar(20) NOT NULL,
            captured_at datetime DEFAULT CURRENT_TIMESTAMP,
            source varchar(40) DEFAULT 'scheduled',
            services varchar(255) DEFAULT '',
            metrics longtext,
            PRIMARY KEY (id),
            UNIQUE KEY client_period (client_id, period_key),
            KEY client_captured (client_id, captured_at)
        ) $charset",

        // What the strategy said would happen, so a later check can ask
        // "are we where we said we would be?" rather than only "has it gone
        // up?". Stored per metric per target date so that question is a
        // lookup rather than a scan through a strategy document.
        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}six_strategy_projections (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            client_id bigint(20) NOT NULL,
            metric_key varchar(60) NOT NULL,
            target_date date NOT NULL,
            projected_value decimal(14,2) DEFAULT 0,
            note varchar(255) DEFAULT '',
            artifact_id bigint(20) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            created_by bigint(20) DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY client_metric_date (client_id, metric_key, target_date),
            KEY client_metric (client_id, metric_key)
        ) $charset",

        // ── What we actually did, and what it did to the numbers ─────────
        //
        // Four tables, and none of them is SEO-specific on purpose. A client
        // on Google Ads or a website build needs the same four questions
        // answered as a client on SEO: what did you change, when, what were
        // the numbers before it, and what are they now. `service_slug` is what
        // keeps them apart.

        // Every change we make on a client's behalf, one row per change.
        // Without this the portal can say a number moved and cannot say what
        // we did, so a monthly report is a chart and an assertion. The
        // before/after columns are the whole point: "title changed" is not
        // evidence, "<title>X</title> became <title>Y</title> on 14 Nov" is.
        //
        // category is free text rather than an enum because the categories
        // differ per service and per client — Title Tags and Schema Markup for
        // SEO, Ad Copy and Negative Keywords for Ads — and an enum here means
        // a migration every time a service learns a new kind of work.
        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}six_work_log (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            client_id bigint(20) NOT NULL,
            service_slug varchar(50) NOT NULL DEFAULT '',
            category varchar(80) NOT NULL DEFAULT '',
            action varchar(40) NOT NULL DEFAULT 'changed',
            entity varchar(500) NOT NULL DEFAULT '',
            title varchar(255) NOT NULL DEFAULT '',
            detail text,
            before_value text,
            after_value text,
            status varchar(20) NOT NULL DEFAULT 'deployed',
            keyword varchar(255) DEFAULT '',
            artifact_id bigint(20) DEFAULT 0,
            approver varchar(190) DEFAULT '',
            score int(11) DEFAULT NULL,
            verify_url varchar(500) DEFAULT '',
            verify_how text,
            origin varchar(20) NOT NULL DEFAULT 'advisor',
            performed_at datetime DEFAULT CURRENT_TIMESTAMP,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            created_by bigint(20) DEFAULT 0,
            PRIMARY KEY (id),
            KEY client_service (client_id, service_slug),
            KEY client_cat (client_id, category),
            KEY client_when (client_id, performed_at)
        ) $charset",

        // Where one page, keyword, campaign or listing stood on one date.
        // The client-level snapshot table cannot answer "did the page we
        // rewrote move", because it stores one row per client per week with
        // service totals in it. This is the table that makes a before/after
        // for a specific change possible, so it has to be capturing BEFORE
        // the change ships or there is no before.
        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}six_entity_metrics (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            client_id bigint(20) NOT NULL,
            service_slug varchar(50) NOT NULL DEFAULT '',
            entity_type varchar(30) NOT NULL DEFAULT 'page',
            entity varchar(500) NOT NULL,
            entity_hash char(32) NOT NULL,
            captured_on date NOT NULL,
            position decimal(8,2) DEFAULT NULL,
            clicks int(11) DEFAULT NULL,
            impressions int(11) DEFAULT NULL,
            ctr decimal(8,4) DEFAULT NULL,
            extra longtext,
            source varchar(60) NOT NULL DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY one_per_day (client_id, entity_type, entity_hash, captured_on),
            KEY client_type_day (client_id, entity_type, captured_on),
            KEY client_entity (client_id, entity_hash)
        ) $charset",

        // One number per client per metric per DAY. The weekly snapshot table
        // is the right grain for a KPI review and the wrong grain for a graph:
        // two captures draw a straight line between two points, which is what
        // the visitors chart has been doing. A daily series is what makes it
        // a line that moves.
        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}six_daily_metrics (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            client_id bigint(20) NOT NULL,
            metric_key varchar(60) NOT NULL,
            captured_on date NOT NULL,
            value decimal(16,4) DEFAULT 0,
            source varchar(40) NOT NULL DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY one_per_day (client_id, metric_key, captured_on),
            KEY client_metric (client_id, metric_key),
            KEY client_day (client_id, captured_on)
        ) $charset",

        // A figure Claude read, waiting for an advisor to confirm it.
        //
        // Nothing Claude produces writes straight to the customer's dashboard.
        // It lands here, the advisor sees it beside whatever the integrations
        // say, and accepting it is what moves it onto the stat cards. That is
        // the difference between a number a human stands behind and a number
        // that appeared.
        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}six_metric_proposals (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            client_id bigint(20) NOT NULL,
            service_slug varchar(50) NOT NULL DEFAULT '',
            metric_key varchar(60) NOT NULL DEFAULT '',
            label varchar(120) NOT NULL DEFAULT '',
            unit varchar(60) DEFAULT '',
            current_value varchar(100) DEFAULT '',
            previous_value varchar(100) DEFAULT '',
            suggested_target varchar(100) DEFAULT '',
            period_start date DEFAULT NULL,
            period_end date DEFAULT NULL,
            source varchar(190) DEFAULT '',
            origin varchar(20) NOT NULL DEFAULT 'claude',
            note text,
            status varchar(20) NOT NULL DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            created_by bigint(20) DEFAULT 0,
            reviewed_at datetime NULL,
            reviewed_by bigint(20) DEFAULT 0,
            PRIMARY KEY (id),
            KEY client_status (client_id, status),
            KEY client_service (client_id, service_slug)
        ) $charset",

        // The plan, as rows somebody can tick off.
        //
        // An approved strategy is a document until it becomes a list of things
        // with owners and states. These rows are what the advisor works from,
        // what Claude reads to know what is already delivered or waiting on the
        // client, and what the work log closes out against.
        //
        // Four states, in the order work moves through them:
        //   todo           not started
        //   client_review  sent to the client, waiting on their approval
        //   implementation being built or deployed
        //   complete       done and verified
        //
        // `published` exists because a freshly generated plan is a draft: the
        // advisor edits the titles, dates and order before the client sees any
        // of it. Nothing unpublished appears on the customer's side.
        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}six_tasks (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            client_id bigint(20) NOT NULL,
            service_slug varchar(50) NOT NULL DEFAULT '',
            phase tinyint(4) NOT NULL DEFAULT 1,
            month_no tinyint(4) NOT NULL DEFAULT 1,
            category varchar(80) NOT NULL DEFAULT '',
            title varchar(255) NOT NULL,
            detail text,
            deliverable varchar(255) DEFAULT '',
            entity varchar(500) DEFAULT '',
            keyword varchar(255) DEFAULT '',
            status varchar(30) NOT NULL DEFAULT 'todo',
            priority tinyint(4) NOT NULL DEFAULT 2,
            due_date date DEFAULT NULL,
            assigned_to varchar(190) DEFAULT '',
            artifact_id bigint(20) DEFAULT 0,
            work_log_id bigint(20) DEFAULT 0,
            sort_order int(11) DEFAULT 0,
            published tinyint(1) NOT NULL DEFAULT 0,
            origin varchar(20) NOT NULL DEFAULT 'claude',
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by bigint(20) DEFAULT 0,
            completed_at datetime NULL,
            PRIMARY KEY (id),
            KEY client_status (client_id, status),
            KEY client_phase (client_id, phase, month_no),
            KEY client_service (client_id, service_slug)
        ) $charset",

        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}six_client_artifacts (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            client_id bigint(20) NOT NULL,
            type varchar(60) NOT NULL,
            source varchar(100) DEFAULT '',
            payload longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY client_type (client_id, type)
        ) $charset",

        // The advisor's confirmed figure for each catalog metric — what the
        // customer's four stat cards actually read. This table used to be
        // created only by visiting ?six_kpi_setup=1 by hand, so on a site
        // where nobody had, every KPI an advisor saved failed silently and
        // the customer saw industry benchmarks for ever. Created with the
        // rest now, version-gated like everything else.
        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}six_client_kpis (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            client_id bigint(20) unsigned NOT NULL,
            kpi_key varchar(60) NOT NULL,
            kpi_value varchar(255) NOT NULL DEFAULT '',
            kpi_prev varchar(255) NOT NULL DEFAULT '',
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY client_kpi (client_id, kpi_key),
            KEY idx_client (client_id)
        ) $charset",

        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}six_metrics (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            client_id bigint(20) NOT NULL,
            service_slug varchar(50) DEFAULT '',
            label varchar(100) NOT NULL,
            previous_value varchar(100) DEFAULT '',
            current_value varchar(100) DEFAULT '',
            target_value varchar(100) DEFAULT '',
            unit varchar(20) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY client_service (client_id, service_slug)
        ) $charset",

        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}six_messages (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            sender_id bigint(20) NOT NULL,
            receiver_id bigint(20) NOT NULL,
            message text NOT NULL,
            is_read tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY thread (sender_id, receiver_id)
        ) $charset",

        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}six_notifications (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            type varchar(50) DEFAULT '',
            title varchar(255) DEFAULT '',
            message text,
            is_read tinyint(1) DEFAULT 0,
            action_url varchar(500) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset",

        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}six_recommendations (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            client_id bigint(20) NOT NULL,
            advisor_id bigint(20) NOT NULL,
            title varchar(255) DEFAULT '',
            description text,
            action_label varchar(100) DEFAULT '',
            action_type varchar(50) DEFAULT '',
            action_value text,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset",

        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}six_reports (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            client_id bigint(20) NOT NULL,
            advisor_id bigint(20) NOT NULL,
            title varchar(255) DEFAULT '',
            type varchar(50) DEFAULT '',
            file_url varchar(500) DEFAULT '',
            file_size varchar(20) DEFAULT '',
            period varchar(50) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY client_id (client_id)
        ) $charset",
    );

    foreach ( $tables as $sql ) {
        dbDelta( $sql );
    }

    six_migrate_assignments_multi_advisor();
    six_reports_ensure_columns();
}

/**
 * Columns six_reports grew after it was first created.
 *
 * Added explicitly rather than by editing the CREATE statement above, because
 * dbDelta does NOT alter an existing table when the statement says
 * `CREATE TABLE IF NOT EXISTS` — its table-name regex captures `IF`, so a
 * column added to that string is never applied to a live database. This
 * codebase has lost writes that way before.
 *
 * Gated on a signature of what is wanted, so the SHOW COLUMNS costs nothing
 * on a normal request.
 *
 * @return array{added:string[],failed:array<string,string>}
 */
function six_reports_ensure_columns( $force = false ) {
    global $wpdb;
    $out  = array( 'added' => array(), 'failed' => array() );
    $need = array(
        'service_slug' => "varchar(50) DEFAULT ''",
        'summary'      => "text",
        'period_start' => 'date DEFAULT NULL',
        'period_end'   => 'date DEFAULT NULL',
    );
    $sig = md5( wp_json_encode( $need ) );
    if ( ! $force && get_option( 'six_reports_cols_sig' ) === $sig ) return $out;

    $tbl = $wpdb->prefix . 'six_reports';
    if ( ! six_table_exists( $tbl ) ) return $out;   // created on the next pass

    $have = array();
    foreach ( (array) $wpdb->get_results( "SHOW COLUMNS FROM {$tbl}", ARRAY_A ) as $c ) {
        $have[ strtolower( $c['Field'] ) ] = true;
    }

    $wpdb->suppress_errors( true );
    foreach ( $need as $col => $def ) {
        if ( isset( $have[ strtolower( $col ) ] ) ) continue;
        if ( $wpdb->query( "ALTER TABLE {$tbl} ADD COLUMN `{$col}` {$def}" ) === false ) {
            $out['failed'][ $col ] = $wpdb->last_error ?: 'could not add column';
            error_log( '6ix reports: could not add column ' . $col . ' — ' . $wpdb->last_error );
        } else {
            $out['added'][] = $col;
        }
    }
    $wpdb->suppress_errors( false );

    if ( ! $out['failed'] ) update_option( 'six_reports_cols_sig', $sig, true );
    return $out;
}

/**
 * The schema version. Bump this whenever a table is added or changed in
 * six_create_tables(), and the next page load creates it.
 */
const SIX_DB_VERSION = '2026.11.18.1';

/**
 * Run six_create_tables() when the code's schema version moves ahead of what
 * this site has already built.
 *
 * Until now that function was only reachable from two buttons on the admin
 * Integrations page, so any table added after someone last clicked one simply
 * did not exist. Three did not: six_client_artifacts, six_performance_snapshots
 * and six_strategy_projections — meaning every audit report, snapshot and
 * strategy target written through the API vanished, while the API reported
 * success. A deploy must not depend on anyone remembering to press a button.
 *
 * Cost on a normal request is a single get_option(), and the statements are
 * all CREATE TABLE IF NOT EXISTS / dbDelta, so running again is harmless.
 */
function six_maybe_upgrade_db() {
    if ( get_option( 'six_db_version' ) === SIX_DB_VERSION ) return;
    if ( ! function_exists( 'six_create_tables' ) ) return;

    six_create_tables();
    update_option( 'six_db_version', SIX_DB_VERSION, true );
    error_log( '6ix: database schema brought up to ' . SIX_DB_VERSION );
}
// Both hooks: admin_init covers an admin visiting any page, and init covers a
// site whose admin is rarely opened but whose REST API is being written to.
add_action( 'admin_init', 'six_maybe_upgrade_db' );
add_action( 'init',       'six_maybe_upgrade_db', 1 );

/**
 * Does a table this code expects actually exist?
 *
 * Callers use this to fail loudly rather than write into nothing. Results are
 * cached per request because SHOW TABLES on every write would be wasteful.
 */
function six_table_exists( $table ) {
    global $wpdb;
    static $seen = array();
    if ( isset( $seen[ $table ] ) ) return $seen[ $table ];
    $seen[ $table ] = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table );
    return $seen[ $table ];
}

/**
 * Upgrade an existing six_assignments table to the multi-advisor schema.
 * dbDelta() can add the new `service_role` column reliably, but it will not
 * reliably swap an existing UNIQUE KEY's column list — the old installs have
 * `UNIQUE KEY client_id (client_id)`, which must become a composite key on
 * (client_id, service_role) before a client can have more than one advisor
 * row. Runs once (guarded by an option) and is safe to call any time,
 * including on a fresh install where the table already has the new key.
 */
function six_migrate_assignments_multi_advisor() {
    if ( get_option( 'six_assignments_schema_v2' ) ) return;
    global $wpdb;
    $table = $wpdb->prefix . 'six_assignments';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) return; // created fresh (already v2) once six_create_tables runs again

    if ( ! $wpdb->get_var( "SHOW COLUMNS FROM $table LIKE 'service_role'" ) ) {
        $wpdb->query( "ALTER TABLE $table ADD COLUMN service_role VARCHAR(20) NOT NULL DEFAULT '' AFTER advisor_id" );
    }
    // Existing rows predate the "role" concept — they're every client's
    // General/primary advisor, which service_role='' already represents
    // (the column default), so no data backfill is needed.

    if ( $wpdb->get_var( "SHOW INDEX FROM $table WHERE Key_name='client_id'" ) ) {
        $wpdb->query( "ALTER TABLE $table DROP INDEX client_id" );
    }
    if ( ! $wpdb->get_var( "SHOW INDEX FROM $table WHERE Key_name='client_service_role'" ) ) {
        $wpdb->query( "ALTER TABLE $table ADD UNIQUE KEY client_service_role (client_id, service_role)" );
    }
    update_option( 'six_assignments_schema_v2', 1 );
}
add_action( 'admin_init', 'six_migrate_assignments_multi_advisor' );

/**
 * Canonical service-role choices for advisor assignment.
 * '' = General / primary advisor (the client's main point of contact).
 * 'all' = this advisor handles every active service for the client — the
 * one-advisor-does-everything case, as a single row instead of one row per
 * service, so the customer-facing UI can show just one person.
 */
function six_advisor_service_roles() {
    return array(
        ''           => 'General',
        'all'        => 'All Services',
        'google-ads' => 'Google Ads',
        'seo'        => 'SEO',
        'smm'        => 'Social Media (SMM)',
    );
}

/** SQL fragment ranking a role column: General first, then All, then the rest. */
function six_advisor_role_priority_sql( $col = 'service_role' ) {
    return "CASE {$col} WHEN '' THEN 0 WHEN 'all' THEN 1 ELSE 2 END";
}

/**
 * The single advisor_id to use for a client when the caller doesn't care
 * about per-service granularity (notifications, ownership checks, "who is
 * my advisor" widgets, etc). Prefers, in order: the exact role requested,
 * the "All Services" advisor, then General — so every pre-existing
 * single-advisor client keeps behaving exactly as before.
 *
 * Pass a specific $service_role to prefer that role's dedicated advisor
 * first (falling back to All Services, then General).
 */
function six_get_client_advisor_id( $client_id, $service_role = '' ) {
    global $wpdb;
    $client_id = intval( $client_id );
    if ( ! $client_id ) return 0;

    if ( $service_role !== '' && $service_role !== 'all' ) {
        $id = $wpdb->get_var( $wpdb->prepare(
            "SELECT advisor_id FROM {$wpdb->prefix}six_assignments WHERE client_id=%d AND service_role=%s",
            $client_id, $service_role
        ) );
        if ( $id ) return intval( $id );
    }

    $priority = six_advisor_role_priority_sql();
    $id = $wpdb->get_var( $wpdb->prepare(
        "SELECT advisor_id FROM {$wpdb->prefix}six_assignments WHERE client_id=%d ORDER BY {$priority} ASC, id ASC LIMIT 1",
        $client_id
    ) );
    return intval( $id );
}

/**
 * Every advisor ASSIGNMENT ROW for a client — one entry per (role, advisor)
 * pair, so the same person can appear more than once if they hold multiple
 * roles. This raw, per-role shape is what the "Manage Advisors" admin modal
 * needs to populate its role selects independently.
 *
 * For anything customer-facing, use six_get_client_advisors_grouped()
 * instead — it collapses this down to one entry per distinct person.
 */
function six_get_client_advisors( $client_id ) {
    global $wpdb;
    $client_id = intval( $client_id );
    if ( ! $client_id ) return array();

    $priority = six_advisor_role_priority_sql( 'a.service_role' );
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT a.id, a.advisor_id, a.service_role, u.display_name, u.user_email
         FROM {$wpdb->prefix}six_assignments a
         INNER JOIN {$wpdb->prefix}users u ON a.advisor_id = u.ID
         WHERE a.client_id=%d
         ORDER BY {$priority} ASC, a.service_role ASC",
        $client_id
    ) );

    $roles = six_advisor_service_roles();
    $out   = array();
    foreach ( $rows as $r ) {
        $out[] = array(
            'assignment_id' => intval( $r->id ),
            'advisor_id'    => intval( $r->advisor_id ),
            'name'          => $r->display_name,
            'email'         => $r->user_email,
            'role'          => $r->service_role,
            'role_label'    => $roles[ $r->service_role ] ?? ucwords( str_replace( '-', ' ', $r->service_role ) ),
        );
    }
    return $out;
}

/**
 * Every DISTINCT advisor assigned to a client — one entry per person, with
 * their role(s) combined into a single display label. This is what
 * customer-facing UI (and the All Clients admin badges) should use: if one
 * advisor was assigned "All Services", or was manually given every role, or
 * is simply the client's only advisor, this returns exactly one entry —
 * matching the client's real-world experience of having one advisor, not one
 * per role. General/All-Services advisors sort first.
 */
function six_get_client_advisors_grouped( $client_id ) {
    $rows = six_get_client_advisors( $client_id );
    if ( ! $rows ) return array();

    $by_advisor = array();
    foreach ( $rows as $r ) {
        $aid = $r['advisor_id'];
        if ( ! isset( $by_advisor[ $aid ] ) ) {
            $by_advisor[ $aid ] = array(
                'advisor_id' => $aid,
                'name'       => $r['name'],
                'email'      => $r['email'],
                'roles'      => array(),
                'labels'     => array(),
            );
        }
        $by_advisor[ $aid ]['roles'][]  = $r['role'];
        $by_advisor[ $aid ]['labels'][] = $r['role_label'];
    }

    $out = array();
    foreach ( $by_advisor as $entry ) {
        if ( in_array( 'all', $entry['roles'], true ) ) {
            $combined = 'All Services';
        } else {
            $combined = implode( ', ', $entry['labels'] );
        }
        $out[] = array(
            'advisor_id' => $entry['advisor_id'],
            'name'       => $entry['name'],
            'email'      => $entry['email'],
            'roles'      => $entry['roles'],
            'role_label' => $combined,
        );
    }

    usort( $out, function ( $a, $b ) {
        $score = function ( $e ) {
            if ( in_array( '', $e['roles'], true ) ) return 0;
            if ( in_array( 'all', $e['roles'], true ) ) return 1;
            return 2;
        };
        return $score( $a ) <=> $score( $b );
    } );
    return $out;
}

// ── PORTAL TEMPLATE ──────────────────────────────────────────────────────────
add_filter( 'template_include', 'six_portal_template' );
function six_portal_template( $template ) {
    $portal_slugs = array( 'portal', 'advisor-portal', 'sales-portal', 'get-started', 'internal-hub' );
    if ( is_page( $portal_slugs ) ) {
        $custom = get_stylesheet_directory() . '/portal-page.php';
        if ( file_exists( $custom ) ) return $custom;
    }
    return $template;
}

// ── HIDE ADMIN BAR ────────────────────────────────────────────────────────────
add_action( 'after_setup_theme', function() {
    $role = class_exists( 'Six_Roles' ) ? Six_Roles::get_portal_role() : '';
    if ( in_array( $role, array( 'six_customer', 'six_advisor', 'six_sales' ), true ) ) {
        show_admin_bar( false );
    }
} );

// ── LOGIN REDIRECTS ───────────────────────────────────────────────────────────
add_action( 'template_redirect', 'six_serve_onboarding_page', 0 );
function six_serve_onboarding_page() {
    if ( ! is_page( 'get-started' ) ) return;

    if ( is_user_logged_in() ) {
        $uid  = get_current_user_id();
        $role = class_exists('Six_Roles') ? Six_Roles::get_portal_role($uid) : '';

        // Logged in but no portal role (e.g. social login created the account
        // as 'subscriber' before the NSL hooks ran) — repair it to a customer
        // so onboarding and the portal routers work.
        if ( $role === 'guest' && ! current_user_can('manage_options') && function_exists('six_social_prepare_user') ) {
            six_social_prepare_user( $uid );
            $role = Six_Roles::get_portal_role( $uid );
        }

        if ( $role === 'six_advisor' )   { wp_redirect( home_url('/advisor-portal/') ); exit; }
        if ( $role === 'six_sales' )     { wp_redirect( home_url('/sales-portal/') ); exit; }
        if ( $role === 'administrator' ) { wp_redirect( admin_url() ); exit; }
        if ( $role === 'six_customer' ) {
            $done = get_user_meta($uid, 'six_checkout_completed', true);
            if ( $done ) { 
                // Prevent redirect loop with portal-page.php
                $ref = sanitize_url( $_SERVER['HTTP_REFERER'] ?? '' );
                if ( strpos($ref, home_url('/portal/')) === false ) {
                    wp_redirect( home_url('/portal/') ); exit;
                }
            }
        }
    }

    $user_id     = is_user_logged_in() ? get_current_user_id() : 0;
    $resume_step = 0;
    if ( $user_id ) {
        global $wpdb;
        $row         = $wpdb->get_row( $wpdb->prepare("SELECT step FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d", $user_id) );
        $resume_step = $row ? intval($row->step) : 1;
    }
    $js_data = array(
        'ajax_url'      => admin_url('admin-ajax.php'),
        'nonce'         => wp_create_nonce('six_nonce'),
        'stripe_key'    => get_option('six_stripe_publishable_key', ''),
        'user_id'       => $user_id,
        'email'         => $user_id ? wp_get_current_user()->user_email : '',
        'resume_step'   => $resume_step,
        // Email passed from the marketing "Client Login" for an unknown address —
        // prefill it and jump straight into the flow.
        'prefill_email' => ( ! $user_id && ! empty($_GET['email']) ) ? sanitize_email( wp_unslash($_GET['email']) ) : '',
    );

    $css_url = get_stylesheet_directory_uri() . '/portal/assets/portal.css';
    $css_ver = @filemtime( get_stylesheet_directory() . '/portal/assets/portal.css' ) ?: '1';

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Get Started — <?php bloginfo('name'); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,600;12..96,700;12..96,800&family=Onest:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo esc_url($css_url . '?v=' . $css_ver); ?>">
<script src="https://js.stripe.com/v3/"></script>
</head>
<body>
<script>var sixPortal = <?php echo wp_json_encode($js_data); ?>;</script>
<?php
    $tpl = get_stylesheet_directory() . '/portal/templates/onboarding.php';
    if ( file_exists($tpl) ) include $tpl;
    else echo '<div style="padding:60px;font-family:sans-serif;color:red">Template not found: ' . esc_html($tpl) . '</div>';
?>
</body>
</html>
<?php
    exit;
}

add_filter( 'login_url', 'six_custom_login_url', 10, 3 );
function six_custom_login_url( $login_url, $redirect, $force_reauth ) {
    if ( is_admin() ) return $login_url;
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if ( strpos($uri, 'get-started') !== false ) return $login_url;
    if ( $redirect && strpos($redirect, 'get-started') !== false ) return $login_url;
    $url = home_url('/get-started/');
    if ( $redirect ) $url = add_query_arg('redirect_to', urlencode($redirect), $url);
    return $url;
}

remove_filter( 'login_redirect', 'six_login_redirect', 10 );
add_filter( 'login_redirect', 'six_portal_login_redirect', 20, 3 );
function six_portal_login_redirect( $redirect_to, $requested, $user ) {
    if ( is_wp_error($user) || ! class_exists('Six_Roles') ) return $redirect_to;
    $role = Six_Roles::get_portal_role($user->ID);
    if ( $role === 'six_advisor' )  return home_url('/advisor-portal/');
    if ( $role === 'six_sales' )    return home_url('/sales-portal/');
    if ( $role === 'six_customer' ) {
        $done = get_user_meta($user->ID, 'six_checkout_completed', true);
        return $done ? home_url('/portal/') : home_url('/get-started/');
    }
    return $redirect_to;
}

// ── SCORE CALCULATORS ─────────────────────────────────────────────────────────
function six_calculate_checkout_score( $user_id ) {
    global $wpdb;
    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}six_checkout_progress WHERE user_id = %d", $user_id
    ) );
    if ( ! $row ) return 0;
    $score = 0;
    if ( $row->user_id )           $score += 10;
    if ( $row->business_name )     $score += 20;
    if ( $row->services_selected ) $score += 20;
    if ( $row->budget )            $score += 20;
    if ( $row->contract_signed )   $score += 20;
    if ( $row->card_saved )        $score += 10;
    $wpdb->update( "{$wpdb->prefix}six_checkout_progress",
        array( 'score' => $score, 'updated_at' => current_time('mysql') ),
        array( 'user_id' => $user_id )
    );
    if ( class_exists('Six_Odoo') ) Six_Odoo::sync_lead( $user_id, array('checkout_score' => $score) );
    return $score;
}

function six_calculate_health_score( $client_id ) {
    return class_exists('Six_Health_Score') ? Six_Health_Score::calculate($client_id) : 0;
}

function six_calculate_readiness_score( $data ) {
    $score = 0;
    if ( ! empty( $data['monthly_revenue'] ) )    $score += 20;
    if ( ! empty( $data['industry'] ) )            $score += 10;
    if ( ! empty( $data['current_traffic'] ) )     $score += 15;
    if ( ! empty( $data['marketing_channels'] ) )  $score += 20;
    if ( ! empty( $data['goals'] ) )               $score += 15;
    $rev = intval( preg_replace('/[^0-9]/', '', $data['monthly_revenue'] ?? '') );
    if ( $rev > 50000 )  $score += 10;
    if ( $rev > 100000 ) $score += 10;
    return min(100, $score);
}

// ── TRACK ACTIVITY ────────────────────────────────────────────────────────────
add_action( 'wp', function() {
    if ( is_user_logged_in() ) {
        $slugs = array('portal', 'advisor-portal', 'sales-portal');
        if ( in_array( get_post_field('post_name', get_the_ID()), $slugs, true ) ) {
            update_user_meta( get_current_user_id(), 'six_last_activity', current_time('mysql') );
        }
    }
} );

// ── AJAX: get advisor for user ─────────────────────────────────────────────────
if ( ! function_exists('six_ajax_get_advisor_for_user') ) {
    add_action( 'wp_ajax_six_get_advisor_for_user',        'six_ajax_get_advisor_for_user' );
    add_action( 'wp_ajax_nopriv_six_get_advisor_for_user', 'six_ajax_get_advisor_for_user' );
    function six_ajax_get_advisor_for_user() {
        check_ajax_referer('six_nonce', 'nonce');
        $uid = intval($_POST['user_id'] ?? 0);
        if ( ! $uid ) { wp_send_json_success(null); return; }
        $aid = six_get_client_advisor_id( $uid );
        if ( ! $aid ) { wp_send_json_success(null); return; }
        $adv = get_userdata($aid);
        $ini = function_exists('six_get_initials') ? six_get_initials($adv->display_name) : strtoupper(substr($adv->display_name,0,2));
        wp_send_json_success(array(
            'id'        => $aid,
            'name'      => $adv->display_name,
            'email'     => $adv->user_email,
            'initials'  => $ini,
            'role'      => 'Account Manager · 6ix Developers',
            'expertise' => array('Google Ads', 'SEO', 'Growth Strategy'),
        ));
    }
}

// ── AUTO-RUN DB MIGRATIONS ────────────────────────────────────────────────────
// Delegates to six_onboarding_db_upgrade() (ajax-onboarding.php) — the single
// source of truth for schema migrations. The previous inline v6 copy here was
// a subset that marked v6 done without adding the schedule_call_* columns,
// which broke six_schedule_onboarding_call on sites where it ran first.
add_action('init', function() {
    if ( ! is_user_logged_in() ) return;
    if ( get_option('six_onboarding_db_v7') ) return;
    if ( function_exists('six_onboarding_db_upgrade') ) {
        six_onboarding_db_upgrade();
    }
}, 20);
