<?php
/**
 * Six_Odoo — Complete CRM + Growth Engine
 * Odoo 18 SaaS via XML-RPC
 *
 * WHAT CHANGED FROM v1:
 * ─────────────────────
 * FIXED:   Tasks under "None" — stopped using project.task for CRM flow.
 *          Now uses mail.activity (Odoo's native chatter/timeline system)
 *          linked to crm.lead via res_model + res_id.
 *
 * ADDED:   Full 6-stage CRM pipeline (New Lead → Customer)
 *          HubSpot-style activity timeline on every lead
 *          Abandoned checkout automation (email + SMS + advisor task)
 *          Twilio SMS integration
 *          Odoo email sending with chatter logging
 *          AI lead scoring + priority tagging (Hot/Warm/Cold)
 *          Advisor round-robin assignment
 *          Growth engine: behavior tracking, intent scoring, automation triggers
 *          Full UTM / device / traffic source capture
 *          All IDs cached to avoid duplicate API calls
 *
 * PIPELINE STAGES (3 only, per system spec):
 * ─────────────────────────────────────────
 *   Onboarding Started → Abandoned | Onboarding Submitted → Customer
 *
 * UPLOAD TO: /wp-content/themes/6ixClaude/portal/class-odoo.php
 *
 * ONE-TIME SETUP: /wp-admin/?six_odoo_setup=1
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Forces XML-RPC struct encoding for Odoo 19 kwargs parameter.
 * Odoo 19 changed its RPC parser to require kwargs as struct, not array.
 */
class Six_Odoo_Struct {
    public $data;
    public function __construct( array $data = array() ) {
        $this->data = $data;
    }
}

if ( ! class_exists( 'Six_Odoo' ) ) :
class Six_Odoo {

    // ── Runtime caches (survive single request, not across requests) ──────
    public  static $uid_cache      = null;
    private static $stage_cache    = array();
    private static $activity_cache = array();
    private static $tag_cache      = array();

    // ═════════════════════════════════════════════════════════════════════
    // SECTION 1 — CREDENTIALS & XML-RPC TRANSPORT
    // ═════════════════════════════════════════════════════════════════════

    private static function creds() {
        return array(
            'url'     => rtrim( get_option('six_odoo_url',''), '/' ),
            'db'      => get_option('six_odoo_db',''),
            'user'    => get_option('six_odoo_username',''),
            'api_key' => get_option('six_odoo_api_key',''),
        );
    }

    private static function xml_request( $method, array $params ) {
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<methodCall><methodName>' . esc_xml($method) . '</methodName><params>';
        foreach ( $params as $p ) {
            $xml .= '<param>' . self::xml_value($p) . '</param>';
        }
        $xml .= '</params></methodCall>';
        return $xml;
    }

    private static function xml_value( $v, $force_struct = false ) {
        // Six_Odoo_Struct wrapper forces struct encoding regardless of content
        if ($v instanceof Six_Odoo_Struct) {
            return self::xml_value($v->data, true);
        }
        if ( is_bool($v) )   return '<value><boolean>' . ($v?'1':'0') . '</boolean></value>';
        if ( is_int($v) )    return '<value><int>' . intval($v) . '</int></value>';
        if ( is_float($v) )  return '<value><double>' . floatval($v) . '</double></value>';
        if ( is_null($v) )   return '<value><boolean>0</boolean></value>';
        if ( is_string($v) ) return '<value><string>' . htmlspecialchars($v,ENT_XML1,'UTF-8') . '</string></value>';

        if ( is_array($v) ) {
            // Encode as struct if:
            // (a) forced via Six_Odoo_Struct wrapper (kwargs must be struct for Odoo 19)
            // (b) array has string keys (associative array)
            // Note: empty arrays encode as <array> by default — only kwargs
            // gets struct treatment via the Six_Odoo_Struct wrapper in execute().
            // An empty domain [] must stay as <array> so Odoo's Domain() accepts it.
            $is_struct = $force_struct
                || ( ! empty($v) && array_keys($v) !== range(0, count($v) - 1) )
                || ( ! empty($v) && is_string(array_key_first($v)) );

            if ($is_struct) {
                $xml = '<value><struct>';
                foreach ($v as $k => $val)
                    $xml .= '<member><name>' . htmlspecialchars((string)$k, ENT_XML1, 'UTF-8') . '</name>' . self::xml_value($val) . '</member>';
                return $xml . '</struct></value>';
            }
            // Numeric indexed array → <array>
            $xml = '<value><array><data>';
            foreach ($v as $val) $xml .= self::xml_value($val);
            return $xml . '</data></array></value>';
        }
        return '<value><string></string></value>';
    }

    // Store last fault for diagnostics
    public static $last_fault = null;

    private static function xml_parse( $body ) {
        $xml = @simplexml_load_string($body);
        if (!$xml) {
            self::$last_fault = 'Could not parse XML response';
            error_log('6ix Odoo: Could not parse XML. Body: ' . substr($body,0,500));
            return false;
        }
        if (isset($xml->fault)) {
            $fault = self::parse_value($xml->fault->value);
            self::$last_fault = $fault;
            error_log('6ix Odoo fault: ' . wp_json_encode($fault));
            return false;
        }
        self::$last_fault = null;
        if (isset($xml->params->param->value))
            return self::parse_value($xml->params->param->value);
        return false;
    }

    private static function parse_value( $v ) {
        if (isset($v->array->data)) {
            $arr = array();
            foreach ($v->array->data->value as $item) $arr[] = self::parse_value($item);
            return $arr;
        }
        if (isset($v->struct)) {
            $arr = array();
            foreach ($v->struct->member as $m)
                $arr[(string)$m->name] = self::parse_value($m->value);
            return $arr;
        }
        if (isset($v->int))     return intval((string)$v->int);
        if (isset($v->i4))      return intval((string)$v->i4);
        if (isset($v->i8))      return intval((string)$v->i8);
        if (isset($v->boolean)) return (bool)(int)(string)$v->boolean;
        if (isset($v->double))  return floatval((string)$v->double);
        if (isset($v->string))  return (string)$v->string;
        if (isset($v->nil))     return null;
        return (string)$v;
    }

    private static function xmlrpc_post( $url, $method, array $params ) {
        $response = wp_remote_post($url, array(
            'timeout' => 25,
            'headers' => array(
                'Content-Type' => 'text/xml; charset=utf-8',
                'User-Agent'   => '6ix-Developers-Portal/2.0',
            ),
            'body'    => self::xml_request($method, $params),
        ));
        if (is_wp_error($response)) {
            error_log('6ix Odoo network: ' . $response->get_error_message());
            return false;
        }
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        if (defined('WP_DEBUG') && WP_DEBUG)
            error_log("6ix Odoo [{$code}] {$method}: " . substr($body,0,400));
        if ($code !== 200) {
            error_log("6ix Odoo HTTP {$code}: " . substr($body,0,300));
            return false;
        }
        return self::xml_parse($body);
    }

    // ═════════════════════════════════════════════════════════════════════
    // SECTION 2 — AUTHENTICATION
    // ═════════════════════════════════════════════════════════════════════

    public static function authenticate() {
        if (self::$uid_cache) return self::$uid_cache;
        $c = self::creds();
        if (!$c['url']||!$c['db']||!$c['user']||!$c['api_key']) return false;
        $uid = self::xmlrpc_post(
            $c['url'] . '/xmlrpc/2/common', 'authenticate',
            array($c['db'], $c['user'], $c['api_key'], array())
        );
        if (!$uid || !is_int($uid) || $uid===0) {
            error_log('6ix Odoo: Auth failed.');
            return false;
        }
        self::$uid_cache = $uid;
        return $uid;
    }

    public static function test_connection() {
        self::$uid_cache = null;
        return is_int(self::authenticate());
    }

    // Public wrapper so external classes (Six_Growth_Engine) can call execute()
    public static function execute_public( $model, $method, $args=array(), $kwargs=array() ) {
        return self::execute( $model, $method, $args, $kwargs );
    }

    // Diagnostic wrapper — returns array with result AND fault message
    public static function diagnostic_test( $model, $method, $args=array(), $kwargs=array() ) {
        self::$last_fault = null;
        $result = self::execute( $model, $method, $args, $kwargs );
        if ($result === false && self::$last_fault) {
            // Return the fault as a string so setup page can display it
            return '__FAULT__: ' . wp_json_encode(self::$last_fault);
        }
        return $result; // array on success, false on network error
    }

    private static function execute( $model, $method, $args=array(), $kwargs=array() ) {
        $c   = self::creds();
        $uid = self::authenticate();
        if (!$uid) return false;
        // IMPORTANT: kwargs (last param) must be XML-RPC <struct>, never <array>.
        // Odoo 19 changed the RPC parser and an empty <array> causes IndexError.
        // We pass an OdooKwargs wrapper so xml_value knows to force struct encoding.
        return self::xmlrpc_post(
            $c['url'] . '/xmlrpc/2/object', 'execute_kw',
            array($c['db'], $uid, $c['api_key'], $model, $method, $args,
                  new Six_Odoo_Struct($kwargs))  // force struct encoding
        );
    }

    // ═════════════════════════════════════════════════════════════════════
    // SECTION 3 — ID LOOKUP HELPERS (cached to avoid duplicate API calls)
    // ═════════════════════════════════════════════════════════════════════

    /**
     * Get CRM stage ID by name. Creates it if missing.
     * Checks WP options first, then Odoo, then creates.
     */
    public static function get_stage_id( $stage_name ) {
        $option_key = 'six_odoo_stage_' . sanitize_key($stage_name);

        // 1. In-memory cache
        if (isset(self::$stage_cache[$stage_name]))
            return self::$stage_cache[$stage_name];

        // 2. WP options cache — verify the ID still exists in Odoo before using
        $cached = intval(get_option($option_key, 0));
        if ($cached) {
            $verify = self::execute('crm.stage','search_read',
                array(array(array('id','=',$cached))),
                array('fields'=>array('id','name'),'limit'=>1));
            if (!empty($verify[0]['id'])) {
                error_log("6ix Odoo: Stage '{$stage_name}' from cache → ID={$cached}");
                self::$stage_cache[$stage_name] = $cached;
                return $cached;
            }
            error_log("6ix Odoo: Stage '{$stage_name}' cached ID={$cached} no longer valid — refreshing");
            delete_option($option_key);
        }

        // 3. Search Odoo by exact name
        $ex = self::execute('crm.stage','search_read',
            array(array(array('name','=',$stage_name))),
            array('fields'=>array('id','name'),'limit'=>1));
        if (!empty($ex[0]['id'])) {
            error_log("6ix Odoo: Stage '{$stage_name}' found in Odoo → ID={$ex[0]['id']}");
            update_option($option_key, $ex[0]['id']);
            self::$stage_cache[$stage_name] = $ex[0]['id'];
            return $ex[0]['id'];
        }

        // 4. Try case-insensitive search as fallback
        $ex2 = self::execute('crm.stage','search_read',
            array(array(array('name','ilike',$stage_name))),
            array('fields'=>array('id','name'),'limit'=>1));
        if (!empty($ex2[0]['id'])) {
            error_log("6ix Odoo: Stage '{$stage_name}' found via ilike → ID={$ex2[0]['id']} name='{$ex2[0]['name']}'");
            update_option($option_key, $ex2[0]['id']);
            self::$stage_cache[$stage_name] = $ex2[0]['id'];
            return $ex2[0]['id'];
        }

        // 5. Create it — with sequence and use_on_lead=true for Odoo 19
        error_log("6ix Odoo: Stage '{$stage_name}' not found — creating");
        $id = self::execute('crm.stage','create',array(array(
            'name'         => $stage_name,
            'sequence'     => self::stage_sequence($stage_name),
            'is_won'       => ($stage_name === 'Customer'),
            'use_on_lead'  => true,
        )));
        if ($id && is_int($id)) {
            update_option($option_key, $id);
            self::$stage_cache[$stage_name] = $id;
            error_log("6ix Odoo: Created stage '{$stage_name}' → ID={$id}");
            return $id;
        }

        error_log("6ix Odoo: FAILED to create stage '{$stage_name}' fault=" . wp_json_encode(self::$last_fault));
        return false;
    }

    /**
     * Clear all cached stage IDs — call this if stages get deleted/recreated in Odoo.
     * Visit /wp-admin/?six_clear_stage_cache=1
     */
    public static function clear_stage_cache() {
        // Clear ALL possible stage name variants — old and new
        $all_stages = array(
            'New Lead', 'In Progress', 'Qualified',
            'Abandoned', 'Call Requested', 'Customer', 'Onboarding Submitted', 'Onboarding Started',
            'Account Created', 'Services Selected', 'Questionnaire', 'Strategy Viewed',
        );
        foreach ( $all_stages as $s ) {
            delete_option( 'six_odoo_stage_' . sanitize_key($s) );
        }
        self::$stage_cache = array();
        error_log( '6ix Odoo: Stage cache cleared (all variants)' );
        return true;
    }

    private static function stage_sequence( $name ) {
        $seq = array(
            'Onboarding Started'   => 5,
            'Abandoned'            => 10,
            'Call Requested'       => 15, // middle: higher intent than Abandoned, not yet Submitted
            'Onboarding Submitted' => 20,
            'Customer'             => 30,
        );
        return $seq[$name] ?? 5;
    }

    /**
     * Get mail.activity.type ID by name (e.g. "Todo", "Email", "Call").
     */
    public static function get_activity_type_id( $type_name = 'To-Do' ) {
        $key = strtolower($type_name);
        if (isset(self::$activity_cache[$key]))
            return self::$activity_cache[$key];

        $ck = 'six_odoo_acttype_' . sanitize_key($key);
        $cached = intval(get_option($ck,0));
        if ($cached) { self::$activity_cache[$key]=$cached; return $cached; }

        // Search by exact name first, then ilike
        $ex = self::execute('mail.activity.type','search_read',
            array(array(array('name','=',$type_name))),
            array('fields'=>array('id','name'),'limit'=>1));
        if (empty($ex[0]['id'])) {
            $ex = self::execute('mail.activity.type','search_read',
                array(array(array('name','ilike',$type_name))),
                array('fields'=>array('id','name'),'limit'=>1));
        }
        if (!empty($ex[0]['id'])) {
            update_option($ck,$ex[0]['id']);
            self::$activity_cache[$key]=$ex[0]['id'];
            return $ex[0]['id'];
        }
        return false;
    }

    private static function get_or_create_tag( $name ) {
        if (isset(self::$tag_cache[$name])) return self::$tag_cache[$name];
        $ck = 'six_odoo_tag_'.md5($name);
        $cached = get_option($ck);
        if ($cached) { self::$tag_cache[$name]=intval($cached); return intval($cached); }
        $ex = self::execute('crm.tag','search_read',
            array(array(array('name','=',$name))),array('fields'=>array('id'),'limit'=>1));
        if (!empty($ex[0]['id'])) {
            update_option($ck,$ex[0]['id']);
            self::$tag_cache[$name]=$ex[0]['id'];
            return $ex[0]['id'];
        }
        $id = self::execute('crm.tag','create',array(array('name'=>$name)));
        if ($id) { update_option($ck,$id); self::$tag_cache[$name]=$id; }
        return $id ?: false;
    }

    // ═════════════════════════════════════════════════════════════════════
    // SECTION 4 — CONTACTS (res.partner)
    // ═════════════════════════════════════════════════════════════════════

    public static function create_or_update_contact( $user_id ) {
        $user = get_userdata($user_id);
        if (!$user) return false;
        global $wpdb;
        $co = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d", $user_id));

        $name = trim(($user->first_name??'').' '.($user->last_name??''))
                ?: $user->display_name ?: $user->user_email;

        $data = array(
            'name'         => $name,
            'email'        => $user->user_email,
            'phone'        => get_user_meta($user_id,'billing_phone',true) ?: '',
            'company_name' => $co->business_name ?? '',
            'website'      => $co->website ?? '',
            'customer_rank'=> 1,
            'comment'      => 'WP User ID: '.$user_id.' — 6ix Developers Portal',
        );

        $ex = self::execute('res.partner','search_read',
            array(array(array('email','=',$user->user_email))),
            array('fields'=>array('id'),'limit'=>1));

        if (!empty($ex[0]['id'])) {
            $pid = $ex[0]['id'];
            self::execute('res.partner','write',array(array($pid),$data));
        } else {
            $pid = self::execute('res.partner','create',array($data));
        }
        if ($pid) update_user_meta($user_id,'six_odoo_partner_id',$pid);
        return $pid ?: false;
    }

    public static function sync_client( $user_id ) {
        return self::create_or_update_contact($user_id);
    }

    // ═════════════════════════════════════════════════════════════════════
    // SECTION 5 — CRM LEAD (main record for pipeline)
    // ═════════════════════════════════════════════════════════════════════

    /**
     * Create or update a crm.lead record.
     * $data keys: user_id, status, score, step, services, business_name,
     *             website, goal, challenge, mktg_budget, utm_source,
     *             utm_medium, utm_campaign, device_type
     */
    public static function sync_lead( $data = array() ) {
        $user_id = intval($data['user_id'] ?? 0);
        if (!$user_id) return false;
        $user = get_userdata($user_id);
        if (!$user) return false;

        global $wpdb;
        $co = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d", $user_id));

        $status = $data['status'] ?? 'new';
        $score  = intval($data['score'] ?? $co->score ?? 0);
        $step   = intval($data['step']  ?? $co->step  ?? 0);

        // Stage map — 4 pipeline stages
        // Onboarding Started → Abandoned | Onboarding Submitted → Customer
        $stage_names = array(
            'new'            => 'Onboarding Started',   // just registered
            'started'        => 'Onboarding Started',   // account created, in progress
            'services'       => 'Onboarding Started',
            'questionnaire'  => 'Onboarding Started',
            'strategy'       => 'Onboarding Started',
            'in_progress'    => 'Onboarding Started',
            'abandoned'      => 'Abandoned',
            'call_requested' => 'Call Requested',       // customer asked for a consultation call
            'qualified'      => 'Onboarding Started',
            'submitted'      => 'Onboarding Submitted',  // step 5 complete
            'active'         => 'Customer',
        );
        $stage_name = $stage_names[$status] ?? 'Onboarding Submitted';
        error_log('6ix Odoo: sync_lead status="' . $status . '" → stage="' . $stage_name . '"');
        $stage_id = self::get_stage_id($stage_name);
        error_log('6ix Odoo: sync_lead stage_id=' . var_export($stage_id, true));

        // Stage protection: don't let a low-intent sync pull a lead BACKWARD out
        // of a "sticky" stage. Abandoned and Call Requested are sticky — once a
        // lead reaches them, a routine progress/abandon sync must not overwrite
        // the stage. Only explicit forward moves (active / submitted /
        // call_requested) may advance the stage past a sticky one. In particular
        // an 'abandoned' sync must NOT overwrite a 'Call Requested' lead.
        if ( ! in_array( $status, array('active','submitted','call_requested'), true ) ) {
            $existing_lead_id = intval( get_user_meta($user_id, 'six_odoo_lead_id', true) );
            if ( $existing_lead_id ) {
                $existing = self::execute('crm.lead','search_read',
                    array(array(array('id','=',$existing_lead_id))),
                    array('fields'=>array('stage_id'),'limit'=>1));
                if ( !empty($existing[0]['stage_id']) ) {
                    $current_stage_id   = is_array($existing[0]['stage_id'])
                        ? intval($existing[0]['stage_id'][0])
                        : intval($existing[0]['stage_id']);
                    $abandoned_stage_id = intval( get_option('six_odoo_stage_abandoned', 0) );
                    if ( ! $abandoned_stage_id ) {
                        $abandoned_stage_id = intval( self::get_stage_id('Abandoned') );
                    }
                    // Call Requested may not exist yet — use the cached option only
                    // so we never force-create it during an unrelated sync.
                    $call_req_stage_id = intval( get_option('six_odoo_stage_'.sanitize_key('Call Requested'), 0) );
                    $sticky_ids = array_filter( array( $abandoned_stage_id, $call_req_stage_id ) );
                    if ( $sticky_ids && in_array( $current_stage_id, $sticky_ids, true ) ) {
                        // Lead is in a sticky stage — keep it, just update other fields
                        error_log("6ix Odoo: sync_lead — lead {$existing_lead_id} in sticky stage {$current_stage_id}, preserving stage");
                        $stage_id = $current_stage_id; // keep same stage
                    }
                }
            }
        }

        $prob_map = array('new'=>10,'started'=>20,'in_progress'=>35,
                          'abandoned'=>15,'call_requested'=>50,'qualified'=>65,'submitted'=>70,'active'=>100);

        $partner_id = intval(get_user_meta($user_id,'six_odoo_partner_id',true));
        if (!$partner_id)
            $partner_id = intval(self::create_or_update_contact($user_id) ?: 0);

        // Services
        $svcs = $data['services'] ?? '';
        if (!$svcs) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT service_name FROM {$wpdb->prefix}six_client_services WHERE client_id=%d", $user_id));
            $svcs = implode(', ', array_column((array)$rows,'service_name'));
        }

        $display = trim(($user->first_name??'').' '.($user->last_name??''))
                   ?: $user->display_name ?: $user->user_email;

        // ── Lead score ───────────────────────────────────────────────────
        $lead_score   = self::calculate_lead_score($user_id, $score, $step, $status);
        $priority_val = $lead_score >= 70 ? '2' : ($lead_score >= 40 ? '1' : '0');

        // ── Build lead — only safe scalar fields that Odoo 19 accepts ─────
        // Do NOT include tag_ids (ORM syntax changed), do NOT include user_id
        // from WordPress (it's a WP ID not an Odoo UID — causes write failure).
        // We add tags and advisor assignment via separate calls after creation.
        $biz_name = $co->business_name ?? $data['business_name'] ?? '';
        $ld = array(
            'name'         => trim($display . ($biz_name ? ' — ' . $biz_name : ' — Onboarding')),
            'email_from'   => $user->user_email,
            'partner_name' => $biz_name,
            'phone'        => (string)(get_user_meta($user_id,'billing_phone',true) ?: ''),
            'website'      => (string)($co->website ?? $data['website'] ?? ''),
            'description'  => self::build_desc($user_id,$data,$co,$svcs,$lead_score),
            'probability'  => intval($prob_map[$status] ?? 10),
        );
        if ($stage_id)   $ld['stage_id']   = intval($stage_id);
        if ($partner_id) $ld['partner_id'] = intval($partner_id);

        // Custom fields — only if setup confirmed they exist
        if (get_option('six_odoo_custom_fields_ready')) {
            $ld['x_wp_user_id']          = intval($user_id);
            $ld['x_checkout_score']      = intval($score);
            $ld['x_checkout_step']       = intval($step);
            // x_onboarding_status was created as char — safe to send any string
            // But if Odoo treats it as Selection, skip it to prevent create failure
            // $ld['x_onboarding_status']   = (string)$status;
            $ld['x_services_selected']   = (string)$svcs;
            $ld['x_marketing_goal']      = (string)($co->goal ?? $data['goal'] ?? '');
            $ld['x_marketing_challenge'] = (string)($co->challenge ?? $data['challenge'] ?? '');
            $eff_budget                  = self::effective_monthly_budget($user_id, $co);
            $ld['x_monthly_budget']      = $eff_budget > 0 ? (string)$eff_budget : (string)($co->mktg_budget ?? $data['mktg_budget'] ?? '');
            $ld['x_lead_score']          = intval($lead_score);
            $ld['x_lead_priority']       = $lead_score >= 70 ? 'Hot' : ($lead_score >= 40 ? 'Warm' : 'Cold');
            $ld['x_utm_source']          = (string)(get_user_meta($user_id,'six_utm_source',true) ?: '');
            $ld['x_utm_medium']          = (string)(get_user_meta($user_id,'six_utm_medium',true) ?: '');
            $ld['x_utm_campaign']        = (string)(get_user_meta($user_id,'six_utm_campaign',true) ?: '');
            $ld['x_device_type']         = (string)(get_user_meta($user_id,'six_device_type',true) ?: '');
            $ld['x_ai_recommendation']   = (string)self::generate_ai_recommendation(array(
                'score'=>$lead_score,'step'=>$step,'status'=>$status,'name'=>$display));
        }

        // Create or update
        $odoo_id = intval(get_user_meta($user_id,'six_odoo_lead_id',true));
        if (!$odoo_id && $co) $odoo_id = intval($co->odoo_lead_id ?? 0);

        if ($odoo_id) {
            $ok = self::execute('crm.lead', 'write', array(array($odoo_id), $ld));
            if ($ok === false) {
                error_log('6ix Odoo: crm.lead WRITE failed. lead_id=' . $odoo_id
                    . ' fault=' . wp_json_encode(self::$last_fault));
                return false;
            }
            error_log('6ix Odoo: crm.lead updated ID=' . $odoo_id . ' stage=' . ($stage_name??''));
            return $odoo_id;
        } else {
            $odoo_id = self::execute('crm.lead', 'create', array($ld));
            if (!is_int($odoo_id) || $odoo_id <= 0) {
                error_log('6ix Odoo: crm.lead CREATE failed. user_id=' . $user_id
                    . ' fault=' . wp_json_encode(self::$last_fault)
                    . ' fields=' . wp_json_encode(array_keys($ld)));
                return false;
            }
            error_log('6ix Odoo: crm.lead created ID=' . $odoo_id . ' for user ' . $user_id);
            update_user_meta($user_id, 'six_odoo_lead_id', $odoo_id);
            if ($co) $wpdb->update("{$wpdb->prefix}six_checkout_progress",
                array('odoo_lead_id' => $odoo_id), array('user_id' => $user_id));
            return $odoo_id;
        }
    }

    /**
     * Update only the pipeline stage for a lead.
     */
    public static function update_lead_stage( $lead_id, $stage_name ) {
        if (!$lead_id) return false;
        $stage_id = self::get_stage_id($stage_name);
        if (!$stage_id) return false;
        return self::execute('crm.lead','write',array(array(intval($lead_id)),array('stage_id'=>$stage_id)));
    }

    private static function build_desc($user_id,$data,$co,$svcs,$lead_score=0) {
        $lines = array('Source: 6ix Developers Portal','Lead Score: '.$lead_score.'/100','');
        // Every non-empty onboarding field — advisors see the full picture in Odoo.
        foreach ( self::context_fields($co) as $label=>$val ) $lines[] = $label.': '.$val;
        if ($svcs) $lines[]='Services selected: '.$svcs;
        if ($data['utm_source']??false)  $lines[]='UTM Source: '.$data['utm_source'];
        if ($data['device_type']??false) $lines[]='Device: '.$data['device_type'];
        if (isset($data['step']))        $lines[]='Checkout Step: '.$data['step'].'/4';
        return implode("\n",$lines);
    }

    /**
     * Ordered [label => value] of every non-empty onboarding field on a checkout
     * row. Single source of truth so leads and advisor tasks carry ALL captured
     * data across every stage — not just a handful of columns.
     */
    private static function context_fields( $co ) {
        if ( ! $co ) return array();
        $map = array(
            'business_name'    => 'Business',
            'industry'         => 'Industry',
            'location'         => 'Location',
            'business_address' => 'Address',
            'website'          => 'Website',
            'years_in_business'=> 'Years in business',
            'employees'        => 'Employees',
            'monthly_revenue'  => 'Monthly revenue',
            'phone'            => 'Phone',
            'goal'             => 'Primary goal',
            'challenge'        => 'Biggest challenge',
            'platforms'        => 'Services / platforms',
            'competitors'      => 'Competitors',
            'mktg_budget'      => 'Total monthly budget',
            // Google Ads
            'gads_running'     => 'Currently running Google Ads',
            'ads_locations'    => 'Google Ads - target locations',
            'ads_loc_type'     => 'Google Ads - location type',
            'ads_products'     => 'Google Ads - products/services',
            'ads_keywords'     => 'Google Ads - keywords',
            'ads_usp'          => 'Google Ads - USP',
            'ads_promo'        => 'Google Ads - promotions',
            'ads_schedule'     => 'Google Ads - schedule',
            'ads_budget'       => 'Google Ads - budget',
            'gads_customer_id' => 'Google Ads - Customer ID',
            'gads_link_status' => 'Google Ads - link status',
            // SEO
            'seo_locations'    => 'SEO - target locations',
            'seo_keywords'     => 'SEO - keywords',
            'seo_pages'        => 'SEO - pages/services',
            'seo_usp'          => 'SEO - USP',
            'seo_gsc'          => 'SEO - Search Console access',
            'seo_blog'         => 'SEO - blog/content',
            'seo_competitors'  => 'SEO - competitors',
            'seo_crm_tools'    => 'SEO - CRM/tools',
            'seo_reviews'      => 'SEO - reviews',
            'seo_extra_info'   => 'SEO - extra info',
            'seo_budget'       => 'SEO - budget',
            // Google Business Profile
            'gbp_name'         => 'GBP - business name',
            'gbp_category'     => 'GBP - category',
            'gbp_services'     => 'GBP - services',
            'gbp_hours'        => 'GBP - hours',
            'gbp_rating'       => 'GBP - rating',
            'gbp_budget'       => 'GBP - budget',
            // Website
            'web_goal'         => 'Website - goal',
            'web_pages'        => 'Website - pages',
            'web_style'        => 'Website - style',
            'web_refs'         => 'Website - references',
            'web_existing'     => 'Website - existing site',
            'web_platform'     => 'Website - platform',
            'web_timeline'     => 'Website - timeline',
            'web_features'     => 'Website - features',
            'web_budget'       => 'Website - budget',
            // Misc
            'crm_tools'        => 'CRM tools',
            'reviews_awards'   => 'Reviews / awards',
            'onboarding_notes' => 'Notes',
        );
        $budget_cols = array('ads_budget','seo_budget','gbp_budget','web_budget');
        $out = array();
        foreach ( $map as $col => $label ) {
            $val = $co->$col ?? '';
            if ( $val === '' || $val === null ) continue;
            if ( in_array( $col, $budget_cols, true ) ) {
                if ( intval($val) <= 0 ) continue;
                $val = '$' . number_format( intval($val), 0 ) . '/mo';
            }
            $out[$label] = (string) $val;
        }
        return $out;
    }

    /**
     * Plain-text dump of every captured onboarding field for advisor task notes.
     */
    private static function build_full_context_note( $user_id ) {
        global $wpdb;
        $co = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d", $user_id ) );
        $fields = self::context_fields( $co );
        if ( empty( $fields ) ) return "Onboarding details: (none captured yet)\n";
        $lines = array( 'Onboarding details:' );
        foreach ( $fields as $label => $val ) $lines[] = "- {$label}: {$val}";
        return implode( "\n", $lines ) . "\n";
    }

    /**
     * Best-available monthly budget for a user. Falls back from the stored total
     * to the sum of per-service budget columns, then to active service budgets,
     * so the figure is never blank when any budget data exists.
     */
    public static function effective_monthly_budget( $user_id, $co = null ) {
        global $wpdb;
        if ( ! $co ) {
            $co = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d", $user_id ) );
        }
        $total = $co ? intval( $co->mktg_budget ?? 0 ) : 0;
        if ( $total <= 0 && $co ) {
            $total = intval($co->ads_budget ?? 0) + intval($co->seo_budget ?? 0)
                   + intval($co->gbp_budget ?? 0) + intval($co->web_budget ?? 0);
        }
        if ( $total <= 0 ) {
            $svc_sum = $wpdb->get_var( $wpdb->prepare(
                "SELECT COALESCE(SUM(budget),0) FROM {$wpdb->prefix}six_client_services WHERE client_id=%d", $user_id ) );
            $total = intval( $svc_sum );
        }
        return $total;
    }

    // ═════════════════════════════════════════════════════════════════════
    // SECTION 6 — ACTIVITIES (mail.activity — replaces project.task)
    //
    // WHY THIS FIXES "NONE":
    // mail.activity is linked to the crm.lead record via res_model + res_id.
    // This is how Odoo's native chatter/timeline works — every activity
    // appears in the lead's timeline, just like HubSpot's activity feed.
    // project.task was a separate record with no CRM connection.
    // ═════════════════════════════════════════════════════════════════════

    /**
     * Create a mail.activity on a crm.lead — appears in the lead's timeline.
     *
     * @param int    $lead_id   Odoo crm.lead ID
     * @param string $summary   Short title shown in timeline
     * @param string $note      Full description / body
     * @param string $type      Activity type name: 'Todo', 'Email', 'Call', 'Upload Document'
     * @param int    $days_due  Days from now until due (default 1)
     * @param int    $assigned_uid  Odoo user ID of the advisor (0 = current user)
     */

    /**
     * Strip HTML tags and emojis — returns clean plain text.
     * Called before every activity and chatter note.
     */
    public static function clean_text( $text ) {
        $text = wp_strip_all_tags( (string) $text );
        $text = preg_replace( "/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}]/u", "", $text );
        $text = str_replace( array("<",">","&lt;","&gt;","&amp;","&nbsp;"), "", $text );
        $text = preg_replace( "/
{3,}/", "

", trim( $text ) );
        return $text;
    }

    public static function create_activity( $lead_id, $summary, $note='', $type='Todo', $days_due=0, $assigned_uid=0 ) {
        if (!$lead_id) return false;

        // Try the requested type, then common Odoo 19 names, then Email as last resort
        $type_id = self::get_activity_type_id($type)
                ?: self::get_activity_type_id('To-Do')
                ?: self::get_activity_type_id('Todo')
                ?: self::get_activity_type_id('Email')
                ?: self::get_activity_type_id('Call');
        if (!$type_id) {
            error_log('6ix Odoo: Could not find any usable activity type');
            return false;
        }

        $due = date('Y-m-d', strtotime('+' . intval($days_due) . ' days'));

        // Odoo 19: use activity_schedule() directly ON the crm.lead record.
        // mail.activity.create() with res_id fails on Odoo 19 SaaS because
        // it validates res_id against the model's mail.thread registration.
        // activity_schedule() is the supported API and always works.
        $kwargs = array(
            'activity_type_id' => intval($type_id),
            'summary'          => self::clean_text(substr($summary, 0, 250)),
            'note'             => '<p>' . nl2br(esc_html(self::clean_text($note))) . '</p>',
            'date_deadline'    => $due,
        );
        if ($assigned_uid) $kwargs['user_id'] = intval($assigned_uid);

        // activity_schedule is called on the lead record: crm.lead.activity_schedule([lead_id], **kwargs)
        $result = self::execute(
            'crm.lead',
            'activity_schedule',
            array( array( intval($lead_id) ) ),  // args: list of IDs
            $kwargs                               // kwargs: activity fields
        );

        if ($result === false || (is_array($result) && empty($result))) {
            // Fallback: try mail.activity.create with correct res_id encoding
            error_log('6ix Odoo: activity_schedule failed, trying mail.activity.create. fault=' . wp_json_encode(self::$last_fault));
            $act = array(
                'res_model'        => 'crm.lead',
                'res_id'           => intval($lead_id),
                'activity_type_id' => intval($type_id),
                'summary'          => self::clean_text(substr($summary, 0, 250)),
                'note'             => '<p>' . nl2br(esc_html(self::clean_text($note))) . '</p>',
                'date_deadline'    => $due,
            );
            if ($assigned_uid) $act['user_id'] = intval($assigned_uid);
            $result = self::execute('mail.activity', 'create', array($act));
        }

        if ($result === false || $result === null) {
            error_log('6ix Odoo: activity create FAILED completely. lead_id=' . $lead_id
                . ' fault=' . wp_json_encode(self::$last_fault));
            return false;
        }

        $activity_id = is_array($result) ? ($result[0] ?? false) : $result;
        error_log('6ix Odoo: Activity created ID=' . $activity_id . ' on lead ' . $lead_id);
        return $activity_id;
    }

    /**
     * Get the ir.model integer ID for crm.lead (needed for mail.activity).
     */
    private static function get_crm_model_id() {
        $ck = 'six_odoo_crm_model_id';
        $cached = intval(get_option($ck,0));
        if ($cached) return $cached;
        // Try ir.model first (needs Technical rights)
        $ex = self::execute('ir.model','search_read',
            array(array(array('model','=','crm.lead'))),
            array('fields'=>array('id'),'limit'=>1));
        if (!empty($ex[0]['id'])) {
            update_option($ck,$ex[0]['id']);
            return $ex[0]['id'];
        }
        // Fallback: search res.model for crm.lead (Odoo 17+)
        $ex2 = self::execute('ir.model','search',
            array(array(array('model','=','crm.lead'))));
        if (!empty($ex2[0])) {
            update_option($ck, $ex2[0]);
            return intval($ex2[0]);
        }
        return false;
    }

    /**
     * Post an internal note to the crm.lead chatter (appears in timeline).
     */
    public static function post_note( $lead_id, $body ) {
        if (!$lead_id) return false;
        // message_post is a method call on the record — args must be empty array,
        // kwargs carry the actual fields. Odoo 19 style.
        $result = self::execute('crm.lead', 'message_post',
            array(array(intval($lead_id))),
            array(
                'body'          => nl2br(esc_html(self::clean_text($body))),
                'message_type'  => 'comment',
                'subtype_xmlid' => 'mail.mt_note',
            )
        );
        if ($result === false) {
            error_log('6ix Odoo: post_note failed on lead ' . $lead_id . ' fault=' . wp_json_encode(self::$last_fault));
        }
        return $result;
    }

    // ═════════════════════════════════════════════════════════════════════
    // SECTION 7 — EMAIL VIA ODOO (logged in chatter)
    // ═════════════════════════════════════════════════════════════════════

    /**
     * Send an email FROM Odoo and log it in the lead's chatter.
     * The email appears in the timeline exactly like HubSpot.
     */
    /**
     * Send plain-text email to user AND log clean note to Odoo chatter.
     * Pass plain text as $body_plain — this function converts to HTML for delivery.
     * No HTML tags, no emojis in chatter. Clean plain text only.
     */
    public static function send_email_odoo( $lead_id, $to_email, $subject, $body_plain, $from_name = '6ix Developers' ) {
        if ( ! $to_email ) return false;

        // Build HTML version for email client (plain text → HTML paragraphs)
        $paragraphs = explode( "

", $body_plain );
        $body_html  = implode( '', array_map( function($p) {
            return '<p>' . nl2br( esc_html( trim($p) ) ) . '</p>';
        }, $paragraphs ) );

        $from_email = get_option( 'admin_email', '' );
        $headers    = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
        );

        error_log( "6ix Email: to={$to_email} subject={$subject}" );
        $sent = wp_mail( $to_email, $subject, $body_html, $headers );
        error_log( "6ix Email: result=" . ( $sent ? 'OK' : 'FAILED' ) );

        if ( ! $sent ) {
            global $phpmailer;
            if ( isset($phpmailer) && $phpmailer->ErrorInfo ) {
                error_log( "6ix Email PHPMailer: " . $phpmailer->ErrorInfo );
            }
        }

        // Log clean plain-text note to Odoo chatter — no HTML, no emojis
        if ( $lead_id ) {
            self::post_note( $lead_id,
                "Email sent to: {$to_email}
Subject: {$subject}
Status: "
                . ( $sent ? 'delivered' : 'failed' )
            );
        }

        return $sent;
    }

    /**
     * Build the abandoned checkout email (personalised by business type + step).
     */
    public static function build_abandoned_email( $user_id, $step, $co=null ) {
        global $wpdb;
        if (!$co) $co = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d",$user_id));

        $user  = get_userdata($user_id);
        $name  = $user ? (explode(' ',$user->display_name)[0]) : 'there';
        $biz   = $co->business_name ?? 'your business';
        $biz_type = $co->industry ?? 'business';
        $cta_url = home_url('/get-started/');

        $step_context = array(
            1 => "You've already told us about {$biz} — you're just a few steps from your free evaluation.",
            2 => "You've selected your marketing services — now let us show you what's possible.",
            3 => "Your personalised strategy is ready and waiting for you.",
            4 => "You were one click away from starting your 10-day free consultation.",
        );
        $context_line = $step_context[$step] ?? "Your free business evaluation is waiting.";

        $body = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;color:#1A1816'>
            <h2 style='color:#C0392B'>Finish your setup &amp; unlock your free business evaluation</h2>
            <p>Hi {$name},</p>
            <p>{$context_line}</p>
            <p>Complete your onboarding and let us prove how we can improve your <strong>{$biz_type}</strong>
               in just 10 days — completely risk-free.</p>
            <p>Your insights are already waiting.</p>
            <div style='margin:30px 0'>
                <a href='{$cta_url}' style='background:#C0392B;color:white;padding:14px 28px;
                   text-decoration:none;border-radius:6px;font-weight:bold;display:inline-block'>
                    Continue Setup →
                </a>
            </div>
            <p style='color:#7A7570;font-size:13px'>
                6ix Developers · <a href='" . home_url() . "' style='color:#2C5F8A'>6ixdevelopers.com</a>
            </p>
        </div>";

        return array(
            'subject' => "Finish your setup & unlock your free business evaluation",
            'body'    => $body,
        );
    }

    // ═════════════════════════════════════════════════════════════════════
    // SECTION 8 — SMS VIA TWILIO
    // ═════════════════════════════════════════════════════════════════════

    /**
     * Send an SMS via Twilio and log it in the Odoo lead chatter.
     *
     * Credentials stored in WP options:
     *   six_twilio_account_sid
     *   six_twilio_auth_token
     *   six_twilio_from_number   (e.g. +14155550000)
     */
    public static function send_sms_twilio( $to_phone, $message, $lead_id=0 ) {
        $sid   = get_option('six_twilio_account_sid','');
        $token = get_option('six_twilio_auth_token','');
        $from  = get_option('six_twilio_from_number','');

        error_log('6ix Twilio PRE-FLIGHT: sid=' . ($sid?'set':'MISSING')
            . ' token=' . ($token?'set':'MISSING')
            . ' from=' . ($from?:('MISSING'))
            . ' to=' . $to_phone);

        if (!$sid || !$token || !$from) {
            error_log('6ix Twilio: BLOCKED — Missing credentials. Go to WP Admin → 6ix Portal → Integrations → Twilio.');
            return false;
        }

        // Normalise to E.164 format (+1XXXXXXXXXX for North American numbers)
        $to_phone = preg_replace('/[^+\d]/', '', $to_phone);
        // Add +1 if it's a 10-digit North American number with no country code
        if (strlen($to_phone) === 10 && $to_phone[0] !== '+') {
            $to_phone = '+1' . $to_phone;
        }
        // Add + if it's 11 digits starting with 1 (no +)
        if (strlen($to_phone) === 11 && $to_phone[0] === '1') {
            $to_phone = '+' . $to_phone;
        }
        if (!$to_phone || strlen($to_phone) < 10) {
            error_log('6ix Twilio: Invalid phone number: ' . $to_phone);
            return false;
        }
        error_log('6ix Twilio: Normalised phone → ' . $to_phone);

        $response = wp_remote_post(
            "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json",
            array(
                'timeout'  => 15,
                'headers'  => array(
                    'Authorization' => 'Basic ' . base64_encode("{$sid}:{$token}"),
                    'Content-Type'  => 'application/x-www-form-urlencoded',
                ),
                'body' => array(
                    'From' => $from,
                    'To'   => $to_phone,
                    'Body' => $message,
                ),
            )
        );

        if (is_wp_error($response)) {
            error_log('6ix Twilio network: ' . $response->get_error_message());
            return false;
        }

        $raw_body = wp_remote_retrieve_body($response);
        $body     = json_decode($raw_body, true);
        $code     = wp_remote_retrieve_response_code($response);
        $status   = $body['status']  ?? 'unknown';
        $sid_msg  = $body['sid']     ?? '';
        $error_msg= $body['message'] ?? ($body['error_message'] ?? '');
        $error_code=$body['code']    ?? '';

        // Full debug log — shows exactly what Twilio returned
        error_log("6ix Twilio [{$code}] Status:{$status} SID:{$sid_msg}"
            . ($error_msg ? " Error:{$error_msg} Code:{$error_code}" : '')
            . " To:{$to_phone} From:{$from}");

        $success = ($code === 201 && $sid_msg);

        if (!$success) {
            // Common Twilio errors explained
            $hint = '';
            if ($error_code == 21608) $hint = ' → HINT: This number is unverified. Go to console.twilio.com → Verified Caller IDs → add ' . $to_phone;
            if ($error_code == 21211) $hint = ' → HINT: Invalid To phone number. Use E.164 format: +1XXXXXXXXXX';
            if ($error_code == 21212) $hint = ' → HINT: Invalid From number. Check your Twilio number in WP Admin → Integrations';
            if ($error_code == 21606) $hint = ' → HINT: From number cannot send SMS. Buy an SMS-capable Twilio number.';
            if ($error_code == 20003) $hint = ' → HINT: Authentication failed. Check Account SID and Auth Token.';
            error_log("6ix Twilio FAILED:{$hint}");
        }

        // Log in Odoo chatter
        if ($lead_id) {
            if ($success) {
                $log = " SMS queued to {$to_phone}
Message: {$message}
Twilio SID: {$sid_msg}";
            } else {
                $log = " SMS failed to {$to_phone}
Error: {$error_msg} (code:{$error_code})";
                if ($error_code == 21608) $log .= "
Fix: Verify this number at console.twilio.com → Verified Caller IDs";
            }
            self::post_note($lead_id, $log);
        }

        return ($code===201 && $sid_msg) ? $sid_msg : false;
    }

    /**
     * Build the abandoned checkout SMS (personalised).
     */
    public static function build_abandoned_sms( $user_id, $co=null ) {
        global $wpdb;
        if (!$co) $co = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d",$user_id));
        $user = get_userdata($user_id);
        $name = $user ? explode(' ',$user->display_name)[0] : '';
        $cta  = home_url('/get-started/');
        $greeting = $name ? "Hey {$name}, " : "Hey, ";
        return "{$greeting}your free business evaluation is ready. Finish your setup to see results in 10 days. Continue here: {$cta}";
    }

    // ═════════════════════════════════════════════════════════════════════
    // SECTION 9 — ABANDONED CHECKOUT HANDLER (full automation)
    // ═════════════════════════════════════════════════════════════════════

    /**
     * Full abandonment automation flow:
     *  1. Update lead stage → Abandoned
     *  2. Create mail.activity (shows in timeline, assigned to advisor)
     *  3. Send SMS via Twilio (immediate)
     *  4. Send email via Odoo (logged in chatter)
     *  5. Schedule 24h follow-up activity
     */
    /**
     * ABANDONMENT HANDLER — fires when user leaves onboarding without completing.
     * Rules: 3-minute debounce in JS (no false fires on tab switch).
     * Stage: always Abandoned.
     * Activity: clean plain text, no HTML, no emojis.
     * Messages: exact wording per spec.
     */
    public static function handle_abandoned_checkout( $user_id, $step, $score ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            error_log( '6ix Odoo: handle_abandoned_checkout — user not found: ' . $user_id );
            return false;
        }

        // Cooldown: 10 minutes between abandon triggers to prevent duplicate messages
        $last = get_user_meta( $user_id, 'six_last_abandon_odoo', true );
        if ( $last && ( time() - intval($last) ) < 86400 ) {
            error_log( "6ix Odoo: Abandon 24h cooldown active for user {$user_id} — skip" );
            return false;
        }
        update_user_meta( $user_id, 'six_last_abandon_odoo', time() );

        // NOTE: Do NOT reset fired flags here.
        // Flags are set to 1 by the Growth Engine before sending, and
        // are only reset in on_re_engage() when the user returns.
        // Resetting here allows duplicate sends on any second call.

        global $wpdb;
        $co = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d", $user_id
        ) );

        $first   = trim( $user->first_name ?: '' );
        $last_nm = trim( $user->last_name  ?: '' );
        $display = trim( "$first $last_nm" ) ?: $user->display_name ?: $user->user_email;

        error_log( "6ix Odoo: handle_abandoned_checkout user={$user_id} step={$step} score={$score}" );

        // 1. Ensure lead exists in CRM
        $lead_id = self::sync_lead( array(
            'user_id' => $user_id,
            'status'  => 'abandoned',
            'score'   => intval( $score ),
            'step'    => intval( $step ),
        ) );

        // If sync_lead failed, try to get existing lead and continue anyway
        if ( ! $lead_id ) {
            $lead_id = intval( get_user_meta( $user_id, 'six_odoo_lead_id', true ) );
            error_log( "6ix Odoo: sync_lead failed, using existing lead_id={$lead_id}" );
            if ( ! $lead_id ) {
                error_log( "6ix Odoo: FAIL — no lead found for user {$user_id}" );
                return false;
            }
        }

        // Force stage to Abandoned explicitly — ensures it even if sync_lead skipped it
        $abandoned_stage = self::get_stage_id('Abandoned');
        error_log( "6ix Odoo: Forcing stage Abandoned → ID=" . var_export($abandoned_stage, true) );
        if ( $abandoned_stage ) {
            $write_result = self::execute('crm.lead','write',
                array( array($lead_id), array('stage_id' => intval($abandoned_stage)) )
            );
            error_log( "6ix Odoo: Stage write result=" . var_export($write_result, true) );
        } else {
            error_log( "6ix Odoo: WARNING — could not get Abandoned stage ID" );
        }

        error_log( "6ix Odoo: Lead {$lead_id} processing abandoned flow for user {$user_id}" );

        $advisor_uid = self::get_advisor_odoo_uid( $user_id );
        $phone       = get_user_meta( $user_id, 'billing_phone', true ) ?: ( $co->phone ?? '' );

        // 2. Rich activity with full context — due TODAY
        $step_labels = array(0=>'Personal Info',1=>'Business Profile',2=>'Services & Budget',3=>'Strategy',4=>'Agreement & Payment');
        $step_label  = $step_labels[intval($step)] ?? "Step {$step}";
        $biz_name    = $co->business_name    ?? '';
        $industry    = $co->industry         ?? '';
        $location    = $co->location         ?? '';
        $budget      = $co->mktg_budget      ?? '';
        $goal        = $co->goal             ?? '';
        $challenge   = $co->challenge        ?? '';
        $website     = $co->website          ?? '';
        $revenue     = $co->monthly_revenue  ?? '';
        $advisor_url = home_url('/advisor-portal/?tab=clients&client=' . $user_id);

        $abandon_count = intval(get_user_meta($user_id,'six_abandon_count',true)) + 1;
        update_user_meta($user_id,'six_abandon_count',$abandon_count);
        $repeat_note = $abandon_count > 1 ? "Abandonment #{$abandon_count} — repeat visitor, higher intent.\n" : '';

        // Pull selected services from DB if user reached step 2+
        $svcs_note = '';
        if ( intval($step) >= 2 ) {
            $svcs = $wpdb->get_results( $wpdb->prepare(
                "SELECT service_name, budget FROM {$wpdb->prefix}six_client_services WHERE client_id=%d ORDER BY id ASC",
                $user_id
            ) );
            if ( $svcs ) {
                $svcs_note = "\nServices selected:\n";
                foreach ( $svcs as $sv ) {
                    $svcs_note .= "- {$sv->service_name}: \$" . number_format(floatval($sv->budget),0) . "/mo\n";
                }
            }
        }

        $act_note = "Contact: {$display}\n"
            . "Email: {$user->user_email}\n"
            . "Phone: " . ($phone ?: 'not provided') . "\n\n"
            . "Stopped at: {$step_label}\n\n"
            . "Business information:\n"
            . ($biz_name  ? "- Business: {$biz_name}\n"       : '')
            . ($industry  ? "- Industry: {$industry}\n"       : '')
            . ($location  ? "- Location: {$location}\n"       : '')
            . ($website   ? "- Website: {$website}\n"         : '')
            . ($revenue   ? "- Revenue: {$revenue}\n"         : '')
            . ($budget    ? "- Marketing budget: {$budget}\n" : '')
            . ($goal      ? "- Goal: {$goal}\n"               : '')
            . ($challenge ? "- Challenge: {$challenge}\n"     : '')
            . $svcs_note
            . "\nAction required:\n"
            . $repeat_note
            . "Contact today — SMS and email already sent automatically.\n"
            . "Advisor profile: {$advisor_url}";

        self::create_activity( $lead_id, 'Abandoned onboarding', $act_note, 'Todo', 0, $advisor_uid );

        // 3. Chatter note — plain text
        self::post_note( $lead_id,
            "Abandoned at: {$step_label}\n"
            . "Date: " . current_time('mysql') . "\n"
            . "Phone: " . ($phone ?: 'none') . "\n"
            . "Abandonment count: {$abandon_count}\n"
            . "SMS: scheduled +1 min via cron\n"
            . "Email: scheduled +10 min via cron"
        );

        // ── Send SMS immediately ────────────────────────────────────────────
        if ( $phone && ! get_user_meta($user_id, 'six_abandon_fired_sms', true) ) {
            update_user_meta( $user_id, 'six_abandon_fired_sms', 1 );
            $sms_text = "Checking in again! Complete your onboarding to see where your business stands "
                      . "and how we can help. Feel free to call me if you have any questions: "
                      . home_url('/get-started/');
            self::send_sms_twilio( $phone, $sms_text, $lead_id );
            error_log("6ix Odoo: Abandon SMS sent to user {$user_id} phone={$phone}");
        } elseif ( ! $phone ) {
            error_log("6ix Odoo: Abandon SMS skipped — no phone for user {$user_id}");
        }

        // ── Send email immediately ──────────────────────────────────────────
        if ( ! get_user_meta($user_id, 'six_abandon_fired_email', true) ) {
            update_user_meta( $user_id, 'six_abandon_fired_email', 1 );
            $first_name = trim($user->first_name ?: '') ?: 'there';
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
            self::send_email_odoo( $lead_id, $user->user_email, $subject, $body );
            error_log("6ix Odoo: Abandon email sent to user {$user_id} email={$user->user_email}");
        }

        error_log("6ix Odoo: Abandon flow complete for user {$user_id} lead {$lead_id}");
        return $lead_id;
    }

    // Backwards compat
    public static function create_abandoned_task( $user_id, $step, $score ) {
        return self::handle_abandoned_checkout($user_id,$step,$score);
    }

    /**
     * INITIAL MESSAGE — fires immediately when user submits personal info (step 0).
     * Spec: "Anastasia from 6ix Developers here. I see you've started the
     * onboarding process — let me know if you have any questions or feel free
     * to call me."
     */
    public static function on_personal_info_submitted( $user_id ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) return false;

        // Don't send twice
        if ( get_user_meta( $user_id, 'six_welcome_sent', true ) ) return false;
        update_user_meta( $user_id, 'six_welcome_sent', 1 );

        // Create lead at 'Onboarding Started' stage — moves to Abandoned or Onboarding Submitted later
        $lead_id = self::sync_lead( array(
            'user_id' => $user_id,
            'status'  => 'started',
            'score'   => 15,
            'step'    => 1,
        ) );

        if ( ! $lead_id ) {
            error_log( "6ix Odoo: on_personal_info_submitted — sync_lead failed for user {$user_id}" );
            return false;
        }

        $first      = trim( $user->first_name ?: '' );
        $first_name = $first ?: 'there';
        $phone      = get_user_meta( $user_id, 'billing_phone', true );

        // SMS — exact wording per spec
        $sms = "Anastasia from 6ix Developers here. I see you've started the onboarding process — let me know if you have any questions or feel free to call me.";
        if ( $phone ) {
            self::send_sms_twilio( $phone, $sms, $lead_id );
        }

        // Email
        $subject = 'Welcome to 6ix Developers';
        $body    = "Hi {$first_name},

Anastasia from 6ix Developers here.

I see you've started the onboarding process — let me know if you have any questions or feel free to call me.

Best,
Anastasia
6ix Developers";
        self::send_email_odoo( $lead_id, $user->user_email, $subject, $body );

        // Log clean note — no HTML, no emojis
        self::post_note( $lead_id,
            "Onboarding started at " . current_time('mysql') . "
"
            . "Welcome SMS sent: " . ( $phone ? 'yes' : 'no' ) . "
"
            . "Welcome email sent: yes"
        );

        error_log( "6ix Odoo: Welcome message sent for user {$user_id} lead {$lead_id}" );
        return $lead_id;
    }

    /**
     * COMPLETION HANDLER — fires when user completes all steps.
     * Stage: Onboarding Submitted (advisor promotes to Customer manually).
     * Spec message: "Thank you for submitting the onboarding. An advisor has
     * been assigned and will get back to you shortly."
     */
    public static function on_onboarding_completed( $user_id, $services_str = '', $budget_total = 0, $payment_saved = false ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) return false;

        $first      = trim( $user->first_name ?: '' );
        $first_name = $first ?: 'there';
        $display    = trim( $user->first_name . ' ' . $user->last_name ) ?: $user->display_name ?: $user->user_email;

        // 1. Move lead to Onboarding Submitted
        $lead_id = self::sync_lead( array(
            'user_id'  => $user_id,
            'status'   => 'submitted',
            'score'    => 100,
            'step'     => 4,
            'services' => $services_str,
        ) );

        if ( ! $lead_id ) {
            error_log( "6ix Odoo: on_onboarding_completed — sync_lead failed for user {$user_id}" );
            return false;
        }

        $advisor_uid = self::get_advisor_odoo_uid( $user_id );
        $phone       = get_user_meta( $user_id, 'billing_phone', true );

        // 2. SMS — exact wording per spec
        $sms = "Thank you for submitting the onboarding. An advisor has been assigned and will get back to you shortly.";
        if ( $phone ) {
            self::send_sms_twilio( $phone, $sms, $lead_id );
        }

        // 3. Email — exact wording per spec
        $subject = 'Your onboarding submission — 6ix Developers';
        $body    = "Hi {$first_name},

Thank you for submitting the onboarding. An advisor has been assigned and will get back to you shortly.

Services selected: " . ( $services_str ?: 'See submission' ) . "
Monthly budget: $" . number_format($budget_total, 0) . "/mo

Best,
6ix Developers Team";
        self::send_email_odoo( $lead_id, $user->user_email, $subject, $body );

        // 4. Rich advisor activity — due TODAY, full submission context
        global $wpdb;
        $co_row      = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d", $user_id));
        $advisor_url = home_url('/advisor-portal/?tab=clients&client=' . $user_id);
        $phone_disp  = get_user_meta($user_id,'billing_phone',true) ?: 'not provided';

        $comp_note = "Contact: {$display}\n"
            . "Email: {$user->user_email}\n"
            . "Phone: {$phone_disp}\n\n"
            . "Business information:\n"
            . ($co_row->business_name   ? "- Business: {$co_row->business_name}\n"      : '')
            . ($co_row->industry        ? "- Industry: {$co_row->industry}\n"           : '')
            . ($co_row->location        ? "- Location: {$co_row->location}\n"           : '')
            . ($co_row->website         ? "- Website: {$co_row->website}\n"             : '')
            . ($co_row->employees       ? "- Employees: {$co_row->employees}\n"         : '')
            . ($co_row->monthly_revenue ? "- Revenue: {$co_row->monthly_revenue}\n"     : '')
            . ($co_row->goal            ? "- Goal: {$co_row->goal}\n"                   : '')
            . ($co_row->challenge       ? "- Challenge: {$co_row->challenge}\n"         : '')
            . "\nSubmission details:\n"
            . "- Services: " . ($services_str ?: 'none') . "\n"
            . "- Monthly budget: $" . number_format($budget_total,0) . "/mo\n"
            . "- Payment method saved: " . ($payment_saved ? 'yes' : 'no') . "\n\n"
            . "Action required:\n"
            . "Review submission and get in touch with the customer.\n"
            . "Advisor profile: {$advisor_url}";

        self::create_activity( $lead_id, 'Review onboarding submission', $comp_note, 'Todo', 0, $advisor_uid );

        // 5. Clean chatter note
        self::post_note( $lead_id,
            "Onboarding completed at " . current_time('mysql') . "
"
            . "Services: " . ( $services_str ?: 'none' ) . "
"
            . "Budget: $" . number_format($budget_total, 0) . "/mo
"
            . "Payment saved: " . ( $payment_saved ? 'yes' : 'no' ) . "
"
            . "SMS sent: " . ( $phone ? 'yes' : 'no' ) . "
"
            . "Email sent: yes"
        );

        error_log( "6ix Odoo: Onboarding completed for user {$user_id} lead {$lead_id}" );
        return $lead_id;
    }

    /**
     * Customer requested a consultation call during onboarding.
     * Moves the lead into the "Call Requested" pipeline stage — a MIDDLE stage,
     * neither abandoned nor submitted — and generates an advisor task on the
     * lead's profile, exactly like on_onboarding_completed does for submissions.
     * Never runs the abandoned flow.
     *
     * @param int   $user_id
     * @param array $args  call_date, call_time, call_notes, services, score, step
     */
    public static function on_call_requested( $user_id, $args = array() ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) return false;

        $call_date  = sanitize_text_field( $args['call_date']  ?? '' );
        $call_time  = sanitize_text_field( $args['call_time']  ?? '' );
        $call_notes = sanitize_text_field( $args['call_notes'] ?? '' );
        $services   = sanitize_text_field( $args['services']   ?? '' );
        $score      = intval( $args['score'] ?? 0 );
        $step       = intval( $args['step']  ?? 0 );

        self::create_or_update_contact( $user_id );

        // 1. Move lead to Call Requested stage (sync_lead carries ALL questionnaire data)
        $lead_id = self::sync_lead( array(
            'user_id'  => $user_id,
            'status'   => 'call_requested',
            'score'    => $score,
            'step'     => $step,
            'services' => $services,
        ) );
        if ( ! $lead_id ) {
            error_log( "6ix Odoo: on_call_requested — sync_lead failed for user {$user_id}" );
            return false;
        }

        $advisor_uid = self::get_advisor_odoo_uid( $user_id );
        $advisor_url = home_url( '/advisor-portal/?tab=clients&client=' . $user_id );

        // 2. Rich advisor task — due on the requested call date, full context
        $note = "Customer requested a consultation call.\n\n"
            . "Requested date: " . ( $call_date ?: 'not specified' ) . "\n"
            . "Requested time: " . ( $call_time ?: 'not specified' ) . "\n"
            . ( $call_notes ? "Customer notes: {$call_notes}\n" : '' )
            . "Services of interest: " . ( $services ?: 'not specified' ) . "\n\n"
            . self::build_full_context_note( $user_id )
            . "\nAction required:\nCall the customer at the requested time and continue onboarding.\n"
            . "Advisor profile: {$advisor_url}";

        // Due on the requested call date when provided, else today.
        $days_due = 0;
        if ( $call_date && ( $ts = strtotime( $call_date ) ) ) {
            $days_due = max( 0, (int) ceil( ( $ts - time() ) / DAY_IN_SECONDS ) );
        }
        self::create_activity(
            $lead_id,
            'Consultation call requested' . ( $call_time ? " — {$call_time}" : '' ),
            $note,
            'Call',
            $days_due,
            $advisor_uid
        );

        // 3. Chatter note on the lead timeline
        self::post_note( $lead_id,
            "Call requested at " . current_time('mysql') . "\n"
            . "Date: " . ( $call_date ?: '-' ) . "  Time: " . ( $call_time ?: '-' ) . "\n"
            . "Services: " . ( $services ?: 'none' )
        );

        error_log( "6ix Odoo: Call requested for user {$user_id} lead {$lead_id} date={$call_date}" );
        return $lead_id;
    }
    public static function create_task( $data=array() ) {
        // Legacy callers: create an activity on the user's lead instead
        $user_id = intval($data['user_id'] ?? 0);
        if (!$user_id) return false;
        $lead_id = intval(get_user_meta($user_id,'six_odoo_lead_id',true));
        if (!$lead_id) $lead_id = self::sync_lead(array('user_id'=>$user_id));
        if (!$lead_id) return false;
        return self::create_activity($lead_id, $data['name']??'Task', $data['description']??'');
    }
    public static function create_onboarding_task($user_id,$type='new_client',$extra=array()) {
        if ($type==='abandoned')
            return self::handle_abandoned_checkout($user_id,$extra['step']??0,$extra['score']??0);
        $user = get_userdata($user_id);
        $lead_id = intval(get_user_meta($user_id,'six_odoo_lead_id',true));
        if (!$lead_id) $lead_id = self::sync_lead(array('user_id'=>$user_id,'status'=>'active'));
        if (!$lead_id) return false;
        return self::create_activity($lead_id,
            'New Client Onboarded: '.($user?$user->display_name:'Unknown'),
            'Client completed onboarding and is now active.');
    }

    // ═════════════════════════════════════════════════════════════════════
    // SECTION 10 — AI LEAD SCORING
    // ═════════════════════════════════════════════════════════════════════

    /**
     * Calculate a 0-100 intent score based on behavior signals.
     * Stored in x_lead_score on the crm.lead.
     */
    public static function calculate_lead_score( $user_id, $base_score=0, $step=0, $status='new' ) {
        $score = intval($base_score); // start with the marketing readiness score

        // Step completion bonus
        $step_bonus = array(1=>10, 2=>20, 3=>30, 4=>50);
        $score += $step_bonus[$step] ?? 0;

        // Status modifiers
        $status_mod = array(
            'active'=>40,'submitted'=>30,'call_requested'=>30,'qualified'=>25,'in_progress'=>15,'started'=>10,'new'=>5,'abandoned'=>-10
        );
        $score += $status_mod[$status] ?? 0;

        // Return visits (each return visit +5)
        $visits = intval(get_user_meta($user_id,'six_return_visits',true));
        $score += min($visits * 5, 20);

        // Has phone number (+5 intent signal)
        if (get_user_meta($user_id,'billing_phone',true)) $score += 5;

        // Budget entered (+10 high intent)
        global $wpdb;
        $co = $wpdb->get_row($wpdb->prepare(
            "SELECT mktg_budget FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d",$user_id));
        if ($co && $co->mktg_budget && $co->mktg_budget !== 'not_sure') $score += 10;

        return max(0, min(100, $score));
    }

    // ═════════════════════════════════════════════════════════════════════
    // SECTION 11 — AI RECOMMENDATION ENGINE
    // ═════════════════════════════════════════════════════════════════════

    /**
     * Generate a contextual action recommendation for advisors.
     * Stored in x_ai_recommendation on crm.lead and activity notes.
     */
    public static function generate_ai_recommendation( $lead_data ) {
        $score  = intval($lead_data['score']  ?? 0);
        $step   = intval($lead_data['step']   ?? 0);
        $status = $lead_data['status'] ?? 'new';
        $name   = $lead_data['name']   ?? 'This lead';

        // High-intent scenarios
        if ($score >= 70 && $step >= 3 && $status === 'abandoned')
            return " HIGH INTENT: {$name} scored {$score}/100 and abandoned at step {$step}/4. "
                 . "Recommend: Call within 1 hour. Use urgency email template. Offer a direct calendar link.";

        if ($score >= 70 && $status === 'in_progress')
            return " HOT LEAD: {$name} is actively onboarding with score {$score}/100. "
                 . "Recommend: Monitor for completion. Prepare advisor briefing.";

        if ($score >= 70 && $status === 'abandoned')
            return " HIGH SCORE ABANDONMENT: {$name} scored {$score}/100. "
                 . "Recommend: Immediate phone outreach. Personalised email with service recap.";

        // Mid-intent
        if ($score >= 40 && $step >= 2 && $status === 'abandoned')
            return " WARM LEAD: {$name} reached step {$step}/4 (score {$score}/100). "
                 . "Recommend: Send personalised email within 10 minutes. Follow up tomorrow.";

        if ($score >= 40 && $status === 'in_progress')
            return " IN PROGRESS: {$name} is completing onboarding (score {$score}/100). "
                 . "Recommend: Standby — may need assistance at payment step.";

        // Low-intent
        if ($step <= 1 && $status === 'abandoned')
            return " COLD LEAD: {$name} dropped very early (step {$step}/4, score {$score}/100). "
                 . "Recommend: Add to nurture email sequence. Revisit in 7 days.";

        // Active / customer
        if ($status === 'active' || $status === 'submitted')
            return " ACTIVE CLIENT: {$name} has completed onboarding. "
                 . "Recommend: Schedule initial strategy call. Review selected services.";

        return " NEW LEAD: {$name} just entered the pipeline (score {$score}/100). "
             . "Recommend: Review profile and assign appropriate advisor.";
    }

    // ═════════════════════════════════════════════════════════════════════
    // SECTION 12 — ADVISOR ASSIGNMENT
    // ═════════════════════════════════════════════════════════════════════

    /**
     * Assign an advisor to a lead using round-robin.
     * Returns the WordPress user ID of the assigned advisor.
     */
    public static function assign_advisor( $client_user_id ) {
        global $wpdb;

        // Check if already assigned
        $assigned = $wpdb->get_var($wpdb->prepare(
            "SELECT advisor_id FROM {$wpdb->prefix}six_assignments WHERE client_id=%d",
            $client_user_id));
        if ($assigned) return intval($assigned);

        // Get all advisors
        $advisors = get_users(array('role'=>'six_advisor','fields'=>array('ID')));
        if (empty($advisors)) return 0;

        // Round-robin: pick advisor with fewest current clients
        $counts = array();
        foreach ($advisors as $adv) {
            $counts[$adv->ID] = intval($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}six_assignments WHERE advisor_id=%d",$adv->ID)));
        }
        asort($counts);
        $advisor_id = array_key_first($counts);

        // Save assignment
        $wpdb->replace("{$wpdb->prefix}six_assignments",
            array('client_id'=>$client_user_id,'advisor_id'=>$advisor_id),
            array('%d','%d'));

        return intval($advisor_id);
    }

    /**
     * Get the Odoo user_id (integer) for the advisor assigned to a WordPress client.
     * Looks up the advisor's email in Odoo's res.users.
     */
    // Public wrapper so ajax-onboarding.php can call this
    public static function get_advisor_odoo_uid_public( $client_user_id ) {
        return self::get_advisor_odoo_uid( $client_user_id );
    }

    private static function get_advisor_odoo_uid( $client_user_id ) {
        // Try assigned WP advisor first
        $advisor_wp_id = self::assign_advisor($client_user_id);
        $advisor_email = '';

        if ($advisor_wp_id) {
            $advisor = get_userdata($advisor_wp_id);
            if ($advisor) $advisor_email = $advisor->user_email;
        }

        // Emails to try in order: assigned advisor → configured default → API user login
        $default_email = get_option('six_default_advisor_email', 'musab@6ixdevelopers.com');
        $emails_to_try = array_unique(array_filter(array(
            $advisor_email,
            $default_email,
        )));

        foreach ($emails_to_try as $email) {
            $cached_key = 'six_odoo_uid_' . sanitize_email($email);
            $cached_raw = get_transient($cached_key);
            if ($cached_raw !== false) {
                $cached = intval($cached_raw);
                if ($cached > 0) {
                    error_log("6ix Odoo: Advisor UID from cache: {$email} = {$cached}");
                    return $cached;
                }
                continue; // cached miss (-1) — skip this email
            }

            $ex = self::execute('res.users','search_read',
                array(array(array('login','=',$email))),
                array('fields'=>array('id'),'limit'=>1));

            if (!empty($ex[0]['id'])) {
                $uid = intval($ex[0]['id']);
                set_transient($cached_key, $uid, 3600);
                error_log("6ix Odoo: Advisor found: {$email} = Odoo UID {$uid}");
                return $uid;
            }
            // Cache the miss so we don't retry on every request (1 hour)
            set_transient($cached_key, -1, 3600);
            // Only log if it's not the admin/owner account (reduces noise)
            if ($email === $default_email) {
                error_log("6ix Odoo: Default advisor {$email} not found in Odoo — using API user");
            }
        }

        // Last resort: use the API user (UID 2) — always exists
        error_log("6ix Odoo: No advisor found — falling back to API user (UID 2)");
        return 2;
    }

    // ═════════════════════════════════════════════════════════════════════
    // SECTION 13 — BEHAVIOR / UTM TRACKING
    // ═════════════════════════════════════════════════════════════════════

    /**
     * Track a behavioral event and update the lead score.
     * Call this from the portal JS via AJAX whenever a meaningful action occurs.
     *
     * $event_type: 'page_view' | 'step_complete' | 'return_visit' | 'service_selected' | 'budget_entered'
     */
    public static function track_event( $user_id, $event_type, $meta=array() ) {
        if (!$user_id) return;

        switch ($event_type) {
            case 'return_visit':
                $visits = intval(get_user_meta($user_id,'six_return_visits',true));
                update_user_meta($user_id,'six_return_visits',$visits+1);
                break;
            case 'utm_capture':
                foreach (array('utm_source','utm_medium','utm_campaign','utm_term','utm_content') as $k)
                    if (!empty($meta[$k])) update_user_meta($user_id,"six_{$k}",$meta[$k]);
                break;
            case 'device_type':
                update_user_meta($user_id,'six_device_type',$meta['device']??'unknown');
                break;
            case 'step_complete':
                // Track which step they're on — if score changed, update lead
                $step = intval($meta['step']??0);
                if ($step > 0) {
                    $lead_id = intval(get_user_meta($user_id,'six_odoo_lead_id',true));
                    if ($lead_id && $step >= 2)
                        self::update_lead_stage($lead_id,'In Progress');
                }
                break;
        }

        // Store last event timestamp
        update_user_meta($user_id,'six_last_event',time());
        update_user_meta($user_id,'six_last_event_type',$event_type);
    }

    // ═════════════════════════════════════════════════════════════════════
    // SECTION 14 — GET LEADS (for portal dashboard)
    // ═════════════════════════════════════════════════════════════════════

    public static function get_leads() {
        return self::execute('crm.lead','search_read',
            array(array(array('x_wp_user_id','!=',false))),
            array('fields'=>array('name','email_from','stage_id','priority',
                'x_wp_user_id','x_checkout_score',
                'x_lead_score','x_lead_priority','x_ai_recommendation'),
                'limit'=>200)) ?: array();
    }

    // ═════════════════════════════════════════════════════════════════════
    // SECTION 15 — SETUP (one-time, /wp-admin/?six_odoo_setup=1)
    // ═════════════════════════════════════════════════════════════════════

    public static function setup() {
        $results = array();

        // ── Step 1: Verify access to crm.lead by doing a direct search ────
        // We do NOT use ir.model (requires Technical rights).
        // Instead we search crm.lead directly — if that works, CRM is accessible.
        $crm_test = self::execute('crm.lead','search_read',
            array(array()),
            array('fields'=>array('id','name'),'limit'=>1));

        if ( $crm_test === false ) {
            // Access denied or CRM not installed.
            // Try to give a clear diagnostic by checking what we CAN access.
            $results[] = " Cannot access crm.lead — connection works but CRM is blocked.";
            $results[] = "→ Root cause: The API user does not have CRM access rights in Odoo.";
            $results[] = "";
            $results[] = "HOW TO FIX (takes 2 minutes):";
            $results[] = "1. In Odoo → Settings → Users & Companies → Users";
            $results[] = "2. Find your API user: " . get_option('six_odoo_username','');
            $results[] = "3. Click the user → scroll to Access Rights";
            $results[] = "4. Under CRM: set to 'User' or 'Administrator'";
            $results[] = "5. Click Save";
            $results[] = "6. Run this setup page again.";
            $results[] = "";
            $results[] = "If CRM is already installed (check Odoo → Apps → Installed), the issue is ONLY permissions.";
            $results[] = " Setup cannot continue until permissions are fixed.";
            return $results;
        }

        $results[] = " crm.lead accessible — CRM is installed and API user has access.";

        // ── Step 2: Get the ir.model ID (needed for mail.activity) ────────
        // Try with Technical access first, fall back gracefully.
        $model_id = false;
        $models = self::execute('ir.model','search_read',
            array(array(array('model','=','crm.lead'))),
            array('fields'=>array('id'),'limit'=>1));
        if (!empty($models[0]['id'])) {
            $model_id = $models[0]['id'];
            update_option('six_odoo_crm_model_id', $model_id);
            $results[] = " crm.lead model ID: {$model_id} (cached for activities)";
        } else {
            $results[] = " Could not read ir.model — API user may lack Technical rights.";
            $results[] = "→ Activities (mail.activity) may not work without this.";
            $results[] = "→ Fix: Odoo → Settings → Users → your user → Technical → tick 'Technical Features'";
        }

        // Custom fields — Odoo 19 requires field_length for char fields
        // and field_type instead of ttype in some versions. We pass both.
        $char = array('ttype'=>'char','field_length'=>255);
        $int  = array('ttype'=>'integer');
        $text = array('ttype'=>'text');
        $custom_fields = array(
            array_merge($int,  array('name'=>'x_wp_user_id',         'field_description'=>'WP User ID')),
            array_merge($int,  array('name'=>'x_checkout_score',      'field_description'=>'Readiness Score')),
            array_merge($int,  array('name'=>'x_checkout_step',       'field_description'=>'Last Checkout Step')),
            array_merge($char, array('name'=>'x_onboarding_status',   'field_description'=>'Onboarding Status')),
            array_merge($char, array('name'=>'x_services_selected',   'field_description'=>'Selected Services')),
            array_merge($char, array('name'=>'x_marketing_goal',      'field_description'=>'Marketing Goal')),
            array_merge($char, array('name'=>'x_marketing_challenge', 'field_description'=>'Marketing Challenge')),
            array_merge($char, array('name'=>'x_monthly_budget',      'field_description'=>'Marketing Budget Range')),
            array_merge($int,  array('name'=>'x_lead_score',          'field_description'=>'Lead Intent Score')),
            array_merge($char, array('name'=>'x_lead_priority',       'field_description'=>'Lead Priority Tag')),
            array_merge($text, array('name'=>'x_ai_recommendation',   'field_description'=>'AI Recommendation')),
            array_merge($char, array('name'=>'x_utm_source',          'field_description'=>'UTM Source')),
            array_merge($char, array('name'=>'x_utm_medium',          'field_description'=>'UTM Medium')),
            array_merge($char, array('name'=>'x_utm_campaign',        'field_description'=>'UTM Campaign')),
            array_merge($char, array('name'=>'x_device_type',         'field_description'=>'Device Type')),
        );

        foreach ($custom_fields as $fd) {
            $ex = self::execute('ir.model.fields','search_read',
                array(array(array('model_id','=',$model_id),array('name','=',$fd['name']))),
                array('fields'=>array('id','name'),'limit'=>1));
            if (!empty($ex[0]['id'])) {
                $results[] = " Field {$fd['name']} exists";
                continue;
            }
            $new_id = self::execute('ir.model.fields','create',array(array_merge($fd,array(
                'model_id'=>$model_id,'state'=>'manual',
            ))));
            $results[] = $new_id ? " Created {$fd['name']}" : " Failed {$fd['name']}";
        }
        update_option('six_odoo_custom_fields_ready',1);

        // Pipeline stages — 4 stages
        $stages = array(
            array('name'=>'Onboarding Started',   'sequence'=>5),
            array('name'=>'Abandoned',            'sequence'=>10),
            array('name'=>'Onboarding Submitted', 'sequence'=>20),
            array('name'=>'Customer',             'sequence'=>30),
        );
        foreach ($stages as $st) {
            $id = self::get_stage_id($st['name']);
            $results[] = $id ? " Stage '{$st['name']}' (ID:{$id})" : " Could not get/create stage '{$st['name']}'";
        }

        // Cache activity type IDs — Odoo 19 renamed Todo to 'To-Do'
        $todo_id = self::get_activity_type_id('To-Do')
                ?: self::get_activity_type_id('Todo')
                ?: self::get_activity_type_id('Upload Document')
                ?: false;
        if ($todo_id) {
            // Cache whichever name worked under both keys
            update_option('six_odoo_acttype_to-do', $todo_id);
            update_option('six_odoo_acttype_todo',  $todo_id);
            $results[] = " Activity type 'To-Do/Todo' (ID:{$todo_id})";
        } else {
            // Use Email as fallback — it always exists
            $results[] = " To-Do activity type not found — will use Email as fallback for activities";
        }
        foreach (array('Email','Call') as $atype) {
            $id = self::get_activity_type_id($atype);
            $results[] = $id ? " Activity type '{$atype}' (ID:{$id})" : " Activity type '{$atype}' not found";
        }

        // Cache crm.lead model ID for activities
        $cmid = self::get_crm_model_id();
        $results[] = $cmid ? " crm.lead model cached (ID:{$cmid})" : " Could not cache crm.lead model ID";

        // Set default advisor email if not already configured
        if (!get_option('six_default_advisor_email')) {
            update_option('six_default_advisor_email', 'musab@6ixdevelopers.com');
            $results[] = " Default advisor email set: musab@6ixdevelopers.com";
        } else {
            $results[] = " Default advisor email: " . get_option('six_default_advisor_email');
        }

        // Clear advisor UID cache so it re-resolves with new default
        $cache_key = 'six_odoo_uid_' . sanitize_email(get_option('six_default_advisor_email'));
        delete_transient($cache_key);

        return $results;
    }
}
endif;

// ═════════════════════════════════════════════════════════════════════════════
// ADMIN: Setup page — /wp-admin/?six_odoo_setup=1
// ═════════════════════════════════════════════════════════════════════════════
add_action('admin_init','six_odoo_maybe_run_setup');

// Clear stage cache: /wp-admin/?six_clear_stage_cache=1
add_action('admin_init', function() {
    if (!current_user_can('manage_options') || empty($_GET['six_clear_stage_cache'])) return;
    if (class_exists('Six_Odoo')) Six_Odoo::clear_stage_cache();
    wp_redirect(admin_url('?six_odoo_setup=1'));
    exit;
});

// Flush PHP opcache + verify file versions: /wp-admin/?six_flush_cache=1
add_action('admin_init', function() {
    if (!current_user_can('manage_options') || empty($_GET['six_flush_cache'])) return;
    $flushed = false;
    if (function_exists('opcache_reset')) {
        opcache_reset();
        $flushed = true;
    }
    if (function_exists('apc_clear_cache')) {
        apc_clear_cache('opcode');
        $flushed = true;
    }
    // Log file versions so we can confirm correct files are loaded
    $od_file = get_stylesheet_directory() . '/portal/class-odoo.php';
    $ob_file = get_stylesheet_directory() . '/portal/templates/onboarding.php';
    $aj_file = get_stylesheet_directory() . '/portal/ajax-onboarding.php';
    error_log('6ix Version check:');
    error_log('  class-odoo.php: '       . (file_exists($od_file) ? md5_file($od_file) . ' (' . filesize($od_file) . ' bytes)' : 'NOT FOUND'));
    error_log('  onboarding.php: '       . (file_exists($ob_file) ? md5_file($ob_file) . ' (' . filesize($ob_file) . ' bytes)' : 'NOT FOUND'));
    error_log('  ajax-onboarding.php: '  . (file_exists($aj_file) ? md5_file($aj_file) . ' (' . filesize($aj_file) . ' bytes)' : 'NOT FOUND'));
    // Check ajax-onboarding has our consolidated handler
    $aj_content = file_exists($aj_file) ? file_get_contents($aj_file) : '';
    $od_content = file_exists($od_file) ? file_get_contents($od_file) : '';
    // Check key markers in current file versions
    $has_handler  = strpos($aj_content, 'Six_Growth_Engine::on_abandon') !== false;
    $has_stale_v2 = strpos($aj_content, 'six_stale_lead_check_v2') !== false;
    $has_odoo_sms = strpos($od_content, '6ix Odoo: Abandon SMS sent') !== false;
    // Check growth engine has clean on_abandon
    $ge_file2 = get_stylesheet_directory() . '/portal/class-growth-engine.php';
    $ge_content = file_exists($ge_file2) ? file_get_contents($ge_file2) : '';
    $has_ge_clean = strpos($ge_content, '6ix on_abandon: complete uid=') !== false;
    $has_ge_sms   = strpos($ge_content, 'self::cron_abandon_sms') !== false;
    error_log('  ajax-onboarding calls growth engine: ' . ($has_handler  ? 'YES' : 'NO'));
    error_log('  ajax-onboarding stale cron v2: '       . ($has_stale_v2 ? 'YES' : 'NO'));
    error_log('  class-odoo handle_abandoned SMS: '     . ($has_odoo_sms ? 'YES' : 'NO'));
    error_log('  growth-engine on_abandon clean: '      . ($has_ge_clean ? 'YES' : 'NO'));
    error_log('  growth-engine fires SMS directly: '    . ($has_ge_sms   ? 'YES' : 'NO'));
    wp_die(
        '<h2>6ix Cache Flushed</h2>' .
        '<p>Opcache flushed: ' . ($flushed ? 'YES' : 'NO (opcache_reset not available)') . '</p>' .
        '<p>ajax-onboarding calls growth engine: <strong>'  . ($has_handler  ? 'YES ✓' : 'NO') . '</strong></p>' .
        '<p>ajax-onboarding stale cron v2: <strong>'          . ($has_stale_v2 ? 'YES ✓' : 'NO') . '</strong></p>' .
        '<p>class-odoo handle_abandoned SMS: <strong>'        . ($has_odoo_sms ? 'YES ✓' : 'NO') . '</strong></p>' .
        '<p>growth-engine on_abandon clean: <strong>'         . ($has_ge_clean ? 'YES ✓' : 'NO') . '</strong></p>' .
        '<p>growth-engine fires SMS directly: <strong>'       . ($has_ge_sms   ? 'YES ✓' : 'NO') . '</strong></p>' .
        '<p>Check debug.log for file hashes.</p>' .
        '<p><a href="' . admin_url() . '">← Back to admin</a></p>'
    );
});

// Reset test user meta: /wp-admin/?six_reset_user=USER_ID
add_action('admin_init', function() {
    if (!current_user_can('manage_options') || empty($_GET['six_reset_user'])) return;
    $uid = intval($_GET['six_reset_user']);
    if (!$uid) { echo 'Invalid user ID'; exit; }
    $keys = array(
        'six_welcome_sent', 'six_last_abandon_odoo', 'six_last_abandon_trigger',
        'six_checkout_completed', 'six_abandoned_at_step', 'six_abandoned_at',
        'six_abandoned_score', 'six_abandon_fired_sms', 'six_abandon_fired_email',
        'six_abandon_fired_activity', 'six_abandon_fired_followup',
        'six_high_intent_fired', 'six_last_event', 'six_odoo_lead_id',
        'six_last_activity',
    );
    foreach ($keys as $k) delete_user_meta($uid, $k);
    update_user_meta($uid, 'six_checkout_step', 0);
    update_user_meta($uid, 'six_checkout_score', 0);
    echo '<div style="font-family:monospace;padding:20px;background:#0d1117;color:#56D364">';
    echo "<h2>✓ User {$uid} reset complete</h2>";
    echo '<p>All abandon flags cleared. Ready for fresh test.</p>';
    echo '</div>';
    exit;
});

// Clear ALL queued abandon cron jobs: /wp-admin/?six_clear_abandon_crons=1
add_action('admin_init', function() {
    if (!current_user_can('manage_options') || empty($_GET['six_clear_abandon_crons'])) return;
    $hooks = array(
        'six_abandon_sms', 'six_abandon_email', 'six_abandon_activity',
        'six_abandon_followup', 'six_stale_lead_check', 'six_stale_lead_check_v2',
        'six_stale_lead_cron',
    );
    $cleared = 0;
    foreach ($hooks as $hook) {
        // Keep removing until none are scheduled
        while ( wp_next_scheduled($hook) ) {
            $timestamp = wp_next_scheduled($hook);
            wp_unschedule_event($timestamp, $hook);
            $cleared++;
        }
        // Also clear with args variants
        wp_clear_scheduled_hook($hook);
        $cleared++;
    }
    echo '<div style="font-family:monospace;padding:20px;background:#0d1117;color:#56D364">';
    echo '<h2>✓ Abandon cron queue cleared</h2>';
    echo '<p>Removed scheduled events for: ' . implode(', ', $hooks) . '</p>';
    echo '<p>Cleared ' . $cleared . ' event slots.</p>';
    echo '<p><a href="' . admin_url() . '" style="color:#83C5ED">← Back to admin</a></p>';
    echo '</div>';
    exit;
});

// Manually trigger abandon for a user: /wp-admin/?six_test_abandon=USER_ID
add_action('admin_init', function() {
    if (!current_user_can('manage_options') || empty($_GET['six_test_abandon'])) return;
    $uid = intval($_GET['six_test_abandon']);
    if (!$uid) { echo 'Invalid user ID'; exit; }
    // Clear ALL cooldowns so it fires fresh
    delete_user_meta($uid, 'six_last_abandon_odoo');
    delete_user_meta($uid, 'six_last_abandon_trigger');
    delete_user_meta($uid, 'six_abandon_fired_sms');
    delete_user_meta($uid, 'six_abandon_fired_email');
    $step  = intval(get_user_meta($uid, 'six_checkout_step', true) ?: 1);
    $score = intval(get_user_meta($uid, 'six_checkout_score', true) ?: 15);
    $user  = get_userdata($uid);
    echo '<div style="font-family:monospace;padding:20px;background:#0d1117;color:#f0f4f8">';
    echo "<h2>Testing abandon for user {$uid} ({$user->user_email}) step={$step} score={$score}</h2><pre>";
    // Use consolidated handler (same as JS beacon)
    if ( function_exists('six_track_abandoned_checkout') ) {
        $_POST = array('user_id'=>$uid,'step'=>$step,'score'=>$score,'email'=>$user->user_email,'session_id'=>'');
        ob_start();
        six_track_abandoned_checkout();
        ob_get_clean();
        echo "six_track_abandoned_checkout() ran. Check debug.log for results.\n";
    } elseif ( class_exists('Six_Odoo') ) {
        $result = Six_Odoo::handle_abandoned_checkout($uid, $step, $score);
        echo "Result: " . var_export($result, true) . "\n";
        echo "Check debug.log for details.\n";
    }
    echo '</pre></div>';
    exit;
});
function six_odoo_maybe_run_setup() {
    if (!current_user_can('manage_options') || empty($_GET['six_odoo_setup'])) return;
    echo '<div style="font-family:monospace;padding:30px;background:#0d1117;color:#f0f4f8;min-height:100vh">';
    echo '<h2 style="color:#FF6699;margin-bottom:20px">6ix Odoo Setup v2 — CRM + Growth Engine</h2>';
    if (!class_exists('Six_Odoo')) { echo '<p style="color:red">Six_Odoo class not found.</p></div>'; return; }

    $url = get_option('six_odoo_url',''); $db=get_option('six_odoo_db','');
    $usr = get_option('six_odoo_username',''); $key=get_option('six_odoo_api_key') ? '✓ set' : ' not set';
    echo "<table style='font-size:13px;margin-bottom:16px'>";
    foreach (array('URL'=>$url,'DB'=>$db,'Username'=>$usr,'API Key'=>$key) as $k=>$v)
        echo "<tr><td style='padding:3px 16px 3px 0;color:#83C5ED'>{$k}:</td><td>".esc_html($v)."</td></tr>";
    echo "</table>";

    if (!$url||!$db||!$usr||!get_option('six_odoo_api_key')) {
        echo '<p style="color:#FF6B6B"> Missing credentials.</p></div>'; return;
    }

    Six_Odoo::$uid_cache = null;
    $ok = Six_Odoo::test_connection();
    if (!$ok) { echo '<p style="color:#FF6B6B"> Connection failed. Check credentials.</p></div>'; return; }

    echo '<p style="color:#56D364"> Connected! UID: '.intval(Six_Odoo::$uid_cache).'</p>';

    // ── RAW DIAGNOSTIC — shows exact Odoo responses before setup runs ──
    echo '<hr style="border-color:#333;margin:16px 0">';
    echo '<p style="color:#83C5ED;font-weight:bold">Diagnostic Tests:</p>';
    echo '<ul style="line-height:2;font-size:12px">';

    // Test 1: Direct crm.lead search (no special rights needed)
    $d = function($label, $model, $method, $args, $kwargs) {
        $r = Six_Odoo::diagnostic_test($model, $method, $args, $kwargs);
        if (is_array($r))
            echo '<li style="color:#56D364"> '.$label.': '.esc_html(wp_json_encode($r)).'</li>';
        elseif (is_string($r) && strpos($r,'__FAULT__')===0)
            echo '<li style="color:#FF6B6B"> '.$label.' FAULT: '.esc_html(str_replace('__FAULT__: ','',$r)).'</li>';
        else
            echo '<li style="color:#FF6B6B"> '.$label.': returned false (network error or XML parse fail — check debug.log)</li>';
    };

    $d('crm.lead search',       'crm.lead',   'search',      array(array()),            array('limit'=>1));
    $d('crm.lead search_read',  'crm.lead',   'search_read', array(array()),            array('fields'=>array('id','name'),'limit'=>1));
    $d('crm.stage search_read', 'crm.stage',  'search_read', array(array()),            array('fields'=>array('id','name'),'limit'=>3));
    $d('res.partner search',    'res.partner','search_read', array(array()),            array('fields'=>array('id','name'),'limit'=>1));
    $d('ir.model → crm.lead',  'ir.model',   'search_read', array(array(array('model','=','crm.lead'))), array('fields'=>array('id','model'),'limit'=>1));
    $d('mail.activity.type',    'mail.activity.type','search_read',array(array()),     array('fields'=>array('id','name'),'limit'=>3));
    echo '<li style="color:#83C5ED">WP_DEBUG: ' . (defined('WP_DEBUG') && WP_DEBUG ? '&#x2705; on &mdash; see wp-content/debug.log' : '&#x26A0; off &mdash; add define(&quot;WP_DEBUG&quot;,true); to wp-config.php') . '</li>';

    echo '</ul>';
    echo '<hr style="border-color:#333;margin:16px 0">';

    $results = Six_Odoo::setup();
    $has_error = false;
    echo '<ul style="line-height:2.2">';
    foreach ($results as $r) {
        $color = '#f0f4f8';
        if (strpos($r,'') !== false) $color = '#56D364';
        elseif (strpos($r,'') !== false || strpos($r,'') !== false) { $color = '#FF6B6B'; $has_error = true; }
        elseif (strpos($r,'') !== false || strpos($r,'→') !== false) { $color = '#E3B341'; }
        elseif (strpos($r,'') !== false) $color = '#83C5ED';
        echo '<li style="color:'.$color.'">' . $r . '</li>';
    }
    echo '</ul>';
    // Count critical vs non-critical errors
    // Field creation failures are non-critical — existing fields still work
    $critical_error = false;
    foreach ($results as $r) {
        if (strpos($r,'') !== false) { $critical_error = true; break; }
    }
    $field_errors = array_filter($results, function($r){ return strpos($r,' Failed') !== false; });
    if ($critical_error) {
        echo '<p style="color:#FF6B6B;font-size:15px;border:1px solid #FF6B6B;padding:12px 16px;margin-top:16px">
             Setup incomplete — fix the errors above and run this page again.
        </p>';
    } elseif (!empty($field_errors)) {
        echo '<p style="color:#E3B341;font-size:15px;border:1px solid #E3B341;padding:12px 16px;margin-top:16px">
             Setup mostly complete. ' . count($field_errors) . ' field(s) could not be created — run setup again to retry.<br>
            Core features (leads, pipeline, email, SMS, activities) are fully operational.
        </p>';
    } else {
        echo '<p style="color:#56D364;font-size:15px"> Setup complete — all systems ready.</p>';
    }
    echo '</div>'; exit;
}

add_action('wp_ajax_six_test_odoo','six_ajax_test_odoo');
function six_ajax_test_odoo() {
    if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
    Six_Odoo::$uid_cache = null;
    $ok = class_exists('Six_Odoo') && Six_Odoo::test_connection();
    $ok ? wp_send_json_success('Connected ✓ UID: '.intval(Six_Odoo::$uid_cache))
        : wp_send_json_error('Connection failed');
}
