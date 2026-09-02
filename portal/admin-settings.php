<?php
/**
 * 6ix Portal — Admin Settings + Advisor Dashboard Google Ads Fields
 *
 * Fixes:
 *  - Assign Advisors "Current Assignments" now shows correctly
 *  - Google Ads credentials stored per-client, editable by advisor
 *  - Admin settings page for all API keys
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ─────────────────────────────────────────────────────────────────────────────
// Admin Menu
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'admin_menu', 'six_admin_menu' );
function six_admin_menu() {
    add_menu_page(
        '6ix Portal', '6ix Portal',
        'manage_options', 'six-portal',
        'six_admin_overview',
        'dashicons-chart-area', 30
    );
    add_submenu_page( 'six-portal', 'Overview',        'Overview',        'manage_options', 'six-portal',          'six_admin_overview' );
    add_submenu_page( 'six-portal', 'Integrations',    'Integrations',    'manage_options', 'six-portal-settings', 'six_admin_settings' );
    add_submenu_page( 'six-portal', 'All Clients',     'All Clients',     'manage_options', 'six-portal-clients',  'six_admin_clients' );
    add_submenu_page( 'six-portal', 'Lead Pipeline',   'Lead Pipeline',   'manage_options', 'six-portal-leads',    'six_admin_leads' );
    add_submenu_page( 'six-portal', 'Assign Advisors', 'Assign Advisors', 'manage_options', 'six-portal-assign',   'six_admin_assign' );
}

// ─────────────────────────────────────────────────────────────────────────────
// Overview
// ─────────────────────────────────────────────────────────────────────────────
function six_admin_overview() {
    global $wpdb;

    // Make sure tables exist before querying
    $tables_exist = $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}six_client_services'" );
    $svc_count  = $tables_exist ? $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}six_client_services WHERE status='active'" ) : 0;
    $pend_count = $tables_exist ? $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}six_client_services WHERE status='pending'" ) : 0;
    $lead_count = $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}six_checkout_progress'" ) ?
        $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}six_checkout_progress WHERE score >= 70" ) : 0;

    $customers = get_users( array( 'role' => 'six_customer', 'count_total' => true ) );
    ?>
    <div class="wrap">
        <h1>6ix Developers Portal — Overview</h1>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin:20px 0">
            <?php
            $stats = array(
                array( 'Total Customers',   count( $customers ),     '#FF6699' ),
                array( 'Active Services',   intval( $svc_count ),    '#6ACAFD' ),
                array( 'Pending Approvals', intval( $pend_count ),   '#E3B341' ),
                array( 'Hot Leads (70+)',   intval( $lead_count ),   '#FF6B6B' ),
            );
            foreach ( $stats as $s ) {
                echo "<div style='background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;border-top:3px solid {$s[2]}'>
                    <div style='font-size:11px;color:#666;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px'>{$s[0]}</div>
                    <div style='font-size:32px;font-weight:700'>{$s[1]}</div>
                </div>";
            }
            ?>
        </div>
        <p><a href="<?php echo admin_url('admin.php?page=six-portal-settings'); ?>" class="button button-primary">Configure Integrations →</a></p>
    </div>
    <?php
}

// ─────────────────────────────────────────────────────────────────────────────
// Integration Settings
// ─────────────────────────────────────────────────────────────────────────────
function six_admin_settings() {
    // All saveable option keys
    $all_fields = array(
        'six_odoo_url', 'six_odoo_db', 'six_odoo_username', 'six_odoo_api_key',
        'six_odoo_project_id',
        'six_odoo_stage_new', 'six_odoo_stage_inprogress',
        'six_odoo_stage_submitted', 'six_odoo_stage_active',
        'six_stripe_publishable_key', 'six_stripe_secret_key', 'six_stripe_webhook_secret',
        'six_google_client_id', 'six_google_client_secret',
        'six_gads_developer_token', 'six_gads_manager_id', 'six_gads_kw_planner_account_id',
        'six_dataforseo_login', 'six_dataforseo_password',
        'six_gads_client_id', 'six_gads_client_secret', 'six_gads_refresh_token',
        'six_anthropic_api_key', 'six_google_analytics_property_id', 'six_google_analytics_key',
        'six_ai_model_deep', 'six_ai_model_fast',
        'six_ga4_property_id', 'six_meta_app_id', 'six_meta_ad_account_id',
        'six_ga4_property_id', 'six_meta_app_id', 'six_meta_ad_account_id',
        // Twilio SMS
        'six_twilio_account_sid', 'six_twilio_auth_token', 'six_twilio_from_number',
        // Notification email recipients
        'six_admin_notify_emails',
    );

    if ( isset( $_POST['six_save_settings'] ) && check_admin_referer( 'six_settings' ) ) {
        $mask = str_repeat( '•', 12 );
        foreach ( $all_fields as $f ) {
            if ( isset( $_POST[$f] ) && $_POST[$f] !== $mask ) {
                // Unslash before sanitising: WordPress adds slashes to $_POST, and
                // API secrets can contain quotes/backslashes. Without this a
                // password like a"b would be stored as a\"b and fail auth (401).
                update_option( $f, sanitize_text_field( wp_unslash( $_POST[$f] ) ) );
            }
        }
        // Multiline / structured options must NOT go through sanitize_text_field
        // (it would collapse the service-account JSON's private key). Admin-only
        // + nonce-verified, so store raw after unslashing.
        foreach ( array( 'six_ga4_service_account_json' ) as $tf ) {
            if ( isset( $_POST[ $tf ] ) ) update_option( $tf, trim( wp_unslash( $_POST[ $tf ] ) ) );
        }
        // Write-only secrets: only save when a new value is typed, so submitting
        // the form with the field left blank keeps the existing token.
        foreach ( array( 'six_meta_access_token', 'six_meta_app_secret' ) as $sf ) {
            if ( isset( $_POST[ $sf ] ) && trim( (string) $_POST[ $sf ] ) !== '' ) {
                update_option( $sf, sanitize_text_field( wp_unslash( $_POST[ $sf ] ) ) );
            }
        }
        echo '<div class="notice notice-success is-dismissible"><p>✓ Settings saved.</p></div>';
    }

    // Helper: masked display of sensitive options
    $mask = str_repeat( '•', 12 );
    $s = function($key, $is_secret=false) use ($mask) {
        $val = get_option($key,'');
        return $is_secret ? ($val ? esc_attr($mask) : '') : esc_attr($val);
    };

    // Odoo connection status
    $odoo_ok = false;
    if ( get_option('six_odoo_url') && get_option('six_odoo_api_key') && class_exists('Six_Odoo') ) {
        $odoo_ok = Six_Odoo::test_connection();
    }
    $odoo_badge = $odoo_ok
        ? '<span style="background:#d4edda;color:#155724;padding:3px 10px;border-radius:4px;font-size:12px;font-weight:600"> Connected</span>'
        : '<span style="background:#f8d7da;color:#721c24;padding:3px 10px;border-radius:4px;font-size:12px;font-weight:600"> Not connected</span>';

    // Stage IDs status
    $stages_set = get_option('six_odoo_stage_new') && get_option('six_odoo_stage_submitted');

    // Small helper: green "Connected" / grey "Not set" badge from a boolean.
    $badge = function( $ok, $on = 'Connected', $off = 'Not set' ) {
        return $ok
            ? '<span class="six-int-badge ok">● ' . esc_html( $on ) . '</span>'
            : '<span class="six-int-badge no">○ ' . esc_html( $off ) . '</span>';
    };
    // Configured flags per integration.
    $has_twilio = get_option('six_twilio_account_sid') && get_option('six_twilio_auth_token') && get_option('six_twilio_from_number');
    $has_stripe = (bool) get_option('six_stripe_secret_key');
    $has_gads   = get_option('six_gads_developer_token') && get_option('six_gads_manager_id') && get_option('six_gads_refresh_token');
    $has_dfs    = get_option('six_dataforseo_login') && get_option('six_dataforseo_password');
    $has_gcal   = get_option('six_google_client_id') && get_option('six_google_client_secret');
    $has_ai     = (bool) get_option('six_anthropic_api_key');
    $has_ga4    = get_option('six_ga4_property_id') && get_option('six_ga4_service_account_json');
    $has_meta   = get_option('six_meta_access_token') && get_option('six_meta_ad_account_id');
    ?>
    <style>
        .six-int-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(360px,1fr));gap:16px;margin:18px 0 24px;align-items:stretch}
        .six-int-card{background:#fff;border:1px solid #dcdcde;border-radius:10px;overflow:hidden;box-shadow:0 1px 2px rgba(0,0,0,.04);display:flex;flex-direction:column;height:100%}
        .six-int-card.wide{grid-column:1/-1}
        .six-int-head{display:flex;align-items:center;gap:9px;padding:12px 16px;border-bottom:1px solid #f0f0f1;background:#f6f7f7;font-size:14px;font-weight:600;color:#1d2327}
        .six-int-head .dot{width:11px;height:11px;border-radius:50%;flex:none}
        .six-int-badge{margin-left:auto;font-size:11px;font-weight:600;padding:2px 9px;border-radius:20px;white-space:nowrap}
        .six-int-badge.ok{background:#d4edda;color:#155724}
        .six-int-badge.no{background:#eef0f1;color:#787c82}
        .six-int-body{padding:15px 16px;display:flex;flex-direction:column;gap:12px;flex:1 1 auto}
        .six-int-body .hint{font-size:12px;color:#646970;line-height:1.5;margin:-2px 0 2px}
        .six-fld label{display:block;font-size:12px;font-weight:600;color:#1d2327;margin-bottom:4px}
        .six-fld input[type=text],.six-fld input[type=password],.six-fld textarea{width:100%;max-width:100%}
        .six-fld .desc{font-size:11px;color:#787c82;margin-top:4px;line-height:1.5}
        .six-int-body .grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        .six-int-body .grid2 .six-fld{margin:0}
        .six-int-sub{font-size:12px;font-weight:700;color:#1d2327;text-transform:uppercase;letter-spacing:.4px;margin:4px 0 -2px}
        .six-int-test{display:flex;align-items:center;gap:10px;flex-wrap:wrap;font-size:13px}
        .six-savebar{position:sticky;bottom:0;background:#fff;border-top:1px solid #dcdcde;padding:12px 0;margin-top:8px;z-index:5}
        @media(max-width:782px){.six-int-body .grid2{grid-template-columns:1fr}}
    </style>
    <div class="wrap">
        <h1>6ix Portal — Integration Settings</h1>
        <p style="color:#646970;font-size:13px;max-width:760px">Each card below is one integration. Fill in the fields, then <strong>Save All Settings</strong> at the bottom. Password fields marked “leave blank to keep existing” are write-only for security. Per-client IDs (Google Ads customer ID, GA4 property, Meta ad account) are set by advisors on each client’s Data Sources tab — the credentials here authorise all of them.</p>

        <form method="post">
            <?php wp_nonce_field( 'six_settings' ); ?>

            <div class="six-int-grid">

            <!-- ═══ NOTIFICATIONS ══════════════════════════════════════ -->
            <div class="six-int-card wide">
                <div class="six-int-head"><span class="dot" style="background:#031523"></span> Notifications <?php echo $badge( true, 'Active' ); ?></div>
                <div class="six-int-body">
                    <div class="hint">Every admin/owner copy of a notification email — form submissions, onboarding abandonment/completion, budget changes, service requests and activations — is sent to these addresses. Edit the email templates themselves under 6ix Portal → Forms (each one, including the "System Generated" entries, has its own editable Subject/Body).</div>
                    <div class="six-fld"><label>Recipients</label><input type="text" name="six_admin_notify_emails" value="<?php echo $s('six_admin_notify_emails'); ?>" placeholder="musab@6ixdevelopers.com, faheem@6ixdevelopers.com"><div class="desc">Comma-separated. Leave blank to use the default (musab@6ixdevelopers.com, faheem@6ixdevelopers.com).</div></div>
                </div>
            </div>

            <!-- ═══ ODOO CRM ═══════════════════════════════════════════ -->
            <div class="six-int-card wide">
                <div class="six-int-head"><span class="dot" style="background:#FF6699"></span> Odoo CRM <?php echo $badge($odoo_ok); ?></div>
                <div class="six-int-body">
                    <?php if ( !$odoo_ok && get_option('six_odoo_url') ) : ?>
                    <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:10px 14px;font-size:12px;line-height:1.6">
                        <strong>Connection failed.</strong> URL must have <strong>no trailing slash</strong> (e.g. <code>https://acme.odoo.com</code>); the API Key is generated in Odoo → Settings → Technical → <strong>API Keys</strong> (not your password); Username is your Odoo login email.
                    </div>
                    <?php endif; ?>
                    <div class="grid2">
                        <div class="six-fld"><label>Instance URL</label><input type="text" name="six_odoo_url" value="<?php echo $s('six_odoo_url'); ?>" placeholder="https://acme.odoo.com"><div class="desc">No trailing slash.</div></div>
                        <div class="six-fld"><label>Database Name</label><input type="text" name="six_odoo_db" value="<?php echo $s('six_odoo_db'); ?>" placeholder="acme"><div class="desc">Usually your Odoo.com subdomain.</div></div>
                        <div class="six-fld"><label>Login Email</label><input type="text" name="six_odoo_username" value="<?php echo $s('six_odoo_username'); ?>" placeholder="admin@yourcompany.com"></div>
                        <div class="six-fld"><label>API Key</label><input type="password" name="six_odoo_api_key" value="<?php echo $s('six_odoo_api_key',true); ?>" placeholder="Odoo API key"><div class="desc">Settings → Technical → API Keys → New. Not your password.</div></div>
                    </div>
                    <?php if ( $odoo_ok ) : ?>
                    <div style="background:#d4edda;border:1px solid #c3e6cb;border-radius:6px;padding:10px 14px;font-size:12px">
                        <strong>Connected!</strong>
                        <?php if ( !$stages_set ) : ?>
                            Run the one-time setup to create custom fields and pipeline stages:
                            <a href="<?php echo admin_url('?six_odoo_setup=1'); ?>" class="button button-small" style="margin-left:8px">Run Odoo Setup →</a>
                        <?php else : ?>
                            Pipeline stages configured — integration fully active.
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <div class="six-int-sub">CRM Pipeline Stages <?php echo $stages_set ? $badge(true,'Configured') : $badge(false,'','Not set — run setup'); ?></div>
                    <div class="hint">Auto-populated by Odoo Setup, or enter manually — the stage ID is in the URL when you open a stage in Odoo CRM → Configuration → Stages.</div>
                    <div class="grid2">
                        <div class="six-fld"><label>New Lead — Stage ID</label><input type="text" name="six_odoo_stage_new" value="<?php echo $s('six_odoo_stage_new'); ?>" placeholder="e.g. 1"><div class="desc">Current: <?php echo get_option('six_odoo_stage_new','<em>not set</em>'); ?></div></div>
                        <div class="six-fld"><label>Onboarding In Progress — Stage ID</label><input type="text" name="six_odoo_stage_inprogress" value="<?php echo $s('six_odoo_stage_inprogress'); ?>" placeholder="e.g. 2"><div class="desc">Current: <?php echo get_option('six_odoo_stage_inprogress','<em>not set</em>'); ?></div></div>
                        <div class="six-fld"><label>Onboarding Submitted — Stage ID</label><input type="text" name="six_odoo_stage_submitted" value="<?php echo $s('six_odoo_stage_submitted'); ?>" placeholder="e.g. 3"><div class="desc">Current: <?php echo get_option('six_odoo_stage_submitted','<em>not set</em>'); ?></div></div>
                        <div class="six-fld"><label>Active Client — Stage ID</label><input type="text" name="six_odoo_stage_active" value="<?php echo $s('six_odoo_stage_active'); ?>" placeholder="e.g. 4"><div class="desc">Current: <?php echo get_option('six_odoo_stage_active','<em>not set</em>'); ?></div></div>
                        <div class="six-fld"><label>Tasks Project ID</label><input type="text" name="six_odoo_project_id" value="<?php echo $s('six_odoo_project_id'); ?>" placeholder="e.g. 1"><div class="desc">Current: <?php echo get_option('six_odoo_project_id','<em>not set</em>'); ?></div></div>
                    </div>
                </div>
            </div>

            <!-- ═══ ANTHROPIC AI ═══════════════════════════════════════ -->
            <div class="six-int-card">
                <div class="six-int-head"><span class="dot" style="background:#8781BA"></span> AI Intelligence — Anthropic <?php echo $badge($has_ai); ?></div>
                <div class="six-int-body">
                    <div class="hint">Powers the AI Strategist, growth plans and competitor intelligence. Key from <a href="https://console.anthropic.com" target="_blank">console.anthropic.com</a>.</div>
                    <div class="six-fld"><label>Anthropic API Key</label><input type="password" name="six_anthropic_api_key" value="<?php echo $s('six_anthropic_api_key',true); ?>" placeholder="sk-ant-..."><div class="desc">Server-side only, never exposed to the browser.</div></div>
                    <div class="six-fld"><label>Deep model (audits &amp; strategy)</label><input type="text" name="six_ai_model_deep" value="<?php echo $s('six_ai_model_deep'); ?>" placeholder="claude-opus-5"><div class="desc">Default <code>claude-opus-5</code>.</div></div>
                    <div class="six-fld"><label>Fast model (quick tasks)</label><input type="text" name="six_ai_model_fast" value="<?php echo $s('six_ai_model_fast'); ?>" placeholder="claude-sonnet-5"><div class="desc">Default <code>claude-sonnet-5</code>.</div></div>
                </div>
            </div>

            <!-- ═══ GOOGLE ADS (MCC) ═══════════════════════════════════ -->
            <div class="six-int-card">
                <div class="six-int-head"><span class="dot" style="background:#4285F4"></span> Google Ads — Manager (MCC) <?php echo $badge($has_gads); ?></div>
                <div class="six-int-body">
                    <div class="hint">One-time agency setup. Advisors then only enter a Customer ID per client on their Data Sources tab.</div>
                    <div class="six-fld"><label>Developer Token</label><input type="password" name="six_gads_developer_token" value="<?php echo $s('six_gads_developer_token',true); ?>"><div class="desc">From the <a href="https://ads.google.com/aw/apicenter" target="_blank">Google Ads API Center</a>.</div></div>
                    <div class="grid2">
                        <div class="six-fld"><label>Manager Account ID (MCC)</label><input type="text" name="six_gads_manager_id" value="<?php echo $s('six_gads_manager_id'); ?>" placeholder="123-456-7890"></div>
                        <div class="six-fld"><label>Keyword Planner Account ID</label><input type="text" name="six_gads_kw_planner_account_id" value="<?php echo $s('six_gads_kw_planner_account_id'); ?>" placeholder="906-224-1852"><div class="desc">A client account under the MCC with active billing.</div></div>
                    </div>
                    <div class="six-fld"><label>OAuth Client ID</label><input type="text" name="six_gads_client_id" value="<?php echo $s('six_gads_client_id'); ?>" placeholder="xxxxxx.apps.googleusercontent.com"><div class="desc"><a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a> → OAuth 2.0 Client IDs.</div></div>
                    <div class="grid2">
                        <div class="six-fld"><label>OAuth Client Secret</label><input type="password" name="six_gads_client_secret" value="<?php echo $s('six_gads_client_secret',true); ?>"></div>
                        <div class="six-fld"><label>MCC Refresh Token</label><input type="password" name="six_gads_refresh_token" value="<?php echo $s('six_gads_refresh_token',true); ?>"><div class="desc">Does not expire unless revoked.</div></div>
                    </div>
                </div>
            </div>

            <!-- ═══ DATAFORSEO ═════════════════════════════════════════ -->
            <div class="six-int-card">
                <div class="six-int-head"><span class="dot" style="background:#00b894"></span> DataForSEO — Keyword &amp; SEO Data <?php echo $badge($has_dfs); ?></div>
                <div class="six-int-body">
                    <div class="hint">Real CPC, search volume, rankings and on-page data. Free trial at <a href="https://dataforseo.com" target="_blank">dataforseo.com</a>.</div>
                    <div class="six-fld"><label>Login (account email)</label><input type="text" name="six_dataforseo_login" value="<?php echo $s('six_dataforseo_login'); ?>" placeholder="your@email.com"></div>
                    <div class="six-fld"><label>API Password</label><input type="password" name="six_dataforseo_password" value="<?php echo $s('six_dataforseo_password', true); ?>" placeholder="••••••••"><div class="desc">The API password from DataForSEO → API Access — not your website login.</div></div>
                    <div class="six-int-test">
                        <button type="button" class="button" id="six-dfs-test">Test connection</button>
                        <span id="six-dfs-test-result" style="font-weight:600"></span>
                    </div>
                    <div class="desc">Save credentials first, then run a live check (balance + sample lookup).</div>
                    <script>
                    (function(){
                        var btn=document.getElementById('six-dfs-test'), out=document.getElementById('six-dfs-test-result');
                        if(!btn) return;
                        btn.addEventListener('click',function(){
                            btn.disabled=true; out.style.color='#666'; out.textContent='Testing…';
                            var fd=new FormData();
                            fd.append('action','six_test_dataforseo');
                            fd.append('nonce','<?php echo esc_js( wp_create_nonce('six_dfs_test') ); ?>');
                            fetch(ajaxurl,{method:'POST',body:fd,credentials:'same-origin'})
                                .then(function(r){return r.json();})
                                .then(function(res){
                                    btn.disabled=false;
                                    var d=res&&res.data?res.data:{};
                                    out.style.color=(res&&res.success&&d.stage==='ok')?'#155724':((res&&res.success)?'#856404':'#a00');
                                    out.textContent=(d.message)||((res&&res.success)?'OK':'Test failed.');
                                })
                                .catch(function(){ btn.disabled=false; out.style.color='#a00'; out.textContent='Request failed.'; });
                        });
                    })();
                    </script>
                </div>
            </div>

            <!-- ═══ GOOGLE ANALYTICS 4 ═════════════════════════════════ -->
            <div class="six-int-card">
                <div class="six-int-head"><span class="dot" style="background:#E8710A"></span> Google Analytics 4 <?php echo $badge($has_ga4); ?></div>
                <div class="six-int-body">
                    <div class="hint">Agency service account. Per-client GA4 property IDs are set on the Data Sources tab.</div>
                    <div class="six-fld"><label>Default GA4 Property ID</label><input type="text" name="six_ga4_property_id" value="<?php echo esc_attr(get_option('six_ga4_property_id','')); ?>" placeholder="123456789"><div class="desc">9-digit number from GA4 Admin → Property Settings (no G- prefix).</div></div>
                    <div class="six-fld"><label>Service Account JSON</label><textarea name="six_ga4_service_account_json" rows="5" class="large-text" placeholder='{"type":"service_account", ...}'><?php echo esc_textarea(get_option('six_ga4_service_account_json','')); ?></textarea><div class="desc">Full JSON of your Google Cloud service-account key. <a href="https://developers.google.com/analytics/devguides/reporting/data/v1/quickstart-client-libraries" target="_blank">Setup guide →</a></div></div>
                    <div class="six-int-test">
                        <button type="button" class="button" id="six-test-ga4">Test connection</button>
                        <span id="six-ga4-result" style="font-size:13px"></span>
                    </div>
                </div>
            </div>

            <!-- ═══ META ADS ═══════════════════════════════════════════ -->
            <div class="six-int-card">
                <div class="six-int-head"><span class="dot" style="background:#1877F2"></span> Meta Ads (Facebook / Instagram) <?php echo $badge($has_meta); ?></div>
                <div class="six-int-body">
                    <div class="hint">Agency System User token reads every client ad account under your Business Manager. Per-client ad account IDs are set on the Data Sources tab.</div>
                    <div class="six-fld"><label>System User Access Token</label><input type="password" name="six_meta_access_token" placeholder="EAAxxxxxxx… — leave blank to keep existing"><div class="desc"><?php echo get_option('six_meta_access_token') ? '✓ Saved (hidden)' : 'Not set'; ?> — token with <code>ads_read</code> from <a href="https://business.facebook.com/settings/system-users" target="_blank">Business Manager → System Users</a>.</div></div>
                    <div class="grid2">
                        <div class="six-fld"><label>App ID</label><input type="text" name="six_meta_app_id" value="<?php echo esc_attr(get_option('six_meta_app_id','')); ?>" placeholder="1234567890"><div class="desc"><a href="https://developers.facebook.com/apps" target="_blank">Meta for Developers</a> → App → Settings.</div></div>
                        <div class="six-fld"><label>App Secret</label><input type="password" name="six_meta_app_secret" placeholder="Leave blank to keep existing"><div class="desc"><?php echo get_option('six_meta_app_secret') ? '✓ Saved (hidden)' : 'Not set'; ?></div></div>
                    </div>
                    <div class="six-fld"><label>Default Ad Account ID</label><input type="text" name="six_meta_ad_account_id" value="<?php echo esc_attr(get_option('six_meta_ad_account_id','')); ?>" placeholder="act_1234567890"><div class="desc">Format act_XXXXXXXX — found in the Ads Manager URL.</div></div>
                    <div class="six-int-test">
                        <button type="button" class="button" id="six-test-meta">Test connection</button>
                        <span id="six-meta-result" style="font-size:13px"></span>
                    </div>
                </div>
            </div>

            <!-- ═══ GOOGLE CALENDAR ════════════════════════════════════ -->
            <div class="six-int-card">
                <div class="six-int-head"><span class="dot" style="background:#3C6478"></span> Google Calendar (Meet links) <?php echo $badge($has_gcal); ?></div>
                <div class="six-int-body">
                    <div class="hint">Agency OAuth for booking calls and generating Google Meet links.</div>
                    <div class="six-fld"><label>OAuth Client ID</label><input type="text" name="six_google_client_id" value="<?php echo $s('six_google_client_id'); ?>"></div>
                    <div class="six-fld"><label>OAuth Client Secret</label><input type="password" name="six_google_client_secret" value="<?php echo $s('six_google_client_secret',true); ?>"></div>
                </div>
            </div>

            <!-- ═══ STRIPE ═════════════════════════════════════════════ -->
            <div class="six-int-card">
                <div class="six-int-head"><span class="dot" style="background:#635BFF"></span> Stripe (Payments) <?php echo $badge($has_stripe); ?></div>
                <div class="six-int-body">
                    <div class="six-fld"><label>Publishable Key</label><input type="text" name="six_stripe_publishable_key" value="<?php echo $s('six_stripe_publishable_key'); ?>" placeholder="pk_live_..."></div>
                    <div class="six-fld"><label>Secret Key</label><input type="password" name="six_stripe_secret_key" value="<?php echo $s('six_stripe_secret_key',true); ?>" placeholder="sk_live_..."><div class="desc">Server-side only — never shared.</div></div>
                    <div class="six-fld"><label>Webhook Secret</label><input type="password" name="six_stripe_webhook_secret" value="<?php echo $s('six_stripe_webhook_secret',true); ?>" placeholder="whsec_..."><div class="desc">Webhook URL: <code><?php echo esc_html( home_url('/wp-json/six/v1/stripe-webhook') ); ?></code></div></div>
                </div>
            </div>

            <!-- ═══ TWILIO SMS ═════════════════════════════════════════ -->
            <div class="six-int-card">
                <div class="six-int-head"><span class="dot" style="background:#F22F46"></span> Twilio SMS <?php echo $badge($has_twilio); ?></div>
                <div class="six-int-body">
                    <div class="hint">Sent automatically on abandoned checkout. Credentials at <a href="https://console.twilio.com" target="_blank">console.twilio.com</a>.</div>
                    <div class="six-fld"><label>Account SID</label><input type="text" name="six_twilio_account_sid" value="<?php echo $s('six_twilio_account_sid'); ?>" placeholder="ACxxxxxxxx..."></div>
                    <div class="grid2">
                        <div class="six-fld"><label>Auth Token</label><input type="password" name="six_twilio_auth_token" value="<?php echo $s('six_twilio_auth_token',true); ?>" placeholder="Auth token"></div>
                        <div class="six-fld"><label>From Number</label><input type="text" name="six_twilio_from_number" value="<?php echo $s('six_twilio_from_number'); ?>" placeholder="+14155550000"><div class="desc">E.164 format, SMS-capable.</div></div>
                    </div>
                </div>
            </div>

            </div><!-- /.six-int-grid -->

            <script>
            document.getElementById('six-test-ga4').addEventListener('click',function(){
                var r=document.getElementById('six-ga4-result');
                r.innerHTML='Testing…';
                fetch(ajaxurl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
                    body:new URLSearchParams({action:'six_test_ga4',_ajax_nonce:'<?php echo wp_create_nonce("six_test_ga4"); ?>',property_id:document.querySelector('[name="six_ga4_property_id"]').value})})
                .then(function(res){return res.json();})
                .then(function(d){r.innerHTML=d.success?'<span style="color:#0a7a2f">✓ '+d.data+'</span>':'<span style="color:#a00">'+(d.data||'Failed')+'</span>';});
            });
            document.getElementById('six-test-meta').addEventListener('click',function(){
                var r=document.getElementById('six-meta-result');
                r.innerHTML='Testing…';
                fetch(ajaxurl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
                    body:new URLSearchParams({action:'six_test_meta',_ajax_nonce:'<?php echo wp_create_nonce("six_test_meta"); ?>'})})
                .then(function(res){return res.json();})
                .then(function(d){r.innerHTML=d.success?'<span style="color:#0a7a2f">✓ '+d.data+'</span>':'<span style="color:#a00">'+(d.data||'Failed')+'</span>';});
            });
            </script>

            <div class="six-savebar">
                <?php submit_button( 'Save All Settings', 'primary large', 'six_save_settings', false ); ?>
            </div>
        </form>
    </div>
    <?php
}

// ─────────────────────────────────────────────────────────────────────────────
// Assign Advisors — FIXED: correctly queries the assignments table
// ─────────────────────────────────────────────────────────────────────────────
function six_admin_assign() {
    global $wpdb;
    $table = $wpdb->prefix . 'six_assignments';
    $roles = six_advisor_service_roles();

    // Handle assignment form submission
    if ( isset( $_POST['six_assign'] ) && check_admin_referer( 'six_assign' ) ) {
        $client_id  = intval( $_POST['client_id'] );
        $advisor_id = intval( $_POST['advisor_id'] );
        $service_role = sanitize_key( $_POST['service_role'] ?? '' );
        if ( ! isset( $roles[ $service_role ] ) ) $service_role = '';
        if ( $client_id && $advisor_id ) {
            // Check table exists
            $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table'" );
            if ( ! $table_exists ) {
                six_create_tables();
            }
            // Upsert — keyed on (client_id, service_role), so a client can
            // have one advisor per role (General + one per service) instead
            // of exactly one advisor overall.
            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM $table WHERE client_id = %d AND service_role = %s", $client_id, $service_role
            ) );
            if ( $existing ) {
                $wpdb->update( $table, array( 'advisor_id' => $advisor_id ), array( 'id' => $existing ) );
            } else {
                $wpdb->insert( $table, array( 'client_id' => $client_id, 'advisor_id' => $advisor_id, 'service_role' => $service_role ) );
            }
            echo '<div class="notice notice-success is-dismissible"><p>✓ Advisor assigned successfully.</p></div>';
        }
    }

    // Handle unassign
    if ( isset( $_GET['unassign'] ) && check_admin_referer( 'six_unassign_' . intval( $_GET['unassign'] ) ) ) {
        $wpdb->delete( $table, array( 'id' => intval( $_GET['unassign'] ) ) );
        echo '<div class="notice notice-success is-dismissible"><p>Assignment removed.</p></div>';
    }

    $customers = get_users( array( 'role' => 'six_customer' ) );
    $advisors  = get_users( array( 'role__in' => array( 'six_advisor', 'administrator' ) ) );

    // Current assignments — join with WP users table directly to avoid meta dependency
    $assignments = $wpdb->get_results(
        "SELECT a.id, a.client_id, a.advisor_id, a.service_role, a.assigned_at,
                uc.display_name AS client_name, uc.user_email AS client_email,
                ua.display_name AS advisor_name
         FROM {$table} a
         INNER JOIN {$wpdb->users} uc ON a.client_id  = uc.ID
         INNER JOIN {$wpdb->users} ua ON a.advisor_id = ua.ID
         ORDER BY a.client_id ASC, (a.service_role='') DESC, a.service_role ASC"
    );
    ?>
    <div class="wrap">
        <h1>Assign Advisors to Clients</h1>
        <p style="color:#666;max-width:720px">A client can have a <strong>General</strong> advisor (their main point of contact), an <strong>All Services</strong> advisor (handles everything active), and/or a dedicated advisor per service — Google Ads, SEO, SMM. Assigning a new advisor to a role that's already filled replaces that role's advisor only; other roles are untouched. This same management is also available per-client from <a href="<?php echo admin_url('admin.php?page=six-portal-clients'); ?>">All Clients</a>.</p>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:30px;margin-top:20px">

            <!-- Assignment Form -->
            <div>
                <h2>Assign / Reassign</h2>
                <form method="post" style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px">
                    <?php wp_nonce_field( 'six_assign' ); ?>
                    <table class="form-table" style="margin:0">
                        <tr>
                            <th style="width:100px">Client</th>
                            <td>
                                <select name="client_id" style="width:100%">
                                    <option value="">— Select a customer —</option>
                                    <?php foreach ( $customers as $c ) : ?>
                                        <option value="<?php echo $c->ID; ?>">
                                            <?php echo esc_html( $c->display_name ); ?> (<?php echo esc_html( $c->user_email ); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                    <?php if ( empty( $customers ) ) : ?>
                                        <option disabled>No customers found — create a user with role "Portal Customer" first</option>
                                    <?php endif; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Role</th>
                            <td>
                                <select name="service_role" style="width:100%">
                                    <?php foreach ( $roles as $rkey => $rlabel ) : ?>
                                        <option value="<?php echo esc_attr( $rkey ); ?>"><?php echo esc_html( $rlabel ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Advisor</th>
                            <td>
                                <select name="advisor_id" style="width:100%">
                                    <option value="">— Select an advisor —</option>
                                    <?php foreach ( $advisors as $a ) : ?>
                                        <option value="<?php echo $a->ID; ?>">
                                            <?php echo esc_html( $a->display_name ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <?php if ( empty( $advisors ) ) : ?>
                                        <option disabled>No advisors found — create a user with role "Portal Advisor" first</option>
                                    <?php endif; ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button( 'Assign Advisor', 'primary', 'six_assign', false ); ?>
                </form>
            </div>

            <!-- Current Assignments -->
            <div>
                <h2>Current Assignments (<?php echo count( $assignments ); ?>)</h2>
                <?php if ( empty( $assignments ) ) : ?>
                    <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:16px">
                        <strong>No assignments yet.</strong><br>
                        Use the form on the left to assign an advisor to a client.
                        <br><br>
                        <strong>Troubleshooting:</strong> If you've assigned before but see nothing here, make sure:
                        <ol style="margin:8px 0 0 16px">
                            <li>The customer has role <code>Portal Customer</code> (not just "Subscriber")</li>
                            <li>The advisor has role <code>Portal Advisor</code></li>
                            <li>The portal database tables are installed — visit <a href="<?php echo admin_url('admin.php?page=six-portal&six_install=1'); ?>">this link</a> to reinstall</li>
                        </ol>
                    </div>
                <?php else : ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Role</th>
                                <th>Advisor</th>
                                <th>Since</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $assignments as $row ) : ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html( $row->client_name ); ?></strong><br>
                                    <small style="color:#666"><?php echo esc_html( $row->client_email ); ?></small>
                                </td>
                                <td><span class="button button-small" style="pointer-events:none"><?php echo esc_html( $roles[ $row->service_role ] ?? $row->service_role ); ?></span></td>
                                <td><?php echo esc_html( $row->advisor_name ); ?></td>
                                <td><?php echo esc_html( date( 'M j, Y', strtotime( $row->assigned_at ) ) ); ?></td>
                                <td>
                                    <a href="<?php echo wp_nonce_url(
                                        admin_url( 'admin.php?page=six-portal-assign&unassign=' . $row->id ),
                                        'six_unassign_' . $row->id
                                    ); ?>" class="button button-small" onclick="return confirm('Remove this assignment?')">Remove</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

// ─────────────────────────────────────────────────────────────────────────────
// Google Ads Credentials Per Client — Advisor edits from WP User profile
// Also exposed as AJAX for the Advisor Portal dashboard
// ─────────────────────────────────────────────────────────────────────────────

// Show Google Ads fields on the user profile page (only advisors/admins can see)
add_action( 'show_user_profile', 'six_show_gads_fields' );
add_action( 'edit_user_profile', 'six_show_gads_fields' );

function six_show_gads_fields( $user ) {
    if ( ! current_user_can( 'six_manage_clients' ) && ! current_user_can( 'manage_options' ) ) return;
    if ( ! in_array( 'six_customer', (array) $user->roles, true ) ) return;
    ?>
    <h2>Google Ads Integration</h2>
    <p style="color:#666;font-size:13px">
        These credentials connect this client's Google Ads account to their portal dashboard.
        Metrics will update daily automatically once credentials are saved.
    </p>
    <table class="form-table">
        <tr>
            <th><label for="six_gads_customer_id">Google Ads Customer ID</label></th>
            <td>
                <input type="text" name="six_gads_customer_id" id="six_gads_customer_id"
                       value="<?php echo esc_attr( get_user_meta( $user->ID, 'six_gads_customer_id', true ) ); ?>"
                       class="regular-text" placeholder="123-456-7890">
                <p class="description">Found in Google Ads → top right corner → Customer ID (no dashes needed)</p>
            </td>
        </tr>
        <tr>
            <th><label for="six_gads_refresh_token">OAuth Refresh Token</label></th>
            <td>
                <input type="password" name="six_gads_refresh_token" id="six_gads_refresh_token"
                       value="<?php echo esc_attr( get_user_meta( $user->ID, 'six_gads_refresh_token', true ) ); ?>"
                       class="regular-text" placeholder="1//0g...">
                <p class="description">Generated via Google OAuth flow for this client's account</p>
            </td>
        </tr>
        <tr>
            <th><label for="six_gads_login_customer_id">Manager Account ID <small>(optional)</small></label></th>
            <td>
                <input type="text" name="six_gads_login_customer_id" id="six_gads_login_customer_id"
                       value="<?php echo esc_attr( get_user_meta( $user->ID, 'six_gads_login_customer_id', true ) ); ?>"
                       class="regular-text" placeholder="Leave blank to use global manager ID">
            </td>
        </tr>
        <tr>
            <th>Last Sync</th>
            <td>
                <?php
                $last_sync = get_user_meta( $user->ID, 'six_gads_last_sync', true );
                echo $last_sync ? esc_html( date( 'M j, Y g:i A', strtotime( $last_sync ) ) ) : '<em>Never synced yet</em>';
                ?>
                <?php if ( $last_sync ) : ?>
                    &nbsp;&nbsp;<a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=six-portal-clients&sync_gads=' . $user->ID ), 'six_sync_gads_' . $user->ID ); ?>" class="button button-small">Sync Now</a>
                <?php endif; ?>
            </td>
        </tr>
    </table>
    <?php wp_nonce_field( 'six_save_gads_' . $user->ID, 'six_gads_nonce' ); ?>
    <?php
}

// Save the Google Ads fields
add_action( 'personal_options_update',  'six_save_gads_fields' );
add_action( 'edit_user_profile_update', 'six_save_gads_fields' );

function six_save_gads_fields( $user_id ) {
    if ( ! isset( $_POST['six_gads_nonce'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['six_gads_nonce'], 'six_save_gads_' . $user_id ) ) return;
    if ( ! current_user_can( 'six_manage_clients' ) && ! current_user_can( 'manage_options' ) ) return;

    $fields = array( 'six_gads_customer_id', 'six_gads_refresh_token', 'six_gads_login_customer_id' );
    foreach ( $fields as $f ) {
        if ( isset( $_POST[ $f ] ) ) {
            update_user_meta( $user_id, $f, sanitize_text_field( $_POST[ $f ] ) );
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// NOTE: six_save_client_gads, six_get_client_gads, six_sync_client_gads are
// all registered in ajax-handlers.php. Duplicate registrations removed here.
// ─────────────────────────────────────────────────────────────────────────────

// ─────────────────────────────────────────────────────────────────────────────
// All Clients admin page
// ─────────────────────────────────────────────────────────────────────────────
function six_admin_clients() {
    global $wpdb;
    $roles = six_advisor_service_roles(); // '' => General, 'google-ads', 'seo', 'smm'

    // Handle manual Google Ads sync trigger
    if ( isset( $_GET['sync_gads'] ) && check_admin_referer( 'six_sync_gads_' . intval( $_GET['sync_gads'] ) ) ) {
        $client_id = intval( $_GET['sync_gads'] );
        Six_Google_Ads::get_campaign_metrics_for_client( $client_id );
        update_user_meta( $client_id, 'six_gads_last_sync', current_time( 'mysql' ) );
        echo '<div class="notice notice-success is-dismissible"><p>✓ Google Ads synced.</p></div>';
    }

    // Handle the "Manage Advisors" modal save — one advisor (or none) per
    // role for this client. Every role field is present in the POST (the
    // modal always renders all 4 role rows), so a role set to "0" means
    // "unassign this role" and any existing row for it is removed.
    if ( isset( $_POST['six_save_advisors'] ) && isset( $_POST['client_id'] ) ) {
        $save_client_id = intval( $_POST['client_id'] );
        if ( $save_client_id && check_admin_referer( 'six_save_advisors_' . $save_client_id, 'six_advisors_nonce_field' ) ) {
            $table = $wpdb->prefix . 'six_assignments';
            foreach ( $roles as $rkey => $rlabel ) {
                $field      = 'role_' . ( $rkey === '' ? 'general' : sanitize_key( $rkey ) );
                $advisor_id = intval( $_POST[ $field ] ?? 0 );
                $existing   = $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM $table WHERE client_id=%d AND service_role=%s", $save_client_id, $rkey
                ) );
                if ( $advisor_id > 0 ) {
                    if ( $existing ) {
                        $wpdb->update( $table, array( 'advisor_id' => $advisor_id ), array( 'id' => $existing ) );
                    } else {
                        $wpdb->insert( $table, array( 'client_id' => $save_client_id, 'advisor_id' => $advisor_id, 'service_role' => $rkey ) );
                    }
                } elseif ( $existing ) {
                    $wpdb->delete( $table, array( 'id' => $existing ) );
                }
            }
            echo '<div class="notice notice-success is-dismissible"><p>✓ Advisors updated for this client.</p></div>';
        }
    }

    $clients  = get_users( array( 'role' => 'six_customer', 'number' => 100 ) );
    $advisors = get_users( array( 'role__in' => array( 'six_advisor', 'administrator' ) ) );
    ?>
    <div class="wrap">
        <h1>All Portal Clients (<?php echo count( $clients ); ?>)</h1>
        <p style="color:#666;max-width:760px">Each client can have a <strong>General</strong> advisor (main point of contact), an <strong>All Services</strong> advisor (one person handling everything), and/or a dedicated advisor per service — <strong>Google Ads</strong>, <strong>SEO</strong>, <strong>SMM</strong>. Use <strong>Manage Advisors</strong> to change or add any of them.</p>
        <?php if ( empty( $clients ) ) : ?>
            <p>No customers yet. <a href="<?php echo admin_url('user-new.php'); ?>">Add a user</a> with role "Portal Customer".</p>
        <?php else : ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Advisors</th>
                    <th>Health</th>
                    <th>Google Ads ID</th>
                    <th>Last Sync</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php
            foreach ( $clients as $client ) :
                $health    = class_exists( 'Six_Health_Score' ) ? Six_Health_Score::calculate( $client->ID ) : '—';
                $color     = is_numeric($health) ? ( $health >= 75 ? '#27ae60' : ( $health >= 50 ? '#f39c12' : '#e74c3c' ) ) : '#999';
                $gads_id   = get_user_meta( $client->ID, 'six_gads_customer_id', true );
                $last_sync = get_user_meta( $client->ID, 'six_gads_last_sync', true );

                $client_advisors = six_get_client_advisors( $client->ID ); // raw, per role — feeds the modal's selects
                // role_key => advisor_id (0 = unassigned), for the modal's selects.
                $role_map = array_fill_keys( array_keys( $roles ), 0 );
                foreach ( $client_advisors as $ca ) { $role_map[ $ca['role'] ] = $ca['advisor_id']; }
                // Grouped by person for display, so one advisor covering every
                // role (via "All Services" or by holding each role) shows once.
                $client_advisors_grouped = six_get_client_advisors_grouped( $client->ID );
            ?>
            <tr>
                <td><strong><?php echo esc_html( $client->display_name ); ?></strong></td>
                <td><?php echo esc_html( $client->user_email ); ?></td>
                <td>
                    <?php if ( empty( $client_advisors_grouped ) ) : ?>
                        <span style="color:#999">— None assigned —</span>
                    <?php else : foreach ( $client_advisors_grouped as $ca ) : ?>
                        <span class="button button-small" style="pointer-events:none;margin:0 4px 4px 0" title="<?php echo esc_attr( $ca['role_label'] ); ?>">
                            <?php echo esc_html( $ca['name'] ); ?> <em style="opacity:.65">(<?php echo esc_html( $ca['role_label'] ); ?>)</em>
                        </span>
                    <?php endforeach; endif; ?>
                </td>
                <td><span style="color:<?php echo $color ?>;font-weight:700"><?php echo is_numeric($health) ? $health.'%' : $health; ?></span></td>
                <td>
                    <?php if ( $gads_id ) : ?>
                        <code><?php echo esc_html( $gads_id ); ?></code>
                    <?php else : ?>
                        <span style="color:#999">Not set</span>
                    <?php endif; ?>
                </td>
                <td><?php echo $last_sync ? esc_html( date( 'M j g:i A', strtotime( $last_sync ) ) ) : '<em>Never</em>'; ?></td>
                <td style="display:flex;gap:6px;flex-wrap:wrap">
                    <button type="button" class="button button-small six-manage-advisors-btn"
                        data-client-id="<?php echo intval( $client->ID ); ?>"
                        data-client-name="<?php echo esc_attr( $client->display_name ); ?>"
                        data-roles="<?php echo esc_attr( wp_json_encode( $role_map ) ); ?>"
                        data-nonce="<?php echo esc_attr( wp_create_nonce( 'six_save_advisors_' . $client->ID ) ); ?>"
                    >Manage Advisors</button>
                    <a href="<?php echo admin_url( 'user-edit.php?user_id=' . $client->ID ); ?>" class="button button-small">Edit Credentials</a>
                    <?php if ( $gads_id ) : ?>
                    <a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=six-portal-clients&sync_gads=' . $client->ID ), 'six_sync_gads_' . $client->ID ); ?>" class="button button-small">Sync Ads</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- ── Manage Advisors modal — one shared modal, repopulated per client ── -->
    <div id="six-advisors-modal" style="display:none;position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,.5)">
        <div style="background:#fff;border-radius:8px;max-width:480px;margin:8vh auto;padding:24px;position:relative;box-shadow:0 20px 60px rgba(0,0,0,.3)">
            <button type="button" id="six-advisors-modal-close" style="position:absolute;top:14px;right:14px;background:none;border:0;font-size:20px;cursor:pointer;line-height:1;color:#666">&times;</button>
            <h2 style="margin-top:0">Manage Advisors — <span id="six-advisors-modal-client"></span></h2>
            <p style="color:#666;font-size:13px">Set one advisor per role, or "— Unassigned —" to remove it. Pick "All Services" instead of filling in each service separately when one advisor handles everything. Save applies every role at once.</p>
            <form method="post">
                <?php wp_nonce_field( 'six_save_advisors_PLACEHOLDER', 'six_advisors_nonce_field', false ); ?>
                <input type="hidden" name="six_save_advisors" value="1">
                <input type="hidden" name="client_id" id="six-advisors-client-id" value="">
                <table class="form-table" style="margin:0">
                    <?php foreach ( $roles as $rkey => $rlabel ) :
                        $field = 'role_' . ( $rkey === '' ? 'general' : sanitize_key( $rkey ) ); ?>
                    <tr>
                        <th style="width:110px"><?php echo esc_html( $rlabel ); ?></th>
                        <td>
                            <select name="<?php echo esc_attr( $field ); ?>" class="six-advisor-role-select" data-role="<?php echo esc_attr( $rkey ); ?>" style="width:100%">
                                <option value="0">— Unassigned —</option>
                                <?php foreach ( $advisors as $a ) : ?>
                                <option value="<?php echo intval( $a->ID ); ?>"><?php echo esc_html( $a->display_name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <p style="margin-top:16px;text-align:right">
                    <button type="button" class="button" id="six-advisors-modal-cancel">Cancel</button>
                    <?php submit_button( 'Save Changes', 'primary', '', false ); ?>
                </p>
            </form>
        </div>
    </div>
    <script>
    (function(){
        var modal   = document.getElementById('six-advisors-modal');
        var closeEls = [document.getElementById('six-advisors-modal-close'), document.getElementById('six-advisors-modal-cancel')];
        var clientIdField = document.getElementById('six-advisors-client-id');
        var clientNameEl  = document.getElementById('six-advisors-modal-client');
        var nonceField    = document.getElementById('six_advisors_nonce_field');

        function open(btn){
            var roles = {};
            try { roles = JSON.parse(btn.getAttribute('data-roles') || '{}'); } catch(e){}
            clientIdField.value = btn.getAttribute('data-client-id');
            clientNameEl.textContent = btn.getAttribute('data-client-name') || '';
            nonceField.value = btn.getAttribute('data-nonce') || '';
            document.querySelectorAll('.six-advisor-role-select').forEach(function(sel){
                var role = sel.getAttribute('data-role');
                sel.value = String(roles[role] || 0);
            });
            modal.style.display = 'block';
        }
        function close(){ modal.style.display = 'none'; }

        document.querySelectorAll('.six-manage-advisors-btn').forEach(function(btn){
            btn.addEventListener('click', function(){ open(btn); });
        });
        closeEls.forEach(function(el){ if(el) el.addEventListener('click', close); });
        modal.addEventListener('click', function(e){ if(e.target === modal) close(); });
        document.addEventListener('keydown', function(e){ if(e.key === 'Escape' && modal.style.display === 'block') close(); });
    })();
    </script>
    <?php
}

// ─────────────────────────────────────────────────────────────────────────────
// Lead Pipeline admin page
// ─────────────────────────────────────────────────────────────────────────────
function six_admin_leads() {
    global $wpdb;
    $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}six_checkout_progress'" );
    if ( ! $table_exists ) {
        echo '<div class="wrap"><h1>Lead Pipeline</h1><p>Database tables not installed yet. <a href="' . admin_url('admin.php?page=six-portal&six_install=1') . '">Click here to install</a>.</p></div>';
        return;
    }

    $leads = $wpdb->get_results(
        "SELECT cp.*, u.display_name, u.user_email
         FROM {$wpdb->prefix}six_checkout_progress cp
         LEFT JOIN {$wpdb->prefix}users u ON cp.user_id = u.ID
         ORDER BY cp.score DESC"
    );
    ?>
    <div class="wrap">
        <h1>Lead Pipeline — Checkout Progress Scores</h1>
        <p>
            <span style="background:#fff5f5;border:1px solid #ffcccc;padding:3px 10px;border-radius:4px;font-size:12px;margin-right:8px"> Hot: 70–100</span>
            <span style="background:#fffbf0;border:1px solid #ffd680;padding:3px 10px;border-radius:4px;font-size:12px;margin-right:8px"> Warm: 40–69</span>
            <span style="background:#f0f8ff;border:1px solid #b0d4f1;padding:3px 10px;border-radius:4px;font-size:12px"> Cold: 0–39</span>
        </p>
        <?php if ( empty( $leads ) ) : ?>
            <p>No leads yet. Leads appear here as visitors start the checkout process.</p>
        <?php else : ?>
        <table class="widefat striped">
            <thead>
                <tr><th>Lead</th><th>Email</th><th>Business</th><th>Score</th><th>Stage</th><th>Odoo ID</th><th>Updated</th></tr>
            </thead>
            <tbody>
            <?php foreach ( $leads as $lead ) :
                $row_style = $lead->score >= 70 ? 'background:#fff5f5' : ( $lead->score >= 40 ? 'background:#fffbf0' : '' );
                $score_color = $lead->score >= 70 ? '#e74c3c' : ( $lead->score >= 40 ? '#f39c12' : '#3498db' );
            ?>
            <tr style="<?php echo $row_style; ?>">
                <td><strong><?php echo esc_html( $lead->display_name ); ?></strong></td>
                <td><?php echo esc_html( $lead->user_email ); ?></td>
                <td><?php echo esc_html( $lead->business_name ?: '—' ); ?></td>
                <td><span style="font-weight:700;font-size:16px;color:<?php echo $score_color; ?>"><?php echo intval( $lead->score ); ?></span></td>
                <td><?php echo esc_html( ucwords( str_replace( '_', ' ', $lead->step ) ) ); ?></td>
                <td><?php echo esc_html( $lead->odoo_lead_id ?: '—' ); ?></td>
                <td><?php echo esc_html( date( 'M j g:i A', strtotime( $lead->updated_at ) ) ); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php
}

// ─────────────────────────────────────────────────────────────────────────────
// Install trigger via admin URL (safe, admin-only)
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'admin_init', function() {
    if ( isset( $_GET['six_install'] ) && current_user_can( 'manage_options' ) ) {
        six_create_tables();
        wp_redirect( admin_url( 'admin.php?page=six-portal&installed=1' ) );
        exit;
    }
    if ( isset( $_GET['installed'] ) && current_user_can( 'manage_options' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>✓ 6ix Portal database tables installed successfully.</p></div>';
        });
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// GOOGLE CALENDAR OAUTH CALLBACK — intercept /advisor-portal/gcal/ early
// This fires before WordPress serves any page, catches the Google redirect,
// processes the token exchange, then sends the advisor to the calendar tab.
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'template_redirect', 'six_handle_gcal_oauth_callback', 0 );
function six_handle_gcal_oauth_callback() {
    // Detect /advisor-portal/gcal/ by raw request URI
    $request = trim( parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
    // Strip subfolder prefix (e.g. 6ix-redesign)
    $home_path = trim( parse_url( home_url(), PHP_URL_PATH ), '/' );
    if ( $home_path ) {
        $request = ltrim( substr( $request, strlen( $home_path ) ), '/' );
    }

    // Only handle /advisor-portal/gcal/
    if ( $request !== 'advisor-portal/gcal' && $request !== 'advisor-portal/gcal/' ) return;

    // Must have a code from Google
    if ( empty( $_GET['code'] ) ) {
        wp_redirect( home_url( '/advisor-portal/?tab=calendar&gcal_error=no_code' ) );
        exit;
    }

    $code      = sanitize_text_field( $_GET['code'] );
    $state_raw = sanitize_text_field( $_GET['state'] ?? '' );
    $state     = $state_raw ? json_decode( base64_decode( $state_raw ), true ) : array();

    $advisor_id  = intval( $state['advisor_id'] ?? 0 );
    $state_nonce = $state['nonce'] ?? '';

    // Require a logged-in advisor — if they're not logged in, redirect to login
    if ( ! $advisor_id || ! get_userdata( $advisor_id ) ) {
        wp_redirect( home_url( '/advisor-portal/?tab=calendar&gcal_error=invalid_state' ) );
        exit;
    }

    // Verify CSRF nonce
    if ( ! wp_verify_nonce( $state_nonce, 'six_gcal_' . $advisor_id ) ) {
        wp_redirect( home_url( '/advisor-portal/?tab=calendar&gcal_error=csrf' ) );
        exit;
    }

    // Exchange code for tokens
    $client_id     = get_option( 'six_google_client_id' );
    $client_secret = get_option( 'six_google_client_secret' );
    $redirect_uri  = home_url( '/advisor-portal/gcal/' ); // must match exactly

    $resp = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
        'timeout' => 15,
        'body'    => array(
            'code'          => $code,
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'redirect_uri'  => $redirect_uri,
            'grant_type'    => 'authorization_code',
        ),
    ) );

    if ( is_wp_error( $resp ) ) {
        error_log( '6ix GCal token exchange network error: ' . $resp->get_error_message() );
        wp_redirect( home_url( '/advisor-portal/?tab=calendar&gcal_error=network' ) );
        exit;
    }

    $data = json_decode( wp_remote_retrieve_body( $resp ), true );

    if ( empty( $data['refresh_token'] ) ) {
        error_log( '6ix GCal token exchange failed: ' . wp_json_encode( $data ) );
        $err = urlencode( $data['error_description'] ?? $data['error'] ?? 'no_refresh_token' );
        wp_redirect( home_url( '/advisor-portal/?tab=calendar&gcal_error=' . $err ) );
        exit;
    }

    // Save tokens
    update_user_meta( $advisor_id, 'six_gcal_refresh_token', $data['refresh_token'] );
    update_user_meta( $advisor_id, 'six_gcal_access_token',  $data['access_token'] );
    update_user_meta( $advisor_id, 'six_gcal_token_expires', time() + intval( $data['expires_in'] ?? 3600 ) );

    // Fetch and store Google email
    $uinfo = wp_remote_get( 'https://www.googleapis.com/oauth2/v2/userinfo', array(
        'timeout' => 10,
        'headers' => array( 'Authorization' => 'Bearer ' . $data['access_token'] ),
    ) );
    if ( ! is_wp_error( $uinfo ) ) {
        $ui = json_decode( wp_remote_retrieve_body( $uinfo ), true );
        if ( ! empty( $ui['email'] ) ) {
            update_user_meta( $advisor_id, 'six_gcal_email', $ui['email'] );
        }
    }

    // All done — redirect to calendar tab
    wp_redirect( home_url( '/advisor-portal/?tab=calendar&gcal_success=1' ) );
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// TEST AI — visit /wp-admin/?six_test_ai=1 to diagnose API issues
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'admin_init', 'six_maybe_test_ai' );
function six_maybe_test_ai() {
    if ( empty($_GET['six_test_ai']) || ! current_user_can('manage_options') ) return;

    echo '<div style="font-family:monospace;padding:30px;background:#0d1117;color:#f0f4f8;min-height:100vh">';
    echo '<h2 style="color:#FF6699;margin-bottom:20px">6ix AI Diagnostic</h2>';

    $key = get_option('six_anthropic_api_key','');

    if ( ! $key ) {
        echo '<p style="color:#FF6B6B"> No API key found in database (option: six_anthropic_api_key)</p>';
        echo '<p>Go to <a href="'.admin_url('admin.php?page=six-portal-settings').'" style="color:#6ACAFD">Integration Settings</a>, enter your key, and save.</p>';
        echo '</div>'; exit;
    }

    echo '<p>Key stored: <code>'.esc_html(substr($key,0,12)).'...'.esc_html(substr($key,-4)).'</code> ('.strlen($key).' chars)</p>';
    echo '<p>Testing Anthropic API…</p>';

    $resp = wp_remote_post('https://api.anthropic.com/v1/messages', array(
        'timeout' => 30,
        'headers' => array(
            'Content-Type'      => 'application/json',
            'x-api-key'         => $key,
            'anthropic-version' => '2023-06-01',
        ),
        'body' => wp_json_encode(array(
            'model'      => 'claude-haiku-4-5-20251001',
            'max_tokens' => 30,
            'messages'   => array(array('role'=>'user','content'=>'Reply with: AI working'))
        )),
    ));

    if ( is_wp_error($resp) ) {
        echo '<p style="color:#FF6B6B"> Network error: '.esc_html($resp->get_error_message()).'</p>';
        echo '</div>'; exit;
    }

    $code = wp_remote_retrieve_response_code($resp);
    $body = json_decode(wp_remote_retrieve_body($resp), true);

    echo '<p>HTTP Response: <strong style="color:'.($code===200?'#56D364':'#FF6B6B').'">'.$code.'</strong></p>';
    echo '<pre style="background:#1a1a2e;padding:14px;border-radius:8px;font-size:12px;overflow:auto;max-height:300px">'.esc_html(wp_json_encode($body, JSON_PRETTY_PRINT)).'</pre>';

    if ( $code === 200 ) {
        echo '<p style="color:#56D364;font-size:16px"> API is working correctly!</p>';
    } elseif ( $code === 401 ) {
        echo '<p style="color:#FF6B6B"> 401 — API key invalid. Re-copy it from console.anthropic.com → API Keys and paste it fresh into settings.</p>';
    } elseif ( $code === 429 ) {
        echo '<p style="color:#E3B341"> 429 — Key works but rate limited. Wait 60 seconds and try the portal again.</p>';
    } else {
        echo '<p style="color:#FF6B6B"> Error '.$code.' — see raw response above.</p>';
    }

    echo '<p style="margin-top:20px"><a href="'.admin_url('admin.php?page=six-portal-settings').'" style="color:#6ACAFD">← Back to Settings</a></p>';
    echo '</div>'; exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// DataForSEO — live connection test (admin only, from the Integrations page)
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'wp_ajax_six_test_dataforseo', 'six_ajax_test_dataforseo' );
function six_ajax_test_dataforseo() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Not allowed.' ) );
    }
    check_ajax_referer( 'six_dfs_test', 'nonce' );
    if ( ! class_exists( 'Six_EstimateEngine' ) || ! method_exists( 'Six_EstimateEngine', 'test_dataforseo' ) ) {
        wp_send_json_error( array( 'message' => 'Estimate engine not loaded.' ) );
    }
    $res = Six_EstimateEngine::test_dataforseo();
    if ( ! empty( $res['ok'] ) ) {
        wp_send_json_success( $res );
    }
    wp_send_json_error( $res );
}
