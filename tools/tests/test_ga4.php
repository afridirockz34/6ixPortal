<?php
/**
 * The GA4 path, exercised against a stubbed Google.
 *
 * The questions: does a deprecated metric name still produce a report, does a
 * permission error produce an instruction instead of a shrug, and does a
 * retry happen only when retrying could help.
 */
define('ABSPATH','/x/');
$GLOBALS['OPTS'] = array();
$GLOBALS['CALLS'] = array();
$GLOBALS['RESPONSES'] = array();

function get_option($k,$d=''){ return $GLOBALS['OPTS'][$k] ?? $d; }
function get_transient($k){ return $k === 'six_ga_sa_token' ? 'fake-token' : false; }
function set_transient($k,$v,$t){ return true; }
function wp_json_encode($d){ return json_encode($d); }
function is_wp_error($t){ return $t instanceof WP_Error; }
function wp_remote_retrieve_body($r){ return $r['body'] ?? ''; }
function wp_remote_retrieve_response_code($r){ return $r['code'] ?? 200; }
class WP_Error { public $m; function __construct($m){$this->m=$m;} function get_error_message(){return $this->m;} }
function wp_remote_post($url,$args=array()){
    $body = json_decode($args['body'] ?? '{}', true);
    $metric = $body['metrics'][3]['name'] ?? '';
    $GLOBALS['CALLS'][] = $metric;
    $next = array_shift($GLOBALS['RESPONSES']);
    return is_callable($next) ? $next($metric) : $next;
}
require dirname(__DIR__, 2) . '/portal/class-analytics.php';

$pass=0; $fail=0;
function check($name,$ok,$detail=''){
    global $pass,$fail;
    if($ok){$pass++; echo "PASS  $name\n";}
    else{$fail++; echo "FAIL  $name\n" . ($detail?"        $detail\n":"");}
}

$SA = json_encode(array('type'=>'service_account','client_email'=>'six-portal@proj.iam.gserviceaccount.com','private_key'=>'x'));
$GLOBALS['OPTS']['six_ga4_service_account_json'] = $SA;

// ── The email, which is the whole point of the setup step ────────────────────
check('service-account email is read from the JSON',
      Six_Analytics::service_account_email() === 'six-portal@proj.iam.gserviceaccount.com');
$GLOBALS['OPTS']['six_ga4_service_account_json'] = '{not json';
check('unparseable JSON yields no email rather than a warning',
      Six_Analytics::service_account_email() === '');
$GLOBALS['OPTS']['six_ga4_service_account_json'] = $SA;

// Stub the token so we exercise the report, not the JWT.
set_transient('x',1,1);
$ref = new ReflectionClass('Six_Analytics');

// ── 1. Happy path on the current metric name ────────────────────────────────
$ok = array('code'=>200,'body'=>json_encode(array('rows'=>array(
    array('dimensionValues'=>array(array('value'=>'Organic Search')),
          'metricValues'=>array(array('value'=>'412'),array('value'=>'330'),array('value'=>'980'),array('value'=>'7'))),
    array('dimensionValues'=>array(array('value'=>'Direct')),
          'metricValues'=>array(array('value'=>'88'),array('value'=>'71'),array('value'=>'150'),array('value'=>'1'))),
))));
$GLOBALS['CALLS']=array(); $GLOBALS['RESPONSES']=array($ok);
$r = Six_Analytics::ga4_summary('312345678', 30);
check('asks Google for keyEvents first', $GLOBALS['CALLS'] === array('keyEvents'),
      'asked: '.implode(',', $GLOBALS['CALLS']));
check('one call when the first succeeds', count($GLOBALS['CALLS']) === 1);
check('totals are summed across channels', ($r['totals']['sessions'] ?? 0) === 500,
      'sessions='.($r['totals']['sessions'] ?? 'missing'));
check('users summed', ($r['totals']['users'] ?? 0) === 401);
check('channels kept', count($r['by_channel'] ?? array()) === 2);

// ── 2. A property that rejects keyEvents still reports ──────────────────────
$metricErr = array('code'=>400,'body'=>json_encode(array('error'=>array(
    'message'=>'Field keyEvents is not a valid metric.'))));
$GLOBALS['CALLS']=array(); $GLOBALS['RESPONSES']=array($metricErr, $ok);
$r = Six_Analytics::ga4_summary('312345678', 30);
check('falls back to conversions when keyEvents is rejected',
      $GLOBALS['CALLS'] === array('keyEvents','conversions'),
      'asked: '.implode(',', $GLOBALS['CALLS']));
check('and still returns the report', ($r['totals']['sessions'] ?? 0) === 500 && !isset($r['error']),
      json_encode($r['error'] ?? $r['totals']));

// ── 3. A permission error is explained, and NOT retried ─────────────────────
$permErr = array('code'=>403,'body'=>json_encode(array('error'=>array(
    'message'=>'User does not have sufficient permissions for this property.'))));
$GLOBALS['CALLS']=array(); $GLOBALS['RESPONSES']=array($permErr, $ok);
$r = Six_Analytics::ga4_summary('312345678', 30);
check('a permission error is not retried', count($GLOBALS['CALLS']) === 1,
      'asked: '.implode(',', $GLOBALS['CALLS']));
check('the error names the property', strpos($r['error'],'312345678') !== false, $r['error']);
check('the error names the email to grant',
      strpos($r['error'],'six-portal@proj.iam.gserviceaccount.com') !== false, $r['error']);
check('the error says where to do it',
      stripos($r['error'],'Property access management') !== false, $r['error']);

// ── 4. A wrong-shaped property ID is explained as such ──────────────────────
$nf = array('code'=>404,'body'=>json_encode(array('error'=>array(
    'message'=>'Property not found.'))));
$GLOBALS['CALLS']=array(); $GLOBALS['RESPONSES']=array($nf);
$r = Six_Analytics::ga4_summary('312345678', 30);
check('a not-found property explains the G- confusion',
      strpos($r['error'],'G-') !== false, $r['error']);

// ── 5. A measurement ID is rejected before any network call ─────────────────
$GLOBALS['CALLS']=array(); $GLOBALS['RESPONSES']=array($ok);
$r = Six_Analytics::ga4_summary('G-ABCDEF1234', 30);
check('a G- measurement ID does not silently become a number',
      isset($r['error']) || $GLOBALS['CALLS'] === array(),
      'digits were stripped and it called anyway: '.json_encode($r));

// ── 6. No property at all ───────────────────────────────────────────────────
$r = Six_Analytics::ga4_summary('', 30);
check('an empty property is an error, not an empty report', isset($r['error']));

// ── 7. Every shape of wrong ID is refused by name, without a network call ───
foreach (array(
    'G-ABCDEF1234'      => 'measurement ID',
    'g-abcdef1234'      => 'measurement ID, lowercase',
    'UA-12345-1'        => 'Universal Analytics ID',
    'GTM-ABC123'        => 'Tag Manager ID',
    'properties/312345' => 'the API resource path',
    '312 345 678'       => 'a number with spaces',
) as $bad => $desc) {
    $GLOBALS['CALLS']=array(); $GLOBALS['RESPONSES']=array($ok);
    $r = Six_Analytics::ga4_summary($bad, 30);
    check("refuses $desc without querying anything",
          isset($r['error']) && $GLOBALS['CALLS'] === array(),
          'got: '.json_encode($r));
}
$GLOBALS['CALLS']=array(); $GLOBALS['RESPONSES']=array($ok);
$r = Six_Analytics::ga4_summary('  312345678  ', 30);
check('a plain number with stray whitespace still works',
      !isset($r['error']) && $GLOBALS['CALLS'] === array('keyEvents'),
      'got: '.json_encode($r['error'] ?? $GLOBALS['CALLS']));

// ── 8. Search Console: the three 403s are three different problems ──────────
// wp_remote_get is only used by gsc_sites, so stub it separately.
$GLOBALS['GET_RESPONSES'] = array();
function wp_remote_get($url, $args = array()) {
    $GLOBALS['CALLS'][] = 'GET ' . $url;
    return array_shift($GLOBALS['GET_RESPONSES']);
}

function sites_resp($entries) {
    return array('code'=>200,'body'=>json_encode(array('siteEntry'=>$entries)));
}
$MUDDY = array(
    array('siteUrl'=>'https://muddyyorkfishing.ca/','permissionLevel'=>'siteFullUser'),
    array('siteUrl'=>'sc-domain:example.org','permissionLevel'=>'siteOwner'),
);

// API not enabled on the Cloud project — the one that looks like a permission
// problem but can never be fixed in Search Console.
$GLOBALS['GET_RESPONSES'] = array(array('code'=>403,'body'=>json_encode(array('error'=>array(
    'status'=>'PERMISSION_DENIED',
    'message'=>'Google Search Console API has not been used in project 12345 before or it is disabled.',
    'errors'=>array(array('reason'=>'accessNotConfigured')),
)))));
$r = Six_Analytics::gsc_sites(true);
check('a disabled API is named as a disabled API',
      isset($r['error']) && stripos($r['error'],'not enabled on the Google Cloud project') !== false, $r['error'] ?? '');
check('and says it is a different API from GA4',
      stripos($r['error'],'Analytics Data API is enabled') !== false, $r['error']);
check('and says adding users will not fix it',
      stripos($r['error'],'will not fix this') !== false, $r['error']);

// A genuine permission problem keeps the old advice, plus Google's words.
$GLOBALS['GET_RESPONSES'] = array(array('code'=>403,'body'=>json_encode(array('error'=>array(
    'status'=>'PERMISSION_DENIED','message'=>'User does not have sufficient permission for site.')))));
$r = Six_Analytics::gsc_sites(true);
check('a real permission error still points at Users and permissions',
      stripos($r['error'],'Users and permissions') !== false, $r['error']);
check('and quotes what Google actually said',
      stripos($r['error'],'sufficient permission') !== false, $r['error']);

// ── 9. Identifier resolution against the real list ──────────────────────────
$GLOBALS['GET_RESPONSES'] = array(sites_resp($MUDDY));
$r = Six_Analytics::resolve_gsc_site('muddyyorkfishing.ca');
check('a bare domain resolves to the URL-prefix property Google really has',
      ($r['site'] ?? '') === 'https://muddyyorkfishing.ca/', json_encode($r));
check('and says it substituted one for the other', !empty($r['note']), json_encode($r));

$GLOBALS['GET_RESPONSES'] = array(sites_resp($MUDDY));
$r = Six_Analytics::resolve_gsc_site('http://muddyyorkfishing.ca/fishing');
check('a URL with a path still resolves to the property that covers it',
      ($r['site'] ?? '') === 'https://muddyyorkfishing.ca/', json_encode($r));

$GLOBALS['GET_RESPONSES'] = array(sites_resp($MUDDY));
$r = Six_Analytics::resolve_gsc_site('https://muddyyorkfishing.ca/');
check('an exact match is used as-is with no note',
      ($r['site'] ?? '') === 'https://muddyyorkfishing.ca/' && empty($r['note']), json_encode($r));

$GLOBALS['GET_RESPONSES'] = array(sites_resp($MUDDY));
$r = Six_Analytics::resolve_gsc_site('www.MuddyYorkFishing.CA');
check('case and www do not defeat the match',
      ($r['site'] ?? '') === 'https://muddyyorkfishing.ca/', json_encode($r));

// A domain property should win over a URL-prefix one for the same host.
$GLOBALS['GET_RESPONSES'] = array(sites_resp(array(
    array('siteUrl'=>'https://shop.example.ca/deep/path/'),
    array('siteUrl'=>'sc-domain:example.ca'),
    array('siteUrl'=>'http://example.ca/'),
)));
$r = Six_Analytics::resolve_gsc_site('example.ca');
check('a domain property is preferred over a URL-prefix one',
      ($r['site'] ?? '') === 'sc-domain:example.ca', json_encode($r));

// Nothing matches: say what IS available rather than just refusing.
$GLOBALS['GET_RESPONSES'] = array(sites_resp($MUDDY));
$r = Six_Analytics::resolve_gsc_site('someoneelse.com');
check('an unmatched domain lists what the account can see',
      isset($r['error']) && strpos($r['error'],'https://muddyyorkfishing.ca/') !== false, $r['error'] ?? '');
check('and warns the identifier is literal',
      stripos($r['error'],'different properties') !== false, $r['error']);

// Account sees nothing at all.
$GLOBALS['GET_RESPONSES'] = array(sites_resp(array()));
$r = Six_Analytics::resolve_gsc_site('muddyyorkfishing.ca');
check('an empty property list is called out as such',
      isset($r['error']) && stripos($r['error'],'no Search Console properties at all') !== false, $r['error'] ?? '');
check('and suggests the wrong account or a pending invitation',
      stripos($r['error'],'pending') !== false, $r['error']);

// If listing fails we still attempt the query rather than giving up.
$GLOBALS['GET_RESPONSES'] = array(array('code'=>500,'body'=>json_encode(array('error'=>array('message'=>'backend error')))));
$r = Six_Analytics::resolve_gsc_site('muddyyorkfishing.ca');
check('an unlistable account still yields something to try',
      ($r['site'] ?? '') === 'sc-domain:muddyyorkfishing.ca' && ($r['verified'] ?? true) === false,
      json_encode($r));

echo "\nPassed: $pass  Failed: $fail\n";
exit($fail ? 1 : 0);
