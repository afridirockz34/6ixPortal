<?php
/**
 * Customer Dashboard v5 — Full Redesign
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$user_id    = get_current_user_id();
$user       = wp_get_current_user();
$initials   = six_get_initials( $user->display_name );
$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'overview';
// Messaging now lives inside the Advisor section — route any old links there.
if ( $active_tab === 'messages' ) $active_tab = 'advisor';
$nonce      = wp_create_nonce( 'six_nonce' );
$ajax_url   = admin_url( 'admin-ajax.php' );

global $wpdb;

$advisor_id  = $wpdb->get_var( $wpdb->prepare( "SELECT advisor_id FROM {$wpdb->prefix}six_assignments WHERE client_id=%d", $user_id ) );
$advisor     = $advisor_id ? get_userdata( $advisor_id ) : null;
$services    = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}six_client_services WHERE client_id=%d ORDER BY status DESC", $user_id ) );
$metrics     = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}six_metrics WHERE client_id=%d ORDER BY service_slug,label", $user_id ) );
$recs        = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}six_recommendations WHERE client_id=%d AND status='active' ORDER BY created_at DESC LIMIT 10", $user_id ) );
$reports     = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}six_reports WHERE client_id=%d ORDER BY created_at DESC", $user_id ) );
$checkout    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d", $user_id ) );

// Backfill WP user meta into empty checkout rows (handles pre-fix completions)
if ( $checkout && empty($checkout->first_name) ) {
    $um_first = get_user_meta($user_id,'first_name',true) ?: '';
    $um_last  = get_user_meta($user_id,'last_name',true)  ?: '';
    $um_phone = get_user_meta($user_id,'billing_phone',true) ?: '';
    if ($um_first || $um_last) {
        $wpdb->update(
            $wpdb->prefix.'six_checkout_progress',
            array_filter(array('first_name'=>$um_first,'last_name'=>$um_last,'phone'=>$um_phone)),
            array('user_id'=>$user_id)
        );
        $checkout->first_name = $um_first;
        $checkout->last_name  = $um_last;
        if ($um_phone) $checkout->phone = $um_phone;
    }
}

// Safe defaults if no onboarding row exists yet
if ( ! $checkout ) {
    $checkout = (object) array(
        'business_name'  => get_user_meta($user_id,'billing_company',true) ?: '',
        'website'        => '',
        'industry'       => '',
        'location'       => get_user_meta($user_id,'billing_city',true) ?: '',
        'employees'      => '',
        'monthly_revenue'=> '',
        'goal'           => '',
        'challenge'      => '',
        'mktg_budget'    => 0,
        'competitors'    => '',
        'platforms'      => '',
        'ads_keywords'   => '',
        'ads_usp'        => '',
        'seo_keywords'   => '',
        'seo_pages'      => '',
        'web_goal'       => '',
        'years_in_business'=> '',
        'business_address' => '',
        'ai_plan_json'   => '',
    );
}
// New v5 questionnaire fields
$q_ads_keywords = $checkout->ads_keywords ?? '';
$q_ads_usp      = $checkout->ads_usp      ?? '';
$q_seo_keywords = $checkout->seo_keywords ?? '';
$q_seo_pages    = $checkout->seo_pages    ?? '';
$q_web_goal     = $checkout->web_goal     ?? '';
$q_years        = $checkout->years_in_business ?? '';
$q_address      = $checkout->business_address  ?? '';
$ai_plan_json   = $checkout->ai_plan_json ?? '';
$all_notifs  = class_exists('Six_Notifications') ? Six_Notifications::get_for_user( $user_id, 50 ) : array();
$unread_msgs = class_exists('Six_Messaging')     ? Six_Messaging::get_unread_count( $user_id )    : 0;
$unread_n    = class_exists('Six_Notifications') ? Six_Notifications::get_unread_count( $user_id ) : 0;

$active_svcs      = array_values( array_filter( (array)$services, fn($s) => $s->status === 'active' ) );
$pending_svcs     = array_values( array_filter( (array)$services, fn($s) => $s->status === 'pending' ) );
$active_svc_slugs = array_column( $active_svcs, 'service_slug' );
$total_budget     = array_sum( array_column( $active_svcs, 'budget' ) );
$ai_business      = $checkout->business_name ?? $user->display_name;
$ai_industry      = $checkout->industry ?? '';
$ai_goal          = $checkout->goal ?? '';
$ai_challenge     = $checkout->challenge ?? '';
$ai_svc_names     = implode(', ', array_column( $active_svcs, 'service_name' ) );
$ai_metrics_summary = '';
foreach ( $metrics as $m ) $ai_metrics_summary .= $m->label . ': ' . $m->current_value . '; ';
$missing_svcs = implode(', ', array_filter(
    array('Google Ads','SEO','Google Business Profile','Website Development'),
    fn($s) => stripos($ai_svc_names, $s) === false
));
$website_url = $checkout->website ?? '';
$comp_list   = array_filter( array_map('trim', explode(',', $checkout->competitors ?? '')) );
$comp_str    = !empty($comp_list) ? implode(', ', $comp_list) : '';

// Marketing Maturity Score
$maturity_items = array(
    array('label'=>'Business Profile',    'weight'=>15, 'earned'=>($checkout&&$checkout->business_name&&$checkout->industry)?15:($checkout&&$checkout->business_name?8:0)),
    array('label'=>'Active Services',     'weight'=>25, 'earned'=>min(25,count($active_svcs)*8)),
    array('label'=>'Competitor Tracking', 'weight'=>10, 'earned'=>!empty($comp_list)?10:0),
    array('label'=>'Campaign Metrics',    'weight'=>20, 'earned'=>min(20,count($metrics)*4)),
    array('label'=>'Marketing Budget',    'weight'=>15, 'earned'=>$total_budget>0?15:0),
    array('label'=>'Advisor Engagement',  'weight'=>15, 'earned'=>$advisor_id?15:0),
);
$maturity_score = array_sum( array_column($maturity_items,'earned') );
$maturity_grade = $maturity_score>=80?'Elite':($maturity_score>=65?'Advanced':($maturity_score>=45?'Growing':'Emerging'));
$maturity_color = $maturity_score>=80?'var(--success)':($maturity_score>=65?'var(--cyan)':($maturity_score>=45?'var(--warning)':'var(--pink)'));

$svc_def = array(
    'google-ads'      => array('name'=>'Google Ads',              'icon'=>'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="3"/><path d="M7 15V9m5 6V7m5 8V5" stroke-width="2"/></svg>', 'color'=>'#4285F4'),
    'seo'             => array('name'=>'SEO',                     'icon'=>'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>', 'color'=>'#56D364'),
    'google-business' => array('name'=>'Google Business Profile', 'icon'=>'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>', 'color'=>'#FBBC05'),
    'website'         => array('name'=>'Website Development',     'icon'=>'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>', 'color'=>'#a855f7'),
);
?>
<!-- Notifications panel -->
<div id="six-notif-panel" style="display:none;position:fixed;top:52px;right:16px;width:360px;max-height:480px;background:var(--dark2);border:1px solid var(--border);border-radius:12px;z-index:9999;box-shadow:0 8px 32px rgba(0,0,0,0.4);overflow:hidden">
    <div style="padding:14px 16px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
        <span style="font-size:13px;font-weight:700">Notifications</span>
        <div style="display:flex;gap:8px">
            <button id="six-mark-all-read-btn" style="font-size:11px;background:none;border:none;color:var(--cyan);cursor:pointer;padding:0">Mark all read</button>
            <button onclick="document.getElementById('six-notif-panel').style.display='none'" style="background:none;border:none;color:var(--text3);cursor:pointer;font-size:16px;padding:0;line-height:1">×</button>
        </div>
    </div>
    <div style="overflow-y:auto;max-height:420px" id="six-notif-list">
        <?php if(empty($all_notifs)): ?>
        <div style="padding:32px;text-align:center;color:var(--text3);font-size:13px">You're all caught up ✓</div>
        <?php else: foreach($all_notifs as $n): ?>
        <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;gap:10px;<?php echo !$n->is_read?'background:rgba(255,102,153,0.04)':''; ?>">
            <div style="width:8px;height:8px;border-radius:50%;margin-top:5px;flex-shrink:0;background:<?php echo !$n->is_read?'var(--pink)':'var(--dark4)'; ?>"></div>
            <div style="flex:1">
                <div style="font-size:13px;font-weight:600;margin-bottom:2px"><?php echo esc_html($n->title); ?></div>
                <div style="font-size:12px;color:var(--text2);line-height:1.5;margin-bottom:4px"><?php echo esc_html($n->message); ?></div>
                <div style="font-size:11px;color:var(--text3)"><?php echo human_time_diff(strtotime($n->created_at),time()); ?> ago</div>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<!-- Topbar -->
<div class="six-topbar">
    <!-- Hamburger — always first on mobile -->
    <button class="six-mobile-menu-btn" id="six-menu-toggle" aria-label="Open menu">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
        </svg>
    </button>
    <!-- Desktop sidebar collapse toggle -->
    <button class="six-sidebar-collapse-btn" id="six-sidebar-collapse" aria-label="Toggle sidebar" title="Toggle sidebar">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
        </svg>
    </button>
    <!-- Logo — links to the public website -->
    <a class="six-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">6ix Developers</a>
    <!-- Right controls -->
    <div class="six-topbar-right">
        <!-- Theme toggle — icon only on mobile -->
        <button class="six-theme-toggle" id="six-theme-btn" title="Toggle light/dark">
            <div class="toggle-track"><div class="toggle-thumb"></div></div>
            <span id="six-theme-label">Dark</span>
        </button>
        <!-- Notification bell -->
        <button id="six-notif-btn" style="position:relative;background:none;border:none;cursor:pointer;padding:5px;color:var(--text1);display:flex;align-items:center">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/>
            </svg>
            <?php if($unread_n>0): ?><span class="six-badge" style="position:absolute;top:-2px;right:-2px;min-width:16px;height:16px;padding:0 3px;font-size:9px"><?php echo min($unread_n,9); ?></span><?php endif; ?>
        </button>
        <!-- Avatar — initials only, no name -->
        <a href="?tab=profile" style="text-decoration:none">
            <div class="six-avatar" title="<?php echo esc_attr($user->display_name); ?>"><?php echo esc_html($initials); ?></div>
        </a>
    </div>
</div>

<div id="six-overlay" class="six-overlay"></div>

<!-- ── BOTTOM NAVIGATION (mobile only) ──────────────────────────── -->
<nav class="six-bottom-nav" id="six-bottom-nav">
  <a class="six-bnav-item <?php echo $active_tab==='overview'?'active':''; ?>" href="?tab=overview">
    <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
    <span>Overview</span>
  </a>
  <a class="six-bnav-item <?php echo $active_tab==='services'?'active':''; ?>" href="?tab=services">
    <svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/></svg>
    <span>Services</span>
  </a>
  <a class="six-bnav-item <?php echo $active_tab==='advisor'?'active':''; ?>" href="?tab=advisor">
    <?php if($unread_msgs > 0): ?><span class="six-bnav-badge"><?php echo min($unread_msgs,9); ?></span><?php endif; ?>
    <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    <span>Advisor</span>
  </a>
  <a class="six-bnav-item <?php echo $active_tab==='reports'?'active':''; ?>" href="?tab=reports">
    <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
    <span>Reports</span>
  </a>
  <a class="six-bnav-item <?php echo $active_tab==='profile'?'active':''; ?>" href="?tab=profile">
    <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    <span>Profile</span>
  </a>
</nav>

<div class="six-layout">

<!-- Sidebar -->
<nav class="six-sidebar">
    <div class="six-nav-section">
        <div class="six-nav-label">Dashboard</div>
        <a href="?tab=overview"     class="six-nav-item <?php echo $active_tab==='overview'    ?'active':''; ?>"><span class="six-nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" ><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg></span> Overview</a>
        <a href="?tab=intelligence" class="six-nav-item <?php echo $active_tab==='intelligence'?'active':''; ?>"><span class="six-nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" ><path d="M12 2a7 7 0 0 1 7 7c0 3.87-3.13 7-7 7s-7-3.13-7-7a7 7 0 0 1 7-7z"/><path d="M9 9h6m-6 3h4"/><line x1="12" y1="16" x2="12" y2="21"/><line x1="8" y1="21" x2="16" y2="21"/></svg></span> AI Insights</a>
        <a href="?tab=competitor"   class="six-nav-item <?php echo $active_tab==='competitor'  ?'active':''; ?>"><span class="six-nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" ><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span> Competitors</a>
    </div>
    <div class="six-nav-section">
        <div class="six-nav-label">Services</div>
        <a href="?tab=services" class="six-nav-item <?php echo $active_tab==='services'?'active':''; ?>"><span class="six-nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" ><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg></span> Services</a>
        <?php
$svc_svg_icons = array(
    'google-ads' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" ><rect x="2" y="2" width="20" height="20" rx="3"/><path d="M7 15V9m5 6V7m5 8V5" stroke-width="2"/></svg>',
    'seo' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" ><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
    'google-business' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" ><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>',
    'website' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" ><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>',
);?>
        <?php foreach($active_svcs as $s):
            $svc_icon_svg = $svc_svg_icons[$s->service_slug] ?? '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" ><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>'; ?>
        <a href="?tab=svc_<?php echo esc_attr($s->service_slug); ?>" class="six-nav-item <?php echo $active_tab==='svc_'.$s->service_slug?'active':''; ?>" style="padding-left:28px">
            <span class="six-nav-icon"><?php echo $svc_icon_svg; ?></span>
            <span style="font-size:12px"><?php echo esc_html($s->service_name); ?></span>
        </a>
        <?php endforeach; ?>
        <a href="?tab=advisor"  class="six-nav-item <?php echo $active_tab==='advisor' ?'active':''; ?>"><span class="six-nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" ><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span> Advisor<?php if($unread_msgs>0): ?><span class="six-badge"><?php echo $unread_msgs; ?></span><?php endif; ?></a>
        <a href="?tab=reports"  class="six-nav-item <?php echo $active_tab==='reports' ?'active':''; ?>"><span class="six-nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" ><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg></span> Reports</a>
    </div>
    <div class="six-nav-section">
        <div class="six-nav-label">Account</div>
        <a href="?tab=billing" class="six-nav-item <?php echo $active_tab==='billing'?'active':''; ?>"><span class="six-nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" ><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></span> Billing</a>
        <a href="?tab=profile" class="six-nav-item <?php echo $active_tab==='profile'?'active':''; ?>"><span class="six-nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" ><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span> Profile</a>
    </div>
    <div class="six-sidebar-bottom">
        <a href="<?php echo esc_url(wp_logout_url(home_url('/get-started/'))); ?>" class="six-nav-item" style="color:var(--text3)"><span class="six-nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" ><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span> Log Out</a>
        <?php if($advisor): ?>
        <div class="six-advisor-card">
            <div class="six-advisor-avatar"><?php echo esc_html(six_get_initials($advisor->display_name)); ?></div>
            <div class="six-advisor-info">
                <div class="six-advisor-name"><?php echo esc_html($advisor->display_name); ?></div>
                <div class="six-advisor-role">Your Advisor</div>
            </div>
            <span class="six-online-dot"></span>
        </div>
        <?php endif; ?>
    </div>
</nav>

<main class="six-main">
<?php if($active_tab==='overview'):
// Load cached AI content for overview
// v2: clear cache once to force re-render with new fmtRoadmap/fmtActions format
$overview_cache_key = 'six_overview_ai_' . $user_id;
$cache_version_key  = 'six_overview_ai_v2_' . $user_id;
if ( ! get_user_meta($user_id, $cache_version_key, true) ) {
    delete_transient($overview_cache_key);
    update_user_meta($user_id, $cache_version_key, 1);
}
$cached_overview = get_transient($overview_cache_key);
$roadmap_cached  = $cached_overview['roadmap'] ?? '';
$action_cached   = $cached_overview['action']  ?? '';
?>



<!-- Dashboard Hero + KPI Row v3 — replaces Marketing Maturity -->
<?php
// ── Toronto timezone everywhere ───────────────────────────────────────────
$tz_toronto = new DateTimeZone('America/Toronto');
$dt_now     = new DateTime('now', $tz_toronto);
$hour       = intval($dt_now->format('H'));
$greeting   = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$date_str   = $dt_now->format('l, F j, Y');

// ── KPI data — from six_client_kpis (advisor-editable) ───────────────────
$kpi_table   = $wpdb->prefix . 'six_client_kpis';
$kpi_data    = array();
$kpi_exists  = $wpdb->get_var("SHOW TABLES LIKE '{$kpi_table}'") === $kpi_table;
if ( $kpi_exists ) {
    $kpi_rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT kpi_key, kpi_value, kpi_prev FROM {$kpi_table} WHERE client_id=%d", $user_id
    ) );
    foreach ( $kpi_rows as $row ) $kpi_data[$row->kpi_key] = $row;
}

// ── AI-estimated ROI if no advisor input yet ──────────────────────────────
$roi_benchmarks = array(
    'google-ads'       => array('roi_mult'=>4.2, 'visitors_mo'=>1200, 'leads_mo'=>60),
    'seo'              => array('roi_mult'=>3.5, 'visitors_mo'=>800,  'leads_mo'=>40),
    'google-business'  => array('roi_mult'=>2.8, 'visitors_mo'=>600,  'leads_mo'=>30),
    'website'          => array('roi_mult'=>2.0, 'visitors_mo'=>400,  'leads_mo'=>20),
);

// ── Try to pull estimates from AI plan generated during onboarding ──────────
$ai_plan        = $ai_plan_json ? json_decode($ai_plan_json, true) : null;
$ai_kpis        = $ai_plan['kpis'] ?? array();
$ai_leads_val   = '';
$ai_roi_val     = '';
foreach ($ai_kpis as $kpi) {
    $lbl = strtolower($kpi['label'] ?? '');
    $val = $kpi['value'] ?? '';
    if (strpos($lbl,'month 1') !== false || strpos($lbl,'month 2') !== false) {
        if (!$ai_leads_val) $ai_leads_val = $val;
    }
    if (strpos($lbl,'roi') !== false) $ai_roi_val = $val;
}

// ── Estimate from selected services (active OR onboarding-selected) ─────────
$est_svcs        = !empty($active_svcs) ? $active_svcs : array();
$est_platforms   = array_filter(array_map('trim', explode(',', $checkout->platforms ?? '')));
$onb_budget      = intval($checkout->mktg_budget ?? 0);
$est_from_onb    = empty($active_svcs) && !empty($est_platforms);

$est_roi         = 0;
$est_visitors    = 0;
$est_leads       = 0;

if (!empty($active_svcs)) {
    // Real active services
    foreach ($active_svcs as $s) {
        $bench = $roi_benchmarks[$s->service_slug] ?? array('roi_mult'=>2.0,'visitors_mo'=>300,'leads_mo'=>15);
        $est_roi      += round(floatval($s->budget) * $bench['roi_mult']);
        $est_visitors += $bench['visitors_mo'];
        $est_leads    += $bench['leads_mo'];
    }
} elseif (!empty($est_platforms) && $onb_budget > 0) {
    // No active services yet — estimate from onboarding selections + budget
    $budget_per_svc = $onb_budget / max(1, count($est_platforms));
    foreach ($est_platforms as $slug) {
        $bench = $roi_benchmarks[$slug] ?? array('roi_mult'=>2.0,'visitors_mo'=>300,'leads_mo'=>15);
        $est_roi      += round($budget_per_svc * $bench['roi_mult']);
        $est_visitors += $bench['visitors_mo'];
        $est_leads    += $bench['leads_mo'];
    }
} elseif (!empty($est_platforms)) {
    // Services selected but no budget — use baseline estimates
    foreach ($est_platforms as $slug) {
        $bench = $roi_benchmarks[$slug] ?? array('roi_mult'=>2.0,'visitors_mo'=>300,'leads_mo'=>15);
        $est_visitors += $bench['visitors_mo'];
        $est_leads    += $bench['leads_mo'];
    }
}

// If AI plan has leads/ROI, prefer those over benchmark estimates
if ($ai_leads_val) {
    // Parse e.g. "11–20" → take lower bound
    $ai_leads_num = intval(preg_replace('/[^0-9].*/', '', $ai_leads_val));
    if ($ai_leads_num > 0) $est_leads = $ai_leads_num;
}
if ($ai_roi_val && strpos($ai_roi_val, '$') !== false) {
    $ai_roi_num = intval(preg_replace('/[^0-9]/', '', $ai_roi_val));
    if ($ai_roi_num > 0) $est_roi = $ai_roi_num;
}

$est_growth_pct = $onb_budget > 0 ? min(35, round(($est_roi / max($onb_budget,1)) * 5)) : 0;

// ── Resolve each KPI: advisor value → metric → estimate → placeholder ─────
function six_kpi_resolve( $kpi_data, $key, $fallback_val, $fallback_prev=null ) {
    if ( isset($kpi_data[$key]) && $kpi_data[$key]->kpi_value !== '' ) {
        return array(
            'val'      => $kpi_data[$key]->kpi_value,
            'prev'     => $kpi_data[$key]->kpi_prev,
            'source'   => 'advisor',
        );
    }
    return array('val'=>$fallback_val, 'prev'=>$fallback_prev, 'source'=>'estimate');
}

// Find metrics
$metric_map = array();
foreach ( $metrics as $m ) $metric_map[strtolower($m->label)] = $m;
$find_m = fn($keys) => array_reduce($keys, fn($c,$k)=>$c??($metric_map[$k]??null), null);
$nc_m      = $find_m(array('new customers','customers','conversions'));
$rev_m     = $find_m(array('sales revenue','revenue','sales'));
$vis_m     = $find_m(array('total visitors','visitors','sessions','organic traffic','traffic'));

$fmt_num = function($v) {
    if(!$v || $v==='—') return $v;
    $has_dollar = strpos($v,'$')!==false; $has_pct = strpos($v,'%')!==false;
    $n = floatval(preg_replace('/[^0-9.]/','',$v));
    if($n===0.0 && $v!=='0') return $v;
    $r = round($n,2);
    $fmt = $r==floor($r) ? number_format((int)$r) : number_format($r,2);
    if($has_dollar) return '$'.$fmt;
    if($has_pct) return $fmt.'%';
    return $fmt;
};
$kpi_nc  = six_kpi_resolve($kpi_data,'new_customers',  $nc_m?$fmt_num($nc_m->current_value):($est_leads>0?number_format((int)round($est_leads)).'/mo':'—'),   $nc_m?$nc_m->previous_value:null);
// Use onboarding budget as revenue estimate base when no active services
$_rev_budget = max($total_budget, $onb_budget ?? 0);
$kpi_rev = six_kpi_resolve($kpi_data,'sales_revenue',  $rev_m?$fmt_num($rev_m->current_value):($_rev_budget>0?'+$'.number_format(round($_rev_budget*2.8)).'/mo':'—'),  $rev_m?$rev_m->previous_value:null);
$kpi_vis = six_kpi_resolve($kpi_data,'total_visitors', $vis_m?$fmt_num($vis_m->current_value):($est_visitors>0?number_format((int)round($est_visitors)).'/mo':'—'), $vis_m?$vis_m->previous_value:null);
$kpi_roi = six_kpi_resolve($kpi_data,'roi_projection', $est_roi>0?'+$'.number_format($est_roi,0).'/mo':'—', null);
$roi_growth_pct = isset($kpi_data['roi_growth_pct']) ? $kpi_data['roi_growth_pct']->kpi_value : ($est_growth_pct > 0 ? '+'.$est_growth_pct.'%' : '');

// ── Trend calculator ──────────────────────────────────────────────────────
function six_calc_trend($val_str, $prev_str) {
    if (!$prev_str || !$val_str || $val_str==='—') return null;
    $cv = floatval(preg_replace('/[^0-9.]/','',$val_str));
    $pv = floatval(preg_replace('/[^0-9.]/','',$prev_str));
    if ($pv <= 0) return null;
    return round((($cv - $pv) / $pv) * 100, 1);
}
$trend_nc  = six_calc_trend($kpi_nc['val'],  $kpi_nc['prev']);
$trend_rev = six_calc_trend($kpi_rev['val'], $kpi_rev['prev']);
$trend_vis = six_calc_trend($kpi_vis['val'], $kpi_vis['prev']);
?>

<!-- ── GREETING ──────────────────────────────────────────────────────────── -->
<div class="six-greeting-row">
    <div>
        <h1 class="six-page-title" style="margin:0"><?php echo esc_html($greeting); ?>, <?php echo esc_html($user->first_name ?: explode(' ',$user->display_name)[0]); ?></h1>
        <p class="six-page-sub" style="margin:4px 0 0"><?php echo esc_html($date_str); ?></p>
    </div>
    <a href="?tab=services" class="six-btn six-btn-primary">+ Add Service</a>
</div>

<!-- ── 4 KPI CARDS ───────────────────────────────────────────────────────── -->
<?php
// Determine metrics source for disclaimer
$metrics_are_real    = !empty($active_svcs) && !empty($kpi_data);
$metrics_from_onb    = $est_from_onb ?? (!empty($est_platforms) && empty($active_svcs));
$has_any_data        = $est_leads > 0 || $est_roi > 0 || $est_visitors > 0;
?>
<?php if ($metrics_from_onb && $has_any_data): ?>
<div style="display:flex;align-items:flex-start;gap:12px;background:rgba(99,102,241,.07);border:1px solid rgba(99,102,241,.2);border-radius:12px;padding:14px 18px;margin-bottom:16px">
    <svg viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <div>
        <div style="font-size:12.5px;font-weight:600;color:var(--text1);margin-bottom:2px">Projected metrics based on your onboarding information</div>
        <div style="font-size:11.5px;color:var(--text3);line-height:1.5">These numbers are estimates calculated from the services you selected, your budget, and industry benchmarks. Your advisor is reviewing your profile and will update these with verified data shortly.</div>
    </div>
</div>
<?php endif; ?>
<?php if (!$has_any_data && empty($active_svcs)): ?>
<div style="display:flex;align-items:flex-start;gap:12px;background:var(--dark2);border:1px solid var(--border);border-radius:12px;padding:14px 18px;margin-bottom:16px">
    <svg viewBox="0 0 24 24" fill="none" stroke="var(--text3)" stroke-width="2" width="18" height="18" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
    <div>
        <div style="font-size:12.5px;font-weight:600;color:var(--text1);margin-bottom:2px">Your advisor is setting up your metrics</div>
        <div style="font-size:11.5px;color:var(--text3);line-height:1.5">Once your services are activated, your dashboard will display real performance data. Your advisor will reach out within one business day.</div>
    </div>
</div>
<?php endif; ?>

<div class="six-kpi-row">

    <!-- 1. New Customers -->
    <div class="six-kc" data-kpi="new_customers">
        <div class="six-kc-bar" style="background:linear-gradient(90deg,#FF6699,#c084fc)"></div>
        <div class="six-kc-head">
            <span class="six-kc-label">New Customers</span>
            <div class="six-kc-icon" style="background:rgba(255,102,153,0.12)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#FF6699" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
            </div>
        </div>
        <div class="six-kc-val"><?php echo esc_html($kpi_nc['val']); ?></div>
        <?php if($trend_nc!==null): ?>
        <div class="six-kc-trend <?php echo $trend_nc>=0?'up':'dn'; ?>">
            <?php if($trend_nc>=0): ?>
            <svg width="10" height="10" viewBox="0 0 10 10"><polyline points="1,7 5,2 9,7" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round"/></svg>
            <?php else: ?>
            <svg width="10" height="10" viewBox="0 0 10 10"><polyline points="1,3 5,8 9,3" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round"/></svg>
            <?php endif; ?>
            <?php echo abs($trend_nc); ?>%
        </div>
        <?php elseif($kpi_nc['val']!=='—'): ?>
        <div class="six-kc-sub"><?php echo $kpi_nc['source']==='estimate'?($metrics_from_onb?'Projected from your plan':'Estimated from services'):'From campaigns'; ?></div>
        <?php else: ?>
        <div class="six-kc-sub" style="opacity:.4">No data yet</div>
        <?php endif; ?>
        <?php if($kpi_nc['prev']): ?><div class="six-kc-from">From <?php echo esc_html($kpi_nc['prev']); ?></div><?php endif; ?>
    </div>

    <!-- 2. Sales Revenue -->
    <div class="six-kc" data-kpi="sales_revenue">
        <div class="six-kc-bar" style="background:linear-gradient(90deg,#E3B341,#f59e0b)"></div>
        <div class="six-kc-head">
            <span class="six-kc-label">Sales Revenue</span>
            <div class="six-kc-icon" style="background:rgba(227,179,65,0.12)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#E3B341" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                </svg>
            </div>
        </div>
        <div class="six-kc-val"><?php echo esc_html($kpi_rev['val']); ?></div>
        <?php if($trend_rev!==null): ?>
        <div class="six-kc-trend <?php echo $trend_rev>=0?'up':'dn'; ?>">
            <?php if($trend_rev>=0): ?>
            <svg width="10" height="10" viewBox="0 0 10 10"><polyline points="1,7 5,2 9,7" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round"/></svg>
            <?php else: ?>
            <svg width="10" height="10" viewBox="0 0 10 10"><polyline points="1,3 5,8 9,3" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round"/></svg>
            <?php endif; ?>
            <?php echo abs($trend_rev); ?>%
        </div>
        <?php elseif($kpi_rev['val']!=='—'): ?>
        <div class="six-kc-sub"><?php echo $kpi_rev['source']==='estimate'?'Based on active budget':'Monthly revenue'; ?></div>
        <?php else: ?>
        <div class="six-kc-sub" style="opacity:.4">No data yet</div>
        <?php endif; ?>
        <?php if($kpi_rev['prev']): ?><div class="six-kc-from">From <?php echo esc_html($kpi_rev['prev']); ?></div><?php endif; ?>
    </div>

    <!-- 3. Total Visitors -->
    <div class="six-kc" data-kpi="total_visitors">
        <div class="six-kc-bar" style="background:linear-gradient(90deg,#56D364,#10b981)"></div>
        <div class="six-kc-head">
            <span class="six-kc-label">Total Visitors</span>
            <div class="six-kc-icon" style="background:rgba(86,211,100,0.12)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#56D364" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>
                </svg>
            </div>
        </div>
        <div class="six-kc-val"><?php echo esc_html($kpi_vis['val']); ?></div>
        <?php if($trend_vis!==null): ?>
        <div class="six-kc-trend <?php echo $trend_vis>=0?'up':'dn'; ?>">
            <?php if($trend_vis>=0): ?>
            <svg width="10" height="10" viewBox="0 0 10 10"><polyline points="1,7 5,2 9,7" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round"/></svg>
            <?php else: ?>
            <svg width="10" height="10" viewBox="0 0 10 10"><polyline points="1,3 5,8 9,3" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round"/></svg>
            <?php endif; ?>
            <?php echo abs($trend_vis); ?>%
        </div>
        <?php elseif($kpi_vis['val']!=='—'): ?>
        <div class="six-kc-sub"><?php echo $kpi_vis['source']==='estimate'?($metrics_from_onb?'Projected from your plan':'Estimated from services'):'Monthly sessions'; ?></div>
        <?php else: ?>
        <div class="six-kc-sub" style="opacity:.4">No data yet</div>
        <?php endif; ?>
        <?php if($kpi_vis['prev']): ?><div class="six-kc-from">From <?php echo esc_html($kpi_vis['prev']); ?></div><?php endif; ?>
    </div>

    <!-- 4. Live ROI Projection -->
    <div class="six-kc six-kc-roi" data-kpi="roi_projection">
        <div class="six-kc-bar" style="background:linear-gradient(90deg,#FF6699,#a855f7,#83C5ED)"></div>
        <div class="six-kc-head">
            <span class="six-kc-label">Live ROI Projection</span>
            <div class="six-kc-icon" style="background:rgba(168,85,247,0.12)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#a855f7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                </svg>
            </div>
        </div>
        <?php if($kpi_roi['val']!=='—'): ?>
        <div class="six-kc-val six-roi-val"><?php echo esc_html($kpi_roi['val']); ?></div>
        <?php if($roi_growth_pct): ?>
        <div class="six-kc-trend up">
            <svg width="10" height="10" viewBox="0 0 10 10"><polyline points="1,7 5,2 9,7" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round"/></svg>
            <?php echo esc_html($roi_growth_pct); ?> growth potential
        </div>
        <?php endif; ?>
        <div class="six-kc-sub" style="margin-top:6px">Based on your business &amp; selected services</div>
        <?php else: ?>
        <div class="six-kc-val" style="font-size:13px;color:var(--text3);font-weight:500;line-height:1.4">ROI will be calculated after advisor review</div>
        <?php endif; ?>
    </div>

</div><!-- /.six-kpi-row -->

<!-- ── HERO CONTENT ROW: Chart left | Advisor right ──────────────────────── -->
<div class="six-hero-row">

    <!-- LEFT: Overview chart placeholder (existing chart code preserved) -->
    <div class="six-chart-pane" id="six-chart-wrap" style="overflow:hidden;padding:0">
        <div id="six-analytics-chart" style="flex:1;display:flex;flex-direction:column;min-height:280px"></div>
    </div>

    <!-- RIGHT: Advisor card -->
    <div class="six-advisor-pane">
        <div class="six-pane-title">Your Advisor</div>
        <?php if($advisor): ?>
        <!-- Avatar -->
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:18px">
            <div class="six-adv-avatar-ring">
                <div class="six-adv-avatar"><?php echo esc_html(six_get_initials($advisor->display_name)); ?></div>
            </div>
            <div>
                <div style="font-family:'Montserrat',sans-serif;font-size:17px;font-weight:800;color:var(--text1)"><?php echo esc_html($advisor->display_name); ?></div>
                <div style="display:flex;align-items:center;gap:5px;margin-top:3px">
                    <span style="width:6px;height:6px;border-radius:50%;background:#56D364;display:inline-block"></span>
                    <span style="font-size:11px;color:#56D364;font-weight:600">Active</span>
                </div>
            </div>
        </div>
        <!-- CTA buttons -->
        <div style="display:flex;gap:10px;margin-bottom:20px">
            <a href="?tab=advisor" class="six-adv-btn six-adv-msg">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
                Message
            </a>
            <a href="?tab=advisor" class="six-adv-btn six-adv-book">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
                Book Call
            </a>
        </div>
        <!-- Services list -->
        <div class="six-adv-services">
            <?php foreach($active_svcs as $s):
                $sd = $svc_def[$s->service_slug] ?? array('color'=>'#83C5ED');
            ?>
            <div class="six-adv-svc-row">
                <div style="display:flex;align-items:center;gap:8px">
                    <div style="width:7px;height:7px;border-radius:2px;background:<?php echo esc_attr($sd['color']); ?>;flex-shrink:0"></div>
                    <span class="six-adv-svc-name"><?php echo esc_html($s->service_name); ?></span>
                </div>
                <span class="six-adv-svc-price" style="color:<?php echo floatval($s->budget)>0?'#83C5ED':'rgba(255,255,255,0.3)'; ?>">
                    $<?php echo number_format(floatval($s->budget),0); ?>
                </span>
            </div>
            <?php endforeach; ?>
            <?php if(empty($active_svcs)): ?>
            <div style="font-size:12px;color:var(--text3);text-align:center;padding:12px 0">No active services yet</div>
            <?php endif; ?>
        </div>
        <!-- Footer note -->
        <div class="six-adv-footer">Advisor will review and confirm your services &amp; pricing</div>
        <?php else: ?>
        <div style="text-align:center;padding:32px 16px;color:var(--text3)">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto 12px;display:block;opacity:.3">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
            </svg>
            <div style="font-size:13px;font-weight:600">Advisor being assigned</div>
            <div style="font-size:11px;margin-top:4px">You'll be notified shortly</div>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /.six-hero-row -->

<style>
/* ── Dashboard Hero Styles ───────────────────────────────────────────────── */
.six-nav-icon{display:flex;align-items:center;justify-content:center;flex-shrink:0;width:18px;height:18px}
.six-nav-icon svg{width:16px;height:16px;display:block}
.six-greeting-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;flex-wrap:wrap;gap:12px}

/* KPI Row */
.six-kpi-row{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:18px}
.six-kc{background:var(--dark2);border:1px solid var(--border);border-radius:16px;padding:20px 20px 18px;position:relative;overflow:hidden;transition:border-color .2s}
.six-kc:hover{border-color:rgba(255,102,153,0.25)}
.six-kc-bar{position:absolute;top:0;left:0;right:0;height:2px}
.six-kc-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
.six-kc-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.9px;color:var(--text3)}
.six-kc-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.six-kc-val{font-family:var(--font-num,'Inter',sans-serif);font-size:22px;font-weight:700;color:var(--text1);line-height:1;letter-spacing:-.5px;margin-bottom:10px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-variant-numeric:tabular-nums}
.six-roi-val{font-family:var(--font-num,'Inter',sans-serif);font-size:22px;font-variant-numeric:tabular-nums;background:linear-gradient(135deg,#FF6699,#a855f7,#83C5ED);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.six-kc-trend{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:700;padding:3px 9px;border-radius:20px}
.six-kc-trend.up{background:rgba(86,211,100,.12);color:#1a7a2e}
.six-kc-trend.dn{background:rgba(220,38,38,.1);color:#b91c1c}
.six-kc-sub{font-size:11px;color:var(--text3)}
.six-kc-from{font-size:11px;color:var(--text3);margin-top:3px}

/* Dark mode overrides — only what the vars don't cover */
[data-theme="dark"] .six-kc-trend.up{background:rgba(86,211,100,.12);color:#56D364}
[data-theme="dark"] .six-kc-trend.dn{background:rgba(255,107,107,.12);color:#FF6B6B}
[data-theme="dark"] .six-roi-val{background:linear-gradient(135deg,#FF6699,#a855f7,#83C5ED);-webkit-background-clip:text;-webkit-text-fill-color:transparent}

/* Hero row */
.six-hero-row{display:grid;grid-template-columns:1fr 320px;gap:16px;margin-bottom:20px;align-items:stretch}
.six-chart-pane{background:var(--dark2);border:1px solid var(--border);border-radius:16px;padding:24px;display:flex;flex-direction:column}
.six-advisor-pane{background:var(--dark2);border:1px solid rgba(255,102,153,.2);border-radius:16px;padding:22px;display:flex;flex-direction:column}
.six-pane-title{font-family:'Montserrat',sans-serif;font-size:15px;font-weight:800;color:var(--text1);margin-bottom:18px}

/* Light mode hero row */
/* Chart + advisor pane use --dark2 var (already correct for both modes) */
/* Legend color adapts to theme via vars */

/* Advisor */
.six-adv-avatar-ring{width:54px;height:54px;border-radius:50%;padding:2px;background:linear-gradient(135deg,#FF6699,#a855f7,#83C5ED);flex-shrink:0}
.six-adv-avatar{border-radius:50%;background:linear-gradient(135deg,var(--dark3,#1a1f2e),var(--dark1,#0E1117));display:flex;align-items:center;justify-content:center;font-family:'Montserrat',sans-serif;font-size:15px;font-weight:800;color:var(--text1);width:100%;height:100%}
:not([data-theme="dark"]) .six-adv-avatar{background:#E8EDF2;color:#111827}
.six-adv-btn{flex:1;display:flex;align-items:center;justify-content:center;gap:6px;font-size:13px;font-weight:600;padding:9px 12px;border-radius:10px;text-decoration:none;cursor:pointer;transition:all .2s}
.six-adv-msg{background:rgba(255,102,153,.08);border:1px solid rgba(255,102,153,.25);color:#FF6699}
.six-adv-msg:hover{background:rgba(255,102,153,.15);color:#FF6699}
.six-adv-book{background:rgba(131,197,237,.06);border:1px solid rgba(131,197,237,.2);color:#83C5ED}
.six-adv-book:hover{background:rgba(131,197,237,.12);color:#83C5ED}



.six-adv-services{flex:1;border-top:1px solid var(--border);padding-top:14px;margin-bottom:12px}
:not([data-theme="dark"]) .six-adv-services{border-color:rgba(0,0,0,.08)}
.six-adv-svc-row{display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid rgba(255,255,255,.04)}
.six-adv-svc-row:last-child{border-bottom:none}
:not([data-theme="dark"]) .six-adv-svc-row{border-color:rgba(0,0,0,.06)}
.six-adv-svc-name{font-size:13px;color:var(--text2)}
.six-adv-svc-price{font-size:13px;font-weight:700}
.six-adv-footer{font-size:11px;color:var(--text3);text-align:center;padding-top:10px;border-top:1px solid var(--border);line-height:1.5}

/* Legend */
.six-legend-item{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text2)}
.six-legend-dot{width:10px;height:10px;border-radius:3px;flex-shrink:0}

/* Responsive */
@media(max-width:900px){
    .six-kpi-row{grid-template-columns:1fr 1fr;gap:10px}
    .six-hero-row{grid-template-columns:1fr;gap:14px}
    .six-chart-pane{min-height:auto}
}
@media(max-width:560px){
    .six-kpi-row{grid-template-columns:1fr 1fr;gap:8px}
    .six-kc{padding:16px}
    .six-kc-val{font-size:20px}
    .six-hero-row{grid-template-columns:1fr}
    .six-chart-pane{padding:16px}
    .six-chart-pane{min-height:280px}
    .six-chart-pane > div{min-height:200px}
}

/* ── Comprehensive theme text fixes ─────────────────────────────── */
/* All text in portal uses CSS vars — these vars are correct already. */
/* These rules override anything that breaks them. */

/* KPI cards */
.six-kc .six-kc-label,.six-kc .six-kc-sub,.six-kc .six-kc-from{color:var(--text3)}
.six-kc .six-kc-val{color:var(--text1)}

/* Hero panes */
.six-chart-pane,.six-advisor-pane{color:var(--text1)}
.six-pane-title{color:var(--text1)}

/* Advisor section */
.six-adv-svc-name{color:var(--text2)}
.six-adv-footer{color:var(--text3)}

/* Greeting */
.six-greeting-row h1,.six-page-title{color:var(--text1)}
.six-page-sub{color:var(--text2)}

/* Legend */
.six-legend-item{color:var(--text2)}

/* Nav */
.six-nav-item{color:var(--text2)}
.six-nav-item.active{color:var(--text1)}
.six-nav-label{color:var(--text3)}

/* Chart month labels — set via JS tickCol which now uses correct isDark */

</style>

<style>
/* The dark chart card fills the pane (so no light gap shows when the pane
   stretches to match the advisor card), but the plot itself keeps a fixed,
   bounded height so it can NEVER run away — the SVG uses overflow:visible, and
   an unbounded/flex plot height made it draw far beyond the card. */
#six-analytics-chart {
    width: 100%;
    height: 100%;
}
#analytics-root {
    border-radius: 14px !important;
    height: 100%;
    overflow: hidden;
}
#anl-wrap {
    height: 360px !important;
}
</style>

<script>
// ── Animated Analytics Line Chart (D3-style, pure vanilla JS + SVG) ───────
(function() {

// ── 1. Pull real metrics from PHP KPI data ─────────────────────────────────
// We use the PHP-resolved KPI values to seed initial data, then let the
// live-update loop add new points from the /api/analytics endpoint.
var phpKpiLeads    = <?php echo intval($est_leads    ?? 0); ?>;
var phpKpiVisitors = <?php echo intval($est_visitors ?? 0); ?>;
var phpKpiRoi      = <?php echo floatval($est_roi    ?? 0); ?>;
var siteUrl        = <?php echo wp_json_encode(get_bloginfo('url')); ?>;

// ── 2. Generate mock history shaped around real KPI values ─────────────────
function generateData(days, baseV, baseL, baseR) {
    var data = [];
    var now = new Date();
    var v = Math.max(200, baseV * 0.7);
    var l = Math.max(20, baseL * 0.7);
    var r = Math.max(0.8, baseR * 0.7);
    for (var i = days; i >= 0; i--) {
        var d = new Date(now);
        d.setDate(d.getDate() - i);
        v += (Math.random() - 0.3) * (baseV * 0.08) + (baseV / days) * 0.6;
        l += (Math.random() - 0.3) * (baseL * 0.08) + (baseL / days) * 0.6;
        r += (Math.random() - 0.3) * 0.15 + (baseR / days) * 0.5;
        data.push({
            date: d.toISOString().slice(0,10),
            visitors: Math.max(100, Math.round(v)),
            leads:    Math.max(10,  Math.round(l)),
            roi:      Math.max(0.5, parseFloat(r.toFixed(2)))
        });
    }
    return data;
}

// ── 3. fetchAnalytics — swap for real API ──────────────────────────────────
async function fetchAnalytics(domain) {
    // Real integration (uncomment one):
    // const r = await fetch('/api/analytics?domain=' + domain); return r.json();
    // GA4:   await fetch('https://analyticsdata.googleapis.com/v1beta/...')
    // GAds:  await fetch('https://googleads.googleapis.com/v17/...')
    return generateData(30,
        phpKpiVisitors > 0 ? phpKpiVisitors * 30 : 1200,
        phpKpiLeads    > 0 ? phpKpiLeads    * 30 : 120,
        phpKpiRoi      > 0 ? phpKpiRoi           : 2.4
    );
}

// ── 4. Chart state ──────────────────────────────────────────────────────────
var DATA = [];
var animStart = null, animFrame = null, animProg = 0;
var ANIM_MS = 2400; // slow, cinematic animation — once on load only
var isDark = (localStorage.getItem('six_theme') || 'dark') === 'dark';

// ── 5. Mount the root element ───────────────────────────────────────────────
var mount = document.getElementById('six-analytics-chart');
if (!mount) return;

mount.innerHTML = [
'<div id="analytics-root" style="',
'  background:' + (isDark ? '#0B0F1A' : '#F4F6FB') + ';',
'  border-radius:14px;padding:22px 22px 14px;position:relative;overflow:hidden;',
'  font-family:\'Mulish\',\'Helvetica Neue\',sans-serif;transition:background .4s">',

'  <canvas id="anl-particles" style="position:absolute;inset:0;pointer-events:none;border-radius:14px;opacity:0.45"></canvas>',

'  <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:4px;position:relative">',
'    <div>',
'      <div style="font-size:14px;font-weight:600;letter-spacing:-.01em;color:' + (isDark?'rgba(255,255,255,.85)':'rgba(15,20,40,.85)') + '" id="anl-title">Growth Analytics</div>',
'      <div style="font-size:11px;color:' + (isDark?'rgba(255,255,255,.3)':'rgba(15,20,40,.38)') + ';margin-top:3px">Visitors · Leads · ROI</div>',
'    </div>',
'    <div style="display:flex;align-items:center;gap:8px">',
'      <div style="display:flex;align-items:center;gap:5px;font-size:10px;color:#FF6699;font-family:monospace;letter-spacing:.04em;opacity:.7">',
'        <div id="anl-live-dot" style="width:5px;height:5px;border-radius:50%;background:#FF6699;animation:anlPulse 1.6s ease-in-out infinite"></div>LIVE',
'      </div>',
'    </div>',
'  </div>',

'  <div id="anl-wrap" style="position:relative;width:100%;height:200px;margin:12px 0 6px">',
'    <svg id="anl-svg" style="width:100%;height:100%;overflow:visible" role="img" aria-label="Growth analytics: visitors, leads and ROI over 30 days">',
'      <defs>',
'        <linearGradient id="anlGV" x1="0%" y1="0%" x2="100%" y2="0%">',
'          <stop offset="0%" stop-color="#70C9F2" stop-opacity=".9"/>',
'          <stop offset="100%" stop-color="#A2C84E" stop-opacity="1"/>',
'        </linearGradient>',
'        <linearGradient id="anlGL" x1="0%" y1="0%" x2="100%" y2="0%">',
'          <stop offset="0%" stop-color="#70C9F2" stop-opacity=".9"/>',
'          <stop offset="100%" stop-color="#8782BA" stop-opacity="1"/>',
'        </linearGradient>',
'        <linearGradient id="anlGR" x1="0%" y1="0%" x2="100%" y2="0%">',
'          <stop offset="0%" stop-color="#70C9F2" stop-opacity=".9"/>',
'          <stop offset="100%" stop-color="#FF6699" stop-opacity="1"/>',
'        </linearGradient>',
'        <linearGradient id="anlFV" x1="0%" y1="0%" x2="0%" y2="100%">',
'          <stop offset="0%" stop-color="#A2C84E" stop-opacity=".16"/>',
'          <stop offset="100%" stop-color="#A2C84E" stop-opacity="0"/>',
'        </linearGradient>',
'        <linearGradient id="anlFL" x1="0%" y1="0%" x2="0%" y2="100%">',
'          <stop offset="0%" stop-color="#8782BA" stop-opacity=".18"/>',
'          <stop offset="100%" stop-color="#8782BA" stop-opacity="0"/>',
'        </linearGradient>',
'        <linearGradient id="anlFR" x1="0%" y1="0%" x2="0%" y2="100%">',
'          <stop offset="0%" stop-color="#FF6699" stop-opacity=".14"/>',
'          <stop offset="100%" stop-color="#FF6699" stop-opacity="0"/>',
'        </linearGradient>',
'        <filter id="anlGlV"><feGaussianBlur stdDeviation="2" result="b"/><feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter>',
'        <filter id="anlGlL"><feGaussianBlur stdDeviation="2.5" result="b"/><feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter>',
'        <filter id="anlGlR"><feGaussianBlur stdDeviation="2" result="b"/><feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter>',
'        <clipPath id="anlClip"><rect id="anl-clip-r" x="0" y="0" width="0" height="400"/></clipPath>',
'      </defs>',
'      <g id="anl-grid"></g>',
'      <g id="anl-xlbl"></g>',
'      <g id="anl-ylbl"></g>',
'      <g clip-path="url(#anlClip)">',
'        <path id="anl-av" fill="url(#anlFV)" opacity=".7"/>',
'        <path id="anl-al" fill="url(#anlFL)" opacity=".7"/>',
'        <path id="anl-ar" fill="url(#anlFR)" opacity=".7"/>',
'        <path id="anl-lv" fill="none" stroke="url(#anlGV)" stroke-width="2.2" filter="url(#anlGlV)"/>',
'        <path id="anl-ll" fill="none" stroke="url(#anlGL)" stroke-width="2.4" filter="url(#anlGlL)"/>',
'        <path id="anl-lr" fill="none" stroke="url(#anlGR)" stroke-width="2.2" filter="url(#anlGlR)"/>',
'        <g id="anl-dv"></g><g id="anl-dl"></g><g id="anl-dr"></g>',
'      </g>',
'      <line id="anl-hvline" stroke="rgba(255,255,255,.1)" stroke-width="1" stroke-dasharray="3,3" opacity="0"/>',
'    </svg>',
'    <div id="anl-tt" style="position:absolute;pointer-events:none;opacity:0;background:rgba(11,15,26,.88);',
'         backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);border:1px solid rgba(112,201,242,.18);',
'         border-radius:10px;padding:10px 14px;font-family:inherit;min-width:148px;',
'         box-shadow:0 8px 32px rgba(0,0,0,.45);transition:opacity .12s;z-index:20">',
'      <div id="anl-tt-d" style="font-size:10px;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.38);margin-bottom:7px;font-family:monospace"></div>',
'      <div style="display:flex;align-items:center;gap:7px;font-size:12px;color:rgba(255,255,255,.82);margin-bottom:4px">',
'        <span style="width:7px;height:7px;border-radius:50%;background:#A2C84E;flex-shrink:0"></span>Visitors',
'        <span style="margin-left:auto;font-family:monospace;font-weight:500" id="anl-tt-v"></span>',
'      </div>',
'      <div style="display:flex;align-items:center;gap:7px;font-size:12px;color:rgba(255,255,255,.82);margin-bottom:4px">',
'        <span style="width:7px;height:7px;border-radius:50%;background:#8782BA;flex-shrink:0"></span>Leads',
'        <span style="margin-left:auto;font-family:monospace;font-weight:500" id="anl-tt-l"></span>',
'      </div>',
'      <div style="display:flex;align-items:center;gap:7px;font-size:12px;color:rgba(255,255,255,.82)">',
'        <span style="width:7px;height:7px;border-radius:50%;background:#FF6699;flex-shrink:0"></span>ROI',
'        <span style="margin-left:auto;font-family:monospace;font-weight:500" id="anl-tt-r"></span>',
'      </div>',
'    </div>',
'  </div>',

'  <div style="display:flex;justify-content:flex-end;gap:18px;align-items:center;position:relative">',
'    <div style="display:flex;align-items:center;gap:6px;font-size:11px;color:rgba(255,255,255,.32);cursor:pointer">',
'      <div style="width:18px;height:2px;border-radius:1px;background:#A2C84E"></div>Visitors',
'    </div>',
'    <div style="display:flex;align-items:center;gap:6px;font-size:11px;color:rgba(255,255,255,.32);cursor:pointer">',
'      <div style="width:18px;height:2px;border-radius:1px;background:#8782BA"></div>Leads',
'    </div>',
'    <div style="display:flex;align-items:center;gap:6px;font-size:11px;color:rgba(255,255,255,.32);cursor:pointer">',
'      <div style="width:18px;height:2px;border-radius:1px;background:#FF6699"></div>ROI',
'    </div>',
'  </div>',
'</div>',

'<style>',
'@keyframes anlPulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.35;transform:scale(.65)}}',
'@keyframes anlDotPulse{0%,100%{r:3;opacity:1}50%{r:5;opacity:.55}}',
'.anl-dot{animation:anlDotPulse 2.4s ease-in-out infinite}',
'</style>'
].join('');

// ── 6. Spline path (Catmull-Rom) ────────────────────────────────────────────
function spline(pts) {
    if (pts.length < 2) return '';
    var d = 'M ' + pts[0][0] + ',' + pts[0][1];
    for (var i = 0; i < pts.length - 1; i++) {
        var p0 = pts[Math.max(0, i-1)];
        var p1 = pts[i];
        var p2 = pts[i+1];
        var p3 = pts[Math.min(pts.length-1, i+2)];
        var cp1x = p1[0] + (p2[0]-p0[0])/6, cp1y = p1[1] + (p2[1]-p0[1])/6;
        var cp2x = p2[0] - (p3[0]-p1[0])/6, cp2y = p2[1] - (p3[1]-p1[1])/6;
        d += ' C ' + cp1x + ',' + cp1y + ' ' + cp2x + ',' + cp2y + ' ' + p2[0] + ',' + p2[1];
    }
    return d;
}
function area(pts, by) {
    return spline(pts) + ' L ' + pts[pts.length-1][0] + ',' + by + ' L ' + pts[0][0] + ',' + by + ' Z';
}

// ── 7. Render ───────────────────────────────────────────────────────────────
function render(prog) {
    var wrap = document.getElementById('anl-wrap');
    var svg  = document.getElementById('anl-svg');
    if (!wrap || !svg || !DATA.length) return;
    var W = wrap.clientWidth, H = wrap.clientHeight;
    var pad = {t:16, b:28, l:40, r:12};
    svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H);

    var gc = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.06)';
    var lc = isDark ? 'rgba(255,255,255,0.28)' : 'rgba(0,0,0,0.33)';

    // scales
    var maxV=Math.max.apply(null,DATA.map(function(d){return d.visitors}));
    var minV=Math.min.apply(null,DATA.map(function(d){return d.visitors}));
    var maxL=Math.max.apply(null,DATA.map(function(d){return d.leads}));
    var minL=Math.min.apply(null,DATA.map(function(d){return d.leads}));
    var maxR=Math.max.apply(null,DATA.map(function(d){return d.roi}));
    var minR=Math.min.apply(null,DATA.map(function(d){return d.roi}));
    function sy(v,mn,mx){return H-pad.b-((v-mn)/(mx-mn+0.001))*(H-pad.t-pad.b);}
    function sx(i){return pad.l+(i/(DATA.length-1))*(W-pad.l-pad.r);}

    // grid
    var gg = document.getElementById('anl-grid'); gg.innerHTML='';
    var ns = 'http://www.w3.org/2000/svg';
    for (var gi=0;gi<=5;gi++){
        var gy=pad.t+(gi/5)*(H-pad.t-pad.b);
        var gl=document.createElementNS(ns,'line');
        gl.setAttribute('x1',pad.l);gl.setAttribute('x2',W-pad.r);
        gl.setAttribute('y1',gy);gl.setAttribute('y2',gy);
        gl.setAttribute('stroke',gc);gl.setAttribute('stroke-width','1');
        gg.appendChild(gl);
    }

    // y labels
    var yg=document.getElementById('anl-ylbl');yg.innerHTML='';
    for (var yi=0;yi<=5;yi++){
        var yv=minV+((5-yi)/5)*(maxV-minV);
        var yt=pad.t+(yi/5)*(H-pad.t-pad.b);
        var ytx=document.createElementNS(ns,'text');
        ytx.setAttribute('x',pad.l-5);ytx.setAttribute('y',yt+4);
        ytx.setAttribute('text-anchor','end');ytx.setAttribute('fill',lc);
        ytx.setAttribute('font-size','9.5');ytx.setAttribute('font-family','DM Mono,monospace');
        ytx.textContent=yv>=1000?Math.round(yv/100)/10+'k':Math.round(yv);
        yg.appendChild(ytx);
    }

    // x labels
    var xg=document.getElementById('anl-xlbl');xg.innerHTML='';
    var step=Math.ceil(DATA.length/6);
    DATA.forEach(function(d,i){
        if(i%step!==0&&i!==DATA.length-1)return;
        var xtx=document.createElementNS(ns,'text');
        xtx.setAttribute('x',sx(i));xtx.setAttribute('y',H-3);
        xtx.setAttribute('text-anchor','middle');xtx.setAttribute('fill',lc);
        xtx.setAttribute('font-size','9.5');xtx.setAttribute('font-family','DM Mono,monospace');
        xtx.textContent=new Date(d.date).toLocaleDateString('en-US',{month:'short',day:'numeric'});
        xg.appendChild(xtx);
    });

    // clip reveal
    var cr=document.getElementById('anl-clip-r');
    var cw=(W-pad.l-pad.r)*prog+pad.l;
    cr.setAttribute('width',cw);cr.setAttribute('height',H+20);

    // point arrays
    var pv=DATA.map(function(d,i){return[sx(i),sy(d.visitors,minV,maxV)];});
    var pl=DATA.map(function(d,i){return[sx(i),sy(d.leads,minL,maxL)];});
    var pr=DATA.map(function(d,i){return[sx(i),sy(d.roi,minR,maxR)];});
    var by=H-pad.b;

    document.getElementById('anl-lv').setAttribute('d',spline(pv));
    document.getElementById('anl-ll').setAttribute('d',spline(pl));
    document.getElementById('anl-lr').setAttribute('d',spline(pr));
    document.getElementById('anl-av').setAttribute('d',area(pv,by));
    document.getElementById('anl-al').setAttribute('d',area(pl,by));
    document.getElementById('anl-ar').setAttribute('d',area(pr,by));

    // dots
    var dotOp=Math.max(0,(prog-0.65)/0.35);
    [['anl-dv',pv,'#A2C84E'],['anl-dl',pl,'#8782BA'],['anl-dr',pr,'#FF6699']].forEach(function(s){
        var grp=document.getElementById(s[0]);grp.innerHTML='';
        if(dotOp<0.04)return;
        s[1].forEach(function(pt,i){
            if(i%4!==0&&i!==DATA.length-1)return;
            var h=document.createElementNS(ns,'circle');
            h.setAttribute('cx',pt[0]);h.setAttribute('cy',pt[1]);h.setAttribute('r','7');
            h.setAttribute('fill',s[2]);h.setAttribute('opacity',String(0.1*dotOp));
            grp.appendChild(h);
            var dot=document.createElementNS(ns,'circle');
            dot.setAttribute('cx',pt[0]);dot.setAttribute('cy',pt[1]);dot.setAttribute('r','3');
            dot.setAttribute('fill',s[2]);dot.setAttribute('opacity',String(dotOp));
            dot.classList.add('anl-dot');
            dot.style.animationDelay=(i*0.09)+'s';
            grp.appendChild(dot);
        });
    });
}

// ── 8. Animation (easeOutQuint) ─────────────────────────────────────────────
function ease(t){return 1-Math.pow(1-t,5);}
function anim(ts){
    if(!animStart)animStart=ts;
    animProg=Math.min(1,ease((ts-animStart)/ANIM_MS));
    render(animProg);
    if(animProg<1)animFrame=requestAnimationFrame(anim);
}
function startAnim(){
    if(animFrame)cancelAnimationFrame(animFrame);
    animStart=null;animProg=0;
    animFrame=requestAnimationFrame(anim);
}

// ── 9. Hover tooltip ────────────────────────────────────────────────────────
var anlWrap = null;
function bindHover(){
    anlWrap = document.getElementById('anl-wrap');
    if(!anlWrap)return;
    anlWrap.addEventListener('mousemove',function(e){
        if(!DATA.length)return;
        var rect=anlWrap.getBoundingClientRect();
        var W=anlWrap.clientWidth,H=anlWrap.clientHeight;
        var pad={t:16,b:28,l:40,r:12};
        var mx=e.clientX-rect.left;
        var idx=Math.max(0,Math.min(DATA.length-1,Math.round(((mx-pad.l)/(W-pad.l-pad.r))*(DATA.length-1))));
        var d=DATA[idx];
        var x=pad.l+(idx/(DATA.length-1))*(W-pad.l-pad.r);
        var hl=document.getElementById('anl-hvline');
        hl.setAttribute('x1',x);hl.setAttribute('x2',x);
        hl.setAttribute('y1',pad.t);hl.setAttribute('y2',H-pad.b);
        hl.setAttribute('opacity','1');
        var tt=document.getElementById('anl-tt');
        document.getElementById('anl-tt-d').textContent=new Date(d.date).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'});
        document.getElementById('anl-tt-v').textContent=d.visitors.toLocaleString();
        document.getElementById('anl-tt-l').textContent=d.leads.toLocaleString();
        document.getElementById('anl-tt-r').textContent=d.roi.toFixed(2)+'x';
        var tw=152;
        var left=x+14; if(left+tw>W)left=x-tw-14;
        tt.style.left=left+'px';tt.style.top='16px';tt.style.opacity='1';
        tt.style.transform='translateY('+((e.clientY-rect.top-H/2)*0.025)+'px)';
    });
    anlWrap.addEventListener('mouseleave',function(){
        document.getElementById('anl-tt').style.opacity='0';
        document.getElementById('anl-hvline').setAttribute('opacity','0');
    });
}

// ── 10. Particles ───────────────────────────────────────────────────────────
function initParticles(){
    var canvas=document.getElementById('anl-particles');
    var root2=document.getElementById('analytics-root');
    if(!canvas||!root2)return;
    var ctx=canvas.getContext('2d');
    var parts=[];
    function resize(){canvas.width=root2.clientWidth;canvas.height=root2.clientHeight;}
    function mp(){return{x:Math.random()*canvas.width,y:Math.random()*canvas.height,r:Math.random()*1.1+0.3,vx:(Math.random()-.5)*.16,vy:(Math.random()-.5)*.16,a:Math.random()*.45+.1,col:['#8782BA','#70C9F2','#A2C84E','#FF6699'][Math.floor(Math.random()*4)]};}
    resize();
    parts=Array.from({length:32},mp);
    function draw(){
        ctx.clearRect(0,0,canvas.width,canvas.height);
        parts.forEach(function(p){
            ctx.beginPath();ctx.arc(p.x,p.y,p.r,0,Math.PI*2);
            ctx.fillStyle=p.col;ctx.globalAlpha=p.a;ctx.fill();
            p.x+=p.vx;p.y+=p.vy;
            if(p.x<0||p.x>canvas.width)p.vx*=-1;
            if(p.y<0||p.y>canvas.height)p.vy*=-1;
        });
        ctx.globalAlpha=1;
        requestAnimationFrame(draw);
    }
    draw();
    window.addEventListener('resize',resize);
}

// ── 11. Theme sync with dashboard ──────────────────────────────────────────
function syncTheme(){
    var root2=document.getElementById('analytics-root');
    if(!root2)return;
    var dark=(localStorage.getItem('six_theme')||'dark')==='dark';
    isDark=dark;
    root2.style.background=dark?'#0B0F1A':'#F4F6FB';
    var title=document.getElementById('anl-title');
    if(title)title.style.color=dark?'rgba(255,255,255,.85)':'rgba(15,20,40,.85)';
    // Re-render so axis lines, labels and series recolor for the new theme
    if(typeof render==='function' && DATA && DATA.length){ render(typeof animProg!=='undefined'?animProg:1); }
}
// Listen for dashboard theme changes
document.addEventListener('six-theme-changed', syncTheme);

// ── 12. Boot ─────────────────────────────────────────────────────────────────
(async function(){
    DATA = await fetchAnalytics(siteUrl);
    bindHover();
    initParticles();
    startAnim();
    window.addEventListener('resize',function(){render(animProg);});
    // Data refresh every 30s — no re-animation (silently update)
    setInterval(async function(){
        if(!DATA.length)return;
        var last=DATA[DATA.length-1];
        var ld=new Date(last.date);ld.setDate(ld.getDate()+1);
        DATA.push({
            date:ld.toISOString().slice(0,10),
            visitors:Math.max(100,last.visitors+Math.round((Math.random()-.3)*120+50)),
            leads:Math.max(10,last.leads+Math.round((Math.random()-.3)*15+6)),
            roi:Math.max(.5,parseFloat((last.roi+(Math.random()-.3)*.18+.05).toFixed(2)))
        });
        if(DATA.length>35)DATA.shift();
        render(1); // render fully — no re-animation
    },30000);
})();

})();
</script>





<!-- Recommendations -->
<?php
$adv_recs=array_values(array_filter((array)$recs,fn($r)=>strpos($r->source??'','advisor_')===0));
$ai_recs =array_values(array_filter((array)$recs,fn($r)=>strpos($r->source??'','ai_')===0));
$all_show=array_merge($adv_recs,$ai_recs);
if(!empty($all_show)): ?>
<div class="six-card">
    <div class="six-card-header">
        <span class="six-card-title">Recommendations</span>
        <?php if(count($adv_recs)>0): ?><span style="font-size:11px;background:rgba(255,102,153,0.1);color:var(--pink);padding:3px 10px;border-radius:20px"><?php echo count($adv_recs); ?> from advisor</span><?php endif; ?>
    </div>
    <div class="six-card-body" style="padding:0">
    <?php foreach($all_show as $rec): $from_adv=strpos($rec->source??'','advisor_')===0; ?>
    <div id="rec-<?php echo $rec->id; ?>" style="padding:16px 20px;border-bottom:1px solid rgba(255,255,255,0.04);display:flex;gap:12px;<?php echo $from_adv?'background:rgba(255,102,153,0.03)':''; ?>">
        <span style="font-size:20px;flex-shrink:0"><?php echo $from_adv?'':''; ?></span>
        <div style="flex:1">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap">
                <span style="font-size:13px;font-weight:600"><?php echo esc_html($rec->title); ?></span>
                <?php if($from_adv): ?><span style="font-size:10px;background:rgba(255,102,153,0.1);color:var(--pink);padding:2px 7px;border-radius:6px">From Advisor</span><?php endif; ?>
            </div>
            <div style="font-size:12px;color:var(--text2);line-height:1.65;margin-bottom:10px"><?php echo esc_html($rec->description); ?></div>
            <div style="display:flex;gap:8px">
                <?php if($from_adv): ?>
                <button class="six-btn six-btn-primary six-btn-sm six-respond-suggestion" data-rec-id="<?php echo $rec->id; ?>" data-response="approve" style="font-size:11px">✓ Approve</button>
                <button class="six-btn six-btn-ghost  six-btn-sm six-respond-suggestion" data-rec-id="<?php echo $rec->id; ?>" data-response="dismiss" style="font-size:11px;color:var(--text3)">Dismiss</button>
                <?php else: ?>
                <?php if($rec->action_label): ?><button class="six-btn six-btn-primary six-btn-sm six-approve-rec" data-rec-id="<?php echo $rec->id; ?>" style="font-size:11px">✓ <?php echo esc_html($rec->action_label); ?></button><?php endif; ?>
                <button class="six-btn six-btn-ghost six-btn-sm six-dismiss-rec" data-rec-id="<?php echo $rec->id; ?>" style="font-size:11px">Dismiss</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php elseif($active_tab==='intelligence'):
$pending_opps  = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}six_recommendations WHERE client_id=%d AND status='active' AND source LIKE 'ai_%' ORDER BY created_at DESC",$user_id));
$approved_opps = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}six_recommendations WHERE client_id=%d AND status='approved' AND source LIKE 'ai_%' ORDER BY created_at DESC LIMIT 6",$user_id));
$opp_types=array(
    array('id'=>'perf',    'icon'=>'traffic',    'title'=>'Missing Traffic Source',   'color'=>'#4285F4','key'=>'performance',
        'prompt'=>"You are a senior marketing analyst at 6ix Developers. In exactly 2 concise sentences, identify the single most impactful missing traffic channel for this business and quantify the opportunity. Business: {$ai_business}, Industry: {$ai_industry}, Active: {$ai_svc_names}. Missing: {$missing_svcs}. End: 'Our [service] directly addresses this.' No filler."),
    array('id'=>'action',  'icon'=>'target',     'title'=>'Highest ROI Opportunity',  'color'=>'var(--pink)','key'=>'action',
        'prompt'=>"You are a marketing ROI strategist at 6ix Developers. In 2 sentences, identify the highest-ROI service to add and give a realistic return range. Business: {$ai_business}, Industry: {$ai_industry}, Goal: {$ai_goal}, Current: {$ai_svc_names}. Missing: {$missing_svcs}. Be specific with numbers. End: 'This is available as part of our [service] package.'"),
    array('id'=>'channel', 'icon'=>'gap',        'title'=>'Competitor Gap',           'color'=>'var(--cyan)','key'=>'channel',
        'prompt'=>"You are a competitive intelligence analyst at 6ix Developers. In 2 sentences, describe the most significant channel gap vs competitors and what business is being lost. Business: {$ai_business}, Industry: {$ai_industry}, Current: {$ai_svc_names}. Competitors: ".($comp_str?:'typical industry players').". End: '6ix Developers can close this with our [service].'"),
    array('id'=>'roi',     'icon'=>'revenue',    'title'=>'Revenue Gap Analysis',     'color'=>'#E3B341','key'=>'roi',
        'prompt'=>"You are a revenue strategist at 6ix Developers. In 2 sentences, identify the biggest revenue opportunity being left on the table. Business: {$ai_business}, Industry: {$ai_industry}, Services: {$ai_svc_names}. Use realistic benchmark numbers. End: 'Our [service] typically delivers [result] within [timeframe].'"),
    array('id'=>'content', 'icon'=>'brand',      'title'=>'Brand Authority Gap',      'color'=>'#a855f7','key'=>'content',
        'prompt'=>"You are a brand strategist at 6ix Developers. In 2 sentences, assess this business's brand authority gap and the competitive risk. Business: {$ai_business}, Industry: {$ai_industry}, Services: {$ai_svc_names}. Make it specific to their industry. End: 'Our brand and content services address this directly.'"),
    array('id'=>'quickwin','icon'=>'trending-up','title'=>'30-Day Revenue Projection','color'=>'var(--success)','key'=>'quickwin',
        'prompt'=>"You are a growth advisor at 6ix Developers. In 2 sentences, project what adding the most impactful missing service delivers in 30 days. Business: {$ai_business}, Industry: {$ai_industry}, Challenge: {$ai_challenge}, Services: {$ai_svc_names}. Missing: {$missing_svcs}. Use compelling realistic numbers. End: 'Book a strategy call — your advisor is ready.'"),
);
?>
<div class="six-page-header">
    <div><h1 class="six-page-title">AI Marketing Insights</h1><p class="six-page-sub">Personalised intelligence for <strong><?php echo esc_html($ai_business); ?></strong></p></div>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <?php if(count($pending_opps)>0): ?><span style="font-size:12px;background:rgba(255,102,153,0.1);color:var(--pink);padding:6px 14px;border-radius:20px;border:1px solid rgba(255,102,153,0.3)"><?php echo count($pending_opps); ?> awaiting advisor</span><?php endif; ?>
    </div>
</div>
<?php if(!empty($approved_opps)): ?>
<div class="six-card" style="margin-bottom:20px;border-color:rgba(86,211,100,0.25);background:rgba(86,211,100,0.03)">
    <div class="six-card-body" style="padding:14px 18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
        <span style="font-size:12px;color:var(--success);font-weight:600">✓ <?php echo count($approved_opps); ?> strateg<?php echo count($approved_opps)===1?'y':'ies'; ?> active with your advisor</span>
        <div style="display:flex;flex-wrap:wrap;gap:6px"><?php foreach($approved_opps as $ap): ?><span style="font-size:11px;background:rgba(86,211,100,0.08);color:var(--success);padding:3px 10px;border-radius:20px;border:1px solid rgba(86,211,100,0.2)">✓ <?php echo esc_html($ap->title); ?></span><?php endforeach; ?></div>
    </div>
</div>
<?php endif; ?>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px">
<?php foreach($opp_types as $opp):
    $existing   =$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}six_recommendations WHERE client_id=%d AND source=%s ORDER BY created_at DESC LIMIT 1",$user_id,'ai_'.$opp['key']));
    $is_pending =$existing&&$existing->status==='active';
    $is_approved=$existing&&$existing->status==='approved';
    $has_content=$existing&&!empty($existing->description)&&$existing->status!=='dismissed';
    if($has_content){$clean=preg_replace('/[→#\*`_]+/','',trim($existing->description));$clean=preg_replace('/\s+/',' ',$clean);}
?>
<div class="six-card" id="opp-card-<?php echo $opp['id']; ?>" style="border-top:3px solid <?php echo $opp['color']; ?>;transition:transform 0.2s,box-shadow 0.2s">
    <div class="six-card-body" style="padding:18px 20px">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
            <div style="width:36px;height:36px;border-radius:9px;background:<?php echo $opp['color']; ?>18;color:<?php echo $opp['color']; ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0"><?php echo class_exists('Six_Icon')?Six_Icon::get($opp['icon'],'','18px'):''; ?></div>
            <div style="flex:1">
                <div style="font-size:13px;font-weight:700"><?php echo esc_html($opp['title']); ?></div>
                <div style="font-size:10px;color:var(--text3);text-transform:uppercase;letter-spacing:0.4px"><?php echo esc_html($ai_industry?:'Your Industry'); ?></div>
            </div>
            <?php if($is_approved): ?><span style="font-size:10px;background:rgba(86,211,100,0.12);color:var(--success);padding:3px 9px;border-radius:10px;font-weight:700">✓ Active</span>
            <?php elseif($is_pending): ?><span class="six-opp-badge" style="font-size:10px;background:rgba(227,179,65,0.12);color:var(--warning);padding:3px 9px;border-radius:10px;font-weight:700"> Sent</span><?php endif; ?>
        </div>
        <div id="opp-content-<?php echo $opp['id']; ?>" data-prompt="<?php echo esc_attr($opp['prompt']); ?>" data-type="<?php echo esc_attr($opp['key']); ?>" style="margin-bottom:14px;min-height:60px">
            <?php if($has_content): ?>
            <div style="font-size:13px;color:var(--text1);line-height:1.75;padding:12px;background:var(--dark4);border-radius:8px;border-left:3px solid <?php echo $opp['color']; ?>"><?php echo esc_html($clean); ?></div>
            <?php else: ?>
            <div class="six-opp-teaser" style="font-size:12px;color:var(--text3);line-height:1.7;padding:4px 0">Select <strong>See Insight</strong> to generate your personalised analysis.</div>
            <div class="six-ai-loading" style="display:none;margin-top:8px"><span class="six-ai-spinner"></span> <span style="font-size:12px;color:var(--text3)">Analysing…</span></div>
            <?php endif; ?>
        </div>
        <div class="six-opp-actions" id="opp-actions-<?php echo $opp['id']; ?>">
            <?php if($is_approved): ?>
            <div style="font-size:12px;color:var(--success);font-weight:600">✓ Your advisor is working on this</div>
            <?php elseif($is_pending): ?>
            <div style="display:flex;align-items:center;justify-content:space-between">
                <span style="font-size:12px;color:var(--warning)"> Advisor reviewing</span>
                <button class="six-btn six-btn-ghost six-btn-sm six-dismiss-opp" data-rec-id="<?php echo $existing->id; ?>" data-card="<?php echo $opp['id']; ?>" style="font-size:11px;color:var(--text3)">Remove</button>
            </div>
            <?php else: ?>
            <?php if($has_content): ?>
            <button class="six-btn six-btn-primary six-btn-sm six-request-opp" data-type="<?php echo esc_attr($opp['key']); ?>" data-title="<?php echo esc_attr($opp['title']); ?>" data-card="<?php echo $opp['id']; ?>" style="font-size:12px">+ Add to Action Plan</button>
            <?php else: ?>
            <button class="six-btn six-btn-ghost six-btn-sm six-preview-opp" data-card="<?php echo $opp['id']; ?>" style="font-size:11px;border-color:<?php echo $opp['color']; ?>;color:<?php echo $opp['color']; ?>">See Insight</button>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<?php elseif($active_tab==='competitor'):
$domain  = $website_url ? preg_replace('/^www\./','',(parse_url($website_url,PHP_URL_HOST)??'')) : '';
$has_data = $domain || $ai_industry || ($checkout->competitors ?? '');
$saved_comps = array_filter(array_map('trim', explode(',', $checkout->competitors ?? '')));
$ctx     = "Business: {$ai_business}, Industry: {$ai_industry}, Website: ".($domain?:'not set').", Location: ".($checkout->location??'not set').", Current services: ".($ai_svc_names?:'none').". Known competitors: ".($comp_str?:'analyse typical industry competitors').". Available 6ix services: Google Ads, SEO, , Website Development.";
?>
<div class="six-page-header">
    <div><h1 class="six-page-title">Competitor Intelligence</h1><p class="six-page-sub">See where you stand and where to gain ground</p></div>
    <button class="six-btn six-btn-primary" id="six-run-competitor-analysis"> Run Analysis</button>
</div>
<?php if(!$has_data): ?>
<div class="six-card"><div class="six-card-body" style="text-align:center;padding:60px">
    <div style="font-size:40px;margin-bottom:16px"></div>
    <div style="font-size:15px;font-weight:600;margin-bottom:8px">Set up your profile first</div>
    <p style="font-size:13px;color:var(--text2);max-width:360px;margin:0 auto 20px;line-height:1.7">Add your website URL and industry in your profile to run a competitor analysis. Your advisor can also add this for you.ebsite, industry, and competitors in your profile to unlock competitive intelligence.</p>
    <a href="?tab=profile" class="six-btn six-btn-primary">Complete Profile →</a>
</div></div>
<?php else: ?>
<div class="six-card" style="margin-bottom:20px">
    <div class="six-card-body" style="padding:14px 18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
        <div style="display:flex;gap:24px;flex-wrap:wrap">
            <?php foreach(array('Your Site'=>$domain?:'Not set','Industry'=>$ai_industry?:'Not set','Competitors'=>$comp_str?:'Not set') as $lbl=>$val): ?>
            <div><div style="font-size:10px;color:var(--text3);font-weight:600;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:2px"><?php echo $lbl; ?></div><div style="font-size:13px;font-weight:600"><?php echo esc_html(strlen($val)>40?substr($val,0,37).'…':$val); ?></div></div>
            <?php endforeach; ?>
        </div>
        <a href="?tab=profile" class="six-btn six-btn-ghost six-btn-sm" style="font-size:11px"> Update</a>
    </div>
</div>
<span id="comp-ctx" data-value="<?php echo esc_attr($ctx); ?>" style="display:none"></span>
<div id="competitor-results" style="display:none">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
        <div class="six-card"><div class="six-card-header" style="border-bottom:1px solid var(--border);padding-bottom:12px"><div style="display:flex;align-items:center;gap:8px"><span></span><span class="six-card-title">Competitive Landscape</span><span class="six-ai-badge">AI</span></div></div><div id="ai-comp-landscape-body" class="six-card-body" style="font-size:13px;color:var(--text2);line-height:1.75"><div class="six-ai-loading"><span class="six-ai-spinner"></span> Mapping…</div></div></div>
        <div class="six-card"><div class="six-card-header" style="border-bottom:1px solid var(--border);padding-bottom:12px"><div style="display:flex;align-items:center;gap:8px"><span></span><span class="six-card-title">Market Gaps You Can Win</span><span class="six-ai-badge">AI</span></div></div><div id="ai-comp-gaps-body" class="six-card-body" style="font-size:13px;color:var(--text2);line-height:1.75"><div class="six-ai-loading"><span class="six-ai-spinner"></span> Finding gaps…</div></div></div>
        <div class="six-card"><div class="six-card-header" style="border-bottom:1px solid var(--border);padding-bottom:12px"><div style="display:flex;align-items:center;gap:8px"><span></span><span class="six-card-title">Keywords to Target</span><span class="six-ai-badge">AI</span></div></div><div id="ai-comp-keywords-body" class="six-card-body" style="font-size:13px;color:var(--text2);line-height:1.75"><div class="six-ai-loading"><span class="six-ai-spinner"></span> Researching…</div></div></div>
        <div class="six-card"><div class="six-card-header" style="border-bottom:1px solid var(--border);padding-bottom:12px"><div style="display:flex;align-items:center;gap:8px"><span></span><span class="six-card-title">Your Positioning Advantage</span><span class="six-ai-badge">AI</span></div></div><div id="ai-comp-positioning-body" class="six-card-body" style="font-size:13px;color:var(--text2);line-height:1.75"><div class="six-ai-loading"><span class="six-ai-spinner"></span> Crafting…</div></div></div>
    </div>
    <div class="six-card"><div class="six-card-header" style="border-bottom:1px solid var(--border);padding-bottom:12px"><div style="display:flex;align-items:center;gap:8px"><span></span><span class="six-card-title">How to Overtake Competitors This Quarter</span><span class="six-ai-badge">AI</span></div></div><div id="ai-comp-winplan-body" class="six-card-body" style="font-size:13px;color:var(--text2);line-height:1.75"><div class="six-ai-loading"><span class="six-ai-spinner"></span> Building strategy…</div></div></div>
</div>
<?php endif; ?>

<?php elseif($active_tab==='services'): ?>
<div class="six-page-header"><div><h1 class="six-page-title">Services</h1><p class="six-page-sub">Manage your marketing services and budgets</p></div></div>
<div class="six-services-grid">
<?php
$all_svc_defs=array(
    array('slug'=>'google-ads',       'name'=>'Google Ads',              'desc'=>'Paid search campaigns that drive immediate, high-intent leads.'),
    array('slug'=>'seo',              'name'=>'SEO',                     'desc'=>'Long-term organic visibility that compounds month over month.'),
    array('slug'=>'google-business',  'name'=>'Google Business Profile', 'desc'=>'Dominate local search and drive walk-ins with an optimised listing.'),
    array('slug'=>'website',          'name'=>'Website Development',     'desc'=>'Conversion-optimised websites built for growth.'),
);
foreach($all_svc_defs as $def):
    $existing=null; foreach($services as $s){if($s->service_slug===$def['slug']){$existing=$s;break;}}
    $sd=$svc_def[$def['slug']]??array('icon'=>'','color'=>'var(--pink)');
    $pending_req=$existing?get_user_meta($user_id,'six_budget_req_'.$existing->id,true):null;
?>
<div class="six-card">
    <div class="six-card-header" style="border-bottom:1px solid var(--border);padding-bottom:14px">
        <div style="display:flex;align-items:center;gap:12px">
            <div style="width:42px;height:42px;border-radius:11px;background:<?php echo $sd['color']; ?>18;display:flex;align-items:center;justify-content:center;font-size:20px"><?php echo $sd['icon']; ?></div>
            <div><div style="font-size:14px;font-weight:700"><?php echo esc_html($def['name']); ?></div><div style="font-size:11px;color:var(--text3);margin-top:2px"><?php echo esc_html($def['desc']); ?></div></div>
        </div>
        <?php if($existing): ?><span class="six-status-badge <?php echo $existing->status; ?>"><?php echo ucfirst($existing->status); ?></span><?php else: ?><span style="font-size:11px;color:var(--text3);background:var(--dark4);padding:3px 9px;border-radius:8px">Not Active</span><?php endif; ?>
    </div>
    <div class="six-card-body" style="padding:14px 16px">
    <?php if($existing&&$existing->status==='active'): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
            <span style="font-size:11px;color:var(--text3);font-weight:600;text-transform:uppercase;letter-spacing:0.5px">Monthly Budget</span>
            <strong style="color:var(--cyan);font-size:15px"><?php echo $existing->budget>0?'$'.number_format($existing->budget,0).'/mo':'<span style="color:var(--text3)">Not set</span>'; ?></strong>
        </div>
        <?php if($pending_req&&($pending_req['status']??'')==='pending'): ?>
        <div class="six-pending-msg" style="font-size:11px;padding:8px;margin-bottom:8px"> Change to $<?php echo number_format($pending_req['requested_budget'],0); ?>/mo pending</div>
        <?php else: ?>
        <div id="svc-budget-form-<?php echo $existing->id; ?>" style="display:none;margin-bottom:8px">
            <div style="display:flex;gap:8px">
                <input type="number" class="six-input six-budget-input" value="<?php echo esc_attr(intval($existing->budget)); ?>" placeholder="e.g. 1500" min="0" style="flex:1;padding:7px 10px;font-size:12px">
                <button class="six-btn six-btn-primary six-btn-sm six-submit-budget" data-service-id="<?php echo $existing->id; ?>">Request</button>
                <button class="six-btn six-btn-ghost six-btn-sm" onclick="document.getElementById('svc-budget-form-<?php echo $existing->id; ?>').style.display='none';document.getElementById('svc-budget-trigger-<?php echo $existing->id; ?>').style.display=''"></button>
            </div>
            <div class="six-budget-msg" style="font-size:11px;margin-top:5px"></div>
        </div>
        <button id="svc-budget-trigger-<?php echo $existing->id; ?>" class="six-btn six-btn-ghost six-btn-sm" onclick="document.getElementById('svc-budget-form-<?php echo $existing->id; ?>').style.display='block';this.style.display='none'" style="font-size:11px;margin-bottom:8px"><?php echo $existing->budget>0?' Request Change':'+ Set Budget'; ?></button>
        <?php endif; ?>
        <a href="?tab=svc_<?php echo esc_attr($existing->service_slug); ?>" class="six-btn six-btn-secondary six-btn-sm" style="font-size:11px;display:block;text-align:center">View Performance →</a>
    <?php elseif($existing&&$existing->status==='pending'): ?>
        <button class="six-btn six-btn-secondary six-btn-sm" disabled style="width:100%;justify-content:center;opacity:.7;cursor:not-allowed">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Awaiting Approval
        </button>
    <?php else: ?>
        <div style="font-size:12px;color:var(--text3);margin-bottom:12px;line-height:1.6">Request this service and your advisor will review it within 24 hours.</div>
        <button class="six-btn six-btn-primary six-btn-sm six-request-service" data-service="<?php echo esc_attr($def['slug']); ?>" style="width:100%;justify-content:center">Request Service</button>
    <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
</div>

<?php elseif(strpos($active_tab,'svc_')===0):
$svc_slug = substr($active_tab, 4);
$cur_svc  = null;
foreach($services as $s){ if($s->service_slug===$svc_slug && $s->status==='active'){ $cur_svc=$s; break; } }
if(!$cur_svc): ?>
<div class="six-card"><div class="six-card-body" style="text-align:center;padding:60px;color:var(--text3)">Service not active. <a href="?tab=services" class="six-btn six-btn-ghost six-btn-sm" style="margin-left:12px">← Back</a></div></div>
<?php else:
$sd        = $svc_def[$svc_slug] ?? array('name'=>ucfirst($svc_slug),'icon'=>'','color'=>'var(--pink)');
$s_mets    = array_values(array_filter((array)$metrics, fn($m)=>$m->service_slug===$svc_slug));
// Only pull recs that are explicitly for this service
// advisor_ recs are included only if title/source mentions the service slug
$s_recs = array_values(array_filter((array)$recs, fn($r) =>
    stripos($r->title??'',   $sd['name'])  !== false ||
    stripos($r->title??'',   $svc_slug)    !== false ||
    stripos($r->source??'',  $svc_slug)    !== false ||
    ( strpos($r->source??'','advisor_')===0 &&
      ( stripos($r->title??'',$sd['name'])!==false ||
        stripos($r->description??'',$sd['name'])!==false ) )
));
$svc_metrics_ctx = '';
foreach($s_mets as $m) $svc_metrics_ctx .= $m->label.': '.$m->current_value.' (prev: '.($m->previous_value?:'N/A').', target: '.$m->target_value.'); ';

// Build chart data from metrics
$chart_labels  = array();
$chart_current = array();
$chart_prev    = array();
$chart_target  = array();
foreach(array_slice($s_mets, 0, 6) as $m) {
    $chart_labels[]  = $m->label;
    $chart_current[] = floatval(preg_replace('/[^0-9.]/','',$m->current_value));
    $chart_prev[]    = floatval(preg_replace('/[^0-9.]/','',$m->previous_value));
    $chart_target[]  = floatval(preg_replace('/[^0-9.]/','',$m->target_value));
}
$chart_json = json_encode(array(
    'labels'  => $chart_labels,
    'current' => $chart_current,
    'prev'    => $chart_prev,
    'target'  => $chart_target,
    'color'   => $sd['color'],
));
?>

<!-- Service page header -->
<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;max-width:100%;overflow:hidden">
    <div style="display:flex;align-items:center;gap:14px;min-width:0">
        <div style="width:52px;height:52px;border-radius:14px;background:<?php echo $sd['color']; ?>18;display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0;border:1px solid <?php echo $sd['color']; ?>30"><?php echo $sd['icon']; ?></div>
        <div>
            <h1 class="six-page-title" style="margin:0"><?php echo esc_html($sd['name']); ?></h1>
            <div style="display:flex;align-items:center;gap:12px;margin-top:4px">
                <span style="font-size:12px;color:var(--success);font-weight:600">● Active</span>
                <span style="font-size:12px;color:var(--cyan);font-weight:600">$<?php echo number_format($cur_svc->budget,0); ?>/mo</span>
                <?php if($advisor): ?><span style="font-size:12px;color:var(--text3)">Managed by <?php echo esc_html($advisor->display_name); ?></span><?php endif; ?>
            </div>
        </div>
    </div>
    <div style="display:flex;gap:8px">
        <a href="?tab=advisor" class="six-btn six-btn-primary six-btn-sm" style="font-size:12px">Message Advisor</a>
    </div>
</div>

<?php if(!empty($s_mets)): ?>

<!-- Metrics — clean table row layout instead of box-on-box -->
<div style="background:var(--dark2);border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:20px">
    <div style="padding:14px 20px;border-bottom:1px solid rgba(255,255,255,0.05);display:flex;align-items:center;gap:8px">
        <div style="width:6px;height:6px;border-radius:50%;background:<?php echo $sd['color']; ?>"></div>
        <span style="font-size:12px;font-weight:700;color:var(--text1)">Performance Metrics</span>
        <span style="font-size:11px;color:var(--text3);margin-left:4px">· Updated by your advisor</span>
    </div>
    <!-- Summary row — big numbers across top -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));border-bottom:1px solid rgba(255,255,255,0.05)">
    <?php foreach(array_slice($s_mets,0,4) as $met):
        $c_n = floatval(preg_replace('/[^0-9.]/','',$met->current_value));
        $p_n = floatval(preg_replace('/[^0-9.]/','',$met->previous_value));
        $tr  = ($p_n>0)?round((($c_n-$p_n)/$p_n)*100):null;
    ?>
    <div style="padding:18px 20px;border-right:1px solid rgba(255,255,255,0.05)">
        <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:var(--text3);margin-bottom:6px"><?php echo esc_html($met->label); ?></div>
        <div style="font-size:22px;font-weight:800;font-family:'Montserrat',sans-serif;color:var(--text1);line-height:1;margin-bottom:6px"><?php echo esc_html($met->current_value); ?></div>
        <?php if($tr!==null): ?>
        <div style="display:flex;align-items:center;gap:4px">
            <span style="font-size:10px;font-weight:700;color:<?php echo $tr>=0?'var(--success)':'var(--danger)'; ?>"><?php echo $tr>=0?'↑':'↓'; ?><?php echo abs($tr); ?>%</span>
            <span style="font-size:10px;color:var(--text3)">vs last month</span>
        </div>
        <?php elseif($met->previous_value): ?>
        <div style="font-size:10px;color:var(--text3)">Was: <?php echo esc_html($met->previous_value); ?></div>
        <?php else: ?>
        <div style="font-size:10px;color:var(--text3)">No prior data</div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    </div>
    <!-- Detail rows for each metric with progress vs target -->
    <?php foreach($s_mets as $i=>$met):
        $c_n = floatval(preg_replace('/[^0-9.]/','',$met->current_value));
        $t_n = floatval(preg_replace('/[^0-9.]/','',$met->target_value));
        $pct = ($t_n>0)?min(100,round(($c_n/$t_n)*100)):0;
        $mc  = $pct>=75?'var(--success)':($pct>=40?$sd['color']:'var(--warning)');
    ?>
    <div style="display:flex;align-items:center;gap:16px;padding:12px 20px;border-bottom:1px solid rgba(255,255,255,0.04);<?php echo $i===count($s_mets)-1?'border-bottom:none':''; ?>">
        <div style="width:140px;flex-shrink:0;font-size:12px;color:var(--text2)"><?php echo esc_html($met->label); ?></div>
        <div style="flex:1;height:5px;background:rgba(255,255,255,0.06);border-radius:3px;overflow:hidden">
            <div style="height:100%;width:<?php echo $pct; ?>%;background:<?php echo $mc; ?>;border-radius:3px;transition:width 1s ease"></div>
        </div>
        <div style="width:80px;text-align:right;font-size:11px;color:var(--text3)">Target: <?php echo esc_html($met->target_value?:'–'); ?></div>
        <div style="width:40px;text-align:right;font-size:11px;font-weight:700;color:<?php echo $mc; ?>"><?php echo $pct; ?>%</div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Performance Chart -->
<?php if(count($s_mets) >= 2): ?>
<div style="background:var(--dark2);border:1px solid var(--border);border-radius:14px;padding:20px;margin-bottom:20px;overflow:hidden;max-width:100%">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:8px">
        <div style="font-size:13px;font-weight:700">Performance Trend</div>
        <div style="display:flex;align-items:center;gap:14px">
            <div style="display:flex;align-items:center;gap:5px"><div style="width:10px;height:3px;background:<?php echo $sd['color']; ?>;border-radius:2px"></div><span style="font-size:10px;color:var(--text3)">Current</span></div>
            <div style="display:flex;align-items:center;gap:5px"><div style="width:10px;height:3px;background:rgba(255,255,255,0.2);border-radius:2px"></div><span style="font-size:10px;color:var(--text3)">Previous</span></div>
            <div style="display:flex;align-items:center;gap:5px"><div style="width:10px;height:3px;border-top:2px dashed #56D364"></div><span style="font-size:10px;color:var(--text3)">Target</span></div>
        </div>
    </div>
    <!-- Fixed-height wrapper prevents Chart.js infinite expansion -->
    <div style="position:relative;height:220px;width:100%;overflow:hidden">
        <canvas id="svc-perf-chart" style="display:block;max-width:100%"></canvas>
    </div>
</div>
<script>
(function(){
    var d=<?php echo $chart_json; ?>;
    var canvas=document.getElementById('svc-perf-chart');
    if(!canvas||!d.labels.length)return;
    if(window.Chart){initChart();return;}
    var script=document.createElement('script');
    script.src='https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js';
    script.onload=initChart;
    document.head.appendChild(script);
    function initChart(){
        new Chart(canvas,{
            type:'bar',
            data:{
                labels:d.labels,
                datasets:[
                    {label:'Current',data:d.current,backgroundColor:d.color+'BB',borderRadius:5,borderSkipped:false,barPercentage:0.45,categoryPercentage:0.8},
                    {label:'Previous',data:d.prev,backgroundColor:'rgba(255,255,255,0.1)',borderRadius:5,borderSkipped:false,barPercentage:0.45,categoryPercentage:0.8},
                    {label:'Target',data:d.target,type:'line',borderColor:'#56D364',backgroundColor:'transparent',borderWidth:2,borderDash:[4,4],pointRadius:3,pointBackgroundColor:'#56D364'}]
            },
            options:{
                responsive:true,
                maintainAspectRatio:false,
                animation:{duration:500,resize:{duration:0}},
                layout:{padding:0},
                plugins:{
                    legend:{display:false},
                    tooltip:{backgroundColor:'rgba(13,17,23,0.95)',borderColor:'rgba(255,255,255,0.1)',borderWidth:1,padding:10,titleFont:{size:11},bodyFont:{size:11}}
                },
                scales:{
                    x:{grid:{color:'rgba(255,255,255,0.04)'},ticks:{color:'rgba(255,255,255,0.35)',font:{size:10}},border:{display:false}},
                    y:{grid:{color:'rgba(255,255,255,0.04)'},ticks:{color:'rgba(255,255,255,0.35)',font:{size:10}},beginAtZero:true,border:{display:false}}
                }
            }
        });
    }
})();
</script>
<?php endif; ?>

<?php else: ?>
<div style="background:var(--dark2);border:1px solid var(--border);border-radius:14px;padding:28px 24px;margin-bottom:20px;display:flex;align-items:center;gap:20px">
    <div style="width:48px;height:48px;border-radius:12px;background:<?php echo $sd['color']; ?>12;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0"><?php echo $sd['icon']; ?></div>
    <div>
        <div style="font-size:13px;font-weight:700;margin-bottom:4px">Metrics coming soon</div>
        <div style="font-size:12px;color:var(--text3)">Your advisor will add performance data as your <?php echo esc_html($sd['name']); ?> campaign runs. You'll be notified when the first report is ready.</div>
    </div>
</div>
<?php endif; ?>

<!-- AI Upsell Opportunity card -->
<div style="background:linear-gradient(135deg,var(--dark2),rgba(168,85,247,0.06));border:1px solid rgba(168,85,247,0.2);border-radius:14px;overflow:hidden;margin-bottom:20px;position:relative">
    <div style="position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,#a855f7,var(--cyan))"></div>
    <div style="padding:18px 20px;border-bottom:1px solid rgba(168,85,247,0.1);display:flex;align-items:center;justify-content:space-between">
        <div style="display:flex;align-items:center;gap:8px">
            <span style="font-size:16px"></span>
            <div>
                <div style="font-size:13px;font-weight:700">Growth Opportunity Analysis</div>
                <div style="font-size:10px;color:var(--text3)">AI-powered · Based on your <?php echo esc_html($sd['name']); ?> performance</div>
            </div>
            <span class="six-ai-badge">AI</span>
        </div>
        <div id="svc-upsell-action-<?php echo esc_attr($svc_slug); ?>" style="display:none">
            <button class="six-btn six-btn-primary six-btn-sm six-request-opp"
                    data-type="svc_upsell_<?php echo esc_attr($svc_slug); ?>"
                    data-title="<?php echo esc_attr($sd['name']); ?> Growth Opportunity"
                    data-card="svc-upsell-<?php echo esc_attr($svc_slug); ?>"
                    style="font-size:11px">+ Add to Action Plan</button>
        </div>
    </div>
    <div class="six-ai-body" id="svc-upsell-body-<?php echo esc_attr($svc_slug); ?>"
         style="padding:16px 20px;font-size:13px;color:var(--text2);line-height:1.8;min-height:80px"
         data-prompt="<?php echo esc_attr("You are a senior ".$sd['name']." growth strategist at 6ix Developers. Based on this client's performance, identify ONE specific upsell opportunity that would dramatically improve results. Business: ".$ai_business.", Industry: ".$ai_industry.", Budget: \$".number_format($cur_svc->budget,0)."/mo, Metrics: ".($svc_metrics_ctx?:"no metrics yet").". Available 6ix services not yet active: ".$missing_svcs.". Write exactly 3 sentences: (1) What the data shows is underperforming. (2) What adding or upgrading would achieve with a specific number. (3) End with: I recommend we discuss this in our next session. Be direct, no filler."); ?>">
        <div class="six-ai-loading" style="padding:4px 0"><span class="six-ai-spinner"></span> <span style="font-size:12px;color:var(--text3)">Analysing your campaign performance…</span></div>
    </div>
</div>

<!-- Advisor Recommendations -->
<?php if(!empty($s_recs)): ?>
<div style="background:var(--dark2);border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:20px;max-width:100%;box-sizing:border-box">
    <div style="padding:16px 20px;border-bottom:1px solid rgba(255,255,255,0.05);display:flex;align-items:center;gap:8px">
        <span></span><span style="font-size:13px;font-weight:700">Advisor Recommendations</span>
        <span style="font-size:11px;color:var(--pink);background:rgba(255,102,153,0.1);padding:2px 8px;border-radius:10px"><?php echo count($s_recs); ?> active</span>
    </div>
    <?php foreach($s_recs as $rec): $from_adv=strpos($rec->source??'','advisor_')===0; ?>
    <div id="rec-<?php echo $rec->id; ?>" style="padding:14px 20px;border-bottom:1px solid rgba(255,255,255,0.04);display:flex;gap:10px;min-width:0;overflow:hidden">
        <span style="font-size:16px;flex-shrink:0;margin-top:2px"><?php echo $from_adv?'':''; ?></span>
        <div style="flex:1;min-width:0;overflow:hidden">
            <div style="font-size:13px;font-weight:600;margin-bottom:4px;overflow:hidden;text-overflow:ellipsis"><?php echo esc_html($rec->title); ?></div>
            <div style="font-size:12px;color:var(--text2);line-height:1.65;margin-bottom:8px;word-wrap:break-word;overflow-wrap:break-word"><?php echo esc_html($rec->description); ?></div>
            <div style="display:flex;gap:8px">
                <?php if($from_adv): ?>
                <button class="six-btn six-btn-primary six-btn-sm six-respond-suggestion" data-rec-id="<?php echo $rec->id; ?>" data-response="approve" style="font-size:11px">✓ Approve</button>
                <button class="six-btn six-btn-ghost  six-btn-sm six-respond-suggestion" data-rec-id="<?php echo $rec->id; ?>" data-response="dismiss" style="font-size:11px;color:var(--text3)">Dismiss</button>
                <?php else: ?>
                <?php if($rec->action_label): ?><button class="six-btn six-btn-primary six-btn-sm six-approve-rec" data-rec-id="<?php echo $rec->id; ?>" style="font-size:11px">✓ <?php echo esc_html($rec->action_label); ?></button><?php endif; ?>
                <button class="six-btn six-btn-ghost six-btn-sm six-dismiss-rec" data-rec-id="<?php echo $rec->id; ?>" style="font-size:11px">Dismiss</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php endif; // cur_svc ?>

<?php elseif($active_tab==='messages'): ?>
<div class="six-page-header"><div><h1 class="six-page-title">Messages</h1></div></div>
<?php if($advisor): $conv=class_exists('Six_Messaging')?Six_Messaging::get_conversation($user_id,$advisor_id):array(); ?>
<div class="six-card">
    <div class="six-card-header">
        <div style="display:flex;align-items:center;gap:10px">
            <div class="six-advisor-avatar" style="width:36px;height:36px"><?php echo esc_html(six_get_initials($advisor->display_name)); ?></div>
            <div><div style="font-weight:700;font-size:14px"><?php echo esc_html($advisor->display_name); ?></div><div style="font-size:11px;color:var(--success)">● Your Advisor · 6ix Developers</div></div>
        </div>
    </div>
    <div class="six-card-body" style="padding:0">
        <div class="six-msg-thread" id="six-msg-thread" style="height:460px;overflow-y:auto;padding:16px">
            <?php if(empty($conv)): ?><div style="text-align:center;padding:40px;color:var(--text3)">No messages yet. Say hello! </div>
            <?php else: foreach($conv as $msg): $is_mine=intval($msg->sender_id)===$user_id; ?>
            <div class="six-msg <?php echo $is_mine?'mine':''; ?>">
                <div class="six-msg-avatar" style="background:<?php echo $is_mine?'linear-gradient(135deg,var(--pink),#a855f7)':'linear-gradient(135deg,var(--blue),var(--cyan))'; ?>"><?php echo esc_html(six_get_initials($msg->sender_name)); ?></div>
                <div><div class="six-msg-bubble"><?php echo esc_html($msg->message); ?></div><div class="six-msg-time"><?php echo human_time_diff(strtotime($msg->created_at),time()); ?> ago</div></div>
            </div>
            <?php endforeach; endif; ?>
        </div>
        <div class="six-msg-input-row" style="padding:12px 16px;border-top:1px solid var(--border)">
            <input class="six-msg-input" id="six-msg-input" placeholder="Type a message…" data-receiver="<?php echo $advisor_id; ?>" style="flex:1">
            <button class="six-btn six-btn-primary" id="six-msg-send" style="flex-shrink:0">Send →</button>
        </div>
    </div>
</div>
<?php else: ?><div class="six-card"><div class="six-card-body" style="text-align:center;padding:40px;color:var(--text3)">No advisor assigned yet.</div></div><?php endif; ?>

<?php elseif($active_tab==='advisor'): ?>
<div class="six-page-header"><div><h1 class="six-page-title">Your Advisor</h1></div></div>
<?php if($advisor):
$adv_bio=$advisor->description??get_user_meta($advisor_id,'description',true);
$adv_phone=get_user_meta($advisor_id,'billing_phone',true);
// Full conversation history with the advisor. get_conversation() also marks the
// advisor's messages as read, so the unread badge clears when this tab opens.
$conv = class_exists('Six_Messaging') ? Six_Messaging::get_conversation($user_id,$advisor_id) : array();
?>
<div style="display:grid;grid-template-columns:70% 30%;gap:20px;align-items:start">
    <div class="six-card">
        <div class="six-card-body" style="text-align:center;padding:32px 24px">
            <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,var(--pink),#a855f7);display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;margin:0 auto 16px;color:white"><?php echo esc_html(six_get_initials($advisor->display_name)); ?></div>
            <div style="font-size:20px;font-weight:800;font-family:'Montserrat',sans-serif;margin-bottom:4px"><?php echo esc_html($advisor->display_name); ?></div>
            <div style="font-size:13px;color:var(--pink);font-weight:600;margin-bottom:16px">Senior Marketing Advisor · 6ix Developers</div>
            <div style="background:var(--dark4);border-radius:10px;padding:14px;margin-bottom:18px;text-align:left;border-left:3px solid var(--pink)">
                <div style="font-size:12px;color:var(--text2);line-height:1.75"><?php
                    $first_name=$user->first_name?:$user->display_name;
                    echo esc_html("Hi {$first_name} — I'm personally committed to growing {$ai_business}. I review your campaigns regularly and you can reach me anytime. Your success is my priority, and I'm here every step of the way.");
                ?></div>
            </div>
            <?php if($adv_bio): ?><div style="font-size:12px;color:var(--text3);line-height:1.6;margin-bottom:16px;text-align:left"><?php echo esc_html($adv_bio); ?></div><?php endif; ?>
            <div style="display:flex;flex-direction:column;gap:8px">
                <?php if($adv_phone): ?><a href="tel:<?php echo esc_attr($adv_phone); ?>" class="six-btn six-btn-ghost six-btn-sm" style="justify-content:center;gap:7px"><?php echo class_exists('Six_Icon')?Six_Icon::get('phone','','15px'):''; ?><?php echo esc_html($adv_phone); ?></a><?php endif; ?>
            </div>
            <?php if(!empty($active_svcs)): ?>
            <div style="margin-top:18px;padding-top:14px;border-top:1px solid var(--border);text-align:left">
                <div style="font-size:10px;color:var(--text3);font-weight:600;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px">Managing for you</div>
                <?php foreach($active_svcs as $s):$sd=$svc_def[$s->service_slug]??array('icon'=>'','color'=>'var(--pink)'); ?>
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
                    <span style="font-size:12px"><?php echo $sd['icon']; ?> <?php echo esc_html($s->service_name); ?></span>
                    <span style="font-size:11px;color:var(--cyan);font-weight:600">$<?php echo number_format($s->budget,0); ?>/mo</span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="six-card">
        <div class="six-card-header" style="border-bottom:1px solid var(--border);padding-bottom:12px">
            <span class="six-card-title">Book a Strategy Meeting</span>
            <span style="font-size:11px;color:var(--success)">● Available 8am – 6:30pm</span>
        </div>
        <div class="six-card-body" style="padding:20px">
            <div style="margin-bottom:16px">
                <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--text3);display:block;margin-bottom:8px">Select a Date</label>
                <input type="date" class="six-input" id="meeting-date" min="<?php echo date('Y-m-d',strtotime('+1 day')); ?>" max="<?php echo date('Y-m-d',strtotime('+30 days')); ?>" style="font-size:13px">
            </div>
            <div id="meeting-slots-wrap" style="display:none;margin-bottom:16px">
                <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--text3);display:block;margin-bottom:8px">Available Times</label>
                <div id="meeting-slots" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(90px,1fr));gap:8px"></div>
                <div id="meeting-slots-loading" style="display:none"><div class="six-ai-loading"><span class="six-ai-spinner"></span> Loading available times…</div></div>
            </div>
            <div style="margin-bottom:16px">
                <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--text3);display:block;margin-bottom:8px">What would you like to discuss?</label>
                <textarea class="six-input" id="meeting-notes" rows="3" placeholder="e.g. Review my Google Ads performance, discuss budget changes…"></textarea>
            </div>
            <button class="six-btn six-btn-primary" id="six-book-meeting-new" disabled style="width:100%;justify-content:center;opacity:0.5">Book 30-Minute Meeting</button>
            <div id="six-meeting-result" style="margin-top:12px;font-size:13px"></div>
            <div style="margin-top:14px;padding:12px;background:var(--dark4);border-radius:8px;font-size:11px;color:var(--text3);line-height:1.6"> Meetings are 30 minutes · Google Meet link is generated automatically · Both you and your advisor receive email confirmations</div>
        </div>
    </div>
</div>

<!-- ── Interactive chat with your advisor (history + send) ─────────────── -->
<div class="six-card" style="margin-top:20px">
    <div class="six-card-header" style="border-bottom:1px solid var(--border);padding-bottom:12px">
        <div style="display:flex;align-items:center;gap:10px">
            <div class="six-advisor-avatar" style="width:34px;height:34px"><?php echo esc_html(six_get_initials($advisor->display_name)); ?></div>
            <div>
                <div style="font-weight:700;font-size:14px">Chat with <?php echo esc_html(explode(' ',$advisor->display_name)[0]); ?></div>
                <div style="font-size:11px;color:var(--success)">● Your Advisor · usually replies within a few hours</div>
            </div>
        </div>
    </div>
    <div class="six-card-body" style="padding:0">
        <div class="six-msg-thread" id="six-msg-thread" style="height:420px;overflow-y:auto;padding:16px">
            <?php if(empty($conv)): ?>
            <div style="text-align:center;padding:48px 20px;color:var(--text3);font-size:13px">No messages yet — send your advisor a note and their replies will show up here.</div>
            <?php else: foreach($conv as $msg): $is_mine=intval($msg->sender_id)===$user_id; ?>
            <div class="six-msg <?php echo $is_mine?'mine':''; ?>">
                <div class="six-msg-avatar" style="background:<?php echo $is_mine?'linear-gradient(135deg,var(--pink),#a855f7)':'linear-gradient(135deg,var(--blue),var(--cyan))'; ?>"><?php echo esc_html(six_get_initials($msg->sender_name)); ?></div>
                <div><div class="six-msg-bubble"><?php echo esc_html($msg->message); ?></div><div class="six-msg-time"><?php echo human_time_diff(strtotime($msg->created_at),time()); ?> ago</div></div>
            </div>
            <?php endforeach; endif; ?>
        </div>
        <div class="six-msg-input-row" style="display:flex;gap:8px;padding:12px 16px;border-top:1px solid var(--border)">
            <input class="six-msg-input" id="six-msg-input" placeholder="Type a message…" data-receiver="<?php echo intval($advisor_id); ?>" style="flex:1">
            <button class="six-btn six-btn-primary" id="six-msg-send" style="flex-shrink:0">Send →</button>
        </div>
    </div>
</div>
<?php else: ?><div class="six-card"><div class="six-card-body" style="text-align:center;padding:60px;color:var(--text3)"><div style="margin-bottom:12px;color:var(--text3)"><?php echo class_exists('Six_Icon')?Six_Icon::get('advisor','','40px'):''; ?></div><div style="font-size:14px;font-weight:600">No advisor assigned yet</div><div style="font-size:13px;margin-top:8px">Contact us at hello@6ixdevelopers.com to get started.</div></div></div><?php endif; ?>

<?php elseif($active_tab==='reports'): ?>
<div class="six-page-header"><div><h1 class="six-page-title">Reports</h1></div></div>
<div class="six-card"><div class="six-card-body">
<?php if(empty($reports)): ?>
<div style="text-align:center;padding:40px;color:var(--text3)"><div style="margin-bottom:12px;color:var(--text3)"><?php echo class_exists('Six_Icon')?Six_Icon::get('reports','','36px'):''; ?></div>Your advisor will upload monthly reports here once campaigns are running.</div>
<?php else: foreach($reports as $rep): ?>
<div class="six-report-row" style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--border)">
    <div style="display:flex;align-items:center;gap:12px">
        <div style="font-size:24px"></div>
        <div><div style="font-weight:600;font-size:13px"><?php echo esc_html($rep->title); ?></div><div style="font-size:11px;color:var(--text3)"><?php echo date('M j, Y',strtotime($rep->created_at)); ?></div></div>
    </div>
    <?php if($rep->file_url): ?><a href="<?php echo esc_url($rep->file_url); ?>" class="six-btn six-btn-secondary six-btn-sm" target="_blank">↓ Download</a><?php endif; ?>
</div>
<?php endforeach; endif; ?>
</div></div>

<?php elseif($active_tab==='billing'):
$has_card=get_user_meta($user_id,'six_stripe_payment_method',true);
$customer_id=get_user_meta($user_id,'six_stripe_customer_id',true);
$last4='••••';$brand='Card';$exp='';$invoices=array();
// Stripe calls hit an external API — never let a failure there take the whole
// billing page down with a WordPress "critical error".
try {
    if($has_card&&$customer_id&&class_exists('Six_Stripe')){
        $pm=Six_Stripe::get_payment_method_details($has_card);
        if(is_array($pm)&&!empty($pm['card'])){$last4=$pm['card']['last4']??'••••';$brand=ucfirst($pm['card']['brand']??'Card');$exp=sprintf('%02d/%s',intval($pm['card']['exp_month']??0),substr((string)($pm['card']['exp_year']??''),2));}
    }
    if($customer_id&&class_exists('Six_Stripe')){
        $inv=Six_Stripe::get_invoices($user_id);
        if(is_array($inv)) $invoices=$inv;
    }
} catch (\Throwable $e) { $invoices=array(); }
$active_svcs_b=array_filter((array)$services,fn($s)=>$s->status==='active'&&floatval($s->budget)>0);
$billing_total=array_sum(array_map(fn($s)=>floatval($s->budget),(array)$active_svcs_b));
$next_billing=date('M 1, Y',strtotime('first day of next month'));
?>
<div class="six-page-header"><div><h1 class="six-page-title">Billing</h1><p class="six-page-sub">Payment method and billing history</p></div></div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
    <div class="six-card">
        <div class="six-card-header" style="border-bottom:1px solid var(--border)"><span class="six-card-title">Payment Method</span></div>
        <div class="six-card-body" style="padding:20px">
            <?php if($has_card): ?>
            <div class="six-credit-card" style="margin-bottom:14px">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px"><div class="six-card-chip"></div><div style="font-size:14px;font-weight:700;color:rgba(255,255,255,0.9)"><?php echo esc_html($brand); ?></div></div>
                <div class="six-card-number">•••• •••• •••• <?php echo esc_html($last4); ?></div>
                <div class="six-card-meta">
                    <div><div class="six-card-meta-label">Card Holder</div><div class="six-card-meta-val"><?php echo esc_html($user->display_name); ?></div></div>
                    <?php if($exp): ?><div><div class="six-card-meta-label">Expires</div><div class="six-card-meta-val"><?php echo esc_html($exp); ?></div></div><?php endif; ?>
                </div>
            </div>
            <button class="six-btn six-btn-ghost six-btn-sm" id="six-update-card" style="width:100%;justify-content:center"> Update Card</button>
            <div id="six-card-update-result" style="font-size:12px;margin-top:8px"></div>
            <?php else: ?>
            <div style="text-align:center;padding:24px"><div style="margin-bottom:12px;color:var(--text3)"><?php echo class_exists('Six_Icon')?Six_Icon::get('card','','36px'):''; ?></div><div style="font-size:13px;color:var(--text2);margin-bottom:16px">No payment method saved yet.</div><button class="six-btn six-btn-primary" id="six-add-card">+ Add Payment Method</button></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="six-card">
        <div class="six-card-header" style="border-bottom:1px solid var(--border)"><span class="six-card-title">Current Plan</span></div>
        <div class="six-card-body" style="padding:20px">
            <?php if(empty($active_svcs_b)): ?>
            <div style="text-align:center;padding:20px;color:var(--text3);font-size:13px">No active services with budget set.</div>
            <?php else: ?>
            <table style="width:100%;font-size:12px;border-collapse:collapse;margin-bottom:14px">
                <thead><tr>
                    <th style="text-align:left;padding:6px 0;color:var(--text3);font-size:10px;text-transform:uppercase;letter-spacing:0.5px;border-bottom:1px solid var(--border)">Service</th>
                    <th style="text-align:right;padding:6px 0;color:var(--text3);font-size:10px;text-transform:uppercase;letter-spacing:0.5px;border-bottom:1px solid var(--border)">Monthly</th>
                </tr></thead>
                <tbody>
                <?php foreach($active_svcs_b as $s): ?>
                <tr style="border-bottom:1px solid rgba(255,255,255,0.04)">
                    <td style="padding:10px 0;color:var(--text2)"><?php echo esc_html($s->service_name); ?></td>
                    <td style="padding:10px 0;text-align:right;font-weight:700">$<?php echo number_format($s->budget,0); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div style="display:flex;justify-content:space-between;padding:12px 0 0;font-size:15px;font-weight:800;font-family:'Montserrat',sans-serif;border-top:2px solid var(--border)"><span>Total / Month</span><span style="color:var(--pink)">$<?php echo number_format($billing_total,0); ?></span></div>
            <?php endif; ?>
            <?php if($billing_total>0): ?>
            <div style="margin-top:14px;padding:12px;background:rgba(131,197,237,0.08);border:1px solid rgba(131,197,237,0.2);border-radius:8px;text-align:center">
                <div style="font-size:10px;color:var(--text3);font-weight:600;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px">Next Billing</div>
                <div style="font-size:15px;font-weight:700;color:var(--cyan)"><?php echo esc_html($next_billing); ?></div>
                <div style="font-size:20px;font-weight:800;font-family:'Montserrat',sans-serif;color:var(--pink);margin-top:4px">$<?php echo number_format($billing_total,0); ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php if(!empty($invoices)): ?>
<div class="six-card">
    <div class="six-card-header"><span class="six-card-title">Billing History</span></div>
    <div class="six-card-body" style="padding:0">
        <table class="six-table">
            <thead><tr><th>Date</th><th>Description</th><th>Amount</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach(array_slice($invoices,0,12) as $inv):
                $amt='$'.number_format(($inv['amount_paid']??0)/100,2);
                $date=date('M j, Y',$inv['created']??time());
                $status=$inv['status']??'draft';
                $sc=$status==='paid'?'var(--success)':($status==='open'?'var(--warning)':'var(--text3)');
            ?>
            <tr>
                <td style="font-size:12px;color:var(--text3)"><?php echo esc_html($date); ?></td>
                <td style="font-size:12px"><?php echo esc_html($inv['description']??($inv['number']??'Invoice')); ?></td>
                <td style="font-weight:700"><?php echo esc_html($amt); ?></td>
                <td><span style="font-size:11px;font-weight:600;color:<?php echo $sc; ?>;text-transform:capitalize"><?php echo esc_html($status); ?></span></td>
                <td><?php if(!empty($inv['hosted_invoice_url'])): ?><a href="<?php echo esc_url($inv['hosted_invoice_url']); ?>" target="_blank" class="six-btn six-btn-ghost six-btn-sm" style="font-size:11px">View</a><?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="six-card"><div class="six-card-body" style="text-align:center;padding:32px;color:var(--text3);font-size:13px">No billing history yet.</div></div>
<?php endif; ?>

<?php elseif($active_tab==='profile'): ?>
<div class="six-page-header"><div><h1 class="six-page-title">Profile</h1><p class="six-page-sub">Your information — used by AI for personalised insights</p></div></div>
<div id="profile-saved-msg" style="display:none;background:rgba(86,211,100,0.1);border:1px solid rgba(86,211,100,0.3);color:var(--success);padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">✓ Profile saved successfully.</div>
<div id="profile-error-msg" style="display:none;background:rgba(255,107,107,0.1);border:1px solid var(--danger);color:var(--danger);padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px"></div>
<div class="six-grid-2" style="margin-bottom:16px">
    <div class="six-card">
        <div class="six-card-header"><span class="six-card-title">Personal Information</span></div>
        <div class="six-card-body">
            <div class="six-form-group"><label class="six-label">First Name</label>
                <input class="six-input" id="prof-first" value="<?php echo esc_attr($user->first_name); ?>" placeholder="First name" autocomplete="given-name">
            </div>
            <div class="six-form-group"><label class="six-label">Last Name</label>
                <input class="six-input" id="prof-last" value="<?php echo esc_attr($user->last_name); ?>" placeholder="Last name" autocomplete="family-name">
            </div>
            <div class="six-form-group"><label class="six-label">Email</label>
                <input class="six-input" value="<?php echo esc_attr($user->user_email); ?>" readonly style="opacity:.6">
            </div>
            <div class="six-form-group"><label class="six-label">Phone</label>
                <input class="six-input" id="prof-phone" value="<?php echo esc_attr(get_user_meta($user_id,'billing_phone',true)); ?>" placeholder="+1 (416) 000-0000" autocomplete="tel">
            </div>
        </div>
    </div>
    <div class="six-card">
        <div class="six-card-header"><span class="six-card-title">Business Information</span></div>
        <div class="six-card-body">
            <div class="six-form-group"><label class="six-label">Business Name</label>
                <input class="six-input" id="prof-biz" value="<?php echo esc_attr($checkout->business_name??''); ?>" placeholder="Your Business Name">
            </div>
            <div class="six-form-group"><label class="six-label">Website</label>
                <input class="six-input" id="prof-website" value="<?php echo esc_attr($checkout->website??''); ?>" placeholder="https://yoursite.com">
            </div>
            <div class="six-form-group"><label class="six-label">Industry</label>
                <input class="six-input" id="prof-industry" value="<?php echo esc_attr($checkout->industry??''); ?>" placeholder="e.g. Dental, Legal, HVAC…">
            </div>
            <div class="six-form-group"><label class="six-label">Location</label>
                <input class="six-input" id="prof-location" value="<?php echo esc_attr($checkout->location??$checkout->business_address??''); ?>" placeholder="City, Province">
            </div>
            <div class="six-form-group"><label class="six-label">Monthly Budget</label>
                <input class="six-input" id="prof-budget" type="number" min="0" value="<?php echo intval($checkout->mktg_budget??0); ?>" placeholder="0">
            </div>
        </div>
    </div>
</div>

<div class="six-card" style="margin-bottom:16px">
    <div class="six-card-header"><span class="six-card-title">Competitor Websites</span><span style="font-size:11px;color:var(--text3)">Used for Competitor Intelligence</span></div>
    <div class="six-card-body">
        <?php $comp_arr=array_pad(array_filter(array_map('trim',explode(',',$checkout->competitors??''))),3,''); ?>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
            <div><label class="six-label">Competitor 1</label><input class="six-input" id="prof-comp1" value="<?php echo esc_attr($comp_arr[0]); ?>" placeholder="competitor1.com"></div>
            <div><label class="six-label">Competitor 2</label><input class="six-input" id="prof-comp2" value="<?php echo esc_attr($comp_arr[1]); ?>" placeholder="competitor2.com"></div>
            <div><label class="six-label">Competitor 3</label><input class="six-input" id="prof-comp3" value="<?php echo esc_attr($comp_arr[2]); ?>" placeholder="competitor3.com"></div>
        </div>
        <div style="font-size:11px;color:var(--text3);margin-top:8px">Our AI uses these domains to build your competitive intelligence report.</div>
    </div>
</div>
<div style="display:flex;justify-content:flex-end"><button class="six-btn six-btn-primary" id="save-profile" style="padding:12px 32px;font-size:14px">Save Profile</button></div>

<?php endif; ?>
</main>
</div>

<style>
.six-ai-badge{font-size:9px;font-weight:800;letter-spacing:1px;text-transform:uppercase;background:linear-gradient(135deg,var(--pink),#a855f7);color:white;padding:3px 8px;border-radius:100px}
.six-ai-body{font-size:13px;color:var(--text2);line-height:1.8;min-height:60px}
.six-ai-loading{display:flex;align-items:center;gap:10px;color:var(--text3);font-size:12px;padding:8px 0}
.six-ai-spinner{width:14px;height:14px;border-radius:50%;border:2px solid rgba(255,102,153,0.3);border-top-color:var(--pink);animation:aiSpin 0.8s linear infinite;flex-shrink:0}
@keyframes aiSpin{to{transform:rotate(360deg)}}
.six-opp-card{transition:transform 0.2s,box-shadow 0.2s}
.six-opp-card:hover{transform:translateY(-2px);box-shadow:0 4px 24px rgba(0,0,0,0.3)}
.meeting-slot{padding:8px 10px;border:1px solid var(--border);border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;text-align:center;transition:all 0.15s;background:var(--dark3);color:var(--text2)}
.meeting-slot:hover{border-color:var(--pink);color:var(--pink);background:rgba(255,102,153,0.06)}
.meeting-slot.selected{border-color:var(--pink);background:rgba(255,102,153,0.12);color:var(--pink)}
.meeting-slot.booked{opacity:0.3;cursor:not-allowed;text-decoration:line-through}
.six-steps-body{padding:4px 0}
#six-notif-panel{animation:slideDown 0.2s ease}
@keyframes slideDown{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}
</style>

<script>
var AJAX  = '<?php echo esc_js($ajax_url); ?>';
var NONCE = '<?php echo esc_js($nonce); ?>';
var INI   = '<?php echo esc_js($initials); ?>';
var ADV_ID= '<?php echo intval($advisor_id); ?>';

// ── Theme toggle (light/dark) ─────────────────────────────────────────────
(function(){
    var root  = document.getElementById('six-portal-root') || document.documentElement;
    var btn   = document.getElementById('six-theme-btn');
    var label = document.getElementById('six-theme-label');
    var stored = localStorage.getItem('six_theme') || 'light';

    function applyTheme(theme){
        if(theme==='dark'){
            root.setAttribute('data-theme','dark');
            if(label) label.textContent = 'Light';
        } else {
            root.removeAttribute('data-theme');
            if(label) label.textContent = 'Dark';
        }
        localStorage.setItem('six_theme', theme);
        // Reload page so chart colours rebuild correctly
        if(window.sixDashChart) {
            var dk = theme==='dark';
            var tc = dk ? 'rgba(255,255,255,0.5)' : 'rgba(0,0,0,0.65)';
            var gc = dk ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.07)';
            var lc = dk ? 'rgba(255,255,255,0.55)' : 'rgba(30,30,30,0.7)';
            window.sixDashChart.options.scales.x.ticks.color = tc;
            window.sixDashChart.options.scales.y.ticks.color = tc;
            window.sixDashChart.options.scales.y.grid.color  = gc;
            window.sixDashChart.options.plugins.legend.labels.color = lc;
            window.sixDashChart.data.datasets[0].backgroundColor = dk ? 'rgba(131,197,237,0.30)' : 'rgba(60,100,120,0.16)';
            window.sixDashChart.update();
        }
    }

    applyTheme(stored);

    if(btn){
        btn.addEventListener('click', function(){
            var current = root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
            applyTheme(current === 'dark' ? 'light' : 'dark');
        });
    }
})();
</script>

<script>
(function(){
'use strict';
function post(d){return fetch(AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(Object.assign({nonce:NONCE},d))}).then(r=>r.json());}

// ── Notifications panel ──────────────────────────────────────────────────
var notifBtn=document.getElementById('six-notif-btn');
var notifPanel=document.getElementById('six-notif-panel');
if(notifBtn&&notifPanel){
    notifBtn.addEventListener('click',function(e){e.stopPropagation();var vis=notifPanel.style.display!=='none';notifPanel.style.display=vis?'none':'block';if(!vis)post({action:'six_mark_notifications_read'});});
    document.addEventListener('click',function(e){if(notifPanel&&!notifPanel.contains(e.target)&&e.target!==notifBtn)notifPanel.style.display='none';});
}
var markAllBtn=document.getElementById('six-mark-all-read-btn');
if(markAllBtn)markAllBtn.addEventListener('click',function(){
    post({action:'six_mark_notifications_read'}).then(function(){
        document.querySelectorAll('[style*="rgba(255,102,153,0.04)"]').forEach(function(r){r.style.background='';});
        var b=document.querySelector('#six-notif-btn span[style*="border-radius:50%"]');if(b)b.remove();
    });
});

// ── Budget submit ────────────────────────────────────────────────────────
document.querySelectorAll('.six-submit-budget').forEach(function(btn){
    btn.addEventListener('click',function(){
        var svcId=this.dataset.serviceId,form=this.closest('div'),input=form.querySelector('.six-budget-input'),msgEl=form.querySelector('.six-budget-msg');
        var budget=parseFloat(input.value);
        if(!budget||budget<=0){if(msgEl)msgEl.innerHTML='<span style="color:var(--danger)">Enter a valid amount.</span>';return;}
        this.textContent='Sending…';this.disabled=true;var self=this;
        post({action:'six_request_budget_change',service_id:svcId,new_budget:budget}).then(function(res){
            if(res.success){form.style.display='none';var p=document.createElement('div');p.className='six-pending-msg';p.style.cssText='font-size:11px;padding:8px;margin-top:6px';p.textContent=' Request sent.';form.parentNode.insertBefore(p,form);}
            else{if(msgEl)msgEl.innerHTML='<span style="color:var(--danger)">'+(res.data||'Error')+'</span>';self.textContent='Request';self.disabled=false;}
        });
    });
});

// ── Request service ─────────────────────────────────────────────────────
document.querySelectorAll('.six-request-service').forEach(function(btn){
    btn.addEventListener('click',function(){
        var svc=this.dataset.service;this.textContent='Requesting…';this.disabled=true;var self=this;
        post({action:'six_request_service',service:svc}).then(function(res){
            if(res.success)self.textContent='✓ Requested',self.style.background='var(--success)';
            else{self.textContent='Request Service';self.disabled=false;}
        });
    });
});

// ── Approve/dismiss recs ─────────────────────────────────────────────────
document.querySelectorAll('.six-approve-rec').forEach(function(btn){
    btn.addEventListener('click',function(){
        var recId=this.dataset.recId;this.disabled=true;
        post({action:'six_approve_recommendation',rec_id:recId}).then(function(res){
            if(res.success){var row=document.getElementById('rec-'+recId);if(row)row.style.opacity='0.4';}
        });
    });
});
document.querySelectorAll('.six-dismiss-rec').forEach(function(btn){
    btn.addEventListener('click',function(){
        var recId=this.dataset.recId;this.disabled=true;
        post({action:'six_dismiss_recommendation',rec_id:recId}).then(function(res){
            if(res.success){var row=document.getElementById('rec-'+recId);if(row){row.style.transition='opacity 0.3s';row.style.opacity='0';setTimeout(function(){row.remove();},320);}}
        });
    });
});
document.querySelectorAll('.six-respond-suggestion').forEach(function(btn){
    btn.addEventListener('click',function(){
        var recId=this.dataset.recId,response=this.dataset.response,row=document.getElementById('rec-'+recId);
        this.textContent='…';this.disabled=true;
        post({action:'six_client_respond_suggestion',rec_id:recId,response:response}).then(function(d){
            if(d&&d.success){if(row){if(response==='approve'){row.style.borderColor='rgba(86,211,100,0.3)';var ab=row.querySelector('[data-response]');if(ab)ab.closest('div').innerHTML='<span style="font-size:12px;color:var(--success);font-weight:600">✓ Approved — advisor notified</span>';}else{row.style.transition='opacity 0.3s';row.style.opacity='0';setTimeout(function(){row.remove();},320);}}}
        });
    });
});

// ── Messages ─────────────────────────────────────────────────────────────
var msgIn=document.getElementById('six-msg-input'),msgSend=document.getElementById('six-msg-send');
if(msgSend&&msgIn){
    var sendMsg=function(){var msg=msgIn.value.trim(),receiver=msgIn.dataset.receiver;if(!msg||!receiver)return;
        post({action:'six_send_message',receiver_id:receiver,message:msg}).then(function(res){
            if(res.success){var t=document.getElementById('six-msg-thread');var d=document.createElement('div');d.className='six-msg mine';d.innerHTML='<div class="six-msg-avatar" style="background:linear-gradient(135deg,var(--pink),#a855f7)">'+INI+'</div><div><div class="six-msg-bubble">'+msg.replace(/</g,'&lt;')+'</div><div class="six-msg-time">just now</div></div>';t.appendChild(d);t.scrollTop=t.scrollHeight;msgIn.value='';}
        });
    };
    msgSend.addEventListener('click',sendMsg);
    msgIn.addEventListener('keydown',function(e){if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();sendMsg();}});
}
var msgThread=document.getElementById('six-msg-thread');
if(msgThread)msgThread.scrollTop=msgThread.scrollHeight;

// ── Meeting calendar slots ───────────────────────────────────────────────
var meetDate=document.getElementById('meeting-date');
var slotsWrap=document.getElementById('meeting-slots-wrap');
var slotsEl=document.getElementById('meeting-slots');
var slotsLoading=document.getElementById('meeting-slots-loading');
var bookBtn=document.getElementById('six-book-meeting-new');
var selectedSlot=null;
if(meetDate){
    meetDate.addEventListener('change',function(){
        if(!this.value)return;
        slotsWrap.style.display='block';slotsEl.innerHTML='';slotsLoading.style.display='block';
        selectedSlot=null;if(bookBtn){bookBtn.disabled=true;bookBtn.style.opacity='0.5';}
        // Always hide spinner — whether success, error or network failure
        function hideSlotSpinner(){
            slotsLoading.style.display='none';
        }
        function showSlotError(msg){
            hideSlotSpinner();
            slotsEl.innerHTML='<div style="color:var(--text3);font-size:12px;padding:8px">'+(msg||'Could not load slots. Try another date.')+'</div>';
        }

        // If no advisor assigned, generate fallback open slots immediately
        if(!ADV_ID || ADV_ID==='0'){
            hideSlotSpinner();
            var fb=[];
            var d=this.value;
            for(var h=8;h<=18;h++){
                var mins=[0];if(h<18)mins=[0,30];
                mins.forEach(function(m){
                    var hh=('0'+h).slice(-2),mm=('0'+m).slice(-2);
                    var el=document.createElement('div');
                    el.className='meeting-slot';
                    el.textContent=(h>12?(h-12):h)+':'+(m===0?'00':'30')+' '+(h>=12?'PM':'AM');
                    el.dataset.time=d+'T'+hh+':'+mm+':00';
                    el.addEventListener('click',function(){
                        document.querySelectorAll('.meeting-slot').forEach(function(b){b.classList.remove('selected');});
                        this.classList.add('selected');selectedSlot=this.dataset.time;
                        if(bookBtn){bookBtn.disabled=false;bookBtn.style.opacity='1';}
                    });
                    slotsEl.appendChild(el);
                });
            }
            return;
        }

        post({action:'six_get_available_slots',advisor_id:ADV_ID,date:this.value}).then(function(res){
            hideSlotSpinner();
            if(res&&res.success&&res.data&&res.data.slots&&res.data.slots.length){
                res.data.slots.forEach(function(slot){
                    var el=document.createElement('div');
                    el.className='meeting-slot'+(slot.booked?' booked':'');
                    el.textContent=slot.label;el.dataset.time=slot.time;
                    if(!slot.booked){
                        el.addEventListener('click',function(){
                            document.querySelectorAll('.meeting-slot').forEach(function(b){b.classList.remove('selected');});
                            this.classList.add('selected');selectedSlot=this.dataset.time;
                            if(bookBtn){bookBtn.disabled=false;bookBtn.style.opacity='1';}
                        });
                    }
                    slotsEl.appendChild(el);
                });
            } else {
                showSlotError('No available slots this day. Try another date.');
            }
        }).catch(function(){
            showSlotError('Could not reach calendar. Please try again.');
        });
    });
}
if(bookBtn){
    bookBtn.addEventListener('click',function(){
        if(!selectedSlot)return;
        var notes=(document.getElementById('meeting-notes')||{}).value||'';
        this.textContent='Booking…';this.disabled=true;var self=this;
        post({action:'six_book_meeting',start:selectedSlot,duration:30,notes:notes}).then(function(d){
            self.textContent='Book 30-Minute Meeting';
            var result=document.getElementById('six-meeting-result');
            if(d&&d.success){
                if(result)result.innerHTML='<div style="background:rgba(86,211,100,0.1);border:1px solid rgba(86,211,100,0.3);border-radius:8px;padding:12px;font-size:13px;color:var(--success)">✓ Meeting booked!'+(d.data&&d.data.meet_link?'<br><a href="'+d.data.meet_link+'" target="_blank" style="color:var(--cyan)">Join Google Meet →</a>':'')+'</div>';
                meetDate.value='';slotsWrap.style.display='none';selectedSlot=null;
            } else {
                self.disabled=false;self.style.opacity='1';
                if(result)result.innerHTML='<div style="color:var(--danger);font-size:12px">'+(d&&d.data?d.data:'Could not book — please try again.')+'</div>';
            }
        });
    });
}

// ── Inline "message your advisor" box ────────────────────────────────────
var advMsgBtn=document.getElementById('adv-msg-send');
if(advMsgBtn){
    advMsgBtn.addEventListener('click',function(){
        var ta=document.getElementById('adv-msg-text');
        var out=document.getElementById('adv-msg-result');
        var msg=(ta&&ta.value||'').trim();
        var to=this.getAttribute('data-advisor');
        if(!msg){if(out)out.innerHTML='<span style="color:var(--warning)">Please type a message first.</span>';return;}
        var self=this;self.disabled=true;var label=self.innerHTML;self.textContent='Sending…';
        post({action:'six_send_message',receiver_id:to,message:msg}).then(function(d){
            self.disabled=false;self.innerHTML=label;
            if(d&&d.success){
                if(ta)ta.value='';
                if(out)out.innerHTML='<span style="color:var(--success)">Message sent — your advisor will reply soon.</span>';
            } else {
                if(out)out.innerHTML='<span style="color:var(--danger)">'+((d&&d.data)||'Could not send. Please try again.')+'</span>';
            }
        }).catch(function(){
            self.disabled=false;self.innerHTML=label;
            if(out)out.innerHTML='<span style="color:var(--danger)">Network error. Please try again.</span>';
        });
    });
}

// ── Profile save ─────────────────────────────────────────────────────────
var saveBtn=document.getElementById('save-profile');
if(saveBtn){
    saveBtn.addEventListener('click',function(){
        this.textContent='Saving…';this.disabled=true;var self=this;
        var savedMsg=document.getElementById('profile-saved-msg');
        var errMsg=document.getElementById('profile-error-msg');
        if(savedMsg)savedMsg.style.display='none';if(errMsg)errMsg.style.display='none';
        post({
            action:'six_save_profile',
            first_name:      (document.getElementById('prof-first')    ||{}).value||'',
            last_name:       (document.getElementById('prof-last')     ||{}).value||'',
            phone:           (document.getElementById('prof-phone')    ||{}).value||'',
            business_name:   (document.getElementById('prof-biz')      ||{}).value||'',
            website:         (document.getElementById('prof-website')  ||{}).value||'',
            industry:        (document.getElementById('prof-industry') ||{}).value||'',
            location:        (document.getElementById('prof-location') ||{}).value||'',
            mktg_budget:     (document.getElementById('prof-budget')   ||{}).value||'',
            competitors:     [(document.getElementById('prof-comp1')||{}).value||'',
                              (document.getElementById('prof-comp2')||{}).value||'',
                              (document.getElementById('prof-comp3')||{}).value||''].filter(Boolean).join(','),
        }).then(function(res){
            self.textContent='Save Profile';self.disabled=false;
            if(res.success){if(savedMsg){savedMsg.style.display='block';setTimeout(function(){savedMsg.style.display='none';},4000);}}
            else{if(errMsg){errMsg.textContent='Error: '+(res.data||'Could not save.');errMsg.style.display='block';}}
        });
    });
}

// ── Billing: update/add card ─────────────────────────────────────────────
function handleCardBtn(id){
    var btn=document.getElementById(id);if(!btn)return;
    btn.addEventListener('click',function(){
        var result=document.getElementById('six-card-update-result');
        btn.textContent='Loading…';btn.disabled=true;
        post({action:'six_get_setup_intent'}).then(function(d){
            btn.textContent=id==='six-update-card'?' Update Card':'+ Add Payment Method';btn.disabled=false;
            if(d&&d.success&&d.data&&d.data.url)window.location.href=d.data.url;
            else if(result)result.innerHTML='<span style="color:var(--danger)">Could not open payment page. Contact your advisor.</span>';
        });
    });
}
handleCardBtn('six-update-card');handleCardBtn('six-add-card');

})();
</script>

<script>
(function(){
'use strict';
function post(d){return fetch(AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(Object.assign({nonce:NONCE},d))}).then(r=>r.json());}

// ── fmtAI ───────────────────────────────────────────────────────────────
function fmtAI(text){
    if(!text)return'';
    // Strip markdown headings (# ## ###) at start of lines
    text=text.replace(/^#{1,3}\s+(.+)$/gm,'<strong>$1</strong>');
    // Arrow bullets with bold
    text=text.replace(/[→\-\*]+\s*\*\*(.*?)\*\*:?\s*/g,'<li><strong>$1</strong>: ');
    text=text.replace(/[→]+\s*/g,'<li>');
    if(text.indexOf('<li>')!==-1){text='<ul style="padding-left:18px;margin:6px 0;line-height:1.85">'+text+'</ul>';text=text.replace(/<li>([\s\S]*?)(?=<li>|<\/ul>)/g,'<li>$1</li>');}
    text=text.replace(/\*\*(.*?)\*\*/g,'<strong style="color:var(--text1)">$1</strong>');
    text=text.replace(/\n\n/g,'</p><p style="margin:10px 0 0">').replace(/\n/g,'<br>');
    return'<p style="margin:0;line-height:1.75">'+text+'</p>';
}

// ── Parse structured steps (shared by roadmap and actions) ──────────────
function parseSteps(text){
    var lines=text.split('\n').map(function(l){return l.trim();}).filter(Boolean);
    var steps=[],cur=null;
    lines.forEach(function(line){
        var m=line.match(/^(PHASE|ACTION)\s*(\d+)[:\s|]+(.+)/i);
        if(m){
            if(cur)steps.push(cur);
            cur={num:parseInt(m[2]),label:m[1].toUpperCase(),title:'',focus:'',service:'',outcome:''};
            var parts=m[3].split('|').map(function(p){return p.trim();});
            cur.title=parts[0]||'';
            parts.slice(1).forEach(function(p){
                if(/^(add|service):/i.test(p))cur.service=p.replace(/^(Add|Service):\s*/i,'');
                else if(/^(outcome|impact|expected):/i.test(p))cur.outcome=p.replace(/^(Outcome|Impact|Expected):\s*/i,'');
                else if(!cur.focus)cur.focus=p;
            });
        } else if(cur&&line){
            // Continuation line — append to focus or outcome
            if(!cur.focus)cur.focus=line;
            else cur.focus+=' '+line;
        }
    });
    if(cur)steps.push(cur);
    return steps;
}

function getSvcColor(service){
    var map={'Google Ads':'#4285F4','SEO':'#56D364','':'#FF6699','Brand':'#E3B341','Website':'#83C5ED'};
    var col='#a855f7';
    Object.keys(map).forEach(function(k){if(service&&service.indexOf(k)!==-1)col=map[k];});
    return col;
}

// ── fmtRoadmap — visual timeline for 6-month plan ────────────────────────
function fmtRoadmap(text){
    if(!text)return fmtAI(text);
    var steps=parseSteps(text);
    if(!steps.length)return fmtAI(text);
    // Read CSS variables for theme-awareness
    var cs=getComputedStyle(document.documentElement);
    var cardBg  = cs.getPropertyValue('--dark2').trim()||'#fff';
    var cardBd  = cs.getPropertyValue('--border').trim()||'#e2e6ed';
    var t1      = cs.getPropertyValue('--text1').trim()||'#0F1923';
    var t2      = cs.getPropertyValue('--text2').trim()||'#4A5568';
    var phLabels=['Month 1–2','Month 3–4','Month 5–6'];
    var phColors=['#4285F4','#a855f7','#83C5ED'];
    var succCol = cs.getPropertyValue('--success').trim()||'#1B9E52';
    var html='<div style="display:flex;flex-direction:column;gap:0;padding:4px 0">';
    steps.forEach(function(s,i){
        var col=phColors[i%phColors.length];
        var sCol=getSvcColor(s.service);
        var label=phLabels[i]||('Phase '+(i+1));
        var isLast=i===steps.length-1;
        html+='<div style="display:flex;gap:0;align-items:stretch">';
        // Timeline column
        html+='<div style="display:flex;flex-direction:column;align-items:center;width:40px;flex-shrink:0">';
        html+='<div style="width:32px;height:32px;border-radius:50%;background:'+col+';display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;color:white;flex-shrink:0;z-index:1">'+(i+1)+'</div>';
        if(!isLast)html+='<div style="width:2px;flex:1;background:linear-gradient(180deg,'+col+','+phColors[(i+1)%phColors.length]+');margin:4px 0;min-height:20px;opacity:0.5"></div>';
        html+='</div>';
        // Content card — uses resolved CSS variables
        html+='<div style="flex:1;min-width:0;margin-left:12px;padding-bottom:'+(isLast?'4px':'20px')+'">';
        html+='<div style="background:'+cardBg+';border:1px solid '+cardBd+';border-radius:12px;padding:14px 16px;position:relative;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,0.06)">';
        html+='<div style="position:absolute;top:0;left:0;right:0;height:3px;background:'+col+';border-radius:12px 12px 0 0"></div>';
        html+='<div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:'+col+';margin-bottom:5px">'+label+'</div>';
        html+='<div style="font-size:13px;font-weight:700;color:'+t1+';margin-bottom:6px;line-height:1.4">'+s.title.replace(/</g,'&lt;').replace(/\|.*/,'').trim()+'</div>';
        if(s.focus){
            html+='<div style="font-size:12px;color:'+t2+';line-height:1.65;margin-bottom:8px">'+s.focus.replace(/</g,'&lt;')+'</div>';
        }
        var tags=[];
        if(s.service)tags.push('<span style="display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:600;background:'+sCol+'22;color:'+sCol+';padding:3px 8px;border-radius:6px;border:1px solid '+sCol+'44">&#128279; '+s.service.replace(/</g,'&lt;')+'</span>');
        if(s.outcome)tags.push('<span style="display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:600;color:'+succCol+';background:rgba(27,158,82,0.08);padding:3px 8px;border-radius:6px;border:1px solid rgba(27,158,82,0.2)">&#10024; '+s.outcome.replace(/</g,'&lt;')+'</span>');
        if(tags.length)html+='<div style="display:flex;gap:6px;flex-wrap:wrap">'+tags.join('')+'</div>';
        html+='</div></div></div>';
    });
    return html+'</div>';
}

// ── fmtActions — weekly cards for 30-day plan ─────────────────────────────
function fmtActions(text){
    if(!text)return fmtAI(text);
    var steps=parseSteps(text);
    if(!steps.length)return fmtAI(text);
    var weekLabels=['Week 1','Week 2','Week 3','Week 4'];
    var weekColors=['#FF6699','#E3B341','#83C5ED','#56D364'];
    var html='<div style="display:flex;flex-direction:column;gap:10px;padding:4px 0">';
    var cs2=getComputedStyle(document.documentElement);
    var cb2=cs2.getPropertyValue('--dark2').trim()||'#fff';
    var cd2=cs2.getPropertyValue('--border').trim()||'#e2e6ed';
    var t1b=cs2.getPropertyValue('--text1').trim()||'#0F1923';
    var t2b=cs2.getPropertyValue('--text2').trim()||'#4A5568';
    var sc2=cs2.getPropertyValue('--success').trim()||'#1B9E52';
    steps.forEach(function(s,i){
        var col=weekColors[i%weekColors.length];
        var sCol=getSvcColor(s.service);
        var wLabel=weekLabels[i]||('Week '+(i+1));
        html+='<div style="background:'+cb2+';border:1px solid '+cd2+';border-radius:12px;padding:14px 16px;position:relative;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,0.06)">';
        html+='<div style="position:absolute;left:0;top:0;bottom:0;width:3px;background:'+col+';border-radius:12px 0 0 12px"></div>';
        html+='<div style="padding-left:8px">';
        html+='<div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:'+col+';margin-bottom:5px">'+wLabel+'</div>';
        html+='<div style="font-size:13px;font-weight:700;color:'+t1b+';margin-bottom:6px;line-height:1.4">'+s.title.replace(/</g,'&lt;')+'</div>';
        if(s.focus){
            html+='<div style="font-size:12px;color:'+t2b+';line-height:1.65;margin-bottom:8px">'+s.focus.replace(/</g,'&lt;')+'</div>';
        }
        var tags=[];
        if(s.service)tags.push('<span style="display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:600;background:'+sCol+'22;color:'+sCol+';padding:3px 8px;border-radius:6px;border:1px solid '+sCol+'44">&#128279; '+s.service.replace(/</g,'&lt;')+'</span>');
        if(s.outcome)tags.push('<span style="display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:600;color:'+sc2+';background:rgba(27,158,82,0.08);padding:3px 8px;border-radius:6px;border:1px solid rgba(27,158,82,0.2)">&#10024; '+s.outcome.replace(/</g,'&lt;')+'</span>');
        if(tags.length)html+='<div style="display:flex;gap:6px;flex-wrap:wrap">'+tags.join('')+'</div>';
        html+='</div></div>';
    });
    return html+'</div>';
}

// ── fmtSteps — kept for backward compat (other service tabs) ─────────────
function fmtSteps(text){ return fmtRoadmap(text); }

// ── AI Queue ─────────────────────────────────────────────────────────────
var Q=[],running=false;
function enqueue(el){
    var prompt=el.getAttribute('data-prompt');
    if(!prompt||el.getAttribute('data-loaded')==='1')return;
    el.setAttribute('data-loaded','1');
    var isSteps=el.classList.contains('six-steps-body');
    Q.push({el:el,prompt:prompt,isSteps:isSteps});
    if(!running)drain();
}
function drain(){
    if(!Q.length){running=false;return;}
    running=true;var item=Q.shift();
    var loader='<div class="six-ai-loading"><span class="six-ai-spinner"></span> <span style="font-size:12px;color:var(--text3)">Generating…</span></div>';
    item.el.innerHTML=loader;
    fetch(AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({action:'six_ai_insight',nonce:NONCE,prompt:item.prompt})})
    .then(function(r){return r.json();})
    .then(function(d){
        if(d&&d.success&&d.data&&d.data.text){
            item.el.setAttribute('data-full-text',d.data.text);
            var cacheKey=item.el.getAttribute('data-cache');
            var rendered;
            if(cacheKey==='action')rendered=fmtActions(d.data.text);
            else if(item.isSteps||cacheKey==='roadmap')rendered=fmtRoadmap(d.data.text);
            else rendered=fmtAI(d.data.text);
            item.el.innerHTML=rendered;
            // show action buttons
            var actId=item.el.id?item.el.id.replace('-body','-action'):null;
            if(actId){var a=document.getElementById(actId);if(a)a.style.display='block';}
        } else {
            item.el.innerHTML='<div style="color:var(--text3);font-size:12px;padding:4px 0">\u26a0 '+(d&&typeof d.data==='string'?d.data:'Could not generate.')+'</div>';
            item.el.setAttribute('data-loaded','0');
        }
    })
    .catch(function(){item.el.innerHTML=loader;item.el.setAttribute('data-loaded','0');})
    .finally(function(){setTimeout(drain,800);});
}

// ── Generate All Insights button ─────────────────────────────────────────
// (Generate All Insights removed — insights load per-card via See Insight)

// ── Render cached AI content on page load ────────────────────────────────
document.querySelectorAll('.six-ai-cached-content').forEach(function(el){
    var text=el.getAttribute('data-text');
    var parent=el.parentElement;
    if(text&&parent){
        var cacheKey=parent.getAttribute('data-cache');
        var rendered;
        if(cacheKey==='action')rendered=fmtActions(text);
        else if(parent.classList.contains('six-steps-body')||cacheKey==='roadmap')rendered=fmtRoadmap(text);
        else rendered=fmtAI(text);
        parent.innerHTML=rendered;
        // Show action button
        var actId=parent.id?parent.id.replace('-body','-action'):null;
        if(actId){var a=document.getElementById(actId);if(a)a.style.display='block';}
    }
});

// ── Growth generate button ───────────────────────────────────────────────
var growthBtn=document.getElementById('six-gen-growth-btn');
if(growthBtn){
    growthBtn.addEventListener('click',function(){
        var btn=this;btn.textContent='\u23f3 Generating\u2026';btn.disabled=true;
        var els=['roadmap-body','action-plan-body'];
        els.forEach(function(id){var el=document.getElementById(id);if(el){el.setAttribute('data-loaded','0');enqueue(el);}});
        // After generation, save to server cache
        var interval=setInterval(function(){
            var allDone=els.every(function(id){var el=document.getElementById(id);return el&&el.getAttribute('data-loaded')==='1';});
            if(allDone){
                clearInterval(interval);
                btn.textContent='\u2728 Refresh';btn.disabled=false;
                // Cache the text
                var roadmapEl=document.getElementById('roadmap-body');
                var actionEl =document.getElementById('action-plan-body');
                var roadmapText=roadmapEl?roadmapEl.getAttribute('data-full-text')||roadmapEl.innerText.trim():'';
                var actionText =actionEl ?actionEl.getAttribute('data-full-text') ||actionEl.innerText.trim() :'';
                fetch(AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
                    body:new URLSearchParams({action:'six_cache_overview_ai',nonce:NONCE,roadmap:roadmapText,action_plan:actionText})})
                .catch(function(){});
                // Show cached badge
                var badge=document.querySelector('[style*="Up to date"]');
                if(!badge){var d=document.createElement('span');d.style.cssText='font-size:11px;color:var(--success);background:rgba(86,211,100,0.1);padding:4px 10px;border-radius:20px';d.textContent='\u2713 Up to date';btn.parentNode.insertBefore(d,btn);}
            }
        },600);
        setTimeout(function(){clearInterval(interval);if(btn.disabled){btn.textContent='\u2728 Refresh';btn.disabled=false;}},15000);
    });
}

// ── Auto-load service AI on service tabs ─────────────────────────────────
var tab=new URLSearchParams(window.location.search).get('tab')||'';
if(tab.indexOf('svc_')===0){
    document.querySelectorAll('.six-ai-body[data-prompt],[class*="six-ai-body"]').forEach(function(el){
        if(el.getAttribute('data-prompt'))enqueue(el);
    });
    // Also load upsell body
    var slug=tab.replace('svc_','');
    var upsellEl=document.getElementById('svc-upsell-body-'+slug);
    if(upsellEl&&upsellEl.getAttribute('data-prompt'))enqueue(upsellEl);
}

// ── Opportunity: preview insight ─────────────────────────────────────────
function attachPreviewListeners(){
    document.querySelectorAll('.six-preview-opp').forEach(function(btn){
        if(btn._prev)return;btn._prev=true;
        btn.addEventListener('click',function(){
            var cardId=this.dataset.card,content=document.getElementById('opp-content-'+cardId);
            if(!content)return;
            content.setAttribute('data-loaded','0');
            enqueue(content);
            var self=this;self.textContent='Loading\u2026';self.disabled=true;
            var check=setInterval(function(){
                if(content.getAttribute('data-loaded')==='1'){
                    clearInterval(check);
                    var actions=document.getElementById('opp-actions-'+cardId);
                    if(actions){
                        var type=content.getAttribute('data-type');
                        var titleEl=content.closest('.six-card').querySelector('[style*="font-weight:700"]');
                        var title=titleEl?titleEl.textContent.trim():type;
                        actions.innerHTML='<button class="six-btn six-btn-primary six-btn-sm six-request-opp" data-type="'+type+'" data-title="'+title.replace(/"/g,'')+'" data-card="'+cardId+'" style="font-size:12px">+ Add to Action Plan</button>';
                        attachRequestListeners();
                    }
                }
            },400);
        });
    });
}

// ── Opportunity: request ─────────────────────────────────────────────────
function attachRequestListeners(){
    document.querySelectorAll('.six-request-opp').forEach(function(btn){
        if(btn._opp)return;btn._opp=true;
        btn.addEventListener('click',function(){
            var type=this.dataset.type,title=this.dataset.title,card=this.dataset.card;
            var cEl=document.getElementById('opp-content-'+card)||document.getElementById(card+'-body');
            var desc=cEl?(cEl.getAttribute('data-full')||cEl.innerText.trim()):title;
            this.textContent='Sending\u2026';this.disabled=true;var self=this;
            fetch(AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:new URLSearchParams({action:'six_request_opportunity',nonce:NONCE,type:type,title:title,description:desc})})
            .then(function(r){return r.json();})
            .then(function(d){
                if(d&&d.success){
                    var actions=document.getElementById('opp-actions-'+card);
                    if(actions)actions.innerHTML='<div style="display:flex;align-items:center;justify-content:space-between"><span style="font-size:12px;color:var(--warning)">\u23f3 Advisor reviewing</span>'+(d.data&&d.data.rec_id?'<button class="six-btn six-btn-ghost six-btn-sm six-dismiss-opp" data-rec-id="'+d.data.rec_id+'" data-card="'+card+'" style="font-size:11px;color:var(--text3)">Remove</button>':'')+'</div>';
                    attachDismissListeners();
                } else {
                    self.textContent='+ Add to Action Plan';self.disabled=false;
                    alert(d&&d.data?d.data:'Error — please try again.');
                }
            })
            .catch(function(){self.textContent='+ Add to Action Plan';self.disabled=false;});
        });
    });
}

// ── Opportunity: dismiss ─────────────────────────────────────────────────
function attachDismissListeners(){
    document.querySelectorAll('.six-dismiss-opp').forEach(function(btn){
        if(btn._opp)return;btn._opp=true;
        btn.addEventListener('click',function(){
            var recId=this.dataset.recId,card=this.dataset.card;
            this.textContent='…';this.disabled=true;
            fetch(AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:new URLSearchParams({action:'six_dismiss_recommendation',nonce:NONCE,rec_id:recId})})
            .then(function(r){return r.json();})
            .then(function(){
                var actions=document.getElementById('opp-actions-'+card);
                if(actions)actions.innerHTML='<span style="font-size:11px;color:var(--text3)">Dismissed</span>';
                var badge=document.querySelector('#opp-card-'+card+' .six-opp-badge');if(badge)badge.remove();
            });
        });
    });
}

attachPreviewListeners();
attachRequestListeners();
attachDismissListeners();

// ── Competitor Analysis ──────────────────────────────────────────────────
var compBtn=document.getElementById('six-run-competitor-analysis');
if(compBtn){
    compBtn.addEventListener('click',function(){
        var ctxEl=document.getElementById('comp-ctx');
        if(!ctxEl){alert('Please complete your profile first.');return;}
        var ctx=ctxEl.dataset.value||'';
        document.getElementById('competitor-results').style.display='block';
        compBtn.textContent='\u23f3 Analysing\u2026';compBtn.disabled=true;
        var panels=[
            {id:'ai-comp-landscape-body',prompt:'You are a 6ix Developers competitive analyst. '+ctx+' In 4 bullet points, identify 4 competitors or competitor types. For each: what they do better AND which 6ix service closes that gap. Use \u2192 bullets. Max 100 words. Be specific to the industry.'},
            {id:'ai-comp-gaps-body',prompt:'You are a 6ix Developers market analyst. '+ctx+' Identify 3 specific keyword or traffic gaps where competitors are winning. For each: name it, quantify the loss, name the 6ix service that fixes it. Use \u2192 bolded gap name. Max 90 words.'},
            {id:'ai-comp-keywords-body',prompt:'You are a 6ix Developers SEO strategist. '+ctx+' List 8 specific commercial-intent keywords competitors rank for that this business is missing. Format: \u2192 [keyword] \u2014 why this drives revenue. End: Our SEO service targets these directly. Max 90 words.'},
            {id:'ai-comp-positioning-body',prompt:'You are a 6ix Developers brand strategist. '+ctx+' Give: 1 sharp positioning statement to beat these competitors, 2 unique differentiators they lack, 1 messaging angle to use immediately. Tie each to a 6ix service. Use \u2192 bullets. Max 90 words.'},
            {id:'ai-comp-winplan-body',prompt:'You are a 6ix Developers growth strategist. '+ctx+' Give 4 urgent actions to overtake competitors this quarter. Each: name the tactic, which 6ix service enables it, measurable 90-day outcome. Use \u2192 bolded action. Max 110 words.'}];
        var remaining=panels.length;
        function done(){remaining--;if(remaining<=0){compBtn.textContent='\uD83C\uDFAF Run Analysis';compBtn.disabled=false;}}
        panels.forEach(function(p,i){
            setTimeout(function(){
                var el=document.getElementById(p.id);if(!el){done();return;}
                el.innerHTML='<div class="six-ai-loading"><span class="six-ai-spinner"></span> Generating\u2026</div>';
                fetch(AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
                    body:new URLSearchParams({action:'six_ai_insight',nonce:NONCE,prompt:p.prompt})})
                .then(function(r){return r.json();})
                .then(function(d){el.innerHTML=(d&&d.success&&d.data&&d.data.text)?fmtAI(d.data.text):'<span style="color:var(--text3);font-size:12px">Could not generate.</span>';done();})
                .catch(function(){el.innerHTML='<span style="color:var(--text3);font-size:12px">Network error.</span>';done();});
            },i*900);
        });
    });
}

})();
</script>

<script>
// Desktop sidebar collapse — default open, remembered per browser.
(function(){
  var cb=document.getElementById('six-sidebar-collapse');
  if(!cb)return;
  if(localStorage.getItem('six_nav_collapsed')==='1') document.body.classList.add('six-nav-collapsed');
  cb.addEventListener('click',function(){
    var c=document.body.classList.toggle('six-nav-collapsed');
    localStorage.setItem('six_nav_collapsed', c?'1':'0');
  });
})();
(function(){
  var btn=document.getElementById('six-menu-toggle');
  var sidebar=document.querySelector('.six-sidebar');
  var overlay=document.getElementById('six-overlay');
  if(!btn||!sidebar)return;
  function openMenu(){
    sidebar.style.display='block';
    sidebar.getBoundingClientRect(); // force reflow so transition fires
    sidebar.classList.add('open');
    if(overlay){ overlay.style.display='block'; overlay.classList.add('open'); }
    document.body.style.overflow='hidden';
  }
  function closeMenu(){
    sidebar.classList.remove('open');
    if(overlay){ overlay.style.display='none'; overlay.classList.remove('open'); }
    document.body.style.overflow='';
  }
  btn.addEventListener('click',function(){sidebar.classList.contains('open')?closeMenu():openMenu();});
  if(overlay)overlay.addEventListener('click',closeMenu);
  sidebar.querySelectorAll('.six-nav-item').forEach(function(a){a.addEventListener('click',function(){if(window.innerWidth<=768)closeMenu();});});
// ── Bottom nav active state ───────────────────────────────────────────────
function setBnavActive(el) {
  document.querySelectorAll('.six-bnav-item').forEach(function(b){
    b.classList.remove('active');
  });
  el.classList.add('active');
}
// Sync bottom nav with sidebar on page load
(function(){
  var currentTab = new URLSearchParams(window.location.search).get('tab') || 'overview';
  document.querySelectorAll('.six-bnav-item').forEach(function(b){
    var href = b.getAttribute('href') || '';
    if(href.includes('tab='+currentTab)) b.classList.add('active');
  });
})();
})();
</script>
