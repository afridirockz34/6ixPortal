<?php
/**
 * Six_Analytics — GA4 + Google Search Console reporting via a Google service
 * account (server-side JWT, no per-advisor OAuth).
 *
 * Setup: create a Google Cloud service account with the GA4 Data API + Search
 * Console API enabled, paste its JSON into option six_ga4_service_account_json,
 * then grant that service-account email:
 *   - Viewer on the GA4 property, and
 *   - a user on the Search Console property.
 *
 * Returns compact, already-summarised arrays for LLM tool use.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Six_Analytics {

    const SCOPES = 'https://www.googleapis.com/auth/analytics.readonly https://www.googleapis.com/auth/webmasters.readonly';

    public static function configured() {
        return (bool) get_option( 'six_ga4_service_account_json', '' );
    }

    /**
     * The service account's own email address.
     *
     * This is the single most useful string in the whole integration and it
     * used to be buried in a textarea of JSON: granting it Viewer on the GA4
     * property, and a user on the Search Console property, is the step that
     * makes everything else work, and it happens in Google rather than here.
     * Returns '' when nothing is configured.
     */
    public static function service_account_email() {
        $key = json_decode( get_option( 'six_ga4_service_account_json', '' ), true );
        return is_array( $key ) && ! empty( $key['client_email'] ) ? $key['client_email'] : '';
    }

    /**
     * Turn a Google error into something an advisor can act on.
     *
     * A raw "PERMISSION_DENIED" tells you nothing about what to do next; the
     * fix is always the same two steps and this says them.
     */
    private static function explain( $message, $property_id = '' ) {
        $email = self::service_account_email();
        $m     = (string) $message;
        if ( stripos( $m, 'permission' ) !== false || stripos( $m, 'denied' ) !== false
             || stripos( $m, 'caller does not have' ) !== false ) {
            return 'Google refused access to '
                . ( $property_id ? "property {$property_id}" : 'this property' ) . '. '
                . ( $email
                    ? "Open that property in Google Analytics → Admin → Property access management and add {$email} with the Viewer role."
                    : 'No service-account JSON is configured yet — add it under Integrations first.' )
                . ' (' . $m . ')';
        }
        if ( stripos( $m, 'not found' ) !== false || stripos( $m, 'invalid' ) !== false ) {
            return 'Google did not recognise '
                . ( $property_id ? "property {$property_id}" : 'that property' )
                . '. It should be the numeric Property ID from Analytics → Admin → Property details, '
                . 'not the measurement ID that starts with G-. (' . $m . ')';
        }
        return $m;
    }

    /** Service-account access token (cached ~50 min). */
    private static function token() {
        $cached = get_transient( 'six_ga_sa_token' );
        if ( $cached ) return $cached;

        $json = get_option( 'six_ga4_service_account_json', '' );
        if ( ! $json ) return array( 'error' => 'GA4 service account JSON not configured.' );
        $key = json_decode( $json, true );
        if ( ! $key || empty( $key['client_email'] ) || empty( $key['private_key'] ) ) {
            return array( 'error' => 'Invalid service account JSON.' );
        }
        $now = time();
        $seg = function ( $d ) { return rtrim( strtr( base64_encode( wp_json_encode( $d ) ), '+/', '-_' ), '=' ); };
        $header  = $seg( array( 'alg' => 'RS256', 'typ' => 'JWT' ) );
        $payload = $seg( array(
            'iss'   => $key['client_email'],
            'scope' => self::SCOPES,
            'aud'   => 'https://oauth2.googleapis.com/token',
            'exp'   => $now + 3600,
            'iat'   => $now,
        ) );
        $pk = openssl_pkey_get_private( $key['private_key'] );
        if ( ! $pk ) return array( 'error' => 'Could not load service-account private key.' );
        openssl_sign( "{$header}.{$payload}", $sig, $pk, OPENSSL_ALGO_SHA256 );
        $jwt = "{$header}.{$payload}." . rtrim( strtr( base64_encode( $sig ), '+/', '-_' ), '=' );

        $resp = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
            'timeout' => 20,
            'body' => array( 'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt ),
        ) );
        if ( is_wp_error( $resp ) ) return array( 'error' => 'Token network error: ' . $resp->get_error_message() );
        $tok = json_decode( wp_remote_retrieve_body( $resp ), true );
        if ( empty( $tok['access_token'] ) ) return array( 'error' => 'Service-account auth failed: ' . ( $tok['error_description'] ?? $tok['error'] ?? 'unknown' ) );

        set_transient( 'six_ga_sa_token', $tok['access_token'], 3000 );
        return $tok['access_token'];
    }

    // ── GA4 ────────────────────────────────────────────────────────────────
    public static function ga4_summary( $property_id, $days = 30 ) {
        $raw = trim( (string) $property_id );

        // A measurement ID is the thing people have to hand, and it is not a
        // property ID. Stripping its non-digits turns G-ABCDEF1234 into 1234,
        // which is a perfectly valid property number belonging to somebody
        // else — so the report would succeed and describe the wrong business.
        // Refuse it by name rather than quietly querying a stranger.
        if ( preg_match( '/^G-/i', $raw ) ) {
            return array( 'error' => 'That is a measurement ID (' . $raw . '), not a property ID. '
                . 'The property ID is the plain number under Analytics → Admin → Property details.' );
        }
        if ( preg_match( '/^(UA|GTM)-/i', $raw ) ) {
            return array( 'error' => 'That is a ' . strtoupper( substr( $raw, 0, 2 ) )
                . ' ID, not a GA4 property ID. Use the plain number under '
                . 'Analytics → Admin → Property details.' );
        }

        $property_id = preg_replace( '/[^0-9]/', '', $raw );
        if ( ! $property_id ) return array( 'error' => 'No GA4 property ID for this client.' );
        // Anything that still carried letters or punctuation was not a bare
        // property number, and the digits left over are not a safe guess.
        if ( $property_id !== $raw ) {
            return array( 'error' => 'GA4 property ID "' . $raw . '" is not a plain number. '
                . 'Use only the digits shown under Analytics → Admin → Property details.' );
        }
        $token = self::token();
        if ( is_array( $token ) ) return $token; // error

        $days = max( 1, min( 365, intval( $days ) ) );

        // Google renamed `conversions` to `keyEvents` in May 2024 and marked the
        // old name deprecated. Both are still accepted today, but one of them
        // will stop being — and a rejected metric name fails the WHOLE report,
        // so sessions and users would disappear along with it. Ask for the
        // current name, and if this property will not serve it, ask again with
        // the old one. Whichever answers, the shape below is the same.
        $attempt = function ( $metric ) use ( $property_id, $token, $days ) {
            $body = array(
                'dateRanges' => array( array( 'startDate' => "{$days}daysAgo", 'endDate' => 'today' ) ),
                'dimensions' => array( array( 'name' => 'sessionDefaultChannelGroup' ) ),
                'metrics'    => array(
                    array( 'name' => 'sessions' ), array( 'name' => 'totalUsers' ),
                    array( 'name' => 'screenPageViews' ), array( 'name' => $metric ),
                ),
                'orderBys'   => array( array( 'metric' => array( 'metricName' => 'sessions' ), 'desc' => true ) ),
                'limit'      => 12,
            );
            return wp_remote_post(
                "https://analyticsdata.googleapis.com/v1beta/properties/{$property_id}:runReport",
                array(
                    'timeout' => 30,
                    'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ),
                    'body'    => wp_json_encode( $body ),
                )
            );
        };

        $resp = $attempt( 'keyEvents' );
        if ( is_wp_error( $resp ) ) {
            return array( 'error' => 'GA4 network error: ' . $resp->get_error_message() );
        }
        $data = json_decode( wp_remote_retrieve_body( $resp ), true );

        // Retry only when the complaint is about the metric itself; a
        // permission or property error would fail the same way twice.
        $err = $data['error']['message'] ?? '';
        if ( $err && stripos( $err, 'keyEvents' ) !== false ) {
            $resp = $attempt( 'conversions' );
            if ( is_wp_error( $resp ) ) {
                return array( 'error' => 'GA4 network error: ' . $resp->get_error_message() );
            }
            $data = json_decode( wp_remote_retrieve_body( $resp ), true );
        }

        if ( isset( $data['error'] ) ) {
            return array( 'error' => 'GA4: ' . self::explain( $data['error']['message'] ?? 'error', $property_id ) );
        }

        $tot = array( 'sessions' => 0, 'users' => 0, 'pageviews' => 0, 'conversions' => 0.0 );
        $channels = array();
        foreach ( $data['rows'] ?? array() as $row ) {
            $ch  = $row['dimensionValues'][0]['value'] ?? 'Other';
            $mv  = $row['metricValues'] ?? array();
            $s   = intval( $mv[0]['value'] ?? 0 );
            $u   = intval( $mv[1]['value'] ?? 0 );
            $pv  = intval( $mv[2]['value'] ?? 0 );
            $cv  = round( floatval( $mv[3]['value'] ?? 0 ), 1 );
            $tot['sessions'] += $s; $tot['users'] += $u; $tot['pageviews'] += $pv; $tot['conversions'] += $cv;
            $channels[] = array( 'channel' => $ch, 'sessions' => $s, 'users' => $u, 'conversions' => $cv );
        }
        return array(
            'property_id' => $property_id, 'period_days' => $days,
            'totals' => $tot, 'by_channel' => $channels,
        );
    }

    // ── Search Console ─────────────────────────────────────────────────────
    public static function gsc_summary( $site, $days = 28 ) {
        $site = trim( (string) $site );
        if ( ! $site ) return array( 'error' => 'No Search Console site for this client.' );
        // Normalize to a GSC property identifier.
        if ( strpos( $site, 'sc-domain:' ) !== 0 && strpos( $site, 'http' ) !== 0 ) {
            $domain = preg_replace( '#^www\.#', '', preg_replace( '#/.*$#', '', $site ) );
            $site = 'sc-domain:' . $domain;
        }
        $token = self::token();
        if ( is_array( $token ) ) return $token;

        $days  = max( 1, min( 480, intval( $days ) ) );
        $start = date( 'Y-m-d', strtotime( "-{$days} days" ) );
        $end   = date( 'Y-m-d', strtotime( '-1 day' ) );
        $url   = 'https://searchconsole.googleapis.com/webmasters/v3/sites/' . rawurlencode( $site ) . '/searchAnalytics/query';

        // Top queries
        $q = self::gsc_query( $url, $token, array(
            'startDate' => $start, 'endDate' => $end, 'dimensions' => array( 'query' ), 'rowLimit' => 15,
        ) );
        if ( isset( $q['error'] ) ) return $q;

        $queries = array(); $tot = array( 'clicks' => 0, 'impressions' => 0 );
        foreach ( $q['rows'] ?? array() as $r ) {
            $queries[] = array(
                'query'       => $r['keys'][0] ?? '',
                'clicks'      => intval( $r['clicks'] ?? 0 ),
                'impressions' => intval( $r['impressions'] ?? 0 ),
                'ctr'         => round( floatval( $r['ctr'] ?? 0 ) * 100, 1 ),
                'position'    => round( floatval( $r['position'] ?? 0 ), 1 ),
            );
        }
        // Site totals (no dimensions)
        $t = self::gsc_query( $url, $token, array( 'startDate' => $start, 'endDate' => $end ) );
        if ( ! isset( $t['error'] ) && ! empty( $t['rows'][0] ) ) {
            $tot['clicks']      = intval( $t['rows'][0]['clicks'] ?? 0 );
            $tot['impressions'] = intval( $t['rows'][0]['impressions'] ?? 0 );
            $tot['avg_ctr']     = round( floatval( $t['rows'][0]['ctr'] ?? 0 ) * 100, 1 );
            $tot['avg_position']= round( floatval( $t['rows'][0]['position'] ?? 0 ), 1 );
        }
        return array( 'site' => $site, 'period' => "{$start} to {$end}", 'totals' => $tot, 'top_queries' => $queries );
    }

    private static function gsc_query( $url, $token, $body ) {
        $resp = wp_remote_post( $url, array(
            'timeout' => 30,
            'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( $body ),
        ) );
        if ( is_wp_error( $resp ) ) return array( 'error' => 'Search Console network error: ' . $resp->get_error_message() );
        $code = wp_remote_retrieve_response_code( $resp );
        $data = json_decode( wp_remote_retrieve_body( $resp ), true );
        if ( $code === 403 ) {
            $email = self::service_account_email();
            return array( 'error' => 'Search Console refused access. '
                . ( $email
                    ? "Open the property in Search Console → Settings → Users and permissions and add {$email}."
                    : 'No service-account JSON is configured yet — add it under Integrations first.' ) );
        }
        if ( isset( $data['error'] ) ) return array( 'error' => 'Search Console: ' . ( $data['error']['message'] ?? 'error' ) );
        return $data ?: array();
    }
}
