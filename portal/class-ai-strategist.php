<?php
/**
 * Six_AI_Strategist — advisor-facing AI strategy engine.
 *
 * A Claude tool-using agent, grounded in a specific client's real data. The
 * advisor chats; Claude decides when to pull live keyword/SERP/on-page data
 * from DataForSEO or the client's own context, then reasons and produces
 * professional Google Ads / SEO analysis and strategy.
 *
 * "Learning over time" is a memory + feedback + playbook loop:
 *   - every turn is persisted per client (six_ai_messages),
 *   - advisors rate/keep outputs (rating column),
 *   - winning outputs become reusable playbooks (six_ai_playbooks) that are
 *     retrieved and injected as context on future, similar clients.
 *
 * Models: deep modes use Opus, quick modes use Sonnet (overridable via options
 * six_ai_model_deep / six_ai_model_fast).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Six_AI_Strategist {

    const DB_VERSION = 2;
    const MAX_TOOL_LOOPS = 6;

    // Deep, multi-step reasoning modes → Opus. Quick tasks → Sonnet.
    private static $deep_modes = array( 'strategy', 'gads_audit', 'seo_audit', 'performance', 'chat' );

    public static function model_for( $mode ) {
        $deep = get_option( 'six_ai_model_deep', 'claude-opus-5' );
        $fast = get_option( 'six_ai_model_fast', 'claude-sonnet-5' );
        return in_array( $mode, self::$deep_modes, true ) ? $deep : $fast;
    }

    // ── Schema ─────────────────────────────────────────────────────────────
    public static function maybe_create_tables() {
        if ( (int) get_option( 'six_ai_db_v', 0 ) >= self::DB_VERSION ) return;
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        dbDelta( "CREATE TABLE {$wpdb->prefix}six_ai_threads (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            client_id bigint(20) NOT NULL,
            advisor_id bigint(20) DEFAULT 0,
            title varchar(255) DEFAULT '',
            mode varchar(30) DEFAULT 'chat',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY client_id (client_id)
        ) $charset" );
        dbDelta( "CREATE TABLE {$wpdb->prefix}six_ai_messages (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            thread_id bigint(20) NOT NULL,
            role varchar(16) NOT NULL,
            content longtext,
            tools_used varchar(255) DEFAULT '',
            rating tinyint(4) DEFAULT 0,
            rating_note varchar(500) DEFAULT '',
            tokens int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY thread_id (thread_id)
        ) $charset" );
        dbDelta( "CREATE TABLE {$wpdb->prefix}six_ai_playbooks (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            title varchar(255) DEFAULT '',
            industry varchar(120) DEFAULT '',
            service varchar(60) DEFAULT '',
            goal_tags varchar(255) DEFAULT '',
            content longtext,
            source_thread_id bigint(20) DEFAULT 0,
            created_by bigint(20) DEFAULT 0,
            uses int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY industry (industry)
        ) $charset" );
        update_option( 'six_ai_db_v', self::DB_VERSION );
    }

    // ── Client context (grounding) ─────────────────────────────────────────
    public static function client_context( $client_id ) {
        global $wpdb;
        $u  = get_userdata( $client_id );
        if ( ! $u ) return 'Unknown client.';
        $co = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d", $client_id ) );
        $svcs = $wpdb->get_results( $wpdb->prepare(
            "SELECT service_name,status,budget FROM {$wpdb->prefix}six_client_services WHERE client_id=%d", $client_id ) );

        $L = array();
        $L[] = 'CLIENT: ' . $u->display_name . ' (' . $u->user_email . ')';
        if ( $co ) {
            $map = array(
                'business_name'=>'Business','industry'=>'Industry','website'=>'Website',
                'location'=>'Location','business_address'=>'Address','years_in_business'=>'Years in business',
                'goal'=>'Primary goal','competitors'=>'Competitors','platforms'=>'Services of interest',
                'mktg_budget'=>'Total monthly budget','deal_value'=>'Avg customer value ($)','close_rate'=>'Close rate (%)',
                // Google Ads
                'gads_running'=>'Currently running Google Ads','ads_locations'=>'GAds target locations',
                'ads_keywords'=>'GAds keywords','ads_products'=>'GAds products/services','ads_usp'=>'GAds USP','ads_budget'=>'GAds budget',
                // SEO
                'seo_locations'=>'SEO target locations','seo_keywords'=>'SEO keywords','seo_pages'=>'SEO pages','seo_competitors'=>'SEO competitors','seo_budget'=>'SEO budget',
                // GBP
                'gbp_name'=>'GBP name','gbp_category'=>'GBP category','gbp_budget'=>'GBP budget',
            );
            foreach ( $map as $c => $lbl ) {
                $v = $co->$c ?? '';
                if ( $v !== '' && $v !== null && $v !== '0' ) $L[] = "{$lbl}: {$v}";
            }
        }
        // Connected data sources
        $ds = array();
        if ( get_user_meta( $client_id, 'six_gads_customer_id', true ) )   $ds[] = 'Google Ads';
        if ( get_user_meta( $client_id, 'six_ga4_property_id', true ) )    $ds[] = 'GA4';
        if ( get_user_meta( $client_id, 'six_meta_ad_account_id', true ) ) $ds[] = 'Meta Ads';
        if ( get_user_meta( $client_id, 'six_gbp_location_id', true ) )    $ds[] = 'Google Business Profile';
        if ( get_user_meta( $client_id, 'six_gsc_site', true ) )           $ds[] = 'Search Console';
        $L[] = 'Connected data sources: ' . ( $ds ? implode( ', ', $ds ) : 'none yet' );
        if ( $svcs ) {
            $sv = array();
            foreach ( $svcs as $s ) $sv[] = $s->service_name . ' (' . $s->status . ', $' . number_format( floatval( $s->budget ), 0 ) . '/mo)';
            $L[] = 'Active/pending services: ' . implode( ', ', $sv );
        }
        return implode( "\n", $L );
    }

    // ── Tool catalogue exposed to Claude ───────────────────────────────────
    public static function tools() {
        return array(
            array(
                'name' => 'keyword_metrics',
                'description' => 'Get real Google search volume, CPC and competition for specific keywords in a location. Use for sizing demand and estimating ad costs.',
                'input_schema' => array( 'type'=>'object', 'properties'=>array(
                    'keywords'=>array('type'=>'array','items'=>array('type'=>'string'),'description'=>'Keywords to look up'),
                    'location'=>array('type'=>'string','description'=>'City/region, e.g. "Toronto" or "Canada"'),
                ), 'required'=>array('keywords') ),
            ),
            array(
                'name' => 'keyword_ideas',
                'description' => 'Discover related/expansion keywords with volume and difficulty from seed keywords. Use for keyword research and clustering.',
                'input_schema' => array( 'type'=>'object', 'properties'=>array(
                    'seed_keywords'=>array('type'=>'array','items'=>array('type'=>'string')),
                    'location'=>array('type'=>'string'),
                    'limit'=>array('type'=>'integer','description'=>'Max ideas (default 50)'),
                ), 'required'=>array('seed_keywords') ),
            ),
            array(
                'name' => 'keyword_difficulty',
                'description' => 'Get SEO ranking difficulty (0-100) for keywords.',
                'input_schema' => array( 'type'=>'object', 'properties'=>array(
                    'keywords'=>array('type'=>'array','items'=>array('type'=>'string')),
                    'location'=>array('type'=>'string'),
                ), 'required'=>array('keywords') ),
            ),
            array(
                'name' => 'ranked_keywords',
                'description' => "Get the keywords a domain already ranks for in Google (with position and volume). Use to audit the client's or a competitor's organic footprint.",
                'input_schema' => array( 'type'=>'object', 'properties'=>array(
                    'domain'=>array('type'=>'string'),
                    'location'=>array('type'=>'string'),
                    'limit'=>array('type'=>'integer'),
                ), 'required'=>array('domain') ),
            ),
            array(
                'name' => 'competitor_domains',
                'description' => 'Find the top organic competitor domains for a target domain, with overlap and traffic.',
                'input_schema' => array( 'type'=>'object', 'properties'=>array(
                    'domain'=>array('type'=>'string'),
                    'location'=>array('type'=>'string'),
                ), 'required'=>array('domain') ),
            ),
            array(
                'name' => 'live_serp',
                'description' => 'Get the live Google organic top results for a keyword (who ranks and their URLs). Use for SERP/competitor analysis.',
                'input_schema' => array( 'type'=>'object', 'properties'=>array(
                    'keyword'=>array('type'=>'string'),
                    'location'=>array('type'=>'string'),
                ), 'required'=>array('keyword') ),
            ),
            array(
                'name' => 'onpage_audit',
                'description' => 'Fetch on-page SEO signals for a single URL (title, meta, H1s, word count, links, issues).',
                'input_schema' => array( 'type'=>'object', 'properties'=>array(
                    'url'=>array('type'=>'string'),
                ), 'required'=>array('url') ),
            ),
            array(
                'name' => 'client_performance',
                'description' => "Get the client's connected accounts, active services/budgets and any advisor-tracked KPI metrics on file.",
                'input_schema' => array( 'type'=>'object', 'properties'=>new stdClass() ),
            ),
            array(
                'name' => 'google_ads_performance',
                'description' => "Pull the client's LIVE Google Ads performance (last 30 days): spend, clicks, impressions, conversions, CPC, CPA and per-campaign breakdown. Use before making Google Ads optimisation claims.",
                'input_schema' => array( 'type'=>'object', 'properties'=>new stdClass() ),
            ),
            array(
                'name' => 'ga4_analytics',
                'description' => "Pull the client's LIVE GA4 website analytics: sessions, users, pageviews, conversions and traffic by channel. Use to understand where traffic and conversions actually come from.",
                'input_schema' => array( 'type'=>'object', 'properties'=>array(
                    'days'=>array('type'=>'integer','description'=>'Lookback window in days (default 30)'),
                ) ),
            ),
            array(
                'name' => 'search_console',
                'description' => "Pull the client's LIVE Google Search Console data: total clicks/impressions/CTR/average position and top search queries. Use for organic/SEO performance analysis.",
                'input_schema' => array( 'type'=>'object', 'properties'=>array(
                    'days'=>array('type'=>'integer','description'=>'Lookback window in days (default 28)'),
                ) ),
            ),
        );
    }

    private static function dispatch_tool( $name, $input, $client_id ) {
        $loc = $input['location'] ?? '';
        switch ( $name ) {
            case 'keyword_metrics':
                return Six_DataForSEO::keyword_overview( $input['keywords'] ?? array(), $loc );
            case 'keyword_ideas':
                return Six_DataForSEO::keyword_ideas( $input['seed_keywords'] ?? array(), $loc, $input['limit'] ?? 50 );
            case 'keyword_difficulty':
                return Six_DataForSEO::keyword_difficulty( $input['keywords'] ?? array(), $loc );
            case 'ranked_keywords':
                return Six_DataForSEO::ranked_keywords( $input['domain'] ?? '', $loc, $input['limit'] ?? 50 );
            case 'competitor_domains':
                return Six_DataForSEO::competitors( $input['domain'] ?? '', $loc );
            case 'live_serp':
                return Six_DataForSEO::serp( $input['keyword'] ?? '', $loc );
            case 'onpage_audit':
                return Six_DataForSEO::onpage( $input['url'] ?? '' );
            case 'client_performance':
                return self::performance_snapshot( $client_id );
            case 'google_ads_performance':
                if ( ! class_exists( 'Six_Google_Ads' ) ) return array( 'error' => 'Google Ads integration unavailable.' );
                $m = Six_Google_Ads::get_campaign_metrics_for_client( $client_id );
                if ( $m === false ) return array( 'error' => Six_Google_Ads::get_last_error() ?: 'Google Ads data unavailable.' );
                if ( empty( $m ) ) return array( 'note' => 'Google Ads connected but no active-campaign data in the last 30 days.' );
                return $m;
            case 'ga4_analytics':
                if ( ! class_exists( 'Six_Analytics' ) ) return array( 'error' => 'Analytics integration unavailable.' );
                $prop = get_user_meta( $client_id, 'six_ga4_property_id', true ) ?: get_option( 'six_ga4_property_id', '' );
                if ( ! $prop ) return array( 'error' => 'No GA4 property ID connected for this client.' );
                return Six_Analytics::ga4_summary( $prop, $input['days'] ?? 30 );
            case 'search_console':
                if ( ! class_exists( 'Six_Analytics' ) ) return array( 'error' => 'Analytics integration unavailable.' );
                $site = get_user_meta( $client_id, 'six_gsc_site', true );
                if ( ! $site ) return array( 'error' => 'No Search Console site connected for this client.' );
                return Six_Analytics::gsc_summary( $site, $input['days'] ?? 28 );
        }
        return array( 'error' => 'Unknown tool: ' . $name );
    }

    private static function performance_snapshot( $client_id ) {
        global $wpdb;
        $out = array( 'connected' => array(), 'services' => array(), 'kpis' => array() );
        foreach ( array(
            'Google Ads'=>'six_gads_customer_id','GA4'=>'six_ga4_property_id','Meta Ads'=>'six_meta_ad_account_id',
            'Google Business Profile'=>'six_gbp_location_id','Search Console'=>'six_gsc_site',
        ) as $label => $key ) {
            $v = get_user_meta( $client_id, $key, true );
            if ( $v ) $out['connected'][ $label ] = $v;
        }
        $svcs = $wpdb->get_results( $wpdb->prepare(
            "SELECT service_name,status,budget FROM {$wpdb->prefix}six_client_services WHERE client_id=%d", $client_id ) );
        foreach ( (array) $svcs as $s ) $out['services'][] = array( 'name'=>$s->service_name,'status'=>$s->status,'budget'=>floatval($s->budget) );
        $metrics = $wpdb->get_results( $wpdb->prepare(
            "SELECT label,current_value,previous_value,service_slug FROM {$wpdb->prefix}six_metrics WHERE client_id=%d", $client_id ) );
        foreach ( (array) $metrics as $m ) $out['kpis'][] = array( 'label'=>$m->label,'current'=>$m->current_value,'previous'=>$m->previous_value,'service'=>$m->service_slug );
        if ( ! $out['kpis'] ) $out['kpis_note'] = 'No KPI metrics recorded yet; rely on onboarding context and live keyword data.';
        // Tell the agent which LIVE data tools will work for this client.
        $live = array();
        if ( get_user_meta( $client_id, 'six_gads_customer_id', true ) ) $live[] = 'google_ads_performance';
        if ( get_user_meta( $client_id, 'six_ga4_property_id', true ) || get_option( 'six_ga4_property_id', '' ) ) $live[] = 'ga4_analytics';
        if ( get_user_meta( $client_id, 'six_gsc_site', true ) ) $live[] = 'search_console';
        $out['available_live_tools'] = $live ?: array( 'none — recommend the advisor connect Google Ads / GA4 / Search Console' );
        return $out;
    }

    // ── Playbook retrieval (the "learns over time" context) ────────────────
    private static function retrieve_playbooks( $client_id, $mode, $limit = 3 ) {
        global $wpdb;
        $co = $wpdb->get_row( $wpdb->prepare(
            "SELECT industry, platforms FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d", $client_id ) );
        $industry = $co->industry ?? '';
        $rows = array();
        if ( $industry ) {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT title, content FROM {$wpdb->prefix}six_ai_playbooks
                 WHERE industry LIKE %s ORDER BY uses DESC, created_at DESC LIMIT %d",
                '%' . $wpdb->esc_like( $industry ) . '%', $limit ) );
        }
        if ( count( $rows ) < $limit ) {
            $more = $wpdb->get_results( $wpdb->prepare(
                "SELECT title, content FROM {$wpdb->prefix}six_ai_playbooks ORDER BY uses DESC, created_at DESC LIMIT %d", $limit ) );
            $rows = array_merge( $rows, (array) $more );
        }
        // De-dup by title
        $seen = array(); $out = array();
        foreach ( $rows as $r ) { if ( isset($seen[$r->title]) ) continue; $seen[$r->title]=1; $out[] = $r; if ( count($out) >= $limit ) break; }
        return $out;
    }

    private static function system_prompt( $client_id, $mode ) {
        $mode_briefs = array(
            'chat'        => 'Act as a senior digital marketing strategist. Answer the advisor precisely and back claims with data you pull via tools.',
            'keywords'    => 'Run professional keyword research: pull volumes/ideas/difficulty, cluster by intent, and recommend a prioritised target list with rationale.',
            'gads_audit'  => 'Perform a Google Ads audit and opportunity analysis: demand sizing, keyword/CPC economics, structure, negatives, budget allocation and expected outcomes.',
            'seo_audit'   => 'Perform an SEO analysis: on-page signals, ranked keywords, competitor gaps, content and technical recommendations, prioritised by impact/effort.',
            'strategy'    => 'Build a complete 90-day digital marketing strategy with monthly milestones, channel mix, budget allocation, KPIs and expected results grounded in real data.',
            'performance' => 'Review the current performance data and recommend concrete next actions and optimisations.',
        );
        $brief = $mode_briefs[ $mode ] ?? $mode_briefs['chat'];

        $sys  = "You are the 6ix Developers AI Strategist — an elite Google Ads and SEO analyst working alongside a human advisor at a professional digital marketing agency. "
              . "Your job: deliver rigorous, specific, data-backed analysis and strategy for the client below. "
              . "Use the available tools to pull REAL keyword, SERP, on-page and performance data before making numeric claims — never invent volumes or CPCs. "
              . "Be concise but complete. Use clear headings and bullet points. Give concrete numbers, prioritised recommendations, and the reasoning behind them. "
              . "When you lack a data point, say so and either pull it with a tool or state the assumption.\n\n"
              . "TASK FOCUS: {$brief}\n\n"
              . "=== CLIENT CONTEXT ===\n" . self::client_context( $client_id ) . "\n";

        $pb = self::retrieve_playbooks( $client_id, $mode );
        if ( $pb ) {
            $sys .= "\n=== PROVEN 6ix PLAYBOOKS (reuse what fits this client) ===\n";
            foreach ( $pb as $p ) {
                $sys .= "• {$p->title}\n" . mb_substr( wp_strip_all_tags( $p->content ), 0, 1200 ) . "\n\n";
            }
        }
        return $sys;
    }

    // ── Anthropic call ─────────────────────────────────────────────────────
    private static function call_anthropic( $model, $system, $tools, $messages ) {
        $api_key = get_option( 'six_anthropic_api_key', '' );
        if ( ! $api_key ) return array( 'error' => 'Anthropic API key not set (six_anthropic_api_key).' );
        $resp = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
            'timeout' => 120,
            'headers' => array(
                'x-api-key' => $api_key, 'Content-Type' => 'application/json', 'anthropic-version' => '2023-06-01',
            ),
            'body' => wp_json_encode( array(
                'model' => $model, 'max_tokens' => 4096, 'system' => $system, 'tools' => $tools, 'messages' => $messages,
            ) ),
        ) );
        if ( is_wp_error( $resp ) ) return array( 'error' => 'Network error: ' . $resp->get_error_message() );
        $code = wp_remote_retrieve_response_code( $resp );
        $body = json_decode( wp_remote_retrieve_body( $resp ), true );
        if ( $code !== 200 ) {
            $msg = $body['error']['message'] ?? substr( wp_remote_retrieve_body( $resp ), 0, 300 );
            return array( 'error' => "Claude HTTP {$code}: {$msg}" );
        }
        return $body;
    }

    /**
     * Run one advisor turn: append the message, run the tool loop, persist and
     * return the assistant reply.
     * @return array success, reply, tools_used, thread_id, message_id, error
     */
    public static function run( $client_id, $thread_id, $advisor_id, $user_message, $mode = 'chat' ) {
        self::maybe_create_tables();
        global $wpdb;

        $client_id = intval( $client_id );
        if ( ! $client_id || ! get_userdata( $client_id ) ) return array( 'success'=>false, 'error'=>'Invalid client.' );
        $user_message = trim( wp_kses_post( $user_message ) );
        if ( $user_message === '' ) return array( 'success'=>false, 'error'=>'Empty message.' );

        // Thread
        $thread_id = intval( $thread_id );
        if ( ! $thread_id ) {
            $wpdb->insert( "{$wpdb->prefix}six_ai_threads", array(
                'client_id'=>$client_id, 'advisor_id'=>$advisor_id,
                'title'=> mb_substr( $user_message, 0, 60 ), 'mode'=>$mode,
                'created_at'=>current_time('mysql'), 'updated_at'=>current_time('mysql'),
            ) );
            $thread_id = intval( $wpdb->insert_id );
        }

        // Prior turns → Anthropic messages (text only, keeps history simple).
        $history = $wpdb->get_results( $wpdb->prepare(
            "SELECT role, content FROM {$wpdb->prefix}six_ai_messages WHERE thread_id=%d AND role IN ('user','assistant') ORDER BY id ASC LIMIT 30", $thread_id ) );
        $messages = array();
        foreach ( (array) $history as $h ) {
            $messages[] = array( 'role'=>$h->role, 'content'=>$h->content );
        }
        $messages[] = array( 'role'=>'user', 'content'=>$user_message );

        // Persist the user message now.
        $wpdb->insert( "{$wpdb->prefix}six_ai_messages", array(
            'thread_id'=>$thread_id, 'role'=>'user', 'content'=>$user_message, 'created_at'=>current_time('mysql') ) );

        $system = self::system_prompt( $client_id, $mode );
        $tools  = self::tools();
        $model  = self::model_for( $mode );

        $tools_used = array();
        $final_text = '';
        for ( $i = 0; $i < self::MAX_TOOL_LOOPS; $i++ ) {
            $body = self::call_anthropic( $model, $system, $tools, $messages );
            if ( isset( $body['error'] ) ) {
                return array( 'success'=>false, 'error'=>$body['error'], 'thread_id'=>$thread_id );
            }
            $content = $body['content'] ?? array();
            $stop    = $body['stop_reason'] ?? '';

            if ( $stop === 'tool_use' ) {
                // Append assistant turn (verbatim content) then run each tool.
                $messages[] = array( 'role'=>'assistant', 'content'=>$content );
                $tool_results = array();
                foreach ( $content as $block ) {
                    if ( ( $block['type'] ?? '' ) !== 'tool_use' ) continue;
                    $tools_used[] = $block['name'];
                    $result = self::dispatch_tool( $block['name'], $block['input'] ?? array(), $client_id );
                    $tool_results[] = array(
                        'type' => 'tool_result',
                        'tool_use_id' => $block['id'],
                        'content' => wp_json_encode( $result ),
                    );
                }
                $messages[] = array( 'role'=>'user', 'content'=>$tool_results );
                continue;
            }

            // Final assistant text
            foreach ( $content as $block ) {
                if ( ( $block['type'] ?? '' ) === 'text' ) $final_text .= $block['text'];
            }
            break;
        }

        if ( $final_text === '' ) $final_text = 'The strategist could not complete the analysis in the allotted steps. Please refine the request or try again.';

        $wpdb->insert( "{$wpdb->prefix}six_ai_messages", array(
            'thread_id'=>$thread_id, 'role'=>'assistant', 'content'=>$final_text,
            'tools_used'=> implode( ',', array_unique( $tools_used ) ), 'created_at'=>current_time('mysql') ) );
        $message_id = intval( $wpdb->insert_id );
        $wpdb->update( "{$wpdb->prefix}six_ai_threads", array( 'updated_at'=>current_time('mysql') ), array( 'id'=>$thread_id ) );

        return array(
            'success'    => true,
            'reply'      => $final_text,
            'tools_used' => array_values( array_unique( $tools_used ) ),
            'thread_id'  => $thread_id,
            'message_id' => $message_id,
        );
    }
}

add_action( 'init', array( 'Six_AI_Strategist', 'maybe_create_tables' ), 20 );
