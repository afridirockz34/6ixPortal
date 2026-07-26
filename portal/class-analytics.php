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
        $property_id = preg_replace( '/[^0-9]/', '', (string) $property_id );
        if ( ! $property_id ) return array( 'error' => 'No GA4 property ID for this client.' );
        $token = self::token();
        if ( is_array( $token ) ) return $token; // error

        $days = max( 1, min( 365, intval( $days ) ) );
        $body = array(
            'dateRanges' => array( array( 'startDate' => "{$days}daysAgo", 'endDate' => 'today' ) ),
            'dimensions' => array( array( 'name' => 'sessionDefaultChannelGroup' ) ),
            'metrics'    => array(
                array( 'name' => 'sessions' ), array( 'name' => 'totalUsers' ),
                array( 'name' => 'screenPageViews' ), array( 'name' => 'conversions' ),
            ),
            'orderBys'   => array( array( 'metric' => array( 'metricName' => 'sessions' ), 'desc' => true ) ),
            'limit'      => 12,
        );
        $resp = wp_remote_post(
            "https://analyticsdata.googleapis.com/v1beta/properties/{$property_id}:runReport",
            array(
                'timeout' => 30,
                'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ),
                'body'    => wp_json_encode( $body ),
            )
        );
        if ( is_wp_error( $resp ) ) return array( 'error' => 'GA4 network error: ' . $resp->get_error_message() );
        $data = json_decode( wp_remote_retrieve_body( $resp ), true );
        if ( isset( $data['error'] ) ) return array( 'error' => 'GA4 API: ' . ( $data['error']['message'] ?? 'error' ) );

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
        // Normalise to a GSC property identifier.
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
        if ( $code === 403 ) return array( 'error' => 'Search Console 403 — grant the service-account email access to this property in Search Console settings.' );
        if ( isset( $data['error'] ) ) return array( 'error' => 'Search Console: ' . ( $data['error']['message'] ?? 'error' ) );
        return $data ?: array();
    }
}
