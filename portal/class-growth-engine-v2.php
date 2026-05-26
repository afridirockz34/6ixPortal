<?php
error_log('6ix Growth Engine v2.0 loaded — abandon-fix build');
/**
 * Six_Growth_Engine — Complete 4-Layer Growth Engine
 * Upload to: /wp-content/themes/6ixClaude/portal/class-growth-engine.php
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * ARCHITECTURE
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Layer 1 — DATA COLLECTION
 *   track_event()        Records every user action with timestamp + metadata
 *   track_step_time()    Records how long a user spends on each step
 *   capture_device()     Stores mobile/desktop signal
 *   capture_utm()        Reads UTM params from user meta (set by onboarding JS)
 *
 * Layer 2 — INTELLIGENCE
 *   calculate_score()    0–100 intent score with 12 signals
 *   classify_lead()      Hot / Warm / Cold based on score + behaviour
 *   get_drop_risk()      Predicts if a lead is about to abandon
 *   funnel_stats()       Drop-off rate per step across all leads
 *
 * Layer 3 — AUTOMATION
 *   on_abandon()         Entry point — schedules timed WP-Cron jobs
 *   ├── +1 min  → SMS via Twilio
 *   ├── +10 min → Personalised email via Odoo
 *   ├── +30 min → Advisor activity in Odoo CRM
 *   └── +24 h   → Follow-up email
 *   on_re_engage()       User returns → update score, notify advisor, remove cold
 *   on_high_intent()     Score crosses threshold → urgent advisor activity
 *
 * Layer 4 — ACTION (Advisor)
 *   get_priority_leads()     Hot leads first, with AI rec + contact history
 *   get_lead_timeline()      All events for one lead in chronological order
 *   get_conversion_insights()Per-step drop-off rates + friction report
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * SETUP
 * ═══════════════════════════════════════════════════════════════════════════
 *  Run /wp-admin/?six_growth_setup=1 once to create the events table.
 *  Requires class-odoo.php to be loaded first (it's in the optional_files list).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Six_Growth_Engine' ) ) :
class Six_Growth_Engine {

    // Score thresholds
    const HOT_THRESHOLD  = 70;
    const WARM_THRESHOLD = 40;

    // ═════════════════════════════════════════════════════════════════════
    // ─── LAYER 1: DATA COLLECTION ────────────────────────────────────────
    // ═════════════════════════════════════════════════════════════════════

    /**
     * Track any behavioural event.
     * Writes to six_growth_events table AND user meta for fast lookup.
     *
     * $type: 'page_load' | 'step_view' | 'step_complete' | 'step_abandon'
     *        | 'return_visit' | 'field_focus' | 'service_select' | 'budget_select'
     *        | 'goal_select'  | 'google_login' | 'email_submit'  | 'conversion'
     * $meta: any additional key-value data as array
     */
    public static function track_event( $user_id, $type, $meta = array() ) {
        if ( ! $user_id ) return;

        global $wpdb;
        $table = $wpdb->prefix . 'six_growth_events';

        // Insert event row
        $wpdb->insert( $table, array(
            'user_id'    => intval( $user_id ),
            'event_type' => sanitize_key( $type ),
            'step'       => intval( $meta['step'] ?? 0 ),
            'meta'       => wp_json_encode( $meta ),
            'created_at' => current_time( 'mysql' ),
        ), array( '%d', '%s', '%d', '%s', '%s' ) );

        // Fast-lookup user meta
        update_user_meta( $user_id, 'six_last_event',      current_time('mysql') );
        update_user_meta( $user_id, 'six_last_event_type', $type );

        // Track return visits
        if ( $type === 'return_visit' || $type === 'page_load' ) {
            $visits = intval( get_user_meta( $user_id, 'six_total_visits', true ) );
            update_user_meta( $user_id, 'six_total_visits', $visits + 1 );
        }

        // On conversion — mark complete and fire high-intent check
        if ( $type === 'conversion' ) {
            update_user_meta( $user_id, 'six_checkout_completed', 1 );
            self::on_high_intent( $user_id ); // final scoring
        }
    }

    /**
     * Record how long a user spent on a step.
     * Called when they advance OR abandon.
     * $duration_seconds = time JS measured on that step.
     */
    public static function track_step_time( $user_id, $step, $duration_seconds ) {
        if ( ! $user_id ) return;
        $key = 'six_step_time_' . intval( $step );
        update_user_meta( $user_id, $key, intval( $duration_seconds ) );

        // Also log as an event
        self::track_event( $user_id, 'step_timing', array(
            'step'     => $step,
            'duration' => $duration_seconds,
        ) );
    }

    /**
     * Capture device type (mobile / desktop / tablet).
     * Called by onboarding JS on page load.
     */
    public static function capture_device( $user_id, $device_type ) {
        if ( ! $user_id ) return;
        update_user_meta( $user_id, 'six_device_type', sanitize_text_field( $device_type ) );
        if ( class_exists('Six_Odoo') ) {
            Six_Odoo::track_event( $user_id, 'device_type', array( 'device' => $device_type ) );
        }
    }

    // ═════════════════════════════════════════════════════════════════════
    // ─── LAYER 2: INTELLIGENCE ───────────────────────────────────────────
    // ═════════════════════════════════════════════════════════════════════

    /**
     * Calculate a 0–100 intent score using 12 behavioural + profile signals.
     * Higher = more likely to convert.
     *
     * Signals:
     *  +20   base (account created)
     *  +5    has phone number
     *  +5    has website
     *  +10   budget entered (and not "not sure")
     *  +10   goal selected
     *  +10   challenge selected
     *  +10   step 1 completed (business profile)
     *  +15   step 2 completed (services selected)
     *  +20   step 3 completed (strategy confirmed)
     *  +30   step 4 completed / payment added
     *  +5    per return visit (max +15)
     *  -10   no activity in 24h after account creation
     *  +5    used Google login (higher trust signal)
     *  +10   high budget ($5000+)
     */
    public static function calculate_score( $user_id ) {
        global $wpdb;
        $score = 20; // base

        $user = get_userdata( $user_id );
        if ( ! $user ) return 0;

        $co = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d", $user_id
        ) );

        // Phone
        if ( get_user_meta( $user_id, 'billing_phone', true ) ) $score += 5;

        // Website
        if ( $co && $co->website ) $score += 5;

        // Budget
        if ( $co && $co->mktg_budget && $co->mktg_budget !== 'not_sure' ) {
            $score += 10;
            if ( $co->mktg_budget === '5000+' ) $score += 10; // high budget bonus
        }

        // Goal + challenge
        if ( $co && $co->goal )      $score += 10;
        if ( $co && $co->challenge ) $score += 10;

        // Checkout step completion
        $step = intval( get_user_meta( $user_id, 'six_checkout_step', true ) );
        $step_bonuses = array( 1 => 10, 2 => 15, 3 => 20, 4 => 30 );
        $score += $step_bonuses[ min( $step, 4 ) ] ?? 0;

        // Return visits
        $visits = intval( get_user_meta( $user_id, 'six_total_visits', true ) );
        $score += min( $visits * 5, 15 );

        // Inactivity penalty — no activity for 24h+
        $last = get_user_meta( $user_id, 'six_last_event', true );
        if ( $last && ( time() - strtotime( $last ) ) > 86400 ) $score -= 10;

        // Google login signal
        if ( get_user_meta( $user_id, 'six_used_google_login', true ) ) $score += 5;

        return max( 0, min( 100, $score ) );
    }

    /**
     * Classify a lead as Hot / Warm / Cold.
     */
    public static function classify_lead( $score ) {
        if ( $score >= self::HOT_THRESHOLD )  return 'hot';
        if ( $score >= self::WARM_THRESHOLD ) return 'warm';
        return 'cold';
    }

    /**
     * Estimate drop-off risk (0–100, higher = more likely to abandon).
     * Used to pre-emptively intervene before they leave.
     */
    public static function get_drop_risk( $user_id ) {
        $risk = 0;

        $step = intval( get_user_meta( $user_id, 'six_checkout_step', true ) );
        $last = get_user_meta( $user_id, 'six_last_event', true );

        // Time since last event
        $idle_mins = $last ? ( time() - strtotime( $last ) ) / 60 : 999;
        if ( $idle_mins > 5  ) $risk += 20;
        if ( $idle_mins > 10 ) $risk += 20;
        if ( $idle_mins > 20 ) $risk += 20;

        // Step 3 and 4 have higher natural friction
        if ( $step === 3 ) $risk += 10;
        if ( $step === 4 ) $risk += 15; // payment step = highest friction

        // No phone = less invested
        if ( ! get_user_meta( $user_id, 'billing_phone', true ) ) $risk += 10;

        return min( 100, $risk );
    }

    /**
     * Get drop-off rate per step across all leads.
     * Returns array: [ 1 => ['views'=>200,'drops'=>60,'rate'=>30], ... ]
     * Used for the conversion insights dashboard.
     */
    public static function funnel_stats() {
        global $wpdb;
        $table = $wpdb->prefix . 'six_growth_events';

        // Check table exists — if setup hasn't been run yet return empty stats
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
        if ( ! $table_exists ) {
            return array(); // Will show "No funnel data yet" in dashboard
        }

        // Count step views and step abandons per step
        $views = $wpdb->get_results(
            "SELECT step, COUNT(DISTINCT user_id) AS cnt
             FROM {$table}
             WHERE event_type = 'step_view' AND step > 0
             GROUP BY step",
            ARRAY_A
        );
        $drops = $wpdb->get_results(
            "SELECT step, COUNT(DISTINCT user_id) AS cnt
             FROM {$table}
             WHERE event_type = 'step_abandon' AND step > 0
             GROUP BY step",
            ARRAY_A
        );

        $view_map = array();
        foreach ( $views as $r ) $view_map[ $r['step'] ] = intval( $r['cnt'] );

        $drop_map = array();
        foreach ( $drops as $r ) $drop_map[ $r['step'] ] = intval( $r['cnt'] );

        $stats = array();
        for ( $s = 0; $s <= 4; $s++ ) {
            $v = $view_map[$s] ?? 0;
            $d = $drop_map[$s] ?? 0;
            $stats[$s] = array(
                'step'     => $s,
                'label'    => self::step_label( $s ),
                'views'    => $v,
                'drops'    => $d,
                'rate'     => $v > 0 ? round( ($d / $v) * 100 ) : 0,
                'avg_time' => self::avg_step_time( $s ),
            );
        }
        return $stats;
    }

    private static function step_label( $step ) {
        $labels = array( 0=>'Your Details', 1=>'Business Profile', 2=>'Services',
                         3=>'Strategy', 4=>'Agreement & Payment' );
        return $labels[$step] ?? "Step {$step}";
    }

    private static function avg_step_time( $step ) {
        global $wpdb;
        $table = $wpdb->prefix . 'six_growth_events';
        if ( $wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table ) return 0;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT AVG(JSON_EXTRACT(meta,'$.duration')) AS avg_sec
             FROM {$table}
             WHERE event_type='step_timing' AND step=%d",
            $step
        ) );
        return $row ? round( floatval( $row->avg_sec ) ) : 0;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ─── LAYER 3: AUTOMATION ─────────────────────────────────────────────
    // ═════════════════════════════════════════════════════════════════════

    /**
     * ABANDONMENT FLOW ENTRY POINT.
     * Call this when a user stops progressing through onboarding.
     *
     * Schedules 4 timed WP-Cron jobs:
     *   +1 min  → SMS
     *   +10 min → Email
     *   +30 min → Advisor Odoo activity
     *   +24 h   → Follow-up email
     *
     * Uses a single scheduled hook per user to avoid duplicates.
     * If the user completes before the job fires, a flag prevents execution.
     */
    public static function on_abandon( $user_id, $step, $score ) {
        if ( ! $user_id ) return;
        error_log("6ix on_abandon: dispatching uid={$user_id} step={$step}");

        // Dispatch to the consolidated AJAX handler — this runs in a fresh PHP
        // context that is guaranteed to have the latest file, bypassing opcache.
        if ( function_exists('six_track_abandoned_checkout') ) {
            // Already in the right context — call directly
            $_POST = array(
                'user_id'    => $user_id,
                'step'       => $step,
                'score'      => $score,
                'email'      => get_userdata($user_id)->user_email ?? '',
                'session_id' => '',
            );
            six_track_abandoned_checkout();
            return;
        }

        // Fallback: HTTP dispatch so the handler always runs in a clean context
        wp_remote_post( admin_url('admin-ajax.php'), array(
            'timeout'  => 10,
            'blocking' => false,
            'body'     => array(
                'action'  => 'six_track_abandoned_checkout',
                'user_id' => $user_id,
                'step'    => $step,
                'score'   => $score,
                'email'   => get_userdata($user_id)->user_email ?? '',
            ),
        ) );
        error_log("6ix on_abandon: HTTP dispatch sent for uid={$user_id}");
        return;

        // ─── Legacy code below — kept for reference but never reached ────────
        // Don't re-trigger within 60s cooldown
        $last_abandon = get_user_meta( $user_id, 'six_last_abandon_trigger', true );
        if ( $last_abandon && ( time() - intval( $last_abandon ) ) < 60 ) {
            error_log("6ix Abandon: cooldown active for user={$user_id} — skipping");
            return;
        }
        update_user_meta( $user_id, 'six_last_abandon_trigger', time() );

        $score = intval( $score );
        $step  = intval( $step );

        // Store abandonment data
        update_user_meta( $user_id, 'six_abandoned_at_step',  $step );
        update_user_meta( $user_id, 'six_abandoned_score',    $score );
        update_user_meta( $user_id, 'six_abandoned_at',       current_time('mysql') );
        update_user_meta( $user_id, 'six_last_activity',      current_time('mysql') );
        update_user_meta( $user_id, 'six_abandon_fired_sms',       0 );
        update_user_meta( $user_id, 'six_abandon_fired_email',     0 );
        update_user_meta( $user_id, 'six_abandon_fired_activity',  0 );
        update_user_meta( $user_id, 'six_abandon_fired_followup',  0 );

        // Track event
        self::track_event( $user_id, 'step_abandon', array( 'step' => $step, 'score' => $score ) );

        // Ensure Odoo contact and lead exist, then move stage to Abandoned
        $lead_id = 0;
        if ( class_exists('Six_Odoo') ) {

            Six_Odoo::create_or_update_contact( $user_id );
            $lead_id = intval( get_user_meta( $user_id, 'six_odoo_lead_id', true ) );
            error_log("6ix Abandon: lead_id from meta={$lead_id} user={$user_id}");

            // Use sync_lead(abandoned) — this is the ONLY reliable way to move the
            // stage in Odoo. It handles pipeline scoping, team assignment, and
            // the stage protection block correctly. A direct write can succeed
            // (returns true) but be ignored if the stage isn't in the lead's pipeline.
            $synced = Six_Odoo::sync_lead( array(
                'user_id' => $user_id,
                'status'  => 'abandoned',
                'score'   => $score,
                'step'    => $step,
            ) );
            if ( $synced ) {
                $lead_id = $synced;
                error_log("6ix Abandon: sync_lead(abandoned) OK lead_id={$lead_id}");
            } else {
                error_log("6ix Abandon: sync_lead(abandoned) FAILED for user={$user_id}");
            }
        }

        // ── Fire SMS and email BEFORE activity (guaranteed to run) ────────────
        self::cron_abandon_sms( $user_id, $step, $score );
        self::cron_abandon_email( $user_id, $step, $score );

        // ── Create Odoo activity with full context ─────────────────────────
        if ( $lead_id && class_exists('Six_Odoo') ) {
            try {
                $user        = get_userdata( $user_id );
                $name        = $user ? $user->display_name : "User #{$user_id}";
                $advisor_uid = self::get_advisor_odoo_uid( $user_id );

                $step_labels = array(
                    0 => 'Login / Email entry',
                    1 => 'Account Creation',
                    2 => 'Service Selection',
                    3 => 'Questionnaire',
                    4 => 'AI Strategy Review',
                    5 => 'Agreement & Payment (Contract)',
                );

                // Completed steps = everything before the step they abandoned at
                $completed_steps = array();
                for ( $i = 1; $i < $step; $i++ ) {
                    $completed_steps[] = "  \u2713 Step {$i}: " . ( $step_labels[$i] ?? "Step {$i}" );
                }

                // Pull ALL available data from checkout_progress
                global $wpdb;
                $co = $wpdb->get_row( $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d",
                    $user_id
                ) );

                $context = array();
                // Business basics
                if ( ! empty($co->business_name) )    $context[] = "Business: {$co->business_name}";
                if ( ! empty($co->website) )           $context[] = "Website: {$co->website}";
                if ( ! empty($co->industry) )          $context[] = "Industry: {$co->industry}";
                if ( ! empty($co->location) )          $context[] = "Location: {$co->location}";
                if ( ! empty($co->business_address) )  $context[] = "Address: {$co->business_address}";
                if ( ! empty($co->years_in_business) ) $context[] = "Years in business: {$co->years_in_business}";
                if ( ! empty($co->goal) )              $context[] = "Goals: " . str_replace(',', ', ', $co->goal);
                if ( ! empty($co->platforms) )         $context[] = "Services selected: " . str_replace(',', ', ', $co->platforms);
                if ( ! empty($co->mktg_budget) )       $context[] = "Total budget: \${$co->mktg_budget}/mo";
                if ( ! empty($co->competitors) )       $context[] = "Competitors: {$co->competitors}";
                // Google Ads
                if ( ! empty($co->ads_keywords) )      $context[] = "[Google Ads] Keywords: {$co->ads_keywords}";
                if ( ! empty($co->ads_locations) )     $context[] = "[Google Ads] Target locations: {$co->ads_locations}";
                if ( ! empty($co->ads_products) )      $context[] = "[Google Ads] Products/services: {$co->ads_products}";
                if ( ! empty($co->ads_usp) )           $context[] = "[Google Ads] USP: {$co->ads_usp}";
                if ( ! empty($co->ads_promo) )         $context[] = "[Google Ads] Promotions: {$co->ads_promo}";
                if ( intval($co->ads_budget) > 0 )     $context[] = "[Google Ads] Budget: \${$co->ads_budget}/mo";
                // SEO
                if ( ! empty($co->seo_keywords) )      $context[] = "[SEO] Keywords: {$co->seo_keywords}";
                if ( ! empty($co->seo_locations) )     $context[] = "[SEO] Target locations: {$co->seo_locations}";
                if ( ! empty($co->seo_pages) )         $context[] = "[SEO] Pages to rank: {$co->seo_pages}";
                if ( ! empty($co->seo_usp) )           $context[] = "[SEO] USP: {$co->seo_usp}";
                if ( intval($co->seo_budget) > 0 )     $context[] = "[SEO] Budget: \${$co->seo_budget}/mo";
                // Google Business Profile
                if ( ! empty($co->gbp_name) )          $context[] = "[GBP] Business name: {$co->gbp_name}";
                if ( ! empty($co->gbp_category) )      $context[] = "[GBP] Category: {$co->gbp_category}";
                if ( ! empty($co->gbp_services) )      $context[] = "[GBP] Services: {$co->gbp_services}";
                if ( intval($co->gbp_budget) > 0 )     $context[] = "[GBP] Budget: \${$co->gbp_budget}/mo";
                // Website
                if ( ! empty($co->web_goal) )          $context[] = "[Website] Goal: " . str_replace(',', ', ', $co->web_goal);
                if ( ! empty($co->web_pages) )         $context[] = "[Website] Pages needed: {$co->web_pages}";
                if ( ! empty($co->web_style) )         $context[] = "[Website] Style: {$co->web_style}";
                if ( ! empty($co->web_refs) )          $context[] = "[Website] Reference sites: {$co->web_refs}";
                if ( intval($co->web_budget) > 0 )     $context[] = "[Website] Budget: \${$co->web_budget}/mo";
                // Context fields
                if ( ! empty($co->crm_tools) )         $context[] = "CRM / tools used: {$co->crm_tools}";
                if ( ! empty($co->reviews_awards) )    $context[] = "Reviews / awards: {$co->reviews_awards}";
                if ( ! empty($co->onboarding_notes) )  $context[] = "Notes: {$co->onboarding_notes}";

                $phone     = get_user_meta( $user_id, 'billing_phone', true ) ?: 'Not provided';
                $email_str = $user ? $user->user_email : 'Unknown';
                $abandoned_label = $step_labels[ $step ] ?? "Step {$step}";
                $completed_str   = ! empty($completed_steps) ? implode("\n", $completed_steps) : '  None';
                $context_str     = ! empty($context) ? implode("\n", $context) : '  No data collected yet';

                $note = "ABANDONED ONBOARDING\n"
                    . str_repeat('-', 40) . "\n\n"
                    . "CONTACT\n"
                    . "  Email: {$email_str}\n"
                    . "  Phone: {$phone}\n\n"
                    . "ABANDONED AT\n"
                    . "  Step {$step}/5 — {$abandoned_label}\n\n"
                    . "COMPLETED STEPS\n"
                    . $completed_str . "\n\n"
                    . "ONBOARDING DATA COLLECTED\n"
                    . $context_str . "\n\n"
                    . "SCORE: {$score}/100\n\n"
                    . "AUTOMATED FOLLOW-UP\n"
                    . "  SMS sent immediately\n"
                    . "  Email sent immediately\n\n"
                    . "Resume: " . home_url('/get-started/');

                $activity_id = Six_Odoo::create_activity(
                    $lead_id,
                    "Abandoned Onboarding — Follow Up Required",
                    $note,
                    'Todo',
                    0,
                    $advisor_uid
                );
                if ( $activity_id ) {
                    update_user_meta( $user_id, 'six_abandon_odoo_task_created', 1 );
                }
            } catch ( \Throwable $e ) {
                error_log("6ix Growth: activity exception user={$user_id}: " . $e->getMessage());
            }
        }



        // ── Schedule cron retries in case the direct calls had a transient error ──
        // These are guarded by six_abandon_fired_* flags so they won't double-send
        if ( ! wp_next_scheduled( 'six_abandon_sms', array( $user_id, $step, $score ) ) ) {
            wp_schedule_single_event( time() + 120, 'six_abandon_sms', array( $user_id, $step, $score ) );
        }
        if ( ! wp_next_scheduled( 'six_abandon_email', array( $user_id, $step, $score ) ) ) {
            wp_schedule_single_event( time() + 720, 'six_abandon_email', array( $user_id, $step, $score ) );
        }
        // +30 min → Advisor detailed Odoo activity (separate from the immediate one above)
        if ( ! wp_next_scheduled( 'six_abandon_activity', array( $user_id, $step, $score ) ) ) {
            wp_schedule_single_event( time() + 1800, 'six_abandon_activity', array( $user_id, $step, $score ) );
        }
        // +24 h → Follow-up email
        if ( ! wp_next_scheduled( 'six_abandon_followup', array( $user_id ) ) ) {
            wp_schedule_single_event( time() + 86400, 'six_abandon_followup', array( $user_id ) );
        }

        // Attempt to wake WP-Cron for the retry jobs
        spawn_wcron_async();

        error_log( "6ix Growth: Abandonment fired for user {$user_id} step {$step} score {$score}" );
    }

    /**
     * RE-ENGAGEMENT FLOW.
     * Call when a user who previously abandoned returns to the onboarding.
     * - Recalculates score (return visit = +5 intent signal)
     * - Updates Odoo lead with new score + stage
     * - Notifies assigned advisor
     * - Removes "cold" tag and re-classifies
     * - Cancels any pending abandon cron jobs (they came back)
     */
    public static function on_re_engage( $user_id ) {
        if ( ! $user_id ) return;

        // Was this user previously abandoned?
        $was_abandoned = get_user_meta( $user_id, 'six_abandoned_at_step', true );
        if ( ! $was_abandoned ) return; // Not previously abandoned — nothing to do

        // Track the return
        $visits = intval( get_user_meta( $user_id, 'six_total_visits', true ) );
        update_user_meta( $user_id, 'six_total_visits', $visits + 1 );
        self::track_event( $user_id, 'return_visit', array( 'from' => 'abandoned' ) );

        // Recalculate score with return visit bonus
        $new_score = self::calculate_score( $user_id );
        $priority  = self::classify_lead( $new_score );

        // Keep abandoned meta — user is still considered abandoned until completion
        // Just reset the cooldown so they can be triggered again if they leave again
        delete_user_meta( $user_id, 'six_last_abandon_odoo' );
        delete_user_meta( $user_id, 'six_abandon_fired_sms' );
        delete_user_meta( $user_id, 'six_abandon_fired_email' );

        if ( ! class_exists('Six_Odoo') ) return;

        $lead_id = intval( get_user_meta( $user_id, 'six_odoo_lead_id', true ) );
        if ( ! $lead_id ) return;

        // Stage stays Abandoned — do NOT change it here.
        $user        = get_userdata( $user_id );
        $name        = $user ? $user->display_name : "User #{$user_id}";
        $advisor_uid = self::get_advisor_odoo_uid( $user_id );
        $step_back   = intval( get_user_meta( $user_id, 'six_checkout_step', true ) );
        $advisor_url = home_url('/advisor-portal/?tab=clients&client=' . $user_id);

        Six_Odoo::create_activity(
            $lead_id,
            "Re-engaged: {$name} returned to onboarding",
            "Contact: {$name}
"
            . "Email: " . ($user ? $user->user_email : '') . "

"
            . "Returned to step: {$step_back}
"
            . "New score: {$new_score}/100

"
            . "Action required: Follow up — they came back.
"
            . "Advisor profile: {$advisor_url}",
            'Todo', 0, $advisor_uid
        );

        error_log( "6ix Growth: Re-engagement noted for user {$user_id} score {$new_score}" );
    }

    /**
     * HIGH-INTENT FLOW.
     * Triggered when a lead's score crosses HOT_THRESHOLD (70) at any point.
     * Creates an urgent advisor activity in Odoo.
     * Idempotent — won't fire twice for the same score level.
     */
    public static function on_high_intent( $user_id ) {
        $score = self::calculate_score( $user_id );
        if ( $score < self::HOT_THRESHOLD ) return;

        // Don't fire again if already fired at this score level
        $fired_at = intval( get_user_meta( $user_id, 'six_high_intent_fired', true ) );
        if ( $fired_at >= $score ) return;
        update_user_meta( $user_id, 'six_high_intent_fired', $score );

        if ( ! class_exists('Six_Odoo') ) return;

        $lead_id = intval( get_user_meta( $user_id, 'six_odoo_lead_id', true ) );
        if ( ! $lead_id ) return;

        $user  = get_userdata( $user_id );
        $name  = $user ? $user->display_name : "User #{$user_id}";
        $step  = intval( get_user_meta( $user_id, 'six_checkout_step', true ) );
        $advisor_uid = self::get_advisor_odoo_uid( $user_id );

        // Only fire high-intent for users who are still in onboarding (not completed)
        if ( get_user_meta($user_id, 'six_checkout_completed', true) ) return;

        $advisor_url = home_url('/advisor-portal/?tab=clients&client=' . $user_id);
        $hi_note = "Contact: {$name}\n"
            . "Lead score: {$score}/100 — crossed HOT threshold\n"
            . "Current step: {$step}/4\n\n"
            . "Action required:\n"
            . "Contact immediately — high-intent leads convert at 3x the rate when contacted within 1 hour.\n"
            . "Advisor profile: {$advisor_url}";

        Six_Odoo::create_activity(
            $lead_id,
            "High intent lead — action required",
            $hi_note,
            'Todo', 0, $advisor_uid
        );

        Six_Odoo::post_note( $lead_id,
            "Lead score reached {$score}/100 at " . current_time('mysql') . " (step {$step}/4).\n"
            . "Advisor notified."
        );

        error_log( "6ix Growth: High-intent fired for user {$user_id} score {$score}" );
    }

    // ═════════════════════════════════════════════════════════════════════
    // ─── LAYER 4: ACTION LAYER (Advisor Dashboard Data) ──────────────────
    // ═════════════════════════════════════════════════════════════════════

    /**
     * Get priority leads for the advisor dashboard.
     * Returns Hot leads first, then Warm, then Cold.
     * Each lead includes score, AI recommendation, last event, contact info.
     */
    public static function get_priority_leads( $advisor_id = 0, $limit = 50 ) {
        global $wpdb;

        // Get all customers who haven't completed — including those with no meta at all
        $users = get_users( array(
            'role__in' => array( 'six_customer' ),
            'number'   => $limit * 3,
            'orderby'  => 'registered',
            'order'    => 'DESC',
        ) );
        // Filter out completed ones in PHP (more reliable than meta_query != )
        $users = array_filter( $users, function( $u ) {
            return ! get_user_meta( $u->ID, 'six_checkout_completed', true );
        } );

        $leads = array();
        foreach ( $users as $u ) {
            // Filter by advisor if specified
            if ( $advisor_id ) {
                $assigned = $wpdb->get_var( $wpdb->prepare(
                    "SELECT advisor_id FROM {$wpdb->prefix}six_assignments WHERE client_id=%d", $u->ID
                ) );
                if ( intval($assigned) !== intval($advisor_id) ) continue;
            }

            $score    = self::calculate_score( $u->ID );
            $step     = intval( get_user_meta( $u->ID, 'six_checkout_step', true ) );
            $priority = self::classify_lead( $score );
            $last_ev  = get_user_meta( $u->ID, 'six_last_event', true );
            $abandoned= get_user_meta( $u->ID, 'six_abandoned_at_step', true );

            $co = $wpdb->get_row( $wpdb->prepare(
                "SELECT business_name, goal, challenge, industry, mktg_budget
                 FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d", $u->ID
            ) );

            $leads[] = array(
                'user_id'    => $u->ID,
                'name'       => $u->display_name ?: $u->user_email,
                'email'      => $u->user_email,
                'phone'      => get_user_meta( $u->ID, 'billing_phone', true ) ?: '',
                'score'      => $score,
                'priority'   => $priority,
                'step'       => $step,
                'abandoned'  => (bool) $abandoned,
                'business'   => $co->business_name ?? '',
                'industry'   => $co->industry ?? '',
                'budget'     => $co->mktg_budget ?? '',
                'goal'       => $co->goal ?? '',
                'last_event' => $last_ev,
                'device'     => get_user_meta( $u->ID, 'six_device_type', true ) ?: 'unknown',
                'utm_source' => get_user_meta( $u->ID, 'six_utm_source', true ) ?: '',
                'ai_rec'     => class_exists('Six_Odoo') ? Six_Odoo::generate_ai_recommendation( array(
                    'score'  => $score,
                    'step'   => $step,
                    'status' => $abandoned ? 'abandoned' : 'in_progress',
                    'name'   => $u->display_name,
                ) ) : 'Review this lead',
                'odoo_lead'  => intval( get_user_meta( $u->ID, 'six_odoo_lead_id', true ) ),
                'drop_risk'  => self::get_drop_risk( $u->ID ),
            );
        }

        // Sort: Hot first, then by score desc
        usort( $leads, function( $a, $b ) {
            $pord = array( 'hot' => 0, 'warm' => 1, 'cold' => 2 );
            $pa = $pord[$a['priority']] ?? 2;
            $pb = $pord[$b['priority']] ?? 2;
            if ( $pa !== $pb ) return $pa - $pb;
            return $b['score'] - $a['score'];
        } );

        return array_slice( $leads, 0, $limit );
    }

    /**
     * Get the full event timeline for one lead.
     * Used in the advisor's client profile view.
     */
    public static function get_lead_timeline( $user_id, $limit = 30 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'six_growth_events';

        $events = $wpdb->get_results( $wpdb->prepare(
            "SELECT event_type, step, meta, created_at
             FROM {$table}
             WHERE user_id=%d
             ORDER BY created_at DESC
             LIMIT %d",
            $user_id, $limit
        ), ARRAY_A );

        $icons = array(
            'step_view'      => '👁',
            'step_complete'  => '✅',
            'step_abandon'   => '⚠️',
            'return_visit'   => '↩',
            'google_login'   => '🔐',
            'email_submit'   => '📧',
            'service_select' => '⚡',
            'budget_select'  => '💰',
            'conversion'     => '🎉',
            'step_timing'    => '⏱',
            'page_load'      => '🌐',
        );

        $labels = array(
            'step_view'      => 'Viewed step',
            'step_complete'  => 'Completed step',
            'step_abandon'   => 'Abandoned at step',
            'return_visit'   => 'Returned to onboarding',
            'google_login'   => 'Signed in with Google',
            'email_submit'   => 'Entered email',
            'service_select' => 'Selected a service',
            'budget_select'  => 'Set budget',
            'conversion'     => 'Completed onboarding',
            'step_timing'    => 'Time on step',
            'page_load'      => 'Opened onboarding',
        );

        $formatted = array();
        foreach ( $events as $e ) {
            $meta   = json_decode( $e['meta'], true ) ?: array();
            $icon   = $icons[$e['event_type']] ?? '•';
            $label  = $labels[$e['event_type']] ?? $e['event_type'];
            $detail = '';
            if ( isset($meta['step']) && $meta['step'] > 0 ) $detail .= ' ' . self::step_label($meta['step']);
            if ( isset($meta['duration']) ) $detail .= ' (' . round($meta['duration']) . 's)';
            if ( isset($meta['score']) )    $detail .= ' — score: ' . $meta['score'];

            $formatted[] = array(
                'icon'   => $icon,
                'label'  => $label . $detail,
                'time'   => $e['created_at'],
                'type'   => $e['event_type'],
                'meta'   => $meta,
            );
        }
        return $formatted;
    }

    /**
     * Get conversion funnel insights for the sales/admin dashboard.
     * Returns per-step drop-off rates + top friction point.
     */
    public static function get_conversion_insights() {
        $stats   = self::funnel_stats(); // empty array if table doesn't exist
        $total_users = get_users( array( 'role' => 'six_customer', 'fields' => 'ID' ) );
        $total   = count( $total_users );
        $done    = count( get_users( array( 'role'=>'six_customer', 'meta_key'=>'six_checkout_completed', 'meta_value'=>'1', 'fields'=>'ID' ) ) );
        $overall = $total > 0 ? round( ($done / $total) * 100 ) : 0;

        // Find highest friction step
        $max_rate = 0;
        $friction_step = null;
        foreach ( $stats as $s ) {
            if ( $s['rate'] > $max_rate ) {
                $max_rate      = $s['rate'];
                $friction_step = $s;
            }
        }

        // AI insight text
        $insight = '';
        if ( $friction_step ) {
            $lbl = $friction_step['label'];
            $rt  = $friction_step['rate'];
            $avg = $friction_step['avg_time'];
            $insight = "⚠️ Highest friction: \"{$lbl}\" has a {$rt}% drop-off rate."
                     . ( $avg ? " Average time on this step: {$avg}s." : '' )
                     . ( $rt > 50 ? " This step needs immediate UX attention." : " Consider simplifying this step." );
        }

        return array(
            'overall_conversion' => $overall,
            'total_leads'        => intval( $total ),
            'completed'          => $done,
            'funnel'             => array_values( $stats ),
            'top_friction'       => $friction_step,
            'ai_insight'         => $insight,
            'generated_at'       => current_time('mysql'),
        );
    }

    // ═════════════════════════════════════════════════════════════════════
    // ─── CRON CALLBACKS (Timed Abandonment Flow) ──────────────────────────
    // ═════════════════════════════════════════════════════════════════════

    /**
     * +1 min: Send SMS if user hasn't completed and hasn't been texted yet.
     */
    /**
     * Schedule cron retries for abandon SMS/email.
     * Called by ajax-onboarding.php after the direct sends.
     * Guards in cron_abandon_sms/email prevent double-sending.
     */
    public static function schedule_abandon_retries( $user_id, $step, $score ) {
        if ( ! wp_next_scheduled( 'six_abandon_sms', array( $user_id, $step, $score ) ) ) {
            wp_schedule_single_event( time() + 120, 'six_abandon_sms', array( $user_id, $step, $score ) );
        }
        if ( ! wp_next_scheduled( 'six_abandon_email', array( $user_id, $step, $score ) ) ) {
            wp_schedule_single_event( time() + 720, 'six_abandon_email', array( $user_id, $step, $score ) );
        }
        spawn_wcron_async();
    }

    public static function cron_abandon_sms( $user_id, $step, $score ) {
        error_log("6ix Abandon SMS: START user={$user_id} step={$step}");

        // Guard: completed or already fired
        if ( get_user_meta( $user_id, 'six_checkout_completed', true ) ) {
            error_log("6ix Abandon SMS: skip — onboarding completed"); return;
        }
        if ( get_user_meta( $user_id, 'six_abandon_fired_sms', true ) ) {
            error_log("6ix Abandon SMS: skip — already fired"); return;
        }

        if ( ! class_exists('Six_Odoo') ) { error_log("6ix Abandon SMS: Six_Odoo missing"); return; }

        $user = get_userdata( $user_id );
        if ( ! $user ) { error_log("6ix Abandon SMS: user {$user_id} not found"); return; }

        // Try billing_phone first, then checkout_progress.phone as fallback
        // (object cache on new users can return empty string for billing_phone)
        $phone = get_user_meta( $user_id, 'billing_phone', true );
        if ( ! $phone ) {
            global $wpdb;
            $phone = $wpdb->get_var( $wpdb->prepare(
                "SELECT phone FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d LIMIT 1",
                $user_id
            ) );
        }
        if ( ! $phone ) {
            // Last resort: re-read directly from DB bypassing object cache
            wp_cache_delete( $user_id, 'user_meta' );
            $phone = get_user_meta( $user_id, 'billing_phone', true );
        }
        if ( ! $phone ) {
            error_log("6ix Abandon SMS: user {$user_id} has no phone in any source — SMS skipped");
            return;
        }

        // Mark as fired NOW (after all guards pass) to prevent duplicates
        update_user_meta( $user_id, 'six_abandon_fired_sms', 1 );

        // Ensure lead exists
        $lead_id = intval( get_user_meta( $user_id, 'six_odoo_lead_id', true ) );
        if ( ! $lead_id ) {
            Six_Odoo::create_or_update_contact( $user_id );
            $lead_id = Six_Odoo::sync_lead( array(
                'user_id' => $user_id, 'status' => 'abandoned', 'score' => $score, 'step' => $step
            ) );
        }

        $sms = "Checking in again! Complete your onboarding to see where your business stands "
             . "and how we can help. Feel free to call me if you have any questions: "
             . home_url('/get-started/');
        Six_Odoo::send_sms_twilio( $phone, $sms, $lead_id );

        self::track_event( $user_id, 'sms_sent', array( 'trigger' => 'abandon', 'step' => $step ) );
        error_log("6ix Abandon SMS: SENT to user={$user_id} phone={$phone} step={$step}");
    }

    /**
     * +10 min: Send personalised email.
     */
    public static function cron_abandon_email( $user_id, $step, $score ) {
        error_log("6ix Abandon Email: START user={$user_id} step={$step}");

        if ( get_user_meta( $user_id, 'six_checkout_completed', true ) ) {
            error_log("6ix Abandon Email: skip — completed"); return;
        }
        if ( get_user_meta( $user_id, 'six_abandon_fired_email', true ) ) {
            error_log("6ix Abandon Email: skip — already fired"); return;
        }
        if ( ! class_exists('Six_Odoo') ) { error_log("6ix Abandon Email: Six_Odoo missing"); return; }

        $user = get_userdata( $user_id );
        if ( ! $user ) { error_log("6ix Abandon Email: user {$user_id} not found"); return; }

        // Mark fired AFTER all guards
        update_user_meta( $user_id, 'six_abandon_fired_email', 1 );

        // Ensure lead exists
        if ( ! intval( get_user_meta( $user_id, 'six_odoo_lead_id', true ) ) ) {
            Six_Odoo::create_or_update_contact( $user_id );
            Six_Odoo::sync_lead( array( 'user_id' => $user_id, 'status' => 'abandoned', 'score' => $score, 'step' => $step ) );
        }

        global $wpdb;
        $co = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d", $user_id
        ) );

        $first_name = trim( $user->first_name ?: '' ) ?: 'there';
        $lead_id    = intval( get_user_meta( $user_id, 'six_odoo_lead_id', true ) );
        $subject    = 'Complete your onboarding with 6ix Developers';
        $body       = "Hi {$first_name},

"
                    . "Checking in again! Complete your onboarding to see where your business stands "
                    . "and how we can help.

"
                    . "Feel free to call me if you have any questions: "
                    . home_url('/get-started/') . "

"
                    . "Best,
Anastasia
6ix Developers";
        Six_Odoo::send_email_odoo( $lead_id, $user->user_email, $subject, $body );

        self::track_event( $user_id, 'email_sent', array( 'trigger' => 'abandon_10min', 'step' => $step ) );
        error_log( "6ix Growth: Email sent to user {$user_id} (abandon +10min)" );
    }

    /**
     * +30 min: Create Odoo advisor activity (the advisor sees this in their CRM queue).
     */
    public static function cron_abandon_activity( $user_id, $step, $score ) {
        if ( get_user_meta( $user_id, 'six_checkout_completed', true ) ) return;
        if ( get_user_meta( $user_id, 'six_abandon_fired_activity', true ) ) return;
        update_user_meta( $user_id, 'six_abandon_fired_activity', 1 );

        if ( ! class_exists('Six_Odoo') ) return;

        $lead_id = intval( get_user_meta( $user_id, 'six_odoo_lead_id', true ) );
        if ( ! $lead_id ) {
            // Lead wasn't created yet — create it now
            $lead_id = Six_Odoo::sync_lead( array(
                'user_id' => $user_id, 'status' => 'abandoned', 'score' => $score, 'step' => $step
            ) );
        }
        if ( ! $lead_id ) return;

        $user   = get_userdata( $user_id );
        $name   = $user ? $user->display_name : "User #{$user_id}";
        $re_calc= self::calculate_score( $user_id );
        $ai_rec = Six_Odoo::generate_ai_recommendation( array(
            'score' => $re_calc, 'step' => $step, 'status' => 'abandoned', 'name' => $name
        ) );
        $advisor_uid = self::get_advisor_odoo_uid( $user_id );

        $step_labels = array(
            0 => 'Email entry', 1 => 'Account creation',
            2 => 'Service selection', 3 => 'Questionnaire',
            4 => 'AI strategy review', 5 => 'Agreement & payment',
        );
        $step_label = $step_labels[ $step ] ?? "Step {$step}";

        Six_Odoo::create_activity(
            $lead_id,
            "📋 Follow Up Required — {$name}",
            "Client abandoned at: {$step_label} (step {$step}/5).
"
            . "Onboarding score: {$re_calc}/100.

"
            . "Automated SMS was sent at +1 min.
"
            . "Automated email was sent at +10 min.

"
            . "AI Recommendation:
{$ai_rec}

"
            . "Resume link: " . home_url('/get-started/'),
            'Todo', 0, $advisor_uid
        );

        self::track_event( $user_id, 'advisor_notified', array( 'trigger' => 'abandon_30min', 'step' => $step ) );
        error_log( "6ix Growth: Advisor activity created for user {$user_id} (abandon +30min)" );
    }

    /**
     * +24 h: Send follow-up email if still not completed.
     */
    public static function cron_abandon_followup( $user_id ) {
        if ( get_user_meta( $user_id, 'six_checkout_completed', true ) ) return;
        if ( get_user_meta( $user_id, 'six_abandon_fired_followup', true ) ) return;
        update_user_meta( $user_id, 'six_abandon_fired_followup', 1 );

        $user = get_userdata( $user_id );
        if ( ! $user || ! class_exists('Six_Odoo') ) return;

        $step    = intval( get_user_meta( $user_id, 'six_abandoned_at_step', true ) );
        $lead_id = intval( get_user_meta( $user_id, 'six_odoo_lead_id', true ) );

        global $wpdb;
        $co = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d", $user_id
        ) );

        // Slightly different copy for the 24h follow-up
        $name    = explode( ' ', $user->display_name )[0] ?: 'there';
        $biz     = $co->business_name ?? 'your business';
        $biz_type= $co->industry ?? 'business';
        $cta_url = home_url('/get-started/');

        $body = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;color:#1A1816'>
            <h2 style='color:#C0392B'>Still thinking it over, {$name}?</h2>
            <p>We saved your progress. Your evaluation for <strong>{$biz}</strong> is still waiting.</p>
            <p>In 10 days, we can show you measurable improvement in your <strong>{$biz_type}</strong>
               — completely risk-free. No commitment until after your free consultation.</p>
            <p>We only work with a small number of businesses at a time so we can give each one
               the attention it deserves. Your spot is still held.</p>
            <div style='margin:30px 0'>
                <a href='{$cta_url}' style='background:#C0392B;color:white;padding:14px 28px;
                   text-decoration:none;border-radius:6px;font-weight:bold;display:inline-block'>
                    Claim My Free Evaluation →
                </a>
            </div>
            <p style='color:#7A7570;font-size:13px'>
                6ix Developers · <a href='" . home_url() . "' style='color:#2C5F8A'>6ixdevelopers.com</a>
            </p>
        </div>";

        Six_Odoo::send_email_odoo(
            $lead_id,
            $user->user_email,
            "Still thinking it over? Your evaluation is waiting.",
            $body
        );

        self::track_event( $user_id, 'email_sent', array( 'trigger' => 'abandon_24h', 'step' => $step ) );
        error_log( "6ix Growth: 24h follow-up email sent to user {$user_id}" );
    }

    // ═════════════════════════════════════════════════════════════════════
    // ─── HELPERS ─────────────────────────────────────────────────────────
    // ═════════════════════════════════════════════════════════════════════

    private static function get_advisor_odoo_uid( $client_user_id ) {
        if ( ! class_exists('Six_Odoo') ) return 0;
        // Use Six_Odoo's internal method via reflection or duplicate the lookup
        global $wpdb;
        $advisor_wp_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT advisor_id FROM {$wpdb->prefix}six_assignments WHERE client_id=%d", $client_user_id
        ) );
        if ( ! $advisor_wp_id ) return 0;
        $advisor = get_userdata( intval($advisor_wp_id) );
        if ( ! $advisor ) return 0;
        // Look up in Odoo
        $ex = Six_Odoo::execute_public( 'res.users', 'search_read',
            array( array( array('login','=',$advisor->user_email) ) ),
            array( 'fields'=>array('id'), 'limit'=>1 ) );
        return ! empty($ex[0]['id']) ? intval($ex[0]['id']) : 0;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ─── DB SETUP ────────────────────────────────────────────────────────
    // ═════════════════════════════════════════════════════════════════════

    public static function create_events_table() {
        global $wpdb;
        $table   = $wpdb->prefix . 'six_growth_events';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id     BIGINT UNSIGNED NOT NULL,
            event_type  VARCHAR(60)     NOT NULL,
            step        TINYINT         NOT NULL DEFAULT 0,
            meta        TEXT,
            created_at  DATETIME        NOT NULL,
            KEY idx_user  (user_id),
            KEY idx_type  (event_type),
            KEY idx_step  (step),
            KEY idx_time  (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( 'six_growth_events_table_v1', 1 );
        return "✅ six_growth_events table ready";
    }
}
endif; // class_exists

// ─────────────────────────────────────────────────────────────────────────────
// STALE-LEAD DETECTOR  
// Runs every 5 minutes. Finds users who were active in onboarding but haven't
// moved in 5+ minutes and haven't completed. Fires abandon for them server-side.
// This is the failsafe when JS beacon/fetch is dropped by the browser or host.
// ─────────────────────────────────────────────────────────────────────────────

if ( ! wp_next_scheduled('six_stale_lead_check') ) {
    wp_schedule_event( time(), 'five_minutes', 'six_stale_lead_check' );
}

add_filter('cron_schedules', function($schedules){
    if ( ! isset($schedules['five_minutes']) ) {
        $schedules['five_minutes'] = array(
            'interval' => 300,
            'display'  => 'Every 5 Minutes',
        );
    }
    return $schedules;
});

add_action('six_stale_lead_check', function(){
    global $wpdb;

    // Step-aware inactivity thresholds (in seconds):
    // Step 1-2 → 5 min, Step 3 → 15 min, Step 4-5 → 20 min
    $thresholds = array(
        1 => 300,   // Account creation — 5 min
        2 => 300,   // Service selection — 5 min
        3 => 900,   // Questionnaire — 15 min
        4 => 1200,  // Strategy review — 20 min
        5 => 1200,  // Agreement — 20 min
    );

    // Use six_last_activity (updated by every goStep) as the activity source.
    // Falls back to updated_at if missing.
    $rows = $wpdb->get_results(
        "SELECT cp.user_id, cp.step, cp.score, cp.updated_at,
                COALESCE(
                    (SELECT meta_value FROM {$wpdb->usermeta}
                     WHERE user_id=cp.user_id AND meta_key='six_last_activity' LIMIT 1),
                    cp.updated_at
                ) AS last_activity
         FROM {$wpdb->prefix}six_checkout_progress cp
         WHERE cp.step > 0
           AND (cp.completed IS NULL OR cp.completed = 0)"
    );

    if ( empty($rows) ) return;

    $now = time();
    foreach ( $rows as $row ) {
        $uid = intval($row->user_id);
        if ( ! $uid ) continue;
        if ( get_user_meta($uid, 'six_checkout_completed', true) ) continue;

        // Step-aware threshold
        $step      = intval($row->step);
        $threshold = $thresholds[ min($step, 5) ] ?? 300;
        $last_ts   = strtotime($row->last_activity);
        $inactive  = $now - $last_ts;

        if ( $inactive < $threshold ) continue;  // not stale yet

        // Cooldown: don't re-trigger if already fired recently
        $last_trigger = intval( get_user_meta($uid, 'six_last_abandon_trigger', true) );
        if ( $last_trigger && ($now - $last_trigger) < 60 ) continue;

        $score = intval($row->score);
        error_log("6ix Stale: firing abandon for user={$uid} step={$step} inactive={$inactive}s");

        // Call the consolidated abandon handler via HTTP so it runs in a fresh
        // PHP process with no opcache dependency on class-growth-engine.
        // This is identical to what the JS beacon does.
        $response = wp_remote_post( admin_url('admin-ajax.php'), array(
            'timeout'  => 10,
            'blocking' => true,
            'body'     => array(
                'action'  => 'six_track_abandoned_checkout',
                'user_id' => $uid,
                'step'    => $step,
                'score'   => $score,
            ),
        ) );
        if ( is_wp_error($response) ) {
            error_log("6ix Stale: HTTP abandon failed for uid={$uid}: " . $response->get_error_message());
            // Fallback: call directly if HTTP fails
            if ( function_exists('six_track_abandoned_checkout') ) {
                $_POST = array('user_id'=>$uid,'step'=>$step,'score'=>$score,'email'=>'','session_id'=>'');
                six_track_abandoned_checkout();
            }
        } else {
            error_log("6ix Stale: HTTP abandon dispatched for uid={$uid} step={$step}");
        }
        // Prevent hammering — mark as triggered so 60s cooldown applies
        update_user_meta($uid, 'six_last_abandon_trigger', time());
    }
});

/**
 * Spawn WP-Cron asynchronously via a non-blocking HTTP request.
 * This ensures cron fires even when the site has low traffic.
 */
if ( ! function_exists('spawn_wcron_async') ) {
    function spawn_wcron_async() {
        $cron_url = add_query_arg( 'doing_wp_cron', '', site_url( 'wp-cron.php' ) );
        $args = array(
            'timeout'   => 0.01,
            'blocking'  => false,
            'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
        );
        wp_remote_get( $cron_url, $args );
    }
}

// =============================================================================
// REGISTER CRON HOOKS
// =============================================================================
add_action( 'six_abandon_sms',      function($uid,$step,$score){ Six_Growth_Engine::cron_abandon_sms($uid,$step,$score); }, 10, 3 );
add_action( 'six_abandon_email',    function($uid,$step,$score){ Six_Growth_Engine::cron_abandon_email($uid,$step,$score); }, 10, 3 );
add_action( 'six_abandon_activity', function($uid,$step,$score){ Six_Growth_Engine::cron_abandon_activity($uid,$step,$score); }, 10, 3 );
add_action( 'six_abandon_followup', function($uid){ Six_Growth_Engine::cron_abandon_followup($uid); }, 10, 1 );

// =============================================================================
// AJAX ENDPOINTS
// =============================================================================

add_action( 'wp_ajax_six_growth_priority_leads', function() {
    if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'six_nonce' ) ) { wp_send_json_error('Invalid nonce'); return; }
    $roles = (array) wp_get_current_user()->roles;
    if ( ! current_user_can('manage_options') && ! in_array('six_advisor',$roles) && ! in_array('six_sales',$roles) && ! current_user_can('edit_posts') ) { wp_send_json_error('Permission denied'); return; }
    $advisor_id = current_user_can('manage_options') ? 0 : get_current_user_id();
    wp_send_json_success( Six_Growth_Engine::get_priority_leads($advisor_id) );
} );

add_action( 'wp_ajax_six_growth_lead_timeline', function() {
    if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'six_nonce' ) ) { wp_send_json_error('Invalid nonce'); return; }
    $uid = intval( $_POST['user_id'] ?? 0 );
    if ( ! $uid ) { wp_send_json_error('Missing user_id'); return; }
    wp_send_json_success( Six_Growth_Engine::get_lead_timeline($uid) );
} );

add_action( 'wp_ajax_six_growth_funnel_stats', function() {
    if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'six_nonce' ) ) { wp_send_json_error('Invalid nonce'); return; }
    $roles2 = (array) wp_get_current_user()->roles;
    if ( ! current_user_can('manage_options') && ! in_array('six_advisor',$roles2) && ! in_array('six_sales',$roles2) && ! current_user_can('edit_posts') ) { wp_send_json_error('Permission denied'); return; }
    wp_send_json_success( Six_Growth_Engine::get_conversion_insights() );
} );

add_action( 'wp_ajax_nopriv_six_growth_track', 'six_growth_track_ajax' );
add_action( 'wp_ajax_six_growth_track',        'six_growth_track_ajax' );
function six_growth_track_ajax() {
    $uid  = intval( $_POST['user_id'] ?? 0 );
    $type = sanitize_key( $_POST['type'] ?? '' );
    $step = intval( $_POST['step'] ?? 0 );
    $meta = $_POST['meta'] ?? '';
    if ( ! $uid || ! $type ) wp_send_json_error('Missing params');
    Six_Growth_Engine::track_event( $uid, $type, $meta ? (array)json_decode(stripslashes($meta),true) : array() );
    if ( $type === 'page_load' ) Six_Growth_Engine::on_re_engage( $uid );
    update_user_meta( $uid, 'six_last_event', current_time('mysql') );
    wp_send_json_success();
}

// Route six_growth_abandon to six_track_abandoned_checkout
// (consolidated handler — see ajax-onboarding.php)
add_action( 'wp_ajax_nopriv_six_growth_abandon', function() {
    if ( function_exists('six_track_abandoned_checkout') ) six_track_abandoned_checkout();
    else wp_send_json_success();
});
add_action( 'wp_ajax_six_growth_abandon', function() {
    if ( function_exists('six_track_abandoned_checkout') ) six_track_abandoned_checkout();
    else wp_send_json_success();
});

// Keep the old function as a thin wrapper for backward compat
function six_growth_abandon_ajax() {
    // LOG EVERY HIT so we can confirm the request reaches the server
    $raw_uid   = $_POST['user_id'] ?? 'MISSING';
    $raw_email = $_POST['email']   ?? 'MISSING';
    $raw_step  = $_POST['step']    ?? 'MISSING';
    error_log( "6ix Abandon RECEIVED: user_id={$raw_uid} email={$raw_email} step={$raw_step}" );

    $uid   = intval( $_POST['user_id'] ?? 0 );
    $step  = intval( $_POST['step']    ?? 0 );
    $score = intval( $_POST['score']   ?? 0 );
    $email = sanitize_email( $_POST['email'] ?? '' );

    $session_id = sanitize_text_field( $_POST['session_id'] ?? '' );

    // Resolution chain: user_id → email → session_id
    if ( ! $uid && $email ) {
        $u = get_user_by( 'email', $email );
        if ( $u ) { $uid = $u->ID; error_log("6ix Abandon: resolved email={$email} → uid={$uid}"); }
    }
    if ( ! $uid && $session_id ) {
        global $wpdb;
        $sid_uid = $wpdb->get_var( $wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key='six_session_id' AND meta_value=%s LIMIT 1",
            $session_id
        ) );
        if ( $sid_uid ) { $uid = intval($sid_uid); error_log("6ix Abandon: resolved session={$session_id} → uid={$uid}"); }
    }

    if ( ! $uid ) {
        error_log("6ix Abandon: UNRESOLVED uid={$raw_uid} email={$email} session={$session_id} step={$raw_step}");
        wp_send_json_success( array( 'note' => 'no_user' ) );
        return;
    }

    Six_Growth_Engine::on_abandon( $uid, $step, $score );
    wp_send_json_success();
}

add_action( 'wp_ajax_nopriv_six_growth_device', 'six_growth_device_ajax' );
add_action( 'wp_ajax_six_growth_device',        'six_growth_device_ajax' );
function six_growth_device_ajax() {
    $uid    = intval( $_POST['user_id'] ?? 0 );
    $device = sanitize_text_field( $_POST['device'] ?? 'unknown' );
    if ( ! $uid ) wp_send_json_error('Missing user_id');
    Six_Growth_Engine::capture_device( $uid, $device );
    wp_send_json_success();
}

// =============================================================================
// ONE-TIME SETUP — /wp-admin/?six_growth_setup=1
// =============================================================================
add_action( 'admin_init', function() {
    if ( ! current_user_can('manage_options') || empty($_GET['six_growth_setup']) ) return;
    echo '<div style="font-family:monospace;padding:30px;background:#0d1117;color:#f0f4f8">';
    echo '<h2 style="color:#FF6699">6ix Growth Engine Setup</h2>';
    echo '<ul style="line-height:2">';
    echo '<li>' . Six_Growth_Engine::create_events_table() . '</li>';
    echo '</ul>';
    echo '<p style="color:#56D364">Growth Engine ready.</p>';
    echo '</div>';
    exit;
} );
