<?php
/**
 * Six_Meta — Meta (Facebook / Instagram) Ads insights client.
 *
 * Agency-token model: a single long-lived System User token (option
 * six_meta_access_token) created in the agency's Meta Business Manager can read
 * every client ad account that lives under that Business. Per client the advisor
 * only stores the ad account ID (user_meta six_meta_ad_account_id) — this class
 * pairs the two to pull live spend / leads / ROAS with no per-client login.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Six_Meta {

    // Graph/Marketing API version. Meta sunsets versions ~2 years after release;
    // bump this one constant when a version is retired.
    const API_VERSION = 'v23.0';

    private static $last_error = '';
    public static function get_last_error() { return self::$last_error; }

    /** True when the agency System User token is configured. */
    public static function configured() {
        return (bool) get_option( 'six_meta_access_token', '' );
    }

    /** Normalise an ad account ID to the act_<digits> form Meta expects. */
    public static function acct( $id ) {
        $digits = preg_replace( '/[^0-9]/', '', str_replace( 'act_', '', (string) $id ) );
        return $digits ? 'act_' . $digits : '';
    }

    /** Sum action values whose type matches any needle (e.g. 'lead', 'purchase'). */
    private static function sum_actions( $actions, array $needles ) {
        $sum = 0.0;
        foreach ( (array) $actions as $a ) {
            $type = (string) ( $a['action_type'] ?? '' );
            foreach ( $needles as $n ) {
                if ( strpos( $type, $n ) !== false ) { $sum += floatval( $a['value'] ?? 0 ); break; }
            }
        }
        return $sum;
    }

    /** Shape a raw insights row into a tidy totals array. */
    private static function shape_totals( $row ) {
        $leads     = self::sum_actions( $row['actions'] ?? array(), array( 'lead' ) );
        $purchases = self::sum_actions( $row['actions'] ?? array(), array( 'purchase' ) );
        $roas = 0.0;
        foreach ( (array) ( $row['purchase_roas'] ?? array() ) as $r ) { $roas = floatval( $r['value'] ?? 0 ); break; }
        return array(
            'spend'       => round( floatval( $row['spend'] ?? 0 ), 2 ),
            'impressions' => intval( $row['impressions'] ?? 0 ),
            'clicks'      => intval( $row['clicks'] ?? 0 ),
            'ctr'         => round( floatval( $row['ctr'] ?? 0 ), 2 ),
            'cpc'         => round( floatval( $row['cpc'] ?? 0 ), 2 ),
            'cpm'         => round( floatval( $row['cpm'] ?? 0 ), 2 ),
            'leads'       => intval( round( $leads ) ),
            'purchases'   => intval( round( $purchases ) ),
            'roas'        => round( $roas, 2 ),
        );
    }

    private static function fields() {
        return 'spend,impressions,clicks,ctr,cpc,cpm,reach,actions,action_values,purchase_roas';
    }

    private static function get( $path, array $params ) {
        $token = get_option( 'six_meta_access_token', '' );
        if ( ! $token ) return array( 'error' => 'Meta System User token not set. Add it in 6ix Portal → Integrations.' );
        $params['access_token'] = $token;
        $url  = 'https://graph.facebook.com/' . self::API_VERSION . '/' . ltrim( $path, '/' ) . '?' . http_build_query( $params );
        $resp = wp_remote_get( $url, array( 'timeout' => 30 ) );
        if ( is_wp_error( $resp ) ) return array( 'error' => 'Meta network error: ' . $resp->get_error_message() );
        $data = json_decode( wp_remote_retrieve_body( $resp ), true );
        if ( isset( $data['error'] ) ) {
            self::$last_error = $data['error']['message'] ?? 'Meta API error';
            return array( 'error' => 'Meta API: ' . self::$last_error );
        }
        return $data;
    }

    /** Account-level insights over the last $days. */
    public static function account_insights( $account_id, $days = 30 ) {
        $acct = self::acct( $account_id );
        if ( ! $acct ) return array( 'error' => 'No Meta ad account ID.' );
        $days = max( 1, min( 365, intval( $days ) ) );
        $data = self::get( $acct . '/insights', array(
            'fields'     => self::fields(),
            'level'      => 'account',
            'time_range' => wp_json_encode( array(
                'since' => gmdate( 'Y-m-d', time() - $days * DAY_IN_SECONDS ),
                'until' => gmdate( 'Y-m-d' ),
            ) ),
        ) );
        if ( isset( $data['error'] ) ) return $data;
        $row = $data['data'][0] ?? array();
        if ( ! $row ) return array( 'account' => $acct, 'period_days' => $days, 'totals' => self::shape_totals( array() ), 'note' => 'No delivery in this period.' );
        return array( 'account' => $acct, 'period_days' => $days, 'totals' => self::shape_totals( $row ) );
    }

    /** Per-campaign breakdown (top by spend). */
    public static function campaign_insights( $account_id, $days = 30, $limit = 25 ) {
        $acct = self::acct( $account_id );
        if ( ! $acct ) return array( 'error' => 'No Meta ad account ID.' );
        $days = max( 1, min( 365, intval( $days ) ) );
        $data = self::get( $acct . '/insights', array(
            'fields'     => 'campaign_name,' . self::fields(),
            'level'      => 'campaign',
            'limit'      => max( 1, min( 100, intval( $limit ) ) ),
            'time_range' => wp_json_encode( array(
                'since' => gmdate( 'Y-m-d', time() - $days * DAY_IN_SECONDS ),
                'until' => gmdate( 'Y-m-d' ),
            ) ),
        ) );
        if ( isset( $data['error'] ) ) return $data;
        $out = array();
        foreach ( (array) ( $data['data'] ?? array() ) as $row ) {
            $t = self::shape_totals( $row );
            $t['campaign'] = (string) ( $row['campaign_name'] ?? '—' );
            $out[] = $t;
        }
        usort( $out, function ( $a, $b ) { return $b['spend'] <=> $a['spend']; } );
        return array_slice( $out, 0, $limit );
    }

    /** Full report for a client: account totals + top campaigns. For the AI tool. */
    public static function client_report( $client_id, $days = 30 ) {
        $acct = get_user_meta( $client_id, 'six_meta_ad_account_id', true );
        if ( ! $acct ) return array( 'error' => 'No Meta ad account connected for this client. Add it in the Data Sources tab.' );
        $summary = self::account_insights( $acct, $days );
        if ( isset( $summary['error'] ) ) return $summary;
        $summary['campaigns'] = self::campaign_insights( $acct, $days, 10 );
        return $summary;
    }
}
