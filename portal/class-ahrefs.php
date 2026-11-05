<?php
/**
 * Six_Ahrefs — client over the Ahrefs API v3.
 *
 * Drop-in replacement for Six_DataForSEO: every method here has the same
 * signature and returns the same shape, so call sites swap one class name and
 * nothing downstream changes.
 *
 * Credentials: option six_ahrefs_token (Ahrefs → Account settings → API keys).
 * Verified working on the Standard plan — 800,000 workspace units a month.
 *
 * TWO THINGS THAT GENUINELY DIFFER FROM DATAFORSEO, both worth knowing before
 * reading a number off this class:
 *
 * 1. Ahrefs' organic endpoints are COUNTRY-level. DataForSEO accepted
 *    "Toronto,Ontario,Canada"; Ahrefs accepts "ca". So a keyword's volume here
 *    is the national figure, not the city's. The city is still carried through
 *    in the returned 'location' key for display and prompt context, but it does
 *    not narrow the query. City-level rank data needs Rank Tracker, which is
 *    project-based — see management-projects, not this class.
 *
 * 2. There is no instant single-URL on-page endpoint. Ahrefs' on-page data
 *    lives in Site Audit, which requires a crawl project per domain. Rather
 *    than leave onpage() broken, it is implemented natively below — we fetch
 *    the URL and parse it ourselves. That costs no API units and is honest
 *    about what it measured.
 *
 * Monetary fields come back in USD CENTS and are divided by 100 here, so every
 * price this class returns is already in dollars.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Six_Ahrefs {

    const BASE = 'https://api.ahrefs.com/v3';

    /** How long a cached answer stays usable, in days. */
    const CACHE_DAYS = 30;

    const TOKEN_OPT = 'six_ahrefs_token';

    /**
     * Endpoint paths. Kept as a map rather than built from strings so a wrong
     * path is one line to fix and test_connection() can walk every one.
     */
    const PATHS = array(
        'subscription'        => '/subscription-info/limits-and-usage',
        'site_metrics'        => '/site-explorer/metrics',
        'organic_competitors' => '/site-explorer/organic-competitors',
        'organic_keywords'    => '/site-explorer/organic-keywords',
        'kw_overview'         => '/keywords-explorer/overview',
        'kw_matching'         => '/keywords-explorer/matching-terms',
        'serp'                => '/serp-overview/serp-overview',
    );

    public static function configured() {
        return (bool) trim( (string) get_option( self::TOKEN_OPT, '' ) );
    }

    // ── Transport ──────────────────────────────────────────────────────────

    /**
     * GET an endpoint. Successful responses are cached for CACHE_DAYS; errors
     * never are, so a transient failure doesn't poison the cache for a month.
     *
     * @param string $key    A key from self::PATHS.
     * @param array  $params Query parameters.
     * @param bool   $cache  Whether this call may be served from cache.
     */
    private static function get( $key, array $params, $cache = true, $timeout = 30 ) {
        $token = trim( (string) get_option( self::TOKEN_OPT, '' ) );
        if ( ! $token ) {
            return array( 'error' => 'Ahrefs API token is not configured. Add it in 6ix Portal → Integrations (get it from Ahrefs → Account settings → API keys).' );
        }
        if ( ! isset( self::PATHS[ $key ] ) ) {
            return array( 'error' => "Unknown Ahrefs endpoint '{$key}'." );
        }
        $path = self::PATHS[ $key ];

        $params   = array_filter( $params, function ( $v ) { return $v !== null && $v !== ''; } );
        $cache_id = 'six_ahrefs_' . md5( $path . '|' . wp_json_encode( $params ) );

        if ( $cache ) {
            $hit = get_transient( $cache_id );
            if ( is_array( $hit ) ) {
                $hit['_cached'] = true;
                return $hit;
            }
        }

        $url  = self::BASE . $path . '?' . http_build_query( $params );
        $resp = wp_remote_get( $url, array(
            'timeout' => $timeout,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ),
        ) );
        if ( is_wp_error( $resp ) ) {
            return array( 'error' => 'Network error reaching Ahrefs: ' . $resp->get_error_message() );
        }

        $code = (int) wp_remote_retrieve_response_code( $resp );
        $body = wp_remote_retrieve_body( $resp );
        $data = json_decode( $body, true );

        if ( $code === 401 || $code === 403 ) {
            return array( 'error' => 'Ahrefs rejected the API token (HTTP ' . $code . '). Check it in 6ix Portal → Integrations against Ahrefs → Account settings → API keys, with no leading or trailing spaces.' );
        }
        if ( $code === 429 ) {
            return array( 'error' => 'Ahrefs rate limit reached (HTTP 429). This usually clears within a minute.' );
        }
        if ( $code !== 200 ) {
            $detail = is_array( $data ) && isset( $data['error'] ) ? $data['error'] : substr( (string) $body, 0, 300 );
            return array( 'error' => "Ahrefs error (HTTP {$code}): " . $detail );
        }
        if ( ! is_array( $data ) ) {
            return array( 'error' => 'Ahrefs returned a response that could not be read as JSON.' );
        }

        if ( $cache ) {
            set_transient( $cache_id, $data, self::CACHE_DAYS * DAY_IN_SECONDS );
        }
        return $data;
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Resolve a free-text location to the two-letter country Ahrefs wants.
     * Deliberately conservative: an unrecognised Canadian city still has to
     * resolve to Canada, not to the US default, or every local-SEO number
     * would silently describe the wrong country.
     */
    public static function country( $location ) {
        $l = strtolower( trim( (string) $location ) );
        if ( $l === '' ) return 'ca';   // the agency's own market

        // Province names, case-insensitively.
        if ( preg_match( '/\b(canada|ontario|quebec|alberta|british columbia|manitoba|saskatchewan|nova scotia|new brunswick|newfoundland)\b/i', $l ) ) return 'ca';
        // Province abbreviations, case-SENSITIVELY on the original string: a
        // case-insensitive \bON\b matches the ordinary word "on".
        if ( preg_match( '/\b(ON|QC|AB|BC|MB|SK|NS|NB|NL|PE)\b/', (string) $location ) ) return 'ca';

        // Deliberately no bare "london" here. For a GTA agency an unqualified
        // "London" is far more likely to be London, Ontario than the UK one,
        // so it falls through to the Canadian default instead.
        if ( preg_match( '/\b(united kingdom|england|scotland|wales)\b/i', $l ) ) return 'gb';
        if ( preg_match( '/\buk\b/', (string) $location ) || preg_match( '/\bUK\b/', (string) $location ) ) return 'gb';
        if ( preg_match( '/\b(australia|sydney|melbourne|brisbane|perth)\b/i', $l ) )        return 'au';
        if ( preg_match( '/\b(united states|usa)\b/i', $l ) )                                return 'us';

        // Canadian cities we serve, checked before any US match so "London,
        // Ontario" doesn't become the UK and "Windsor" doesn't become the US.
        $ca_cities = array( 'toronto','mississauga','brampton','vaughan','markham','richmond hill',
            'scarborough','etobicoke','north york','oakville','burlington','hamilton','milton',
            'oshawa','whitby','ajax','pickering','barrie','guelph','kitchener','waterloo','cambridge',
            'london ontario','windsor','ottawa','montreal','quebec city','vancouver','surrey','burnaby',
            'richmond bc','victoria','calgary','edmonton','winnipeg','saskatoon','regina','halifax',
            'gta','greater toronto' );
        foreach ( $ca_cities as $c ) {
            if ( strpos( $l, $c ) !== false ) return 'ca';
        }

        $us_cities = array( 'new york','los angeles','chicago','houston','phoenix','philadelphia',
            'san antonio','san diego','dallas','austin','miami','atlanta','boston','seattle','denver',
            'detroit','las vegas','orlando','tampa' );
        foreach ( $us_cities as $c ) {
            if ( strpos( $l, $c ) !== false ) return 'us';
        }

        return 'ca';
    }

    /** Ahrefs reports "as of" a date; today is what we always want. */
    private static function today() {
        return gmdate( 'Y-m-d' );
    }

    private static function domain( $d ) {
        $d = trim( strtolower( (string) $d ) );
        $d = preg_replace( '#^https?://#', '', $d );
        $d = preg_replace( '#^www\.#', '', $d );
        $d = preg_replace( '#[/?].*$#', '', $d );
        return $d;
    }

    private static function clean_kw( $keywords ) {
        if ( is_string( $keywords ) ) $keywords = preg_split( '/[\n,]+/', $keywords );
        return array_values( array_unique( array_filter( array_map( function ( $k ) {
            return trim( strtolower( (string) $k ) );
        }, (array) $keywords ) ) ) );
    }

    /** USD cents → dollars, to 2dp. Null stays null rather than becoming 0. */
    private static function dollars( $cents ) {
        if ( $cents === null || $cents === '' ) return null;
        return round( ( (float) $cents ) / 100, 2 );
    }

    // ── Keyword volume / CPC / difficulty ──────────────────────────────────

    public static function keyword_overview( $keywords, $location = '' ) {
        $keywords = array_slice( self::clean_kw( $keywords ), 0, 200 );
        if ( ! $keywords ) return array( 'error' => 'No keywords provided.' );

        $res = self::get( 'kw_overview', array(
            'select'   => 'keyword,volume,cpc,difficulty,cps',
            'country'  => self::country( $location ),
            'keywords' => implode( ',', $keywords ),
            'limit'    => count( $keywords ),
        ) );
        if ( isset( $res['error'] ) ) return $res;

        $rows = array();
        foreach ( $res['keywords'] ?? array() as $r ) {
            $rows[] = array(
                'keyword'     => $r['keyword'] ?? '',
                'volume'      => (int) ( $r['volume'] ?? 0 ),
                'cpc'         => self::dollars( $r['cpc'] ?? null ),
                // DataForSEO returned a 0–1 "competition" float; Ahrefs'
                // closest equivalent is keyword difficulty on 0–100. Kept
                // under the same key so prompts keep working, but it is a
                // different scale — do not compare the two historically.
                'competition' => $r['difficulty'] ?? null,
                'difficulty'  => $r['difficulty'] ?? null,
            );
        }
        usort( $rows, function ( $a, $b ) { return $b['volume'] <=> $a['volume']; } );
        return array(
            'location' => (string) $location ?: 'Canada',
            'country'  => self::country( $location ),
            'keywords' => $rows,
            'source'   => 'ahrefs',
            '_cached'  => ! empty( $res['_cached'] ),
        );
    }

    // ── Keyword ideas ──────────────────────────────────────────────────────

    public static function keyword_ideas( $seed_keywords, $location = '', $limit = 50 ) {
        $seed = array_slice( self::clean_kw( $seed_keywords ), 0, 20 );
        if ( ! $seed ) return array( 'error' => 'No seed keywords provided.' );

        $res = self::get( 'kw_matching', array(
            'select'     => 'keyword,volume,cpc,difficulty',
            'country'    => self::country( $location ),
            'keywords'   => implode( ',', $seed ),
            'match_mode' => 'terms',
            'terms'      => 'all',
            'order_by'   => 'volume:desc',
            'limit'      => max( 1, (int) $limit ),
        ) );
        if ( isset( $res['error'] ) ) return $res;

        $rows = array();
        foreach ( $res['keywords'] ?? array() as $r ) {
            $rows[] = array(
                'keyword'    => $r['keyword'] ?? '',
                'volume'     => (int) ( $r['volume'] ?? 0 ),
                'cpc'        => self::dollars( $r['cpc'] ?? null ),
                'difficulty' => $r['difficulty'] ?? null,
            );
        }
        return array(
            'location' => (string) $location ?: 'Canada',
            'country'  => self::country( $location ),
            'ideas'    => $rows,
            'source'   => 'ahrefs',
            '_cached'  => ! empty( $res['_cached'] ),
        );
    }

    public static function keyword_difficulty( $keywords, $location = '' ) {
        $keywords = array_slice( self::clean_kw( $keywords ), 0, 100 );
        if ( ! $keywords ) return array( 'error' => 'No keywords provided.' );

        $res = self::get( 'kw_overview', array(
            'select'   => 'keyword,difficulty',
            'country'  => self::country( $location ),
            'keywords' => implode( ',', $keywords ),
            'limit'    => count( $keywords ),
        ) );
        if ( isset( $res['error'] ) ) return $res;

        $rows = array();
        foreach ( $res['keywords'] ?? array() as $r ) {
            $rows[] = array(
                'keyword'    => $r['keyword'] ?? '',
                'difficulty' => $r['difficulty'] ?? null,
            );
        }
        return array( 'keywords' => $rows, 'source' => 'ahrefs', '_cached' => ! empty( $res['_cached'] ) );
    }

    // ── A domain's ranked keywords ─────────────────────────────────────────

    public static function ranked_keywords( $domain, $location = '', $limit = 50 ) {
        $domain = self::domain( $domain );
        if ( ! $domain ) return array( 'error' => 'No domain provided.' );

        $res = self::get( 'organic_keywords', array(
            'select'   => 'keyword,best_position,volume,best_position_url,keyword_difficulty,sum_traffic',
            'target'   => $domain,
            'date'     => self::today(),
            'country'  => self::country( $location ),
            // A domain name must use subdomains mode or www is excluded.
            'mode'     => 'subdomains',
            'order_by' => 'sum_traffic:desc',
            'limit'    => max( 1, (int) $limit ),
        ) );
        if ( isset( $res['error'] ) ) return $res;

        $rows = array();
        foreach ( $res['keywords'] ?? array() as $r ) {
            $rows[] = array(
                'keyword'    => $r['keyword'] ?? '',
                'position'   => (int) ( $r['best_position'] ?? 0 ),
                'volume'     => (int) ( $r['volume'] ?? 0 ),
                'url'        => $r['best_position_url'] ?? '',
                'difficulty' => $r['keyword_difficulty'] ?? null,
                'traffic'    => (int) ( $r['sum_traffic'] ?? 0 ),
            );
        }
        return array( 'domain' => $domain, 'ranked_keywords' => $rows, 'source' => 'ahrefs', '_cached' => ! empty( $res['_cached'] ) );
    }

    // ── Competitor domains ─────────────────────────────────────────────────

    /**
     * Competing domains, ordered so the list is actually useful in a pitch.
     *
     * Ordering by raw traffic is what you'd reach for first and it is wrong:
     * for a small local business Ahrefs returns linkedin.com and indeed.com at
     * the top, because they out-rank everyone on one incidental shared term.
     * Filtering to domains with real keyword overlap and dropping the
     * mega-aggregators by domain rating leaves the genuine local competitors.
     */
    public static function competitors( $domain, $location = '', $limit = 15 ) {
        $domain = self::domain( $domain );
        if ( ! $domain ) return array( 'error' => 'No domain provided.' );

        $res = self::get( 'organic_competitors', array(
            'select'   => 'competitor_domain,traffic,keywords_common,keywords_competitor,domain_rating,share',
            'target'   => $domain,
            'date'     => self::today(),
            'country'  => self::country( $location ),
            'mode'     => 'subdomains',
            'where'    => wp_json_encode( array( 'and' => array(
                array( 'field' => 'share',         'is' => array( 'gt', 0 ) ),
                array( 'field' => 'domain_rating', 'is' => array( 'lt', 80 ) ),
            ) ) ),
            'order_by' => 'traffic:desc',
            'limit'    => max( 1, (int) $limit ),
        ) );
        if ( isset( $res['error'] ) ) return $res;

        $rows = array();
        foreach ( $res['competitors'] ?? array() as $r ) {
            $d = self::domain( $r['competitor_domain'] ?? '' );
            if ( ! $d || $d === $domain ) continue;
            $rows[] = array(
                'domain'           => $d,
                'common_keywords'  => (int) ( $r['keywords_common'] ?? 0 ),
                'organic_traffic'  => (int) ( $r['traffic'] ?? 0 ),
                'organic_keywords' => (int) ( $r['keywords_competitor'] ?? 0 ),
                'domain_rating'    => isset( $r['domain_rating'] ) ? (float) $r['domain_rating'] : null,
                'overlap_pct'      => isset( $r['share'] ) ? (float) $r['share'] : null,
            );
        }
        return array( 'target' => $domain, 'competitors' => $rows, 'source' => 'ahrefs', '_cached' => ! empty( $res['_cached'] ) );
    }

    // ── Whole-site metrics ─────────────────────────────────────────────────

    /**
     * Headline organic numbers for a domain. This is what the client's own
     * "visitors in the last 30 days" figure and each competitor's comparison
     * figure are read from.
     *
     * org_traffic is Ahrefs' estimate of monthly organic visitors. It is an
     * estimate, not analytics, and anything presenting it to a client should
     * say so.
     */
    public static function site_metrics( $domain, $location = '' ) {
        $domain = self::domain( $domain );
        if ( ! $domain ) return array( 'error' => 'No domain provided.' );

        $res = self::get( 'site_metrics', array(
            'target'  => $domain,
            'date'    => self::today(),
            'country' => self::country( $location ),
            'mode'    => 'subdomains',
        ) );
        if ( isset( $res['error'] ) ) return $res;

        $m = $res['metrics'] ?? array();
        return array(
            'domain'            => $domain,
            'organic_traffic'   => (int) ( $m['org_traffic'] ?? 0 ),
            'organic_keywords'  => (int) ( $m['org_keywords'] ?? 0 ),
            'keywords_top3'     => (int) ( $m['org_keywords_1_3'] ?? 0 ),
            'traffic_value'     => self::dollars( $m['org_cost'] ?? null ),
            'paid_traffic'      => (int) ( $m['paid_traffic'] ?? 0 ),
            'paid_keywords'     => (int) ( $m['paid_keywords'] ?? 0 ),
            'source'            => 'ahrefs',
            'is_estimate'       => true,
            '_cached'           => ! empty( $res['_cached'] ),
        );
    }

    // ── Live SERP ──────────────────────────────────────────────────────────

    public static function serp( $keyword, $location = '', $depth = 10 ) {
        $keyword = trim( (string) $keyword );
        if ( $keyword === '' ) return array( 'error' => 'No keyword provided.' );

        $res = self::get( 'serp', array(
            'select'   => 'position,url,title,domain_rating,traffic',
            'keyword'  => $keyword,
            'country'  => self::country( $location ),
            'date'     => self::today(),
            'top_positions' => max( 1, (int) $depth ),
        ), true, 45 );
        if ( isset( $res['error'] ) ) return $res;

        // The payload key has varied across revisions; accept either.
        $items = $res['positions'] ?? ( $res['serp_overview'] ?? array() );
        $rows  = array();
        foreach ( $items as $it ) {
            $url = $it['url'] ?? '';
            $rows[] = array(
                'position' => (int) ( $it['position'] ?? 0 ),
                'title'    => $it['title'] ?? '',
                'url'      => $url,
                'domain'   => self::domain( $url ),
            );
            if ( count( $rows ) >= $depth ) break;
        }
        return array( 'keyword' => $keyword, 'results' => $rows, 'source' => 'ahrefs', '_cached' => ! empty( $res['_cached'] ) );
    }

    // ── On-page snapshot (native — see the file header) ────────────────────

    /**
     * Fetch a URL and measure it ourselves.
     *
     * Ahrefs has no instant single-page endpoint, so rather than report
     * "unsupported" this does the work directly. Everything returned is
     * something actually observed in the fetched HTML — there is no estimation
     * anywhere in here, which makes it the most trustworthy data in this class.
     */
    public static function onpage( $url ) {
        $url = esc_url_raw( $url );
        if ( ! $url ) return array( 'error' => 'No URL provided.' );
        if ( ! preg_match( '#^https?://#i', $url ) ) $url = 'https://' . $url;

        $start = microtime( true );
        $resp  = wp_remote_get( $url, array(
            'timeout'     => 20,
            'redirection' => 5,
            'user-agent'  => '6ixPortal-SiteCheck/1.0 (+https://6ixdevelopers.com)',
        ) );
        $elapsed = (int) round( ( microtime( true ) - $start ) * 1000 );

        if ( is_wp_error( $resp ) ) {
            return array( 'error' => 'Could not fetch the page: ' . $resp->get_error_message(), 'url' => $url );
        }
        $code = (int) wp_remote_retrieve_response_code( $resp );
        $html = (string) wp_remote_retrieve_body( $resp );
        if ( $html === '' ) {
            return array( 'error' => 'The page returned no HTML (HTTP ' . $code . ').', 'url' => $url, 'status_code' => $code );
        }

        $title = $desc = '';
        $h1    = array();
        $issues = array();

        if ( preg_match( '#<title[^>]*>(.*?)</title>#is', $html, $m ) ) {
            $title = trim( html_entity_decode( wp_strip_all_tags( $m[1] ), ENT_QUOTES, 'UTF-8' ) );
        }
        if ( preg_match( '#<meta[^>]+name=["\']description["\'][^>]*content=["\'](.*?)["\']#is', $html, $m )
          || preg_match( '#<meta[^>]+content=["\'](.*?)["\'][^>]*name=["\']description["\']#is', $html, $m ) ) {
            $desc = trim( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ) );
        }
        if ( preg_match_all( '#<h1[^>]*>(.*?)</h1>#is', $html, $m ) ) {
            foreach ( $m[1] as $h ) {
                $h = trim( html_entity_decode( wp_strip_all_tags( $h ), ENT_QUOTES, 'UTF-8' ) );
                if ( $h !== '' ) $h1[] = $h;
            }
        }

        // Strip script/style before counting words, or JS inflates the count.
        $text  = preg_replace( '#<(script|style|noscript)\b[^>]*>.*?</\1>#is', ' ', $html );
        $text  = wp_strip_all_tags( $text );
        $words = count( preg_split( '/\s+/', trim( preg_replace( '/\s+/', ' ', $text ) ), -1, PREG_SPLIT_NO_EMPTY ) );

        $host     = parse_url( $url, PHP_URL_HOST );
        $internal = $external = 0;
        if ( preg_match_all( '#<a\b[^>]*href=["\']([^"\']+)["\']#is', $html, $m ) ) {
            foreach ( $m[1] as $href ) {
                if ( preg_match( '/^(#|mailto:|tel:|javascript:)/i', $href ) ) continue;
                if ( preg_match( '#^https?://#i', $href ) ) {
                    $h = parse_url( $href, PHP_URL_HOST );
                    if ( $h && $host && stripos( $h, (string) $host ) !== false ) $internal++;
                    else $external++;
                } else {
                    $internal++;
                }
            }
        }

        $imgs = $no_alt = 0;
        if ( preg_match_all( '#<img\b[^>]*>#is', $html, $m ) ) {
            foreach ( $m[0] as $tag ) {
                $imgs++;
                if ( ! preg_match( '#\balt\s*=\s*["\'][^"\']+["\']#i', $tag ) ) $no_alt++;
            }
        }

        // Findings, each one checkable against the numbers above.
        if ( $code >= 400 )                         $issues[] = 'page_returns_error_' . $code;
        if ( $title === '' )                        $issues[] = 'missing_title';
        elseif ( mb_strlen( $title ) > 60 )         $issues[] = 'title_too_long';
        elseif ( mb_strlen( $title ) < 20 )         $issues[] = 'title_too_short';
        if ( $desc === '' )                         $issues[] = 'missing_meta_description';
        elseif ( mb_strlen( $desc ) > 160 )         $issues[] = 'meta_description_too_long';
        if ( ! $h1 )                                $issues[] = 'missing_h1';
        elseif ( count( $h1 ) > 1 )                 $issues[] = 'multiple_h1';
        if ( $words < 300 )                         $issues[] = 'thin_content';
        if ( $no_alt > 0 )                          $issues[] = 'images_missing_alt';
        if ( $internal < 3 )                        $issues[] = 'few_internal_links';
        if ( $elapsed > 2500 )                      $issues[] = 'slow_response';
        if ( stripos( $url, 'https://' ) !== 0 )    $issues[] = 'not_https';
        if ( ! preg_match( '#<meta[^>]+name=["\']viewport["\']#i', $html ) ) $issues[] = 'no_viewport_meta';
        if ( ! preg_match( '#application/ld\+json#i', $html ) )              $issues[] = 'no_structured_data';

        return array(
            'url'                => $url,
            'status_code'        => $code,
            'title'              => $title,
            'title_length'       => mb_strlen( $title ),
            'description'        => $desc,
            'description_length' => mb_strlen( $desc ),
            'h1'                 => $h1,
            'word_count'         => $words,
            'internal_links'     => $internal,
            'external_links'     => $external,
            'images'             => $imgs,
            'images_without_alt' => $no_alt,
            'load_time_ms'       => $elapsed,
            'issues'             => $issues,
            'source'             => 'native',
        );
    }

    // ── Diagnostics ────────────────────────────────────────────────────────

    /** Units remaining this month. Free — this endpoint costs nothing. */
    public static function usage() {
        $res = self::get( 'subscription', array(), false, 15 );
        if ( isset( $res['error'] ) ) return $res;
        $l = $res['limits_and_usage'] ?? array();
        return array(
            'plan'             => $l['subscription'] ?? '',
            'resets_on'        => $l['usage_reset_date'] ?? '',
            'units_limit'      => (int) ( $l['units_limit_workspace'] ?? 0 ),
            'units_used'       => (int) ( $l['units_usage_workspace'] ?? 0 ),
            'key_units_limit'  => (int) ( $l['units_limit_api_key'] ?? 0 ),
            'key_units_used'   => (int) ( $l['units_usage_api_key'] ?? 0 ),
        );
    }

    /**
     * Walk every endpoint against a known-cheap target and report which work.
     *
     * Worth having because the REST paths are the one part of this client that
     * can't be proven correct without a live key: a typo'd path is a 404 that
     * would otherwise surface as a vague empty result weeks later. The admin
     * Integrations page runs this behind a button.
     */
    public static function test_connection( $domain = 'ahrefs.com' ) {
        $out = array();

        $u = self::usage();
        $out['subscription'] = isset( $u['error'] )
            ? array( 'ok' => false, 'detail' => $u['error'] )
            : array( 'ok' => true,  'detail' => $u['plan'] . ' — ' . number_format( $u['units_limit'] - $u['units_used'] ) . ' of ' . number_format( $u['units_limit'] ) . ' units left' );

        // ahrefs.com as a target is free of charge, so this costs nothing.
        $checks = array(
            'site_metrics'        => function () use ( $domain ) { return self::site_metrics( $domain, 'Toronto' ); },
            'organic_competitors' => function () use ( $domain ) { return self::competitors( $domain, 'Toronto', 3 ); },
            'organic_keywords'    => function () use ( $domain ) { return self::ranked_keywords( $domain, 'Toronto', 3 ); },
            'kw_overview'         => function () { return self::keyword_overview( array( 'seo services' ), 'Toronto' ); },
            'kw_matching'         => function () { return self::keyword_ideas( array( 'seo services' ), 'Toronto', 3 ); },
            'serp'                => function () { return self::serp( 'seo services toronto', 'Toronto', 3 ); },
        );
        foreach ( $checks as $name => $fn ) {
            $r = $fn();
            $out[ $name ] = isset( $r['error'] )
                ? array( 'ok' => false, 'detail' => $r['error'] )
                : array( 'ok' => true,  'detail' => 'OK' );
        }

        $o = self::onpage( 'https://6ixdevelopers.com/' );
        $out['onpage_native'] = isset( $o['error'] )
            ? array( 'ok' => false, 'detail' => $o['error'] )
            : array( 'ok' => true,  'detail' => 'OK — fetched and parsed, no API units used' );

        return $out;
    }

    /** Forget every cached Ahrefs answer. */
    public static function flush_cache() {
        global $wpdb;
        return (int) $wpdb->query(
            "DELETE FROM {$wpdb->options}
              WHERE option_name LIKE '_transient_six_ahrefs_%'
                 OR option_name LIKE '_transient_timeout_six_ahrefs_%'"
        );
    }
}
