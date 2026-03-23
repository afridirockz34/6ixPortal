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
                array( 'Active Services',   intval( $svc_count ),    '#83C5ED' ),
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
        'six_gads_developer_token', 'six_gads_manager_id',
        'six_gads_client_id', 'six_gads_client_secret', 'six_gads_refresh_token',
    );

    if ( isset( $_POST['six_save_settings'] ) && check_admin_referer( 'six_settings' ) ) {
        $mask = str_repeat( '•', 12 );
        foreach ( $all_fields as $f ) {
            if ( isset( $_POST[$f] ) && $_POST[$f] !== $mask ) {
                update_option( $f, sanitize_text_field( $_POST[$f] ) );
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
        ? '<span style="background:#d4edda;color:#155724;padding:3px 10px;border-radius:4px;font-size:12px;font-weight:600">✅ Connected</span>'
        : '<span style="background:#f8d7da;color:#721c24;padding:3px 10px;border-radius:4px;font-size:12px;font-weight:600">❌ Not connected</span>';

    // Stage IDs status
    $stages_set = get_option('six_odoo_stage_new') && get_option('six_odoo_stage_submitted');
    ?>
    <div class="wrap">
        <h1>6ix Portal — Integration Settings</h1>

        <form method="post">
            <?php wp_nonce_field( 'six_settings' ); ?>

            <!-- ═══ ODOO ═══════════════════════════════════════════════ -->
            <h2 style="border-bottom:3px solid #FF6699;padding-bottom:8px;margin-top:30px">
                🔗 Odoo CRM <?php echo $odoo_badge; ?>
            </h2>

            <?php if ( !$odoo_ok && get_option('six_odoo_url') ) : ?>
            <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:12px 16px;margin-bottom:16px;font-size:13px">
                <strong>⚠ Connection failed.</strong> Common causes:
                <ul style="margin:6px 0 0 18px;line-height:1.8">
                    <li>URL must have <strong>no trailing slash</strong> — e.g. <code>https://yourcompany.odoo.com</code></li>
                    <li>API Key must be generated in Odoo → Settings → Technical → <strong>API Keys</strong> (not your login password)</li>
                    <li>Username must be your <strong>Odoo login email</strong></li>
                    <li>On Odoo.com SaaS: make sure Technical menu is enabled in Settings</li>
                </ul>
            </div>
            <?php endif; ?>

            <table class="form-table">
                <tr>
                    <th>Odoo Instance URL</th>
                    <td>
                        <input name="six_odoo_url" value="<?php echo $s('six_odoo_url'); ?>" class="regular-text" placeholder="https://yourcompany.odoo.com">
                        <p class="description">No trailing slash. Example: <code>https://acme.odoo.com</code></p>
                    </td>
                </tr>
                <tr>
                    <th>Database Name</th>
                    <td>
                        <input name="six_odoo_db" value="<?php echo $s('six_odoo_db'); ?>" class="regular-text" placeholder="acme">
                        <p class="description">Found in Odoo → Settings → Technical → Database. On Odoo.com this is usually your subdomain.</p>
                    </td>
                </tr>
                <tr>
                    <th>Login Email</th>
                    <td>
                        <input name="six_odoo_username" value="<?php echo $s('six_odoo_username'); ?>" class="regular-text" placeholder="admin@yourcompany.com">
                    </td>
                </tr>
                <tr>
                    <th>API Key</th>
                    <td>
                        <input name="six_odoo_api_key" type="password" value="<?php echo $s('six_odoo_api_key',true); ?>" class="regular-text" placeholder="Paste your Odoo API key here">
                        <p class="description">
                            Generate in Odoo: <strong>Settings → Technical → API Keys → New</strong><br>
                            ⚠ This is NOT your login password — it's a separate API key.
                        </p>
                    </td>
                </tr>
            </table>

            <?php if ( $odoo_ok ) : ?>
            <div style="background:#d4edda;border:1px solid #c3e6cb;border-radius:6px;padding:12px 16px;margin:10px 0 20px;font-size:13px">
                ✅ <strong>Connected!</strong>
                <?php if ( !$stages_set ) : ?>
                    Now run the one-time setup to create custom fields and pipeline stages:
                    <a href="<?php echo admin_url('?six_odoo_setup=1'); ?>" class="button button-primary" style="margin-left:10px">Run Odoo Setup →</a>
                <?php else : ?>
                    All pipeline stages are configured. Odoo integration is fully active.
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Stage IDs — set automatically by setup, but editable manually -->
            <h3 style="margin-top:20px">CRM Pipeline Stages
                <?php if ($stages_set): ?>
                    <span style="background:#d4edda;color:#155724;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600">✅ Configured</span>
                <?php else: ?>
                    <span style="background:#f8d7da;color:#721c24;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600">❌ Not set — run setup</span>
                <?php endif; ?>
            </h3>
            <p style="color:#666;font-size:13px;margin-bottom:10px">
                These are auto-populated when you run the Odoo Setup. You can also enter them manually if your stages already exist in Odoo.
                Find the ID by opening a stage in Odoo CRM → Configuration → Stages — the ID is in the URL (<code>?id=5</code>).
            </p>
            <table class="form-table">
                <tr>
                    <th>New Lead — Stage ID</th>
                    <td>
                        <input name="six_odoo_stage_new" value="<?php echo $s('six_odoo_stage_new'); ?>" class="small-text" placeholder="e.g. 1">
                        <span style="color:#666;font-size:12px;margin-left:8px">Current: <?php echo get_option('six_odoo_stage_new','<em>not set</em>'); ?></span>
                    </td>
                </tr>
                <tr>
                    <th>Onboarding In Progress — Stage ID</th>
                    <td>
                        <input name="six_odoo_stage_inprogress" value="<?php echo $s('six_odoo_stage_inprogress'); ?>" class="small-text" placeholder="e.g. 2">
                        <span style="color:#666;font-size:12px;margin-left:8px">Current: <?php echo get_option('six_odoo_stage_inprogress','<em>not set</em>'); ?></span>
                    </td>
                </tr>
                <tr>
                    <th>Onboarding Submitted — Stage ID</th>
                    <td>
                        <input name="six_odoo_stage_submitted" value="<?php echo $s('six_odoo_stage_submitted'); ?>" class="small-text" placeholder="e.g. 3">
                        <span style="color:#666;font-size:12px;margin-left:8px">Current: <?php echo get_option('six_odoo_stage_submitted','<em>not set</em>'); ?></span>
                    </td>
                </tr>
                <tr>
                    <th>Active Client — Stage ID</th>
                    <td>
                        <input name="six_odoo_stage_active" value="<?php echo $s('six_odoo_stage_active'); ?>" class="small-text" placeholder="e.g. 4">
                        <span style="color:#666;font-size:12px;margin-left:8px">Current: <?php echo get_option('six_odoo_stage_active','<em>not set</em>'); ?></span>
                    </td>
                </tr>
                <tr>
                    <th>Tasks Project ID</th>
                    <td>
                        <input name="six_odoo_project_id" value="<?php echo $s('six_odoo_project_id'); ?>" class="small-text" placeholder="e.g. 1">
                        <span style="color:#666;font-size:12px;margin-left:8px">Current: <?php echo get_option('six_odoo_project_id','<em>not set</em>'); ?></span>
                        <p class="description">Auto-set by setup. Or find it in Odoo → Project → your project → URL ?id=X</p>
                    </td>
                </tr>
            </table>

            <!-- ═══ STRIPE ═════════════════════════════════════════════ -->
            <h2 style="border-bottom:3px solid #83C5ED;padding-bottom:8px;margin-top:30px">💳 Stripe</h2>
            <table class="form-table">
                <tr>
                    <th>Publishable Key</th>
                    <td><input name="six_stripe_publishable_key" value="<?php echo $s('six_stripe_publishable_key'); ?>" class="regular-text" placeholder="pk_live_..."></td>
                </tr>
                <tr>
                    <th>Secret Key</th>
                    <td>
                        <input name="six_stripe_secret_key" type="password" value="<?php echo $s('six_stripe_secret_key',true); ?>" class="regular-text" placeholder="sk_live_...">
                        <p class="description">Never share this. Used server-side only.</p>
                    </td>
                </tr>
                <tr>
                    <th>Webhook Secret</th>
                    <td>
                        <input name="six_stripe_webhook_secret" type="password" value="<?php echo $s('six_stripe_webhook_secret',true); ?>" class="regular-text" placeholder="whsec_...">
                        <p class="description">Webhook URL: <code><?php echo esc_html( home_url('/wp-json/six/v1/stripe-webhook') ); ?></code></p>
                    </td>
                </tr>
            </table>

            <!-- ═══ GOOGLE ADS (MCC) ════════════════════════════════════ -->
            <h2 style="border-bottom:3px solid #4285F4;padding-bottom:8px;margin-top:30px">📊 Google Ads — Manager Account (MCC)</h2>
            <p style="color:#666;font-size:13px;margin-bottom:10px">
                One-time setup. After this, advisors only need to enter a Customer ID per client.
                You already have a refresh token — paste it here.
            </p>
            <table class="form-table">
                <tr>
                    <th>Developer Token</th>
                    <td>
                        <input name="six_gads_developer_token" type="password" value="<?php echo $s('six_gads_developer_token',true); ?>" class="regular-text">
                        <p class="description">From <a href="https://ads.google.com/aw/apicenter" target="_blank">Google Ads API Center</a></p>
                    </td>
                </tr>
                <tr>
                    <th>Manager Account ID (MCC)</th>
                    <td>
                        <input name="six_gads_manager_id" value="<?php echo $s('six_gads_manager_id'); ?>" class="regular-text" placeholder="123-456-7890">
                    </td>
                </tr>
                <tr>
                    <th>OAuth Client ID</th>
                    <td>
                        <input name="six_gads_client_id" value="<?php echo $s('six_gads_client_id'); ?>" class="regular-text" placeholder="xxxxxx.apps.googleusercontent.com">
                        <p class="description">From <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a> → OAuth 2.0 Client IDs</p>
                    </td>
                </tr>
                <tr>
                    <th>OAuth Client Secret</th>
                    <td><input name="six_gads_client_secret" type="password" value="<?php echo $s('six_gads_client_secret',true); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th>MCC Refresh Token</th>
                    <td>
                        <input name="six_gads_refresh_token" type="password" value="<?php echo $s('six_gads_refresh_token',true); ?>" class="regular-text">
                        <p class="description">You already generated this ✓ — paste it here. Does not expire unless revoked.</p>
                    </td>
                </tr>
            </table>

            <!-- ═══ GOOGLE CALENDAR ══════════════════════════════════════ -->
            <h2 style="border-bottom:3px solid #3C6478;padding-bottom:8px;margin-top:30px">📅 Google Calendar (Agency OAuth)</h2>
            <table class="form-table">
                <tr>
                    <th>OAuth Client ID</th>
                    <td><input name="six_google_client_id" value="<?php echo $s('six_google_client_id'); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th>OAuth Client Secret</th>
                    <td><input name="six_google_client_secret" type="password" value="<?php echo $s('six_google_client_secret',true); ?>" class="regular-text"></td>
                </tr>
            </table>

            <?php submit_button( 'Save All Settings', 'primary large', 'six_save_settings' ); ?>
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

    // Handle assignment form submission
    if ( isset( $_POST['six_assign'] ) && check_admin_referer( 'six_assign' ) ) {
        $client_id  = intval( $_POST['client_id'] );
        $advisor_id = intval( $_POST['advisor_id'] );
        if ( $client_id && $advisor_id ) {
            // Check table exists
            $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table'" );
            if ( ! $table_exists ) {
                six_create_tables();
            }
            // Upsert
            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM $table WHERE client_id = %d", $client_id
            ) );
            if ( $existing ) {
                $wpdb->update( $table, array( 'advisor_id' => $advisor_id ), array( 'client_id' => $client_id ) );
            } else {
                $wpdb->insert( $table, array( 'client_id' => $client_id, 'advisor_id' => $advisor_id ) );
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
        "SELECT a.id, a.client_id, a.advisor_id, a.assigned_at,
                uc.display_name AS client_name, uc.user_email AS client_email,
                ua.display_name AS advisor_name
         FROM {$table} a
         INNER JOIN {$wpdb->users} uc ON a.client_id  = uc.ID
         INNER JOIN {$wpdb->users} ua ON a.advisor_id = ua.ID
         ORDER BY a.assigned_at DESC"
    );
    ?>
    <div class="wrap">
        <h1>Assign Advisors to Clients</h1>

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
    // Handle manual Google Ads sync trigger
    if ( isset( $_GET['sync_gads'] ) && check_admin_referer( 'six_sync_gads_' . intval( $_GET['sync_gads'] ) ) ) {
        $client_id = intval( $_GET['sync_gads'] );
        Six_Google_Ads::get_campaign_metrics_for_client( $client_id );
        update_user_meta( $client_id, 'six_gads_last_sync', current_time( 'mysql' ) );
        echo '<div class="notice notice-success is-dismissible"><p>✓ Google Ads synced.</p></div>';
    }

    $clients = get_users( array( 'role' => 'six_customer', 'number' => 100 ) );
    ?>
    <div class="wrap">
        <h1>All Portal Clients (<?php echo count( $clients ); ?>)</h1>
        <?php if ( empty( $clients ) ) : ?>
            <p>No customers yet. <a href="<?php echo admin_url('user-new.php'); ?>">Add a user</a> with role "Portal Customer".</p>
        <?php else : ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Advisor</th>
                    <th>Health</th>
                    <th>Google Ads ID</th>
                    <th>Last Sync</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php
            global $wpdb;
            foreach ( $clients as $client ) :
                $health     = class_exists( 'Six_Health_Score' ) ? Six_Health_Score::calculate( $client->ID ) : '—';
                $color      = is_numeric($health) ? ( $health >= 75 ? '#27ae60' : ( $health >= 50 ? '#f39c12' : '#e74c3c' ) ) : '#999';
                $gads_id    = get_user_meta( $client->ID, 'six_gads_customer_id', true );
                $last_sync  = get_user_meta( $client->ID, 'six_gads_last_sync', true );
                $advisor_id = $wpdb->get_var( $wpdb->prepare(
                    "SELECT advisor_id FROM {$wpdb->prefix}six_assignments WHERE client_id = %d",
                    $client->ID
                ) );
                $advisor_name = $advisor_id ? get_userdata( $advisor_id )->display_name : '—';
            ?>
            <tr>
                <td><strong><?php echo esc_html( $client->display_name ); ?></strong></td>
                <td><?php echo esc_html( $client->user_email ); ?></td>
                <td><?php echo esc_html( $advisor_name ); ?></td>
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
            <span style="background:#fff5f5;border:1px solid #ffcccc;padding:3px 10px;border-radius:4px;font-size:12px;margin-right:8px">🔥 Hot: 70–100</span>
            <span style="background:#fffbf0;border:1px solid #ffd680;padding:3px 10px;border-radius:4px;font-size:12px;margin-right:8px">⚡ Warm: 40–69</span>
            <span style="background:#f0f8ff;border:1px solid #b0d4f1;padding:3px 10px;border-radius:4px;font-size:12px">❄ Cold: 0–39</span>
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
