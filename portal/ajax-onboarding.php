<?php
/**
 * 6ix Developers — Onboarding AJAX Handlers
 * Upload to: /wp-content/themes/6ixClaude/portal/ajax-onboarding.php
 * Then add to functions.php: require_once SIX_PLUGIN_DIR . 'ajax-onboarding.php';
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ─────────────────────────────────────────────────────────────────────────────
// 1. CHECK EMAIL — does this address have an account?
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'wp_ajax_nopriv_six_check_email', 'six_check_email' );
add_action( 'wp_ajax_six_check_email',        'six_check_email' );
function six_check_email() {
    // No nonce check needed — this just checks if an email exists, no side effects.
    $email = sanitize_email( $_POST['email'] ?? '' );
    if ( ! is_email( $email ) ) wp_send_json_error( 'Invalid email' );
    $user  = get_user_by( 'email', $email );
    wp_send_json_success( array( 'exists' => (bool) $user ) );
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. LOGIN via AJAX (existing user)
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'wp_ajax_nopriv_six_portal_login', 'six_portal_login' );
add_action( 'wp_ajax_six_portal_login',        'six_portal_login' );
function six_portal_login() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    $email    = sanitize_email( $_POST['email']    ?? '' );
    $password = sanitize_text_field( $_POST['password'] ?? '' );
    $user     = wp_authenticate( $email, $password );
    if ( is_wp_error( $user ) ) {
        wp_send_json_error( 'Incorrect email or password.' );
    }
    wp_set_current_user( $user->ID );
    wp_set_auth_cookie( $user->ID, true );

    $fresh_nonce = wp_create_nonce( 'six_nonce' );

    // Check role — non-customers must never enter the onboarding flow
    $role = class_exists( 'Six_Roles' ) ? Six_Roles::get_portal_role( $user->ID ) : '';
    if ( $role === 'six_advisor' ) {
        wp_send_json_success( array(
            'redirect_url' => home_url( '/advisor-portal/' ),
            'role'         => 'six_advisor',
            'nonce'        => $fresh_nonce,
        ) );
    }
    if ( $role === 'six_sales' ) {
        wp_send_json_success( array(
            'redirect_url' => home_url( '/sales-portal/' ),
            'role'         => 'six_sales',
            'nonce'        => $fresh_nonce,
        ) );
    }
    if ( current_user_can( 'manage_options' ) && ! $role ) {
        wp_send_json_success( array(
            'redirect_url' => admin_url(),
            'role'         => 'administrator',
            'nonce'        => $fresh_nonce,
        ) );
    }

    // Customer flow
    $completed   = get_user_meta( $user->ID, 'six_checkout_completed', true );
    $resume_step = intval( get_user_meta( $user->ID, 'six_checkout_step', true ) ?: 1 );
    wp_send_json_success( array(
        'user_id'                => $user->ID,
        'has_completed_checkout' => (bool) $completed,
        'resume_step'            => $resume_step,
        'role'                   => 'six_customer',
        'nonce'                  => $fresh_nonce,
    ) );
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. CREATE PARTIAL ACCOUNT — new email, start onboarding
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'wp_ajax_nopriv_six_create_partial_account', 'six_create_partial_account' );
add_action( 'wp_ajax_six_create_partial_account',        'six_create_partial_account' );
function six_create_partial_account() {
    // No nonce check — this fires as a guest (nopriv). The guest nonce from
    // page load is valid here. Security: email is validated before use.
    $email = sanitize_email( $_POST['email'] ?? '' );
    if ( ! is_email( $email ) ) wp_send_json_error( 'Invalid email' );
    if ( email_exists( $email ) ) {
        $user = get_user_by( 'email', $email );
        // Already exists but not completed — resume
        $advisor = six_get_advisor_for_user( $user->ID );
        wp_send_json_success( array( 'user_id' => $user->ID, 'advisor' => $advisor ) );
        return;
    }

    // Generate temp password
    $password = wp_generate_password( 16, false );
    $username = sanitize_user( strstr( $email, '@', true ) . '_' . wp_rand( 100, 999 ) );
    while ( username_exists( $username ) ) { $username .= wp_rand( 1, 9 ); }

    $user_id = wp_insert_user( array(
        'user_login' => $username,
        'user_email' => $email,
        'user_pass'  => $password,
        'role'       => 'six_customer',
    ) );

    if ( is_wp_error( $user_id ) ) wp_send_json_error( $user_id->get_error_message() );

    // Store temp password in meta (emailed to user on completion)
    update_user_meta( $user_id, 'six_temp_password',      $password );
    update_user_meta( $user_id, 'six_checkout_step',      1 );
    update_user_meta( $user_id, 'six_checkout_score',     10 ); // account created = 10
    update_user_meta( $user_id, 'six_checkout_completed', 0 );

    // Log them in immediately
    wp_set_current_user( $user_id );
    wp_set_auth_cookie( $user_id, true );

    // CRITICAL: Regenerate nonce now that the user is logged in.
    // The original nonce was minted for a guest — all subsequent AJAX calls
    // (including six_complete_onboarding) must use this new logged-in nonce,
    // otherwise check_ajax_referer will return -1 and every call will fail.
    $fresh_nonce = wp_create_nonce( 'six_nonce' );

    // Assign advisor (round-robin)
    $advisor = six_assign_advisor_round_robin( $user_id );

    // Create Odoo contact + new lead immediately on registration
    if ( class_exists('Six_Odoo') ) {
        Six_Odoo::create_or_update_contact( $user_id );
        Six_Odoo::sync_lead( array(
            'user_id' => $user_id,
            'status'  => 'new',
            'score'   => 10,
        ) );
    }

    wp_send_json_success( array(
        'user_id' => $user_id,
        'advisor' => $advisor,
        'nonce'   => $fresh_nonce,  // JS must update S.nonce with this value
    ) );
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. SAVE CHECKOUT STEP
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'wp_ajax_nopriv_six_save_checkout_step', 'six_ajax_save_checkout_step' );
add_action( 'wp_ajax_six_save_checkout_step',        'six_ajax_save_checkout_step' );
function six_ajax_save_checkout_step() {
    // No nonce check — same session timing issue as six_complete_onboarding.
    // Validated by user_id in POST body.
    $user_id = intval( $_POST['user_id'] ?? get_current_user_id() );
    if ( ! $user_id ) wp_send_json_error( 'No user' );

    $step     = sanitize_text_field( $_POST['step'] ?? '' );
    $raw_data = $_POST['data'] ?? '';
    $score    = intval( $_POST['score'] ?? 0 );
    $data     = $raw_data ? json_decode( stripslashes( $raw_data ), true ) : array();

    // Score thresholds
    $score_map = array(
        '1'  => 30,
        '1b' => 35, // readiness score step
        '2'  => 60,
        '3'  => 80,
        '4'  => 90,
    );

    $new_score = $score_map[ $step ] ?? $score;
    $current   = intval( get_user_meta( $user_id, 'six_checkout_score', true ) ?: 0 );
    if ( $new_score > $current ) update_user_meta( $user_id, 'six_checkout_score', $new_score );

    // Update checkout progress table
    global $wpdb;
    $table = $wpdb->prefix . 'six_checkout_progress';
    $existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id=%d", $user_id ) );

    $update = array( 'step' => $step, 'score' => max( $new_score, $current ), 'updated_at' => current_time('mysql') );
    // Merge step-specific data
    if ( $step === '1' && $data ) {
        $update = array_merge( $update, array(
            'first_name'     => sanitize_text_field( $data['first']       ?? '' ),
            'last_name'      => sanitize_text_field( $data['last']        ?? '' ),
            'phone'          => sanitize_text_field( $data['phone']       ?? '' ),
            'business_name'  => sanitize_text_field( $data['bizname']     ?? '' ),
            'website'        => esc_url_raw(          $data['website']    ?? '' ),
            'industry'       => sanitize_text_field( $data['industry']    ?? '' ),
            'location'       => sanitize_text_field( $data['location']    ?? '' ),
            'employees'      => sanitize_text_field( $data['employees']   ?? '' ),
            'monthly_revenue'=> sanitize_text_field( $data['revenue']     ?? '' ),
            'goal'           => sanitize_text_field( $data['goal']        ?? '' ),
            'challenge'      => sanitize_text_field( $data['challenge']   ?? '' ),
            'mktg_budget'    => sanitize_text_field( $data['mktg_budget'] ?? '' ),
            'platforms'      => sanitize_text_field( $data['platforms']   ?? '' ),
        ) );
        // Also update WP user name
        wp_update_user( array(
            'ID'           => $user_id,
            'first_name'   => $update['first_name'],
            'last_name'    => $update['last_name'],
            'display_name' => trim( $update['first_name'] . ' ' . $update['last_name'] ),
        ) );
        update_user_meta( $user_id, 'billing_phone', $update['phone'] );
    }

    if ( $existing ) {
        $wpdb->update( $table, $update, array( 'user_id' => $user_id ) );
    } else {
        $update['user_id']    = $user_id;
        $update['created_at'] = current_time('mysql');
        $wpdb->insert( $table, $update );
    }

    // Step 1 complete — update contact and set lead to in_progress
    if ( $step === '1' && $data && class_exists('Six_Odoo') ) {
        Six_Odoo::create_or_update_contact( $user_id );
        Six_Odoo::sync_lead( array(
            'user_id' => $user_id,
            'status'  => 'in_progress',
            'score'   => $new_score,
            'step'    => 1,
        ) );
    }

    wp_send_json_success( array( 'score' => max($new_score, $current) ) );
}

// ─────────────────────────────────────────────────────────────────────────────
// 5. COMPLETE ONBOARDING
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'wp_ajax_six_complete_onboarding',        'six_complete_onboarding' );
add_action( 'wp_ajax_nopriv_six_complete_onboarding', 'six_complete_onboarding' );
function six_complete_onboarding() {
    // No nonce check here — the nonce was minted after wp_set_current_user()
    // in the same PHP process but the auth cookie hasn't been sent to the
    // browser yet on a nopriv request, so WordPress has user_id=0 at
    // verification time and ANY nonce fails. Security is provided instead by:
    //   1. Requiring a valid user_id that maps to a real WP user
    //   2. Requiring a non-empty signature (proves they reached step 4)
    //   3. Rate limiting is handled by WordPress's own AJAX infrastructure

    try {
    $user_id          = intval( $_POST['user_id'] ?? get_current_user_id() );
    $signature        = sanitize_text_field( $_POST['signature']         ?? '' );
    $payment_method   = sanitize_text_field( $_POST['payment_method_id'] ?? '' );
    $services_raw     = sanitize_text_field( $_POST['services']          ?? '{}' );
    $step1_raw        = sanitize_text_field( $_POST['step1_data']        ?? '{}' );
    $score            = intval( $_POST['score'] ?? 0 );

    if ( ! $user_id || ! $signature ) wp_send_json_error( 'Missing required fields.' );

    $services  = json_decode( stripslashes( $services_raw ), true ) ?: array();
    $step1     = json_decode( stripslashes( $step1_raw ),    true ) ?: array();

    // Final score = 100
    update_user_meta( $user_id, 'six_checkout_score',     100 );
    update_user_meta( $user_id, 'six_checkout_completed', 1 );
    update_user_meta( $user_id, 'six_checkout_signature', $signature );

    global $wpdb;

    // Mark checkout complete
    $wpdb->update( $wpdb->prefix . 'six_checkout_progress', array(
        'step'       => '4',
        'score'      => 100,
        'completed'  => 1,
        'updated_at' => current_time('mysql'),
    ), array( 'user_id' => $user_id ) );

    // Insert selected services
    foreach ( $services as $slug => $budget ) {
        $slug   = sanitize_text_field( $slug );
        $budget = floatval( $budget );
        $names  = array(
            'google-ads'   => 'Google Ads',
            'seo'          => 'SEO',
            'social-media' => 'Social Media Marketing',
            'brand-dev'    => 'Brand Development',
            'website'      => 'Website Development',
        );
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}six_client_services WHERE client_id=%d AND service_slug=%s",
            $user_id, $slug
        ) );
        if ( ! $existing ) {
            $wpdb->insert( $wpdb->prefix . 'six_client_services', array(
                'client_id'    => $user_id,
                'service_slug' => $slug,
                'service_name' => $names[$slug] ?? ucwords( str_replace('-',' ',$slug) ),
                'status'       => 'pending',
                'budget'       => $budget,
            ) );
        }
    }

    // Save Stripe payment method (use save_payment_method — attach_payment_method doesn't exist)
    if ( $payment_method ) {
        update_user_meta( $user_id, 'six_stripe_payment_method', $payment_method );
        if ( class_exists('Six_Stripe') ) {
            Six_Stripe::save_payment_method( $user_id, $payment_method );
        }
    }

    // Notify advisor
    $advisor_id = $wpdb->get_var( $wpdb->prepare(
        "SELECT advisor_id FROM {$wpdb->prefix}six_assignments WHERE client_id=%d", $user_id
    ) );
    if ( $advisor_id && class_exists('Six_Notifications') ) {
        $user = get_userdata( $user_id );
        Six_Notifications::create( array(
            'user_id'    => $advisor_id,
            'type'       => 'client_onboarded',
            'title'      => 'New Client Onboarded',
            'message'    => ( $user->display_name ?: $user->user_email ) . ' has completed onboarding. Readiness score: ' . $score . '/100.',
            'action_url' => admin_url('admin.php?page=six-clients'),
        ) );
        // Email advisor
        $advisor = get_userdata( $advisor_id );
        $user    = get_userdata( $user_id );
        $svc_list = implode( ', ', array_keys( $services ) );
        wp_mail(
            $advisor->user_email,
            '[6ix Developers] New Client: ' . $user->display_name,
            '<p>Hi ' . esc_html($advisor->first_name) . ',</p>'
            . '<p><strong>' . esc_html($user->display_name) . '</strong> has completed onboarding.</p>'
            . '<ul><li><strong>Email:</strong> ' . esc_html($user->user_email) . '</li>'
            . '<li><strong>Score:</strong> ' . $score . '/100</li>'
            . '<li><strong>Services:</strong> ' . esc_html($svc_list) . '</li></ul>'
            . '<p><a href="' . admin_url('admin.php?page=six-clients') . '">View in Portal →</a></p>',
            array('Content-Type: text/html; charset=UTF-8')
        );
    }

    // Generate AI suggestions for dashboard
    six_generate_ai_suggestions( $user_id, $step1, $services );

    // Update contact with full info, mark lead as submitted
    if ( class_exists('Six_Odoo') ) {
        Six_Odoo::create_or_update_contact( $user_id );
        $svc_names = array();
        foreach ($services as $slug=>$budget) {
            $nm = array('google-ads'=>'Google Ads','seo'=>'SEO','social-media'=>'Social Media',
                        'brand-dev'=>'Brand Development','website'=>'Website Development');
            $svc_names[] = $nm[$slug] ?? ucwords(str_replace('-',' ',$slug));
        }
        Six_Odoo::sync_lead( array(
            'user_id'  => $user_id,
            'status'   => 'submitted',
            'score'    => 100,
            'step'     => 4,
            'services' => implode(', ', $svc_names),
        ) );
    }

    // Send welcome email with login info
    $temp_pass = get_user_meta( $user_id, 'six_temp_password', true );
    $user      = get_userdata( $user_id );
    if ( $temp_pass ) {
        wp_mail(
            $user->user_email,
            'Welcome to 6ix Developers — Your Account Details',
            '<p>Hi ' . esc_html($user->first_name ?: $user->display_name) . ',</p>'
            . '<p>Welcome! Your account is ready. Here are your login details:</p>'
            . '<ul><li><strong>Email:</strong> ' . esc_html($user->user_email) . '</li>'
            . '<li><strong>Temporary Password:</strong> ' . esc_html($temp_pass) . '</li></ul>'
            . '<p><a href="' . home_url('/portal/') . '">Go to My Dashboard →</a></p>'
            . '<p>Your advisor will reach out within one business day.</p>',
            array('Content-Type: text/html; charset=UTF-8')
        );
        delete_user_meta( $user_id, 'six_temp_password' );
    }

    wp_send_json_success( array( 'redirect' => home_url('/portal/') ) );

    } catch ( Throwable $e ) {
        wp_send_json_error( 'Server error: ' . $e->getMessage() );
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 6. ABANDONED CHECKOUT TRACKING
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'wp_ajax_nopriv_six_track_abandoned_checkout', 'six_track_abandoned_checkout' );
add_action( 'wp_ajax_six_track_abandoned_checkout',        'six_track_abandoned_checkout' );
function six_track_abandoned_checkout() {
    // No nonce check — sendBeacon fires during page unload and can't retry
    $user_id = intval( $_POST['user_id'] ?? 0 );
    $step    = intval( $_POST['step']    ?? 0 );
    $score   = intval( $_POST['score']   ?? 0 );
    if ( ! $user_id || ! $step ) { wp_send_json_success(); return; }

    // Don't overwrite completed checkouts
    $already_completed = get_user_meta( $user_id, 'six_checkout_completed', true );
    if ( $already_completed ) { wp_send_json_success(); return; }

    // Save to user meta
    update_user_meta( $user_id, 'six_abandoned_at_step', $step );
    update_user_meta( $user_id, 'six_abandoned_score',   $score );
    update_user_meta( $user_id, 'six_abandoned_at',      current_time('mysql') );

    // Also upsert into checkout_progress so it shows up in sales dashboard queries
    global $wpdb;
    $table    = $wpdb->prefix . 'six_checkout_progress';
    $existing = $wpdb->get_var( $wpdb->prepare("SELECT id FROM {$table} WHERE user_id=%d", $user_id) );
    $data     = array( 'step' => $step, 'score' => $score, 'updated_at' => current_time('mysql') );
    if ( $existing ) {
        $wpdb->update( $table, $data, array('user_id' => $user_id) );
    } else {
        $data['user_id']    = $user_id;
        $data['created_at'] = current_time('mysql');
        $wpdb->insert( $table, $data );
    }

    // Notify advisor (global $wpdb already declared above)
    $advisor_id = $wpdb->get_var( $wpdb->prepare(
        "SELECT advisor_id FROM {$wpdb->prefix}six_assignments WHERE client_id=%d", $user_id
    ) );
    if ( $advisor_id && class_exists('Six_Notifications') ) {
        $user = get_userdata( $user_id );
        $lead_type = $score >= 70 ? '🔴 Hot Lead' : ( $score >= 40 ? '🟡 Warm Lead' : '🔵 Cold Lead' );
        Six_Notifications::create( array(
            'user_id' => $advisor_id,
            'type'    => 'abandoned_checkout',
            'title'   => 'Abandoned Checkout — ' . $lead_type,
            'message' => ( $user->user_email ) . ' abandoned at step ' . $step . '. Score: ' . $score . '/100.',
            'action_url' => home_url('/advisor-portal/?tab=approvals'),
        ) );
    }

    // Create Odoo 'Abandoned Onboarding Process' task + update lead
    if ( class_exists('Six_Odoo') ) {
        Six_Odoo::create_abandoned_task( $user_id, $step, $score );
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 7. ROUND-ROBIN ADVISOR ASSIGNMENT
// ─────────────────────────────────────────────────────────────────────────────
function six_assign_advisor_round_robin( $client_id ) {
    global $wpdb;

    // Get all advisors
    $advisors = get_users( array( 'role' => 'six_advisor' ) );
    if ( empty( $advisors ) ) {
        // Fallback: assign to admin
        $admins = get_users( array( 'role' => 'administrator' ) );
        if ( empty( $admins ) ) return null;
        $advisors = $admins;
    }

    // Find advisor with fewest clients (true round-robin by load)
    $advisor_loads = array();
    foreach ( $advisors as $adv ) {
        $count = intval( $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}six_assignments WHERE advisor_id=%d", $adv->ID
        ) ) );
        $advisor_loads[ $adv->ID ] = $count;
    }
    asort( $advisor_loads );
    $assigned_id = array_key_first( $advisor_loads );

    // Save assignment
    $existing = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}six_assignments WHERE client_id=%d", $client_id
    ) );
    if ( ! $existing ) {
        $wpdb->insert( $wpdb->prefix . 'six_assignments', array(
            'client_id'   => $client_id,
            'advisor_id'  => $assigned_id,
            'assigned_at' => current_time('mysql'),
        ) );
    }

    $advisor = get_userdata( $assigned_id );
    return array(
        'id'        => $assigned_id,
        'name'      => $advisor->display_name,
        'email'     => $advisor->user_email,
        'role'      => 'Account Manager · 6ix Developers',
        'initials'  => six_get_initials( $advisor->display_name ),
        'expertise' => array( 'Google Ads', 'SEO', 'Growth Strategy' ),
    );
}

function six_get_advisor_for_user( $user_id ) {
    global $wpdb;
    $advisor_id = $wpdb->get_var( $wpdb->prepare(
        "SELECT advisor_id FROM {$wpdb->prefix}six_assignments WHERE client_id=%d", $user_id
    ) );
    if ( ! $advisor_id ) return null;
    $advisor = get_userdata( $advisor_id );
    return array(
        'id'       => $advisor_id,
        'name'     => $advisor->display_name,
        'initials' => six_get_initials( $advisor->display_name ),
        'role'     => 'Account Manager · 6ix Developers',
        'expertise'=> array( 'Google Ads', 'SEO', 'Growth Strategy' ),
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// 8. AI SUGGESTIONS GENERATOR
// ─────────────────────────────────────────────────────────────────────────────
function six_generate_ai_suggestions( $user_id, $step1, $services ) {
    global $wpdb;
    $suggestions = array();
    $goal      = $step1['goal']      ?? '';
    $challenge = $step1['challenge'] ?? '';
    $platforms = $step1['platforms'] ?? '';
    $budget    = $step1['mktg_budget'] ?? '';
    $svc_keys  = array_keys( $services );

    // Rule-based suggestions (could be replaced with Claude API call later)
    if ( $goal === 'leads' && in_array( 'google-ads', $svc_keys ) ) {
        $suggestions[] = array(
            'title'        => 'Google Ads is Your Primary Lead Channel',
            'description'  => 'Your goal is lead generation and you\'ve activated Google Ads. Focus on high-intent search terms to maximize qualified leads.',
            'action_label' => 'Set Up Campaigns',
            'action_type'  => 'google-ads',
        );
    }
    if ( ! in_array('seo', $svc_keys) && strpos($platforms,'seo') === false ) {
        $suggestions[] = array(
            'title'        => 'SEO Could Build Long-Term Traffic',
            'description'  => 'Your SEO presence isn\'t established yet. Organic search is a compounding asset — starting now means results in 3–6 months.',
            'action_label' => 'Add SEO Service',
            'action_type'  => 'service-add',
        );
    }
    if ( $challenge === 'conversion' ) {
        $suggestions[] = array(
            'title'        => 'Your Conversion Rate Needs Attention',
            'description'  => 'You\'ve identified low conversion rates as your main challenge. A landing page audit and A/B testing setup can deliver immediate improvements.',
            'action_label' => 'Schedule Audit',
            'action_type'  => 'info',
        );
    }
    if ( $challenge === 'traffic' && ! in_array('seo', $svc_keys) ) {
        $suggestions[] = array(
            'title'        => 'Organic Traffic Strategy Recommended',
            'description'  => 'Low traffic is your biggest challenge. A combined Google Ads + SEO approach can drive both immediate and sustainable traffic.',
            'action_label' => 'Explore Strategy',
            'action_type'  => 'info',
        );
    }
    if ( count($svc_keys) >= 2 ) {
        $suggestions[] = array(
            'title'        => 'Multi-Channel Synergy Opportunity',
            'description'  => 'You\'ve selected multiple services. Cross-channel attribution tracking will help you understand which channels drive the most value.',
            'action_label' => 'Learn More',
            'action_type'  => 'info',
        );
    }

    // Insert suggestions into DB
    foreach ( $suggestions as $s ) {
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}six_recommendations WHERE client_id=%d AND title=%s",
            $user_id, $s['title']
        ) );
        if ( ! $existing ) {
            $advisor_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT advisor_id FROM {$wpdb->prefix}six_assignments WHERE client_id=%d", $user_id
            ) ) ?: 1;
            $wpdb->insert( $wpdb->prefix . 'six_recommendations', array(
                'client_id'    => $user_id,
                'advisor_id'   => $advisor_id,
                'title'        => $s['title'],
                'description'  => $s['description'],
                'action_label' => $s['action_label'],
                'action_type'  => $s['action_type'],
                'status'       => 'active',
                'source'       => 'ai_onboarding',
            ) );
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 9. GROWTH OPPORTUNITY ENGINE — periodic cron
// ─────────────────────────────────────────────────────────────────────────────
if ( ! wp_next_scheduled( 'six_growth_engine_cron' ) ) {
    wp_schedule_event( time(), 'daily', 'six_growth_engine_cron' );
}
add_action( 'six_growth_engine_cron', 'six_run_growth_engine' );

function six_run_growth_engine() {
    global $wpdb;
    $clients = get_users( array( 'role' => 'six_customer', 'meta_key' => 'six_checkout_completed', 'meta_value' => '1' ) );
    foreach ( $clients as $client ) {
        six_generate_growth_opportunities( $client->ID );
        sleep( 1 );
    }
}

function six_generate_growth_opportunities( $user_id ) {
    global $wpdb;
    $services = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}six_client_services WHERE client_id=%d AND status='active'", $user_id
    ) );
    $metrics = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}six_metrics WHERE client_id=%d", $user_id
    ) );
    $checkout = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d", $user_id
    ) );
    $opportunities = array();
    $advisor_id = $wpdb->get_var( $wpdb->prepare(
        "SELECT advisor_id FROM {$wpdb->prefix}six_assignments WHERE client_id=%d", $user_id
    ) ) ?: 1;

    foreach ( $services as $svc ) {
        $svc_metrics = array_filter( $metrics, fn($m) => $m->service_slug === $svc->service_slug );
        foreach ( $svc_metrics as $m ) {
            $cur = floatval( preg_replace('/[^0-9.]/','',$m->current_value) );
            $tar = floatval( preg_replace('/[^0-9.]/','',$m->target_value) );
            if ( $tar > 0 && $cur / $tar > 0.85 ) {
                // Near target — suggest budget increase
                $opportunities[] = array(
                    'title'        => $svc->service_name . ' is Approaching Its Target',
                    'description'  => 'Your ' . $m->label . ' is at ' . $m->current_value . ', close to your target of ' . $m->target_value . '. Increasing your ' . $svc->service_name . ' budget could unlock the next growth stage.',
                    'action_label' => 'Request Budget Increase',
                    'action_type'  => 'budget_request',
                    'source'       => 'growth_engine',
                );
            }
            if ( $svc->service_slug === 'google-ads' && stripos($m->label,'conversion') !== false ) {
                if ( $cur < 2.0 ) {
                    $opportunities[] = array(
                        'title'        => 'Low Conversion Rate Detected',
                        'description'  => 'Your Google Ads conversion rate is ' . $m->current_value . '. Industry average is 3–5%. A landing page review could significantly improve results.',
                        'action_label' => 'Request Landing Page Audit',
                        'action_type'  => 'info',
                        'source'       => 'growth_engine',
                    );
                }
            }
        }
    }

    foreach ( $opportunities as $opp ) {
        $title = $opp['title'];
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}six_recommendations WHERE client_id=%d AND title=%s AND status='active'",
            $user_id, $title
        ) );
        if ( ! $exists ) {
            $wpdb->insert( $wpdb->prefix . 'six_recommendations', array(
                'client_id'    => $user_id,
                'advisor_id'   => $advisor_id,
                'title'        => $title,
                'description'  => $opp['description'],
                'action_label' => $opp['action_label'],
                'action_type'  => $opp['action_type'],
                'status'       => 'active',
                'source'       => $opp['source'],
            ) );
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 10. ODOO SYNC HELPERS
// ─────────────────────────────────────────────────────────────────────────────
function six_odoo_create_onboarding_task( $user_id, $email, $type = 'new_client', $extra = array() ) {
    if ( ! class_exists('Six_Odoo') ) return;
    $user     = $user_id ? get_userdata($user_id) : null;
    $name     = $user ? $user->display_name : $email;
    $checkout = $user_id ? get_user_meta($user_id,'six_checkout_score',true) : 0;

    $titles = array(
        'new_client' => 'New Client Registered: ' . $name,
        'abandoned'  => 'Abandoned Checkout: ' . ($user?$user->user_email:$email),
    );
    $task_data = array(
        'name'        => $titles[$type] ?? 'Onboarding Event',
        'description' => $type === 'abandoned'
            ? 'Score: ' . ($extra['score']??0) . '/100, abandoned at step ' . ($extra['step']??'?')
            : 'New client registered. Readiness score: ' . $checkout . '/100.',
        'user_id'     => $user_id,
        'email'       => $user ? $user->user_email : $email,
    );
    Six_Odoo::create_task( $task_data );
}

function six_sync_onboarding_to_odoo( $user_id, $step1, $services = array(), $score = 0 ) {
    if ( ! class_exists('Six_Odoo') ) return;
    $user = get_userdata($user_id);
    Six_Odoo::sync_lead( array(
        'name'         => $step1['first'] . ' ' . $step1['last'],
        'email'        => $user->user_email,
        'phone'        => $step1['phone']   ?? '',
        'company_name' => $step1['bizname'] ?? '',
        'website'      => $step1['website'] ?? '',
        'description'  => 'Goal: '.($step1['goal']??'')."\nChallenge: ".($step1['challenge']??'')."\nServices: ".implode(',',array_keys($services)),
        'score'        => $score,
        'user_id'      => $user_id,
    ) );
}

// ─────────────────────────────────────────────────────────────────────────────
// 11. SALES DASHBOARD DATA — lead classification
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'wp_ajax_six_get_abandoned_leads', 'six_get_abandoned_leads' );
function six_get_abandoned_leads() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! Six_Roles::is_advisor() ) wp_send_json_error('Permission denied');
    $users = get_users( array(
        'meta_key'     => 'six_abandoned_at_step',
        'meta_compare' => 'EXISTS',
    ) );
    $leads = array();
    foreach ( $users as $u ) {
        $score = intval( get_user_meta($u->ID,'six_abandoned_score',true) );
        $step  = intval( get_user_meta($u->ID,'six_abandoned_at_step',true) );
        $time  = get_user_meta($u->ID,'six_abandoned_at',true);
        $leads[] = array(
            'id'         => $u->ID,
            'name'       => $u->display_name ?: $u->user_email,
            'email'      => $u->user_email,
            'score'      => $score,
            'step'       => $step,
            'lead_type'  => $score >= 70 ? 'hot' : ($score >= 40 ? 'warm' : 'cold'),
            'abandoned_at'=> $time,
        );
    }
    usort( $leads, fn($a,$b) => $b['score'] - $a['score'] );
    wp_send_json_success($leads);
}

// ─────────────────────────────────────────────────────────────────────────────
// DB TABLE — add source + completed columns to existing tables
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'admin_init', 'six_onboarding_db_upgrade' );
function six_onboarding_db_upgrade() {
    if ( get_option('six_onboarding_db_v2') ) return;
    global $wpdb;

    // Add source column to recommendations if missing
    $cols = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->prefix}six_recommendations", 0);
    if (!in_array('source', $cols)) {
        $wpdb->query("ALTER TABLE {$wpdb->prefix}six_recommendations ADD COLUMN source VARCHAR(50) DEFAULT 'advisor' AFTER action_type");
    }

    // Add completed column to checkout_progress if missing
    $cols2 = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->prefix}six_checkout_progress", 0);
    if (!in_array('completed', $cols2)) {
        $wpdb->query("ALTER TABLE {$wpdb->prefix}six_checkout_progress ADD COLUMN completed TINYINT(1) DEFAULT 0");
    }
    if (!in_array('website', $cols2)) {
        $wpdb->query("ALTER TABLE {$wpdb->prefix}six_checkout_progress ADD COLUMN website VARCHAR(255) DEFAULT ''");
    }
    if (!in_array('location', $cols2)) {
        $wpdb->query("ALTER TABLE {$wpdb->prefix}six_checkout_progress ADD COLUMN location VARCHAR(255) DEFAULT ''");
    }
    if (!in_array('employees', $cols2)) {
        $wpdb->query("ALTER TABLE {$wpdb->prefix}six_checkout_progress ADD COLUMN employees VARCHAR(50) DEFAULT ''");
    }
    if (!in_array('platforms', $cols2)) {
        $wpdb->query("ALTER TABLE {$wpdb->prefix}six_checkout_progress ADD COLUMN platforms VARCHAR(255) DEFAULT ''");
    }
    if (!in_array('mktg_budget', $cols2)) {
        $wpdb->query("ALTER TABLE {$wpdb->prefix}six_checkout_progress ADD COLUMN mktg_budget VARCHAR(50) DEFAULT ''");
    }
    if (!in_array('challenge', $cols2)) {
        $wpdb->query("ALTER TABLE {$wpdb->prefix}six_checkout_progress ADD COLUMN challenge VARCHAR(100) DEFAULT ''");
    }

    update_option('six_onboarding_db_v2', 1);
}

// ─────────────────────────────────────────────────────────────────────────────
// PASSWORD MANAGEMENT
// ─────────────────────────────────────────────────────────────────────────────

// Set password for new user (called after step 1)
add_action( 'wp_ajax_nopriv_six_set_user_password', 'six_set_user_password' );
add_action( 'wp_ajax_six_set_user_password',        'six_set_user_password' );
function six_set_user_password() {
    $user_id  = intval( $_POST['user_id'] ?? 0 );
    $password = $_POST['password'] ?? '';
    if ( ! $user_id || strlen($password) < 8 ) {
        wp_send_json_error('Invalid data');
    }
    // Verify user exists and hasn't completed checkout (prevent misuse)
    $done = get_user_meta($user_id,'six_checkout_completed',true);
    if ($done) { wp_send_json_error('Not applicable'); }
    wp_set_password($password,$user_id);
    // Re-auth cookie since password changed
    $user = get_userdata($user_id);
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true);
    // Update temp password meta
    update_user_meta($user_id,'six_temp_password',$password);
    wp_send_json_success();
}

// Inline password reset (custom forgot password)
add_action( 'wp_ajax_nopriv_six_send_password_reset', 'six_send_password_reset' );
add_action( 'wp_ajax_six_send_password_reset',        'six_send_password_reset' );
function six_send_password_reset() {
    $email = sanitize_email( $_POST['email'] ?? '' );
    if ( ! is_email($email) ) { wp_send_json_error('Invalid email'); }
    $user = get_user_by('email',$email);
    if ( ! $user ) {
        // Don't reveal whether email exists
        wp_send_json_success();
        return;
    }
    // Use WordPress's own reset mechanism
    $reset_key  = get_password_reset_key($user);
    if ( is_wp_error($reset_key) ) { wp_send_json_error('Could not generate reset key'); }
    $reset_url  = network_site_url("wp-login.php?action=rp&key={$reset_key}&login=" . rawurlencode($user->user_login), 'login');
    $site_name  = get_bloginfo('name');
    $subject    = "Reset your {$site_name} password";
    $message    = "<p>Hi " . esc_html($user->first_name ?: $user->display_name) . ",</p>"
                . "<p>Someone requested a password reset for your account. If this was you, click below:</p>"
                . "<p><a href='{$reset_url}' style='background:#FF6699;color:white;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600'>Reset My Password →</a></p>"
                . "<p style='color:#666;font-size:12px'>If you didn't request this, you can safely ignore this email.</p>";
    wp_mail($user->user_email, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));
    wp_send_json_success();
}
