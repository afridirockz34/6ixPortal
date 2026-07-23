<?php
/**
 * Six_EstimateEngine — Tailored growth plan generator
 *
 * Uses Google Ads Keyword Planner API (your MCC account, NOT the client's)
 * plus Claude AI to generate a specific, credible 90-day roadmap.
 *
 * No client Google Ads account required — keyword data is pulled using
 * your MCC developer token with the client's keywords and location as inputs.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Six_EstimateEngine {

    private static $conv_rates = array(
        'dental'=>3.8,'dentist'=>3.8,'orthodont'=>3.5,
        'legal'=>2.7,'lawyer'=>2.7,'attorney'=>2.7,'law firm'=>2.7,
        'real estate'=>2.4,'realtor'=>2.4,'mortgage'=>2.2,
        'plumb'=>4.1,'hvac'=>4.2,'roof'=>3.9,'electrician'=>3.8,
        'contractor'=>3.2,'home service'=>4.1,'landscap'=>3.5,
        'medical'=>3.3,'health'=>3.3,'physio'=>3.4,'chiro'=>3.6,
        'fitness'=>2.8,'gym'=>2.8,'personal train'=>3.1,
        'restaurant'=>2.1,'food'=>2.0,'catering'=>2.5,
        'retail'=>2.3,'ecommerce'=>1.9,'shop'=>2.2,
        'finance'=>2.9,'insurance'=>2.6,'accounting'=>3.0,
        'education'=>3.1,'tutor'=>3.4,'school'=>2.8,
        'tech'=>2.3,'software'=>2.1,'it service'=>2.8,
        'automotive'=>3.4,'auto'=>3.4,'mechanic'=>3.6,
        'cleaning'=>3.7,'pest'=>3.9,'security'=>3.2,
        'photography'=>2.6,'event'=>2.4,'wedding'=>2.3,
        'salon'=>3.0,'spa'=>2.8,'beauty'=>2.9,
        'moving'=>3.8,'storage'=>3.1,'logistics'=>2.7,
    );
    private static $default_conv = 2.7;

    // ─────────────────────────────────────────────────────────────────────
    // MAIN ENTRY POINT
    // ─────────────────────────────────────────────────────────────────────

    public static function generate( int $user_id, array $override = array() ): array {
        global $wpdb;

        $co = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d", $user_id
        ) );

        // Merge POST overrides — JS data wins over stale/empty DB row
        // Handles logged-in users where S.q was not pre-populated from DB
        if ( ! $co ) $co = (object) array();
        foreach ( $override as $col => $val ) {
            if ( $val !== '' && $val !== 0 ) {
                $co->$col = $val;
            } elseif ( ! isset($co->$col) ) {
                $co->$col = $val;
            }
        }

        error_log( "6ix Estimate: biz=" . ($co->business_name??'empty') . " ind=" . ($co->industry??'empty') . " platforms=" . ($co->platforms??'empty') . " ads_kw=" . substr($co->ads_keywords??'empty',0,40) );

        $services    = array_filter( explode( ',', $co->platforms ?? '' ) );
        $industry    = strtolower( $co->industry ?? '' );
        // Use service-specific target location for KW Planner, fall back to business address
        $target_loc  = trim( $co->ads_locations ?? $co->seo_locations ?? '' );
        $biz_loc     = trim( $co->location ?? $co->business_address ?? '' );
        $kw_location = $target_loc ?: $biz_loc;
        $conv_rate   = self::conv_rate( $industry );

        // Pull keyword CPC data — try DataForSEO first (most reliable),
        // then Google Ads Keyword Planner as fallback
        $kw = array();
        $kw_raw = ! empty($co->ads_keywords) ? $co->ads_keywords : ($co->seo_keywords ?? '');
        $kw_list = array_slice(
            array_filter( array_map('trim', explode(',', $kw_raw)) ),
            0, 10
        );

        if ( ! empty($kw_list) && ( in_array('google-ads',$services) || in_array('seo',$services) ) ) {
            // Try DataForSEO first
            $kw = self::dataforseo_cpc( $kw_list, $kw_location );

            // Fall back to Google Ads Keyword Planner if DataForSEO not configured
            if ( empty($kw) ) {
                $kw = self::keyword_planner( $kw_raw, $kw_location );
            }
        }

        // Build Claude prompt
        $prompt = self::build_prompt( $co, $services, $kw, $conv_rate );

        // Ask Claude
        $plan = self::ask_claude( $prompt );

        if ( ! $plan || empty($plan['headline']) ) {
            $plan = self::numeric_fallback( $co, $services, $kw, $conv_rate );
        }

        // Tag whether real keyword data was used
        $plan['data_backed'] = ! empty($kw);

        // Cache the real market keyword economics so the post-onboarding growth
        // projection can ground itself (avg CPC + volume) without a live API call
        // on every dashboard load.
        if ( ! empty($kw['avg_cpc']) ) {
            update_user_meta( $user_id, 'six_market_avg_cpc',   floatval($kw['avg_cpc']) );
            update_user_meta( $user_id, 'six_market_total_vol', intval($kw['total_vol'] ?? 0) );
            update_user_meta( $user_id, 'six_market_kw_source', sanitize_text_field($kw['source'] ?? 'DataForSEO') );
            update_user_meta( $user_id, 'six_market_cpc_at',    current_time('mysql') );
        }

        // Save to DB
        $wpdb->update(
            "{$wpdb->prefix}six_checkout_progress",
            array( 'ai_plan_json' => wp_json_encode( $plan ) ),
            array( 'user_id' => $user_id )
        );

        error_log( "6ix Estimate: user={$user_id} kw=" . (!empty($kw)?'YES':'NO') . " svcs=" . implode(',',$services) );
        return $plan;
    }


    // ─────────────────────────────────────────────────────────────────────
    // DATAFORSEO — Real Google Ads CPC + Volume (primary data source)
    // Requires six_dataforseo_login + six_dataforseo_password in WP options
    // ~$0.002 per keyword — sign up free at dataforseo.com
    // ─────────────────────────────────────────────────────────────────────

    private static function dataforseo_cpc( array $keywords, string $location ): array {
        $login    = get_option('six_dataforseo_login', '');
        $password = get_option('six_dataforseo_password', '');

        if ( ! $login || ! $password ) {
            error_log('6ix Estimate: DataForSEO credentials not set — skipping');
            return array();
        }

        // Resolve location to DataForSEO location name
        // DataForSEO uses location strings like "Toronto,Ontario,Canada"
        $loc_map = array(
            'toronto'    => 'Toronto,Ontario,Canada',
            'mississauga'=> 'Mississauga,Ontario,Canada',
            'vancouver'  => 'Vancouver,British Columbia,Canada',
            'calgary'    => 'Calgary,Alberta,Canada',
            'ottawa'     => 'Ottawa,Ontario,Canada',
            'edmonton'   => 'Edmonton,Alberta,Canada',
            'montreal'   => 'Montreal,Quebec,Canada',
            'new york'   => 'New York,New York,United States',
            'los angeles'=> 'Los Angeles,California,United States',
            'chicago'    => 'Chicago,Illinois,United States',
            'houston'    => 'Houston,Texas,United States',
            'phoenix'    => 'Phoenix,Arizona,United States',
        );

        $city        = strtolower( trim( preg_replace('/,.*$/', '', $location) ) );
        $dfs_loc     = $loc_map[$city] ?? 'Canada';
        $lang        = 'en';

        // Send ALL keywords in a single task — DataForSEO's search_volume/live
        // endpoint accepts up to 1000 keywords per task. One task is cheaper and
        // faster than one-task-per-keyword, and returns every keyword's data in
        // a single result set.
        $kw_clean = array_values( array_unique( array_filter( array_map( function( $k ) {
            return trim( (string) $k );
        }, $keywords ) ) ) );
        if ( empty( $kw_clean ) ) return array();
        $kw_clean = array_slice( $kw_clean, 0, 1000 );

        $tasks = array( array(
            'keywords'       => $kw_clean,
            'location_name'  => $dfs_loc,
            'language_name'  => 'English',
            'search_partners'=> false,
        ) );

        $resp = wp_remote_post(
            'https://api.dataforseo.com/v3/keywords_data/google_ads/search_volume/live',
            array(
                'timeout' => 20,
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode("{$login}:{$password}"),
                    'Content-Type'  => 'application/json',
                ),
                'body' => wp_json_encode($tasks),
            )
        );

        if ( is_wp_error($resp) ) {
            error_log('6ix Estimate: DataForSEO error: ' . $resp->get_error_message());
            return array();
        }

        $code = wp_remote_retrieve_response_code($resp);
        $data = json_decode(wp_remote_retrieve_body($resp), true);

        if ( $code !== 200 || ($data['status_code'] ?? 0) !== 20000 ) {
            error_log("6ix Estimate: DataForSEO HTTP {$code} status=" . ($data['status_code']??'?') . ": " . substr(wp_remote_retrieve_body($resp),0,300));
            return array();
        }

        $rows    = array();
        $vol_sum = $cpc_sum = $n = 0;
        $comp    = 0.5;

        foreach ( $data['tasks'] ?? array() as $task ) {
            foreach ( $task['result'] ?? array() as $r ) {
                foreach ( $r['items'] ?? array($r) as $item ) {
                    $kw  = $item['keyword'] ?? $r['keyword'] ?? '';
                    $vol = intval($item['search_volume'] ?? $r['search_volume'] ?? 0);
                    $cpc = round(floatval($item['cpc'] ?? $r['cpc'] ?? 0), 2);
                    $cmp = floatval($item['competition'] ?? $r['competition'] ?? 0);

                    if ( $cpc > 0 || $vol > 0 ) {
                        $vol_sum += $vol;
                        $cpc_sum += $cpc;
                        $comp    = $cmp;
                        $n++;
                        $rows[] = array('kw'=>$kw, 'vol'=>$vol, 'cpc'=>$cpc);
                    }
                }
            }
        }

        if ( $n === 0 ) {
            error_log('6ix Estimate: DataForSEO returned no usable results');
            return array();
        }

        $avg_cpc = round($cpc_sum / $n, 2);
        $comp_label = $comp > 0.7 ? 'HIGH' : ($comp > 0.4 ? 'MEDIUM' : 'LOW');
        error_log("6ix Estimate: DataForSEO OK n={$n} avg_cpc=\${$avg_cpc} vol={$vol_sum}");

        return array(
            'avg_cpc'    => $avg_cpc,
            'total_vol'  => $vol_sum,
            'competition'=> $comp_label,
            'keywords'   => $rows,
            'source'     => 'DataForSEO',
        );
    }

    /**
     * Live connectivity/health test for the DataForSEO integration.
     * Runs a real minimal query and returns a structured status the admin
     * settings page can render. Never throws.
     */
    public static function test_dataforseo(): array {
        $login    = get_option('six_dataforseo_login', '');
        $password = get_option('six_dataforseo_password', '');
        if ( ! $login || ! $password ) {
            return array('ok'=>false,'stage'=>'config','message'=>'DataForSEO login/password are not set. Add them in the fields above and save.');
        }

        // 1) Cheap auth check via the account endpoint (also returns balance).
        $resp = wp_remote_get(
            'https://api.dataforseo.com/v3/appendix/user_data',
            array(
                'timeout' => 20,
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode("{$login}:{$password}"),
                    'Content-Type'  => 'application/json',
                ),
            )
        );
        if ( is_wp_error( $resp ) ) {
            return array('ok'=>false,'stage'=>'network','message'=>'Network error: ' . $resp->get_error_message());
        }
        $code = wp_remote_retrieve_response_code( $resp );
        $body = json_decode( wp_remote_retrieve_body( $resp ), true );
        if ( $code === 401 ) {
            return array('ok'=>false,'stage'=>'auth','message'=>'Authentication failed (401). The login email or password is incorrect.');
        }
        if ( $code !== 200 || ( $body['status_code'] ?? 0 ) !== 20000 ) {
            return array('ok'=>false,'stage'=>'api','message'=>"API error — HTTP {$code}, status " . ( $body['status_code'] ?? '?' ) . ': ' . ( $body['status_message'] ?? 'unknown' ));
        }

        $money   = $body['tasks'][0]['result'][0]['money'] ?? array();
        $balance = isset( $money['balance'] ) ? '$' . number_format( (float) $money['balance'], 2 ) : 'n/a';

        // 2) Prove the search-volume endpoint actually returns keyword data.
        $kw = self::dataforseo_cpc( array( 'emergency plumber' ), 'Toronto,Ontario,Canada' );
        if ( empty( $kw ) ) {
            return array(
                'ok'      => true,
                'stage'   => 'partial',
                'balance' => $balance,
                'message' => "Authenticated successfully (balance {$balance}), but the keyword search-volume test returned no data. Credentials are valid — the keyword endpoint may require a positive account balance.",
            );
        }
        return array(
            'ok'      => true,
            'stage'   => 'ok',
            'balance' => $balance,
            'sample'  => $kw,
            'message' => "Working. Account balance {$balance}. Live sample \"emergency plumber\" (Toronto): avg CPC \${$kw['avg_cpc']}, ~" . number_format( $kw['total_vol'] ) . " searches/mo, competition {$kw['competition']}.",
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // GOOGLE ADS KEYWORD PLANNER (YOUR MCC — no client account needed)
    // ─────────────────────────────────────────────────────────────────────

    private static function keyword_planner( string $kw_raw, string $location ): array {
        if ( ! class_exists('Six_Google_Ads') ) return array();

        $token     = Six_Google_Ads::get_mcc_access_token();
        $dev_token = get_option('six_gads_developer_token');
        $mcc_id    = get_option('six_gads_manager_id');

        if ( ! $dev_token ) { error_log('6ix Estimate: six_gads_developer_token not set — no real CPC data'); return array(); }
        if ( ! $mcc_id )    { error_log('6ix Estimate: six_gads_manager_id not set — no real CPC data'); return array(); }
        if ( ! $token )     { error_log('6ix Estimate: Google Ads access token failed — check OAuth credentials'); return array(); }
        error_log("6ix Estimate: KW Planner calling API for keywords=[{$kw_raw}] location=[{$location}]");

        $keywords = array_slice(
            array_filter( array_map( 'trim', explode( ',', $kw_raw ) ) ),
            0, 10
        );
        if ( empty($keywords) ) return array();

        $headers = array(
            'Authorization'     => 'Bearer ' . $token,
            'Content-Type'      => 'application/json',
            'developer-token'   => $dev_token,
            'login-customer-id' => preg_replace('/[^0-9]/', '', $mcc_id),
        );

        // Google Ads REST API v20 uses snake_case field names
        // Google Ads REST v20 — all camelCase, keywordSeed.keywords is array of strings
        $body = array(
            'keywordSeed'              => array( 'keywords' => $keywords ),
            'keywordPlanNetwork'       => 'GOOGLE_SEARCH',
            'includeAdultKeywords'     => false,

        );

        $geo = self::geo_target( $location, $headers );
        if ( $geo ) {
            $body['geoTargetConstants'] = array( "geoTargetConstants/{$geo}" );
            error_log("6ix Estimate: geo_target resolved [{$location}] → ID={$geo}");
        } else {
            error_log("6ix Estimate: geo_target failed for [{$location}] — using global data");
        }

        // Use dedicated keyword planner account if set, else fall back to MCC
        // Strip dashes — Google Ads API requires numeric ID only (e.g. 9824374323 not 982-437-4323)
        // IMPORTANT: The URL customer must be a CLIENT account (not MCC) to get metrics
        // Set six_gads_kw_planner_account_id to any active client account under your MCC
        $raw_kw_acct = get_option('six_gads_kw_planner_account_id') ?: '9062241852';  // default client account
        $kw_acct = preg_replace('/[^0-9]/', '', $raw_kw_acct ?: $mcc_id);
        error_log("6ix Estimate: using customer_id=[{$kw_acct}] mcc=[{$mcc_id}] (set six_gads_kw_planner_account_id to a client account for metrics)");

        $resp = wp_remote_post(
            "https://googleads.googleapis.com/v20/customers/{$kw_acct}:generateKeywordIdeas",
            array( 'timeout'=>20, 'headers'=>$headers, 'body'=>wp_json_encode($body) )
        );

        if ( is_wp_error($resp) ) {
            error_log('6ix Estimate: KW Planner network error: ' . $resp->get_error_message());
            return array();
        }

        $code = wp_remote_retrieve_response_code($resp);
        $raw  = wp_remote_retrieve_body($resp);
        $data = json_decode($raw, true);

        if ( $code !== 200 ) {
            error_log("6ix Estimate: KW Planner HTTP {$code} FULL: " . $raw);
            return array();
        }

        // Log raw response structure to diagnose missing metrics
        error_log("6ix Estimate: KW API raw (first 1500): " . substr($raw, 0, 1500));
        $total_results = count($data['results'] ?? array());
        // Check ALL possible metric field name variants
        $has_metrics_count = count(array_filter($data['results'] ?? array(), function($r) {
            return ! empty($r['keywordIdeaMetrics'])
                || ! empty($r['keyword_idea_metrics'])
                || ! empty($r['keywordIdeaMetrics']['lowTopOfPageBidMicros'])
                || ! empty($r['keywordIdeaMetrics']['avgMonthlySearches'])
                || isset($r['keywordIdeaMetrics']['competition']);
        }));
        error_log("6ix Estimate: KW API total={$total_results} with_metrics={$has_metrics_count}");
        // Log the first result WITH metrics to see its structure
        foreach ( $data['results'] ?? array() as $r ) {
            if ( ! empty($r['keywordIdeaMetrics']) ) {
                error_log("6ix Estimate: KW first-with-metrics keys: " . implode(',', array_keys($r['keywordIdeaMetrics'])));
                error_log("6ix Estimate: KW first-with-metrics full: " . wp_json_encode($r));
                break;
            }
        }

        $rows    = array();
        $vol_sum = $cpc_sum = $n = 0;
        $comp    = 'MEDIUM';

        $all_results = $data['results'] ?? array();

        // Collect all results that have bid data (regardless of keyword text match)
        // The API returns our seed keywords first (often without metrics) then
        // related keywords with real metrics — we use those for CPC calculation
        $with_bids = array();
        foreach ( $all_results as $r ) {
            $m = $r['keywordIdeaMetrics'] ?? array();
            // Accept any result that has metrics — even without bid data
            if ( ! empty($m) ) {
                $with_bids[] = $r;
            }
        }

        // Sort by relevance: prefer results whose text contains our input keywords
        $input_kws_lower = array_map('strtolower', $keywords);
        usort($with_bids, function($a, $b) use ($input_kws_lower) {
            $a_match = (int) array_reduce($input_kws_lower, fn($carry, $kw) => $carry || strpos(strtolower($a['text']??''), $kw) !== false, false);
            $b_match = (int) array_reduce($input_kws_lower, fn($carry, $kw) => $carry || strpos(strtolower($b['text']??''), $kw) !== false, false);
            return $b_match - $a_match; // matches first
        });

        $results_to_use = array_slice($with_bids, 0, 8);
        error_log("6ix Estimate: KW using=" . count($results_to_use) . " bid-results from " . count($all_results) . " total");

        foreach ( $results_to_use as $r ) {
            $m   = $r['keywordIdeaMetrics'] ?? array();
            // avgMonthlySearches comes back as a string — cast to int
            $vol = intval($m['avgMonthlySearches'] ?? 0);
            // API returns lowTopOfPageBidMicros / highTopOfPageBidMicros (not averageCpc)
            // Use midpoint of low+high bids as the realistic avg CPC
            $low_micros  = intval($m['lowTopOfPageBidMicros']  ?? 0);
            $high_micros = intval($m['highTopOfPageBidMicros'] ?? 0);
            if ( $low_micros > 0 && $high_micros > 0 ) {
                // When low bid << high bid (common), the low bid is a floor with ~zero traffic
                // Realistic avg CPC is 30-40% of the high bid
                // This aligns with industry data: most clicks happen near the competitive rate
                $ratio = $high_micros / max($low_micros, 1);
                if ( $ratio > 20 ) {
                    // Wide spread — low bid is junk floor, use 35% of high
                    $cpc = round( $high_micros * 0.35 / 1000000, 2 );
                } else {
                    // Tight spread — simple midpoint is fine
                    $cpc = round( ($low_micros + $high_micros) / 2 / 1000000, 2 );
                }
            } elseif ( $high_micros > 0 ) {
                $cpc = round( $high_micros * 0.35 / 1000000, 2 );
            } elseif ( $low_micros > 0 ) {
                $cpc = round( $low_micros / 1000000, 2 );
            } else {
                $cpc = 0.0;
            }
            if ( $vol < 1 ) continue;
            $vol_sum += $vol; $cpc_sum += $cpc; $n++;
            $comp = $m['competition'] ?? $comp;
            $rows[] = array( 'kw'=>$r['text']??'', 'vol'=>$vol, 'cpc'=>$cpc );
        }

        if ( $n === 0 ) return array();

        $avg_cpc_final = round($cpc_sum/$n, 2);
        error_log("6ix Estimate: KW aggregated n={$n} avg_cpc=\${$avg_cpc_final} total_vol={$vol_sum}");
        return array(
            'avg_cpc'    => $avg_cpc_final,
            'total_vol'  => $vol_sum,
            'competition'=> $comp,
            'keywords'   => $rows,
        );
    }

    private static function geo_target( string $loc, array $headers ): int {
        if ( ! $loc ) return 0;
        $ck = 'six_geo_' . sanitize_key($loc);
        $cached = get_transient($ck);
        if ( $cached ) return intval($cached);
        $city = trim(preg_replace('/,.*$/','',$loc));

        // Check hardcoded Canadian cities FIRST — faster and more reliable than API
        // Hardcoded fallback for common Canadian cities
        // Canadian cities
        $ca_cities = array(
            'toronto'=>1002723,'mississauga'=>1002924,'vancouver'=>1002208,
            'calgary'=>1001800,'ottawa'=>1002700,'edmonton'=>1002836,
            'montreal'=>1002518,'winnipeg'=>1002979,'hamilton'=>1002854,
            'brampton'=>1002841,'markham'=>1002859,'vaughan'=>1002870,
            'scarborough'=>1002729,'north york'=>1002727,'etobicoke'=>1002730,
            'richmond hill'=>1002860,'oakville'=>1002856,'burlington'=>1002842,
            'london'=>1002871,'kitchener'=>1002855,'waterloo'=>1002869,
            'windsor'=>1002876,'barrie'=>1002838,'kingston'=>1002858,
        );
        // US cities
        $us_cities = array(
            'new york'=>1023191,'los angeles'=>1013962,'chicago'=>1016367,
            'houston'=>1026481,'phoenix'=>1022444,'philadelphia'=>1025261,
            'san antonio'=>1026457,'san diego'=>1013972,'dallas'=>1026487,
            'san jose'=>1013985,'austin'=>1026500,'jacksonville'=>1012873,
            'seattle'=>1027140,'denver'=>1022183,'boston'=>1018127,
            'miami'=>1012594,'atlanta'=>1015116,'detroit'=>1018390,
            'minneapolis'=>1020688,'portland'=>1027178,'las vegas'=>1030827,
            'charlotte'=>1025298,'nashville'=>1025354,'baltimore'=>1019743,
        );
        $ca_cities = array_merge($ca_cities, $us_cities);
        $city_lower = strtolower($city);
        foreach ( $ca_cities as $cn => $gid ) {
            if ( strpos($city_lower, $cn) !== false ) {
                error_log("6ix Estimate: geo_target using hardcoded CA ID for [{$city}] → {$gid}");
                set_transient($ck, $gid, WEEK_IN_SECONDS);
                return $gid;
            }
        }
        return 0;
    }

    // ─────────────────────────────────────────────────────────────────────
    // CLAUDE PROMPT  — produces kpis[] + roadmap[] format
    // ─────────────────────────────────────────────────────────────────────

    private static function build_prompt( $co, array $svcs, array $kw, float $cr ): string {
        $svc_labels = array(
            'google-ads'     => 'Google Ads',
            'seo'            => 'SEO',
            'google-business'=> 'Google Business Profile',
            'website'        => 'Website Development',
        );

        $biz    = $co->business_name ?? 'the business';
        $ind    = $co->industry      ?? 'local business';
        // Prefer service-specific target location over business address
        $target_loc = trim($co->ads_locations ?? $co->seo_locations ?? '');
        $biz_loc    = trim($co->location ?? $co->business_address ?? '');
        $loc        = $target_loc ?: $biz_loc ?: 'their area';
        $loc_type   = trim($co->ads_loc_type ?? 'Include');
        $goals  = $co->goal ? str_replace(',',', ',$co->goal) : 'grow the business';
        $comps  = $co->competitors ?? '';
        $bud_ads = intval($co->ads_budget  ?? 0);
        $bud_seo = intval($co->seo_budget  ?? 0);
        $bud_gbp = intval($co->gbp_budget  ?? 0);
        $bud_web = intval($co->web_budget   ?? 0);
        $total   = $bud_ads + $bud_seo + $bud_gbp + $bud_web;

        $svc_str = implode(', ', array_map(fn($s)=>$svc_labels[$s]??$s, $svcs));

        // ── Pre-calculate numbers for the prompt ─────────────────────────
        $kw_source   = ! empty($kw) ? ($kw['source'] ?? 'Google Ads API') : 'industry benchmarks';
        $avg_cpc     = ! empty($kw['avg_cpc']) && $kw['avg_cpc'] > 0 ? $kw['avg_cpc'] : self::industry_cpc($ind);
        $total_vol   = ! empty($kw['total_vol']) ? $kw['total_vol'] : 0;
        $has_real    = ! empty($kw) && $kw['avg_cpc'] > 0;

        $clicks_mo   = $bud_ads > 0 && $avg_cpc > 0 ? floor($bud_ads / $avg_cpc) : 0;
        $leads_lo    = $clicks_mo > 0 ? max(1, (int)round($clicks_mo * ($cr - 0.8) / 100)) : 1;
        $leads_hi    = $clicks_mo > 0 ? max(2, (int)round($clicks_mo * ($cr + 0.8) / 100)) : 3;
        $leads_m2_lo = (int)round($leads_lo * 1.5);
        $leads_m2_hi = (int)round($leads_hi * 1.5);

        // Deal value lookup (comprehensive)
        $dv_map = array(
            'dental'=>800,'dentist'=>800,'orthodont'=>2500,
            'legal'=>3500,'lawyer'=>3500,'attorney'=>3500,
            'real estate'=>8000,'realtor'=>8000,'mortgage'=>2200,
            'plumb'=>650,'hvac'=>1200,'roof'=>7500,'electrician'=>800,
            'contractor'=>4500,'home service'=>900,'landscap'=>1800,
            'medical'=>400,'health'=>400,'physio'=>350,'chiro'=>300,
            'fitness'=>180,'gym'=>120,'restaurant'=>85,'food'=>85,'catering'=>600,
            'retail'=>250,'ecommerce'=>180,
            'finance'=>2800,'insurance'=>1400,'accounting'=>2200,
            'marketing'=>3500,'advertising'=>3500,'agency'=>3500,'digital'=>3000,
            'seo'=>2500,'web design'=>4000,'web develop'=>4000,'software'=>5000,
            'automotive'=>1100,'mechanic'=>600,'cleaning'=>400,
            'salon'=>150,'spa'=>200,'beauty'=>180,
            'consult'=>4000,'coach'=>1200,'recruit'=>3000,
        );
        $deal_val = 1200;
        foreach ($dv_map as $dk => $dv) if (strpos($ind,$dk)!==false){$deal_val=$dv;break;}

        $rev_lo  = (int)round($leads_lo  * $deal_val * 0.20);
        $rev_hi  = (int)round($leads_hi  * $deal_val * 0.20);
        $roi_lo  = max(0, $rev_lo - $bud_ads);
        $roi_hi  = max(0, $rev_hi - $bud_ads);

        $fmt = fn($n) => $n >= 10000 ? '$'.round($n/1000,1).'k' : '$'.number_format($n);
        $roi_str = $roi_hi > 0 ? '+'.$fmt($roi_lo).'–'.$fmt($roi_hi) : ($rev_hi>0 ? $fmt($rev_lo).'–'.$fmt($rev_hi).' est. revenue' : 'TBD');

        $kw_detail = '';
        if (!empty($kw['keywords'])) {
            $top3 = array_slice($kw['keywords'], 0, 3);
            $kw_detail = implode(', ', array_map(fn($k)=>"\"{$k['kw']}\" (\${$k['cpc']} CPC, ".number_format($k['vol'])." searches/mo)", $top3));
        }

        $comp_raw    = $co->competitors ?? '';
        $comp_list   = array_filter(array_slice(array_map('trim', explode(',', $comp_raw)), 0, 3));
        $comps_clean = implode(', ', $comp_list) ?: 'local competitors';

        // ── Existing-Google-Ads audit branch ──────────────────────────────
        // When the client already runs Google Ads, the plan is reframed as an
        // improvement audit ("opportunities to improve what you already run")
        // rather than a from-scratch launch. Decode the audit answers they gave.
        $ga_audit    = array();
        if ( in_array('google-ads',$svcs) && ($co->gads_running ?? '') === 'yes' && ! empty($co->gads_audit_json) ) {
            $decoded = json_decode( $co->gads_audit_json, true );
            if ( is_array($decoded) ) $ga_audit = $decoded;
        }
        $is_ga_audit = ! empty($ga_audit);

        // ── Build Claude prompt ───────────────────────────────────────────
        $L = array();
        $L[] = 'You are a senior performance marketing strategist at 6ix Developers, a Toronto-based digital marketing agency.';
        if ( $is_ga_audit ) {
            $L[] = 'This client ALREADY RUNS Google Ads. Write a hyper-specific 60-day IMPROVEMENT plan — an initial audit of what they already run, surfacing the highest-impact opportunities to get more leads from the same or better spend. This plan is the LAST thing they see before entering their credit card.';
            $L[] = 'Your job: make them feel that 6ix Developers has already reviewed their setup, spotted exactly where money is leaking, and that continuing alone would keep costing them.';
            $L[] = 'Do NOT talk about "setting up" or "launching" campaigns from scratch — they are live. Talk about auditing, fixing, optimising, and scaling what exists.';
            $L[] = 'Frame the opportunities by IMPACT: the first is their Biggest Opportunity, then Opportunity #2, #3. Every line must reference their actual campaigns, keywords, location, competitors, budget, or the specific problems they described. Zero generic filler.';
        } else {
            $L[] = 'Write a hyper-specific 60-day growth plan for a prospective client. This plan is the LAST thing they see before entering their credit card.';
            $L[] = 'Your job: make them feel that 6ix Developers has already done the homework, knows their market cold, and that NOT signing would be a mistake.';
            $L[] = 'Every line must reference their actual business, keywords, location, competitors, or budget. Zero generic filler.';
        }
        $L[] = '';
        $L[] = '=== CLIENT PROFILE ===';
        $L[] = "Business: {$biz}";
        $L[] = "Industry: {$ind}";
        $L[] = "Target market: {$loc}";
        if ($biz_loc && $biz_loc !== $loc) $L[] = "Business address: {$biz_loc}";
        $L[] = "Services: {$svc_str}";
        $L[] = "Monthly investment: \${$total}";
        $L[] = "Goals: {$goals}";
        $L[] = "Competitors: {$comps_clean}";
        if ($co->ads_keywords) $L[] = "Target keywords: {$co->ads_keywords}";
        if ($co->ads_usp)      $L[] = "Their USP: {$co->ads_usp}";
        if ($co->seo_keywords) $L[] = "SEO keywords: {$co->seo_keywords}";
        if ($co->gbp_category) $L[] = "GBP category: {$co->gbp_category}";
        if ($co->gbp_rating)   $L[] = "Current Google rating: {$co->gbp_rating}";

        if ( $is_ga_audit ) {
            $ga_dur   = array( '<3m'=>'under 3 months', '3-12m'=>'3–12 months', '1-2y'=>'1–2 years', '2y+'=>'2+ years' );
            $ga_mgr   = array( 'self'=>'themselves', 'agency'=>'an agency', 'freelancer'=>'a freelancer' );
            $dur_txt  = $ga_dur[ $ga_audit['duration'] ?? '' ] ?? ($ga_audit['duration'] ?? 'unknown');
            $mgr_txt  = $ga_mgr[ $ga_audit['manager'] ?? '' ] ?? ($ga_audit['manager'] ?? 'unknown');
            $L[] = '';
            $L[] = '=== THEIR CURRENT GOOGLE ADS (audit source — react to these directly) ===';
            $L[] = "Running Google Ads for: {$dur_txt}";
            if ( ! empty($ga_audit['goal']) )           $L[] = "Their primary goal: {$ga_audit['goal']}";
            if ( ! empty($ga_audit['campaign_types']) ) $L[] = "Campaign types they run: {$ga_audit['campaign_types']}";
            $L[] = "Currently managed by: {$mgr_txt}";
            if ( isset($ga_audit['satisfied']) )        $L[] = "Happy with current results: " . ( $ga_audit['satisfied'] === 'yes' ? 'yes' : 'NO — they are not satisfied' );
            if ( ! empty($ga_audit['working']) )        $L[] = "What they say is working: {$ga_audit['working']}";
            if ( ! empty($ga_audit['not_working']) )    $L[] = "What they say is NOT working: {$ga_audit['not_working']}";
            if ( ! empty($ga_audit['challenge']) )      $L[] = "Their biggest stated challenge: {$ga_audit['challenge']}";
            $L[] = 'Use these answers as the backbone of the plan. Directly address what they said is not working and their biggest challenge in the top opportunities.';
        }

        $L[] = '';
        $L[] = '=== CALCULATED NUMBERS (use these exactly in your output) ===';
        $L[] = "Data source: {$kw_source}";
        $L[] = "Avg CPC for their keywords: \${$avg_cpc}";
        if ($total_vol) $L[] = "Combined monthly search volume: ".number_format($total_vol)." searches/mo";
        if ($kw_detail) $L[] = "Keyword breakdown: {$kw_detail}";
        $L[] = "Monthly budget: \${$bud_ads} → {$clicks_mo} estimated clicks";
        $L[] = "Industry conversion rate: {$cr}%";
        $L[] = "Estimated leads Month 1: {$leads_lo}–{$leads_hi}";
        $L[] = "Estimated leads Month 2: {$leads_m2_lo}–{$leads_m2_hi}";
        $L[] = "Avg deal value in {$ind}: \${$deal_val}";
        $L[] = "ROI formula: leads × \${$deal_val} × 20% close rate − \${$bud_ads} = net ROI";
        $L[] = "Est. net ROI by Month 2: {$roi_str}";
        $L[] = '';
        $L[] = '';
        $L[] = '=== REQUIRED JSON OUTPUT ===';
        $L[] = 'Return ONLY valid JSON. No markdown fences, no text outside the JSON object.';
        $L[] = 'Every insight must cite a REAL number (CPC, keyword volume, competitor name, industry stat).';
        $L[] = 'NEVER use generic phrases like "optimise your presence" or "improve performance".';
        $L[] = 'ALWAYS name their specific keyword, service, location, or business.';
        $L[] = '';
        // Build service-specific insight instructions
        $svc_insight_instructions = array();
        if ( strpos($svc_str,'Google Ads') !== false )
            $svc_insight_instructions[] = 'Google Ads insight: cite their keyword avg CPC (' . $avg_cpc . '), explain why competition level matters, name 1 specific campaign structure decision.';
        if ( strpos($svc_str,'SEO') !== false )
            $svc_insight_instructions[] = 'SEO insight: describe their keyword gap with a number, why long-tail terms in ' . $ind . ' convert better, and the #1 on-page or technical action.';
        if ( strpos($svc_str,'Google Business') !== false )
            $svc_insight_instructions[] = 'GBP insight: explain their current rating (' . ($co->gbp_rating ?? 'unknown') . ') vs local competition, why map pack visibility drives walk-ins for ' . $ind . ', and 1 specific optimisation (photos, posts, or Q&A).';
        if ( strpos($svc_str,'Website') !== false )
            $svc_insight_instructions[] = 'Website insight: identify 1 conversion friction point common in ' . $ind . ', explain why load speed or headline copy affects cost per lead, and name the highest-ROI page to build first.';

        if ( $is_ga_audit ) {
            $L[] = 'For the "insights" array, produce 3 IMPROVEMENT OPPORTUNITIES, ordered by revenue impact (biggest first). Each MUST follow this structure:';
            $L[] = '  what: The specific gap or leak in their CURRENT Google Ads (cite a number, their stated problem, or a competitor)';
            $L[] = '  why: Why fixing it grows ' . $biz . '\'s leads/revenue (be specific to ' . $ind . ' and what they told us)';
            $L[] = '  action: The single highest-impact fix (name the keyword, campaign type, setting, or tactic)';
            $L[] = 'In the FIRST insight\'s "what", begin with "Biggest opportunity: ". In the second, begin with "Opportunity #2: ". In the third, "Opportunity #3: ".';
            $L[] = 'Directly reference what they said is not working and their biggest challenge. Do not invent problems they did not describe unless the data clearly shows one.';
            $L[] = '';
            $L[] = '{';
            $L[] = '  "headline": "Max 15 words. Frame as improving ' . $biz . '\'s existing Google Ads in ' . $loc . '.",';
            $L[] = '  "sub": "Max 18 words. Reference their avg CPC $' . $avg_cpc . ', their stated challenge, or wasted-spend opportunity.",';
            $L[] = '  "kpis": [';
            $L[] = '    {"label": "Added Leads / Month", "value": "'.$leads_lo.'–'.$leads_hi.'"},';
            $L[] = '    {"label": "By Month 2",          "value": "'.$leads_m2_lo.'–'.$leads_m2_hi.'"},';
            $L[] = '    {"label": "Est. ROI Upside",     "value": "'.$roi_str.'"}';
            $L[] = '  ],';
            $L[] = '  "insight": "Max 20 words. The single biggest lever to improve their current account — cite a number or their own words.",';
            $L[] = '  "insights": [';
            $L[] = '    { "what": "Biggest opportunity: <gap in their current ads, cite a number>", "why": "Why it matters for ' . $biz . '", "action": "One specific fix" }';
            $L[] = '  ],';
            $L[] = '  "roadmap": [';
            $L[] = '    {"week":"Days 1–15","phase":"Audit & Fix","title":"5-word title","points":["what we audit first in their account","the top leak we fix (cite their stated problem)","expected early win with a number"]},';
            $L[] = '    {"week":"Days 16–30","phase":"Optimise","title":"5-word title","points":["bidding or targeting change citing their keyword/CPC $' . $avg_cpc . '","what we cut vs double down on","updated cost-per-lead or lead number"]},';
            $L[] = '    {"week":"Days 31–45","phase":"Expand","title":"5-word title","points":["new campaign type or keyword group to add","competitor gap we exploit (name one)","incremental lead projection"]},';
            $L[] = '    {"week":"Days 46–60","phase":"Scale","title":"5-word title","points":["scale step tied to their goal","budget reallocation to top performers","Month 2 lead or ROI projection"]}';
            $L[] = '  ]';
            $L[] = '}';
        } else {
        $L[] = 'For the "insights" array, produce one insight per service. Each insight MUST follow this structure:';
        $L[] = '  what: What is happening in their market RIGHT NOW (cite a number or competitor name)';
        $L[] = '  why: Why this directly affects ' . $biz . '\'s revenue (be specific to ' . $ind . ')';
        $L[] = '  action: The single most important next step (name the keyword, page, or tactic)';
        if ( !empty($svc_insight_instructions) ) {
            $L[] = 'Per-service guidance:';
            foreach ($svc_insight_instructions as $si) $L[] = '- ' . $si;
        }
        $L[] = '';
        $L[] = '{';
        $L[] = '  "headline": "Max 15 words. Include ' . $biz . ', lead range, and ' . $loc . '.",';
        $L[] = '  "sub": "Max 18 words. State avg CPC $' . $avg_cpc . ' or monthly search volume. Be specific.",';
        $L[] = '  "kpis": [';
        $L[] = '    {"label": "Est. Leads / Month 1", "value": "'.$leads_lo.'–'.$leads_hi.'"},';
        $L[] = '    {"label": "Est. Leads / Month 2", "value": "'.$leads_m2_lo.'–'.$leads_m2_hi.'"},';
        $L[] = '    {"label": "Est. Monthly ROI",     "value": "'.$roi_str.'"}';
        $L[] = '  ],';
        $L[] = '  "insight": "Max 20 words. Single sharpest insight — cite CPC, volume, or rank gap.",';
        $L[] = '  "insights": [';
        $L[] = '    { "what": "What is happening now (cite a number)", "why": "Why it matters for ' . $biz . '", "action": "One specific next step" }';
        $L[] = '  ],';
        $L[] = '  "roadmap": [';
        $L[] = '    {"week":"Week 1–2","phase":"Foundation","title":"5-word title","points":["specific setup action","configuration or audit step","expected first outcome with a number"]},';
        $L[] = '    {"week":"Week 3–4","phase":"Launch","title":"5-word title","points":["go-live action","targeting decision citing their keyword or category","first measurable milestone"]},';
        $L[] = '    {"week":"Day 30–45","phase":"Optimise","title":"5-word title","points":["data-driven cut or scale decision","what underperforms vs what wins in ' . $ind . '","updated ROI or ranking number"]},';
        $L[] = '    {"week":"Day 46–60","phase":"Scale","title":"5-word title","points":["expansion step specific to their goal","new keyword group or content piece to add","Month 2 lead or traffic projection"]}';
        $L[] = '  ]';
        $L[] = '}';
        }

        // Tag the plan output so the UI can render the audit framing + disclaimer
        if ( $is_ga_audit ) {
            $L[] = '';
            $L[] = 'Also include a top-level boolean field "ga_audit": true in the JSON.';
        }

                return implode("\n", $L);
    }

    private static function ask_claude( string $prompt ): ?array {
        $api_key = get_option('six_anthropic_api_key', '');
        if ( ! $api_key ) {
            error_log('6ix Estimate: six_anthropic_api_key not set — using numeric fallback');
            return null;
        }
        $resp = wp_remote_post('https://api.anthropic.com/v1/messages', array(
            'timeout' => 25,
            'headers' => array(
                'x-api-key'         => $api_key,
                'Content-Type'      => 'application/json',
                'anthropic-version' => '2023-06-01',
            ),
            'body' => wp_json_encode(array(
                'model'      => 'claude-sonnet-5',
                'max_tokens' => 2000,
                // Sonnet 5 runs adaptive thinking by default; disable it so
                // the token budget goes to the JSON plan and stays fast
                'thinking'   => array( 'type' => 'disabled' ),
                'messages'   => array( array('role'=>'user','content'=>$prompt) ),
            )),
        ));

        if ( is_wp_error($resp) ) { error_log('6ix Estimate: Claude network error: '.$resp->get_error_message()); return null; }

        $http_code = wp_remote_retrieve_response_code($resp);
        $raw_body  = wp_remote_retrieve_body($resp);
        $data = json_decode($raw_body, true);

        if ( $http_code !== 200 ) {
            error_log("6ix Estimate: Claude HTTP {$http_code}: " . substr($raw_body, 0, 200));
            return null;
        }

        $text = implode('', array_map(fn($b)=>$b['text']??'', $data['content']??array()));
        if ( empty($text) ) { error_log('6ix Estimate: Claude returned empty content'); return null; }
        $json = preg_replace('/^```json\s*|\s*```$/', '', trim($text));

        try {
            $plan = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            error_log('6ix Estimate: Claude response OK');
            return $plan;
        } catch (\Exception $e) {
            error_log('6ix Estimate: JSON parse error: '.$e->getMessage().' raw='.substr($json,0,200));
            return null;
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // NUMERIC FALLBACK (no API — calculated from inputs)
    // ─────────────────────────────────────────────────────────────────────

    private static function numeric_fallback( $co, array $svcs, array $kw, float $cr ): array {
        $svc_labels = array(
            'google-ads'=>'Google Ads','seo'=>'SEO',
            'google-business'=>'Google Business Profile','website'=>'Website Development',
        );
        $biz  = $co->business_name ?? 'your business';
        $ind  = $co->industry      ?? 'your industry';
        // Use target location from questionnaire, not business address
        $target_loc = trim($co->ads_locations ?? $co->seo_locations ?? '');
        $biz_loc    = trim($co->location ?? $co->business_address ?? '');
        $loc        = $target_loc ?: $biz_loc ?: 'your area';
        $loc_type   = trim($co->ads_loc_type ?? 'Include');
        $ch   = implode(' + ', array_map(fn($s)=>$svc_labels[$s]??$s, $svcs));
        $bud  = intval($co->ads_budget??0)+intval($co->seo_budget??0)+intval($co->gbp_budget??0);
        // Clean keywords — strip URLs, take first 3, join nicely
        $raw_kws = $co->ads_keywords ?: $co->seo_keywords ?: '';
        if ( $raw_kws ) {
            $kw_list = array_filter( array_map( 'trim', explode(',', $raw_kws) ) );
            $kw_list = array_filter( $kw_list, fn($k) => ! preg_match('/https?:\/\//', $k) );
            $kw_list = array_slice( array_values($kw_list), 0, 3 );
            $kws = ! empty($kw_list) ? implode(', ', $kw_list) : substr($raw_kws, 0, 60);
        } else {
            $kws = $ind . ' services near me';
        }

        // Clean competitors — strip URLs, take first 2
        $raw_comp = $co->competitors ?? '';
        if ( $raw_comp ) {
            $comp_list = array_filter( array_map( 'trim', explode(',', $raw_comp) ) );
            $comp_list = array_filter( $comp_list, fn($c) => ! preg_match('/https?:\/\//', $c) );
            if ( empty($comp_list) ) {
                // Extract domain names from URLs
                $comp_list = array_map( function($c) {
                    preg_match('/(?:https?:\/\/)?(?:www\.)?([^\/,]+)/', $c, $m);
                    return $m[1] ?? $c;
                }, array_filter( array_map('trim', explode(',', $raw_comp)) ) );
            }
            $comp = implode(' and ', array_slice( array_values($comp_list), 0, 2 ) );
        } else {
            $comp = 'other ' . $ind . ' businesses in ' . ($loc !== 'your area' ? $loc : 'your market');
        }

        // Deal value estimate by industry (used for ROI calculation)
        $deal_values = array(
            'dental'=>800,'dentist'=>800,'orthodont'=>2500,
            'legal'=>3500,'lawyer'=>3500,'attorney'=>3500,
            'real estate'=>8000,'realtor'=>8000,'mortgage'=>2200,
            'plumb'=>650,'hvac'=>1200,'roof'=>7500,'electrician'=>800,
            'contractor'=>4500,'home service'=>900,'landscap'=>1800,
            'medical'=>400,'health'=>400,'physio'=>350,'chiro'=>300,
            'fitness'=>180,'gym'=>120,
            'restaurant'=>85,'food'=>85,'catering'=>600,
            'retail'=>250,'ecommerce'=>180,
            'finance'=>2800,'insurance'=>1400,'accounting'=>2200,
            'marketing'=>3500,'advertising'=>3500,'agency'=>3500,'digital'=>3000,
            'seo'=>2500,'web design'=>4000,'web develop'=>4000,'software'=>5000,
            'it service'=>2800,'tech'=>2500,'saas'=>2000,
            'automotive'=>1100,'auto'=>900,'mechanic'=>600,
            'cleaning'=>400,'security'=>2500,
            'salon'=>150,'spa'=>200,'beauty'=>180,
            'moving'=>900,'storage'=>600,'logistics'=>1200,
            'tutor'=>800,'education'=>600,'school'=>1500,
            'photography'=>1500,'event'=>2000,'wedding'=>3500,
            'consult'=>4000,'coach'=>1200,'recruit'=>3000,
        );
        $deal_value = 1200; // default raised — most B2B services worth more than $500
        foreach ( $deal_values as $kw_d => $val ) {
            if ( strpos($ind, $kw_d) !== false ) { $deal_value = $val; break; }
        }

        // Calculate leads from real CPC if available, else use industry avg
        $avg_cpc    = ! empty($kw['avg_cpc']) ? $kw['avg_cpc'] : self::industry_cpc($ind);
        $clicks     = $bud > 0 && $avg_cpc > 0 ? floor($bud / $avg_cpc) : floor($bud / 3);
        $lo         = max(3, round($clicks * ($cr - 0.8) / 100));
        $hi         = max(6, round($clicks * ($cr + 0.8) / 100));
        $month3_lo  = round($lo * 1.8);
        $month3_hi  = round($hi * 2.2);

        // ROI = (leads × deal_value × close_rate) - budget
        // Assume 20% close rate on inbound leads
        $close_rate = 0.20;
        $rev_lo     = round($lo  * $deal_value * $close_rate);
        $rev_hi     = round($hi  * $deal_value * $close_rate);
        $roi_lo     = max(0, $rev_lo - $bud);
        $roi_hi     = max(0, $rev_hi - $bud);
        // Format ROI — abbreviate large numbers, never show negative
        if ( $bud > 0 && $roi_hi > 0 ) {
            $fmt_n = fn($n) => $n >= 10000 ? '$'.round($n/1000,1).'k' : '$'.number_format($n);
            $roi_str = '+'.$fmt_n($roi_lo).'–'.$fmt_n($roi_hi);
        } elseif ( $bud > 0 && $rev_hi > 0 ) {
            // Revenue exists but less than budget — show estimated revenue instead
            $fmt_n = fn($n) => $n >= 10000 ? '$'.round($n/1000,1).'k' : '$'.number_format($n);
            $roi_str = $fmt_n($rev_lo).'–'.$fmt_n($rev_hi);
        } else {
            $roi_str = 'TBD';
        }

        return array(
            'headline' => "{$biz} is projected to generate {$lo}–{$hi} qualified leads/month through {$ch}" . ($loc !== 'your area' ? " in {$loc}" : '') . ", based on industry benchmarks for {$ind} at your \${$bud}/month investment.",
            'kpis' => array(
                array('label'=>'Est. Leads / Month 1', 'value'=>"{$lo}–{$hi}"),
                array('label'=>'Est. Leads / Month 3', 'value'=>"{$month3_lo}–{$month3_hi}"),
                array('label'=>'Est. Monthly ROI',      'value'=> $roi_str),
            ),
            'roadmap' => array(
                array(
                    'week'   => 'Week 1–2',
                    'phase'  => 'Foundation',
                    'title'  => 'Audit, setup, and campaign architecture',
                    'points' => array(
                        "Audit your current digital presence against {$comp} in {$loc}",
                        "Build campaign structure for \"{$kws}\" — ad groups, bidding strategy, negative keywords",
                        "Install conversion tracking, call tracking, and goal attribution",
                    ),
                    'outcome' => 'All accounts live and tracking',
                ),
                array(
                    'week'   => 'Week 3–4',
                    'phase'  => 'Launch',
                    'title'  => 'Campaigns go live — first data in',
                    'points' => array(
                        "Launch {$ch} targeting {$ind} buyers in {$loc}",
                        "Run A/B tests on headlines and landing page CTAs to maximise conversion rate",
                        "Daily monitoring — adjust bids and targeting based on early signals",
                    ),
                    'outcome' => "First {$lo}–" . round($hi*0.6) . " leads within 30 days",
                ),
                array(
                    'week'   => 'Month 2',
                    'phase'  => 'Optimise',
                    'title'  => 'Data-driven refinement',
                    'points' => array(
                        "Identify top-converting keywords and audiences — double down on winners",
                        "Cut spend on underperformers and reallocate to highest-ROI placements",
                        "Deliver performance report with cost per lead and recommendations",
                    ),
                    'outcome' => 'Lower cost per lead, higher lead quality',
                ),
                array(
                    'week'   => 'Month 3',
                    'phase'  => 'Scale',
                    'title'  => 'Scale winning campaigns',
                    'points' => array(
                        "Expand to new keyword clusters and audience segments in {$loc}",
                        in_array('seo',$svcs) ? "SEO rankings begin improving — organic traffic starts supplementing paid" : "Layer retargeting campaigns to re-engage website visitors",
                        "Forecast months 4–6 with real performance data as baseline",
                    ),
                    'outcome' => "Consistent {$month3_lo}–{$month3_hi} qualified leads/month",
                ),
            ),
        );
    }

    private static function conv_rate( string $ind ): float {
        foreach ( self::$conv_rates as $kw => $r )
            if ( strpos($ind, $kw) !== false ) return $r;
        return self::$default_conv;
    }

    // Industry average CPC benchmarks — Google Ads (WordStream/Google industry data)
    // Used when Google Ads Keyword Planner API is unavailable
    private static function industry_cpc( string $ind ): float {
        $cpcs = array(
            // Legal
            'legal'=>7.50,'lawyer'=>8.20,'attorney'=>8.50,'law firm'=>7.80,
            // Finance
            'insurance'=>9.50,'mortgage'=>8.80,'finance'=>7.20,'accounting'=>5.50,
            'financial plan'=>6.80,'wealth'=>6.20,'tax'=>5.80,'bookkeep'=>4.90,
            // Medical / Dental
            'dental'=>5.80,'dentist'=>5.80,'orthodont'=>6.50,
            'medical'=>4.20,'doctor'=>4.00,'clinic'=>3.80,
            'physio'=>3.50,'chiro'=>3.80,'optom'=>4.10,
            'mental health'=>4.50,'therapist'=>4.80,'counsell'=>4.20,
            // Home Services
            'roof'=>7.20,'roofing'=>7.20,
            'hvac'=>6.80,'heating'=>6.20,'cooling'=>6.00,
            'plumb'=>5.90,'electrician'=>5.50,
            'pest'=>4.80,'landscap'=>3.20,'clean'=>2.80,
            'mover'=>3.50,'moving'=>3.50,'storage'=>2.40,
            'contractor'=>4.80,'renovati'=>4.20,'flooring'=>4.00,
            'home service'=>4.50,'painting'=>3.10,
            // Real Estate
            'real estate'=>4.50,'realtor'=>4.80,'mortgage broker'=>8.50,
            'property'=>3.80,
            // Automotive
            'auto repair'=>3.80,'mechanic'=>3.50,'car dealer'=>2.90,
            'auto body'=>3.20,'towing'=>2.80,'detailing'=>2.40,
            // Marketing / Agency (B2B — higher CPC)
            'marketing'=>8.50,'advertising'=>8.00,'agency'=>7.50,'digital market'=>9.00,
            'seo'=>7.80,'ppc'=>9.50,'google ads'=>9.00,'web design'=>6.50,
            'web develop'=>7.00,'software'=>6.50,'it service'=>5.80,'tech support'=>4.80,
            'saas'=>8.00,'consult'=>6.80,'recruit'=>6.50,
            // Fitness / Wellness
            'gym'=>2.50,'fitness'=>2.80,'personal train'=>3.20,'yoga'=>2.20,
            'spa'=>2.80,'salon'=>2.20,'beauty'=>2.50,'massage'=>2.80,
            // Food / Hospitality
            'restaurant'=>1.80,'café'=>1.60,'catering'=>2.40,'bakery'=>1.50,
            'hotel'=>3.20,'hospitality'=>2.80,
            // Retail / E-commerce
            'retail'=>1.80,'ecommerce'=>1.60,'clothing'=>1.40,'fashion'=>1.50,
            'furniture'=>2.20,'jewel'=>2.80,
            // Education
            'education'=>3.20,'tutor'=>3.50,'school'=>2.80,'driving school'=>3.80,
            // Other
            'photography'=>2.20,'event'=>2.80,'wedding'=>3.50,'security'=>4.20,
        );
        foreach ( $cpcs as $kw => $cpc )
            if ( strpos($ind, $kw) !== false ) return $cpc;
        return 3.50; // general average
    }
}
