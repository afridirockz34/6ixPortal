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

    // ── PageSpeed Insights ─────────────────────────────────────────────────
    /**
     * Google's own measurement of a page, for anybody's URL.
     *
     * Takes a URL rather than a client, because the commonest use is a
     * prospect we have no accounts for at all. Google's tool on purpose: the
     * reader checks our figure at pagespeed.web.dev and gets the same number,
     * which a timing run of our own could never promise.
     */
    public static function pagespeed( $url, $strategy = 'mobile' ) {
        $url = trim( (string) $url );
        if ( ! $url ) return array( 'error' => 'No URL to measure.' );
        if ( ! preg_match( '#^https?://#i', $url ) ) $url = 'https://' . $url;
        if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return array( 'error' => 'That is not a URL this can measure: ' . $url );
        }
        $strategy = in_array( strtolower( $strategy ), array( 'mobile', 'desktop' ), true )
            ? strtolower( $strategy ) : 'mobile';

        $args = array(
            'url'      => $url,
            'strategy' => $strategy,
            'category' => 'performance',
        );
        $key = get_option( 'six_pagespeed_api_key', '' );
        if ( $key ) $args['key'] = $key;

        $resp = wp_remote_get(
            'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?' . http_build_query( $args ),
            array( 'timeout' => 60 )
        );
        if ( is_wp_error( $resp ) ) {
            return array( 'error' => 'PageSpeed network error: ' . $resp->get_error_message() );
        }
        $code = wp_remote_retrieve_response_code( $resp );
        $data = json_decode( wp_remote_retrieve_body( $resp ), true );
        if ( $code === 429 || ( isset( $data['error'] ) && (int) ( $data['error']['code'] ?? 0 ) === 429 ) ) {
            return array( 'error' => $key
                ? 'PageSpeed refused the request as over quota, even with a key. Wait and retry.'
                : 'PageSpeed quota exhausted. The anonymous quota is shared across the whole internet '
                  . 'and is normally spent — add a key under 6ix Portal → Integrations → Google PageSpeed Insights.' );
        }
        if ( isset( $data['error'] ) ) {
            return array( 'error' => 'PageSpeed: ' . ( $data['error']['message'] ?? 'error' ) );
        }

        $lh      = $data['lighthouseResult'] ?? array();
        $audits  = $lh['audits'] ?? array();
        $metrics = array();
        foreach ( array(
            'first-contentful-paint' => 'First Contentful Paint',
            'largest-contentful-paint' => 'Largest Contentful Paint',
            'total-blocking-time'    => 'Total Blocking Time',
            'cumulative-layout-shift' => 'Cumulative Layout Shift',
            'speed-index'            => 'Speed Index',
        ) as $k => $label ) {
            if ( ! isset( $audits[ $k ] ) ) continue;
            $metrics[] = array(
                'id'      => $k,
                'label'   => $label,
                'value'   => $audits[ $k ]['displayValue'] ?? '',
                'score'   => isset( $audits[ $k ]['score'] ) ? (float) $audits[ $k ]['score'] : null,
                'numeric' => $audits[ $k ]['numericValue'] ?? null,
            );
        }
        $score = isset( $lh['categories']['performance']['score'] )
            ? (int) round( 100 * (float) $lh['categories']['performance']['score'] ) : null;

        return array(
            'url'        => $lh['finalUrl'] ?? $url,
            'strategy'   => $strategy,
            'score'      => $score,
            'metrics'    => $metrics,
            'fetched'    => $lh['fetchTime'] ?? '',
            'source'     => 'Google PageSpeed Insights',
            'note'       => 'A lab measurement of one load, not an average of real visitors. '
                          . 'Anyone can reproduce it at pagespeed.web.dev with the same URL.',
        );
    }

    // ── Search Console ─────────────────────────────────────────────────────
    public static function gsc_summary( $site, $days = 28 ) {
        // Resolve against the properties Google will actually serve, rather
        // than guessing an identifier's shape. See resolve_gsc_site().
        $res = self::resolve_gsc_site( $site );
        if ( isset( $res['error'] ) ) return $res;
        $site     = $res['site'];
        $res_note = $res['note'] ?? '';

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
        $out = array( 'site' => $site, 'period' => "{$start} to {$end}", 'totals' => $tot, 'top_queries' => $queries );
        if ( $res_note ) $out['note'] = $res_note;
        return $out;
    }

    /**
     * Which Search Console properties can this service account actually see?
     *
     * The decisive diagnostic. A 403 from Search Console has three unrelated
     * causes that all used to print the same sentence: the API is not enabled
     * on the Cloud project, the property identifier is not the one Google
     * knows, or the account genuinely has not been added. This call separates
     * them — if it errors, the problem is the project or the key; if it
     * returns a list, the problem is which identifier we are asking for, and
     * the list says what to ask for instead.
     */
    public static function gsc_sites( $force = false ) {
        if ( ! $force ) {
            $cached = get_transient( 'six_gsc_sites' );
            if ( is_array( $cached ) ) return $cached;
        }
        $token = self::token();
        if ( is_array( $token ) ) return $token;

        $resp = wp_remote_get( 'https://searchconsole.googleapis.com/webmasters/v3/sites', array(
            'timeout' => 20,
            'headers' => array( 'Authorization' => 'Bearer ' . $token ),
        ) );
        if ( is_wp_error( $resp ) ) {
            return array( 'error' => 'Search Console network error: ' . $resp->get_error_message() );
        }
        $data = json_decode( wp_remote_retrieve_body( $resp ), true );
        if ( isset( $data['error'] ) ) {
            return array( 'error' => self::explain_gsc( $data['error'] ) );
        }
        $out = array( 'sites' => array(), 'permissions' => array() );
        foreach ( (array) ( $data['siteEntry'] ?? array() ) as $e ) {
            if ( empty( $e['siteUrl'] ) ) continue;
            $out['sites'][] = $e['siteUrl'];
            $out['permissions'][ $e['siteUrl'] ] = $e['permissionLevel'] ?? '';
        }
        set_transient( 'six_gsc_sites', $out, 300 );
        return $out;
    }

    /** The bare host behind any Search Console identifier. */
    private static function gsc_host( $id ) {
        $id = trim( (string) $id );
        $id = preg_replace( '#^sc-domain:#i', '', $id );
        $id = preg_replace( '#^https?://#i', '', $id );
        $id = preg_replace( '#/.*$#', '', $id );
        return strtolower( preg_replace( '#^www\.#i', '', $id ) );
    }

    /**
     * Turn what the advisor saved into an identifier Google will accept.
     *
     * Search Console identifiers are exact strings, not URLs that get tidied
     * up: `sc-domain:example.ca`, `https://example.ca/` and
     * `https://example.ca/shop/` are three different properties, and asking
     * for one you cannot see returns the same 403 as not being invited to one
     * you can. So rather than guess the shape, ask Google what exists and
     * match against that.
     */
    public static function resolve_gsc_site( $site ) {
        $site = trim( (string) $site );
        if ( ! $site ) return array( 'error' => 'No Search Console site for this client.' );

        $list = self::gsc_sites();
        if ( isset( $list['error'] ) ) {
            // Cannot enumerate — fall back to the old normalisation so the
            // query still runs, and carry the reason for the report.
            $guess = $site;
            if ( strpos( $site, 'sc-domain:' ) !== 0 && strpos( $site, 'http' ) !== 0 ) {
                $guess = 'sc-domain:' . self::gsc_host( $site );
            }
            return array( 'site' => $guess, 'verified' => false, 'note' => $list['error'] );
        }

        $have = $list['sites'];
        if ( ! $have ) {
            return array( 'error' => 'The service account ' . ( self::service_account_email() ?: '' )
                . ' can see no Search Console properties at all. Adding it to a property takes effect '
                . 'immediately, so this usually means it was added to a different Google account, or '
                . 'added as a pending invitation that has not been accepted.' );
        }
        if ( in_array( $site, $have, true ) ) {
            return array( 'site' => $site, 'verified' => true );
        }

        // Same host, whatever shape it was saved in.
        $host  = self::gsc_host( $site );
        $cands = array();
        foreach ( $have as $h ) {
            if ( self::gsc_host( $h ) === $host ) $cands[] = $h;
        }
        if ( $cands ) {
            // A domain property covers every path, so prefer it; then https
            // over http; then the shortest path, which is the most inclusive.
            usort( $cands, function ( $a, $b ) {
                $sa = ( strpos( $a, 'sc-domain:' ) === 0 ) ? 0 : ( strpos( $a, 'https://' ) === 0 ? 1 : 2 );
                $sb = ( strpos( $b, 'sc-domain:' ) === 0 ) ? 0 : ( strpos( $b, 'https://' ) === 0 ? 1 : 2 );
                return $sa === $sb ? strlen( $a ) - strlen( $b ) : $sa - $sb;
            } );
            return array(
                'site' => $cands[0], 'verified' => true,
                'note' => $site === $cands[0] ? '' : 'Saved as "' . $site . '"; using "' . $cands[0]
                          . '", which is the property Search Console actually has for that domain.',
            );
        }

        return array( 'error' => 'No Search Console property matches "' . $site . '". '
            . 'The service account ' . ( self::service_account_email() ?: '' ) . ' can currently see: '
            . implode( ', ', array_slice( $have, 0, 12 ) )
            . ( count( $have ) > 12 ? ' (and ' . ( count( $have ) - 12 ) . ' more)' : '' )
            . '. Save one of those exactly as written — the identifier is literal, so '
            . 'https://site.ca/ and sc-domain:site.ca are different properties.' );
    }

    /**
     * A Search Console API error, said in terms of what to do about it.
     *
     * The one that matters most: a project with the GA4 Data API enabled but
     * not the Search Console API returns 403 here while GA4 keeps working, and
     * no amount of adding users in Search Console will ever fix it.
     */
    private static function explain_gsc( $err ) {
        $msg    = is_array( $err ) ? ( $err['message'] ?? 'error' ) : (string) $err;
        $status = is_array( $err ) ? ( $err['status'] ?? '' ) : '';
        $reason = '';
        if ( is_array( $err ) && ! empty( $err['errors'][0]['reason'] ) ) {
            $reason = $err['errors'][0]['reason'];
        }
        $email = self::service_account_email();

        if ( $status === 'PERMISSION_DENIED' && stripos( $msg, 'has not been used in project' ) !== false
             || $reason === 'accessNotConfigured' || stripos( $msg, 'is disabled' ) !== false ) {
            return 'The Search Console API is not enabled on the Google Cloud project this '
                . 'service account belongs to. GA4 works because the Analytics Data API is enabled '
                . 'separately — they are two different APIs. Enable "Google Search Console API" in '
                . 'that project and retry; adding users in Search Console will not fix this. '
                . 'Google said: ' . $msg;
        }
        if ( $status === 'PERMISSION_DENIED' || stripos( $msg, 'permission' ) !== false ) {
            return 'Search Console refused access. '
                . ( $email
                    ? "Check that {$email} is listed under Settings → Users and permissions on the "
                      . 'exact property being requested, and that the invitation was not left pending. '
                    : 'No service-account JSON is configured yet — add it under Integrations first. ' )
                . 'Google said: ' . $msg;
        }
        return 'Search Console: ' . $msg;
    }

    private static function gsc_query( $url, $token, $body ) {
        $resp = wp_remote_post( $url, array(
            'timeout' => 30,
            'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( $body ),
        ) );
        if ( is_wp_error( $resp ) ) return array( 'error' => 'Search Console network error: ' . $resp->get_error_message() );
        $data = json_decode( wp_remote_retrieve_body( $resp ), true );
        if ( isset( $data['error'] ) ) return array( 'error' => self::explain_gsc( $data['error'] ) );
        return $data ?: array();
    }
}
