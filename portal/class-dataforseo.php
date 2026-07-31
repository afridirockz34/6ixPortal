<?php
/**
 * Six_DataForSEO — thin client over the DataForSEO REST API.
 *
 * Exposes the endpoints the AI Strategist needs as tools: keyword overview
 * (volume/CPC), keyword ideas, keyword difficulty, a domain's ranked keywords,
 * competitor domains, live SERP, and an on-page snapshot. Every method returns a
 * COMPACT, already-summarised array (never the raw multi-KB response) so it can
 * be fed straight into an LLM tool result without blowing the token budget.
 *
 * Credentials: options six_dataforseo_login / six_dataforseo_password.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Six_DataForSEO {

    const BASE = 'https://api.dataforseo.com/v3';

    public static function configured() {
        return get_option( 'six_dataforseo_login', '' ) && get_option( 'six_dataforseo_password', '' );
    }

    /** Low-level POST. $tasks is an array of task objects. Returns tasks[].result or WP_Error-ish array. */
    private static function post( $path, array $tasks, $timeout = 30 ) {
        $login    = get_option( 'six_dataforseo_login', '' );
        $password = get_option( 'six_dataforseo_password', '' );
        if ( ! $login || ! $password ) {
            return array( 'error' => 'DataForSEO credentials are not configured.' );
        }
        $resp = wp_remote_post( self::BASE . $path, array(
            'timeout' => $timeout,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( "{$login}:{$password}" ),
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode( $tasks ),
        ) );
        if ( is_wp_error( $resp ) ) return array( 'error' => 'Network error: ' . $resp->get_error_message() );

        $code = wp_remote_retrieve_response_code( $resp );
        $data = json_decode( wp_remote_retrieve_body( $resp ), true );
        if ( $code === 401 ) {
            return array( 'error' => 'DataForSEO auth failed (HTTP 401). The login/password combination was rejected. In 6ix Portal → Integrations, set the login to your DataForSEO account email and the password to your API password from the DataForSEO dashboard (API Access → API password) — this is not your website login. Re-save with no leading/trailing spaces.' );
        }
        if ( $code !== 200 || ( $data['status_code'] ?? 0 ) !== 20000 ) {
            return array( 'error' => "DataForSEO error (HTTP {$code}, status " . ( $data['status_code'] ?? '?' ) . '): ' . ( $data['status_message'] ?? 'unknown' ) );
        }
        // Return the first task's result set (we send one task per call).
        $task = $data['tasks'][0] ?? array();
        if ( ( $task['status_code'] ?? 0 ) !== 20000 ) {
            return array( 'error' => 'DataForSEO task error: ' . ( $task['status_message'] ?? 'unknown' ) );
        }
        return array( 'result' => $task['result'] ?? array() );
    }

    /** Normalise a location string to a DataForSEO location_name. */
    private static function loc( $location ) {
        $location = trim( (string) $location );
        if ( $location === '' ) return 'United States';
        $city = strtolower( trim( preg_replace( '/,.*$/', '', $location ) ) );
        $map = array(
            'toronto'=>'Toronto,Ontario,Canada','mississauga'=>'Mississauga,Ontario,Canada',
            'vancouver'=>'Vancouver,British Columbia,Canada','calgary'=>'Calgary,Alberta,Canada',
            'ottawa'=>'Ottawa,Ontario,Canada','edmonton'=>'Edmonton,Alberta,Canada',
            'montreal'=>'Montreal,Quebec,Canada','new york'=>'New York,New York,United States',
            'los angeles'=>'Los Angeles,California,United States','chicago'=>'Chicago,Illinois,United States',
            'houston'=>'Houston,Texas,United States','phoenix'=>'Phoenix,Arizona,United States',
            'miami'=>'Miami,Florida,United States','dallas'=>'Dallas,Texas,United States',
        );
        if ( isset( $map[ $city ] ) ) return $map[ $city ];
        // Country-level fallbacks
        if ( preg_match( '/canada/i', $location ) ) return 'Canada';
        if ( preg_match( '/united kingdom|uk|england/i', $location ) ) return 'United Kingdom';
        if ( preg_match( '/australia/i', $location ) ) return 'Australia';
        return 'United States';
    }

    private static function clean_kw( $keywords ) {
        if ( is_string( $keywords ) ) $keywords = preg_split( '/[\n,]+/', $keywords );
        $keywords = array_values( array_unique( array_filter( array_map( function ( $k ) {
            return trim( strtolower( (string) $k ) );
        }, (array) $keywords ) ) ) );
        return $keywords;
    }

    // ── Keyword volume / CPC / competition ─────────────────────────────────
    public static function keyword_overview( $keywords, $location = '' ) {
        $keywords = array_slice( self::clean_kw( $keywords ), 0, 200 );
        if ( ! $keywords ) return array( 'error' => 'No keywords provided.' );
        $res = self::post( '/keywords_data/google_ads/search_volume/live', array( array(
            'keywords' => $keywords, 'location_name' => self::loc( $location ), 'language_name' => 'English',
        ) ) );
        if ( isset( $res['error'] ) ) return $res;
        $rows = array();
        foreach ( $res['result'] ?? array() as $r ) {
            $rows[] = array(
                'keyword'     => $r['keyword'] ?? '',
                'volume'      => intval( $r['search_volume'] ?? 0 ),
                'cpc'         => round( floatval( $r['cpc'] ?? 0 ), 2 ),
                'competition' => $r['competition'] ?? null,
            );
        }
        usort( $rows, fn( $a, $b ) => $b['volume'] <=> $a['volume'] );
        return array( 'location' => self::loc( $location ), 'keywords' => $rows );
    }

    // ── Keyword ideas (DataForSEO Labs) ────────────────────────────────────
    public static function keyword_ideas( $seed_keywords, $location = '', $limit = 50 ) {
        $seed = array_slice( self::clean_kw( $seed_keywords ), 0, 20 );
        if ( ! $seed ) return array( 'error' => 'No seed keywords provided.' );
        $res = self::post( '/dataforseo_labs/google/keyword_ideas/live', array( array(
            'keywords' => $seed, 'location_name' => self::loc( $location ), 'language_name' => 'English',
            'limit' => intval( $limit ), 'order_by' => array( 'keyword_info.search_volume,desc' ),
        ) ) );
        if ( isset( $res['error'] ) ) return $res;
        $items = $res['result'][0]['items'] ?? array();
        $rows = array();
        foreach ( $items as $it ) {
            $ki = $it['keyword_info'] ?? array();
            $rows[] = array(
                'keyword'    => $it['keyword'] ?? '',
                'volume'     => intval( $ki['search_volume'] ?? 0 ),
                'cpc'        => round( floatval( $ki['cpc'] ?? 0 ), 2 ),
                'difficulty' => $it['keyword_properties']['keyword_difficulty'] ?? null,
            );
        }
        return array( 'location' => self::loc( $location ), 'ideas' => $rows );
    }

    // ── Keyword difficulty (Labs, bulk) ────────────────────────────────────
    public static function keyword_difficulty( $keywords, $location = '' ) {
        $keywords = array_slice( self::clean_kw( $keywords ), 0, 100 );
        if ( ! $keywords ) return array( 'error' => 'No keywords provided.' );
        $res = self::post( '/dataforseo_labs/google/bulk_keyword_difficulty/live', array( array(
            'keywords' => $keywords, 'location_name' => self::loc( $location ), 'language_name' => 'English',
        ) ) );
        if ( isset( $res['error'] ) ) return $res;
        $rows = array();
        foreach ( $res['result'][0]['items'] ?? array() as $it ) {
            $rows[] = array( 'keyword' => $it['keyword'] ?? '', 'difficulty' => $it['keyword_difficulty'] ?? null );
        }
        return array( 'keywords' => $rows );
    }

    // ── A domain's ranked keywords (Labs) ──────────────────────────────────
    public static function ranked_keywords( $domain, $location = '', $limit = 50 ) {
        $domain = self::domain( $domain );
        if ( ! $domain ) return array( 'error' => 'No domain provided.' );
        $res = self::post( '/dataforseo_labs/google/ranked_keywords/live', array( array(
            'target' => $domain, 'location_name' => self::loc( $location ), 'language_name' => 'English',
            'limit' => intval( $limit ), 'order_by' => array( 'keyword_data.keyword_info.search_volume,desc' ),
        ) ) );
        if ( isset( $res['error'] ) ) return $res;
        $rows = array();
        foreach ( $res['result'][0]['items'] ?? array() as $it ) {
            $kd  = $it['keyword_data'] ?? array();
            $ki  = $kd['keyword_info'] ?? array();
            $se  = $it['ranked_serp_element']['serp_item'] ?? array();
            $rows[] = array(
                'keyword'  => $kd['keyword'] ?? '',
                'position' => intval( $se['rank_absolute'] ?? 0 ),
                'volume'   => intval( $ki['search_volume'] ?? 0 ),
                'url'      => $se['url'] ?? '',
            );
        }
        return array( 'domain' => $domain, 'ranked_keywords' => $rows );
    }

    // ── Competitor domains (Labs) ──────────────────────────────────────────
    public static function competitors( $domain, $location = '', $limit = 15 ) {
        $domain = self::domain( $domain );
        if ( ! $domain ) return array( 'error' => 'No domain provided.' );
        $res = self::post( '/dataforseo_labs/google/competitors_domain/live', array( array(
            'target' => $domain, 'location_name' => self::loc( $location ), 'language_name' => 'English',
            'limit' => intval( $limit ),
        ) ) );
        if ( isset( $res['error'] ) ) return $res;
        $rows = array();
        foreach ( $res['result'][0]['items'] ?? array() as $it ) {
            $m = $it['metrics']['organic'] ?? array();
            $rows[] = array(
                'domain'         => $it['domain'] ?? '',
                'common_keywords'=> intval( $it['intersections'] ?? 0 ),
                'organic_traffic'=> intval( $m['etv'] ?? 0 ),
                'organic_keywords'=> intval( $m['count'] ?? 0 ),
            );
        }
        return array( 'target' => $domain, 'competitors' => $rows );
    }

    // ── Live Google SERP for a keyword ─────────────────────────────────────
    public static function serp( $keyword, $location = '', $depth = 10 ) {
        $keyword = trim( (string) $keyword );
        if ( $keyword === '' ) return array( 'error' => 'No keyword provided.' );
        $res = self::post( '/serp/google/organic/live/advanced', array( array(
            'keyword' => $keyword, 'location_name' => self::loc( $location ), 'language_name' => 'English',
            'depth' => intval( $depth ),
        ) ), 45 );
        if ( isset( $res['error'] ) ) return $res;
        $items = $res['result'][0]['items'] ?? array();
        $rows = array();
        foreach ( $items as $it ) {
            if ( ( $it['type'] ?? '' ) !== 'organic' ) continue;
            $rows[] = array(
                'position' => intval( $it['rank_absolute'] ?? 0 ),
                'title'    => $it['title'] ?? '',
                'url'      => $it['url'] ?? '',
                'domain'   => $it['domain'] ?? '',
            );
            if ( count( $rows ) >= $depth ) break;
        }
        return array( 'keyword' => $keyword, 'results' => $rows );
    }

    // ── On-page snapshot (instant, single URL) ─────────────────────────────
    public static function onpage( $url ) {
        $url = esc_url_raw( $url );
        if ( ! $url ) return array( 'error' => 'No URL provided.' );
        $res = self::post( '/on_page/instant_pages', array( array(
            'url' => $url, 'enable_javascript' => false,
        ) ), 45 );
        if ( isset( $res['error'] ) ) return $res;
        $item = $res['result'][0]['items'][0] ?? array();
        if ( ! $item ) return array( 'error' => 'No on-page data returned.' );
        $meta = $item['meta'] ?? array();
        $checks = $item['checks'] ?? array();
        return array(
            'url'              => $url,
            'status_code'      => intval( $item['status_code'] ?? 0 ),
            'title'            => $meta['title'] ?? '',
            'title_length'     => intval( $meta['title_length'] ?? 0 ),
            'description'      => $meta['description'] ?? '',
            'description_length'=> intval( $meta['description_length'] ?? 0 ),
            'h1'               => $meta['htags']['h1'] ?? array(),
            'word_count'       => intval( $meta['content']['plain_text_word_count'] ?? 0 ),
            'internal_links'   => intval( $meta['internal_links_count'] ?? 0 ),
            'external_links'   => intval( $meta['external_links_count'] ?? 0 ),
            'images_without_alt'=> intval( $meta['images_count'] ?? 0 ) - intval( $meta['images_size'] ?? 0 ),
            'load_time_ms'     => intval( $item['page_timing']['dom_complete'] ?? 0 ),
            'issues'           => array_keys( array_filter( $checks, fn( $v ) => $v === true || $v === 1 ) ),
        );
    }

    private static function domain( $d ) {
        $d = trim( strtolower( (string) $d ) );
        $d = preg_replace( '#^https?://#', '', $d );
        $d = preg_replace( '#^www\.#', '', $d );
        $d = preg_replace( '#/.*$#', '', $d );
        return $d;
    }
}
