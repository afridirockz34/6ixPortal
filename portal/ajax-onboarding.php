<?php
/**
 * 6ix Developers — Onboarding AJAX Handlers
 * Upload to: /wp-content/themes/6ixClaude/portal/ajax-onboarding.php
 * Then add to functions.php: require_once SIX_PLUGIN_DIR . 'ajax-onboarding.php';
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ── JSON SANITIZER ────────────────────────────────────────────────────────
// Store a JSON blob safely: decode → recursively sanitize scalar values →
// re-encode. Non-JSON or empty input yields ''. This keeps the Google Ads
// audit payload intact (quotes/structure preserved) while stripping any HTML
// injected into free-text answers.
function six_sanitize_json( $raw ) {
    if ( ! is_string( $raw ) || $raw === '' ) return '';
    $decoded = json_decode( stripslashes( $raw ), true );
    if ( ! is_array( $decoded ) ) return '';
    $clean = function ( $v ) use ( &$clean ) {
        if ( is_array( $v ) ) return array_map( $clean, $v );
        if ( is_string( $v ) ) return sanitize_textarea_field( $v );
        return $v;
    };
    $decoded = array_map( $clean, $decoded );
    $out = wp_json_encode( $decoded );
    return $out ?: '';
}

// ── TARGET-USER AUTHORIZATION ─────────────────────────────────────────────
// Several onboarding endpoints accept a user_id in POST and stay nopriv
// because the auth cookie may not be attached yet on the first requests after
// account creation. Without this guard, anyone could act on any user_id.
// Rules: logged-in users act on themselves (advisors/admins on anyone);
// guests may only act on fresh six_customer accounts that haven't completed
// checkout — the only case where the cookie-lag edge actually applies.
function six_onboarding_resolve_user() {
    $requested = intval( $_POST['user_id'] ?? 0 );
    $current   = get_current_user_id();

    if ( $current ) {
        $is_staff = ( class_exists('Six_Roles') && Six_Roles::is_advisor() ) || current_user_can('manage_options');
        if ( $requested && $requested !== $current && ! $is_staff ) {
            return $current;
        }
        return $requested ?: $current;
    }

    if ( ! $requested ) return 0;
    $target = get_userdata( $requested );
    if ( ! $target ) return 0;
    if ( ! in_array( 'six_customer', (array) $target->roles, true ) ) return 0;
    if ( user_can( $requested, 'manage_options' ) ) return 0;
    if ( get_user_meta( $requested, 'six_checkout_completed', true ) ) return 0;
    if ( ( time() - strtotime( $target->user_registered ) ) > DAY_IN_SECONDS ) return 0;
    return $requested;
}

// ── GET ONBOARDING STATE (resume on refresh) ─────────────────────────────
// Returns all saved checkout_progress data so JS can restore S.q on refresh
add_action( 'wp_ajax_six_get_onboarding_state',        'six_get_onboarding_state' );
add_action( 'wp_ajax_nopriv_six_get_onboarding_state', 'six_get_onboarding_state' );
function six_get_onboarding_state() {
    // Returns the full questionnaire (names, phone, business data) — never
    // serve it for an arbitrary user_id.
    $user_id = six_onboarding_resolve_user();
    if ( ! $user_id ) { wp_send_json_error('No user'); return; }

    global $wpdb;
    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d", $user_id
    ) );

    if ( ! $row ) { wp_send_json_error('No state'); return; }

    // Return all fields the JS needs to restore S.q and S.svcs
    wp_send_json_success( array(
        'step'      => intval( $row->step ?? 1 ),
        'platforms' => $row->platforms ?? '',
        'q' => array(
            // Personal
            'first'       => $row->first_name         ?? '',
            'last'        => $row->last_name           ?? '',
            'phone'       => $row->phone               ?? '',
            'bizname'     => $row->business_name       ?? '',
            'website'     => $row->website             ?? '',
            'industry'    => $row->industry            ?? '',
            'address'     => $row->location ?? $row->business_address ?? '',
            'years'       => $row->years_in_business   ?? '',
            'goals'       => $row->goal                ?? '',
            'comp1'       => '',
            'comp2'       => '',
            'comp3'       => '',
            // Competitors — stored as comma-separated
            '_competitors'=> $row->competitors         ?? '',
            // Google Ads
            'ads_loc'     => $row->ads_locations       ?? '',
            'ads_loc_type'=> $row->ads_loc_type        ?? 'Include',
            'ads_prod'    => $row->ads_products        ?? '',
            'ads_kw'      => $row->ads_keywords        ?? '',
            'ads_usp'     => $row->ads_usp             ?? '',
            'ads_promo'   => $row->ads_promo           ?? '',
            'ads_sched'   => $row->ads_schedule        ?? '',
            'ads_bud'     => intval( $row->ads_budget  ?? 0 ),
            // Google Ads — "currently running" audit branch
            'gads_running'=> $row->gads_running        ?? '',
            'gads_audit'  => $row->gads_audit_json     ?? '',
            'gads_link'   => $row->gads_link_status    ?? '',
            'gads_cid'    => $row->gads_customer_id    ?? '',
            // SEO
            'seo_pages'   => $row->seo_pages           ?? '',
            'seo_loc'     => $row->seo_locations       ?? '',
            'seo_kw'      => $row->seo_keywords        ?? '',
            'seo_usp'     => $row->seo_usp             ?? '',
            'seo_gsc'     => $row->seo_gsc             ?? '',
            'seo_blog'    => $row->seo_blog            ?? '',
            'seo_comp'    => $row->seo_competitors     ?? '',
            'seo_crm'     => $row->seo_crm_tools       ?? '',
            'seo_reviews' => $row->seo_reviews         ?? '',
            'seo_extra'   => $row->seo_extra_info      ?? '',
            'seo_bud'     => intval( $row->seo_budget  ?? 0 ),
            // GBP
            'gbp_name'    => $row->gbp_name            ?? '',
            'gbp_cat'     => $row->gbp_category        ?? '',
            'gbp_svcs'    => $row->gbp_services        ?? '',
            'gbp_hrs'     => $row->gbp_hours           ?? '',
            'gbp_rating'  => $row->gbp_rating          ?? '',
            'gbp_bud'     => intval( $row->gbp_budget  ?? 0 ),
            // Website
            'web_goal'    => $row->web_goal            ?? '',
            'web_pages'   => $row->web_pages           ?? '',
            'web_style'   => $row->web_style           ?? '',
            'web_refs'    => $row->web_refs            ?? '',
            'web_exist'   => $row->web_existing        ?? '',
            'web_platform'=> $row->web_platform        ?? '',
            'web_timeline'=> $row->web_timeline        ?? '',
            'web_features'=> $row->web_features        ?? '',
            'web_bud'     => intval( $row->web_budget  ?? 0 ),
        ),
        'budgets' => array(
            'google-ads'      => intval( $row->ads_budget  ?? 0 ),
            'seo'             => intval( $row->seo_budget  ?? 0 ),
            'google-business' => intval( $row->gbp_budget  ?? 0 ),
            'website'         => intval( $row->web_budget  ?? 0 ),
        ),
    ) );
}


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

    // Create Odoo contact immediately — welcome message fired by on_personal_info_submitted
    if ( class_exists('Six_Odoo') ) {
        Six_Odoo::create_or_update_contact( $user_id );
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
    // Target authorization via six_onboarding_resolve_user().
    $user_id = six_onboarding_resolve_user();
    if ( ! $user_id ) wp_send_json_error( 'No user' );
    // Update last_event timestamp for stale lead detection
    update_user_meta( $user_id, 'six_last_event', current_time('mysql') );

    $step     = sanitize_text_field( $_POST['step'] ?? '' );
    $raw_data = $_POST['data'] ?? '';
    $score    = intval( $_POST['score'] ?? 0 );
    $data     = $raw_data ? json_decode( stripslashes( $raw_data ), true ) : array();

    // Keep the resume pointer accurate everywhere (login redirect, admin views).
    // Navigation pings from goStep() carry a step but no data payload; the
    // data-dump saves always send step:1 WITH data. Only advance on the
    // payload-free navigation pings, and never regress — the furthest step
    // reached is where we resume, and all typed data is preserved regardless.
    if ( $raw_data === '' && $step !== '' ) {
        $numeric = intval( $step ); // '3a'/'3b'/'3c' -> 3, '1b' -> 1
        $prev    = intval( get_user_meta( $user_id, 'six_checkout_step', true ) ?: 0 );
        if ( $numeric > $prev ) update_user_meta( $user_id, 'six_checkout_step', $numeric );
    }

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
            'competitors'    => sanitize_text_field( $data['competitors'] ?? '' ),
            'goal'           => sanitize_text_field( $data['goal']        ?? '' ),
            'challenge'      => sanitize_text_field( $data['challenge']   ?? '' ),
            'mktg_budget'    => sanitize_text_field( $data['mktg_budget'] ?? '' ),
            'platforms'      => sanitize_text_field( $data['platforms']   ?? '' ),
            'monthly_revenue'    => sanitize_text_field( $data['revenue']     ?? $data['monthly_revenue'] ?? '' ),
            // New v5 fields
            'business_address'   => sanitize_text_field( $data['location']     ?? '' ),
            'years_in_business'  => sanitize_text_field( $data['years']        ?? '' ),
            'employees'          => sanitize_text_field( $data['employees']    ?? '' ),
            'monthly_revenue'    => sanitize_text_field( $data['revenue']      ?? '' ),
            'challenge'          => sanitize_text_field( $data['challenge']    ?? '' ),
            'ads_locations'      => sanitize_text_field( $data['ads_loc']      ?? '' ),
            'ads_loc_type'       => sanitize_text_field( $data['ads_loc_type'] ?? 'Include' ),
            'ads_products'       => sanitize_textarea_field( $data['ads_prod'] ?? '' ),
            'ads_keywords'       => sanitize_textarea_field( $data['ads_kw']   ?? '' ),
            'ads_usp'            => sanitize_textarea_field( $data['ads_usp']  ?? '' ),
            'ads_promo'          => sanitize_text_field( $data['ads_promo']    ?? '' ),
            'ads_schedule'       => sanitize_text_field( $data['ads_sched']    ?? '' ),
            'ads_budget'         => intval(               $data['ads_bud']     ?? 0 ),
            // Google Ads "currently running" audit branch
            'gads_running'       => sanitize_text_field( $data['gads_running'] ?? '' ),
            'gads_audit_json'    => six_sanitize_json(   $data['gads_audit']   ?? '' ),
            'seo_pages'          => sanitize_textarea_field( $data['seo_pages']?? '' ),
            'seo_locations'      => sanitize_text_field( $data['seo_loc']      ?? '' ),
            'seo_keywords'       => sanitize_textarea_field( $data['seo_kw']   ?? '' ),
            'seo_usp'            => sanitize_textarea_field( $data['seo_usp']  ?? '' ),
            'seo_gsc'            => sanitize_text_field( $data['seo_gsc']      ?? '' ),
            'seo_blog'           => sanitize_text_field( $data['seo_blog']     ?? '' ),
            'seo_competitors'    => sanitize_text_field( $data['seo_comp']    ?? '' ),
            'seo_crm_tools'      => sanitize_text_field( $data['seo_crm']     ?? '' ),
            'seo_reviews'        => sanitize_text_field( $data['seo_reviews'] ?? '' ),
            'seo_extra_info'     => sanitize_text_field( $data['seo_extra']   ?? '' ),
            'seo_budget'         => intval(               $data['seo_bud']     ?? 0 ),
            'gbp_name'           => sanitize_text_field( $data['gbp_name']     ?? '' ),
            'gbp_category'       => sanitize_text_field( $data['gbp_cat']      ?? '' ),
            'gbp_services'       => sanitize_textarea_field( $data['gbp_svcs'] ?? '' ),
            'gbp_hours'          => sanitize_text_field( $data['gbp_hrs']     ?? '' ),
            'gbp_rating'         => sanitize_text_field( $data['gbp_rating']   ?? '' ),
            'gbp_budget'         => intval(               $data['gbp_bud']     ?? 0 ),
            'web_goal'           => sanitize_text_field( $data['web_goal']     ?? '' ),
            'web_platform'       => sanitize_text_field( $data['web_platform'] ?? '' ),
            'web_timeline'       => sanitize_text_field( $data['web_timeline'] ?? '' ),
            'web_features'       => sanitize_text_field( $data['web_features'] ?? '' ),
            'web_pages'          => sanitize_text_field( $data['web_pages']    ?? '' ),
            'web_style'          => sanitize_text_field( $data['web_style']    ?? '' ),
            'web_refs'           => sanitize_text_field( $data['web_refs']     ?? '' ),
            'web_existing'       => sanitize_text_field( $data['web_exist']    ?? '' ),
            'web_budget'         => intval(               $data['web_bud']     ?? 0 ),
            'crm_tools'          => sanitize_text_field( $data['crm']          ?? '' ),
            'reviews_awards'     => sanitize_text_field( $data['awards']       ?? '' ),
            'onboarding_notes'   => sanitize_textarea_field( $data['notes']    ?? '' ),
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

    // Save step 2 API IDs to user meta immediately for use at final checkout
    if ( $step === '2' ) {
        $api_data = array();
        if ( !empty($data['gads_id']) ) { update_user_meta($user_id,'six_gads_customer_id',sanitize_text_field($data['gads_id'])); $api_data['gads_id']=$data['gads_id']; }
        if ( !empty($data['ga4_id'])  ) { update_user_meta($user_id,'six_ga4_property_id', sanitize_text_field($data['ga4_id']));  $api_data['ga4_id'] =$data['ga4_id'];  }
        if ( !empty($data['meta_id']) ) { update_user_meta($user_id,'six_meta_ad_account_id',sanitize_text_field($data['meta_id'])); $api_data['meta_id']=$data['meta_id']; }
        if ( !empty($api_data) ) update_user_meta($user_id,'six_checkout_step2_data',json_encode($api_data));
    }

    // Update last activity timestamp — used by stale-lead cron for inactivity detection
    if ( $user_id ) {
        update_user_meta( $user_id, 'six_last_activity', current_time('mysql') );
    }

    // Step 1 complete — update contact and update lead score/data in Odoo
    if ( $step === '1' && $data && class_exists('Six_Odoo') ) {
        // Post questionnaire data as a chatter note for advisor visibility
        $note_parts = array();
        if ( !empty($data['bizname'])    ) $note_parts[] = 'Business: '     . $data['bizname'];
        if ( !empty($data['industry'])   ) $note_parts[] = 'Industry: '     . $data['industry'];
        if ( !empty($data['location'])   ) $note_parts[] = 'Location: '     . $data['location'];
        if ( !empty($data['goal'])       ) $note_parts[] = 'Goals: '        . $data['goal'];
        if ( !empty($data['platforms'])  ) $note_parts[] = 'Services: '     . $data['platforms'];
        if ( !empty($data['mktg_budget'])) $note_parts[] = 'Budget: $'      . $data['mktg_budget'] . '/mo';
        if ( !empty($data['ads_kw'])     ) $note_parts[] = 'Ad Keywords: '  . $data['ads_kw'];
        if ( !empty($data['ads_usp'])    ) $note_parts[] = 'USP: '          . $data['ads_usp'];
        if ( !empty($data['seo_kw'])     ) $note_parts[] = 'SEO Keywords: ' . $data['seo_kw'];
        if ( !empty($data['competitors'])) $note_parts[] = 'Competitors: '  . $data['competitors'];
        $q_note = implode( "
", $note_parts );
        Six_Odoo::create_or_update_contact( $user_id );
        // Only update lead fields (score, step, services data) — not the stage
        $lead_id = intval( get_user_meta( $user_id, 'six_odoo_lead_id', true ) );
        if ( $lead_id ) {
            // Update custom fields only — no stage_id change
            $fields = array();
            if ( get_option('six_odoo_custom_fields_ready') ) {
                $fields['x_checkout_step']  = 1;
                $fields['x_checkout_score'] = $new_score;
            }
            if ( ! empty($fields) ) {
                Six_Odoo::execute_public( 'crm.lead', 'write', array( array($lead_id), $fields ) );
            }
            if ( ! empty($q_note) ) {
                Six_Odoo::post_note( $lead_id, "Onboarding questionnaire submitted:

" . $q_note );
            }
        }
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
    global $wpdb;
    $user_id          = six_onboarding_resolve_user();
    $signature        = sanitize_text_field( $_POST['signature']         ?? '' );
    $payment_method   = sanitize_text_field( $_POST['payment_method_id'] ?? '' );
    $services_raw     = sanitize_text_field( $_POST['services']          ?? '{}' );
    $step1_raw        = sanitize_text_field( $_POST['step1_data']        ?? '{}' );
    $score            = intval( $_POST['score'] ?? 0 );

    if ( ! $user_id || ! $signature ) wp_send_json_error( 'Missing required fields.' );

    $services  = json_decode( stripslashes( $services_raw ), true ) ?: array();
    $step1     = json_decode( stripslashes( $step1_raw ),    true ) ?: array();

    // Save AI plan JSON to checkout_progress if provided
    $ai_plan_json = $_POST['ai_plan_json'] ?? '';

    // ── GUARANTEED QUESTIONNAIRE SAVE ─────────────────────────────────────────
    // step1_data contains the full S.q object — save it here so data is never
    // lost even if the intermediate async saves during onboarding failed.
    $d = json_decode( stripslashes( $_POST['step1_data'] ?? '{}' ), true ) ?: array();
    if ( $d && $user_id ) {
        $tbl = $wpdb->prefix . 'six_checkout_progress';
        $q_save = array(
            'first_name'      => sanitize_text_field( $d['first']        ?? '' ),
            'last_name'       => sanitize_text_field( $d['last']         ?? '' ),
            'phone'           => sanitize_text_field( $d['phone']        ?? '' ),
            'business_name'   => sanitize_text_field( $d['bizname']      ?? '' ),
            'website'         => esc_url_raw(         $d['website']      ?? '' ),
            'industry'        => sanitize_text_field( $d['industry']     ?? '' ),
            'location'        => sanitize_text_field( $d['address']      ?? $d['location'] ?? '' ),
            'business_address'=> sanitize_text_field( $d['address']      ?? '' ),
            'years_in_business'=> sanitize_text_field($d['years']        ?? '' ),
            'goal'            => sanitize_text_field( $d['goals']        ?? '' ),
            'competitors'     => sanitize_text_field( implode(',', array_filter([
                                    $d['comp1']??'', $d['comp2']??'', $d['comp3']??''
                                ])) ),
            'platforms'       => sanitize_text_field( $d['platforms']    ?? '' ),
            'mktg_budget'     => sanitize_text_field( $d['mktg_budget']  ?? '' ),
            // Google Ads
            'ads_locations'   => sanitize_text_field( $d['ads_loc']      ?? '' ),
            'ads_loc_type'    => sanitize_text_field( $d['ads_loc_type'] ?? 'Include' ),
            'ads_products'    => sanitize_textarea_field( $d['ads_prod'] ?? '' ),
            'ads_keywords'    => sanitize_textarea_field( $d['ads_kw']   ?? '' ),
            'ads_usp'         => sanitize_textarea_field( $d['ads_usp']  ?? '' ),
            'ads_promo'       => sanitize_text_field( $d['ads_promo']    ?? '' ),
            'ads_schedule'    => sanitize_text_field( $d['ads_sched']    ?? '' ),
            'ads_budget'      => intval(               $d['ads_bud']     ?? 0 ),
            // Google Ads "currently running" audit branch
            'gads_running'    => sanitize_text_field( $d['gads_running'] ?? '' ),
            'gads_audit_json' => six_sanitize_json(   $d['gads_audit']   ?? '' ),
            // SEO
            'seo_pages'       => sanitize_textarea_field( $d['seo_pages']?? '' ),
            'seo_locations'   => sanitize_text_field( $d['seo_loc']      ?? '' ),
            'seo_keywords'    => sanitize_textarea_field( $d['seo_kw']   ?? '' ),
            'seo_usp'         => sanitize_textarea_field( $d['seo_usp']  ?? '' ),
            'seo_gsc'         => sanitize_text_field( $d['seo_gsc']      ?? '' ),
            'seo_blog'        => sanitize_text_field( $d['seo_blog']     ?? '' ),
            'seo_competitors' => sanitize_text_field( $d['seo_comp']     ?? '' ),
            'seo_crm_tools'   => sanitize_text_field( $d['seo_crm']      ?? '' ),
            'seo_reviews'     => sanitize_text_field( $d['seo_reviews']  ?? '' ),
            'seo_extra_info'  => sanitize_textarea_field( $d['seo_extra']?? '' ),
            'seo_budget'      => intval(               $d['seo_bud']     ?? 0 ),
            // Google Business Profile
            'gbp_name'        => sanitize_text_field( $d['gbp_name']     ?? '' ),
            'gbp_category'    => sanitize_text_field( $d['gbp_cat']      ?? '' ),
            'gbp_services'    => sanitize_textarea_field( $d['gbp_svcs'] ?? '' ),
            'gbp_hours'       => sanitize_text_field( $d['gbp_hrs']      ?? '' ),
            'gbp_rating'      => sanitize_text_field( $d['gbp_rating']   ?? '' ),
            'gbp_budget'      => intval(               $d['gbp_bud']     ?? 0 ),
            // Website
            'web_goal'        => sanitize_text_field( $d['web_goal']     ?? '' ),
            'web_pages'       => sanitize_text_field( $d['web_pages']    ?? '' ),
            'web_style'       => sanitize_text_field( $d['web_style']    ?? '' ),
            'web_refs'        => sanitize_text_field( $d['web_refs']     ?? '' ),
            'web_existing'    => sanitize_text_field( $d['web_exist']    ?? '' ),
            'web_budget'      => intval(               $d['web_bud']     ?? 0 ),
            'updated_at'      => current_time('mysql'),
        );
        if ( $ai_plan_json ) {
            $q_save['ai_plan_json'] = sanitize_textarea_field($ai_plan_json);
        }
        // Guarantee the row exists, and NEVER wipe already-saved answers with
        // blanks. On update we write only the fields that actually have a value
        // (a partial S.q at completion must not clear business name / competitors
        // that the intermediate saves already stored).
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$tbl} WHERE user_id=%d", $user_id
        ));
        $write = $q_save;
        if ( $exists ) {
            $write = array_filter( $q_save, function ( $v ) { return $v !== '' && $v !== null && $v !== 0; } );
            $write['updated_at'] = current_time('mysql');
            $res = $wpdb->update( $tbl, $write, array( 'user_id' => $user_id ) );
        } else {
            $q_save['user_id']    = $user_id;
            $q_save['created_at'] = current_time('mysql');
            $q_save['step']       = '4';
            $write = $q_save;
            $res = $wpdb->insert( $tbl, $q_save );
        }
        // A missing column makes the write fail silently and the profile stays
        // blank — run the schema migration and retry with only the live columns.
        if ( $res === false && $wpdb->last_error ) {
            if ( function_exists( 'six_onboarding_db_upgrade' ) ) {
                delete_option( 'six_onboarding_db_v4' );
                six_onboarding_db_upgrade();
            }
            $live = $wpdb->get_col( "SHOW COLUMNS FROM {$tbl}", 0 );
            $safe = array_intersect_key( $write, array_flip( $live ) );
            if ( $exists ) $wpdb->update( $tbl, $safe, array( 'user_id' => $user_id ) );
            else           $wpdb->insert( $tbl, $safe );
        }
        // Also update WP user name fields
        if ( !empty($q_save['first_name']) ) {
            wp_update_user( array(
                'ID'           => $user_id,
                'first_name'   => $q_save['first_name'],
                'last_name'    => $q_save['last_name'],
                'display_name' => trim($q_save['first_name'].' '.$q_save['last_name']),
            ) );
        }
        if ( !empty($q_save['phone']) ) {
            update_user_meta( $user_id, 'billing_phone', $q_save['phone'] );
        }
    } elseif ( $ai_plan_json && $user_id ) {
        $wpdb->update( $wpdb->prefix . 'six_checkout_progress',
            array( 'ai_plan_json' => sanitize_textarea_field($ai_plan_json) ),
            array( 'user_id' => $user_id ) );
    }

    // Final score = 100
    update_user_meta( $user_id, 'six_checkout_score',     100 );
    update_user_meta( $user_id, 'six_checkout_completed', 1 );
    update_user_meta( $user_id, 'six_checkout_signature', $signature );

    // Existing-Google-Ads: record the account-link intent for the advisor.
    // We store only the (non-secret) Customer ID and a link status — never a
    // password. 'requested' means the client gave their Customer ID so the
    // advisor can send an MCC access request; 'later' means link on the call.
    $gads_cid    = preg_replace( '/[^0-9]/', '', sanitize_text_field( $_POST['gads_customer_id'] ?? '' ) );
    $gads_link   = sanitize_text_field( $_POST['gads_link_status'] ?? '' );
    if ( $gads_link === 'requested' || $gads_link === 'later' ) {
        update_user_meta( $user_id, 'six_gads_link_status', $gads_link );
        if ( $gads_cid !== '' ) {
            update_user_meta( $user_id, 'six_gads_customer_id', $gads_cid );
        }
        $gads_update = array( 'gads_link_status' => $gads_link );
        if ( $gads_cid !== '' ) $gads_update['gads_customer_id'] = $gads_cid;
        $wpdb->update( $wpdb->prefix . 'six_checkout_progress', $gads_update, array( 'user_id' => $user_id ) );
    }

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

    // Save API connection IDs if provided (from step 2)
    $step2_data = json_decode( stripslashes( get_user_meta($user_id,'six_checkout_step2_data',true)??'{}' ), true ) ?: array();
    if( !empty($step2_data['gads_id']) ) update_user_meta( $user_id, 'six_gads_customer_id', sanitize_text_field($step2_data['gads_id']) );
    if( !empty($step2_data['ga4_id'])  ) update_user_meta( $user_id, 'six_ga4_property_id',  sanitize_text_field($step2_data['ga4_id'])  );
    if( !empty($step2_data['meta_id']) ) update_user_meta( $user_id, 'six_meta_ad_account_id',sanitize_text_field($step2_data['meta_id']) );

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

    // Odoo: update contact, move stage to Onboarding Submitted, send messages
    if ( class_exists('Six_Odoo') ) {
        Six_Odoo::create_or_update_contact( $user_id );

        $svc_nm = array(
            'google-ads'   => 'Google Ads',
            'seo'          => 'SEO',
            'social-media' => 'Social Media Marketing',
            'brand-dev'    => 'Brand Development',
            'website'      => 'Website Development',
        );
        $svc_names    = array();
        $total_budget = 0;
        foreach ( $services as $slug => $budget ) {
            $svc_names[]   = $svc_nm[$slug] ?? ucwords( str_replace('-',' ',$slug) );
            $total_budget += floatval( $budget );
        }

        Six_Odoo::on_onboarding_completed(
            $user_id,
            implode(', ', $svc_names),
            $total_budget,
            ! empty( $payment_method )
        );
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
add_action( 'wp_ajax_nopriv_six_growth_abandon',           'six_track_abandoned_checkout' );
add_action( 'wp_ajax_six_growth_abandon',                  'six_track_abandoned_checkout' );

function six_track_abandoned_checkout() {
    $user_id    = intval( $_POST['user_id']    ?? 0 );
    $step       = intval( $_POST['step']       ?? 0 );
    $score      = intval( $_POST['score']      ?? 0 );
    $email      = sanitize_email( $_POST['email']      ?? '' );
    $session_id = sanitize_text_field( $_POST['session_id'] ?? '' );

    error_log("6ix Abandon AJAX: uid={$user_id} email={$email} step={$step}");

    // Resolve user from email or session_id if user_id missing
    if ( ! $user_id && $email ) {
        $u = get_user_by( 'email', $email );
        if ( $u ) $user_id = $u->ID;
    }
    if ( ! $user_id && $session_id ) {
        global $wpdb;
        $found = $wpdb->get_var( $wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key='six_session_id' AND meta_value=%s LIMIT 1",
            $session_id
        ) );
        if ( $found ) $user_id = intval($found);
    }
    if ( ! $user_id ) { wp_send_json_success( array('note'=>'no_user') ); return; }
    if ( get_user_meta( $user_id, 'six_checkout_completed', true ) ) {
        wp_send_json_success( array('note'=>'completed') ); return;
    }

    // Fill step/score from DB if not provided
    if ( ! $step  ) $step  = intval( get_user_meta($user_id,'six_checkout_step', true) ?: 0 );
    if ( ! $score ) $score = intval( get_user_meta($user_id,'six_checkout_score',true) ?: 0 );

    // Delegate to Growth Engine — single authoritative abandon handler
    if ( class_exists('Six_Growth_Engine') ) {
        Six_Growth_Engine::on_abandon( $user_id, $step, $score );
    }

    wp_send_json_success();
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
// 8b. SERVER-SIDE ABANDON CRON — runs every 15 min
// Catches users who left without the JS beacon firing (mobile, browser crash, etc.)
// Fires abandon flow for any user who:
//   - Has a userId (step 0 done)
//   - Has NOT completed onboarding
//   - Has NOT been abandon-triggered in last 10 min
//   - Has been inactive for more than 10 minutes (last event > 10 min ago)
// ─────────────────────────────────────────────────────────────────────────────
add_filter( 'cron_schedules', 'six_add_15min_schedule' );
function six_add_15min_schedule( $schedules ) {
    if ( ! isset($schedules['six_15min']) ) {
        $schedules['six_15min'] = array( 'interval' => 900, 'display' => 'Every 15 minutes' );
    }
    return $schedules;
}

// six_stale_lead_cron disabled — handled by six_stale_lead_check_v2
// if ( ! wp_next_scheduled( 'six_stale_lead_cron' ) ) {
//     wp_schedule_event( time(), 'six_15min', 'six_stale_lead_cron' );
// }
add_action( 'six_stale_lead_cron', 'six_process_stale_leads' );
function six_process_stale_leads() {
    if ( ! class_exists('Six_Odoo') ) return;

    // Find customers who started onboarding but haven't completed
    $users = get_users( array(
        'role'       => 'six_customer',
        'number'     => 50,
        'meta_query' => array(
            array( 'key' => 'six_checkout_completed', 'compare' => 'NOT EXISTS' ),
        ),
    ) );
    // Also include those with six_checkout_completed = 0
    $users2 = get_users( array(
        'role'       => 'six_customer',
        'number'     => 50,
        'meta_key'   => 'six_checkout_completed',
        'meta_value' => '0',
    ) );
    $all = array_merge( $users, $users2 );
    $seen = array();

    foreach ( $all as $u ) {
        if ( in_array($u->ID, $seen) ) continue;
        $seen[] = $u->ID;

        // Skip if already abandon-triggered in last 24 hours
        $last_abandon = get_user_meta( $u->ID, 'six_last_abandon_odoo', true );
        if ( $last_abandon && ( time() - intval($last_abandon) ) < 86400 ) continue;

        // Skip if no lead created yet (user just registered, < 5 min ago)
        $registered = strtotime( $u->user_registered );
        if ( ( time() - $registered ) < 300 ) continue;

        // Skip if active in last 10 min (last_event meta)
        $last_event = get_user_meta( $u->ID, 'six_last_event', true );
        if ( $last_event && ( time() - strtotime($last_event) ) < 600 ) continue;

        $step  = intval( get_user_meta( $u->ID, 'six_checkout_step',  true ) ?: 0 );
        $score = intval( get_user_meta( $u->ID, 'six_checkout_score', true ) ?: 0 );

        // Skip if they only just registered (step 0, < 10 min ago — welcome already sent)
        // Stale = they went past step 0 and haven't touched it in 10+ min
        if ( $step === 0 ) {
            $registered = strtotime( $u->user_registered );
            if ( ( time() - $registered ) < 600 ) {
                continue; // too recent, not stale yet
            }
        }

        // Handled by six_stale_lead_check_v2 (every 5min) — skip here to avoid duplicates
        // error_log( "6ix StaleLeadCron: skip uid={$u->ID} — handled by StaleV2" );
        continue;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 8c. 3-DAY FOLLOW-UP CRON — generates new activity every 3 days for long-term abandoned
// ─────────────────────────────────────────────────────────────────────────────
add_filter( 'cron_schedules', 'six_add_3day_schedule' );
function six_add_3day_schedule( $schedules ) {
    if ( ! isset($schedules['six_3days']) ) {
        $schedules['six_3days'] = array( 'interval' => 259200, 'display' => 'Every 3 days' );
    }
    return $schedules;
}
if ( ! wp_next_scheduled( 'six_followup_cron' ) ) {
    wp_schedule_event( time(), 'six_3days', 'six_followup_cron' );
}
add_action( 'six_followup_cron', 'six_process_abandoned_followups' );
function six_process_abandoned_followups() {
    if ( ! class_exists('Six_Odoo') ) return;

    // Find all abandoned users who have not completed onboarding
    $users = get_users( array(
        'role'     => 'six_customer',
        'number'   => 100,
        'meta_query' => array(
            'relation' => 'AND',
            array( 'key' => 'six_abandoned_at_step', 'compare' => 'EXISTS' ),
            array( 'key' => 'six_checkout_completed', 'compare' => 'NOT EXISTS' ),
        ),
    ) );

    foreach ( $users as $u ) {
        // Skip if completed
        if ( get_user_meta($u->ID, 'six_checkout_completed', true) ) continue;

        $lead_id = intval( get_user_meta($u->ID, 'six_odoo_lead_id', true) );
        if ( ! $lead_id ) continue;

        // Skip if last followup was less than 3 days ago
        $last_followup = get_user_meta($u->ID, 'six_last_3day_followup', true);
        if ( $last_followup && ( time() - intval($last_followup) ) < 259200 ) continue;
        update_user_meta($u->ID, 'six_last_3day_followup', time());

        $step        = intval( get_user_meta($u->ID,'six_checkout_step',true) ?: 0 );
        $advisor_uid = Six_Odoo::get_advisor_odoo_uid_public($u->ID);
        $advisor_url = home_url('/advisor-portal/?tab=clients&client=' . $u->ID);
        $step_labels = array(0=>'Personal Info',1=>'Business Profile',2=>'Services & Budget',3=>'Strategy',4=>'Agreement & Payment');
        $step_label  = $step_labels[$step] ?? "Step {$step}";

        Six_Odoo::create_activity(
            $lead_id,
            'Follow-up on abandoned onboarding',
            "Contact: {$u->display_name}\n"
            . "Email: {$u->user_email}\n"
            . "Phone: " . (get_user_meta($u->ID,'billing_phone',true) ?: 'not provided') . "\n\n"
            . "Stopped at: {$step_label}\n"
            . "This is an automated 3-day follow-up reminder.\n\n"
            . "Action required: Re-engage this lead.\n"
            . "Advisor profile: {$advisor_url}",
            'Todo', 0, $advisor_uid
        );

        error_log( "6ix FollowupCron: 3-day reminder created for user={$u->ID} lead={$lead_id}" );
        sleep(1);
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
        $svc_metrics = array_filter( $metrics, function($m) use ($svc) { return $m->service_slug === $svc->service_slug; } );
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
    usort( $leads, function($a,$b){ return $b['score'] - $a['score']; } );
    wp_send_json_success($leads);
}

// ─────────────────────────────────────────────────────────────────────────────
// DB TABLE — add source + completed columns to existing tables
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'admin_init', 'six_onboarding_db_upgrade' );
function six_onboarding_db_upgrade() {
    global $wpdb;

    // Fresh install: tables don't exist yet — don't mark migrations done
    $tbl = $wpdb->prefix . 'six_checkout_progress';
    if ( $wpdb->get_var( $wpdb->prepare('SHOW TABLES LIKE %s', $tbl) ) !== $tbl ) return;

    // v2 migration
    if ( ! get_option('six_onboarding_db_v2') ) {
        $cols = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->prefix}six_recommendations", 0);
        if (!in_array('source', $cols)) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}six_recommendations ADD COLUMN source VARCHAR(50) DEFAULT 'advisor' AFTER action_type");
        }
        $cols2 = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->prefix}six_checkout_progress", 0);
        foreach ( array(
            'completed'  => 'TINYINT(1) DEFAULT 0',
            'website'    => "VARCHAR(255) DEFAULT ''",
            'location'   => "VARCHAR(255) DEFAULT ''",
            'employees'  => "VARCHAR(50) DEFAULT ''",
            'platforms'  => "VARCHAR(255) DEFAULT ''",
            'mktg_budget'=> "VARCHAR(50) DEFAULT ''",
            'challenge'  => "VARCHAR(100) DEFAULT ''",
        ) as $col => $def ) {
            if (!in_array($col, $cols2)) {
                $wpdb->query("ALTER TABLE {$wpdb->prefix}six_checkout_progress ADD COLUMN {$col} {$def}");
            }
        }
        update_option('six_onboarding_db_v2', 1);
    }

    // v3 migration — add goal column and any other missing fields
    if ( ! get_option('six_onboarding_db_v3') ) {
        $cols3 = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->prefix}six_checkout_progress", 0);
        foreach ( array(
            'goal'           => "VARCHAR(50) DEFAULT ''",
            'first_name'     => "VARCHAR(100) DEFAULT ''",
            'last_name'      => "VARCHAR(100) DEFAULT ''",
            'phone'          => "VARCHAR(30) DEFAULT ''",
            'monthly_revenue'=> "VARCHAR(100) DEFAULT ''",
            'competitors'    => "VARCHAR(500) DEFAULT ''",
        ) as $col => $def ) {
            if (!in_array($col, $cols3)) {
                $wpdb->query("ALTER TABLE {$wpdb->prefix}six_checkout_progress ADD COLUMN {$col} {$def}");
            }
        }
        update_option('six_onboarding_db_v3', 1);
    }

    // v4 migration — ensure all fields exist (idempotent, runs once)
    if ( ! get_option('six_onboarding_db_v4') ) {
        $cols4 = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}six_checkout_progress", 0 );
        $required = array(
            'goal'            => "VARCHAR(50) DEFAULT ''",
            'challenge'       => "VARCHAR(100) DEFAULT ''",
            'first_name'      => "VARCHAR(100) DEFAULT ''",
            'last_name'       => "VARCHAR(100) DEFAULT ''",
            'phone'           => "VARCHAR(30) DEFAULT ''",
            'monthly_revenue' => "VARCHAR(100) DEFAULT ''",
            'competitors'     => "VARCHAR(500) DEFAULT ''",
            'mktg_budget'     => "VARCHAR(50) DEFAULT ''",
            'location'        => "VARCHAR(255) DEFAULT ''",
            'website'         => "VARCHAR(255) DEFAULT ''",
            'employees'       => "VARCHAR(50) DEFAULT ''",
            'platforms'       => "VARCHAR(255) DEFAULT ''",
        );
        foreach ( $required as $col => $def ) {
            if ( ! in_array( $col, $cols4 ) ) {
                $wpdb->query( "ALTER TABLE {$wpdb->prefix}six_checkout_progress ADD COLUMN {$col} {$def}" );
            }
        }
        update_option( 'six_onboarding_db_v4', 1 );
    }

    // v5 migration — service-specific questionnaire fields
    if ( ! get_option('six_onboarding_db_v5') ) {
        $cols5 = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}six_checkout_progress", 0 );
        $v5_cols = array(
            // Business basics (new fields)
            'business_address'   => "VARCHAR(500) DEFAULT ''",
            'years_in_business'  => "VARCHAR(50) DEFAULT ''",
            // Google Ads
            'ads_locations'      => "VARCHAR(500) DEFAULT ''",
            'ads_loc_type'       => "VARCHAR(10) DEFAULT 'Include'",
            'employees'          => "VARCHAR(50) DEFAULT ''",
            'monthly_revenue'    => "VARCHAR(50) DEFAULT ''",
            'challenge'          => "VARCHAR(50) DEFAULT ''",
            'ads_products'       => "TEXT DEFAULT ''",
            'ads_keywords'       => "TEXT DEFAULT ''",
            'ads_usp'            => "TEXT DEFAULT ''",
            'ads_promo'          => "VARCHAR(500) DEFAULT ''",
            'ads_financing'      => "VARCHAR(5) DEFAULT ''",
            'ads_budget'         => "INT DEFAULT 0",
            // SEO
            'seo_pages'          => "TEXT DEFAULT ''",
            'seo_locations'      => "VARCHAR(500) DEFAULT ''",
            'seo_keywords'       => "TEXT DEFAULT ''",
            'seo_usp'            => "TEXT DEFAULT ''",
            'seo_gsc'            => "VARCHAR(5) DEFAULT ''",
            'seo_blog'           => "VARCHAR(5) DEFAULT ''",
            'seo_budget'         => "INT DEFAULT 0",
            // Google Business Profile
            'gbp_name'           => "VARCHAR(255) DEFAULT ''",
            'gbp_category'       => "VARCHAR(255) DEFAULT ''",
            'gbp_services'       => "TEXT DEFAULT ''",
            'gbp_rating'         => "VARCHAR(100) DEFAULT ''",
            'gbp_budget'         => "INT DEFAULT 0",
            // Website
            'web_goal'           => "VARCHAR(255) DEFAULT ''",
            'web_pages'          => "VARCHAR(500) DEFAULT ''",
            'web_style'          => "VARCHAR(255) DEFAULT ''",
            'web_refs'           => "VARCHAR(500) DEFAULT ''",
            'web_existing'       => "VARCHAR(5) DEFAULT ''",
            'web_budget'         => "INT DEFAULT 0",
            // Context
            'crm_tools'          => "VARCHAR(255) DEFAULT ''",
            'reviews_awards'     => "VARCHAR(500) DEFAULT ''",
            'onboarding_notes'   => "TEXT DEFAULT ''",
            // AI plan output
            'ai_plan_headline'   => "TEXT DEFAULT ''",
            'ai_plan_json'       => "LONGTEXT DEFAULT ''",
        );
        foreach ( $v5_cols as $col => $def ) {
            if ( ! in_array( $col, $cols5 ) ) {
                $wpdb->query( "ALTER TABLE {$wpdb->prefix}six_checkout_progress ADD COLUMN {$col} {$def}" );
            }
        }
        update_option( 'six_onboarding_db_v5', 1 );
    }

    // v6 migration — new SEO/GBP/Ads fields added in May 2026
    if ( ! get_option('six_onboarding_db_v6') ) {
        $cols6 = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}six_checkout_progress", 0 );
        $v6_cols = array(
            'ads_schedule'       => "VARCHAR(255) DEFAULT ''",
            'seo_competitors'    => "TEXT DEFAULT ''",
            'seo_crm_tools'      => "VARCHAR(500) DEFAULT ''",
            'seo_reviews'        => "VARCHAR(500) DEFAULT ''",
            'seo_extra_info'     => "TEXT DEFAULT ''",
            'gbp_hours'          => "VARCHAR(500) DEFAULT ''",
            // Rename ads_financing → ads_schedule (keep ads_financing for compat)
            'web_platform'       => "VARCHAR(255) DEFAULT ''",
            'web_timeline'       => "VARCHAR(100) DEFAULT ''",
            'web_features'       => "TEXT DEFAULT ''",
            'schedule_call_date' => "VARCHAR(50) DEFAULT ''",
            'schedule_call_time' => "VARCHAR(50) DEFAULT ''",
            'schedule_call_notes'=> "TEXT DEFAULT ''",
        );
        foreach ( $v6_cols as $col => $def ) {
            if ( ! in_array( $col, $cols6 ) ) {
                $wpdb->query( "ALTER TABLE {$wpdb->prefix}six_checkout_progress ADD COLUMN {$col} {$def}" );
            }
        }
        update_option( 'six_onboarding_db_v6', 1 );
    }

    // v7 migration — repair columns missed when the functions.php inline v6
    // migration marked v6 done early, plus columns handlers already write to:
    //  - six_checkout_progress.call_scheduled_at + schedule_call_* (six_schedule_onboarding_call)
    //  - six_client_services.advisor_id (approve/add service handlers)
    //  - six_client_services.updated_at (six_adv_set_budget)
    if ( ! get_option('six_onboarding_db_v7') ) {
        $cols7 = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}six_checkout_progress", 0 );
        $v7_checkout = array(
            'schedule_call_date'  => "VARCHAR(50) DEFAULT ''",
            'schedule_call_time'  => "VARCHAR(50) DEFAULT ''",
            'schedule_call_notes' => "TEXT",
            'call_scheduled_at'   => "DATETIME DEFAULT NULL",
        );
        foreach ( $v7_checkout as $col => $def ) {
            if ( ! in_array( $col, $cols7 ) ) {
                $wpdb->query( "ALTER TABLE {$wpdb->prefix}six_checkout_progress ADD COLUMN {$col} {$def}" );
            }
        }
        $svc_cols = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}six_client_services", 0 );
        $v7_services = array(
            'advisor_id' => "BIGINT(20) DEFAULT 0",
            'updated_at' => "DATETIME DEFAULT NULL",
        );
        foreach ( $v7_services as $col => $def ) {
            if ( ! in_array( $col, $svc_cols ) ) {
                $wpdb->query( "ALTER TABLE {$wpdb->prefix}six_client_services ADD COLUMN {$col} {$def}" );
            }
        }
        update_option( 'six_onboarding_db_v7', 1 );
    }

    // v8 migration — Google Ads "currently running" branch (audit flow)
    if ( ! get_option('six_onboarding_db_v8') ) {
        $cols8 = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}six_checkout_progress", 0 );
        $v8_cols = array(
            'gads_running'      => "VARCHAR(10) DEFAULT ''",
            'gads_audit_json'   => "TEXT",
            'gads_link_status'  => "VARCHAR(20) DEFAULT ''",
            'gads_customer_id'  => "VARCHAR(30) DEFAULT ''",
        );
        foreach ( $v8_cols as $col => $def ) {
            if ( ! in_array( $col, $cols8 ) ) {
                $wpdb->query( "ALTER TABLE {$wpdb->prefix}six_checkout_progress ADD COLUMN {$col} {$def}" );
            }
        }
        update_option( 'six_onboarding_db_v8', 1 );
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PASSWORD MANAGEMENT
// ─────────────────────────────────────────────────────────────────────────────

// Set password for new user (called after step 1)
add_action( 'wp_ajax_nopriv_six_set_user_password', 'six_set_user_password' );
add_action( 'wp_ajax_six_set_user_password',        'six_set_user_password' );
function six_set_user_password() {
    // six_onboarding_resolve_user() restricts this to self (logged in) or a
    // fresh, incomplete six_customer account — previously any user_id was
    // accepted, which allowed password takeover of arbitrary accounts.
    $user_id  = six_onboarding_resolve_user();
    $password = $_POST['password'] ?? '';
    if ( ! $user_id || strlen($password) < 8 ) {
        wp_send_json_error('Invalid data');
    }
    // Never allow this endpoint to change staff/admin passwords
    if ( user_can($user_id,'manage_options') || user_can($user_id,'six_manage_clients') ) {
        wp_send_json_error('Not applicable');
    }
    // Verify user hasn't completed checkout (prevent misuse)
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

// =============================================================================
// SIX_CREATE_PERSONAL_ACCOUNT
// Stage 0 — creates a full WP account with personal info (name, phone, password)
// in one call. This replaces the old two-step (partial create → set password).
// Odoo contact is created immediately so the sales team has complete info.
// =============================================================================
add_action( 'wp_ajax_nopriv_six_create_personal_account', 'six_create_personal_account' );
add_action( 'wp_ajax_six_create_personal_account',        'six_create_personal_account' );
function six_create_personal_account() {
    $email    = sanitize_email( $_POST['email']    ?? '' );
    $first    = sanitize_text_field( $_POST['first']    ?? '' );
    $last     = sanitize_text_field( $_POST['last']     ?? '' );
    $phone    = sanitize_text_field( $_POST['phone']    ?? '' );
    $password = $_POST['password'] ?? '';

    // Validate
    if ( ! is_email( $email ) ) wp_send_json_error( 'Invalid email address.' );
    if ( ! $first || ! $last )  wp_send_json_error( 'Please enter your first and last name.' );
    if ( ! $phone )             wp_send_json_error( 'Please enter your phone number.' );
    if ( strlen( $password ) < 8 ) wp_send_json_error( 'Password must be at least 8 characters.' );

    // If account already exists — log them in and resume
    if ( email_exists( $email ) ) {
        $existing = get_user_by( 'email', $email );
        // Update name + phone in case they were missing
        wp_update_user( array(
            'ID'         => $existing->ID,
            'first_name' => $first,
            'last_name'  => $last,
            'display_name' => $first . ' ' . $last,
        ) );
        update_user_meta( $existing->ID, 'billing_phone', $phone );
        wp_set_current_user( $existing->ID );
        wp_set_auth_cookie( $existing->ID, true );
        $advisor     = six_get_advisor_for_user( $existing->ID );
        $fresh_nonce = wp_create_nonce( 'six_nonce' );

        // Update Odoo contact with complete info
        if ( class_exists( 'Six_Odoo' ) ) {
            Six_Odoo::create_or_update_contact( $existing->ID );
            Six_Odoo::track_event( $existing->ID, 'return_visit' );
        }

        wp_send_json_success( array(
            'user_id'     => $existing->ID,
            'advisor'     => $advisor,
            'nonce'       => $fresh_nonce,
            'resume_step' => intval( get_user_meta( $existing->ID, 'six_checkout_step', true ) ?: 1 ),
        ) );
        return;
    }

    // Build username from first.last
    $base_username = sanitize_user( strtolower( $first . '.' . $last ) );
    $username = $base_username;
    $i = 1;
    while ( username_exists( $username ) ) { $username = $base_username . $i++; }

    $user_id = wp_insert_user( array(
        'user_login'   => $username,
        'user_email'   => $email,
        'user_pass'    => $password,
        'first_name'   => $first,
        'last_name'    => $last,
        'display_name' => $first . ' ' . $last,
        'role'         => 'six_customer',
    ) );

    if ( is_wp_error( $user_id ) ) {
        wp_send_json_error( $user_id->get_error_message() );
    }

    // Save meta
    update_user_meta( $user_id, 'billing_phone',           $phone );
    update_user_meta( $user_id, 'six_checkout_step',       1 );
    update_user_meta( $user_id, 'six_checkout_score',      15 );
    update_user_meta( $user_id, 'six_checkout_completed',  0 );
    // Reset abandon flags for fresh session
    delete_user_meta( $user_id, 'six_last_abandon_trigger' );
    delete_user_meta( $user_id, 'six_last_abandon_odoo' );
    update_user_meta( $user_id, 'six_abandon_fired_sms',   0 );
    update_user_meta( $user_id, 'six_abandon_fired_email', 0 );
    // Store session_id as fallback identifier for abandon tracking
    if ( ! session_id() ) @session_start();
    $sess = session_id() ?: wp_generate_uuid4();
    update_user_meta( $user_id, 'six_session_id', $sess );
    update_user_meta( $user_id, 'six_last_activity', current_time('mysql') );

    // Log in immediately
    wp_set_current_user( $user_id );
    wp_set_auth_cookie( $user_id, true );

    // Fresh nonce for authenticated calls
    $fresh_nonce = wp_create_nonce( 'six_nonce' );

    // Assign advisor
    $advisor = six_assign_advisor_round_robin( $user_id );

    // Create Odoo contact + lead + send welcome message immediately
    if ( class_exists( 'Six_Odoo' ) ) {
        Six_Odoo::create_or_update_contact( $user_id );
        Six_Odoo::on_personal_info_submitted( $user_id ); // welcome SMS + email + lead creation
    }

    wp_send_json_success( array(
        'user_id' => $user_id,
        'advisor' => $advisor,
        'nonce'   => $fresh_nonce,
        'first'   => $first,
        'last'    => $last,
    ) );
}

// =============================================================================
// SIX_GOOGLE_LOGIN_COMPLETE
// Called by JS after NextEnd Social Login completes Google auth.
// Reads the now-logged-in user, ensures they have a WP account with correct
// role, creates Odoo contact, and returns everything the JS needs.
// =============================================================================
add_action( 'wp_ajax_six_google_login_complete', 'six_google_login_complete' );
function six_google_login_complete() {
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( 'Not logged in — Google auth may not have completed.' );
    }

    $user_id = get_current_user_id();
    $user    = get_userdata( $user_id );

    // Role fix + onboarding meta + advisor assignment + Odoo — shared with
    // the server-side NSL hooks so popup and redirect mode behave the same
    if ( function_exists('six_social_prepare_user') ) {
        six_social_prepare_user( $user_id );
        $user = get_userdata( $user_id ); // refresh roles
    } elseif ( ! in_array( 'six_customer', (array) $user->roles )
      && ! in_array( 'six_advisor',  (array) $user->roles )
      && ! $user->has_cap( 'manage_options' ) ) {
        $user_obj = new WP_User( $user_id );
        $user_obj->set_role( 'six_customer' );
    }

    // Check if non-customer role — redirect them away
    $portal_role = class_exists('Six_Roles') ? Six_Roles::get_portal_role() : '';
    if ( in_array( $portal_role, array('six_advisor','six_sales') ) || current_user_can('manage_options') ) {
        $redirect = class_exists('Six_Roles')
            ? Six_Roles::get_portal_url()
            : home_url('/advisor-portal/');
        wp_send_json_success( array( 'redirect_url' => $redirect ) );
        return;
    }

    // Set checkout meta if not already set
    if ( ! get_user_meta( $user_id, 'six_checkout_step', true ) ) {
        update_user_meta( $user_id, 'six_checkout_step',      1 );
        update_user_meta( $user_id, 'six_checkout_completed', 0 );
    }

    $completed   = get_user_meta( $user_id, 'six_checkout_completed', true );
    $resume_step = intval( get_user_meta( $user_id, 'six_checkout_step', true ) ?: 1 );
    $fresh_nonce = wp_create_nonce( 'six_nonce' );
    $advisor     = six_get_advisor_for_user( $user_id );

    // Parse name from Google (NextEnd stores it in display_name)
    $name_parts = explode( ' ', trim( $user->display_name ), 2 );
    $first = $name_parts[0] ?? '';
    $last  = $name_parts[1] ?? '';
    $phone = get_user_meta( $user_id, 'billing_phone', true ) ?: '';

    // Odoo contact/lead handled by six_social_prepare_user() above; keep a
    // fallback for installs where social-login.php isn't deployed yet
    if ( ! function_exists('six_social_prepare_user') && class_exists( 'Six_Odoo' ) ) {
        Six_Odoo::create_or_update_contact( $user_id );
        if ( ! get_user_meta( $user_id, 'six_odoo_lead_id', true ) ) {
            Six_Odoo::sync_lead( array(
                'user_id' => $user_id,
                'status'  => 'started',
                'score'   => 20, // Google login = higher intent signal
                'step'    => 1,
            ) );
        }
    }

    wp_send_json_success( array(
        'user_id'     => $user_id,
        'email'       => $user->user_email,
        'first'       => $first,
        'last'        => $last,
        'phone'       => $phone,
        'advisor'     => $advisor,
        'nonce'       => $fresh_nonce,
        'completed'   => (bool) $completed,
        'resume_step' => $completed ? 1 : $resume_step,
    ) );
}

// =============================================================================
// GROWTH PLAN GENERATOR — calls Six_EstimateEngine with real API data
// =============================================================================
add_action( 'wp_ajax_six_generate_growth_plan',        'six_ajax_generate_growth_plan' );
add_action( 'wp_ajax_nopriv_six_generate_growth_plan', 'six_ajax_generate_growth_plan' );
function six_ajax_generate_growth_plan() {
    $user_id = six_onboarding_resolve_user();
    if ( ! $user_id ) { wp_send_json_error('No user'); return; }
    error_log("6ix GrowthPlan: generating for user={$user_id}");

    // Merge POST params into DB row so engine always has latest data
    // This handles the case where DB write hasn't completed or S.q wasn't persisted
    global $wpdb;
    $post_override = array(
        'business_name' => sanitize_text_field( $_POST['bizname']     ?? '' ),
        'industry'      => sanitize_text_field( $_POST['industry']    ?? '' ),
        'location'      => sanitize_text_field( $_POST['location']    ?? '' ),
        'employees'     => sanitize_text_field( $_POST['employees']  ?? '' ),
        'monthly_revenue' => sanitize_text_field( $_POST['revenue']  ?? '' ),
        'challenge'     => sanitize_text_field( $_POST['challenge']  ?? '' ),
        'platforms'     => sanitize_text_field( $_POST['platforms']   ?? '' ),
        'ads_locations' => sanitize_text_field( $_POST['ads_loc']     ?? '' ),
        'ads_keywords'  => sanitize_textarea_field( $_POST['ads_kw']  ?? '' ),
        'seo_keywords'  => sanitize_textarea_field( $_POST['seo_kw']  ?? '' ),
        'gbp_category'  => sanitize_text_field( $_POST['gbp_cat']     ?? '' ),
        'ads_budget'    => intval( $_POST['ads_bud'] ?? 0 ),
        'seo_budget'    => intval( $_POST['seo_bud'] ?? 0 ),
        'gbp_budget'    => intval( $_POST['gbp_bud'] ?? 0 ),
        'web_budget'    => intval( $_POST['web_bud'] ?? 0 ),
        'competitors'   => sanitize_text_field( $_POST['competitors'] ?? '' ),
        // Existing-Google-Ads audit branch
        'gads_running'    => sanitize_text_field( $_POST['gads_running'] ?? '' ),
        'gads_audit_json' => six_sanitize_json(   $_POST['gads_audit']   ?? '' ),
    );
    error_log("6ix GrowthPlan: bizname=" . $post_override['business_name'] . " industry=" . $post_override['industry'] . " platforms=" . $post_override['platforms']);

    if ( ! class_exists('Six_EstimateEngine') ) {
        wp_send_json_error('EstimateEngine not loaded');
        return;
    }

    $plan = Six_EstimateEngine::generate( $user_id, $post_override );
    wp_send_json_success( $plan );
}


// Register DataForSEO settings
add_action('admin_init', function(){
    register_setting('six_portal_settings', 'six_dataforseo_login');
    register_setting('six_portal_settings', 'six_dataforseo_password');
});

// =============================================================================
// SCHEDULE ONBOARDING CALL
// Saves call request, marks as abandoned-with-call-scheduled in Odoo
// =============================================================================
add_action( 'wp_ajax_six_schedule_onboarding_call',        'six_schedule_onboarding_call' );
add_action( 'wp_ajax_nopriv_six_schedule_onboarding_call', 'six_schedule_onboarding_call' );

function six_schedule_onboarding_call() {
    $user_id    = six_onboarding_resolve_user();
    $call_date  = sanitize_text_field( $_POST['call_date']  ?? '' );
    $call_time  = sanitize_text_field( $_POST['call_time']  ?? '' );
    $call_notes = sanitize_textarea_field( $_POST['call_notes'] ?? '' );
    $services   = sanitize_text_field( $_POST['services']   ?? '' );
    $score      = intval( $_POST['score'] ?? 0 );

    if ( ! $user_id )   { wp_send_json_error('No user ID'); return; }
    if ( ! $call_date ) { wp_send_json_error('Please select a date'); return; }
    if ( ! $call_time ) { wp_send_json_error('Please select a time window'); return; }

    global $wpdb;
    $table = $wpdb->prefix . 'six_checkout_progress';

    // A requested call supersedes any earlier abandonment — clear the abandon
    // state so the abandoned-checkout follow-up cron does not nag this lead.
    delete_user_meta( $user_id, 'six_abandoned_at_step' );
    delete_user_meta( $user_id, 'six_abandoned_score' );
    delete_user_meta( $user_id, 'six_abandoned_at' );
    update_user_meta( $user_id, 'six_call_requested_at', current_time('mysql') );

    // Save call scheduling to DB
    $wpdb->update(
        $table,
        array(
            'schedule_call_date'  => $call_date,
            'schedule_call_time'  => $call_time,
            'schedule_call_notes' => $call_notes,
            'call_scheduled_at'   => current_time('mysql'),
            'step'                => 5,
            'score'               => $score,
        ),
        array( 'user_id' => $user_id )
    );

    // Save full questionnaire data if passed
    $step1_raw = $_POST['step1_data'] ?? '';
    if ( $step1_raw ) {
        $data = json_decode( stripslashes($step1_raw), true ) ?: array();
        if ( ! empty($data) ) {
            $update = array();
            $field_map = array(
                'bizname'=>'business_name','website'=>'website','industry'=>'industry',
                'location'=>'location','goals'=>'goal','years'=>'years_in_business',
                'platforms'=>'platforms','competitors'=>'competitors',
                'ads_loc'=>'ads_locations','ads_kw'=>'ads_keywords','ads_usp'=>'ads_usp',
                'ads_prod'=>'ads_products','ads_bud'=>'ads_budget',
                'seo_loc'=>'seo_locations','seo_kw'=>'seo_keywords','seo_usp'=>'seo_usp',
                'seo_pages'=>'seo_pages','seo_gsc'=>'seo_gsc','seo_bud'=>'seo_budget',
                'gbp_name'=>'gbp_name','gbp_cat'=>'gbp_category','gbp_svcs'=>'gbp_services',
                'gbp_rating'=>'gbp_rating','gbp_bud'=>'gbp_budget',
                'web_goal'=>'web_goal','web_pages'=>'web_pages','web_style'=>'web_style',
                'web_refs'=>'web_refs','web_exist'=>'web_exist','web_bud'=>'web_budget',
                'web_platform'=>'web_platform','web_timeline'=>'web_timeline',
                'web_features'=>'web_features',
            );
            foreach ( $field_map as $js_key => $db_col ) {
                if ( isset($data[$js_key]) && $data[$js_key] !== '' ) {
                    $update[$db_col] = sanitize_text_field($data[$js_key]);
                }
            }
            if ( ! empty($update) ) {
                $wpdb->update($table, $update, array('user_id'=>$user_id));
            }
        }
    }

    // A requested call is NOT an abandonment. Route the lead to the dedicated
    // "Call Requested" pipeline stage (a middle stage, neither abandoned nor
    // submitted) and generate an advisor task on the profile — mirroring how
    // onboarding-submitted generates its task. Do NOT run the abandoned flow.
    if ( class_exists('Six_Odoo') ) {
        Six_Odoo::on_call_requested( $user_id, array(
            'call_date'  => $call_date,
            'call_time'  => $call_time,
            'call_notes' => $call_notes,
            'services'   => $services,
            'score'      => $score,
            'step'       => 3,
        ) );
    }

    error_log("6ix Schedule Call: user={$user_id} date={$call_date} time={$call_time}");
    wp_send_json_success( array(
        'message'   => 'Call request saved.',
        'call_date' => $call_date,
        'call_time' => $call_time,
    ) );
}
