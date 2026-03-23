<?php
/**
 * Six_Odoo — Odoo 18 SaaS Integration via XML-RPC
 * Upload to: /wp-content/themes/6ixClaude/portal/class-odoo.php
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * WHY XML-RPC (not JSON-RPC or REST)
 * ═══════════════════════════════════════════════════════════════════════════
 * Odoo 18 SaaS uses Odoo Online authentication — users have no local password.
 * /web/dataset/call_kw requires a live browser session cookie (not Basic Auth).
 * The ONLY protocol that works stateless with an API key on Odoo SaaS 18 is:
 *
 *   Step 1: POST to /xmlrpc/2/common  → call authenticate() → get uid (integer)
 *   Step 2: POST to /xmlrpc/2/object  → call execute_kw() using uid + api_key
 *
 * The API key is passed as the "password" parameter — Odoo 14+ accepts API
 * keys transparently via the same password field in both XML-RPC endpoints.
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * CREDENTIALS — WP Admin → 6ix Portal → Integrations
 * ═══════════════════════════════════════════════════════════════════════════
 *   six_odoo_url        https://yourcompany.odoo.com   (no trailing slash)
 *   six_odoo_db         your-database-name
 *   six_odoo_username   admin@yourcompany.com
 *   six_odoo_api_key    API key from Odoo → your name → Preferences →
 *                       Account Security → API Keys → New
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * ONE-TIME SETUP — visit /wp-admin/?six_odoo_setup=1
 * ═══════════════════════════════════════════════════════════════════════════
 * Creates custom fields on crm.lead, CRM stages, and the tasks project.
 * Stage IDs are saved to WP options automatically — no manual entry needed.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Six_Odoo' ) ) :
class Six_Odoo {

    private static $uid_cache = null;

    // ─────────────────────────────────────────────────────────────────────
    // CREDENTIALS
    // ─────────────────────────────────────────────────────────────────────

    private static function creds() {
        return array(
            'url'     => rtrim( get_option( 'six_odoo_url', '' ), '/' ),
            'db'      => get_option( 'six_odoo_db', '' ),
            'user'    => get_option( 'six_odoo_username', '' ),
            'api_key' => get_option( 'six_odoo_api_key', '' ),
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // XML-RPC TRANSPORT
    // PHP 8 removed the xmlrpc extension, so we build XML manually.
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Build an XML-RPC methodCall payload.
     */
    private static function xml_request( $method, array $params ) {
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<methodCall><methodName>' . esc_xml( $method ) . '</methodName>';
        $xml .= '<params>';
        foreach ( $params as $p ) {
            $xml .= '<param>' . self::xml_value( $p ) . '</param>';
        }
        $xml .= '</params></methodCall>';
        return $xml;
    }

    /**
     * Recursively encode a PHP value as XML-RPC <value>.
     */
    private static function xml_value( $v ) {
        if ( is_bool( $v ) )   return '<value><boolean>' . ( $v ? '1' : '0' ) . '</boolean></value>';
        if ( is_int( $v ) )    return '<value><int>' . intval($v) . '</int></value>';
        if ( is_float( $v ) )  return '<value><double>' . floatval($v) . '</double></value>';
        if ( is_null( $v ) )   return '<value><boolean>0</boolean></value>';
        if ( is_string( $v ) ) return '<value><string>' . htmlspecialchars( $v, ENT_XML1, 'UTF-8' ) . '</string></value>';

        if ( is_array( $v ) ) {
            // Struct (associative) vs Array (indexed)
            $is_struct = array_keys($v) !== range(0, count($v)-1);
            if ( $is_struct || ( ! empty($v) && is_string( array_key_first($v) ) ) ) {
                $xml = '<value><struct>';
                foreach ( $v as $k => $val ) {
                    $xml .= '<member><name>' . htmlspecialchars((string)$k,ENT_XML1,'UTF-8') . '</name>' . self::xml_value($val) . '</member>';
                }
                $xml .= '</struct></value>';
            } else {
                $xml = '<value><array><data>';
                foreach ( $v as $val ) {
                    $xml .= self::xml_value( $val );
                }
                $xml .= '</data></array></value>';
            }
            return $xml;
        }
        return '<value><string></string></value>';
    }

    /**
     * Parse XML-RPC response body → PHP value.
     */
    private static function xml_parse( $body ) {
        $xml = @simplexml_load_string( $body );
        if ( ! $xml ) return false;

        // Fault
        if ( isset( $xml->fault ) ) {
            $fault = self::parse_value( $xml->fault->value );
            error_log( '6ix Odoo XML-RPC fault: ' . wp_json_encode($fault) );
            return false;
        }

        // Normal response
        if ( isset( $xml->params->param->value ) ) {
            return self::parse_value( $xml->params->param->value );
        }
        return false;
    }

    private static function parse_value( $v ) {
        if ( isset($v->array->data) ) {
            $arr = array();
            foreach ( $v->array->data->value as $item ) {
                $arr[] = self::parse_value( $item );
            }
            return $arr;
        }
        if ( isset($v->struct) ) {
            $arr = array();
            foreach ( $v->struct->member as $m ) {
                $arr[(string)$m->name] = self::parse_value( $m->value );
            }
            return $arr;
        }
        if ( isset($v->int)     ) return intval( (string)$v->int );
        if ( isset($v->i4)      ) return intval( (string)$v->i4 );
        if ( isset($v->i8)      ) return intval( (string)$v->i8 );
        if ( isset($v->boolean) ) return (bool)(int)(string)$v->boolean;
        if ( isset($v->double)  ) return floatval( (string)$v->double );
        if ( isset($v->string)  ) return (string)$v->string;
        if ( isset($v->nil)     ) return null;
        // bare text
        return (string)$v;
    }

    /**
     * Make an XML-RPC POST request.
     */
    private static function xmlrpc_post( $url, $method, array $params ) {
        $body     = self::xml_request( $method, $params );
        $response = wp_remote_post( $url, array(
            'timeout'     => 25,
            'headers'     => array(
                'Content-Type' => 'text/xml; charset=utf-8',
                'User-Agent'   => '6ix-Developers-Portal/1.0',
            ),
            'body'        => $body,
            'sslverify'   => true,
        ) );

        if ( is_wp_error( $response ) ) {
            error_log( '6ix Odoo network error: ' . $response->get_error_message() );
            return false;
        }
        $code     = wp_remote_retrieve_response_code( $response );
        $raw_body = wp_remote_retrieve_body( $response );

        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log( "6ix Odoo [{$code}] {$method}: " . substr($raw_body,0,400) );
        }

        if ( $code !== 200 ) {
            error_log( "6ix Odoo HTTP {$code} on {$method}: " . substr($raw_body,0,300) );
            return false;
        }
        return self::xml_parse( $raw_body );
    }

    // ─────────────────────────────────────────────────────────────────────
    // AUTHENTICATION
    // Calls /xmlrpc/2/common → authenticate() → returns integer uid
    // API key is passed as the password — Odoo 14+ handles this transparently
    // ─────────────────────────────────────────────────────────────────────

    public static function authenticate() {
        if ( self::$uid_cache ) return self::$uid_cache;

        $c = self::creds();
        if ( ! $c['url'] || ! $c['db'] || ! $c['user'] || ! $c['api_key'] ) {
            return false;
        }

        $uid = self::xmlrpc_post(
            $c['url'] . '/xmlrpc/2/common',
            'authenticate',
            array( $c['db'], $c['user'], $c['api_key'], array() )
        );

        if ( ! $uid || ! is_int($uid) || $uid === 0 ) {
            error_log( '6ix Odoo: Authentication failed. Check username and API key.' );
            return false;
        }

        self::$uid_cache = $uid;
        return $uid;
    }

    public static function test_connection() {
        self::$uid_cache = null; // force fresh auth
        $uid = self::authenticate();
        return is_int($uid) && $uid > 0;
    }

    // ─────────────────────────────────────────────────────────────────────
    // EXECUTE_KW — the single method for all ORM operations
    // Calls /xmlrpc/2/object → execute_kw(db, uid, api_key, model, method, args, kwargs)
    // ─────────────────────────────────────────────────────────────────────

    private static function execute( $model, $method, $args = array(), $kwargs = array() ) {
        $c   = self::creds();
        $uid = self::authenticate();
        if ( ! $uid ) return false;

        return self::xmlrpc_post(
            $c['url'] . '/xmlrpc/2/object',
            'execute_kw',
            array( $c['db'], $uid, $c['api_key'], $model, $method, $args, $kwargs )
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // ONE-TIME SETUP
    // Visit /wp-admin/?six_odoo_setup=1
    // ─────────────────────────────────────────────────────────────────────

    public static function setup() {
        $results = array();

        // 1. Get crm.lead model ID for field creation
        $model_id = false;
        $models = self::execute( 'ir.model', 'search_read',
            array( array( array('model','=','crm.lead') ) ),
            array( 'fields' => array('id'), 'limit' => 1 )
        );
        if ( ! empty($models[0]['id']) ) {
            $model_id = $models[0]['id'];
            $results[] = "✅ Found crm.lead model (ID: {$model_id})";
        } else {
            $results[] = "❌ Could not find crm.lead model — check API permissions";
            return $results;
        }

        // 2. Create custom fields on crm.lead
        $custom_fields = array(
            array( 'name'=>'x_wp_user_id',          'field_description'=>'WP User ID',             'ttype'=>'integer' ),
            array( 'name'=>'x_checkout_score',       'field_description'=>'Readiness Score',        'ttype'=>'integer' ),
            array( 'name'=>'x_checkout_step',        'field_description'=>'Last Checkout Step',     'ttype'=>'integer' ),
            array( 'name'=>'x_onboarding_status',    'field_description'=>'Onboarding Status',      'ttype'=>'char'    ),
            array( 'name'=>'x_services_selected',    'field_description'=>'Selected Services',      'ttype'=>'char'    ),
            array( 'name'=>'x_marketing_goal',       'field_description'=>'Marketing Goal',         'ttype'=>'char'    ),
            array( 'name'=>'x_marketing_challenge',  'field_description'=>'Marketing Challenge',    'ttype'=>'char'    ),
            array( 'name'=>'x_monthly_budget',       'field_description'=>'Marketing Budget Range', 'ttype'=>'char'    ),
        );

        foreach ( $custom_fields as $fd ) {
            // Check if exists
            $ex = self::execute( 'ir.model.fields', 'search_read',
                array( array( array('model_id','=',$model_id), array('name','=',$fd['name']) ) ),
                array( 'fields'=>array('id','name'), 'limit'=>1 )
            );
            if ( ! empty($ex[0]['id']) ) {
                $results[] = "⏭ Field {$fd['name']} already exists (ID: {$ex[0]['id']})";
                continue;
            }
            $new_id = self::execute( 'ir.model.fields', 'create', array( array_merge($fd, array(
                'model_id' => $model_id,
                'state'    => 'manual',
            )) ) );
            if ( $new_id ) {
                $results[] = "✅ Created field {$fd['name']} (ID: {$new_id})";
            } else {
                $results[] = "❌ Failed to create field {$fd['name']}";
            }
        }

        update_option( 'six_odoo_custom_fields_ready', 1 );

        // 3. Create CRM pipeline stages
        $stages = array(
            array( 'name'=>'New Lead',               'sequence'=>1,  'key'=>'six_odoo_stage_new' ),
            array( 'name'=>'Onboarding In Progress', 'sequence'=>5,  'key'=>'six_odoo_stage_inprogress' ),
            array( 'name'=>'Onboarding Submitted',   'sequence'=>10, 'key'=>'six_odoo_stage_submitted' ),
            array( 'name'=>'Active Client',          'sequence'=>15, 'key'=>'six_odoo_stage_active' ),
        );
        foreach ( $stages as $st ) {
            $ex = self::execute( 'crm.stage', 'search_read',
                array( array( array('name','=',$st['name']) ) ),
                array( 'fields'=>array('id'), 'limit'=>1 )
            );
            if ( ! empty($ex[0]['id']) ) {
                update_option( $st['key'], $ex[0]['id'] );
                $results[] = "⏭ Stage '{$st['name']}' already exists (ID: {$ex[0]['id']}) — saved";
            } else {
                $new_id = self::execute( 'crm.stage', 'create', array( array(
                    'name'     => $st['name'],
                    'sequence' => $st['sequence'],
                    'is_won'   => $st['name'] === 'Active Client',
                ) ) );
                if ( $new_id ) {
                    update_option( $st['key'], $new_id );
                    $results[] = "✅ Created stage '{$st['name']}' (ID: {$new_id}) — saved to WP options";
                } else {
                    $results[] = "❌ Failed to create stage '{$st['name']}'";
                }
            }
        }

        // 4. Create project for tasks
        $proj_id = intval( get_option('six_odoo_project_id',0) );
        if ( ! $proj_id ) {
            $ex = self::execute( 'project.project', 'search_read',
                array( array( array('name','=','6ix Developers — Onboarding') ) ),
                array( 'fields'=>array('id'), 'limit'=>1 )
            );
            if ( ! empty($ex[0]['id']) ) {
                update_option('six_odoo_project_id',$ex[0]['id']);
                $results[] = "⏭ Project exists (ID: {$ex[0]['id']}) — saved";
            } else {
                $new_id = self::execute( 'project.project', 'create', array( array(
                    'name'               => '6ix Developers — Onboarding',
                    'privacy_visibility' => 'employees',
                ) ) );
                if ( $new_id ) {
                    update_option('six_odoo_project_id',$new_id);
                    $results[] = "✅ Created project (ID: {$new_id}) — saved";
                } else {
                    $results[] = "❌ Failed to create project";
                }
            }
        } else {
            $results[] = "⏭ Project already set (ID: {$proj_id})";
        }

        return $results;
    }

    // ─────────────────────────────────────────────────────────────────────
    // CONTACTS (res.partner)
    // ─────────────────────────────────────────────────────────────────────

    public static function create_or_update_contact( $user_id ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) return false;

        global $wpdb;
        $co = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d", $user_id
        ) );

        $name = trim( ($user->first_name ?? '') . ' ' . ($user->last_name ?? '') )
                ?: $user->display_name
                ?: $user->user_email;

        $data = array(
            'name'          => $name,
            'email'         => $user->user_email,
            'phone'         => get_user_meta($user_id,'billing_phone',true) ?: '',
            'company_name'  => $co->business_name ?? '',
            'website'       => $co->website ?? '',
            'customer_rank' => 1,
            'comment'       => 'WP User ID: ' . $user_id . ' — 6ix Developers Portal',
        );

        // Find by email
        $ex = self::execute( 'res.partner', 'search_read',
            array( array( array('email','=',$user->user_email) ) ),
            array( 'fields'=>array('id'), 'limit'=>1 )
        );

        if ( ! empty($ex[0]['id']) ) {
            $pid = $ex[0]['id'];
            self::execute( 'res.partner', 'write', array( array($pid), $data ) );
        } else {
            $pid = self::execute( 'res.partner', 'create', array($data) );
        }

        if ( $pid ) update_user_meta( $user_id, 'six_odoo_partner_id', $pid );
        return $pid ?: false;
    }

    public static function sync_client( $user_id ) {
        return self::create_or_update_contact( $user_id );
    }

    // ─────────────────────────────────────────────────────────────────────
    // CRM LEADS
    // ─────────────────────────────────────────────────────────────────────

    public static function sync_lead( $data = array() ) {
        $user_id = intval( $data['user_id'] ?? 0 );
        if ( ! $user_id ) return false;
        $user = get_userdata( $user_id );
        if ( ! $user ) return false;

        global $wpdb;
        $co = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d", $user_id
        ) );

        $status = $data['status'] ?? 'new';
        $score  = intval( $data['score'] ?? $co->score ?? 0 );
        $step   = intval( $data['step']  ?? $co->step  ?? 0 );

        $stage_map = array(
            'new'         => intval( get_option('six_odoo_stage_new',1) ),
            'in_progress' => intval( get_option('six_odoo_stage_inprogress',2) ),
            'abandoned'   => intval( get_option('six_odoo_stage_inprogress',2) ),
            'submitted'   => intval( get_option('six_odoo_stage_submitted',3) ),
            'active'      => intval( get_option('six_odoo_stage_active',4) ),
        );
        $prob_map = array('new'=>10,'in_progress'=>30,'abandoned'=>20,'submitted'=>70,'active'=>100);

        $partner_id = intval( get_user_meta($user_id,'six_odoo_partner_id',true) );
        if ( ! $partner_id ) $partner_id = intval( self::create_or_update_contact($user_id) ?: 0 );

        $svcs = $data['services'] ?? '';
        if ( ! $svcs ) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT service_name FROM {$wpdb->prefix}six_client_services WHERE client_id=%d",$user_id));
            $svcs = implode(', ', array_column((array)$rows,'service_name'));
        }

        $display = trim(($user->first_name??'').' '.($user->last_name??'')) ?: $user->display_name ?: $user->user_email;

        // Base fields (always present in Odoo)
        $ld = array(
            'name'         => $display . ' — Onboarding',
            'email_from'   => $user->user_email,
            'partner_name' => $co->business_name ?? sanitize_text_field($data['business_name'] ?? ''),
            'phone'        => get_user_meta($user_id,'billing_phone',true) ?: '',
            'website'      => $co->website ?? sanitize_text_field($data['website'] ?? ''),
            'description'  => self::build_desc($user_id,$data,$co,$svcs),
            'stage_id'     => $stage_map[$status],
            'probability'  => $prob_map[$status] ?? 10,
        );
        if ( $partner_id ) $ld['partner_id'] = $partner_id;

        // Custom fields (only if setup has run)
        if ( get_option('six_odoo_custom_fields_ready') ) {
            $ld['x_wp_user_id']         = $user_id;
            $ld['x_checkout_score']     = $score;
            $ld['x_checkout_step']      = $step;
            $ld['x_onboarding_status']  = $status;
            $ld['x_services_selected']  = $svcs;
            $ld['x_marketing_goal']     = $co->goal ?? sanitize_text_field($data['goal'] ?? '');
            $ld['x_marketing_challenge']= $co->challenge ?? sanitize_text_field($data['challenge'] ?? '');
            $ld['x_monthly_budget']     = $co->mktg_budget ?? sanitize_text_field($data['mktg_budget'] ?? '');
        }

        // Tag
        $tag_name = $score>=70 ? 'Hot Lead' : ($score>=40 ? 'Warm Lead' : 'Cold Lead');
        $tag_id   = self::get_or_create_tag($tag_name);
        if ($tag_id) $ld['tag_ids'] = array( array(6,0,array($tag_id)) );

        // Find existing lead
        $odoo_id = intval(get_user_meta($user_id,'six_odoo_lead_id',true));
        if (!$odoo_id && $co) $odoo_id = intval($co->odoo_lead_id ?? 0);

        if ( $odoo_id ) {
            $ok = self::execute('crm.lead','write',array(array($odoo_id),$ld));
            return $ok !== false ? $odoo_id : false;
        } else {
            $odoo_id = self::execute('crm.lead','create',array($ld));
            if ( is_int($odoo_id) && $odoo_id > 0 ) {
                update_user_meta($user_id,'six_odoo_lead_id',$odoo_id);
                if ($co) $wpdb->update("{$wpdb->prefix}six_checkout_progress",
                    array('odoo_lead_id'=>$odoo_id),array('user_id'=>$user_id));
            }
            return ( is_int($odoo_id) && $odoo_id > 0 ) ? $odoo_id : false;
        }
    }

    private static function build_desc($user_id,$data,$co,$svcs) {
        $lines = array('Source: 6ix Developers Portal','');
        $map = array('business_name'=>'Business','industry'=>'Industry','location'=>'Location',
            'employees'=>'Employees','monthly_revenue'=>'Revenue','goal'=>'Goal',
            'challenge'=>'Challenge','mktg_budget'=>'Budget','website'=>'Website');
        if ($co) foreach ($map as $k=>$l) if (!empty($co->$k)) $lines[]=$l.': '.$co->$k;
        if ($svcs)              $lines[] = 'Services: '.$svcs;
        if (isset($data['score'])) $lines[] = 'Score: '.$data['score'].'/100';
        if (isset($data['step']))  $lines[] = 'Step: '.$data['step'];
        return implode("\n",$lines);
    }

    // ─────────────────────────────────────────────────────────────────────
    // TASKS
    // ─────────────────────────────────────────────────────────────────────

    public static function create_task( $data = array() ) {
        $td = array(
            'name'        => $data['name'] ?? ($data['title'] ?? 'Task'),
            'description' => $data['description'] ?? '',
            'project_id'  => intval( get_option('six_odoo_project_id',1) ),
        );
        return self::execute('project.task','create',array($td));
    }

    public static function create_abandoned_task( $user_id, $step, $score ) {
        $user  = get_userdata($user_id);
        $email = $user ? $user->user_email : 'Unknown';
        $name  = $user ? (trim(($user->first_name??'').' '.($user->last_name??''))?:$user->display_name?:$email) : $email;
        $type  = $score>=70?'Hot Lead 🔴':($score>=40?'Warm Lead 🟡':'Cold Lead 🔵');
        global $wpdb;
        $co = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d",$user_id));
        $desc = "Customer: {$name}\nEmail: {$email}\nScore: {$score}/100\nAbandoned at Step: {$step}/4\nClassification: {$type}\n";
        if ($co && $co->business_name) $desc .= "Business: {$co->business_name}\n";
        if ($co && $co->goal)          $desc .= "Goal: {$co->goal}\n";
        $desc .= "\nAction: Follow up to complete onboarding.\n".admin_url('user-edit.php?user_id='.$user_id);
        self::sync_lead(array('user_id'=>$user_id,'status'=>'abandoned','score'=>$score,'step'=>$step));
        return self::create_task(array('name'=>'Abandoned Onboarding — '.$name,'description'=>$desc));
    }

    // Backwards compat alias
    public static function create_onboarding_task($user_id,$type='new_client',$extra=array()) {
        if ($type==='abandoned')
            return self::create_abandoned_task($user_id,$extra['step']??0,$extra['score']??0);
        $user = get_userdata($user_id);
        return self::create_task(array(
            'name'=>'New Client: '.($user?$user->display_name:'Unknown'),
            'description'=>'Email: '.($user?$user->user_email:'')
        ));
    }

    // ─────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────

    private static function get_or_create_tag($name) {
        $ck = 'six_odoo_tag_'.md5($name);
        $cached = get_option($ck);
        if ($cached) return intval($cached);
        $ex = self::execute('crm.tag','search_read',
            array(array(array('name','=',$name))),array('fields'=>array('id'),'limit'=>1));
        if (!empty($ex[0]['id'])) { update_option($ck,$ex[0]['id']); return $ex[0]['id']; }
        $id = self::execute('crm.tag','create',array(array('name'=>$name)));
        if ($id) update_option($ck,$id);
        return $id ?: false;
    }

    public static function get_leads() {
        return self::execute('crm.lead','search_read',
            array(array(array('x_wp_user_id','!=',false))),
            array('fields'=>array('name','email_from','stage_id','x_wp_user_id',
                'x_checkout_score','x_onboarding_status'),'limit'=>100)) ?: array();
    }
}
endif;

// ─────────────────────────────────────────────────────────────────────────────
// ADMIN: Run setup via /wp-admin/?six_odoo_setup=1
// ─────────────────────────────────────────────────────────────────────────────
add_action('admin_init','six_odoo_maybe_run_setup');
function six_odoo_maybe_run_setup() {
    if ( ! current_user_can('manage_options') || empty($_GET['six_odoo_setup']) ) return;
    echo '<div style="font-family:monospace;padding:30px;background:#0d1117;color:#f0f4f8;min-height:100vh">';
    echo '<h2 style="color:#FF6699;margin-bottom:20px">6ix Odoo Setup — Odoo 18 SaaS (XML-RPC)</h2>';
    if ( ! class_exists('Six_Odoo') ) { echo '<p style="color:red">Six_Odoo class not found.</p></div>'; return; }

    // Show current credentials (masked)
    $url = get_option('six_odoo_url','');
    $db  = get_option('six_odoo_db','');
    $usr = get_option('six_odoo_username','');
    $key = get_option('six_odoo_api_key') ? '✓ set ('.strlen(get_option('six_odoo_api_key')).' chars)' : '❌ not set';
    echo "<table style='font-size:13px;margin-bottom:16px'>";
    foreach (array('URL'=>$url,'DB'=>$db,'Username'=>$usr,'API Key'=>$key) as $k=>$v)
        echo "<tr><td style='padding:3px 16px 3px 0;color:#83C5ED'>{$k}:</td><td>".esc_html($v)."</td></tr>";
    echo "</table>";

    if (!$url || !$db || !$usr || !get_option('six_odoo_api_key')) {
        echo '<p style="color:#FF6B6B">❌ Missing credentials. <a href="'.admin_url('admin.php?page=six-portal-settings').'" style="color:#83C5ED">Go to Integrations →</a></p></div>'; return;
    }

    echo '<p>Testing XML-RPC connection to <code>'.esc_html($url).'/xmlrpc/2/common</code>…</p>';
    Six_Odoo::$uid_cache = null;
    $ok = Six_Odoo::test_connection();

    if (!$ok) {
        echo '<p style="color:#FF6B6B">❌ Authentication failed.</p>';
        echo '<div style="background:#1a1a2e;border:1px solid #FF6B6B;border-radius:8px;padding:16px;margin:10px 0;line-height:2;font-size:13px">';
        echo '<strong>On Odoo SaaS — you must:</strong><ol style="margin:8px 0 0 18px;color:#ccc">';
        echo '<li>Go to Odoo → click your <strong>name</strong> (top right) → <strong>Preferences</strong></li>';
        echo '<li>Click <strong>Account Security</strong> tab</li>';
        echo '<li>Under <strong>API Keys</strong>, click <strong>New Key</strong></li>';
        echo '<li>Give it a name → copy the key (shown only once)</li>';
        echo '<li>Paste it in WP Admin → 6ix Portal → Integrations → <strong>API Key</strong></li>';
        echo '<li>Also set the <strong>Database Name</strong> — on Odoo.com this is your subdomain (e.g. <code>acme</code> for acme.odoo.com)</li>';
        echo '</ol></div>';
        echo '<p>Check <code>wp-content/debug.log</code> for the raw Odoo response.</p>';
        echo '<p><a href="'.admin_url('admin.php?page=six-portal-settings').'" class="button">← Back to Settings</a></p>';
        echo '</div>'; return;
    }

    echo '<p style="color:#56D364">✅ Connected! UID: '.intval(Six_Odoo::$uid_cache).'</p>';
    echo '<hr style="border-color:#333;margin:16px 0"><p>Creating fields and stages…</p><ul style="line-height:2">';
    $results = Six_Odoo::setup();
    foreach ($results as $r) echo '<li>'.$r.'</li>';
    echo '</ul>';
    echo '<hr style="border-color:#333;margin:16px 0">';
    echo '<p style="color:#56D364;font-size:15px">✅ Setup complete! Stage IDs saved to WP options automatically.</p>';
    echo '<p><a href="'.admin_url('admin.php?page=six-portal-settings').'" class="button">View Integration Settings →</a></p>';
    echo '</div>'; exit;
}

add_action('wp_ajax_six_test_odoo','six_ajax_test_odoo');
function six_ajax_test_odoo() {
    if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
    Six_Odoo::$uid_cache = null;
    $ok = class_exists('Six_Odoo') && Six_Odoo::test_connection();
    $ok ? wp_send_json_success('Connected ✓ UID: '.intval(Six_Odoo::$uid_cache))
        : wp_send_json_error('Connection failed — check URL, database, username, and API key in Integrations');
}
