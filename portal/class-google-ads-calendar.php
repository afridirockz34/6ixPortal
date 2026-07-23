<?php
/**
 * Six_Google_Ads — Per-Client Google Ads API Integration
 *
 * Credentials are stored per client user (set by advisor):
 *   user_meta: six_gads_customer_id, six_gads_refresh_token, six_gads_login_customer_id
 *
 * Upload to: /wp-content/themes/6ixdevelopers/portal/class-google-ads-calendar.php
 * (replaces the previous version)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Six_Google_Ads {

    private static $last_error = '';

    public static function get_last_error() {
        return self::$last_error;
    }

    /**
     * Get a valid MCC access token.
     * Uses the global MCC refresh token (single token for all client accounts).
     * Credentials stored as WP options (set in WP Admin → 6ix Portal → Integrations):
     *   six_gads_client_id      — OAuth Client ID
     *   six_gads_client_secret  — OAuth Client Secret
     *   six_gads_refresh_token  — MCC refresh token (global, not per-client)
     */
    public static function get_mcc_access_token( $force = false ) {
        if ( ! $force ) {
            $cached  = get_option( 'six_gads_access_token' );
            $expires = (int) get_option( 'six_gads_token_expires', 0 );
            if ( $cached && time() < ( $expires - 300 ) ) {
                return $cached;
            }
        }

        $client_id     = get_option( 'six_gads_client_id' );
        $client_secret = get_option( 'six_gads_client_secret' );
        $refresh_token = get_option( 'six_gads_refresh_token' ); // global MCC token

        if ( ! $client_id || ! $client_secret || ! $refresh_token ) {
            self::$last_error = 'MCC credentials not configured. Go to WP Admin → 6ix Portal → Integrations → Google Ads section.';
            return false;
        }

        $response = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
            'timeout' => 15,
            'body'    => array(
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'refresh_token' => $refresh_token,
                'grant_type'    => 'refresh_token',
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            self::$last_error = 'Token refresh network error: ' . $response->get_error_message();
            return false;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! empty( $data['access_token'] ) ) {
            update_option( 'six_gads_access_token',  $data['access_token'] );
            update_option( 'six_gads_token_expires', time() + intval( $data['expires_in'] ?? 3600 ) );
            return $data['access_token'];
        }

        $err = $data['error_description'] ?? $data['error'] ?? 'Unknown token error';
        if ( ( $data['error'] ?? '' ) === 'invalid_grant' ) {
            self::$last_error = 'Refresh token expired or revoked. Generate a new one and update it in WP Admin → 6ix Portal → Integrations.';
            delete_option( 'six_gads_access_token' );
        } else {
            self::$last_error = 'Token refresh failed: ' . $err;
        }
        error_log( '6ix Google Ads token error: ' . wp_json_encode( $data ) );
        return false;
    }

    /**
     * Fetch campaign metrics for a client using their Customer ID.
     * Uses the single MCC access token + login-customer-id header.
     * Advisors only need to set the Customer ID per client — no per-client tokens.
     */
    public static function get_campaign_metrics_for_client( $client_id, $date_range = 'LAST_30_DAYS' ) {
        $customer_id = get_user_meta( $client_id, 'six_gads_customer_id', true );
        if ( ! $customer_id ) {
            self::$last_error = 'No Google Ads Customer ID set for this client.';
            return false;
        }
        $customer_id = preg_replace( '/[^0-9]/', '', $customer_id );
        $manager_id  = preg_replace( '/[^0-9]/', '', get_option( 'six_gads_manager_id', '' ) );

        // Guard: if the customer ID matches the MCC manager ID, the advisor
        // accidentally entered the MCC ID instead of the client's own account ID
        if ( $manager_id && $customer_id === $manager_id ) {
            self::$last_error = "The Customer ID set for this client ({$customer_id}) is the same as your MCC Manager Account ID. "
                . "These must be different. The Customer ID should be the CLIENT's individual Google Ads account ID — "
                . "find it by going into the client's account inside your MCC and looking at the top-right corner.";
            return false;
        }

        // Check cache first
        $cache_key = 'six_gads_' . $customer_id . '_' . md5($date_range);
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) return $cached;

        // Get MCC access token
        $access_token    = self::get_mcc_access_token();
        if ( ! $access_token ) return false;

        $developer_token = get_option( 'six_gads_developer_token', '' );

        if ( ! $developer_token ) {
            self::$last_error = 'Developer Token not set. Add it in WP Admin → 6ix Portal → Integrations → Google Ads section.';
            return false;
        }

        $query = "SELECT
            campaign.id,
            campaign.name,
            campaign.status,
            metrics.clicks,
            metrics.impressions,
            metrics.cost_micros,
            metrics.conversions,
            metrics.ctr,
            metrics.average_cpc,
            metrics.cost_per_conversion
          FROM campaign
          WHERE campaign.status = 'ENABLED'
          ORDER BY metrics.cost_micros DESC
          LIMIT 20";

        $headers = array(
            'Authorization'   => 'Bearer ' . $access_token,
            'Content-Type'    => 'application/json',
            'developer-token' => $developer_token,
        );
        // login-customer-id = MCC account ID — required to query sub-accounts
        if ( $manager_id ) {
            $headers['login-customer-id'] = $manager_id;
        }

        $response = wp_remote_post(
            "https://googleads.googleapis.com/v20/customers/{$customer_id}/googleAds:search",
            array(
                'timeout' => 20,
                'headers' => $headers,
                'body'    => wp_json_encode( array( 'query' => $query ) ),
            )
        );

        if ( is_wp_error( $response ) ) {
            self::$last_error = 'API request failed: ' . $response->get_error_message();
            return false;
        }

        $http_code = wp_remote_retrieve_response_code( $response );
        $body      = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $http_code !== 200 ) {
            // Log full response body to debug.log for diagnosis
            error_log( '6ix Google Ads full error response for client ' . $client_id . ': ' . wp_remote_retrieve_body( $response ) );

            $api_err    = $body['error']['message'] ?? ( $body['error']['status'] ?? 'HTTP ' . $http_code );
            $api_detail = '';
            if ( ! empty( $body['error']['details'] ) ) {
                foreach ( $body['error']['details'] as $d ) {
                    if ( ! empty( $d['errors'] ) ) {
                        foreach ( $d['errors'] as $e ) {
                            $api_detail .= ' | ' . ( $e['message'] ?? '' ) . ' [field: ' . ( $e['location']['fieldPathElements'][0]['fieldName'] ?? 'unknown' ) . ']';
                        }
                    }
                }
            }

            // Clear cache so next attempt retries
            delete_transient( $cache_key );

            if ( $http_code === 404 ) {
                self::$last_error = "404 Not Found — API version or Customer ID is incorrect. Customer ID used: {$customer_id}. Verify it has no letters, is 10 digits, and the account exists in your MCC.";
            } elseif ( $http_code === 403 ) {
                $has_login_id = ! empty( $manager_id );
                self::$last_error = "403 Permission Denied.\n"
                    . "• Customer ID sent: {$customer_id}\n"
                    . "• Manager (MCC) ID sent: " . ( $manager_id ?: '(empty — not set)' ) . "\n"
                    . "• login-customer-id header: " . ( $has_login_id ? "✓ included ({$manager_id})" : " NOT sent — this is likely the problem" ) . "\n"
                    . "Fix checklist:\n"
                    . "1. Confirm client account {$customer_id} is linked to your MCC in Google Ads → click your MCC account → Accounts\n"
                    . "2. Confirm Manager ID in WP Admin → Integrations matches your MCC account ID exactly (digits only)\n"
                    . "3. Make sure the refresh token was generated while logged into the MCC account, not a client account";
            } elseif ( strpos( $api_err, 'manager account' ) !== false || strpos( $api_err, 'Metrics cannot be requested for a manager' ) !== false ) {
                self::$last_error = "The Customer ID ({$customer_id}) is a Manager (MCC) account — you cannot pull metrics from it directly. "
                    . "In the advisor portal, set the Customer ID to the CLIENT's individual account ID (not the MCC). "
                    . "Find it by opening the client's account inside your MCC → the 10-digit ID shows in the top-right corner of Google Ads.";
            } elseif ( strpos( $api_err, 'CUSTOMER_NOT_FOUND' ) !== false ) {
                self::$last_error = "Customer ID {$customer_id} not found. Verify the ID in Google Ads.";
            } elseif ( strpos( $api_err, 'AUTHORIZATION_ERROR' ) !== false || strpos( $api_err, 'USER_PERMISSION_DENIED' ) !== false ) {
                self::$last_error = "Authorization error for customer {$customer_id}. Make sure the client account is linked to your MCC in Google Ads.";
            } elseif ( strpos( $api_err, 'DEVELOPER_TOKEN_NOT_APPROVED' ) !== false ) {
                self::$last_error = "Developer Token not approved for production. You need Basic or Standard access (you mentioned you now have Basic — try regenerating the developer token).";
            } elseif ( strpos( $api_err, 'invalid_grant' ) !== false || strpos( $api_err, 'UNAUTHENTICATED' ) !== false ) {
                self::$last_error = "Authentication failed. The refresh token may be expired. Generate a new one and update it in Integrations.";
            } else {
                self::$last_error = "Google Ads API error ({$http_code}): {$api_err}{$api_detail}";
            }
            error_log( '6ix Google Ads error for client ' . $client_id . ': ' . wp_json_encode( $body ) );
            return false;
        }

        $metrics = self::aggregate_metrics( $body['results'] ?? array() );
        if ( $metrics ) {
            self::save_metrics_to_db( $client_id, $metrics );
            set_transient( $cache_key, $metrics, 6 * HOUR_IN_SECONDS );
        }
        return $metrics ?: array();
    }

    // ── Aggregate raw API results into summary totals ──────────────────────
    private static function aggregate_metrics( $results ) {
        $totals = array(
            'clicks'          => 0,
            'conversions'     => 0.0,
            'impressions'     => 0,
            'cost'            => 0.0,
            'avg_cpc'         => 0.0,
            'conversion_rate' => 0.0,
            'ctr'             => 0.0,
            'campaigns'       => count( $results ),
            'campaigns_data'  => array(),
        );
        foreach ( $results as $row ) {
            $m = $row['metrics'] ?? array();
            $totals['clicks']      += intval( $m['clicks'] ?? 0 );
            $totals['conversions'] += floatval( $m['conversions'] ?? 0 );
            $totals['impressions'] += intval( $m['impressions'] ?? 0 );
            $totals['cost']        += intval( $m['costMicros'] ?? 0 ) / 1_000_000;
            $totals['campaigns_data'][] = array(
                'name'        => $row['campaign']['name'] ?? 'Campaign',
                'status'      => $row['campaign']['status'] ?? '',
                'clicks'      => intval( $m['clicks'] ?? 0 ),
                'conversions' => floatval( $m['conversions'] ?? 0 ),
                'cost'        => round( intval( $m['costMicros'] ?? 0 ) / 1_000_000, 2 ),
                'ctr'         => round( floatval( $m['ctr'] ?? 0 ) * 100, 2 ),
            );
        }
        if ( $totals['clicks'] > 0 ) {
            $totals['avg_cpc']         = round( $totals['cost'] / $totals['clicks'], 2 );
            $totals['conversion_rate'] = round( ( $totals['conversions'] / $totals['clicks'] ) * 100, 2 );
        }
        if ( $totals['impressions'] > 0 ) {
            $totals['ctr'] = round( ( $totals['clicks'] / $totals['impressions'] ) * 100, 2 );
        }
        $totals['cost'] = round( $totals['cost'], 2 );
        return $totals;
    }

    // ── Save aggregated metrics to the six_metrics DB table ───────────────
    private static function save_metrics_to_db( $client_id, $metrics ) {
        global $wpdb;
        $table = $wpdb->prefix . 'six_metrics';
        $now   = current_time( 'mysql' );
        $map   = array(
            'Clicks'          => strval( $metrics['clicks'] ),
            'Conversions'     => strval( $metrics['conversions'] ),
            'Impressions'     => strval( $metrics['impressions'] ),
            'Ad Spend'        => '$' . number_format( $metrics['cost'], 2 ),
            'Avg CPC'         => '$' . $metrics['avg_cpc'],
            'Conversion Rate' => $metrics['conversion_rate'] . '%',
            'CTR'             => $metrics['ctr'] . '%',
        );
        foreach ( $map as $label => $value ) {
            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM $table WHERE client_id=%d AND service_slug='google-ads' AND label=%s",
                $client_id, $label
            ) );
            if ( $existing ) {
                $wpdb->update( $table,
                    array( 'current_value' => $value, 'updated_at' => $now ),
                    array( 'id' => $existing )
                );
            } else {
                $wpdb->insert( $table, array(
                    'client_id'     => $client_id,
                    'service_slug'  => 'google-ads',
                    'label'         => $label,
                    'current_value' => $value,
                    'updated_at'    => $now,
                ) );
            }
        }
        update_user_meta( $client_id, 'six_gads_last_sync', $now );
    }

    // ── WP-Cron daily sync ─────────────────────────────────────────────────
    public static function schedule_daily_sync() {
        if ( ! wp_next_scheduled( 'six_gads_daily_sync' ) ) {
            wp_schedule_event( strtotime( 'tomorrow 03:00:00' ), 'daily', 'six_gads_daily_sync' );
        }
    }

    public static function run_daily_sync() {
        $clients = get_users( array(
            'role'       => 'six_customer',
            'meta_query' => array(
                array( 'key' => 'six_gads_customer_id', 'compare' => 'EXISTS' ),
                array( 'key' => 'six_gads_customer_id', 'value' => '', 'compare' => '!=' ),
            ),
        ) );
        foreach ( $clients as $client ) {
            self::get_campaign_metrics_for_client( $client->ID );
            usleep( 500000 );
        }
    }

} // end class Six_Google_Ads

add_action( 'six_gads_daily_sync', array( 'Six_Google_Ads', 'run_daily_sync' ) );
add_action( 'init', array( 'Six_Google_Ads', 'schedule_daily_sync' ) );


// ─────────────────────────────────────────────────────────────────────────────
// Six_Google_Calendar — Advisor availability + meeting booking
// ─────────────────────────────────────────────────────────────────────────────
class Six_Google_Calendar {

    // Public: called by the six_get_available_slots AJAX handler. Was private,
    // which made that handler fatal — the reason calendar booking failed.
    public static function get_access_token( $user_id ) {
        $cached  = get_user_meta( $user_id, 'six_gcal_access_token', true );
        $expires = (int) get_user_meta( $user_id, 'six_gcal_token_expires', true );

        if ( $cached && time() < ( $expires - 300 ) ) return $cached;

        $refresh = get_user_meta( $user_id, 'six_gcal_refresh_token', true );
        if ( ! $refresh ) return false;

        $response = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
            'body' => array(
                'client_id'     => get_option( 'six_google_client_id' ),
                'client_secret' => get_option( 'six_google_client_secret' ),
                'refresh_token' => $refresh,
                'grant_type'    => 'refresh_token',
            ),
        ) );

        if ( is_wp_error( $response ) ) return false;
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! empty( $data['access_token'] ) ) {
            update_user_meta( $user_id, 'six_gcal_access_token', $data['access_token'] );
            update_user_meta( $user_id, 'six_gcal_token_expires', time() + intval( $data['expires_in'] ) );
            return $data['access_token'];
        }
        return false;
    }

    /**
     * Get today's events for the advisor's calendar.
     * Returns array of event arrays with title, start, end, client_name, meet_link, duration.
     */
    public static function get_today_events( $advisor_id ) {
        return self::get_upcoming_events( $advisor_id, 1 );
    }

    /**
     * Get upcoming events for the next $days days.
     */
    public static function get_upcoming_events( $advisor_id, $days = 7 ) {
        $token  = self::get_access_token( $advisor_id );
        $cal_id = get_user_meta( $advisor_id, 'six_gcal_calendar_id', true ) ?: 'primary';

        if ( ! $token ) return array();

        $time_min = date( 'c', strtotime( 'today 00:00:00' ) );
        $time_max = date( 'c', strtotime( "+{$days} days 23:59:59" ) );

        $response = wp_remote_get(
            'https://www.googleapis.com/calendar/v3/calendars/' . urlencode($cal_id) . '/events?' . http_build_query(array(
                'timeMin'      => $time_min,
                'timeMax'      => $time_max,
                'singleEvents' => 'true',
                'orderBy'      => 'startTime',
                'maxResults'   => 50,
            )),
            array(
                'timeout' => 15,
                'headers' => array( 'Authorization' => 'Bearer ' . $token ),
            )
        );

        if ( is_wp_error($response) ) return array();

        $data   = json_decode( wp_remote_retrieve_body($response), true );
        $events = array();

        foreach ( $data['items'] ?? array() as $item ) {
            if ( ($item['status'] ?? '') === 'cancelled' ) continue;

            $start = $item['start']['dateTime'] ?? $item['start']['date'] ?? '';
            $end   = $item['end']['dateTime']   ?? $item['end']['date']   ?? '';
            if ( ! $start ) continue;

            // Extract Google Meet link
            $meet_link = '';
            foreach ( $item['conferenceData']['entryPoints'] ?? array() as $ep ) {
                if ( ($ep['entryPointType'] ?? '') === 'video' ) {
                    $meet_link = $ep['uri'] ?? '';
                    break;
                }
            }
            // Also check hangoutLink
            if ( ! $meet_link && ! empty($item['hangoutLink']) ) {
                $meet_link = $item['hangoutLink'];
            }

            // Try to match event to a client by email in attendees
            $client_name = '';
            foreach ( $item['attendees'] ?? array() as $att ) {
                if ( ($att['self'] ?? false) ) continue;
                $client_user = get_user_by('email', $att['email'] ?? '');
                if ( $client_user ) {
                    $client_name = $client_user->display_name;
                    break;
                }
                // Fallback: use attendee displayName
                if ( empty($client_name) && ! empty($att['displayName']) ) {
                    $client_name = $att['displayName'];
                }
            }

            $events[] = array(
                'id'          => $item['id'],
                'title'       => $item['summary'] ?? 'Meeting',
                'description' => $item['description'] ?? '',
                'start'       => $start,
                'end'         => $end,
                'duration'    => $end ? round((strtotime($end)-strtotime($start))/60) : 0,
                'client_name' => $client_name,
                'meet_link'   => $meet_link,
                'location'    => $item['location'] ?? '',
            );
        }

        return $events;
    }

    public static function get_availability( $advisor_id, $date_start, $date_end ) {
        $token   = self::get_access_token( $advisor_id );
        $cal_id  = get_user_meta( $advisor_id, 'six_gcal_calendar_id', true ) ?: 'primary';

        if ( ! $token ) {
            // Return mock slots if no token configured yet
            return self::mock_slots( $date_start );
        }

        $response = wp_remote_get(
            'https://www.googleapis.com/calendar/v3/calendars/' . urlencode( $cal_id ) . '/events?' . http_build_query( array(
                'timeMin'      => date( 'c', strtotime( $date_start ) ),
                'timeMax'      => date( 'c', strtotime( $date_end ) ),
                'singleEvents' => 'true',
                'orderBy'      => 'startTime',
            ) ),
            array( 'headers' => array( 'Authorization' => 'Bearer ' . $token ), 'timeout' => 15 )
        );

        if ( is_wp_error( $response ) ) return self::mock_slots( $date_start );

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        $busy = array();
        foreach ( $data['items'] ?? array() as $event ) {
            $busy[] = array(
                'start' => $event['start']['dateTime'] ?? $event['start']['date'],
                'end'   => $event['end']['dateTime']   ?? $event['end']['date'],
            );
        }

        return self::generate_slots( $date_start, $date_end, $busy );
    }

    private static function generate_slots( $date_start, $date_end, $busy = array() ) {
        $slots = array();
        $day   = strtotime( $date_start );
        $end   = strtotime( $date_end );

        while ( $day < $end ) {
            if ( date( 'N', $day ) < 6 ) {
                for ( $h = 9; $h < 17; $h++ ) {
                    for ( $m = 0; $m < 60; $m += 30 ) {
                        $slot_start = mktime( $h, $m, 0, date('n',$day), date('j',$day), date('Y',$day) );
                        $slot_end   = $slot_start + 1800;
                        $available  = true;
                        foreach ( $busy as $b ) {
                            if ( strtotime($b['start']) < $slot_end && strtotime($b['end']) > $slot_start ) {
                                $available = false; break;
                            }
                        }
                        if ( $available && $slot_start > time() ) {
                            $slots[] = array(
                                'start'        => date( 'c', $slot_start ),
                                'end'          => date( 'c', $slot_end ),
                                'display'      => date( 'g:i A', $slot_start ),
                                'date_display' => date( 'D, M j', $slot_start ),
                                'timestamp'    => $slot_start,
                            );
                        }
                    }
                }
            }
            $day = strtotime( '+1 day', $day );
        }
        return $slots;
    }

    private static function mock_slots( $date_start ) {
        // Return placeholder slots when Google Calendar not yet configured
        return self::generate_slots( $date_start, date( 'Y-m-d', strtotime( '+7 days' ) ) );
    }

    public static function book_meeting( $args ) {
        $advisor_id = intval( $args['advisor_id'] );
        $client_id  = intval( $args['client_id'] );
        $start      = sanitize_text_field( $args['start'] );
        $duration   = intval( $args['duration'] ?? 30 );
        $notes      = sanitize_textarea_field( $args['notes'] ?? '' );
        $suppress   = ! empty( $args['suppress_notify'] );
        $token      = self::get_access_token( $advisor_id );
        $cal_id     = get_user_meta( $advisor_id, 'six_gcal_calendar_id', true ) ?: 'primary';

        $client  = get_userdata( $client_id );
        $advisor = get_userdata( $advisor_id );
        $end     = date( 'c', strtotime( $start ) + ( $duration * 60 ) );
        $summary = sanitize_text_field( $args['title'] ?? '' )
            ?: ( '6ix Developers — Strategy Meeting with ' . $client->display_name );

        $event = array(
            'summary'     => $summary,
            'description' => $notes ?: 'Marketing strategy meeting booked via 6ix Developers Portal.',
            'start'       => array( 'dateTime' => $start, 'timeZone' => 'America/Toronto' ),
            'end'         => array( 'dateTime' => $end,   'timeZone' => 'America/Toronto' ),
            'attendees'   => array(
                array( 'email' => $advisor->user_email ),
                array( 'email' => $client->user_email ),
            ),
        );

        if ( ! $token ) {
            // No calendar token — still notify and create Odoo task (unless the
            // caller is handling notifications itself).
            if ( ! $suppress && class_exists( 'Six_Notifications' ) ) {
                Six_Notifications::create( array(
                    'user_id' => $advisor_id,
                    'type'    => 'meeting_booked',
                    'title'   => 'New Meeting Request',
                    'message' => $client->display_name . ' requested a meeting on ' . date( 'M j \a\t g:i A', strtotime( $start ) ),
                ) );
            }
            return array( 'success' => true, 'event_id' => '', 'meet_link' => '' );
        }

        $response = wp_remote_post(
            'https://www.googleapis.com/calendar/v3/calendars/' . urlencode( $cal_id ) . '/events?conferenceDataVersion=1',
            array(
                'timeout' => 20,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ),
                'body' => wp_json_encode( array_merge( $event, array(
                    'conferenceData' => array(
                        'createRequest' => array(
                            'requestId'             => uniqid(),
                            'conferenceSolutionKey' => array( 'type' => 'hangoutsMeet' ),
                        )
                    ),
                ) ) ),
            )
        );

        if ( is_wp_error( $response ) ) return false;
        $result = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! empty( $result['id'] ) ) {
            // Extract the Google Meet link — hangoutLink or conferenceData.
            $meet_link = $result['hangoutLink'] ?? '';
            if ( ! $meet_link ) {
                foreach ( $result['conferenceData']['entryPoints'] ?? array() as $ep ) {
                    if ( ( $ep['entryPointType'] ?? '' ) === 'video' ) { $meet_link = $ep['uri'] ?? ''; break; }
                }
            }
            if ( ! $suppress ) {
                if ( class_exists( 'Six_Odoo' ) ) {
                    Six_Odoo::create_task( array(
                        'title'        => $event['summary'],
                        'description'  => "Booked: {$start}\nClient: {$client->display_name}\nNotes: {$notes}",
                        'date'         => date( 'Y-m-d', strtotime( $start ) ),
                        'client_email' => $client->user_email,
                    ) );
                }
                if ( class_exists( 'Six_Notifications' ) ) {
                    Six_Notifications::create( array(
                        'user_id' => $advisor_id,
                        'type'    => 'meeting_booked',
                        'title'   => 'Meeting Booked',
                        'message' => $client->display_name . ' booked a meeting on ' . date( 'M j \a\t g:i A', strtotime( $start ) ),
                    ) );
                }
            }
            return array( 'success' => true, 'event_id' => $result['id'], 'meet_link' => $meet_link );
        }
        return false;
    }
}
