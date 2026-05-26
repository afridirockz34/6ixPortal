<?php
/**
 * Advisor Dashboard v3
 * Upload to: /wp-content/themes/6ixClaude/portal/templates/advisor-dashboard.php
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! Six_Roles::is_advisor() ) { wp_redirect( home_url( '/portal/' ) ); exit; }

$advisor_id = get_current_user_id();
$advisor    = wp_get_current_user();
$initials   = six_get_initials( $advisor->display_name );
$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'overview';

// ── Handle Calendar OAuth actions BEFORE any HTML output ─────────────────────
if ( $active_tab === 'calendar' ) {
    if ( isset( $_GET['gcal_disconnect'] ) ) {
        delete_user_meta( $advisor_id, 'six_gcal_refresh_token' );
        delete_user_meta( $advisor_id, 'six_gcal_access_token' );
        delete_user_meta( $advisor_id, 'six_gcal_token_expires' );
        delete_user_meta( $advisor_id, 'six_gcal_email' );
        wp_redirect( home_url( '/advisor-portal/?tab=calendar' ) ); exit;
    }
    if ( isset( $_GET['gcal_auth'] ) ) {
        $gcal_client_id = get_option( 'six_google_client_id' );
        if ( $gcal_client_id ) {
            $gcal_state = base64_encode( json_encode( array(
                'advisor_id' => $advisor_id,
                'nonce'      => wp_create_nonce( 'six_gcal_' . $advisor_id )) ) );
            $gcal_auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query( array(
                'client_id'     => $gcal_client_id,
                'redirect_uri'  => home_url( '/advisor-portal/gcal/' ),
                'response_type' => 'code',
                'scope'         => 'https://www.googleapis.com/auth/calendar.readonly https://www.googleapis.com/auth/userinfo.email',
                'access_type'   => 'offline',
                'prompt'        => 'consent',
                'state'         => $gcal_state) );
            wp_redirect( $gcal_auth_url ); exit;
        }
    }
}
$nonce      = wp_create_nonce( 'six_nonce' );
$ajax_url   = admin_url( 'admin-ajax.php' );

global $wpdb;

// ── Clients: server-side search + pagination ──────────────────────────────
$client_search   = sanitize_text_field( $_GET['csearch'] ?? '' );
$client_page     = max(1, intval( $_GET['cpage'] ?? 1 ));
$clients_per_page = 15;
$clients_offset  = ($client_page - 1) * $clients_per_page;

$where_search = '';
$search_params = array($advisor_id);
if ($client_search !== '') {
    $like = '%' . $wpdb->esc_like($client_search) . '%';
    $where_search = " AND (u.display_name LIKE %s OR u.user_email LIKE %s OR
                     EXISTS(SELECT 1 FROM {$wpdb->prefix}usermeta um
                            WHERE um.user_id=u.ID AND um.meta_key='billing_phone'
                            AND um.meta_value LIKE %s))";
    $search_params[] = $like;
    $search_params[] = $like;
    $search_params[] = $like;
}
$search_params_count = array_merge($search_params, array($clients_per_page, $clients_offset));

$clients_total = intval($wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}six_assignments a
     INNER JOIN {$wpdb->prefix}users u ON a.client_id=u.ID
     WHERE a.advisor_id=%d{$where_search}",
    ...$search_params
)));

$clients = $wpdb->get_results($wpdb->prepare(
    "SELECT u.ID, u.display_name, u.user_email FROM {$wpdb->prefix}six_assignments a
     INNER JOIN {$wpdb->prefix}users u ON a.client_id=u.ID
     WHERE a.advisor_id=%d{$where_search}
     ORDER BY u.display_name ASC LIMIT %d OFFSET %d",
    ...$search_params_count
));
$clients_total_pages = max(1, ceil($clients_total / $clients_per_page));

// Keep a full list (IDs only) for other queries that need all clients
$all_client_ids = $wpdb->get_col($wpdb->prepare(
    "SELECT a.client_id FROM {$wpdb->prefix}six_assignments a WHERE a.advisor_id=%d",
    $advisor_id
));

$total_mrr = 0;
foreach ( $clients as $c ) {
    $total_mrr += floatval( $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(budget),0) FROM {$wpdb->prefix}six_client_services WHERE client_id=%d AND status='active'", $c->ID
    ) ) );
}

$notifs     = class_exists('Six_Notifications') ? Six_Notifications::get_for_user( $advisor_id, 30 ) : array();
$unread_n   = class_exists('Six_Notifications') ? Six_Notifications::get_unread_count( $advisor_id ) : 0;
$unread_msg = class_exists('Six_Messaging')     ? Six_Messaging::get_unread_count( $advisor_id )    : 0;

$pending_svcs = $wpdb->get_results( $wpdb->prepare(
    "SELECT cs.*, u.display_name AS client_name FROM {$wpdb->prefix}six_client_services cs
     INNER JOIN {$wpdb->prefix}six_assignments a ON cs.client_id=a.client_id AND a.advisor_id=%d
     INNER JOIN {$wpdb->prefix}users u ON cs.client_id=u.ID
     WHERE cs.status='pending' ORDER BY cs.id DESC", $advisor_id
) );

// Budget change requests pending for my clients
$budget_requests = array();
foreach ( $clients as $c ) {
    $c_svcs = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}six_client_services WHERE client_id=%d AND status='active'", $c->ID
    ) );
    foreach ( $c_svcs as $svc ) {
        $req = get_user_meta( $c->ID, 'six_budget_req_' . $svc->id, true );
        if ( $req && ( $req['status'] ?? '' ) === 'pending' ) {
            $budget_requests[] = array_merge( (array) $svc, array(
                'client_id'        => $c->ID,
                'client_name'      => $c->display_name,
                'client_email'     => $c->user_email,
                'requested_budget' => $req['requested_budget'],
                'requested_at'     => $req['requested_at']) );
        }
    }
}
$total_pending = count( $pending_svcs ) + count( $budget_requests );

$view_client_id = isset( $_GET['client'] ) ? intval( $_GET['client'] ) : 0;
$view_client    = $view_client_id ? get_userdata( $view_client_id ) : null;

$mcc_configured = ! empty( get_option('six_gads_refresh_token') ) && ! empty( get_option('six_gads_developer_token') );
?>
<div class="six-topbar">
    <!-- Hamburger — always first on mobile -->
    <button class="six-mobile-menu-btn" id="six-menu-toggle" aria-label="Open menu">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
        </svg>
    </button>
    <!-- Logo — compact -->
    <div class="six-logo">6ix Developers</div>
    <!-- Role badge hidden on mobile via CSS -->
    <div class="six-role-badge advisor">Advisor</div>
    <!-- Right controls -->
    <div class="six-topbar-right">
        <!-- Theme toggle — track hidden on mobile via CSS, shows icon only -->
        <button class="six-theme-toggle" id="six-theme-btn" title="Toggle light/dark">
            <div class="toggle-track"><div class="toggle-thumb"></div></div>
            <span id="six-theme-label">Dark</span>
        </button>
        <!-- Notification bell -->
        <a href="?tab=notifications" class="six-notif-bell" style="text-decoration:none;color:inherit;position:relative;display:flex;align-items:center;padding:5px">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/>
            </svg>
            <?php if($unread_n>0):?><span class="six-badge" style="position:absolute;top:-2px;right:-2px;min-width:16px;height:16px;padding:0 3px;font-size:9px"><?php echo min($unread_n,99);?></span><?php endif;?>
        </a>
        <!-- Avatar — initials only, no name -->
        <div class="six-avatar" title="<?php echo esc_attr($advisor->display_name);?>"><?php echo esc_html($initials);?></div>
    </div>
</div>

<div id="six-overlay" style="display:none;position:fixed;inset:0;background:rgba(15,25,35,0.5);z-index:399;cursor:pointer" onclick="document.querySelector('.six-sidebar').classList.remove('open');this.style.display='none';document.body.style.overflow=''"></div>
<nav class="six-bottom-nav" id="six-bottom-nav">
  <a class="six-bnav-item <?php echo $active_tab==='overview'?'active':''; ?>" href="?tab=overview">
    <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
    <span>Overview</span>
  </a>
  <a class="six-bnav-item <?php echo $active_tab==='clients'?'active':''; ?>" href="?tab=clients">
    <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
    <span>Clients</span>
  </a>
  <a class="six-bnav-item <?php echo $active_tab==='messages'?'active':''; ?>" href="?tab=messages">
    <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
    <span>Messages</span>
  </a>
  <a class="six-bnav-item <?php echo $active_tab==='intelligence'?'active':''; ?>" href="?tab=intelligence">
    <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
    <span>Insights</span>
  </a>
  <a class="six-bnav-item <?php echo $active_tab==='calendar'?'active':''; ?>" href="?tab=calendar">
    <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
    <span>Calendar</span>
  </a>
</nav>
<div class="six-layout">
    <nav class="six-sidebar">
        <div class="six-nav-section">
            <div class="six-nav-label">Mission Control</div>
            <a href="?tab=overview"      class="six-nav-item <?php echo $active_tab==='overview'     ?'active':'';?>"><span class="six-nav-icon">⬡</span> Overview</a>
            <a href="?tab=clients"       class="six-nav-item <?php echo $active_tab==='clients'      ?'active':'';?>"><span class="six-nav-icon">◈</span> Clients</a>
            <a href="?tab=messages"      class="six-nav-item <?php echo $active_tab==='messages'     ?'active':'';?>">
                <span class="six-nav-icon">◻</span> Messages
                <?php if($unread_msg>0):?><span class="six-badge"><?php echo $unread_msg;?></span><?php endif;?>
            </a>
            <a href="?tab=notifications" class="six-nav-item <?php echo $active_tab==='notifications'?'active':'';?>">
                <span class="six-nav-icon">◎</span> Alerts
                <?php if($unread_n>0):?><span class="six-badge"><?php echo $unread_n;?></span><?php endif;?>
            </a>
            <a href="?tab=approvals"     class="six-nav-item <?php echo $active_tab==='approvals'   ?'active':'';?>">
                <span class="six-nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg></span> Approvals
                <?php if($total_pending>0):?><span class="six-badge"><?php echo $total_pending;?></span><?php endif;?>
            </a>
        </div>
        <div class="six-nav-section">
            <div class="six-nav-label">Management</div>
            
            <a href="?tab=revenue"  class="six-nav-item <?php echo $active_tab==='revenue' ?'active':'';?>"><span class="six-nav-icon">⬠</span> Revenue</a>
            <a href="?tab=gads"     class="six-nav-item <?php echo $active_tab==='gads'    ?'active':'';?>">
                <span class="six-nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span> Google Ads
                <?php if(!$mcc_configured):?><span style="font-size:9px;color:var(--warning);margin-left:auto">Setup</span><?php endif;?>
            </a>
            <a href="?tab=intelligence" class="six-nav-item <?php echo $active_tab==='intelligence'?'active':'';?>">
                <span class="six-nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><circle cx="12" cy="12" r="3"/><path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/></svg></span> Intelligence
                <?php
                $intel_pending = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}six_recommendations r
                     INNER JOIN {$wpdb->prefix}six_assignments a ON r.client_id=a.client_id
                     WHERE a.advisor_id=%d AND r.status='active' AND r.source LIKE 'ai_%%'", $advisor_id));
                if($intel_pending>0):?><span class="six-badge"><?php echo $intel_pending;?></span><?php endif;?>
            </a>
            <a href="?tab=calendar" class="six-nav-item <?php echo $active_tab==='calendar'?'active':'';?>">
                <span class="six-nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span> Calendar
                <?php if(!get_user_meta($advisor_id,'six_gcal_refresh_token',true)):?><span style="font-size:9px;color:var(--warning);margin-left:auto">Connect</span><?php endif;?>
            </a>
        </div>
        <div class="six-sidebar-bottom">
            <a href="<?php echo esc_url(wp_logout_url(home_url('/get-started/'))); ?>" class="six-nav-item" style="color:var(--text3);margin-bottom:10px"><span class="six-nav-icon">↩</span> Log Out</a>
            <div class="six-advisor-card">
                <div class="six-advisor-avatar" style="background:linear-gradient(135deg,var(--pink),#a855f7)"><?php echo esc_html($initials);?></div>
                <div class="six-advisor-info">
                    <div class="six-advisor-name"><?php echo esc_html($advisor->display_name);?></div>
                    <div class="six-advisor-role">Account Manager</div>
                </div>
                <span class="six-online-dot"></span>
            </div>
        </div>
    </nav>

    <main class="six-main">

    <?php /* ════════════ OVERVIEW / MISSION CONTROL ════════════ */ if($active_tab==='overview'):
        // Build client data for overview
        $clients_attention = array();
        $total_avg_health  = 0;
        $health_count      = 0;
        foreach ($clients as $cl) {
            $h  = class_exists('Six_Health_Score') ? Six_Health_Score::calculate($cl->ID) : 0;
            $sc_raw = $wpdb->get_results($wpdb->prepare(
                "SELECT service_name,status FROM {$wpdb->prefix}six_client_services WHERE client_id=%d AND status='active'",$cl->ID), ARRAY_A);
            $sc = $sc_raw ?: array();
            $mr = floatval($wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(budget),0) FROM {$wpdb->prefix}six_client_services WHERE client_id=%d AND status='active'",$cl->ID)));
            $total_avg_health += $h; $health_count++;
            $clients_attention[] = array('id'=>$cl->ID,'name'=>$cl->display_name,'email'=>$cl->user_email,
                'health'=>$h,'services'=>$sc,'mrr'=>$mr,'attention'=>$h<75||($mr==0&&count($sc)>0));
        }
        $avg_health = $health_count ? round($total_avg_health/$health_count) : 0;
        usort($clients_attention, function($a,$b){ return $a['health'] <=> $b['health']; }); // lowest health first

        // Today's meetings from Google Calendar
        $today_meetings = array();
        $gcal_token = get_user_meta($advisor_id,'six_gcal_refresh_token',true);
        if ($gcal_token && class_exists('Six_Google_Calendar')) {
            $slots = Six_Google_Calendar::get_today_events($advisor_id);
            $today_meetings = $slots ?: array();
        }

        // Upsell opportunities based on health + services
        $upsells = array();
        foreach ($clients_attention as $cl) {
            if (count($cl['services'])>=1 && $cl['health']>=70) {
                $svc_names = array_column($cl['services'],'service_name');
                if (!in_array('seo',$svc_names)) $upsells[]=array('client'=>$cl['name'],'service'=>'SEO','reason'=>'High engagement — strong SEO candidate','color'=>'var(--cyan)');
                if (!in_array('google-business',$svc_names)) $upsells[]=array('client'=>$cl['name'],'service'=>'Google Business Profile','reason'=>'Local presence can drive walk-in traffic','color'=>'var(--cyan)');
            }
            if (count($upsells)>=4) break;
        }
        $clients_need_attention = count(array_filter($clients_attention,function($c){ return $c['attention']; }));

        // Intelligence counts for overview stats
        $total_intel_pending = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}six_recommendations r
             INNER JOIN {$wpdb->prefix}six_assignments a ON r.client_id=a.client_id
             WHERE a.advisor_id=%d AND r.status='active' AND r.source LIKE 'ai_%'",
            $advisor_id));
        $total_intel_approved = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}six_recommendations r
             INNER JOIN {$wpdb->prefix}six_assignments a ON r.client_id=a.client_id
             WHERE a.advisor_id=%d AND r.status='approved' AND r.source LIKE 'ai_%'",
            $advisor_id));
        ?>

        <!-- ── Page header ─────────────────────────────────────────── -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px">
            <div>
                <h1 class="six-page-title" style="margin:0">Mission Control</h1>
                <p class="six-page-sub" style="margin:4px 0 0"><?php echo date('l, F j, Y');?>
                    <?php if($clients_need_attention>0) echo ' — <span style="color:var(--danger);font-weight:600">'.$clients_need_attention.' client'.($clients_need_attention>1?'s':'').' need'.($clients_need_attention>1?'':'s').' attention today</span>';?>
                </p>
            </div>
            <a href="<?php echo admin_url('admin.php?page=six-portal-assign');?>" class="six-btn six-btn-primary" style="font-size:13px">+ Add Client</a>
        </div>

        <!-- ── Stat cards ──────────────────────────────────────────── -->
        <div style="display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;margin-bottom:24px">
            <div class="six-stat-card pink">
                <div class="six-stat-label">Total Clients</div>
                <div class="six-stat-val"><?php echo count($clients);?></div>
                <?php $new_this_month = count(array_filter($clients_attention,function($c){ $u=get_userdata($c['id']); return $u&&strtotime($u->user_registered)>strtotime('first day of this month'); })); ?>
                <?php if($new_this_month>0):?><div class="six-stat-trend up">↑ <?php echo $new_this_month;?> this month</div><?php endif;?>
            </div>
            <div class="six-stat-card cyan">
                <div class="six-stat-label">Total MRR</div>
                <div class="six-stat-val">$<?php echo number_format($total_mrr/1000,1);?>K</div>
                <?php if($total_mrr>0):?><div class="six-stat-trend up">↑ Active</div><?php endif;?>
            </div>
            <div class="six-stat-card blue">
                <div class="six-stat-label">Meetings Today</div>
                <div class="six-stat-val"><?php echo count($today_meetings);?></div>
                <?php if(!empty($today_meetings)):
                    $next=null; foreach($today_meetings as $m){ if(strtotime($m['start'])>time()){$next=$m;break;} }
                    if($next): ?><div class="six-stat-trend" style="color:var(--cyan)">Next: <?php echo date('g:i A',strtotime($next['start']));?></div><?php endif;?>
                <?php elseif(!$gcal_token):?><div class="six-stat-trend" style="color:var(--warning)"><a href="?tab=calendar" style="color:var(--warning)">Connect Calendar</a></div><?php endif;?>
            </div>
            <div class="six-stat-card green">
                <div class="six-stat-label">Avg Health Score</div>
                <div class="six-stat-val"><?php echo $avg_health;?>%</div>
                <?php $attention_ct=count(array_filter($clients_attention,function($c){ return $c['health'] < 50; })); if($attention_ct>0):?>
                <div class="six-stat-trend" style="color:var(--danger)">↓ <?php echo $attention_ct;?> critical</div>
                <?php else:?><div class="six-stat-trend up">↑ Healthy</div><?php endif;?>
            </div>
            <div class="six-stat-card" style="border-color:rgba(168,85,247,0.3)">
                <div class="six-stat-label">AI Requests</div>
                <div class="six-stat-val" style="color:#a855f7"><?php echo intval($total_intel_pending);?></div>
                <?php if(intval($total_intel_pending)>0):?>
                <div class="six-stat-trend" style="color:var(--warning)"><a href="?tab=intelligence" style="color:var(--warning)">Review →</a></div>
                <?php else:?><div class="six-stat-trend" style="color:var(--text3)"><?php echo intval($total_intel_approved);?> approved total</div><?php endif;?>
            </div>
        </div>

        <!-- ── Two-column layout (Clients + right panel) ───────────── -->
        <div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start;min-width:0">

            <!-- Clients Needing Attention -->
            <div class="six-card">
                <div class="six-card-header">
                    <span class="six-card-title">Clients Needing Attention</span>
                    <?php if($clients_need_attention>0):?>
                    <span class="six-badge" style="background:var(--danger);padding:4px 10px;border-radius:100px;font-size:11px"><?php echo $clients_need_attention;?> urgent</span>
                    <?php endif;?>
                </div>
                <div class="six-card-body" style="padding:0">
                <?php if(empty($clients_attention)):?>
                    <div style="padding:40px;text-align:center;color:var(--text3)">No clients assigned. Go to WP Admin → 6ix Portal → Assign Advisors.</div>
                <?php else:?>
                <table class="six-table">
                    <thead><tr><th>Client</th><th>Health</th><th>Services</th><th>MRR</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach($clients_attention as $cl):
                        $h=$cl['health'];
                        $hc=$h>=75?'high':($h>=50?'med':'low');
                        $dot_col=$h>=75?'var(--success)':($h>=50?'var(--warning)':'var(--danger)');
                        $svc_ct=count($cl['services']);
                    ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px">
                                <div class="six-client-initials"><?php echo esc_html(six_get_initials($cl['name']));?></div>
                                <div>
                                    <strong><?php echo esc_html($cl['name']);?></strong>
                                    <div style="font-size:11px;color:var(--text3)"><?php
                                        $biz=get_user_meta($cl['id'],'billing_company',true)?:
                                             $wpdb->get_var($wpdb->prepare("SELECT business_name FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d",$cl['id']));
                                        echo esc_html($biz?:'No business name');
                                    ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="six-health <?php echo $hc;?>" style="display:inline-flex;align-items:center;gap:5px">
                                <span style="width:8px;height:8px;border-radius:50%;background:<?php echo $dot_col;?>;display:inline-block"></span>
                                <?php echo $h;?>%
                            </span>
                        </td>
                        <td>
                            <?php if($svc_ct===0): ?>
                                <span style="font-size:11px;color:var(--text3)">—</span>
                            <?php elseif($svc_ct===1): ?>
                                <span class="six-tag" style="font-size:11px"><?php
                                    $svc0 = $cl['services'][0];
                                    echo esc_html(is_object($svc0) ? ($svc0->service_name??'') : ($svc0['service_name']??''));
                                ?></span>
                            <?php else: ?>
                                <span class="six-tag" style="font-size:11px"><?php echo $svc_ct;?> Active</span>
                            <?php endif;?>
                        </td>
                        <td><?php echo $cl['mrr']>0?'$'.number_format($cl['mrr'],0):'—';?></td>
                        <td>
                            <?php if($h<50): ?>
                                <a href="?tab=clients&client=<?php echo $cl['id'];?>" class="six-btn six-btn-primary six-btn-sm" style="background:var(--pink)">Review</a>
                            <?php else: ?>
                                <a href="?tab=clients&client=<?php echo $cl['id'];?>" class="six-btn six-btn-ghost six-btn-sm">View</a>
                            <?php endif;?>
                        </td>
                    </tr>
                    <?php endforeach;?>
                    </tbody>
                </table>
                <?php endif;?>
                </div>
            </div>

            <!-- Right panel: Meetings + Upsells -->
            <div style="display:flex;flex-direction:column;gap:16px">

                <!-- Today's Meetings -->
                <div class="six-card">
                    <div class="six-card-header" style="border-bottom:1px solid var(--border);padding-bottom:12px">
                        <span class="six-card-title">Today's Meetings</span>
                        <a href="?tab=calendar" class="six-btn six-btn-ghost six-btn-sm">Calendar →</a>
                    </div>
                    <div class="six-card-body" style="padding:16px 0 0">
                    <?php if(empty($today_meetings) && !$gcal_token): ?>
                        <div style="padding:0 16px 16px;text-align:center">
                            <div style="font-size:28px;margin-bottom:10px"></div>
                            <div style="font-size:12px;color:var(--text3);margin-bottom:12px">Connect Google Calendar to see your meetings here.</div>
                            <a href="?tab=calendar" class="six-btn six-btn-primary" style="font-size:12px;padding:8px 16px">Connect Calendar →</a>
                        </div>
                    <?php elseif(empty($today_meetings)): ?>
                        <div style="padding:0 16px 16px;text-align:center;color:var(--text3);font-size:12px">No meetings scheduled today.</div>
                    <?php else: ?>
                        <?php foreach($today_meetings as $i=>$meeting):
                            $is_next = isset($next) && $meeting['start']===$next['start'];
                            $time_col = $is_next ? 'var(--pink)' : ($i===0?'var(--warning)':'var(--text3)');
                        ?>
                        <div style="display:flex;gap:14px;padding:10px 16px;border-bottom:1px solid rgba(255,255,255,0.04);<?php echo $is_next?'background:rgba(255,102,153,0.04)':'';?>">
                            <div style="font-size:11px;font-weight:700;color:<?php echo $time_col;?>;min-width:52px;padding-top:2px;text-align:right;line-height:1.3">
                                <?php echo date('g:i',$m_start=strtotime($meeting['start']));?><br>
                                <span style="font-weight:400"><?php echo date('A',$m_start);?></span>
                            </div>
                            <div style="flex:1">
                                <div style="font-size:13px;font-weight:600;margin-bottom:2px"><?php echo esc_html($meeting['title']??'Meeting');?></div>
                                <div style="font-size:11px;color:var(--text3)"><?php echo esc_html($meeting['client_name']??'');?>
                                    <?php if(!empty($meeting['duration'])):?> · <?php echo $meeting['duration'];?> min<?php endif;?>
                                </div>
                                <?php if(!empty($meeting['meet_link'])):?>
                                <a href="<?php echo esc_url($meeting['meet_link']);?>" target="_blank" style="font-size:10px;color:var(--cyan);text-decoration:none;margin-top:3px;display:inline-block">Join Meet →</a>
                                <?php endif;?>
                            </div>
                        </div>
                        <?php endforeach;?>
                    <?php endif;?>
                    </div>
                </div>

                <!-- Upsell Opportunities -->
                <div class="six-card">
                    <div class="six-card-header" style="border-bottom:1px solid var(--border);padding-bottom:12px">
                        <span class="six-card-title">Upsell Opportunities</span>
                    </div>
                    <div class="six-card-body" style="padding:16px">
                    <?php if(empty($upsells)):?>
                        <div style="font-size:12px;color:var(--text3)">No upsell signals detected yet.</div>
                    <?php else:?>
                        <div style="font-size:11px;color:var(--text3);margin-bottom:12px">Based on campaign performance signals:</div>
                        <?php foreach($upsells as $u):?>
                        <div style="padding:10px 12px;background:var(--dark4);border-radius:8px;margin-bottom:8px;border-left:3px solid var(--cyan)">
                            <div style="font-size:12px;font-weight:600;margin-bottom:3px"><?php echo esc_html($u['client']);?> → <?php echo esc_html($u['service']);?></div>
                            <div style="font-size:11px;color:<?php echo $u['color'];?>">↑ <?php echo esc_html($u['reason']);?></div>
                        </div>
                        <?php endforeach;?>
                    <?php endif;?>
                    </div>
                </div>

                <!-- Pending approvals quick widget -->
                <?php if($total_pending>0):?>
                <div class="six-card" style="border-color:rgba(227,179,65,0.3)">
                    <div class="six-card-body" style="padding:14px 16px;display:flex;align-items:center;justify-content:space-between">
                        <div>
                            <div style="font-size:12px;font-weight:700;color:var(--warning)"> <?php echo $total_pending;?> Pending Approval<?php echo $total_pending>1?'s':'';?></div>
                            <div style="font-size:11px;color:var(--text3);margin-top:2px">Service activations &amp; budget changes</div>
                        </div>
                        <a href="?tab=approvals" class="six-btn six-btn-primary six-btn-sm" style="background:var(--warning);color:var(--dark1);white-space:nowrap">Review →</a>
                    </div>
                </div>
                <?php endif;?>
            </div>
        </div><!-- /.two-col -->

    <?php /* ════════════ CLIENTS ════════════ */ elseif($active_tab==='clients'): ?>

    <?php if($view_client && $view_client_id):
        // ── Load ALL client data ──────────────────────────────────────────
        $c_svcs        = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}six_client_services WHERE client_id=%d ORDER BY status DESC,budget DESC",$view_client_id));
        $c_active_svcs_arr = array_filter((array)$c_svcs, function($s){ return $s->status === 'active'; });
        $c_pending_svcs    = array_filter((array)$c_svcs, function($s){ return $s->status === 'pending'; });
        $c_metrics     = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}six_metrics WHERE client_id=%d ORDER BY service_slug,label",$view_client_id));
        $c_recs        = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}six_recommendations WHERE client_id=%d AND status IN('active','approved') ORDER BY created_at DESC",$view_client_id));
        $c_reports     = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}six_reports WHERE client_id=%d ORDER BY created_at DESC LIMIT 10",$view_client_id));
        $c_checkout    = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d",$view_client_id));

        // ── Fallback: if checkout row is empty, pull from WP user meta ──────────
        // This handles clients who completed onboarding before the guaranteed-save fix
        if ( $c_checkout && empty($c_checkout->first_name) ) {
            $um_first = get_user_meta($view_client_id,'first_name',true);
            $um_last  = get_user_meta($view_client_id,'last_name',true);
            $um_phone = get_user_meta($view_client_id,'billing_phone',true);
            if ($um_first || $um_last) {
                // Backfill into checkout row so future loads are faster
                $wpdb->update(
                    $wpdb->prefix.'six_checkout_progress',
                    array_filter(array(
                        'first_name' => $um_first,
                        'last_name'  => $um_last,
                        'phone'      => $um_phone,
                    )),
                    array('user_id'=>$view_client_id)
                );
                $c_checkout->first_name = $um_first;
                $c_checkout->last_name  = $um_last;
                $c_checkout->phone      = $um_phone;
            }
        }

        $c_biz         = ($c_checkout->business_name ?? '') ?: ($view_client->display_name ?? '');
        $c_industry    = $c_checkout->industry ?? '';
        $c_goal        = $c_checkout->goal ?? '';
        $c_challenge   = $c_checkout->challenge ?? '';
        $c_website     = $c_checkout->website ?? '';
        $c_location    = $c_checkout->location ?? '';
        $c_employees   = $c_checkout->employees ?? '';
        $c_revenue     = $c_checkout->monthly_revenue ?? '';
        $c_mktg_budget = $c_checkout->mktg_budget ?? '';
        $c_competitors = array_filter(array_map('trim', explode(',', $c_checkout->competitors ?? '')));
        $c_comp_str    = implode(', ', $c_competitors);
        // v5 questionnaire fields
        $c_address     = $c_checkout->business_address ?? '';
        $c_years       = $c_checkout->years_in_business ?? '';
        $c_platforms   = $c_checkout->platforms ?? '';
        $c_notes       = $c_checkout->onboarding_notes ?? '';
        $c_crm         = $c_checkout->crm_tools ?? '';
        $c_awards      = $c_checkout->reviews_awards ?? '';
        // Google Ads questionnaire
        $c_ads_loc     = $c_checkout->ads_locations ?? '';
        $c_ads_prod    = $c_checkout->ads_products ?? '';
        $c_ads_kw      = $c_checkout->ads_keywords ?? '';
        $c_ads_usp     = $c_checkout->ads_usp ?? '';
        $c_ads_promo   = $c_checkout->ads_promo ?? '';
        $c_ads_fin     = $c_checkout->ads_financing ?? '';
        $c_ads_bud     = intval($c_checkout->ads_budget ?? 0);
        // SEO questionnaire
        $c_seo_pages   = $c_checkout->seo_pages ?? '';
        $c_seo_loc     = $c_checkout->seo_locations ?? '';
        $c_seo_kw      = $c_checkout->seo_keywords ?? '';
        $c_seo_usp     = $c_checkout->seo_usp ?? '';
        $c_seo_gsc     = $c_checkout->seo_gsc ?? '';
        $c_seo_blog    = $c_checkout->seo_blog ?? '';
        $c_seo_comp    = $c_checkout->seo_competitors ?? '';
        $c_seo_crm     = $c_checkout->seo_crm_tools ?? '';
        $c_seo_reviews = $c_checkout->seo_reviews ?? '';
        $c_seo_extra   = $c_checkout->seo_extra_info ?? '';
        $c_seo_bud     = intval($c_checkout->seo_budget ?? 0);
        // Google Business questionnaire
        $c_gbp_name    = $c_checkout->gbp_name ?? '';
        $c_gbp_cat     = $c_checkout->gbp_category ?? '';
        $c_gbp_svcs    = $c_checkout->gbp_services ?? '';
        $c_gbp_hrs     = $c_checkout->gbp_hours ?? '';
        $c_gbp_rating  = $c_checkout->gbp_rating ?? '';
        $c_gbp_bud     = intval($c_checkout->gbp_budget ?? 0);
        $c_ads_sched   = $c_checkout->ads_schedule ?? '';
        // Website questionnaire
        $c_web_goal    = $c_checkout->web_goal ?? '';
        $c_web_pages   = $c_checkout->web_pages ?? '';
        $c_web_style   = $c_checkout->web_style ?? '';
        $c_web_refs    = $c_checkout->web_refs ?? '';
        $c_web_exist   = $c_checkout->web_existing ?? '';
        $c_web_bud     = intval($c_checkout->web_budget ?? 0);
        // AI plan
        $c_ai_plan     = $c_checkout->ai_plan_json ?? '';
        $c_ai_data     = $c_ai_plan ? json_decode($c_ai_plan, true) : null;
        $c_active_svc_names = implode(', ', array_column((array)$c_active_svcs_arr,'service_name'));
        $c_total_budget = array_sum(array_column((array)$c_active_svcs_arr,'budget'));
        $c_missing_svcs = implode(', ', array_filter(
            array('Google Ads','SEO','Website Development'),
            function($s) use ($c_active_svc_names){ return stripos($c_active_svc_names,$s)===false; }
        ));
        // API connection data per client
        $c_gads    = get_user_meta($view_client_id,'six_gads_customer_id_display',true)?:get_user_meta($view_client_id,'six_gads_customer_id',true);
        $c_ga4_id  = get_user_meta($view_client_id,'six_ga4_property_id',true);
        $c_meta_account = get_user_meta($view_client_id,'six_meta_ad_account_id',true);
        $c_meta_business = get_user_meta($view_client_id,'six_meta_business_id',true);
        $c_meta_pixel = get_user_meta($view_client_id,'six_meta_pixel_id',true);
        $c_sync    = get_user_meta($view_client_id,'six_gads_last_sync',true);
        $health    = class_exists('Six_Health_Score')?Six_Health_Score::calculate($view_client_id):0;
        // Activity/requests from budget change requests
        $c_budget_reqs = $wpdb->get_results($wpdb->prepare(
            "SELECT cs.*, um.meta_value as req_data FROM {$wpdb->prefix}six_client_services cs
             LEFT JOIN {$wpdb->prefix}usermeta um ON um.user_id=%d AND um.meta_key=CONCAT('six_budget_req_',cs.id)
             WHERE cs.client_id=%d AND cs.status='active' ORDER BY cs.id DESC", $view_client_id, $view_client_id));
        // AI recs
        $c_ai_pending  = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}six_recommendations WHERE client_id=%d AND status='active' ORDER BY created_at DESC",$view_client_id));
        $c_ai_approved = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}six_recommendations WHERE client_id=%d AND status='approved' ORDER BY created_at DESC LIMIT 8",$view_client_id));

        $svc_def = array(
            'google-ads'      => array('name'=>'Google Ads',              'icon'=>'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="3"/><path d="M7 15V9m5 6V7m5 8V5" stroke-width="2"/></svg>', 'color'=>'#4285F4'),
            'seo'             => array('name'=>'SEO',                     'icon'=>'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>', 'color'=>'#56D364'),
            'google-business' => array('name'=>'Google Business Profile', 'icon'=>'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>', 'color'=>'#FBBC05'),
            'website'         => array('name'=>'Website Development',     'icon'=>'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>', 'color'=>'#a855f7'));

        // Determine account status
        $acct_status = count($c_active_svcs_arr)>0 ? 'Active' : (count($c_pending_svcs)>0 ? 'Onboarding' : ($health<40 ? 'At Risk' : 'Active'));
        $acct_color  = $acct_status==='Active'?'var(--success)':($acct_status==='Onboarding'?'var(--warning)':'var(--danger)');

        // Internal tab within client view
        $ctab = isset($_GET['ctab']) ? sanitize_key($_GET['ctab']) : 'overview';
        $base_url = "?tab=clients&client={$view_client_id}";
    ?>

    <!-- ── CLIENT BACK + BREADCRUMB ─────────────────────────────────── -->
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap">
        <a href="?tab=clients" style="font-size:12px;color:var(--text3);text-decoration:none;display:flex;align-items:center;gap:4px">← All Clients</a>
        <span style="color:var(--text3);font-size:12px">/</span>
        <span style="font-size:12px;color:var(--text1);font-weight:600"><?php echo esc_html($c_biz); ?></span>
        <span style="margin-left:auto;display:flex;align-items:center;gap:8px">
            <span style="font-size:11px;font-weight:700;color:<?php echo $acct_color; ?>;background:<?php echo $acct_color; ?>18;border:1px solid <?php echo $acct_color; ?>30;padding:3px 10px;border-radius:20px"><?php echo $acct_status; ?></span>
            <span style="font-size:11px;color:var(--text3)">Health: <strong style="color:<?php echo $health>=70?'var(--success)':($health>=40?'var(--warning)':'var(--danger)'); ?>"><?php echo $health; ?>%</strong></span>
        </span>
    </div>

    <!-- ── CUSTOMER INTELLIGENCE HEADER ─────────────────────────────── -->
    <div class="six-client-header">
        <!-- Top accent bar -->
        <div class="six-client-header-bar"></div>

        <!-- Identity row: avatar + name + contact -->
        <div class="six-client-header-identity">
            <div class="six-client-header-avatar">
                <?php echo esc_html(six_get_initials($c_biz?:$view_client->display_name)); ?>
            </div>
            <div class="six-client-header-info">
                <div class="six-client-header-name"><?php echo esc_html($c_biz); ?></div>
                <div class="six-client-header-meta">
                    <?php echo esc_html($view_client->display_name); ?>
                    <span class="six-client-header-sep">·</span>
                    <?php echo esc_html($view_client->user_email); ?>
                    <?php if($c_industry): ?>
                    <span class="six-client-header-sep">·</span><?php echo esc_html($c_industry); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Service badges row -->
        <div class="six-client-header-services">
            <?php foreach($c_active_svcs_arr as $s):
                $sd=$svc_def[$s->service_slug]??array('icon'=>'⚙','color'=>'var(--pink)'); ?>
            <span class="six-svc-badge" style="--bc:<?php echo $sd['color']; ?>"><?php echo $sd['icon']; ?> <?php echo esc_html($s->service_name); ?></span>
            <?php endforeach; ?>
            <?php foreach($c_pending_svcs as $s): ?>
            <span class="six-svc-badge six-svc-badge-pending">⏳ <?php echo esc_html($s->service_name); ?></span>
            <?php endforeach; ?>
        </div>

        <!-- KPI stats grid: 4 tiles -->
        <div class="six-client-header-kpis">
            <div class="six-client-kpi">
                <div class="six-client-kpi-val" style="color:var(--cyan)">$<?php echo number_format($c_total_budget,0); ?></div>
                <div class="six-client-kpi-label">Monthly Budget</div>
            </div>
            <div class="six-client-kpi">
                <div class="six-client-kpi-val" style="color:<?php echo $health>=70?'var(--success)':($health>=40?'var(--warning)':'var(--danger)'); ?>"><?php echo $health; ?>%</div>
                <div class="six-client-kpi-label">Health Score</div>
            </div>
            <div class="six-client-kpi">
                <div class="six-client-kpi-val" style="color:var(--pink)"><?php echo count($c_active_svcs_arr); ?></div>
                <div class="six-client-kpi-label">Active Services</div>
            </div>
            <div class="six-client-kpi">
                <div class="six-client-kpi-val" style="color:var(--success)"><?php echo count($c_metrics); ?></div>
                <div class="six-client-kpi-label">Metrics Tracked</div>
            </div>
        </div>
    </div>

    <!-- ── INTERNAL NAVIGATION TABS ──────────────────────────────────── -->
    <div class="six-client-tabs">
        <?php foreach(array(
            'overview'     => array('icon'=>'', 'label'=>'Overview',          'short'=>'Overview'),
            'services'     => array('icon'=>'', 'label'=>'Services',          'short'=>'Services'),
            'ai'           => array('icon'=>'', 'label'=>'AI Strategy',       'short'=>'AI'),
            'datasources'  => array('icon'=>'', 'label'=>'Data Sources',      'short'=>'Data'),
            'activity'     => array('icon'=>'', 'label'=>'Activity',          'short'=>'Activity'),
            'profile'      => array('icon'=>'', 'label'=>'Client Profile',    'short'=>'Profile'),
            'questionnaire'=> array('icon'=>'', 'label'=>'Questionnaire',     'short'=>'Q&amp;A'),
            'reports'      => array('icon'=>'', 'label'=>'Reports',           'short'=>'Reports')) as $ctab_key => $ctab_def): ?>
        <a href="<?php echo $base_url; ?>&ctab=<?php echo $ctab_key; ?>"
           class="six-client-tab <?php echo $ctab===$ctab_key?'active':''; ?>">
            <span class="six-client-tab-full"><?php echo $ctab_def['label']; ?></span>
            <span class="six-client-tab-short"><?php echo $ctab_def['short']; ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- ═══════════════════════ CTAB: OVERVIEW ═══════════════════════ -->
    <?php if($ctab==='overview'): ?>

    <!-- AI Plan summary card (from onboarding) -->
    <?php if($c_ai_data): ?>
    <div style="background:linear-gradient(135deg,rgba(255,102,153,0.08),rgba(131,197,237,0.06));border:1px solid rgba(255,102,153,0.2);border-radius:14px;padding:20px;margin-bottom:20px;position:relative;overflow:hidden">
        <div style="position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--pink),var(--cyan))"></div>
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px">
            <div>
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--text3);margin-bottom:4px">AI Growth Plan (from onboarding)</div>
                <div style="font-size:14px;font-weight:600;color:var(--text1);line-height:1.4;max-width:520px"><?php echo esc_html($c_ai_data['headline']??'Growth plan pending.'); ?></div>
            </div>
            <a href="<?php echo $base_url; ?>&ctab=services" style="font-size:11px;color:var(--cyan);text-decoration:none;white-space:nowrap;margin-top:2px">Edit KPI targets →</a>
        </div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:14px">
            <?php
            $ai_stats = array(
                array('label'=>'Month 1 Leads',  'val'=>$c_ai_data['month1_leads']??'—',  'color'=>'var(--cyan)'),
                array('label'=>'Month 3 Leads',  'val'=>$c_ai_data['month3_leads']??'—',  'color'=>'var(--success)'),
                array('label'=>'Est. Monthly ROI','val'=>$c_ai_data['monthly_roi']??'—',   'color'=>'var(--pink)'));
            foreach($ai_stats as $stat): ?>
            <div style="background:var(--dark3);border-radius:10px;padding:12px;text-align:center">
                <div style="font-family:'Syne',sans-serif;font-size:16px;font-weight:700;color:<?php echo $stat['color']; ?>"><?php echo esc_html($stat['val']); ?></div>
                <div style="font-size:10px;color:var(--text3);margin-top:3px"><?php echo esc_html($stat['label']); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div style="background:rgba(227,179,65,0.08);border:1px solid rgba(227,179,65,0.2);border-radius:8px;padding:10px 14px;display:flex;align-items:flex-start;gap:8px">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--warning)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span style="font-size:12px;color:var(--warning)">These are AI-generated estimates based on the client&rsquo;s onboarding answers. <strong>Review and set accurate KPI targets</strong> in the Services tab before the client sees them in their dashboard.</span>
        </div>
    </div>
    <?php elseif($c_mktg_budget||$c_goal): ?>
    <div style="background:var(--dark2);border:1px solid var(--border);border-radius:12px;padding:16px 18px;margin-bottom:20px">
        <div style="font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:10px">Onboarding Summary</div>
        <div style="display:flex;gap:16px;flex-wrap:wrap">
            <?php if($c_goal): ?><span style="font-size:12px;color:var(--text2)"><strong>Goals:</strong> <?php echo esc_html($c_goal); ?></span><?php endif; ?>
            <?php if($c_mktg_budget): ?><span style="font-size:12px;color:var(--text2)"><strong>Budget:</strong> $<?php echo esc_html($c_mktg_budget); ?>/mo</span><?php endif; ?>
            <?php if($c_platforms): ?><span style="font-size:12px;color:var(--text2)"><strong>Services:</strong> <?php echo esc_html(str_replace(',',', ',$c_platforms)); ?></span><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Metrics summary -->
    <?php if(!empty($c_metrics)): ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px">
        <?php foreach($c_metrics as $met):
            $c_n=floatval(preg_replace('/[^0-9.]/','',$met->current_value));
            $p_n=floatval(preg_replace('/[^0-9.]/','',$met->previous_value));
            $tr =($p_n>0)?round((($c_n-$p_n)/$p_n)*100):null;
            $sd2=$svc_def[$met->service_slug]??array('color'=>'var(--pink)');
        ?>
        <div style="background:var(--dark2);border:1px solid <?php echo $sd2['color']; ?>20;border-radius:12px;padding:14px;position:relative;overflow:hidden">
            <div style="position:absolute;top:0;left:0;right:0;height:2px;background:<?php echo $sd2['color']; ?>"></div>
            <div style="font-size:9px;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px"><?php echo esc_html($met->label); ?></div>
            <div style="font-size:22px;font-weight:800;font-family:'Syne',sans-serif;color:var(--text1);line-height:1;margin-bottom:6px"><?php echo esc_html($met->current_value); ?></div>
            <?php if($tr!==null): ?>
            <div style="font-size:10px;font-weight:700;color:<?php echo $tr>=0?'var(--success)':'var(--danger)'; ?>"><?php echo $tr>=0?'↑':'↓'; ?><?php echo abs($tr); ?>% vs prev</div>
            <?php else: ?>
            <div style="font-size:10px;color:var(--text3)"><?php echo esc_html($met->service_slug); ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="background:var(--dark2);border:1px solid var(--border);border-radius:12px;padding:16px 18px;margin-bottom:20px;display:flex;align-items:center;gap:12px">
        <span style="font-size:20px"></span>
        <div>
            <div style="font-size:13px;font-weight:600;margin-bottom:2px">No metrics added yet</div>
            <div style="font-size:12px;color:var(--text3)">Add metrics in the Services & Metrics tab to track performance here.</div>
        </div>
        <a href="<?php echo $base_url; ?>&ctab=services" class="six-btn six-btn-primary six-btn-sm" style="margin-left:auto;font-size:11px">Add Metrics →</a>
    </div>
    <?php endif; ?>

    <!-- Advisor Recommendations section -->
    <?php $active_recs = array_filter((array)$c_recs, function($r){ return $r->status === 'active'; }); ?>
    <?php if(!empty($active_recs)): ?>
    <div style="background:var(--dark2);border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:20px">
        <div style="padding:14px 18px;border-bottom:1px solid rgba(255,255,255,0.05);display:flex;align-items:center;justify-content:space-between">
            <span style="font-size:13px;font-weight:700">Active Recommendations <span style="font-size:11px;color:var(--pink);background:rgba(255,102,153,0.1);padding:2px 8px;border-radius:10px;margin-left:6px"><?php echo count($active_recs); ?></span></span>
            <a href="<?php echo $base_url; ?>&ctab=ai" class="six-btn six-btn-ghost six-btn-sm" style="font-size:11px">Manage →</a>
        </div>
        <?php foreach(array_slice($active_recs,0,3) as $rec): ?>
        <div style="padding:12px 18px;border-bottom:1px solid rgba(255,255,255,0.04);display:flex;align-items:flex-start;gap:10px;min-width:0">
            <span style="font-size:14px;flex-shrink:0"></span>
            <div style="flex:1;min-width:0">
                <div style="font-size:12px;font-weight:600;margin-bottom:2px"><?php echo esc_html($rec->title); ?></div>
                <div style="font-size:11px;color:var(--text3);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo esc_html($rec->description); ?></div>
            </div>
            <span style="font-size:10px;color:var(--text3);flex-shrink:0"><?php echo human_time_diff(strtotime($rec->created_at),time()); ?> ago</span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Budget change requests -->
    <?php
    $pending_budgets=array();
    foreach($c_budget_reqs as $row){
        if($row->req_data){$rd=maybe_unserialize($row->req_data);if(is_array($rd)&&($rd['status']??'')!=='approved')$pending_budgets[]=$row;}
    }
    if(!empty($pending_budgets)): ?>
    <div style="background:rgba(227,179,65,0.06);border:1px solid rgba(227,179,65,0.2);border-radius:12px;padding:16px 18px;margin-bottom:20px">
        <div style="font-size:12px;font-weight:700;color:var(--warning);margin-bottom:10px">⏳ Pending Budget Change Requests</div>
        <?php foreach($pending_budgets as $row):
            $rd=maybe_unserialize($row->req_data);
            $sd2=$svc_def[$row->service_slug]??array('icon'=>'⚙','color'=>'var(--pink)');
        ?>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;flex-wrap:wrap">
            <span style="font-size:12px"><?php echo $sd2['icon']; ?> <?php echo esc_html($row->service_name); ?></span>
            <span style="font-size:12px;color:var(--text3)">Current: <strong>$<?php echo number_format($row->budget,0); ?>/mo</strong></span>
            <span style="font-size:12px;color:var(--warning)">Requested: <strong>$<?php echo number_format($rd['requested_budget']??0,0); ?>/mo</strong></span>
            <div style="display:flex;gap:6px;margin-left:auto">
                <button class="six-btn six-btn-primary six-btn-sm six-adv-approve-budget" data-service-id="<?php echo $row->id; ?>" data-budget="<?php echo intval($rd['requested_budget']??0); ?>" style="font-size:11px">Approve</button>
                <button class="six-btn six-btn-ghost six-btn-sm six-adv-decline-budget" data-service-id="<?php echo $row->id; ?>" style="font-size:11px">Decline</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ═══════════════════════ CTAB: SERVICES & METRICS ═══════════════════════ -->
    <?php elseif($ctab==='services'): ?>

    <!-- ── Advisor KPI Editor: customer dashboard fields ── -->
    <div style="background:var(--dark3);border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:20px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
            <div>
                <div style="font-size:14px;font-weight:700;color:var(--text1)">Set Client KPI Targets</div>
                <div style="font-size:11px;color:var(--text3);margin-top:2px;max-width:480px">
                    These replace the AI estimates on the customer dashboard. Set realistic, achievable targets based on your expertise.
                    <?php if($c_ai_data): ?>
                    <span style="color:var(--warning)"> AI suggested: <?php echo esc_html($c_ai_data['month1_leads']??'—'); ?> leads/mo1 · <?php echo esc_html($c_ai_data['monthly_roi']??'—'); ?> ROI</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px" id="kpi-editor-grid">
        <?php
        $kpi_tbl     = $wpdb->prefix . 'six_client_kpis';
        $kpi_rows_adv = $wpdb->get_var("SHOW TABLES LIKE '{$kpi_tbl}'") === $kpi_tbl
            ? $wpdb->get_results($wpdb->prepare(
                "SELECT kpi_key, kpi_value FROM {$kpi_tbl} WHERE client_id=%d",
                $view_client_id
              ))
            : array();
        $kpi_vals_adv = array();
        foreach( $kpi_rows_adv as $r ) $kpi_vals_adv[$r->kpi_key] = $r->kpi_value;
        $kpi_fields = array(
            'new_customers'  => array('label'=>'New Customers',    'ph'=>'e.g. 40,841',      'hint'=>'Monthly new customer count'),
            'sales_revenue'  => array('label'=>'Sales Revenue',    'ph'=>'e.g. $30,816',    'hint'=>'Monthly revenue figure'),
            'total_visitors' => array('label'=>'Total Visitors',   'ph'=>'e.g. 520,612',     'hint'=>'Monthly website sessions'),
            'roi_projection' => array('label'=>'ROI Projection',   'ph'=>'e.g. +$4,250/month','hint'=>'Monthly ROI value'),
            'roi_growth_pct' => array('label'=>'Growth Potential', 'ph'=>'e.g. +18%',    'hint'=>'Growth % shown under ROI'));
        foreach( $kpi_fields as $key => $f ):
        ?>
        <div>
            <label style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--text3);display:block;margin-bottom:6px"><?php echo esc_html($f['label']); ?></label>
            <div style="display:flex;gap:6px">
                <input class="ob-input" style="flex:1;font-size:12px;padding:8px 12px"
                    type="text"
                    id="kpi-<?php echo esc_attr($key); ?>"
                    value="<?php echo esc_attr($kpi_vals_adv[$key] ?? ''); ?>"
                    placeholder="<?php echo esc_attr($f['ph']); ?>"
                    data-kpi="<?php echo esc_attr($key); ?>"
                    data-client="<?php echo esc_attr($view_client_id); ?>">
                <button onclick="sixSaveKpi(this)" data-kpi="<?php echo esc_attr($key); ?>" data-client="<?php echo esc_attr($view_client_id); ?>"
                    style="padding:8px 14px;background:rgba(131,197,237,.1);border:1px solid rgba(131,197,237,.2);border-radius:8px;color:#83C5ED;font-size:11px;font-weight:600;cursor:pointer;flex-shrink:0;transition:all .2s"
                    onmouseover="this.style.background='rgba(131,197,237,.2)'" onmouseout="this.style.background='rgba(131,197,237,.1)'"
                >Save</button>
            </div>
            <div style="font-size:10px;color:var(--text3);margin-top:4px"><?php echo esc_html($f['hint']); ?></div>
        </div>
        <?php endforeach; ?>
        </div>
        <div id="kpi-save-msg" style="display:none;margin-top:12px;font-size:12px;color:#56D364"></div>
    </div>

    <script>
    function sixSaveKpi(btn) {
        var key    = btn.dataset.kpi;
        var client = btn.dataset.client;
        var input  = document.getElementById('kpi-' + key);
        var val    = input ? input.value.trim() : '';
        var msg    = document.getElementById('kpi-save-msg');
        btn.textContent = 'Saving...';
        var params = new URLSearchParams({
            action: 'six_save_client_kpi',
            nonce: NONCE,
            client_id: client,
            kpi_key: key,
            kpi_value: val
        });
        fetch(AJAX_URL, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:params.toString()})
        .then(r=>r.json()).then(function(r){
            btn.textContent = r.success ? 'Saved' : 'Error';
            if (msg) { msg.style.display='block'; msg.textContent = r.success ? 'Saved — customer dashboard updated.' : (r.data||'Error saving'); }
            setTimeout(function(){ btn.textContent='Save'; if(msg) msg.style.display='none'; }, 2500);
        });
    }
    </script>

    <!-- Services list with per-service metric management -->
    <?php foreach($svc_def as $slug => $sd_item):
        $svc_row = null;
        foreach($c_svcs as $s){ if($s->service_slug===$slug){ $svc_row=$s; break; } }
        $svc_metrics = array_filter((array)$c_metrics, function($m) use ($slug){ return $m->service_slug === $slug; });
    ?>
    <div style="background:var(--dark2);border:1px solid <?php echo $sd_item['color']; ?>22;border-radius:14px;overflow:hidden;margin-bottom:16px">
        <!-- Service header -->
        <div style="padding:14px 18px;border-bottom:1px solid rgba(255,255,255,0.05);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
            <div style="display:flex;align-items:center;gap:10px">
                <div style="width:34px;height:34px;border-radius:9px;background:<?php echo $sd_item['color']; ?>15;display:flex;align-items:center;justify-content:center;font-size:16px"><?php echo $sd_item['icon']; ?></div>
                <div>
                    <div style="font-size:13px;font-weight:700"><?php echo esc_html($sd_item['name']); ?></div>
                    <?php if($svc_row): ?>
                    <div style="font-size:11px;color:var(--text3)">$<?php echo number_format($svc_row->budget,0); ?>/mo · <?php echo ucfirst($svc_row->status); ?></div>
                    <?php else: ?><div style="font-size:11px;color:var(--text3)">Not active</div><?php endif; ?>
                </div>
            </div>
            <div style="display:flex;gap:8px;align-items:center">
                <?php if($svc_row&&$svc_row->status==='active'): ?>
                <span style="font-size:10px;background:rgba(86,211,100,0.1);color:var(--success);padding:3px 9px;border-radius:10px;font-weight:700">● Active</span>
                <?php if(!$svc_row->advisor_id&&$advisor_id): ?>
                <button class="six-btn six-btn-ghost six-btn-sm six-adv-assign-self" data-service-id="<?php echo $svc_row->id; ?>" style="font-size:11px">Assign to Me</button>
                <?php endif; ?>
                <?php elseif($svc_row&&$svc_row->status==='pending'): ?>
                <button class="six-btn six-btn-primary six-btn-sm" onclick="sixApproveService(this,<?php echo intval($svc_row->id); ?>,<?php echo intval($view_client_id); ?>)">Approve Service</button>
                <?php else: ?>
                <span style="font-size:10px;color:var(--text3)">Not requested</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if($svc_row&&$svc_row->status==='active'): ?>
        <!-- Metrics for this service -->
        <div style="padding:14px 18px">
            <?php if(!empty($svc_metrics)): ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;margin-bottom:14px">
                <?php foreach($svc_metrics as $met):
                    $c_n=floatval(preg_replace('/[^0-9.]/','',$met->current_value));
                    $t_n=floatval(preg_replace('/[^0-9.]/','',$met->target_value));
                    $pct=($t_n>0)?min(100,round(($c_n/$t_n)*100)):0;
                    $mc=$pct>=75?'var(--success)':($pct>=50?$sd_item['color']:'var(--warning)');
                ?>
                <div style="background:var(--dark3);border-radius:10px;padding:12px;position:relative">
                    <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--text3);margin-bottom:6px"><?php echo esc_html($met->label); ?></div>
                    <div style="font-size:20px;font-weight:800;font-family:'Syne',sans-serif;margin-bottom:6px;line-height:1"><?php echo esc_html($met->current_value); ?></div>
                    <?php if($t_n>0): ?>
                    <div style="height:3px;background:rgba(255,255,255,0.06);border-radius:2px;overflow:hidden;margin-bottom:4px">
                        <div style="height:100%;width:<?php echo $pct; ?>%;background:<?php echo $mc; ?>;border-radius:2px"></div>
                    </div>
                    <div style="font-size:10px;color:var(--text3)">Target: <?php echo esc_html($met->target_value); ?></div>
                    <?php endif; ?>
                    <!-- Edit/Delete -->
                    <div style="position:absolute;top:8px;right:8px;display:flex;gap:4px">
                        <button class="six-btn six-btn-ghost" style="padding:2px 6px;font-size:10px;color:var(--cyan)" onclick="populateMetricForm(<?php echo $met->id; ?>,'<?php echo esc_js($met->label); ?>','<?php echo esc_js($met->current_value); ?>','<?php echo esc_js($met->previous_value); ?>','<?php echo esc_js($met->target_value); ?>','<?php echo esc_js($met->service_slug); ?>')">Edit</button>
                        <button class="six-btn six-btn-ghost six-del-metric" style="padding:2px 6px;font-size:10px;color:var(--danger)" data-metric-id="<?php echo $met->id; ?>"></button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Add metric form for this service -->
            <div style="background:var(--dark4);border-radius:10px;padding:14px" id="metric-form-<?php echo esc_attr($slug); ?>">
                <div style="font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:10px">+ Add / Update Metric</div>
                <input type="hidden" class="metric-svc-slug" value="<?php echo esc_attr($slug); ?>">
                <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:8px;align-items:end">
                    <div>
                        <div style="font-size:10px;color:var(--text3);margin-bottom:4px">Label</div>
                        <input class="six-input metric-label-<?php echo esc_attr($slug); ?>" placeholder="e.g. Monthly Traffic" style="font-size:12px;padding:7px 10px">
                    </div>
                    <div>
                        <div style="font-size:10px;color:var(--text3);margin-bottom:4px">Current</div>
                        <input class="six-input metric-current-<?php echo esc_attr($slug); ?>" placeholder="420" style="font-size:12px;padding:7px 10px">
                    </div>
                    <div>
                        <div style="font-size:10px;color:var(--text3);margin-bottom:4px">Previous</div>
                        <input class="six-input metric-prev-<?php echo esc_attr($slug); ?>" placeholder="310" style="font-size:12px;padding:7px 10px">
                    </div>
                    <div>
                        <div style="font-size:10px;color:var(--text3);margin-bottom:4px">Target</div>
                        <input class="six-input metric-target-<?php echo esc_attr($slug); ?>" placeholder="600" style="font-size:12px;padding:7px 10px">
                    </div>
                    <button class="six-btn six-btn-primary six-btn-sm six-add-metric-svc"
                            data-slug="<?php echo esc_attr($slug); ?>"
                            data-client="<?php echo $view_client_id; ?>"
                            style="font-size:11px;padding:8px 12px">Save</button>
                </div>
                <div class="metric-result-<?php echo esc_attr($slug); ?>" style="font-size:11px;margin-top:6px"></div>
            </div>

        </div>
        <?php endif; // active svc ?>
    </div>
    <?php endforeach; // svc_def ?>

    <!-- ═══════════════════════ CTAB: AI STRATEGY ═══════════════════════ -->
    <?php elseif($ctab==='ai'): ?>

    <!-- AI Generator -->
    <div style="background:var(--dark2);border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:20px">
        <div style="padding:16px 18px;border-bottom:1px solid rgba(255,255,255,0.05)">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
                <span style="font-size:13px;font-weight:700"> AI Strategy Generator</span>
                <span style="font-size:10px;background:rgba(255,102,153,0.1);color:var(--pink);padding:2px 8px;border-radius:10px"><?php echo esc_html($c_biz); ?></span>
            </div>
            <?php
            $c_metrics_ctx=''; foreach((array)$c_metrics as $m) $c_metrics_ctx.=$m->label.':'.$m->current_value.'; ';
            $ai_context = 'Client: ' . $c_biz
                . ', Industry: ' . ($c_industry ?: 'unknown')
                . ', Goal: ' . ($c_goal ?: 'grow business')
                . ', Challenge: ' . ($c_challenge ?: 'unknown')
                . ', Active Services: ' . ($c_active_svc_names ?: 'none')
                . ($c_total_budget ? ', Budget: $' . number_format($c_total_budget) . '/mo' : '')
                . ', Metrics: ' . ($c_metrics_ctx ?: 'none')
                . ', Competitors: ' . ($c_comp_str ?: 'unknown')
                . ', Website: ' . ($c_website ?: 'unknown')
                . '. Available services to upsell: ' . ($c_missing_svcs ?: 'none') . '.';
            $strategy_types = array(
                'seo_strategy'    => array('label'=>' SEO Optimization','prompt'=>"You are a 6ix Developers SEO strategist. {$ai_context} Write a specific 3-point SEO strategy for this client. Each point: what to do, expected traffic impact (%), timeframe. Start directly — no intro. Use → bullets."),
                'gads_strategy'   => array('label'=>' Google Ads Optimization','prompt'=>"You are a 6ix Developers Google Ads specialist. {$ai_context} Write a 3-point Google Ads optimization plan. Each point: tactic, expected ROAS or CPA improvement, timeframe. Numbers required. Use → bullets."),
                'gbp_strategy'    => array('label'=>'Google Business Profile','prompt'=>"You are a 6ix Developers local SEO specialist. {$ai_context} Write a 3-point Google Business Profile optimisation plan. Each point: specific action, expected local search impact, timeframe. Use → bullets."),
                'web_strategy'    => array('label'=>'Website Growth','prompt'=>"You are a 6ix Developers web strategist. {$ai_context} Write a 3-point website conversion improvement plan. Each point: specific change, expected conversion lift (%), implementation time. Use → bullets."),
                'quick_wins'      => array('label'=>' Quick Wins','prompt'=>"You are a 6ix Developers growth advisor. {$ai_context} Give 3 quick wins this client can achieve THIS month. Each win: specific action (1 sentence), the 6ix service that enables it, measurable 30-day result. Use → bullets."),
                'competitor_alert'=> array('label'=>' Competitor Alerts','prompt'=>"You are a 6ix Developers competitive intelligence advisor. {$ai_context} Identify 3 specific competitor threats and what this client must do NOW. Each: the threat, what competitor is doing, the counter-move using a 6ix service. Use → bullets."),
                'roi_opportunity' => array('label'=>' ROI Opportunities','prompt'=>"You are a 6ix Developers ROI strategist. {$ai_context} Identify 3 high-ROI opportunities being missed. Each: the opportunity, realistic ROI projection with numbers, 6ix service that captures it. Use → bullets."),
                'service_gaps'    => array('label'=>' Service Gaps','prompt'=>"You are a 6ix Developers account strategist. {$ai_context} Identify the 3 most critical missing services and their revenue impact. Each: missing service, what revenue/growth it would unlock (with numbers), urgency level. Use → bullets."),
                'budget_optimize' => array('label'=>' Budget Optimization','prompt'=>"You are a 6ix Developers budget strategist. {$ai_context} Give 3 specific budget reallocation recommendations. Each: current vs recommended allocation, expected performance improvement (%), rationale. Use → bullets."));
            ?>
            <div style="display:flex;flex-wrap:wrap;gap:6px">
                <?php foreach($strategy_types as $type_key => $type_def): ?>
                <button class="six-btn six-btn-ghost six-btn-sm six-ai-suggest-btn"
                        data-type="<?php echo esc_attr($type_key); ?>"
                        data-client="<?php echo $view_client_id; ?>"
                        data-prompt="<?php echo esc_attr($type_def['prompt']); ?>"
                        style="font-size:11px;padding:6px 12px">
                    <?php echo esc_html($type_def['label']); ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Output area -->
        <div style="padding:16px 18px">
            <div id="ai-suggest-output" style="display:none;margin-bottom:14px">
                <div id="ai-suggest-text" style="font-size:13px;color:var(--text2);line-height:1.8;background:var(--dark4);border-radius:10px;padding:14px;min-height:80px;margin-bottom:12px;border-left:3px solid var(--pink)"></div>
                <div style="display:grid;grid-template-columns:1fr 1fr auto auto;gap:8px;align-items:end">
                    <div>
                        <div style="font-size:10px;color:var(--text3);margin-bottom:4px">Title</div>
                        <input class="six-input" id="ai-suggest-title" style="font-size:12px" placeholder="Recommendation title…">
                    </div>
                    <div>
                        <div style="font-size:10px;color:var(--text3);margin-bottom:4px">Priority</div>
                        <select class="six-input" id="ai-suggest-priority" style="font-size:12px">
                            <option value="high"> High</option>
                            <option value="medium" selected> Medium</option>
                            <option value="low"> Low</option>
                        </select>
                    </div>
                    <div style="align-self:end">
                        <button class="six-btn six-btn-primary six-btn-sm" id="ai-suggest-send"
                                data-client="<?php echo $view_client_id; ?>"
                                style="font-size:11px;padding:8px 14px">Send to Client</button>
                    </div>
                    <div style="align-self:end">
                        <button class="six-btn six-btn-ghost six-btn-sm" id="ai-suggest-clear" style="font-size:11px">Clear</button>
                    </div>
                </div>
            </div>
            <div id="ai-suggest-loading" style="display:none"><div class="six-ai-loading"><span class="six-ai-spinner"></span> <span style="font-size:12px;color:var(--text3)">Generating strategy…</span></div></div>
            <div id="ai-suggest-placeholder" style="text-align:center;padding:24px;color:var(--text3)">
                <div style="font-size:24px;margin-bottom:8px"></div>
                <div style="font-size:13px;font-weight:600;margin-bottom:4px">Select a strategy type above</div>
                <div style="font-size:12px">AI will generate a personalised strategy based on <?php echo esc_html($c_biz); ?>'s data</div>
            </div>
        </div>
    </div>

    <!-- Active recommendations with edit/delete -->
    <?php if(!empty($c_ai_pending)): ?>
    <div style="background:var(--dark2);border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:20px">
        <div style="padding:14px 18px;border-bottom:1px solid rgba(255,255,255,0.05);display:flex;align-items:center;justify-content:space-between">
            <span style="font-size:13px;font-weight:700">Active Recommendations <span style="font-size:11px;color:var(--pink);background:rgba(255,102,153,0.1);padding:2px 8px;border-radius:10px;margin-left:4px"><?php echo count($c_ai_pending); ?></span></span>
        </div>
        <?php foreach($c_ai_pending as $rec):
            $pr=$rec->priority??'medium';
            $pr_color=$pr==='high'?'var(--danger)':($pr==='medium'?'var(--warning)':'var(--success)');
        ?>
        <div id="adv-rec-<?php echo $rec->id; ?>" style="padding:14px 18px;border-bottom:1px solid rgba(255,255,255,0.04);min-width:0">
            <div style="display:flex;align-items:flex-start;gap:10px;flex-wrap:wrap">
                <div style="flex:1;min-width:0">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap">
                        <span style="font-size:13px;font-weight:700"><?php echo esc_html($rec->title); ?></span>
                        <span style="font-size:9px;font-weight:700;text-transform:uppercase;color:<?php echo $pr_color; ?>;background:<?php echo $pr_color; ?>15;padding:2px 7px;border-radius:8px"><?php echo esc_html($pr); ?></span>
                        <span style="font-size:10px;color:var(--text3)"><?php echo esc_html($rec->source??''); ?></span>
                    </div>
                    <div style="font-size:12px;color:var(--text2);line-height:1.65;word-wrap:break-word"><?php echo esc_html($rec->description); ?></div>
                </div>
                <div style="display:flex;gap:6px;flex-shrink:0">
                    <button class="six-btn six-btn-ghost six-btn-sm six-adv-edit-rec" data-rec-id="<?php echo $rec->id; ?>"
                            data-title="<?php echo esc_attr($rec->title); ?>"
                            data-desc="<?php echo esc_attr($rec->description); ?>"
                            style="font-size:11px;color:var(--cyan)">Edit</button>
                    <button class="six-btn six-btn-ghost six-btn-sm six-adv-delete-rec" data-rec-id="<?php echo $rec->id; ?>" style="font-size:11px;color:var(--danger)">Delete</button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Approved recs -->
    <?php if(!empty($c_ai_approved)): ?>
    <div style="background:var(--dark2);border:1px solid rgba(86,211,100,0.15);border-radius:14px;overflow:hidden;margin-bottom:20px">
        <div style="padding:14px 18px;border-bottom:1px solid rgba(255,255,255,0.05)">
            <span style="font-size:13px;font-weight:700;color:var(--success)">Approved by Client <span style="font-size:11px;color:var(--text3);font-weight:400">(<?php echo count($c_ai_approved); ?>)</span></span>
        </div>
        <?php foreach($c_ai_approved as $rec): ?>
        <div style="padding:12px 18px;border-bottom:1px solid rgba(255,255,255,0.04);min-width:0">
            <div style="font-size:12px;font-weight:600;margin-bottom:2px"><?php echo esc_html($rec->title); ?></div>
            <div style="font-size:11px;color:var(--text3);word-wrap:break-word"><?php echo esc_html(substr($rec->description,0,120)); ?>…</div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Add recommendation manually -->
    <div style="background:var(--dark2);border:1px solid var(--border);border-radius:14px;padding:16px 18px">
        <div style="font-size:12px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:12px">+ Manual Recommendation</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">
            <div>
                <div style="font-size:10px;color:var(--text3);margin-bottom:4px">Title</div>
                <input class="six-input" id="rec-title" placeholder="e.g. Increase Google Ads Budget" style="font-size:12px">
            </div>
            <div>
                <div style="font-size:10px;color:var(--text3);margin-bottom:4px">Priority</div>
                <select class="six-input" id="rec-priority" style="font-size:12px">
                    <option value="high"> High</option>
                    <option value="medium" selected> Medium</option>
                    <option value="low"> Low</option>
                </select>
            </div>
        </div>
        <div style="margin-bottom:10px">
            <div style="font-size:10px;color:var(--text3);margin-bottom:4px">Description</div>
            <textarea class="six-input" id="rec-desc" rows="3" placeholder="Detailed recommendation…" style="font-size:12px;resize:vertical"></textarea>
        </div>
        <div style="display:flex;gap:8px;align-items:center">
            <div style="flex:1">
                <div style="font-size:10px;color:var(--text3);margin-bottom:4px">Action Label (optional)</div>
                <input class="six-input" id="rec-action" placeholder="e.g. Approve Budget Increase" style="font-size:12px">
            </div>
            <button class="six-btn six-btn-primary six-btn-sm" id="six-add-rec-btn" data-client="<?php echo $view_client_id; ?>" style="font-size:11px;align-self:flex-end;padding:9px 16px">Send Recommendation</button>
        </div>
        <div id="rec-result" style="font-size:12px;margin-top:8px"></div>
    </div>

    <!-- ═══════════════════════ CTAB: DATA SOURCES ═══════════════════════ -->
    <?php elseif($ctab==='datasources'): ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">

        <!-- Google Ads -->
        <div style="background:var(--dark2);border:1px solid rgba(66,133,244,0.2);border-radius:14px;overflow:hidden">
            <div style="padding:14px 18px;border-bottom:1px solid rgba(255,255,255,0.05);display:flex;align-items:center;gap:8px">
                <span style="font-size:16px"></span>
                <span style="font-size:13px;font-weight:700">Google Ads</span>
                <?php if($c_gads): ?><span style="font-size:10px;background:rgba(86,211,100,0.1);color:var(--success);padding:2px 8px;border-radius:10px;font-weight:700">● Connected</span><?php endif; ?>
            </div>
            <div style="padding:14px 18px">
                <?php if(!$mcc_configured): ?>
                <div style="font-size:12px;color:var(--text3);margin-bottom:10px">⚠ <a href="?tab=gads" style="color:var(--warning)">Configure MCC credentials first →</a></div>
                <?php endif; ?>
                <div style="margin-bottom:10px">
                    <div style="font-size:10px;color:var(--text3);margin-bottom:4px">Customer ID</div>
                    <input class="six-input" id="gads-cid" value="<?php echo esc_attr($c_gads); ?>" placeholder="123-456-7890" style="font-size:12px;font-family:monospace">
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <button class="six-btn six-btn-primary six-btn-sm" id="save-gads-cid" data-client="<?php echo $view_client_id; ?>" style="font-size:11px">Save & Connect</button>
                    <?php if($c_gads): ?>
                    <button class="six-btn six-btn-ghost six-btn-sm" id="sync-gads-now" data-client="<?php echo $view_client_id; ?>" style="font-size:11px">↻ Sync Now</button>
                    <?php if($c_sync): ?><span style="font-size:11px;color:var(--text3);align-self:center">Last: <?php echo human_time_diff(strtotime($c_sync),time()); ?> ago</span><?php endif; ?>
                    <?php endif; ?>
                </div>
                <div id="gads-result" style="margin-top:8px;font-size:12px"></div>
            </div>
        </div>

        <!-- Google Analytics 4 -->
        <div style="background:var(--dark2);border:1px solid rgba(66,133,244,0.2);border-radius:14px;overflow:hidden">
            <div style="padding:14px 18px;border-bottom:1px solid rgba(255,255,255,0.05);display:flex;align-items:center;gap:8px">
                <span style="font-size:16px"></span>
                <span style="font-size:13px;font-weight:700">Google Analytics 4</span>
                <?php if($c_ga4_id): ?><span style="font-size:10px;background:rgba(86,211,100,0.1);color:var(--success);padding:2px 8px;border-radius:10px;font-weight:700">● Connected</span><?php endif; ?>
            </div>
            <div style="padding:14px 18px">
                <div style="margin-bottom:10px">
                    <div style="font-size:10px;color:var(--text3);margin-bottom:4px">GA4 Property ID</div>
                    <input class="six-input" id="ga4-property-id" value="<?php echo esc_attr($c_ga4_id); ?>" placeholder="123456789" style="font-size:12px;font-family:monospace">
                    <div style="font-size:10px;color:var(--text3);margin-top:3px">9-digit number from GA4 Admin → Property Settings</div>
                </div>
                <button class="six-btn six-btn-primary six-btn-sm" id="save-ga4-id" data-client="<?php echo $view_client_id; ?>" style="font-size:11px">Save Property ID</button>
                <div id="ga4-result" style="margin-top:8px;font-size:12px"></div>
            </div>
        </div>

        <!-- Meta Ads -->
        <div style="background:var(--dark2);border:1px solid rgba(24,119,242,0.2);border-radius:14px;overflow:hidden">
            <div style="padding:14px 18px;border-bottom:1px solid rgba(255,255,255,0.05);display:flex;align-items:center;gap:8px">
                <span style="font-size:16px"></span>
                <span style="font-size:13px;font-weight:700">Meta Ads</span>
                <?php if($c_meta_account): ?><span style="font-size:10px;background:rgba(86,211,100,0.1);color:var(--success);padding:2px 8px;border-radius:10px;font-weight:700">● Connected</span><?php endif; ?>
            </div>
            <div style="padding:14px 18px">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px">
                    <div>
                        <div style="font-size:10px;color:var(--text3);margin-bottom:4px">Business ID</div>
                        <input class="six-input" id="meta-business-id" value="<?php echo esc_attr($c_meta_business); ?>" placeholder="1234567890" style="font-size:12px;font-family:monospace">
                    </div>
                    <div>
                        <div style="font-size:10px;color:var(--text3);margin-bottom:4px">Ad Account ID</div>
                        <input class="six-input" id="meta-account-id" value="<?php echo esc_attr($c_meta_account); ?>" placeholder="act_1234567890" style="font-size:12px;font-family:monospace">
                    </div>
                    <div>
                        <div style="font-size:10px;color:var(--text3);margin-bottom:4px">Pixel ID</div>
                        <input class="six-input" id="meta-pixel-id" value="<?php echo esc_attr($c_meta_pixel); ?>" placeholder="1234567890" style="font-size:12px;font-family:monospace">
                    </div>
                </div>
                <button class="six-btn six-btn-primary six-btn-sm" id="save-meta-ids" data-client="<?php echo $view_client_id; ?>" style="font-size:11px">Save Meta IDs</button>
                <div id="meta-result" style="margin-top:8px;font-size:12px"></div>
            </div>
        </div>

        
    </div>

    <!-- ═══════════════════════ CTAB: ACTIVITY ═══════════════════════ -->
    <?php elseif($ctab==='activity'): ?>

    <?php
    // Build unified activity feed
    $activity = array();
    foreach((array)$c_svcs as $s){
        $activity[]=array('time'=>strtotime($s->created_at??'now'),'icon'=>'◈','color'=>'var(--cyan)','text'=>"Service request: <strong>".esc_html($s->service_name)."</strong> — ".ucfirst($s->status));
    }
    foreach((array)$c_recs as $r){
        $activity[]=array('time'=>strtotime($r->created_at??'now'),'icon'=>'','color'=>'var(--pink)','text'=>"Recommendation: <strong>".esc_html($r->title)."</strong> — ".ucfirst($r->status));
    }
    foreach((array)$c_reports as $r){
        $activity[]=array('time'=>strtotime($r->created_at??'now'),'icon'=>'','color'=>'var(--success)','text'=>"Report uploaded: <strong>".esc_html($r->title)."</strong>");
    }
    usort($activity, function($a,$b){ return $b['time'] - $a['time']; });
    ?>

    <div style="background:var(--dark2);border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:20px">
        <div style="padding:14px 18px;border-bottom:1px solid rgba(255,255,255,0.05)">
            <span style="font-size:13px;font-weight:700">Activity Timeline</span>
        </div>
        <?php if(empty($activity)): ?>
        <div style="padding:32px;text-align:center;color:var(--text3);font-size:13px">No activity yet for this client.</div>
        <?php else: ?>
        <div style="padding:14px 18px">
        <?php foreach($activity as $act): ?>
        <div style="display:flex;gap:12px;align-items:flex-start;margin-bottom:14px">
            <div style="width:30px;height:30px;border-radius:8px;background:<?php echo $act['color']; ?>15;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0"><?php echo $act['icon']; ?></div>
            <div style="flex:1;min-width:0;padding-top:4px">
                <div style="font-size:12px;line-height:1.6"><?php echo $act['text']; ?></div>
                <div style="font-size:10px;color:var(--text3);margin-top:2px"><?php echo human_time_diff($act['time'],time()); ?> ago</div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Budget change requests log -->
    <div style="background:var(--dark2);border:1px solid var(--border);border-radius:14px;overflow:hidden">
        <div style="padding:14px 18px;border-bottom:1px solid rgba(255,255,255,0.05)">
            <span style="font-size:13px;font-weight:700">Budget Change Requests</span>
        </div>
        <div style="padding:0">
        <?php $has_reqs=false;
        foreach($c_budget_reqs as $row){
            $rd=maybe_unserialize($row->req_data);
            if(!is_array($rd))continue; $has_reqs=true;
            $sd2=$svc_def[$row->service_slug]??array('icon'=>'⚙','color'=>'var(--pink)');
            $status=$rd['status']??'pending';
            $sc=$status==='approved'?'var(--success)':($status==='declined'?'var(--danger)':'var(--warning)');
        ?>
        <div style="padding:12px 18px;border-bottom:1px solid rgba(255,255,255,0.04);display:flex;align-items:center;gap:12px;flex-wrap:wrap">
            <span style="font-size:13px"><?php echo $sd2['icon']; ?></span>
            <span style="font-size:12px;font-weight:600"><?php echo esc_html($row->service_name); ?></span>
            <span style="font-size:12px;color:var(--text3)">$<?php echo number_format($row->budget,0); ?> → $<?php echo number_format($rd['requested_budget']??0,0); ?>/mo</span>
            <span style="margin-left:auto;font-size:11px;color:<?php echo $sc; ?>;font-weight:700;text-transform:capitalize"><?php echo $status; ?></span>
            <?php if($status==='pending'): ?>
            <div style="display:flex;gap:6px">
                <button class="six-btn six-btn-primary six-btn-sm six-adv-approve-budget" data-service-id="<?php echo $row->id; ?>" data-budget="<?php echo intval($rd['requested_budget']??0); ?>" style="font-size:11px"></button>
                <button class="six-btn six-btn-ghost six-btn-sm six-adv-decline-budget" data-service-id="<?php echo $row->id; ?>" style="font-size:11px"></button>
            </div>
            <?php endif; ?>
        </div>
        <?php } if(!$has_reqs): ?>
        <div style="padding:24px;text-align:center;color:var(--text3);font-size:12px">No budget change requests.</div>
        <?php endif; ?>
        </div>
    </div>

    <!-- ═══════════════════════ CTAB: CLIENT PROFILE ═══════════════════════ -->
    <?php elseif($ctab==='profile'): ?>

    <div id="adv-profile-saved" style="display:none;background:rgba(86,211,100,0.1);border:1px solid rgba(86,211,100,0.3);color:var(--success);padding:12px 16px;border-radius:8px;margin-bottom:14px;font-size:13px">Profile updated.</div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
        <div style="background:var(--dark2);border:1px solid var(--border);border-radius:14px;padding:18px">
            <div style="font-size:12px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:14px">Personal Information</div>
            <div style="display:flex;flex-direction:column;gap:10px">
                <div><div style="font-size:10px;color:var(--text3);margin-bottom:4px">First Name</div><input class="six-input" id="adv-prof-first" value="<?php echo esc_attr($c_checkout->first_name??$view_client->first_name??get_user_meta($view_client_id,'first_name',true)??''); ?>" style="font-size:12px"></div>
                <div><div style="font-size:10px;color:var(--text3);margin-bottom:4px">Last Name</div><input class="six-input" id="adv-prof-last" value="<?php echo esc_attr($c_checkout->last_name??$view_client->last_name??get_user_meta($view_client_id,'last_name',true)??''); ?>" style="font-size:12px"></div>
                <div><div style="font-size:10px;color:var(--text3);margin-bottom:4px">Email</div><input class="six-input" value="<?php echo esc_attr($view_client->user_email); ?>" style="font-size:12px;opacity:0.5" readonly></div>
                <div><div style="font-size:10px;color:var(--text3);margin-bottom:4px">Phone</div><input class="six-input" id="adv-prof-phone" value="<?php echo esc_attr(get_user_meta($view_client_id,'billing_phone',true)); ?>" style="font-size:12px"></div>
            </div>
        </div>
        <div style="background:var(--dark2);border:1px solid var(--border);border-radius:14px;padding:18px">
            <div style="font-size:12px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:14px">Business Information</div>
            <div style="display:flex;flex-direction:column;gap:10px">
                <div><div style="font-size:10px;color:var(--text3);margin-bottom:4px">Business Name</div><input class="six-input" id="adv-prof-biz" value="<?php echo esc_attr($c_biz); ?>" style="font-size:12px"></div>
                <div><div style="font-size:10px;color:var(--text3);margin-bottom:4px">Website</div><input class="six-input" id="adv-prof-website" value="<?php echo esc_attr($c_website); ?>" style="font-size:12px"></div>
                <div><div style="font-size:10px;color:var(--text3);margin-bottom:4px">Industry</div><input class="six-input" id="adv-prof-industry" value="<?php echo esc_attr($c_industry); ?>" style="font-size:12px"></div>
                <div><div style="font-size:10px;color:var(--text3);margin-bottom:4px">Address</div><input class="six-input" id="adv-prof-address" value="<?php echo esc_attr($c_address?:$c_location); ?>" style="font-size:12px"></div>
                <div><div style="font-size:10px;color:var(--text3);margin-bottom:4px">Years in Business</div><input class="six-input" id="adv-prof-years" value="<?php echo esc_attr($c_years); ?>" style="font-size:12px"></div>
            </div>
        </div>
    </div>
    <!-- Service budgets -->
    <div style="background:var(--dark2);border:1px solid var(--border);border-radius:14px;padding:18px;margin-bottom:16px">
        <div style="font-size:12px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:14px">Service Budgets</div>
        <?php foreach($c_active_svcs_arr as $s):
            $sd2=$svc_def[$s->service_slug]??array('icon'=>'⚙','color'=>'var(--pink)'); ?>
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
            <span style="font-size:13px;flex-shrink:0"><?php echo $sd2['icon']; ?></span>
            <span style="font-size:12px;font-weight:600;min-width:160px"><?php echo esc_html($s->service_name); ?></span>
            <input class="six-input adv-svc-budget-input" data-service-id="<?php echo $s->id; ?>" value="<?php echo esc_attr(intval($s->budget)); ?>" type="number" min="0" style="font-size:12px;max-width:140px">
            <span style="font-size:11px;color:var(--text3)">/mo</span>
            <button class="six-btn six-btn-primary six-btn-sm six-adv-set-budget" data-service-id="<?php echo $s->id; ?>" data-client="<?php echo $view_client_id; ?>" style="font-size:11px">Update</button>
        </div>
        <?php endforeach; ?>
    </div>
    <div style="display:flex;justify-content:flex-end">
        <button class="six-btn six-btn-primary" id="adv-save-profile-btn" data-client="<?php echo $view_client_id; ?>" style="font-size:13px;padding:10px 28px"> Save Profile</button>
    </div>


    <!-- ═══════════════════════ COMPLETE ONBOARDING PANEL ═══════════════════════ -->
    <?php
    $ob_step      = intval( get_user_meta($view_client_id, 'six_checkout_step', true) ?: 0 );
    $ob_completed = intval( get_user_meta($view_client_id, 'six_checkout_completed', true) );
    $ob_sched_date = $c_checkout->schedule_call_date ?? '';
    $ob_sched_time = $c_checkout->schedule_call_time ?? '';
    ?>
    <?php if( ! $ob_completed ): ?>
    <div style="background:var(--dark2);border:1.5px solid var(--warning-border,#f59e0b40);border-radius:14px;padding:20px;margin-bottom:16px">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
            <div style="width:8px;height:8px;border-radius:50%;background:#f59e0b;flex-shrink:0"></div>
            <div style="font-size:13px;font-weight:700;color:var(--text1)">Onboarding Incomplete</div>
            <div style="margin-left:auto;font-size:11px;color:var(--text3)">Step <?php echo $ob_step; ?> of 5</div>
        </div>

        <?php if($ob_sched_date): ?>
        <div style="background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.25);border-radius:10px;padding:12px 14px;margin-bottom:14px;font-size:12.5px;color:var(--text2)">
            <strong>Call requested:</strong> <?php echo esc_html($ob_sched_date); ?> &middot; <?php echo esc_html($ob_sched_time); ?>
            <?php if($c_checkout->schedule_call_notes ?? ''): ?>
            <div style="margin-top:4px;color:var(--text3)"><?php echo esc_html($c_checkout->schedule_call_notes); ?></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Complete onboarding form for advisor -->
        <div style="font-size:12px;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:.6px;margin-bottom:12px">Complete Onboarding for Customer</div>

        <div style="margin-bottom:14px">
            <label style="font-size:11px;color:var(--text3);display:block;margin-bottom:8px">Services <span style="font-weight:400">(select all that apply)</span></label>
            <div style="display:flex;flex-direction:column;gap:6px" id="adv-ob-svc-list">
                <?php
                $plats = $c_checkout->platforms ?? '';
                foreach([
                    'google-ads'      => 'Google Ads',
                    'seo'             => 'SEO',
                    'google-business' => 'Google Business Profile',
                    'website'         => 'Website Development',
                ] as $slug => $label):
                    $checked = strpos($plats, $slug) !== false ? 'checked' : '';
                ?>
                <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;padding:8px 10px;border:1px solid var(--border);border-radius:8px;background:var(--dark3)">
                    <input type="checkbox" class="adv-ob-svc-check" value="<?php echo $slug;?>" <?php echo $checked;?> style="width:14px;height:14px;accent-color:var(--pink)">
                    <?php echo $label;?>
                </label>
                <?php endforeach;?>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
            <div>
                <label style="font-size:11px;color:var(--text3);display:block;margin-bottom:4px">Monthly Budget ($)</label>
                <input type="number" id="adv-ob-budget" min="0" step="100"
                    value="<?php echo intval($c_checkout->mktg_budget??0); ?>"
                    style="width:100%;border:1px solid var(--border);border-radius:8px;padding:10px;font-size:13px;background:var(--dark3);color:var(--text1)">
            </div>
            <div>
                <label style="font-size:11px;color:var(--text3);display:block;margin-bottom:4px">Payment Collected?</label>
                <select id="adv-ob-payment" style="width:100%;border:1px solid var(--border);border-radius:8px;padding:10px;font-size:12px;background:var(--dark3);color:var(--text1)">
                    <option value="0">No card on file</option>
                    <option value="offline">Collected offline</option>
                    <option value="waived">Waived by advisor</option>
                </select>
            </div>
        </div>

        <div style="margin-bottom:14px">
            <label style="font-size:11px;color:var(--text3);display:block;margin-bottom:4px">Card / Payment Details <span style="font-weight:400">(if collected offline)</span></label>
            <input type="text" id="adv-ob-card-info" placeholder="e.g. Visa ending 4242, or Interac e-Transfer confirmed"
                style="width:100%;border:1px solid var(--border);border-radius:8px;padding:10px;font-size:12.5px;background:var(--dark3);color:var(--text1);box-sizing:border-box">
        </div>

        <div style="margin-bottom:12px">
            <label style="font-size:11px;color:var(--text3);display:block;margin-bottom:4px">Advisor Notes (internal)</label>
            <textarea id="adv-ob-notes" rows="2"
                style="width:100%;border:1px solid var(--border);border-radius:8px;padding:10px;font-size:12.5px;background:var(--dark3);color:var(--text1);resize:vertical"
                placeholder="Notes about how onboarding was completed…"></textarea>
        </div>

        <div id="adv-ob-complete-msg" style="display:none;font-size:12px;color:var(--success);margin-bottom:10px"></div>

        <button class="six-btn six-btn-primary" style="font-size:13px;width:100%"
            onclick="advCompleteOnboarding(<?php echo $view_client_id; ?>)">
            Mark Onboarding as Completed
        </button>
    </div>
    <?php endif; ?>

    <!-- Add Service + Set Budget (always shown for any client) -->
    <div style="background:var(--dark2);border:1px solid var(--border);border-radius:14px;padding:20px;margin-bottom:16px">
        <div style="font-size:12px;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:.6px;margin-bottom:14px">Services &amp; Budget</div>

        <!-- Current services -->
        <?php foreach($c_svcs as $svc):?>
        <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border)">
            <div style="flex:1;font-size:13px"><?php echo esc_html($svc->service_name);?></div>
            <div style="font-size:12px;color:var(--text3)">$<?php echo number_format($svc->budget);?>/mo</div>
            <input type="number" placeholder="Budget" min="0" step="100"
                value="<?php echo intval($svc->budget);?>"
                style="width:90px;padding:5px 8px;border:1px solid var(--border);border-radius:7px;background:var(--dark3);color:var(--text1);font-size:12px"
                onchange="advUpdateServiceBudget(<?php echo $view_client_id;?>,<?php echo $svc->id;?>,this.value)">
            <span style="font-size:10px;padding:2px 8px;border-radius:20px;background:<?php echo $svc->status==='active'?'rgba(86,211,100,.1)':'rgba(255,165,0,.1)';?>;color:<?php echo $svc->status==='active'?'var(--success)':'var(--warning)';?>"><?php echo ucfirst($svc->status);?></span>
        </div>
        <?php endforeach;?>
        <?php if(empty($c_svcs)):?><div style="font-size:12px;color:var(--text3);padding:8px 0">No services yet.</div><?php endif;?>

        <!-- Add service -->
        <div style="display:flex;gap:8px;align-items:center;margin-top:12px">
            <select id="adv-add-svc-<?php echo $view_client_id;?>"
                style="flex:1;padding:8px;border:1px solid var(--border);border-radius:8px;background:var(--dark3);color:var(--text1);font-size:12px">
                <option value="">Add a service…</option>
                <option value="google-ads">Google Ads</option>
                <option value="seo">SEO</option>
                <option value="google-business">Google Business Profile</option>
                <option value="website">Website Development</option>
            </select>
            <input type="number" id="adv-add-svc-bud-<?php echo $view_client_id;?>" min="0" step="100" placeholder="Budget"
                style="width:90px;padding:8px;border:1px solid var(--border);border-radius:8px;background:var(--dark3);color:var(--text1);font-size:12px">
            <button class="six-btn six-btn-primary six-btn-sm" onclick="advAddService(<?php echo $view_client_id;?>)">Add</button>
        </div>
        <div id="adv-svc-msg-<?php echo $view_client_id;?>" style="font-size:12px;margin-top:6px"></div>
    </div>

    <script>
    function advSearchClients(q){
    q=q.toLowerCase().trim();
    var rows=document.querySelectorAll('#adv-client-table tbody tr[data-name]');
    var visible=0;
    rows.forEach(function(r){
        var name=(r.dataset.name||'').toLowerCase();
        var email=(r.dataset.email||'').toLowerCase();
        var phone=(r.dataset.phone||'').toLowerCase();
        var show=!q||name.includes(q)||email.includes(q)||phone.includes(q);
        r.style.display=show?'':'none';
        if(show)visible++;
    });
    var cnt=document.getElementById('adv-search-count');
    if(cnt)cnt.textContent=q?visible+' result'+(visible!==1?'s':''):'';
}

function advUpdateServiceBudget(clientId,svcId,budget){
    fetch(AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({action:'six_adv_update_service_budget',nonce:NONCE,client_id:clientId,service_id:svcId,budget:budget})
    }).then(r=>r.json()).then(d=>{
        if(!d.success)alert(d.data||'Error updating budget');
    });
}

function advAddService(clientId){
    var slug=document.getElementById('adv-add-svc-'+clientId).value;
    var budget=document.getElementById('adv-add-svc-bud-'+clientId).value||0;
    var msg=document.getElementById('adv-svc-msg-'+clientId);
    if(!slug){if(msg)msg.textContent='Please select a service.';return;}
    if(msg)msg.textContent='Saving…';
    fetch(AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({action:'six_adv_add_client_service',nonce:NONCE,client_id:clientId,service_slug:slug,budget:budget})
    }).then(r=>r.json()).then(d=>{
        if(d.success){if(msg)msg.textContent='Service added.';setTimeout(()=>location.reload(),800);}
        else{if(msg)msg.textContent=d.data||'Error';}
    });
}

function advCompleteOnboarding(clientId){
        var btn = event.target;
        var services = Array.from(document.querySelectorAll('.adv-ob-svc-check:checked')).map(o=>o.value).join(',');
        var budget   = document.getElementById('adv-ob-budget').value;
        var payment  = document.getElementById('adv-ob-payment').value;
        var notes    = document.getElementById('adv-ob-notes').value;
        if(!services){alert('Please select at least one service.');return;}
        btn.textContent='Saving…';btn.disabled=true;
        fetch(AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:new URLSearchParams({
                action:'six_advisor_complete_onboarding',
                nonce:NONCE,
                client_id:clientId,
                services:services,
                budget:budget,
                payment_method:payment,
                card_info:(document.getElementById('adv-ob-card-info')||{}).value||'',
                notes:notes,
            })
        }).then(r=>r.json()).then(d=>{
            btn.textContent='Mark Onboarding as Completed';btn.disabled=false;
            if(d.success){
                document.getElementById('adv-ob-complete-msg').style.display='block';
                document.getElementById('adv-ob-complete-msg').textContent='Onboarding marked complete. Page will refresh…';
                setTimeout(()=>location.reload(),1500);
            } else {
                alert(d.data||'Error completing onboarding');
            }
        });
    }
    </script>

    <!-- ═══════════════════════ CTAB: QUESTIONNAIRE ═══════════════════════ -->
    <?php elseif($ctab==='questionnaire'): ?>

    <?php
    // Helper to render a field row — only show if value is non-empty
    function adv_qrow($label, $value, $full=false){
        if(!$value||$value==='—') return;
        $style = $full ? 'grid-column:1/-1' : '';
        echo '<div style="'.$style.'">';
        echo '<div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.6px;color:var(--text3);margin-bottom:4px">'.esc_html($label).'</div>';
        echo '<div style="font-size:13px;color:var(--text1);line-height:1.5;word-break:break-word">'.nl2br(esc_html($value)).'</div>';
        echo '</div>';
    }
    function adv_qsec($title, $icon_path=''){
        echo '<div style="display:flex;align-items:center;gap:8px;margin:0 0 14px;padding-bottom:10px;border-bottom:1px solid var(--border)">';
        if($icon_path) echo '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--cyan)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'.$icon_path.'</svg>';
        echo '<span style="font-size:12px;font-weight:700;color:var(--text1);text-transform:uppercase;letter-spacing:.5px">'.$title.'</span>';
        echo '</div>';
    }
    ?>

    <div style="display:flex;flex-direction:column;gap:16px">

    <!-- Business Basics -->
    <div style="background:var(--dark2);border:1px solid var(--border);border-radius:14px;padding:18px">
        <?php adv_qsec('Business Basics','<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>'); ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <?php
            adv_qrow('Business Name',    $c_biz);
            adv_qrow('Website',          $c_website);
            adv_qrow('Industry',         $c_industry);
            adv_qrow('Location',         ($c_address ?: $c_location));
            adv_qrow('Years in Business',$c_years);
            adv_qrow('Phone',            $c_checkout->phone ?? get_user_meta($view_client_id,'billing_phone',true));
            adv_qrow('Employees', $c_employees);
            adv_qrow('Monthly Revenue', $c_revenue);
            adv_qrow('Marketing Goals', $c_goal ? str_replace(',', ', ', $c_goal) : '');
            adv_qrow('Competitors', $c_comp_str, true);
            adv_qrow('CRM / Tools', $c_crm);
            adv_qrow('Reviews / Awards', $c_awards);
            adv_qrow('Additional Notes', $c_notes, true);
            // Only show empty state if there is literally nothing at all
            $basics_empty = !$c_biz && !$c_website && !$c_industry && !$c_address && !$c_location && !$c_comp_str;
            if ($basics_empty):
            ?>
            <div style="grid-column:1/-1;padding:16px;background:rgba(112,201,242,0.06);border:1px solid rgba(112,201,242,0.15);border-radius:10px;font-size:12px;color:var(--text3);line-height:1.6">
                No business info saved yet.
                <a href="?tab=clients&client=<?php echo $view_client_id; ?>&ctab=profile" style="color:var(--cyan);text-decoration:none;font-weight:600"> → Go to Client Profile to add it</a>
                <br><span style="font-size:11px;opacity:.7">Once saved from the Profile tab, it will appear here automatically.</span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if($c_ads_kw || $c_ads_loc || $c_ads_prod): ?>
    <!-- Google Ads -->
    <div style="background:var(--dark2);border:1px solid rgba(66,133,244,0.3);border-radius:14px;padding:18px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid var(--border)">
            <div style="display:flex;align-items:center;gap:8px">
                <div style="width:10px;height:10px;border-radius:50%;background:#4285F4"></div>
                <span style="font-size:12px;font-weight:700;color:var(--text1);text-transform:uppercase;letter-spacing:.5px">Google Ads</span>
                <?php if($c_ads_bud>0): ?><span style="font-size:11px;font-weight:700;color:#4285F4;background:rgba(66,133,244,0.1);border:1px solid rgba(66,133,244,0.2);border-radius:20px;padding:2px 9px">$<?php echo number_format($c_ads_bud); ?>/mo</span><?php endif; ?>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <?php
            adv_qrow('Target Locations', $c_ads_loc);
            adv_qrow('Ad Schedule', $c_ads_sched ?? '');
            adv_qrow('Products / Services to Promote', $c_ads_prod, true);
            adv_qrow('Keywords', $c_ads_kw ? str_replace(',', ', ', $c_ads_kw) : '', true);
            adv_qrow('Unique Selling Points', $c_ads_usp, true);
            adv_qrow('Current Promotions', $c_ads_promo, true);
            ?>
        </div>
    </div>
    <?php endif; // end ads budget ?>

    <?php if($c_seo_kw || $c_seo_pages || $c_seo_loc): ?>
    <!-- SEO -->
    <div style="background:var(--dark2);border:1px solid rgba(27,158,82,0.3);border-radius:14px;padding:18px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid var(--border)">
            <div style="display:flex;align-items:center;gap:8px">
                <div style="width:10px;height:10px;border-radius:50%;background:#1B9E52"></div>
                <span style="font-size:12px;font-weight:700;color:var(--text1);text-transform:uppercase;letter-spacing:.5px">SEO</span>
                <?php if($c_seo_bud>0): ?><span style="font-size:11px;font-weight:700;color:#1B9E52;background:rgba(27,158,82,0.1);border:1px solid rgba(27,158,82,0.2);border-radius:20px;padding:2px 9px">$<?php echo number_format($c_seo_bud); ?>/mo</span><?php endif; ?>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <?php
            adv_qrow('Target Locations', $c_seo_loc);
            adv_qrow('Google Search Console', $c_seo_gsc ? ucfirst($c_seo_gsc) : '');
            adv_qrow('Existing Blog / Content', $c_seo_blog ? ucfirst($c_seo_blog) : '');
            adv_qrow('Pages to Rank', $c_seo_pages, true);
            adv_qrow('Target Keywords', $c_seo_kw ? str_replace(',', ', ', $c_seo_kw) : '', true);
            adv_qrow('Unique Selling Points', $c_seo_usp, true);
            adv_qrow('Competitors', $c_seo_comp, true);
            adv_qrow('CRM / Tools', $c_seo_crm, true);
            adv_qrow('Reviews / Awards', $c_seo_reviews, true);
            adv_qrow('Additional Notes', $c_seo_extra, true);
            ?>
        </div>
    </div>
    <?php endif; // end seo budget ?>

    <?php if($c_gbp_name || $c_gbp_cat): ?>
    <!-- Google Business Profile -->
    <div style="background:var(--dark2);border:1px solid rgba(251,188,4,0.3);border-radius:14px;padding:18px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid var(--border)">
            <div style="display:flex;align-items:center;gap:8px">
                <div style="width:10px;height:10px;border-radius:50%;background:#FBBC04"></div>
                <span style="font-size:12px;font-weight:700;color:var(--text1);text-transform:uppercase;letter-spacing:.5px">Google Business Profile</span>
                <?php if($c_gbp_bud>0): ?><span style="font-size:11px;font-weight:700;color:#C17B1A;background:rgba(251,188,4,0.1);border:1px solid rgba(251,188,4,0.2);border-radius:20px;padding:2px 9px">$<?php echo number_format($c_gbp_bud); ?>/mo</span><?php endif; ?>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <?php
            adv_qrow('Business Name on Google', $c_gbp_name);
            adv_qrow('Primary Category', $c_gbp_cat);
            adv_qrow('Business Hours', $c_gbp_hrs ?? '');
            adv_qrow('Current Rating', $c_gbp_rating);
            adv_qrow('Services to Highlight', $c_gbp_svcs, true);
            ?>
        </div>
    </div>
    <?php endif; // end gbp budget ?>

    <?php if($c_web_goal || $c_web_pages): ?>
    <!-- Website -->
    <div style="background:var(--dark2);border:1px solid rgba(124,92,191,0.3);border-radius:14px;padding:18px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid var(--border)">
            <div style="display:flex;align-items:center;gap:8px">
                <div style="width:10px;height:10px;border-radius:50%;background:#7C5CBF"></div>
                <span style="font-size:12px;font-weight:700;color:var(--text1);text-transform:uppercase;letter-spacing:.5px">Website Development</span>
                <?php if($c_web_bud>0): ?><span style="font-size:11px;font-weight:700;color:#7C5CBF;background:rgba(124,92,191,0.1);border:1px solid rgba(124,92,191,0.2);border-radius:20px;padding:2px 9px">$<?php echo number_format($c_web_bud); ?>/mo</span><?php endif; ?>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <?php
            adv_qrow('Website Goal', $c_web_goal ? str_replace(',', ', ', $c_web_goal) : '');
            adv_qrow('Design Style', $c_web_style ? str_replace(',', ', ', $c_web_style) : '');
            adv_qrow('Existing Website to Redesign', $c_web_exist ? ucfirst($c_web_exist) : '');
            adv_qrow('Reference Sites', $c_web_refs);
            adv_qrow('Pages Needed', $c_web_pages, true);
            ?>
        </div>
    </div>
    <?php endif; // end web budget ?>

    <?php if(!$c_ads_kw && !$c_seo_kw && !$c_gbp_name && !$c_web_goal): ?>
    <div style="background:var(--dark2);border:1px solid var(--border);border-radius:12px;padding:28px;text-align:center;color:var(--text3)">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto 12px;display:block;opacity:.4"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/></svg>
        <div style="font-size:13px;font-weight:600;margin-bottom:4px">No service questionnaire data yet</div>
        <div style="font-size:12px">This client hasn't completed the service-specific questions. Basic business info shows above.</div>
    </div>
    <?php endif; ?>

    </div><!-- /questionnaire cards -->

    <!-- ═══════════════════════ CTAB: REPORTS ═══════════════════════ -->
    <?php elseif($ctab==='reports'): ?>

    <div style="background:var(--dark2);border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:20px">
        <div style="padding:14px 18px;border-bottom:1px solid rgba(255,255,255,0.05)">
            <span style="font-size:13px;font-weight:700">Upload Report</span>
        </div>
        <div style="padding:16px 18px">
            <div style="display:grid;grid-template-columns:1fr auto;gap:10px;align-items:end;margin-bottom:10px">
                <div><div style="font-size:10px;color:var(--text3);margin-bottom:4px">Report Title</div><input class="six-input" id="rpt-title" placeholder="e.g. March 2026 Performance Report" style="font-size:12px"></div>
            </div>
            <div style="margin-bottom:10px">
                <div style="font-size:10px;color:var(--text3);margin-bottom:4px">Upload File (PDF, PNG, JPG — max 10MB)</div>
                <input type="file" id="rpt-file" accept=".pdf,.png,.jpg,.jpeg" class="six-input" style="font-size:12px;padding:6px">
            </div>
            <button class="six-btn six-btn-primary six-btn-sm" id="upload-report-btn" data-client="<?php echo $view_client_id; ?>" style="font-size:12px">Upload Report</button>
            <div id="rpt-result" style="margin-top:8px;font-size:12px"></div>
        </div>
    </div>

    <!-- Past reports -->
    <?php if(!empty($c_reports)): ?>
    <div style="background:var(--dark2);border:1px solid var(--border);border-radius:14px;overflow:hidden">
        <div style="padding:14px 18px;border-bottom:1px solid rgba(255,255,255,0.05)"><span style="font-size:13px;font-weight:700">Uploaded Reports</span></div>
        <?php foreach($c_reports as $rep): ?>
        <div style="padding:12px 18px;border-bottom:1px solid rgba(255,255,255,0.04);display:flex;align-items:center;gap:12px">
            <span style="font-size:18px"></span>
            <div style="flex:1;min-width:0">
                <div style="font-size:12px;font-weight:600"><?php echo esc_html($rep->title); ?></div>
                <div style="font-size:10px;color:var(--text3)"><?php echo date('M j, Y',strtotime($rep->created_at)); ?></div>
            </div>
            <?php if($rep->file_url): ?><a href="<?php echo esc_url($rep->file_url); ?>" target="_blank" class="six-btn six-btn-ghost six-btn-sm" style="font-size:11px">↓ View</a><?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; // end ctab chain ?>

    <?php else: // Client list view ?>

        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
            <h1 class="six-page-title">Clients</h1>
        </div>
        <?php if(empty($clients)): ?>
        <div style="background:var(--dark2);border:1px solid var(--border);border-radius:14px;padding:48px;text-align:center">
            <div style="font-size:36px;margin-bottom:16px"></div>
            <div style="font-size:14px;font-weight:600;margin-bottom:8px">No clients assigned yet</div>
            <p style="font-size:13px;color:var(--text3)">Clients are assigned by an admin. Contact your manager to get clients assigned.</p>
        </div>
        <?php else: ?>
        <!-- Search + filter bar (server-side) -->
        <form method="GET" action="" id="adv-client-search-form" style="display:flex;gap:10px;align-items:center;margin-bottom:14px">
            <input type="hidden" name="tab" value="clients">
            <div style="position:relative;flex:1">
                <svg style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--text3);pointer-events:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" name="csearch" id="adv-client-search"
                    value="<?php echo esc_attr($client_search); ?>"
                    placeholder="Search by name, email or phone…"
                    style="width:100%;padding:8px 12px 8px 34px;border:1px solid var(--border);border-radius:9px;background:var(--dark3);color:var(--text1);font-size:13px;box-sizing:border-box;outline:none"
                    oninput="clearTimeout(window._csrch);window._csrch=setTimeout(()=>this.form.submit(),350)">
            </div>
            <span style="font-size:11px;color:var(--text3);white-space:nowrap"><?php echo $clients_total; ?> client<?php echo $clients_total!==1?'s':''; ?></span>
            <?php if($client_search): ?>
            <a href="?tab=clients" style="font-size:11px;color:var(--pink);text-decoration:none;white-space:nowrap">Clear</a>
            <?php endif; ?>
        </form>
        <!-- Desktop table (hidden on mobile) -->
        <div class="six-client-table-wrap" style="overflow-x:auto">
        <table class="six-table six-client-desktop" id="adv-client-table">
            <thead><tr><th>Client</th><th>Health</th><th>Services</th><th>MRR</th><th>Last Active</th><th></th></tr></thead>
            <tbody>
            <?php foreach($clients as $cl):
                $cl_svcs=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}six_client_services WHERE client_id=%d AND status='active'",$cl->ID));
                $cl_mrr =array_sum(array_column($cl_svcs,'budget'));
                $cl_h   =class_exists('Six_Health_Score')?Six_Health_Score::calculate($cl->ID):0;
                $cl_last=$wpdb->get_var($wpdb->prepare("SELECT MAX(created_at) FROM {$wpdb->prefix}six_recommendations WHERE client_id=%d",$cl->ID));
                $cl_co  =$wpdb->get_row($wpdb->prepare("SELECT business_name FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d",$cl->ID));
                $cl_name = esc_html($cl_co->business_name??$cl->display_name);
                $cl_color = $cl_h>=70?'var(--success)':($cl_h>=40?'var(--warning)':'var(--danger)');
            ?>
            <tr data-name="<?php echo esc_attr(strtolower($cl->display_name.' '.($cl_co->business_name??''))); ?>" data-email="<?php echo esc_attr(strtolower($cl->user_email??'')); ?>" data-phone="<?php echo esc_attr(get_user_meta($cl->ID,'billing_phone',true)); ?>">
                <td>
                    <div style="font-weight:600;font-size:13px"><?php echo $cl_name; ?></div>
                    <div style="font-size:11px;color:var(--text3)"><?php echo esc_html($cl->user_email); ?></div>
                </td>
                <td><span style="font-size:12px;font-weight:700;color:<?php echo $cl_color; ?>"><?php echo $cl_h; ?>%</span></td>
                <td><?php foreach($cl_svcs as $s): $sd2=$svc_def[$s->service_slug]??array('icon'=>'⚙','color'=>'var(--pink)'); ?><span title="<?php echo esc_attr($s->service_name); ?>" style="margin-right:4px"><?php echo $sd2['icon']; ?></span><?php endforeach; ?></td>
                <td style="font-weight:700;color:var(--cyan)">$<?php echo number_format($cl_mrr,0); ?>/mo</td>
                <td style="font-size:11px;color:var(--text3)"><?php echo $cl_last?human_time_diff(strtotime($cl_last),time()).' ago':'Never'; ?></td>
                <td><a href="?tab=clients&client=<?php echo $cl->ID; ?>" class="six-btn six-btn-primary six-btn-sm" style="font-size:11px">Open →</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <!-- Mobile card list (hidden on desktop) -->
        <div class="six-client-cards">
            <?php foreach($clients as $cl):
                $cl_svcs2=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}six_client_services WHERE client_id=%d AND status='active'",$cl->ID));
                $cl_mrr2 =array_sum(array_column($cl_svcs2,'budget'));
                $cl_h2   =class_exists('Six_Health_Score')?Six_Health_Score::calculate($cl->ID):0;
                $cl_co2  =$wpdb->get_row($wpdb->prepare("SELECT business_name FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d",$cl->ID));
                $cl_name2 = esc_html($cl_co2->business_name??$cl->display_name);
                $cl_color2 = $cl_h2>=70?'var(--success)':($cl_h2>=40?'var(--warning)':'var(--danger)');
                $initials2 = strtoupper(substr($cl->display_name,0,1).substr(strrchr($cl->display_name,' '),1,1));
            ?>
            <a href="?tab=clients&client=<?php echo $cl->ID; ?>" class="six-client-card" style="text-decoration:none;color:inherit">
                <div class="six-client-card-avatar"><?php echo esc_html($initials2); ?></div>
                <div class="six-client-card-info">
                    <div class="six-client-card-name"><?php echo $cl_name2; ?></div>
                    <div class="six-client-card-email"><?php echo esc_html($cl->user_email); ?></div>
                    <div class="six-client-card-meta">
                        <?php foreach($cl_svcs2 as $s2): $sd3=$svc_def[$s2->service_slug]??array('icon'=>'⚙'); ?>
                        <span style="font-size:12px"><?php echo $sd3['icon']; ?></span>
                        <?php endforeach; ?>
                        <?php if($cl_mrr2>0): ?>
                        <span class="six-client-card-mrr">$<?php echo number_format($cl_mrr2,0); ?>/mo</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="six-client-card-health" style="color:<?php echo $cl_color2; ?>"><?php echo $cl_h2; ?>%</div>
                <div class="six-client-card-arrow">→</div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Pagination (mobile) -->
        <?php if($clients_total_pages > 1): ?>
        <div style="display:flex;align-items:center;justify-content:center;gap:6px;margin-top:16px;flex-wrap:wrap">
            <?php if($client_page > 1): ?>
            <a href="?tab=clients&csearch=<?php echo urlencode($client_search); ?>&cpage=<?php echo $client_page-1; ?>" class="six-btn six-btn-ghost six-btn-sm">&larr;</a>
            <?php endif; ?>
            <?php for($pg=max(1,$client_page-2); $pg<=min($clients_total_pages,$client_page+2); $pg++): ?>
            <a href="?tab=clients&csearch=<?php echo urlencode($client_search); ?>&cpage=<?php echo $pg; ?>"
               style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;font-size:13px;text-decoration:none;<?php echo $pg===$client_page?'background:var(--pink);color:#fff;border:none;':'background:var(--dark3);color:var(--text2);border:1px solid var(--border);'; ?>">
               <?php echo $pg; ?></a>
            <?php endfor; ?>
            <?php if($client_page < $clients_total_pages): ?>
            <a href="?tab=clients&csearch=<?php echo urlencode($client_search); ?>&cpage=<?php echo $client_page+1; ?>" class="six-btn six-btn-ghost six-btn-sm">&rarr;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php endif; // end empty(clients) if/else ?>
    <?php endif; // end view_client if($view_client) ?>

        <?php /* ════════════ MESSAGES ════════════ */ elseif($active_tab==='messages'):
        $selected_id = isset($_GET['with']) ? intval($_GET['with']) : 0;
        // Get all threads
        $threads = $wpdb->get_results($wpdb->prepare(
            "SELECT u.ID, u.display_name, u.user_email,
                (SELECT COUNT(*) FROM {$wpdb->prefix}six_messages WHERE receiver_id=%d AND sender_id=u.ID AND is_read=0) AS unread,
                (SELECT message FROM {$wpdb->prefix}six_messages WHERE (sender_id=u.ID AND receiver_id=%d) OR (sender_id=%d AND receiver_id=u.ID) ORDER BY created_at DESC LIMIT 1) AS last_msg,
                (SELECT created_at FROM {$wpdb->prefix}six_messages WHERE (sender_id=u.ID AND receiver_id=%d) OR (sender_id=%d AND receiver_id=u.ID) ORDER BY created_at DESC LIMIT 1) AS last_time
             FROM {$wpdb->prefix}six_assignments a
             INNER JOIN {$wpdb->prefix}users u ON a.client_id=u.ID
             WHERE a.advisor_id=%d ORDER BY last_time DESC",
            $advisor_id,$advisor_id,$advisor_id,$advisor_id,$advisor_id,$advisor_id
        ));
        if(!$selected_id && !empty($threads)) $selected_id=$threads[0]->ID;
        $sel_user = $selected_id ? get_userdata($selected_id) : null;
        $conv = ($selected_id&&class_exists('Six_Messaging')) ? Six_Messaging::get_conversation($advisor_id,$selected_id) : array();
    ?>
        <div class="six-page-header"><div><h1 class="six-page-title">Messages</h1></div></div>
        <div style="display:grid;grid-template-columns:260px 1fr;gap:16px;height:calc(100vh - 180px);min-height:420px">

            <!-- Thread list sidebar -->
            <div class="six-card" style="overflow-y:auto;margin-bottom:0;display:flex;flex-direction:column">
                <div class="six-card-header"><span class="six-card-title">Conversations</span></div>
                <div style="flex:1;overflow-y:auto">
                <?php if(empty($threads)):?>
                <div style="padding:20px;text-align:center;color:var(--text3);font-size:12px">No conversations yet.</div>
                <?php else:?>
                <?php foreach($threads as $t):$active=$t->ID===$selected_id;?>
                <a href="?tab=messages&with=<?php echo $t->ID;?>" style="display:flex;align-items:center;gap:10px;padding:12px 14px;text-decoration:none;border-bottom:1px solid var(--border);background:<?php echo $active?'rgba(255,102,153,0.07)':'transparent';?>">
                    <div class="six-client-initials" style="width:34px;height:34px;font-size:11px;flex-shrink:0"><?php echo esc_html(six_get_initials($t->display_name));?></div>
                    <div style="min-width:0;flex:1">
                        <div style="font-size:12px;font-weight:<?php echo $t->unread>0?'700':'600';?>;color:var(--text1);display:flex;justify-content:space-between;align-items:center">
                            <?php echo esc_html($t->display_name);?>
                            <?php if($t->unread>0):?><span class="six-badge"><?php echo $t->unread;?></span><?php endif;?>
                        </div>
                        <?php if($t->last_msg):?><div style="font-size:11px;color:var(--text3);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:160px;margin-top:1px"><?php echo esc_html(wp_trim_words($t->last_msg,6));?></div><?php endif;?>
                    </div>
                </a>
                <?php endforeach;?>
                <?php endif;?>
                </div>
                <!-- Compose new -->
                <div style="padding:12px;border-top:1px solid var(--border)">
                    <div style="font-size:10px;color:var(--text3);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px">New conversation</div>
                    <select class="six-input" id="compose-to" style="font-size:11px;padding:6px 8px;margin-bottom:6px">
                        <option value="">Select client…</option>
                        <?php foreach($clients as $c):?><option value="<?php echo $c->ID;?>"><?php echo esc_html($c->display_name);?></option><?php endforeach;?>
                    </select>
                    <button class="six-btn six-btn-primary six-btn-sm" id="compose-go" style="width:100%;justify-content:center">Start Chat</button>
                </div>
            </div>

            <!-- Conversation pane -->
            <div class="six-card" style="display:flex;flex-direction:column;margin-bottom:0;overflow:hidden">
                <?php if($sel_user):?>
                <div class="six-card-header" style="flex-shrink:0">
                    <div style="display:flex;align-items:center;gap:10px">
                        <div class="six-client-initials" style="width:32px;height:32px;font-size:11px"><?php echo esc_html(six_get_initials($sel_user->display_name));?></div>
                        <div><strong style="font-size:13px"><?php echo esc_html($sel_user->display_name);?></strong><div style="font-size:11px;color:var(--text3)"><?php echo esc_html($sel_user->user_email);?></div></div>
                    </div>
                </div>
                <div id="six-msg-thread" class="six-msg-thread" style="flex:1;height:auto;border-radius:0;margin:0;overflow-y:auto;padding:16px">
                    <?php if(empty($conv)):?><div style="text-align:center;padding:30px;color:var(--text3)">No messages yet — say hello!</div><?php else:?>
                    <?php foreach($conv as $msg):$is_mine=intval($msg->sender_id)===$advisor_id;?>
                    <div class="six-msg <?php echo $is_mine?'mine':'';?>">
                        <div class="six-msg-avatar" style="background:<?php echo $is_mine?'linear-gradient(135deg,var(--pink),#a855f7)':'linear-gradient(135deg,var(--blue),var(--cyan))';?>"><?php echo esc_html(six_get_initials($msg->sender_name));?></div>
                        <div><div class="six-msg-bubble"><?php echo esc_html($msg->message);?></div><div class="six-msg-time"><?php echo human_time_diff(strtotime($msg->created_at),time()).' ago';?></div></div>
                    </div>
                    <?php endforeach;?><?php endif;?>
                </div>
                <div class="six-msg-input-row" style="padding:12px;border-top:1px solid var(--border);flex-shrink:0">
                    <input class="six-msg-input" id="six-msg-input" placeholder="Type a message to <?php echo esc_attr($sel_user->first_name?:$sel_user->display_name);?>…" data-receiver="<?php echo $selected_id;?>">
                    <button class="six-btn six-btn-primary" id="six-msg-send">Send →</button>
                </div>
                <?php else:?>
                <div class="six-card-body" style="text-align:center;padding:60px;color:var(--text3)"><div style="font-size:30px;margin-bottom:12px"></div>Select a conversation or start a new one.</div>
                <?php endif;?>
            </div>
        </div>

    <?php /* ════════════ NOTIFICATIONS ════════════ */ elseif($active_tab==='notifications'):?>
        <?php
        $notif_filter = sanitize_key($_GET['ntype'] ?? 'all');
        $notif_page   = max(1, intval($_GET['npage'] ?? 1));
        $notif_per    = 20;
        $all_notifs   = class_exists('Six_Notifications') ? Six_Notifications::get_for_user($advisor_id, 300) : array();
        $unread_ct    = count(array_filter($all_notifs, fn($n) => !$n->is_read));
        $type_map     = array('all'=>'All','unread'=>'Unread','service'=>'Services','message'=>'Messages','approval'=>'Approvals','activity'=>'Activity');
        $filtered = $notif_filter==='all'    ? $all_notifs :
                   ($notif_filter==='unread' ? array_values(array_filter($all_notifs, fn($n)=>!$n->is_read)) :
                    array_values(array_filter($all_notifs, fn($n)=>($n->type??'activity')===$notif_filter)));
        $total_pages = max(1, ceil(count($filtered)/$notif_per));
        $page_items  = array_slice($filtered, ($notif_page-1)*$notif_per, $notif_per);
        ?>
        <div class="six-page-header">
            <div><h1 class="six-page-title">Notifications</h1>
            <p class="six-page-sub"><?php echo count($all_notifs); ?> total &middot; <?php echo $unread_ct; ?> unread</p></div>
            <?php if($unread_ct>0):?>
            <button class="six-btn six-btn-secondary six-btn-sm" id="mark-all-read">Mark all read</button>
            <?php endif;?>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px">
            <?php foreach($type_map as $k=>$lbl): $active_k=$notif_filter===$k; $badge=($k==='unread'&&$unread_ct)?'('.$unread_ct.')':''; ?>
            <a href="?tab=notifications&ntype=<?php echo $k;?>"
               style="display:inline-flex;align-items:center;padding:5px 14px;border-radius:20px;font-size:12px;font-weight:500;text-decoration:none;
                      <?php echo $active_k?'background:var(--pink);color:#fff;':'background:var(--dark3);color:var(--text2);border:1px solid var(--border);';?>">
               <?php echo $lbl.($badge?' '.$badge:''); ?></a>
            <?php endforeach;?>
        </div>
        <div class="six-card">
        <div class="six-card-body" style="padding:0">
        <?php if(empty($page_items)):?>
        <div style="text-align:center;padding:48px 24px;color:var(--text3)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="32" height="32" style="display:block;margin:0 auto 12px;opacity:.4"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            No notifications<?php echo $notif_filter!=='all'?' here':'';?>.
        </div>
        <?php else: foreach($page_items as $n):
            $nt = $n->type??'activity';
            $icons = array('service'=>'<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
                           'message'=>'<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
                           'approval'=>'<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
                           'activity'=>'<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>');
            $colors = array('service'=>'#4285F4','message'=>'var(--cyan)','approval'=>'var(--pink)','activity'=>'var(--text3)');
            $ipath  = $icons[$nt]??$icons['activity']; $icolor=$colors[$nt]??'var(--text3)';
        ?>
        <div style="display:flex;align-items:flex-start;gap:14px;padding:14px 18px;border-bottom:1px solid var(--border);<?php echo !$n->is_read?'background:rgba(255,102,153,0.03);':'';?>"
             data-notif-id="<?php echo intval($n->id??0);?>">
            <div style="width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;
                        background:<?php echo $n->is_read?'var(--dark3)':'rgba(255,102,153,0.08)';?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="<?php echo $icolor;?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><?php echo $ipath;?></svg>
            </div>
            <div style="flex:1;min-width:0">
                <div style="font-size:13px;font-weight:<?php echo $n->is_read?'400':'600';?>;color:var(--text1);margin-bottom:2px"><?php echo esc_html($n->title??'');?></div>
                <div style="font-size:12px;color:var(--text2);line-height:1.5;margin-bottom:4px"><?php echo esc_html($n->message??'');?></div>
                <div style="font-size:11px;color:var(--text3)"><?php echo isset($n->created_at)?human_time_diff(strtotime($n->created_at)).' ago':'';?></div>
            </div>
            <?php if(!$n->is_read):?>
            <button onclick="markNotifRead(<?php echo intval($n->id??0);?>,this)"
                    style="flex-shrink:0;background:none;border:none;color:var(--text3);font-size:11px;cursor:pointer;padding:4px 8px;border-radius:6px;white-space:nowrap">Mark read</button>
            <?php endif;?>
        </div>
        <?php endforeach; endif;?>
        </div></div>
        <?php if($total_pages>1):?>
        <div style="display:flex;align-items:center;justify-content:center;gap:6px;margin-top:14px">
            <?php if($notif_page>1):?><a href="?tab=notifications&ntype=<?php echo $notif_filter;?>&npage=<?php echo $notif_page-1;?>" class="six-btn six-btn-ghost six-btn-sm">&larr;</a><?php endif;?>
            <?php for($pg=max(1,$notif_page-2);$pg<=min($total_pages,$notif_page+2);$pg++):?>
            <a href="?tab=notifications&ntype=<?php echo $notif_filter;?>&npage=<?php echo $pg;?>"
               style="display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:8px;font-size:12px;text-decoration:none;<?php echo $pg===$notif_page?'background:var(--pink);color:#fff;':'background:var(--dark3);color:var(--text2);border:1px solid var(--border);';?>"><?php echo $pg;?></a>
            <?php endfor;?>
            <?php if($notif_page<$total_pages):?><a href="?tab=notifications&ntype=<?php echo $notif_filter;?>&npage=<?php echo $notif_page+1;?>" class="six-btn six-btn-ghost six-btn-sm">&rarr;</a><?php endif;?>
        </div>
        <?php endif;?>
        <script>
        function markNotifRead(id,btn){
            fetch(AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:new URLSearchParams({action:'six_mark_notification_read',nonce:NONCE,notification_id:id})
            }).then(r=>r.json()).then(d=>{if(d.success){var row=btn.closest('[data-notif-id]');if(row)row.style.background='';btn.remove();}});
        }
        var _mar=document.getElementById('mark-all-read');
        if(_mar)_mar.addEventListener('click',function(){
            fetch(AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:new URLSearchParams({action:'six_mark_all_notifications_read',nonce:NONCE})
            }).then(r=>r.json()).then(d=>{if(d.success)location.reload();});
        });
        </script>


    <?php /* ════════════ APPROVALS ════════════ */ elseif($active_tab==='approvals'):?>
        <div class="six-page-header"><div><h1 class="six-page-title">Approvals</h1><p class="six-page-sub"><?php echo $total_pending;?> pending</p></div></div>

        <!-- Call Requests -->
        <?php
        $call_requests = $wpdb->get_results($wpdb->prepare(
            "SELECT p.user_id, p.schedule_call_date, p.schedule_call_time, p.schedule_call_notes,
                    p.business_name, u.display_name, u.user_email
             FROM {$wpdb->prefix}six_checkout_progress p
             INNER JOIN {$wpdb->prefix}users u ON p.user_id=u.ID
             INNER JOIN {$wpdb->prefix}six_assignments a ON p.user_id=a.client_id
             WHERE a.advisor_id=%d
               AND p.schedule_call_date IS NOT NULL AND p.schedule_call_date != ''
             ORDER BY p.schedule_call_date ASC LIMIT 20",
            $advisor_id
        ));
        ?>
        <?php if(!empty($call_requests)):?>
        <div class="six-card" style="margin-bottom:16px;border-color:rgba(99,102,241,.3)">
            <div class="six-card-header" style="background:rgba(99,102,241,.06)">
                <span class="six-card-title" style="display:flex;align-items:center;gap:8px">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    Call Requests
                </span>
                <span class="six-badge" style="background:rgba(99,102,241,.15);color:#6366f1"><?php echo count($call_requests);?></span>
            </div>
            <div class="six-card-body" style="padding:0">
                <?php foreach($call_requests as $cr):?>
                <div style="display:flex;align-items:center;gap:14px;padding:12px 18px;border-bottom:1px solid var(--border)">
                    <div class="six-client-initials" style="flex-shrink:0"><?php echo strtoupper(substr($cr->display_name,0,1)); ?></div>
                    <div style="flex:1;min-width:0">
                        <div style="font-size:13px;font-weight:600"><?php echo esc_html($cr->display_name);?>
                            <?php if($cr->business_name):?><span style="font-weight:400;color:var(--text3)"> &middot; <?php echo esc_html($cr->business_name);?></span><?php endif;?>
                        </div>
                        <div style="font-size:11px;color:var(--text3);margin-top:2px">
                            <strong><?php echo esc_html($cr->schedule_call_date);?></strong> &middot; <?php echo esc_html($cr->schedule_call_time);?>
                            <?php if($cr->schedule_call_notes):?> &middot; <?php echo esc_html(substr($cr->schedule_call_notes,0,80));?><?php endif;?>
                        </div>
                    </div>
                    <a href="?tab=clients&client=<?php echo $cr->user_id;?>" class="six-btn six-btn-ghost six-btn-sm">View Profile</a>
                </div>
                <?php endforeach;?>
            </div>
        </div>
        <?php endif;?>

        

        <?php if(!empty($budget_requests)):?>
        <div class="six-card">
            <div class="six-card-header"><span class="six-card-title">Budget Change Requests</span></div>
            <div class="six-card-body" style="padding:0">
            <table class="six-table"><thead><tr><th>Client</th><th>Service</th><th>Current</th><th>Requested</th><th>Set Final & Approve</th><th></th></tr></thead><tbody>
            <?php foreach($budget_requests as $br):?>
            <tr id="breq-<?php echo $br['client_id'].'-'.$br['id'];?>">
                <td><strong><?php echo esc_html($br['client_name']);?></strong></td>
                <td><?php echo esc_html($br['service_name']);?></td>
                <td style="color:var(--text3)">$<?php echo number_format(floatval($br['budget']??0),0);?>/mo</td>
                <td style="color:var(--warning);font-weight:700">$<?php echo number_format($br['requested_budget'],0);?>/mo</td>
                <td>
                    <div style="display:flex;gap:6px;align-items:center">
                        <span style="color:var(--text3);font-size:11px">$</span>
                        <input type="number" class="six-input budget-final" value="<?php echo intval($br['requested_budget']);?>" style="width:80px;padding:4px 8px;font-size:12px">
                        <span style="color:var(--text3);font-size:11px">/mo</span>
                        <button class="six-btn six-btn-primary six-btn-sm six-approve-budget"
                                data-client="<?php echo $br['client_id'];?>" data-service="<?php echo $br['id'];?>">Approve</button>
                    </div>
                </td>
                <td><button class="six-btn six-btn-ghost six-btn-sm six-decline-budget" data-client="<?php echo $br['client_id'];?>" data-service="<?php echo $br['id'];?>" style="color:var(--danger)">Decline</button></td>
            </tr>
            <?php endforeach;?>
            </tbody></table>
            </div>
        </div>
        <?php endif;?>
        <?php if($total_pending===0):?><div class="six-card"><div class="six-card-body" style="text-align:center;padding:40px;color:var(--text3)">Nothing pending.</div></div><?php endif;?>

    <?php /* ════════════ REPORTS ════════════ */ elseif($active_tab==='reports'):?>
        <div class="six-page-header"><div><h1 class="six-page-title">Reports</h1></div></div>
        <div class="six-card"><div class="six-card-body">
            <p style="font-size:12px;color:var(--text2)">To upload reports, open a client's page: <a href="?tab=clients">Clients →</a></p>
            <?php $all_rpts=$wpdb->get_results($wpdb->prepare(
                "SELECT r.*,u.display_name AS cname FROM {$wpdb->prefix}six_reports r
                 INNER JOIN {$wpdb->prefix}six_assignments a ON r.client_id=a.client_id AND a.advisor_id=%d
                 INNER JOIN {$wpdb->prefix}users u ON r.client_id=u.ID ORDER BY r.created_at DESC",$advisor_id));?>
            <?php if(empty($all_rpts)):?><div style="text-align:center;padding:30px;color:var(--text3)">No reports yet.</div>
            <?php else:?>
            <table class="six-table" style="margin-top:16px"><thead><tr><th>Client</th><th>Report</th><th>Period</th><th>Date</th><th></th></tr></thead><tbody>
            <?php foreach($all_rpts as $r):?>
            <tr><td><?php echo esc_html($r->cname);?></td><td><strong><?php echo esc_html($r->title);?></strong></td><td><?php echo esc_html($r->period?:'—');?></td><td style="font-size:11px;color:var(--text3)"><?php echo date('M j, Y',strtotime($r->created_at));?></td>
            <td><?php if($r->file_url):?><a href="<?php echo esc_url($r->file_url);?>" class="six-btn six-btn-secondary six-btn-sm" target="_blank">View</a><?php endif;?></td></tr>
            <?php endforeach;?>
            </tbody></table>
            <?php endif;?>
        </div></div>

    <?php /* ════════════ REVENUE ════════════ */ elseif($active_tab==='revenue'):?>
        <div class="six-page-header"><div><h1 class="six-page-title">Revenue Pipeline</h1></div></div>
        <div class="six-stats-grid">
            <div class="six-stat-card pink"><div class="six-stat-label">Total MRR</div><div class="six-stat-val">$<?php echo number_format($total_mrr,0);?></div></div>
            <div class="six-stat-card cyan"><div class="six-stat-label">Active Clients</div><div class="six-stat-val"><?php echo count($clients);?></div></div>
            <div class="six-stat-card blue"><div class="six-stat-label">Avg Client Value</div><div class="six-stat-val">$<?php echo count($clients)>0?number_format($total_mrr/count($clients),0):0;?></div></div>
            <div class="six-stat-card green"><div class="six-stat-label">Budget Requests</div><div class="six-stat-val"><?php echo count($budget_requests);?></div></div>
        </div>
        <div class="six-card"><div class="six-card-header"><span class="six-card-title">MRR by Client</span></div><div class="six-card-body">
            <?php foreach($clients as $c):$mr=floatval($wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(budget),0) FROM {$wpdb->prefix}six_client_services WHERE client_id=%d AND status='active'",$c->ID)));$pct=$total_mrr>0?round(($mr/$total_mrr)*100):0;?>
            <div style="margin-bottom:14px">
                <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:5px"><span style="color:var(--text2)"><?php echo esc_html($c->display_name);?></span><strong>$<?php echo number_format($mr,0);?>/mo</strong></div>
                <div class="six-progress-track"><div class="six-progress-fill" style="width:<?php echo $pct;?>%;background:var(--pink)"></div></div>
            </div>
            <?php endforeach;?>
        </div></div>

    <?php /* ════════════ GOOGLE ADS SETUP ════════════ */ elseif($active_tab==='gads'):?>
        <div class="six-page-header">
            <div><h1 class="six-page-title">Google Ads — MCC Setup</h1>
            <p class="six-page-sub">One-time configuration. After this, advisors only need to enter a Customer ID per client.</p></div>
        </div>

        <!-- Status -->
        <div class="six-card" style="margin-bottom:16px;border-color:<?php echo $mcc_configured?'rgba(86,211,100,0.35)':'rgba(227,179,65,0.35)';?>">
            <div class="six-card-body" style="display:flex;align-items:center;gap:14px">
                <div style="font-size:28px"><?php echo $mcc_configured?'':'⚠';?></div>
                <div>
                    <div style="font-weight:700;font-size:14px;color:<?php echo $mcc_configured?'var(--success)':'var(--warning)';?>"><?php echo $mcc_configured?'MCC Account Connected':'Setup Required';?></div>
                    <div style="font-size:12px;color:var(--text2);margin-top:3px">
                        <?php if($mcc_configured):?>
                            Manager ID: <code style="background:var(--dark3);padding:1px 6px;border-radius:4px"><?php echo esc_html(get_option('six_gads_manager_id','—'));?></code>
                            &nbsp;·&nbsp;
                            <?php $exp=intval(get_option('six_gads_token_expires',0));echo $exp>time()?'Token valid for '.human_time_diff(time(),$exp):'<span style="color:var(--danger)">⚠ Token expired — re-enter refresh token below</span>';?>
                        <?php else:?>
                            Fill in the credentials below to connect your Google Ads Manager Account.
                        <?php endif;?>
                    </div>
                </div>
            </div>
        </div>

        <div class="six-card">
                <div class="six-card-header"><span class="six-card-title">MCC Credentials</span></div>
                <div class="six-card-body">
                    <?php
                    $mask = str_repeat('•',12);
                    $fields_display = array(
                        'six_gads_developer_token' => array('label'=>'Developer Token','type'=>'password','hint'=>'From <a href="https://ads.google.com/aw/apicenter" target="_blank" style="color:var(--cyan)">Google Ads API Center</a>'),
                        'six_gads_manager_id'      => array('label'=>'Manager Account ID (MCC)','type'=>'text','hint'=>'Your top-level manager account ID'),
                        'six_gads_client_id'       => array('label'=>'OAuth Client ID','type'=>'text','hint'=>'From <a href="https://console.cloud.google.com/apis/credentials" target="_blank" style="color:var(--cyan)">Google Cloud Console</a> → OAuth 2.0 Client IDs'),
                        'six_gads_client_secret'   => array('label'=>'OAuth Client Secret','type'=>'password','hint'=>'Your OAuth client secret'),
                        'six_gads_refresh_token'   => array('label'=>'MCC Refresh Token','type'=>'password','hint'=>'You already generated this — paste it here. Does NOT expire unless revoked.'));
                    foreach($fields_display as $key=>$f):
                        $has_val=!empty(get_option($key));
                    ?>
                    <div class="six-form-group">
                        <label class="six-label"><?php echo $f['label'];?></label>
                        <input class="six-input gads-field" type="<?php echo $f['type'];?>" id="<?php echo $key;?>"
                               value="<?php echo $has_val?esc_attr($f['type']==='text'?get_option($key):$mask):'';?>"
                               placeholder="<?php echo $f['type']==='password'?'Enter value…':get_option($key,'');?>"
                               style="<?php echo $f['type']==='password'&&$has_val?'letter-spacing:2px':'';?>">
                        <?php if($f['hint']):?><div style="font-size:11px;color:var(--text3);margin-top:3px"><?php echo $f['hint'];?></div><?php endif;?>
                    </div>
                    <?php endforeach;?>
                    <button class="six-btn six-btn-primary" id="save-mcc" style="margin-top:4px"> Save & Verify Connection</button>
                    <div id="mcc-result" style="margin-top:12px;font-size:12px"></div>
                </div>
            </div>

                    <div class="six-card-body" style="padding:0">
            <table class="six-table"><thead><tr><th>Client</th><th>Customer ID</th><th>Last Sync</th><th></th></tr></thead><tbody>
            <?php foreach($clients as $c):
                $cid=get_user_meta($c->ID,'six_gads_customer_id_display',true)?:get_user_meta($c->ID,'six_gads_customer_id',true);
                $syn=get_user_meta($c->ID,'six_gads_last_sync',true);
            ?>
            <tr>
                <td><strong><?php echo esc_html($c->display_name);?></strong></td>
                <td><?php echo $cid?'<code style="background:var(--dark3);padding:2px 8px;border-radius:4px">'.esc_html($cid).'</code>':'<span style="color:var(--text3)">Not set</span>';?></td>
                <td style="font-size:12px;color:var(--text3)"><?php echo $syn?human_time_diff(strtotime($syn),time()).' ago':'Never';?></td>
                <td><a href="?tab=clients&client=<?php echo $c->ID;?>" class="six-btn six-btn-secondary six-btn-sm">Set ID →</a></td>
            </tr>
            <?php endforeach;?>
            </tbody></table>
            </div>
        </div>

    <?php /* ════════════ CALENDAR ════════════ */ elseif($active_tab==='calendar'):
        // Re-read these fresh — token may have just been saved by the callback handler
        $gcal_connected    = ! empty( get_user_meta($advisor_id,'six_gcal_refresh_token',true) );
        $gcal_email        = get_user_meta($advisor_id,'six_gcal_email',true);
        $oauth_url         = home_url('/advisor-portal/?tab=calendar&gcal_auth=1');
        $disconnect_url    = home_url('/advisor-portal/?tab=calendar&gcal_disconnect=1');

        // Show success banner if just connected
        if ( isset($_GET['gcal_success']) ) {
            echo '<div style="background:rgba(86,211,100,0.1);border:1px solid rgba(86,211,100,0.3);border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:var(--success)"> Google Calendar connected successfully!</div>';
        }
        if ( isset($_GET['gcal_error']) ) {
            echo '<div style="background:rgba(255,107,107,0.1);border:1px solid var(--danger);border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:var(--danger)"> Connection error: '.esc_html($_GET['gcal_error']).' — please try again.</div>';
        }

        // OAuth callback is handled by six_handle_gcal_oauth_callback() in admin-settings.php
        // It intercepts GET /advisor-portal/gcal/ at template_redirect priority 0,
        // exchanges the code for tokens, saves them, then redirects here with gcal_success=1.

        // Fetch upcoming events (next 7 days)
        $upcoming_events = array();
        if ($gcal_connected && class_exists('Six_Google_Calendar')) {
            $upcoming_events = Six_Google_Calendar::get_upcoming_events($advisor_id, 14) ?: array();
        }

        // Group events by date
        $events_by_date = array();
        foreach ($upcoming_events as $ev) {
            $day = date('Y-m-d', strtotime($ev['start']));
            $events_by_date[$day][] = $ev;
        }
    ?>
        <div class="six-page-header" style="margin-bottom:24px">
            <div>
                <h1 class="six-page-title">Google Calendar</h1>
                <p class="six-page-sub">Your upcoming meetings and availability</p>
            </div>
            <?php if($gcal_connected):?>
            <a href="<?php echo esc_url($disconnect_url);?>" class="six-btn six-btn-ghost six-btn-sm" onclick="return confirm('Disconnect Google Calendar?')" style="color:var(--danger)">Disconnect</a>
            <?php endif;?>
        </div>

        <?php if(!$gcal_connected): ?>
        <!-- Connect screen -->
        <div style="max-width:480px;margin:60px auto;text-align:center">
            <div style="width:80px;height:80px;border-radius:50%;background:var(--dark3);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:36px;margin:0 auto 24px"></div>
            <h2 style="font-family:'Syne',sans-serif;font-size:22px;font-weight:700;margin-bottom:12px">Connect Google Calendar</h2>
            <p style="font-size:14px;color:var(--text2);line-height:1.7;margin-bottom:32px">
                Sign in with Google to see your upcoming meetings, client calls, and availability directly in your advisor dashboard.
            </p>
            <?php if(get_option('six_google_client_id')):?>
            <a href="<?php echo esc_url($oauth_url);?>" class="six-btn six-btn-primary" style="font-size:15px;padding:14px 32px;display:inline-flex;align-items:center;gap:10px">
                <svg width="18" height="18" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.2l6.8-6.8C35.7 2.2 30.2 0 24 0 14.7 0 6.8 5.4 2.9 13.3l7.9 6.1C12.7 13.1 17.9 9.5 24 9.5z"/><path fill="#4285F4" d="M46.9 24.5c0-1.7-.1-3-.4-4.5H24v8.5h13.1c-.6 3-2.4 5.5-5 7.2l7.8 6c4.6-4.2 7-10.5 7-17.2z"/><path fill="#FBBC05" d="M10.8 28.6A14.6 14.6 0 0 1 9.5 24c0-1.6.3-3.1.7-4.6l-7.9-6.1A23.8 23.8 0 0 0 0 24c0 3.9.9 7.5 2.5 10.8l8.3-6.2z"/><path fill="#34A853" d="M24 48c6.2 0 11.5-2 15.3-5.5l-7.8-6c-2 1.4-4.6 2.2-7.5 2.2-6.1 0-11.3-3.6-13.2-9l-8.3 6.2C6.8 42.6 14.7 48 24 48z"/></svg>
                Sign in with Google
            </a>
            <?php else:?>
            <div style="background:rgba(227,179,65,0.1);border:1px solid rgba(227,179,65,0.3);border-radius:10px;padding:16px;font-size:13px;color:var(--warning)">
                ⚠ Google OAuth not configured. Go to <a href="<?php echo admin_url('admin.php?page=six-portal-settings');?>" style="color:var(--cyan)">WP Admin → 6ix Portal → Integrations</a> and add your Google OAuth Client ID and Secret.
            </div>
            <?php endif;?>
        </div>

        <?php else: ?>
        <!-- Connected — show calendar -->
        <div style="display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start">

            <!-- Upcoming meetings -->
            <div class="six-card">
                <div class="six-card-header">
                    <span class="six-card-title">Upcoming Meetings</span>
                    <?php if($gcal_email):?><span style="font-size:11px;color:var(--text3)"> <?php echo esc_html($gcal_email);?></span><?php endif;?>
                </div>
                <div class="six-card-body" style="padding:0">
                <?php if(empty($upcoming_events)):?>
                    <div style="padding:40px;text-align:center;color:var(--text3)">
                        <div style="font-size:32px;margin-bottom:12px"></div>
                        No meetings in the next 14 days.
                    </div>
                <?php else:?>
                    <?php
                    $today_str    = date('Y-m-d');
                    $tomorrow_str = date('Y-m-d',strtotime('+1 day'));
                    foreach ($events_by_date as $day => $events):
                        $day_label = $day===$today_str ? 'Today' : ($day===$tomorrow_str ? 'Tomorrow' : date('l, M j',$ts=strtotime($day)));
                        $day_color = $day===$today_str ? 'var(--pink)' : 'var(--text3)';
                    ?>
                    <div style="padding:12px 20px;background:var(--dark4);border-bottom:1px solid var(--border)">
                        <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:<?php echo $day_color;?>"><?php echo esc_html($day_label);?></span>
                    </div>
                    <?php foreach($events as $ev):
                        $start_ts = strtotime($ev['start']);
                        $end_ts   = isset($ev['end']) ? strtotime($ev['end']) : 0;
                        $dur      = $end_ts ? round(($end_ts-$start_ts)/60) : 0;
                        $is_now   = $start_ts<=time() && $end_ts>=time();
                    ?>
                    <div style="display:flex;gap:16px;padding:14px 20px;border-bottom:1px solid rgba(255,255,255,0.04);<?php echo $is_now?'background:rgba(255,102,153,0.04);border-left:3px solid var(--pink)':'';?>">
                        <div style="min-width:58px;text-align:right;padding-top:2px">
                            <div style="font-size:13px;font-weight:700;color:<?php echo $is_now?'var(--pink)':'var(--text1)';?>"><?php echo date('g:i',$start_ts);?></div>
                            <div style="font-size:10px;color:var(--text3)"><?php echo date('A',$start_ts);?></div>
                        </div>
                        <div style="flex:1">
                            <div style="font-size:13px;font-weight:600;margin-bottom:3px">
                                <?php if($is_now):?><span style="font-size:9px;background:var(--pink);color:white;padding:2px 6px;border-radius:4px;margin-right:6px;font-weight:700">LIVE</span><?php endif;?>
                                <?php echo esc_html($ev['title']??'Meeting');?>
                            </div>
                            <?php if(!empty($ev['client_name'])): ?>
                            <div style="font-size:11px;color:var(--text3);margin-bottom:4px"><?php echo esc_html($ev['client_name']);?><?php if($dur): ?> · <?php echo $dur;?> min<?php endif;?></div>
                            <?php elseif($dur): ?>
                            <div style="font-size:11px;color:var(--text3);margin-bottom:4px"><?php echo $dur;?> min</div>
                            <?php endif;?>
                            <?php if(!empty($ev['description'])): ?>
                            <div style="font-size:11px;color:var(--text3)"><?php echo esc_html(substr($ev['description'],0,80));?><?php echo strlen($ev['description'])>80?'…':'';?></div>
                            <?php endif;?>
                            <?php if(!empty($ev['meet_link'])): ?>
                            <a href="<?php echo esc_url($ev['meet_link']);?>" target="_blank" class="six-btn six-btn-primary" style="font-size:11px;padding:5px 12px;margin-top:8px;display:inline-flex;align-items:center;gap:5px">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="white"><path d="M17 10.5V7a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-3.5l4 4v-11l-4 4z"/></svg>
                                Join Meet
                            </a>
                            <?php endif;?>
                        </div>
                    </div>
                    <?php endforeach;?>
                    <?php endforeach;?>
                <?php endif;?>
                </div>
            </div>

            <!-- Right: quick stats + connect info -->
            <div style="display:flex;flex-direction:column;gap:14px">
                <div class="six-card">
                    <div class="six-card-body" style="padding:16px">
                        <div style="font-size:11px;color:var(--text3);font-weight:600;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:12px">This Week</div>
                        <?php
                        $week_start = strtotime('monday this week');
                        $week_end   = strtotime('sunday this week 23:59:59');
                        $this_week  = array_filter($upcoming_events, function($e) use ($week_start,$week_end){ return strtotime($e['start'])>=$week_start&&strtotime($e['start'])<=$week_end; });
                        $today_ev   = array_filter($upcoming_events, function($e) use ($today_str){ return date('Y-m-d',strtotime($e['start']))=== $today_str; });
                        ?>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                            <div style="text-align:center;padding:12px;background:var(--dark4);border-radius:8px">
                                <div style="font-size:24px;font-weight:800;font-family:'Syne',sans-serif;color:var(--pink)"><?php echo count($today_ev);?></div>
                                <div style="font-size:10px;color:var(--text3);margin-top:2px">Today</div>
                            </div>
                            <div style="text-align:center;padding:12px;background:var(--dark4);border-radius:8px">
                                <div style="font-size:24px;font-weight:800;font-family:'Syne',sans-serif;color:var(--cyan)"><?php echo count($this_week);?></div>
                                <div style="font-size:10px;color:var(--text3);margin-top:2px">This Week</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="six-card" style="border-color:rgba(86,211,100,0.25)">
                    <div class="six-card-body" style="padding:14px 16px;display:flex;align-items:center;gap:10px">
                        <span style="font-size:22px"></span>
                        <div>
                            <div style="font-size:12px;font-weight:600;color:var(--success)">Calendar Connected</div>
                            <?php if($gcal_email):?><div style="font-size:11px;color:var(--text3);margin-top:2px"><?php echo esc_html($gcal_email);?></div><?php endif;?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php endif; // gcal_connected ?>


    <?php /* ════════════ INTELLIGENCE ════════════ */ elseif($active_tab==='intelligence'):?>
        <?php
        $all_opps = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, u.display_name AS client_name
             FROM {$wpdb->prefix}six_recommendations r
             INNER JOIN {$wpdb->prefix}six_assignments a ON r.client_id=a.client_id
             INNER JOIN {$wpdb->prefix}users u ON r.client_id=u.ID
             WHERE a.advisor_id=%d AND r.source LIKE 'ai_%'
             ORDER BY r.created_at DESC LIMIT 100",
            $advisor_id
        ));
        $pending_opps  = array_filter($all_opps, fn($o) => $o->status === 'active');
        $approved_opps = array_filter($all_opps, fn($o) => $o->status === 'approved');
        $intel_filter  = sanitize_key($_GET['itype'] ?? 'all');
        $filtered_opps = $intel_filter === 'pending'  ? $pending_opps :
                        ($intel_filter === 'approved' ? $approved_opps : $all_opps);
        ?>

        <?php
        // Onboarding drop-off tracking — count clients at each step
        $dropoff = array(1=>0, 2=>0, 3=>0, 4=>0, 5=>0);
        $dropoff_labels = array(1=>'User Info', 2=>'Services', 3=>'Questionnaire', 4=>'Strategy', 5=>'Complete');
        $dropoff_total  = count($all_client_ids ?: array());
        if ($dropoff_total > 0) {
            $id_list = implode(',', array_map('intval', $all_client_ids ?: array()));
            $step_rows = $wpdb->get_results(
                "SELECT COALESCE(p.step, 0) AS step, COUNT(*) AS cnt
                 FROM {$wpdb->prefix}six_checkout_progress p
                 WHERE p.user_id IN ({$id_list})
                 GROUP BY COALESCE(p.step, 0)"
            );
            foreach ($step_rows as $sr) {
                $s = max(1, min(5, intval($sr->step)));
                $dropoff[$s] = ($dropoff[$s] ?? 0) + intval($sr->cnt);
            }
        }
        $dropoff_max = max(array_sum($dropoff), 1);
        ?>

        <div class="six-page-header">
            <div>
                <h1 class="six-page-title">Client Intelligence</h1>
                <p class="six-page-sub">AI-generated growth insights across your client base</p>
            </div>
        </div>

        <!-- Onboarding Drop-off Funnel -->
        <?php if ($dropoff_total > 0): ?>
        <div style="background:var(--dark2);border:1px solid var(--border);border-radius:14px;padding:20px;margin-bottom:20px">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
                <div>
                    <div style="font-size:13px;font-weight:700;color:var(--text1)">Onboarding Drop-off Funnel</div>
                    <div style="font-size:11px;color:var(--text3);margin-top:2px"><?php echo $dropoff_total; ?> clients tracked</div>
                </div>
                <svg viewBox="0 0 24 24" fill="none" stroke="var(--pink)" stroke-width="2" width="16" height="16"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
            </div>
            <div style="display:flex;flex-direction:column;gap:8px">
            <?php
            $running = $dropoff_total;
            foreach ($dropoff as $step => $cnt):
                if ($step < 5) $running_next = $running - $cnt;
                $pct = $dropoff_total > 0 ? round(($running / $dropoff_total) * 100) : 0;
                $bar_color = $step===5 ? 'var(--success)' : ($pct < 50 ? 'var(--warning)' : 'var(--cyan)');
            ?>
            <div style="display:flex;align-items:center;gap:12px">
                <div style="font-size:11px;color:var(--text3);min-width:100px;white-space:nowrap">
                    Step <?php echo $step; ?>: <?php echo esc_html($dropoff_labels[$step]); ?>
                </div>
                <div style="flex:1;background:rgba(255,255,255,0.05);border-radius:4px;height:8px;overflow:hidden">
                    <div style="width:<?php echo $pct; ?>%;height:100%;background:<?php echo $bar_color; ?>;border-radius:4px;transition:width .6s ease"></div>
                </div>
                <div style="font-size:12px;font-weight:600;color:var(--text1);min-width:36px;text-align:right"><?php echo $running; ?></div>
                <div style="font-size:10px;color:var(--text3);min-width:34px"><?php echo $pct; ?>%</div>
            </div>
            <?php
                if ($step < 5) { $running = max(0, $running - $cnt); }
            endforeach;
            ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Summary row -->
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:24px">
            <div style="background:var(--dark2);border:1px solid var(--border);border-radius:14px;padding:18px">
                <div style="font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:.6px;margin-bottom:6px">Pending Review</div>
                <div style="font-size:28px;font-weight:700;color:var(--text1)"><?php echo count($pending_opps);?></div>
                <div style="font-size:12px;color:var(--text3);margin-top:2px">Awaiting your action</div>
            </div>
            <div style="background:var(--dark2);border:1px solid var(--border);border-radius:14px;padding:18px">
                <div style="font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:.6px;margin-bottom:6px">Approved</div>
                <div style="font-size:28px;font-weight:700;color:var(--success)"><?php echo count($approved_opps);?></div>
                <div style="font-size:12px;color:var(--text3);margin-top:2px">Strategies confirmed</div>
            </div>
            <div style="background:var(--dark2);border:1px solid var(--border);border-radius:14px;padding:18px">
                <div style="font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:.6px;margin-bottom:6px">Total Insights</div>
                <div style="font-size:28px;font-weight:700;color:var(--text1)"><?php echo count($all_opps);?></div>
                <div style="font-size:12px;color:var(--text3);margin-top:2px">Across all clients</div>
            </div>
        </div>

        <!-- Filter -->
        <div style="display:flex;gap:6px;margin-bottom:16px">
            <?php foreach(array('all'=>'All Insights','pending'=>'Pending Review','approved'=>'Approved') as $k=>$lbl):
                $ia = $intel_filter===$k; ?>
            <a href="?tab=intelligence&itype=<?php echo $k;?>"
               style="display:inline-flex;align-items:center;padding:5px 14px;border-radius:20px;font-size:12px;font-weight:500;text-decoration:none;
                      <?php echo $ia?'background:var(--pink);color:#fff;':'background:var(--dark3);color:var(--text2);border:1px solid var(--border);';?>">
               <?php echo $lbl;?></a>
            <?php endforeach;?>
        </div>

        <!-- Insights list -->
        <?php if(empty($filtered_opps)):?>
        <div style="background:var(--dark2);border:1px solid var(--border);border-radius:14px;padding:48px 24px;text-align:center;color:var(--text3)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="32" height="32" style="display:block;margin:0 auto 12px;opacity:.4"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
            No insights<?php echo $intel_filter!=='all'?' in this category':'';?> yet.
        </div>
        <?php else:?>
        <div style="display:flex;flex-direction:column;gap:10px">
        <?php foreach($filtered_opps as $opp):
            $is_pending  = $opp->status === 'active';
            $client_link = '?tab=clients&client=' . intval($opp->client_id);
        ?>
        <div style="background:var(--dark2);border:1px solid var(--border);border-radius:14px;padding:18px 20px;display:flex;align-items:flex-start;gap:16px">
            <!-- Status indicator -->
            <div style="width:10px;height:10px;border-radius:50%;flex-shrink:0;margin-top:4px;
                        background:<?php echo $is_pending?'var(--warning)':'var(--success)';?>"></div>
            <div style="flex:1;min-width:0">
                <!-- Client + type -->
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;flex-wrap:wrap">
                    <a href="<?php echo $client_link;?>" style="font-size:12px;font-weight:700;color:var(--text1);text-decoration:none"><?php echo esc_html($opp->client_name);?></a>
                    <span style="font-size:10px;color:var(--text3);background:var(--dark3);padding:2px 8px;border-radius:4px;text-transform:uppercase;letter-spacing:.4px">
                        <?php echo esc_html(str_replace('ai_','',str_replace('_',' ',$opp->source??'')));?>
                    </span>
                    <span style="font-size:10px;color:<?php echo $is_pending?'var(--warning)':'var(--success)';?>;margin-left:auto">
                        <?php echo $is_pending?'Pending':'Approved';?>
                    </span>
                </div>
                <!-- Title -->
                <div style="font-size:13px;font-weight:600;color:var(--text1);margin-bottom:4px"><?php echo esc_html($opp->title??'');?></div>
                <!-- Description -->
                <div style="font-size:12px;color:var(--text2);line-height:1.6"><?php echo esc_html(substr($opp->description??'',0,180)).(strlen($opp->description??'')>180?'…':'');?></div>
                <!-- Date -->
                <div style="font-size:11px;color:var(--text3);margin-top:6px"><?php echo isset($opp->created_at)?human_time_diff(strtotime($opp->created_at)).' ago':'';?></div>
            </div>
            <!-- Actions -->
            <?php if($is_pending):?>
            <div style="display:flex;gap:6px;flex-shrink:0">
                <button class="six-btn six-btn-primary six-btn-sm six-approve-intel"
                        data-id="<?php echo $opp->id;?>" data-client="<?php echo $opp->client_id;?>">Approve</button>
                <button class="six-btn six-btn-ghost six-btn-sm six-dismiss-intel"
                        data-id="<?php echo $opp->id;?>">Dismiss</button>
            </div>
            <?php endif;?>
        </div>
        <?php endforeach;?>
        </div>
        <?php endif;?>

    <?php endif;?>
    </main>
</div>

<script>
// Global vars accessible by all script blocks
var AJAX = '<?php echo esc_js($ajax_url);?>';
var NONCE = '<?php echo esc_js($nonce);?>';
var INI = '<?php echo esc_js($initials);?>';
</script>
<script>
(function(){
'use strict';
// AJAX, NONCE, INI defined globally above

function post(data){ return fetch(AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(Object.assign({nonce:NONCE},data))}).then(r=>r.json()); }

// ── Messaging ───────────────────────────────────────────────────────────────
var msgSend = document.getElementById('six-msg-send');
var msgIn   = document.getElementById('six-msg-input');
if(msgSend && msgIn){
    var doSend = function(){
        var msg = msgIn.value.trim(), receiver = msgIn.dataset.receiver;
        if(!msg||!receiver) return;
        post({action:'six_send_message',receiver_id:receiver,message:msg}).then(function(res){
            if(res.success){
                var t=document.getElementById('six-msg-thread');
                var d=document.createElement('div'); d.className='six-msg mine';
                d.innerHTML='<div class="six-msg-avatar" style="background:linear-gradient(135deg,var(--pink),#a855f7)">'+INI+'</div><div><div class="six-msg-bubble">'+msg.replace(/</g,'&lt;')+'</div><div class="six-msg-time">just now</div></div>';
                t.appendChild(d); t.scrollTop=t.scrollHeight; msgIn.value='';
            }
        });
    };
    msgSend.addEventListener('click',doSend);
    msgIn.addEventListener('keydown',function(e){if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();doSend();}});
}
var thread=document.getElementById('six-msg-thread');
if(thread) thread.scrollTop=thread.scrollHeight;
var composeGo=document.getElementById('compose-go');
if(composeGo) composeGo.addEventListener('click',function(){var id=document.getElementById('compose-to').value;if(id)window.location.href='?tab=messages&with='+id;});

// ── Approve Service ─────────────────────────────────────────────────────────
function sixApproveService(btn, serviceId, clientId) {
    if (!serviceId) { alert('Missing service ID'); return; }
    btn.textContent = 'Approving…';
    btn.disabled = true;
    btn.style.opacity = '0.7';
    // Refresh nonce inline to prevent stale-nonce failures
    var currentNonce = (typeof NONCE !== 'undefined') ? NONCE : '';
    fetch(AJAX, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action:     'six_approve_service',
            nonce:      currentNonce,
            service_id: String(serviceId),
            client_id:  String(clientId || 0)
        })
    })
    .then(function(r){
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
    })
    .then(function(res){
        if (res.success) {
            btn.textContent = '✓ Approved';
            btn.style.background = 'var(--success)';
            btn.style.borderColor = 'var(--success)';
            setTimeout(function(){ location.reload(); }, 800);
        } else {
            btn.textContent = 'Approve Service';
            btn.disabled = false;
            btn.style.opacity = '1';
            alert('Error: ' + (res.data || 'Could not approve service'));
        }
    })
    .catch(function(err){
        btn.textContent = 'Approve Service';
        btn.disabled = false;
        btn.style.opacity = '1';
        alert('Network error — please try again');
        console.error('Approve error:', err);
    });
}

// Legacy delegation for any old-style buttons
document.querySelectorAll('.six-approve-service').forEach(function(btn){
    btn.addEventListener('click',function(){
        var id=this.dataset.serviceId||this.dataset.id;
        var cid=this.dataset.client||0;
        sixApproveService(this, id, cid);
    });
});

// ── Budget approve ──────────────────────────────────────────────────────────
document.querySelectorAll('.six-approve-budget').forEach(function(btn){
    btn.addEventListener('click',function(){
        var row=this.closest('tr');
        var input=row?row.querySelector('.budget-final'):null;
        var budget=input?parseFloat(input.value):(parseFloat(this.dataset.budget)||0);
        this.textContent='Approving…';this.disabled=true;
        post({action:'six_approve_budget_change',client_id:this.dataset.client,service_id:this.dataset.service,budget}).then(function(res){
            if(res.success)setTimeout(function(){location.reload();},600);
        });
    });
});
document.querySelectorAll('.six-decline-budget').forEach(function(btn){
    btn.addEventListener('click',function(){
        var row=this.closest('tr');
        post({action:'six_decline_budget_change',client_id:this.dataset.client,service_id:this.dataset.service}).then(function(res){if(res.success)row&&row.remove();});
    });
});

// ── Metric edit ─────────────────────────────────────────────────────────────
document.querySelectorAll('.six-edit-metric').forEach(function(btn){
    btn.addEventListener('click',function(){
        document.getElementById('metric-edit-id').value=this.dataset.id;
        document.getElementById('metric-svc').value=this.dataset.svc;
        document.getElementById('metric-label').value=this.dataset.label;
        document.getElementById('metric-prev').value=this.dataset.prev;
        document.getElementById('metric-cur').value=this.dataset.cur;
        document.getElementById('metric-target').value=this.dataset.target;
        document.getElementById('metric-form-heading').textContent=' Edit Metric';
        document.getElementById('cancel-metric').style.display='';
        document.getElementById('metric-card').scrollIntoView({behavior:'smooth',block:'center'});
    });
});
var cancelMetric=document.getElementById('cancel-metric');
if(cancelMetric) cancelMetric.addEventListener('click',function(){
    document.getElementById('metric-edit-id').value='';
    document.getElementById('metric-form-heading').textContent='+ Add Metric';
    this.style.display='none';
    ['metric-label','metric-prev','metric-cur','metric-target'].forEach(function(id){var el=document.getElementById(id);if(el)el.value='';});
});
var saveMetric=document.getElementById('save-metric');
if(saveMetric) saveMetric.addEventListener('click',function(){
    var client=this.dataset.client;
    post({action:'six_add_metric',client_id:client,
        metric_id:document.getElementById('metric-edit-id').value,
        service:document.getElementById('metric-svc').value,
        label:document.getElementById('metric-label').value,
        previous:document.getElementById('metric-prev').value,
        current:document.getElementById('metric-cur').value,
        target:document.getElementById('metric-target').value,
    }).then(function(res){
        document.getElementById('metric-result').innerHTML=res.success?'<span style="color:var(--success)">Saved!</span>':'<span style="color:var(--danger)">Error</span>';
        if(res.success)setTimeout(function(){location.reload();},900);
    });
});

// ── Metric delete ────────────────────────────────────────────────────────────
document.querySelectorAll('.six-delete-metric').forEach(function(btn){
    btn.addEventListener('click',function(){
        if(!confirm('Delete this metric?'))return;
        var id=this.dataset.id;
        post({action:'six_delete_metric',metric_id:id}).then(function(res){
            if(res.success){var row=document.getElementById('mrow-'+id);if(row){row.style.opacity='0.3';setTimeout(function(){row.remove();},300);}}
        });
    });
});

// ── Recommendation edit ──────────────────────────────────────────────────────
document.querySelectorAll('.six-edit-rec').forEach(function(btn){
    btn.addEventListener('click',function(){
        document.getElementById('rec-edit-id').value=this.dataset.id;
        document.getElementById('rec-title').value=this.dataset.title;
        document.getElementById('rec-desc').value=this.dataset.desc;
        document.getElementById('rec-action').value=this.dataset.action;
        document.getElementById('rec-form-heading').textContent=' Edit Recommendation';
        document.getElementById('cancel-rec').style.display='';
        document.getElementById('rec-title').scrollIntoView({behavior:'smooth',block:'center'});
    });
});
var cancelRec=document.getElementById('cancel-rec');
if(cancelRec) cancelRec.addEventListener('click',function(){
    document.getElementById('rec-edit-id').value='';
    document.getElementById('rec-form-heading').textContent='+ New Recommendation';
    this.style.display='none';
    ['rec-title','rec-desc','rec-action'].forEach(function(id){var el=document.getElementById(id);if(el)el.value='';});
});
var saveRec=document.getElementById('save-rec');
if(saveRec) saveRec.addEventListener('click',function(){
    var client=this.dataset.client;
    post({action:'six_add_recommendation',client_id:client,rec_id:document.getElementById('rec-edit-id').value,
        title:document.getElementById('rec-title').value,description:document.getElementById('rec-desc').value,action_label:document.getElementById('rec-action').value,
    }).then(function(res){
        document.getElementById('rec-result').innerHTML=res.success?'<span style="color:var(--success)">'+(res.data&&res.data.updated?'Updated!':'Sent to client!')+'</span>':'<span style="color:var(--danger)">Error</span>';
        if(res.success)setTimeout(function(){location.reload();},900);
    });
});

// ── Recommendation delete ────────────────────────────────────────────────────
document.querySelectorAll('.six-delete-rec').forEach(function(btn){
    btn.addEventListener('click',function(){
        if(!confirm('Delete this recommendation?'))return;
        var id=this.dataset.id;
        post({action:'six_delete_recommendation',rec_id:id}).then(function(res){
            if(res.success){var row=document.getElementById('arec-'+id);if(row){row.style.opacity='0';setTimeout(function(){row.remove();},300);}}
        });
    });
});

// ── Report upload (file or URL) ──────────────────────────────────────────────
var saveReport=document.getElementById('save-report');
if(saveReport) saveReport.addEventListener('click',function(){
    var title=(document.getElementById('rpt-title')||{}).value;
    if(!title){document.getElementById('report-result').innerHTML='<span style="color:var(--danger)">Enter a title.</span>';return;}
    var method=(document.querySelector('input[name="rpt-method"]:checked')||{}).value||'url';
    var formData=new FormData();
    formData.append('action','six_upload_report');
    formData.append('nonce',NONCE);
    formData.append('client_id',this.dataset.client);
    formData.append('title',title);
    formData.append('period',(document.getElementById('rpt-period')||{}).value||'');
    if(method==='file'){var f=(document.getElementById('rpt-file')||{}).files;if(f&&f[0])formData.append('report_file',f[0]);}
    else{formData.append('url',(document.getElementById('rpt-url')||{}).value||'');}
    this.textContent='Uploading…';this.disabled=true;
    fetch(AJAX,{method:'POST',body:formData}).then(function(r){return r.json();}).then(function(res){
        document.getElementById('report-result').innerHTML=res.success
            ?'<span style="color:var(--success)">Report published!</span>'
            :'<span style="color:var(--danger)">'+(res.data||'Upload failed')+'</span>';
        saveReport.textContent=' Publish Report';saveReport.disabled=false;
    });
});

// ── Google Ads: save Customer ID ─────────────────────────────────────────────
var saveCid=document.getElementById('save-gads-cid');
if(saveCid) saveCid.addEventListener('click',function(){
    var btn=this;btn.textContent='Saving…';btn.disabled=true;
    post({action:'six_save_client_gads',client_id:btn.dataset.client,six_gads_customer_id:(document.getElementById('gads-cid')||{}).value||''}).then(function(res){
        document.getElementById('gads-result').innerHTML=res.success?'<span style="color:var(--success)">'+(res.data.message||'Saved')+'</span>':'<span style="color:var(--danger)">'+(res.data||'Error')+'</span>';
        btn.textContent='Save Customer ID';btn.disabled=false;
    });
});
var syncNow=document.getElementById('sync-gads-now');
if(syncNow) syncNow.addEventListener('click',function(){
    var btn=this;btn.textContent='Syncing…';btn.disabled=true;
    post({action:'six_sync_client_gads',client_id:btn.dataset.client}).then(function(res){
        document.getElementById('gads-result').innerHTML=res.success
            ?'<span style="color:var(--success)">Synced! Metrics updated.</span>'
            :'<span style="color:var(--danger)">'+(res.data||'Sync failed')+'</span>';
        btn.textContent='↻ Sync Now';btn.disabled=false;
    });
});

// ── MCC Credentials ──────────────────────────────────────────────────────────
var saveMcc=document.getElementById('save-mcc');
if(saveMcc) saveMcc.addEventListener('click',function(){
    var btn=this; btn.textContent='Saving & Verifying…'; btn.disabled=true;
    var data={action:'six_save_mcc_credentials'};
    document.querySelectorAll('.gads-field').forEach(function(el){data[el.id]=el.value;});
    post(data).then(function(res){
        document.getElementById('mcc-result').innerHTML=res.success
            ?'<span style="color:var(--success)"> '+(res.data.message||'Connected')+'</span>'
            :'<span style="color:var(--danger)">'+(res.data||'Verification failed')+'</span>';
        btn.textContent=' Save & Verify Connection'; btn.disabled=false;
        if(res.success) setTimeout(function(){location.reload();},1500);
    });
});

// ── Mark all notifications read ───────────────────────────────────────────────
var markAll=document.getElementById('mark-all-read');
if(markAll) markAll.addEventListener('click',function(){
    post({action:'six_mark_all_notifications_read'}).then(function(){location.reload();});
});


// Intelligence Tab: Approve / Decline opportunity
document.querySelectorAll('.six-approve-intel-opp').forEach(function(btn){
    btn.addEventListener('click', function(){
        var recId = this.dataset.recId;
        var row   = document.getElementById('intel-opp-' + recId);
        this.textContent = 'Approving...'; this.disabled = true;
        var self = this;
        post({action:'six_approve_opportunity', rec_id:recId})
        .then(function(d){
            if(d && d.success){
                if(row){
                    row.style.transition = 'opacity 0.4s';
                    row.style.opacity    = '0.4';
                    var ab = row.querySelector('.six-btn.six-btn-primary');
                    if(ab) ab.closest('div').innerHTML = '<span style="font-size:12px;color:var(--success);font-weight:600">&#10003; Approved &mdash; client notified &amp; Odoo task created</span>';
                }
            } else {
                self.textContent = '&#10003; Approve Strategy';
                self.disabled    = false;
                alert(d && d.data ? d.data : 'Error approving strategy.');
            }
        })
        .catch(function(){ self.textContent='&#10003; Approve Strategy'; self.disabled=false; });
    });
});

document.querySelectorAll('.six-dismiss-intel-opp').forEach(function(btn){
    btn.addEventListener('click', function(){
        var recId = this.dataset.recId;
        var row   = document.getElementById('intel-opp-' + recId);
        this.textContent = '...'; this.disabled = true;
        post({action:'six_dismiss_recommendation', rec_id:recId})
        .then(function(){
            if(row){
                row.style.transition = 'opacity 0.3s';
                row.style.opacity    = '0';
                setTimeout(function(){ row.remove(); }, 320);
            }
        });
    });
});



// ─── AI Suggestion Generator (Client Profile) ────────────────────────────────
// AJAX and NONCE are global (defined above main script block)

document.querySelectorAll('.six-ai-suggest-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var prompt  = this.dataset.prompt;
        var type    = this.dataset.type;
        var output  = document.getElementById('ai-suggest-output');
        var loading = document.getElementById('ai-suggest-loading');
        var placeholder = document.getElementById('ai-suggest-placeholder');
        var textEl  = document.getElementById('ai-suggest-text');
        var titleEl = document.getElementById('ai-suggest-title');

        if (!prompt) return;

        // Highlight active button
        document.querySelectorAll('.six-ai-suggest-btn').forEach(function(b){ b.style.borderColor=''; b.style.color=''; });
        this.style.borderColor = 'var(--pink)'; this.style.color = 'var(--pink)';

        if (output)      output.style.display      = 'none';
        if (loading)     loading.style.display      = 'block';
        if (placeholder) placeholder.style.display  = 'none';

        fetch(AJAX, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({action:'six_ai_insight', nonce:NONCE, prompt:prompt})
        })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (loading) loading.style.display = 'none';
            if (d && d.success && d.data && d.data.text) {
                var text = d.data.text
                    .replace(/^#{1,3}\s+(.+)$/gm, '<strong>$1</strong>')
                    .replace(/[→\-\*]+\s*\*\*(.*?)\*\*:?\s*/g, '<li><strong>$1</strong>: ')
                    .replace(/[→]+\s*/g, '<li>')
                    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                    .replace(/\n\n/g, '</p><p style="margin:8px 0 0">').replace(/\n/g, '<br>');
                if (text.indexOf('<li>') !== -1) {
                    text = '<ul style="padding-left:18px;margin:6px 0;line-height:1.85">' + text + '</ul>';
                    text = text.replace(/<li>([\s\S]*?)(?=<li>|<\/ul>)/g, '<li>$1</li>');
                } else {
                    text = '<p style="margin:0">' + text + '</p>';
                }
                if (textEl)  textEl.innerHTML = text;
                if (titleEl) titleEl.value = type.replace(/_/g,' ').replace(/\b\w/g,function(l){return l.toUpperCase();});
                if (output)  output.style.display = 'block';
            } else {
                if (loading) loading.style.display = 'none';
                if (placeholder) { placeholder.style.display = 'block'; placeholder.innerHTML = '<div style="text-align:center;padding:16px;color:var(--danger);font-size:12px">Could not generate — try again.</div>'; }
            }
        })
        .catch(function() {
            if (loading) loading.style.display = 'none';
            if (placeholder) placeholder.style.display = 'block';
        });
    });
});

// Cancel button
var cancelBtn = document.getElementById('ai-suggest-cancel');
if (cancelBtn) {
    cancelBtn.addEventListener('click', function() {
        var output = document.getElementById('ai-suggest-output');
        if (output) output.style.display = 'none';
        var result = document.getElementById('ai-suggest-result');
        if (result) result.innerHTML = '';
    });
}

// Send recommendation to client
var sendBtn = document.getElementById('ai-suggest-send');
if (sendBtn) {
    sendBtn.addEventListener('click', function() {
        var clientId = this.dataset.client || '<?php echo intval($view_client_id); ?>';
        var type     = this.dataset.type || 'advisor_ai';
        var titleEl  = document.getElementById('ai-suggest-title');
        var msgEl    = document.getElementById('ai-suggest-msg');
        var resultEl = document.getElementById('ai-suggest-result');
        var title    = titleEl ? titleEl.value.trim() : '';
        var msg      = msgEl   ? msgEl.value.trim()   : '';

        if (!title || !msg) {
            if (resultEl) resultEl.innerHTML = '<span style="color:var(--danger)">Please fill in both title and message.</span>';
            return;
        }

        this.textContent = 'Sending...'; this.disabled = true;
        var self = this;

        fetch(AJAX, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action:      'six_advisor_push_suggestion',
                nonce:       NONCE,
                client_id:   clientId,
                type:        type,
                title:       title,
                description: msg
            })
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            self.textContent = 'Send to Client'; self.disabled = false;
            if (d && d.success) {
                if (resultEl) resultEl.innerHTML = '<span style="color:var(--success)">Sent — client will see this in their portal and can approve or dismiss.</span>';
                if (titleEl)  titleEl.value = '';
                if (msgEl)    msgEl.value   = '';
                setTimeout(function() {
                    var output = document.getElementById('ai-suggest-output');
                    if (output) output.style.display = 'none';
                }, 2500);
            } else {
                if (resultEl) resultEl.innerHTML = '<span style="color:var(--danger)">' + (d && d.data ? d.data : 'Error sending.') + '</span>';
            }
        })
        .catch(function() {
            self.textContent = 'Send to Client'; self.disabled = false;
        });
    });
}


})();
</script>

<script>
(function(){
  var btn = document.getElementById('six-menu-toggle');
  var sidebar = document.querySelector('.six-sidebar');
  var overlay = document.getElementById('six-overlay');
  if(!btn || !sidebar) return;
  function openMenu(){
    sidebar.style.display='block'; // ensure visible in case CSS hid it
    // Force reflow before adding class (ensures transition fires)
    sidebar.getBoundingClientRect();
    sidebar.classList.add('open');
    var ov=document.getElementById('six-overlay');
    if(ov){ ov.style.display='block'; ov.classList.add('open'); }
    document.body.style.overflow='hidden';
  }
  function closeMenu(){
    sidebar.classList.remove('open');
    var ov=document.getElementById('six-overlay');
    if(ov){ ov.style.display='none'; ov.classList.remove('open'); }
    document.body.style.overflow='';
  }
  btn.addEventListener('click', function(){ sidebar.classList.contains('open') ? closeMenu() : openMenu(); });
  if(overlay) overlay.addEventListener('click', closeMenu);
  // Close on nav link click (mobile UX)
  sidebar.querySelectorAll('.six-nav-item').forEach(function(a){
    a.addEventListener('click', function(){ if(window.innerWidth<=768) closeMenu(); });
  });
// ── Per-service metric add ─────────────────────────────────────────────
document.querySelectorAll('.six-add-metric-svc').forEach(function(btn){
    btn.addEventListener('click',function(){
        var slug    = this.dataset.slug;
        var client  = this.dataset.client;
        var label   = document.querySelector('.metric-label-'+slug);
        var current = document.querySelector('.metric-current-'+slug);
        var prev    = document.querySelector('.metric-prev-'+slug);
        var target  = document.querySelector('.metric-target-'+slug);
        var result  = document.querySelector('.metric-result-'+slug);
        if(!label||!label.value.trim()){if(result)result.innerHTML='<span style="color:var(--danger)">Label required.</span>';return;}
        this.textContent='Saving…';this.disabled=true;var self=this;
        fetch(AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:new URLSearchParams({action:'six_add_metric',nonce:NONCE,client_id:client,
                service_slug:slug,label:label.value,current_value:current?current.value:'',
                previous_value:prev?prev.value:'',target_value:target?target.value:''})})
        .then(function(r){return r.json();})
        .then(function(d){
            self.textContent='Save';self.disabled=false;
            if(d&&d.success){
                if(result)result.innerHTML='<span style="color:var(--success)">Saved</span>';
                if(label)label.value='';if(current)current.value='';if(prev)prev.value='';if(target)target.value='';
                setTimeout(function(){location.reload();},800);
            } else {
                if(result)result.innerHTML='<span style="color:var(--danger)">'+(d.data||'Error')+'</span>';
            }
        });
    });
});

// ── Advisor saves client profile ───────────────────────────────────────
var advSaveProfileBtn = document.getElementById('adv-save-profile-btn');
if(advSaveProfileBtn){
    advSaveProfileBtn.addEventListener('click',function(){
        var client = this.dataset.client;
        this.textContent='Saving…';this.disabled=true;var self=this;
        var savedMsg=document.getElementById('adv-profile-saved');
        fetch(AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:new URLSearchParams({action:'six_adv_save_client_profile',nonce:NONCE,client_id:client,
                first_name:(document.getElementById('adv-prof-first')||{}).value||'',
                last_name:(document.getElementById('adv-prof-last')||{}).value||'',
                phone:(document.getElementById('adv-prof-phone')||{}).value||'',
                business_name:(document.getElementById('adv-prof-biz')||{}).value||'',
                website:(document.getElementById('adv-prof-website')||{}).value||'',
                industry:(document.getElementById('adv-prof-industry')||{}).value||'',
                location:(document.getElementById('adv-prof-location')||{}).value||'',
                goal:(document.getElementById('adv-prof-goal')||{}).value||'',
                challenge:(document.getElementById('adv-prof-challenge')||{}).value||'',
                mktg_budget:(document.getElementById('adv-prof-mktg-budget')||{}).value||'',
                competitors:(document.getElementById('adv-prof-competitors')||{}).value||''})})
        .then(function(r){return r.json();})
        .then(function(d){
            self.textContent=' Save Profile';self.disabled=false;
            if(d&&d.success&&savedMsg){savedMsg.style.display='block';setTimeout(function(){savedMsg.style.display='none';},3000);}
        });
    });
}

// ── Advisor sets service budget directly ───────────────────────────────
document.querySelectorAll('.six-adv-set-budget').forEach(function(btn){
    btn.addEventListener('click',function(){
        var svcId=this.dataset.serviceId,client=this.dataset.client;
        var input=document.querySelector('.adv-svc-budget-input[data-service-id="'+svcId+'"]');
        var budget=input?parseFloat(input.value):0;
        this.textContent='…';this.disabled=true;var self=this;
        fetch(AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:new URLSearchParams({action:'six_adv_set_budget',nonce:NONCE,service_id:svcId,client_id:client,budget:budget})})
        .then(function(r){return r.json();})
        .then(function(d){self.textContent='Update';self.disabled=false;if(d&&d.success)self.textContent='Updated';});
    });
});

// ── Save GA4 Property ID per client ───────────────────────────────────
var saveGa4Btn=document.getElementById('save-ga4-id');
if(saveGa4Btn){saveGa4Btn.addEventListener('click',function(){
    var client=this.dataset.client,val=(document.getElementById('ga4-property-id')||{}).value||'';
    var res=document.getElementById('ga4-result');
    this.textContent='Saving…';this.disabled=true;var self=this;
    fetch(AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({action:'six_save_client_datasource',nonce:NONCE,client_id:client,key:'six_ga4_property_id',value:val})})
    .then(function(r){return r.json();})
    .then(function(d){self.textContent='Save Property ID';self.disabled=false;if(res)res.innerHTML=d.success?'<span style="color:var(--success)">Saved</span>':'<span style="color:var(--danger)">Error</span>';});
});}

// ── Save Meta IDs per client ───────────────────────────────────────────
var saveMetaBtn=document.getElementById('save-meta-ids');
if(saveMetaBtn){saveMetaBtn.addEventListener('click',function(){
    var client=this.dataset.client,res=document.getElementById('meta-result');
    this.textContent='Saving…';this.disabled=true;var self=this;
    var fields={six_meta_business_id:(document.getElementById('meta-business-id')||{}).value||'',
                six_meta_ad_account_id:(document.getElementById('meta-account-id')||{}).value||'',
                six_meta_pixel_id:(document.getElementById('meta-pixel-id')||{}).value||''};
    fetch(AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams(Object.assign({action:'six_save_client_datasources',nonce:NONCE,client_id:client},fields))})
    .then(function(r){return r.json();})
    .then(function(d){self.textContent='Save Meta IDs';self.disabled=false;if(res)res.innerHTML=d.success?'<span style="color:var(--success)">Saved</span>':'<span style="color:var(--danger)">Error</span>';});
});}

// ── Sync to Odoo ───────────────────────────────────────────────────────
var odooBtn=document.getElementById('sync-odoo-btn');
if(odooBtn){odooBtn.addEventListener('click',function(){
    var client=this.dataset.client,res=document.getElementById('odoo-sync-result');
    this.textContent='Syncing…';this.disabled=true;var self=this;
    fetch(AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({action:'six_sync_odoo_client',nonce:NONCE,client_id:client})})
    .then(function(r){return r.json();})
    .then(function(d){self.textContent='↻ Sync to Odoo';self.disabled=false;if(res)res.innerHTML=d.success?'<span style="color:var(--success)">Synced</span>':'<span style="color:var(--danger)">'+(d.data||'Error')+'</span>';});
});}

// ── Edit recommendation (advisor) ─────────────────────────────────────
document.querySelectorAll('.six-adv-edit-rec').forEach(function(btn){
    btn.addEventListener('click',function(){
        var recId=this.dataset.recId,title=this.dataset.title,desc=this.dataset.desc;
        var row=document.getElementById('adv-rec-'+recId);if(!row)return;
        row.querySelector('[style*="font-weight:700"]').innerHTML=
            '<input class="six-input" id="edit-rec-title-'+recId+'" value="'+title.replace(/"/g,'&quot;')+'" style="font-size:13px;margin-bottom:6px">'+
            '<textarea class="six-input" id="edit-rec-desc-'+recId+'" rows="2" style="font-size:12px">'+desc.replace(/</g,'&lt;')+'</textarea>'+
            '<div style="display:flex;gap:6px;margin-top:6px">'+
            '<button class="six-btn six-btn-primary six-btn-sm" onclick="saveRecEdit('+recId+')" style="font-size:11px">Save</button>'+
            '<button class="six-btn six-btn-ghost six-btn-sm" onclick="location.reload()" style="font-size:11px">Cancel</button></div>';
    });
});
function saveRecEdit(recId){
    var title=(document.getElementById('edit-rec-title-'+recId)||{}).value||'';
    var desc=(document.getElementById('edit-rec-desc-'+recId)||{}).value||'';
    fetch(AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({action:'six_adv_edit_rec',nonce:NONCE,rec_id:recId,title:title,description:desc})})
    .then(function(r){return r.json();}).then(function(d){if(d&&d.success)location.reload();});
}

// ── Delete recommendation (advisor) ───────────────────────────────────
document.querySelectorAll('.six-adv-delete-rec').forEach(function(btn){
    btn.addEventListener('click',function(){
        if(!confirm('Delete this recommendation?'))return;
        var recId=this.dataset.recId,row=document.getElementById('adv-rec-'+recId);
        fetch(AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:new URLSearchParams({action:'six_delete_recommendation',nonce:NONCE,rec_id:recId})})
        .then(function(r){return r.json();}).then(function(d){if(d&&d.success&&row){row.style.transition='opacity 0.3s';row.style.opacity='0';setTimeout(function(){row.remove();},300);}});
    });
});

// ── Approve/decline budget (from advisor overview or activity) ─────────
document.querySelectorAll('.six-adv-approve-budget').forEach(function(btn){
    btn.addEventListener('click',function(){
        var svcId=this.dataset.serviceId,budget=this.dataset.budget;
        this.textContent='…';this.disabled=true;var self=this;
        fetch(AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:new URLSearchParams({action:'six_approve_budget_change',nonce:NONCE,service_id:svcId,new_budget:budget})})
        .then(function(r){return r.json();}).then(function(d){if(d&&d.success)location.reload();else{self.textContent='';self.disabled=false;}});
    });
});
document.querySelectorAll('.six-adv-decline-budget').forEach(function(btn){
    btn.addEventListener('click',function(){
        var svcId=this.dataset.serviceId;
        this.textContent='…';this.disabled=true;
        fetch(AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:new URLSearchParams({action:'six_decline_budget_change',nonce:NONCE,service_id:svcId})})
        .then(function(r){return r.json();}).then(function(d){if(d&&d.success)location.reload();});
    });
});

// ── AI suggest clear ──────────────────────────────────────────────────
var clearBtn=document.getElementById('ai-suggest-clear');
if(clearBtn){clearBtn.addEventListener('click',function(){
    var out=document.getElementById('ai-suggest-output'),ph=document.getElementById('ai-suggest-placeholder');
    if(out)out.style.display='none';if(ph)ph.style.display='block';
    document.querySelectorAll('.six-ai-suggest-btn').forEach(function(b){b.style.borderColor='';b.style.color='';});
});}

// ── Theme toggle ─────────────────────────────────────────────────────────
(function(){
    var root=document.getElementById('six-portal-root')||document.documentElement;
    var stored=localStorage.getItem('six_theme')||'light';
    function applyTheme(t){
        if(t==='dark') root.setAttribute('data-theme','dark');
        else root.removeAttribute('data-theme');
        localStorage.setItem('six_theme',t);
        var lbl=document.getElementById('six-theme-label');
        if(lbl) lbl.textContent=t==='dark'?'Light':'Dark';
    }
    applyTheme(stored);
    var btn=document.getElementById('six-theme-btn');
    if(btn) btn.addEventListener('click',function(){
        applyTheme(root.getAttribute('data-theme')==='dark'?'light':'dark');
    });
})();
})();
</script>