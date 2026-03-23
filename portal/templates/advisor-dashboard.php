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
$nonce      = wp_create_nonce( 'six_nonce' );
$ajax_url   = admin_url( 'admin-ajax.php' );

global $wpdb;

$clients = $wpdb->get_results( $wpdb->prepare(
    "SELECT u.ID, u.display_name, u.user_email FROM {$wpdb->prefix}six_assignments a
     INNER JOIN {$wpdb->prefix}users u ON a.client_id=u.ID
     WHERE a.advisor_id=%d ORDER BY u.display_name ASC", $advisor_id
) );

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
                'requested_at'     => $req['requested_at'],
            ) );
        }
    }
}
$total_pending = count( $pending_svcs ) + count( $budget_requests );

$view_client_id = isset( $_GET['client'] ) ? intval( $_GET['client'] ) : 0;
$view_client    = $view_client_id ? get_userdata( $view_client_id ) : null;

$mcc_configured = ! empty( get_option('six_gads_refresh_token') ) && ! empty( get_option('six_gads_developer_token') );
?>
<div class="six-topbar">
    <div class="six-logo">6ix Developers</div>
    <div class="six-role-badge advisor">Advisor Portal</div>
    <button class="six-mobile-menu-btn" id="six-menu-toggle" aria-label="Menu">☰</button>
    <div class="six-topbar-right">
        <?php if($unread_n>0):?><a href="?tab=notifications" class="six-notif-bell" style="text-decoration:none;color:inherit">🔔 <span class="six-badge"><?php echo $unread_n;?></span></a><?php endif;?>
        <span class="six-user-name"><?php echo esc_html($advisor->display_name);?></span>
        <div class="six-avatar"><?php echo esc_html($initials);?></div>
    </div>
</div>

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
                <span class="six-nav-icon">⚡</span> Approvals
                <?php if($total_pending>0):?><span class="six-badge"><?php echo $total_pending;?></span><?php endif;?>
            </a>
        </div>
        <div class="six-nav-section">
            <div class="six-nav-label">Management</div>
            <a href="?tab=reports"  class="six-nav-item <?php echo $active_tab==='reports' ?'active':'';?>"><span class="six-nav-icon">◷</span> Reports</a>
            <a href="?tab=revenue"  class="six-nav-item <?php echo $active_tab==='revenue' ?'active':'';?>"><span class="six-nav-icon">⬠</span> Revenue</a>
            <a href="?tab=gads"     class="six-nav-item <?php echo $active_tab==='gads'    ?'active':'';?>">
                <span class="six-nav-icon">📊</span> Google Ads
                <?php if(!$mcc_configured):?><span style="font-size:9px;color:var(--warning);margin-left:auto">Setup</span><?php endif;?>
            </a>
            <a href="?tab=calendar" class="six-nav-item <?php echo $active_tab==='calendar'?'active':'';?>">
                <span class="six-nav-icon">📅</span> Calendar
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
            $sc = (array) $wpdb->get_results($wpdb->prepare(
                "SELECT service_name,status FROM {$wpdb->prefix}six_client_services WHERE client_id=%d AND status='active'",$cl->ID));
            $mr = floatval($wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(budget),0) FROM {$wpdb->prefix}six_client_services WHERE client_id=%d AND status='active'",$cl->ID)));
            $total_avg_health += $h; $health_count++;
            $clients_attention[] = array('id'=>$cl->ID,'name'=>$cl->display_name,'email'=>$cl->user_email,
                'health'=>$h,'services'=>$sc,'mrr'=>$mr,'attention'=>$h<75||($mr==0&&count($sc)>0));
        }
        $avg_health = $health_count ? round($total_avg_health/$health_count) : 0;
        usort($clients_attention, fn($a,$b)=>$a['health']<=>$b['health']); // lowest health first

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
                if (!in_array('Brand Development',$svc_names)) $upsells[]=array('client'=>$cl['name'],'service'=>'Brand Dev','reason'=>'High engagement, ready to expand','color'=>'var(--cyan)');
                if (!in_array('Social Media Marketing',$svc_names)) $upsells[]=array('client'=>$cl['name'],'service'=>'Social Media','reason'=>'Strong performance signals brand momentum','color'=>'var(--cyan)');
            }
            if (count($upsells)>=4) break;
        }
        $clients_need_attention = count(array_filter($clients_attention,fn($c)=>$c['attention']));
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
        <div class="six-stats-grid" style="margin-bottom:24px">
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
                <?php $attention_ct=count(array_filter($clients_attention,fn($c)=>$c['health']<50)); if($attention_ct>0):?>
                <div class="six-stat-trend" style="color:var(--danger)">↓ <?php echo $attention_ct;?> critical</div>
                <?php else:?><div class="six-stat-trend up">↑ Healthy</div><?php endif;?>
            </div>
        </div>

        <!-- ── Two-column layout (Clients + right panel) ───────────── -->
        <div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start">

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
                                <span class="six-tag" style="font-size:11px"><?php echo esc_html($cl['services'][0]['service_name']??'');?></span>
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
                            <div style="font-size:28px;margin-bottom:10px">📅</div>
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
                            <div style="font-size:12px;font-weight:700;color:var(--warning)">⚡ <?php echo $total_pending;?> Pending Approval<?php echo $total_pending>1?'s':'';?></div>
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
            $c_svcs    = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}six_client_services WHERE client_id=%d ORDER BY status DESC",$view_client_id));
            $c_metrics = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}six_metrics WHERE client_id=%d ORDER BY service_slug,label",$view_client_id));
            $c_recs    = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}six_recommendations WHERE client_id=%d AND status='active' ORDER BY created_at DESC",$view_client_id));
            $c_gads    = get_user_meta($view_client_id,'six_gads_customer_id_display',true)?:get_user_meta($view_client_id,'six_gads_customer_id',true);
            $c_sync    = get_user_meta($view_client_id,'six_gads_last_sync',true);
            $health    = class_exists('Six_Health_Score')?Six_Health_Score::calculate($view_client_id):0;
        ?>
        <div class="six-page-header">
            <div>
                <a href="?tab=clients" style="font-size:12px;color:var(--text3);text-decoration:none">← All Clients</a>
                <h1 class="six-page-title" style="margin-top:4px"><?php echo esc_html($view_client->display_name);?></h1>
                <p class="six-page-sub"><?php echo esc_html($view_client->user_email);?></p>
            </div>
            <span class="six-health <?php echo $health>=75?'high':($health>=50?'med':'low');?>" style="font-size:16px;padding:6px 14px">Health: <?php echo $health;?>%</span>
        </div>

        <div class="six-grid-2" style="margin-bottom:16px">
            <!-- Google Ads: just Customer ID (MCC handles auth) -->
            <div class="six-card">
                <div class="six-card-header">
                    <span class="six-card-title">📊 Google Ads</span>
                    <?php if(!$mcc_configured):?>
                    <a href="?tab=gads" class="six-btn six-btn-ghost six-btn-sm" style="color:var(--warning)">⚠ Setup MCC first</a>
                    <?php elseif($c_gads):?>
                    <span class="six-status-badge active">Connected</span>
                    <?php else:?>
                    <span class="six-status-badge pending">No ID yet</span>
                    <?php endif;?>
                </div>
                <div class="six-card-body">
                    <?php if(!$mcc_configured):?>
                    <p style="font-size:12px;color:var(--text2)">Configure MCC credentials in <a href="?tab=gads" style="color:var(--cyan)">Google Ads Setup</a> first, then come back to add customer IDs.</p>
                    <?php else:?>
                    <div class="six-form-group">
                        <label class="six-label">Customer ID</label>
                        <input class="six-input" id="gads-cid" value="<?php echo esc_attr($c_gads);?>" placeholder="123-456-7890" style="font-family:monospace">
                        <div style="font-size:11px;color:var(--text3);margin-top:4px">Format: 123-456-7890. Found in Google Ads → top right corner.</div>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                        <button class="six-btn six-btn-primary six-btn-sm" id="save-gads-cid" data-client="<?php echo $view_client_id;?>">Save Customer ID</button>
                        <?php if($c_gads):?>
                        <button class="six-btn six-btn-secondary six-btn-sm" id="sync-gads-now" data-client="<?php echo $view_client_id;?>">↻ Sync Now</button>
                        <?php if($c_sync):?><span style="font-size:11px;color:var(--text3)">Last: <?php echo human_time_diff(strtotime($c_sync),time()).' ago';?></span><?php endif;?>
                        <?php endif;?>
                    </div>
                    <div id="gads-result" style="margin-top:10px;font-size:12px"></div>
                    <?php endif;?>
                </div>
            </div>

            <!-- Add / Edit Metric -->
            <div class="six-card" id="metric-card">
                <div class="six-card-header"><span class="six-card-title" id="metric-form-heading">+ Add Metric</span></div>
                <div class="six-card-body">
                    <input type="hidden" id="metric-edit-id" value="">
                    <div class="six-form-group">
                        <label class="six-label">Service</label>
                        <select class="six-input" id="metric-svc">
                            <option value="google-ads">Google Ads</option>
                            <option value="seo">SEO</option>
                            <option value="social-media">Social Media</option>
                            <option value="brand-dev">Brand Development</option>
                        </select>
                    </div>
                    <div class="six-form-group"><label class="six-label">Label</label><input class="six-input" id="metric-label" placeholder="e.g. Leads Generated"></div>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:12px">
                        <div><label class="six-label">Previous</label><input class="six-input" id="metric-prev" placeholder="20"></div>
                        <div><label class="six-label">Current</label><input class="six-input" id="metric-cur" placeholder="35"></div>
                        <div><label class="six-label">Target</label><input class="six-input" id="metric-target" placeholder="60"></div>
                    </div>
                    <div style="display:flex;gap:8px">
                        <button class="six-btn six-btn-primary six-btn-sm" id="save-metric" data-client="<?php echo $view_client_id;?>">Save Metric</button>
                        <button class="six-btn six-btn-ghost six-btn-sm" id="cancel-metric" style="display:none">Cancel</button>
                    </div>
                    <div id="metric-result" style="margin-top:8px;font-size:12px"></div>
                </div>
            </div>
        </div>

        <!-- Metrics table -->
        <?php if(!empty($c_metrics)):?>
        <div class="six-card" style="margin-bottom:16px">
            <div class="six-card-header"><span class="six-card-title">Current Metrics</span></div>
            <div class="six-card-body" style="padding:0">
            <table class="six-table">
                <thead><tr><th>Service</th><th>Metric</th><th>Previous</th><th>Current</th><th>Target</th><th>Updated</th><th>Actions</th></tr></thead>
                <tbody id="metrics-tbody">
                <?php foreach($c_metrics as $m):?>
                <tr id="mrow-<?php echo $m->id;?>">
                    <td><span class="six-tag"><?php echo esc_html(ucwords(str_replace('-',' ',$m->service_slug)));?></span></td>
                    <td><strong><?php echo esc_html($m->label);?></strong></td>
                    <td style="color:var(--text3)"><?php echo esc_html($m->previous_value?:'—');?></td>
                    <td style="color:var(--success);font-weight:600"><?php echo esc_html($m->current_value);?></td>
                    <td style="color:var(--cyan)"><?php echo esc_html($m->target_value?:'—');?></td>
                    <td style="font-size:11px;color:var(--text3)"><?php echo human_time_diff(strtotime($m->updated_at),time()).' ago';?></td>
                    <td>
                        <div style="display:flex;gap:6px">
                        <button class="six-btn six-btn-secondary six-btn-sm six-edit-metric"
                            data-id="<?php echo $m->id;?>" data-svc="<?php echo esc_attr($m->service_slug);?>"
                            data-label="<?php echo esc_attr($m->label);?>" data-prev="<?php echo esc_attr($m->previous_value);?>"
                            data-cur="<?php echo esc_attr($m->current_value);?>" data-target="<?php echo esc_attr($m->target_value);?>">Edit</button>
                        <button class="six-btn six-btn-ghost six-btn-sm six-delete-metric"
                            data-id="<?php echo $m->id;?>" style="color:var(--danger);border-color:rgba(255,107,107,0.3)">Delete</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach;?>
                </tbody>
            </table>
            </div>
        </div>
        <?php endif;?>

        <!-- Recommendations -->
        <div class="six-card" style="margin-bottom:16px">
            <div class="six-card-header"><span class="six-card-title">Recommendations</span></div>
            <div class="six-card-body">
                <div id="recs-list">
                <?php if(empty($c_recs)):?>
                    <p style="color:var(--text3);font-size:12px;margin:0 0 16px">No active recommendations yet.</p>
                <?php else:?>
                    <?php foreach($c_recs as $rec):?>
                    <div class="six-rec" id="arec-<?php echo $rec->id;?>">
                        <div class="six-rec-icon">💡</div>
                        <div style="flex:1">
                            <div class="six-rec-title"><?php echo esc_html($rec->title);?></div>
                            <div class="six-rec-desc"><?php echo esc_html($rec->description);?></div>
                        </div>
                        <div style="display:flex;gap:6px;flex-shrink:0;margin-left:12px">
                            <button class="six-btn six-btn-secondary six-btn-sm six-edit-rec"
                                data-id="<?php echo $rec->id;?>" data-title="<?php echo esc_attr($rec->title);?>"
                                data-desc="<?php echo esc_attr($rec->description);?>" data-action="<?php echo esc_attr($rec->action_label);?>">Edit</button>
                            <button class="six-btn six-btn-ghost six-btn-sm six-delete-rec"
                                data-id="<?php echo $rec->id;?>" style="color:var(--danger);border-color:rgba(255,107,107,0.3)">Del</button>
                        </div>
                    </div>
                    <?php endforeach;?>
                <?php endif;?>
                </div>

                <!-- Add/Edit Recommendation Form -->
                <div style="border-top:1px solid var(--border);padding-top:14px;margin-top:8px">
                    <div id="rec-form-heading" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--text3);margin-bottom:10px">+ New Recommendation</div>
                    <input type="hidden" id="rec-edit-id" value="">
                    <div class="six-form-group"><label class="six-label">Title</label><input class="six-input" id="rec-title" placeholder="e.g. Increase Google Ads Budget"></div>
                    <div class="six-form-group"><label class="six-label">Description</label><textarea class="six-input" id="rec-desc" rows="2" placeholder="Explain the recommendation…"></textarea></div>
                    <div class="six-form-group"><label class="six-label">Action Button Label</label><input class="six-input" id="rec-action" placeholder="e.g. Increase Budget"></div>
                    <div style="display:flex;gap:8px">
                        <button class="six-btn six-btn-primary six-btn-sm" id="save-rec" data-client="<?php echo $view_client_id;?>">Send Recommendation</button>
                        <button class="six-btn six-btn-ghost six-btn-sm" id="cancel-rec" style="display:none">Cancel</button>
                    </div>
                    <div id="rec-result" style="margin-top:8px;font-size:12px"></div>
                </div>
            </div>
        </div>

        <!-- Upload Report -->
        <div class="six-card">
            <div class="six-card-header"><span class="six-card-title">Upload Report</span></div>
            <div class="six-card-body">
                <div class="six-grid-2" style="margin-bottom:0">
                    <div class="six-form-group"><label class="six-label">Report Title</label><input class="six-input" id="rpt-title" placeholder="e.g. March 2026 Monthly Report"></div>
                    <div class="six-form-group"><label class="six-label">Period</label><input class="six-input" id="rpt-period" placeholder="e.g. March 2026"></div>
                </div>
                <div class="six-form-group">
                    <label class="six-label">Upload Method</label>
                    <div style="display:flex;gap:16px;margin-bottom:10px">
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px"><input type="radio" name="rpt-method" value="file" checked onchange="document.getElementById('rpt-file-area').style.display='';document.getElementById('rpt-url-area').style.display='none'"> Upload File</label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px"><input type="radio" name="rpt-method" value="url" onchange="document.getElementById('rpt-url-area').style.display='';document.getElementById('rpt-file-area').style.display='none'"> Paste URL</label>
                    </div>
                    <div id="rpt-file-area">
                        <input type="file" class="six-input" id="rpt-file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx" style="padding:6px">
                        <div style="font-size:11px;color:var(--text3);margin-top:4px">PDF, Word, Excel, PowerPoint</div>
                    </div>
                    <div id="rpt-url-area" style="display:none">
                        <input class="six-input" id="rpt-url" placeholder="https://drive.google.com/...">
                    </div>
                </div>
                <button class="six-btn six-btn-primary" id="save-report" data-client="<?php echo $view_client_id;?>">📤 Publish Report</button>
                <div id="report-result" style="margin-top:8px;font-size:12px"></div>
            </div>
        </div>

        <?php else: /* Clients list */ ?>
        <div class="six-page-header"><div><h1 class="six-page-title">Your Clients</h1><p class="six-page-sub"><?php echo count($clients);?> assigned</p></div></div>
        <div class="six-card"><div class="six-card-body" style="padding:0">
        <?php if(empty($clients)):?>
            <div style="padding:40px;text-align:center;color:var(--text3)">No clients assigned. Go to WP Admin → 6ix Portal → Assign Advisors.</div>
        <?php else:?>
            <table class="six-table"><thead><tr><th>Client</th><th>Health</th><th>Services</th><th>MRR</th><th>Last Active</th><th></th></tr></thead><tbody>
            <?php foreach($clients as $c):
                $h=class_exists('Six_Health_Score')?Six_Health_Score::calculate($c->ID):0;
                $sc=intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}six_client_services WHERE client_id=%d AND status='active'",$c->ID)));
                $mr=$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(budget),0) FROM {$wpdb->prefix}six_client_services WHERE client_id=%d AND status='active'",$c->ID));
                $la=get_user_meta($c->ID,'six_last_activity',true);
            ?>
            <tr>
                <td><div style="display:flex;align-items:center;gap:10px"><div class="six-client-initials"><?php echo esc_html(six_get_initials($c->display_name));?></div><div><strong><?php echo esc_html($c->display_name);?></strong><div style="font-size:11px;color:var(--text3)"><?php echo esc_html($c->user_email);?></div></div></div></td>
                <td><span class="six-health <?php echo $h>=75?'high':($h>=50?'med':'low');?>"><?php echo $h;?>%</span></td>
                <td><?php echo $sc;?></td>
                <td><?php echo $mr>0?'$'.number_format($mr,0):'—';?></td>
                <td style="font-size:11px;color:var(--text3)"><?php echo $la?human_time_diff(strtotime($la),time()).' ago':'Never';?></td>
                <td><a href="?tab=clients&client=<?php echo $c->ID;?>" class="six-btn six-btn-primary six-btn-sm">Manage →</a></td>
            </tr>
            <?php endforeach;?>
            </tbody></table>
        <?php endif;?>
        </div></div>
        <?php endif;?>

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
                <div class="six-card-body" style="text-align:center;padding:60px;color:var(--text3)"><div style="font-size:30px;margin-bottom:12px">💬</div>Select a conversation or start a new one.</div>
                <?php endif;?>
            </div>
        </div>

    <?php /* ════════════ NOTIFICATIONS ════════════ */ elseif($active_tab==='notifications'):?>
        <div class="six-page-header">
            <div><h1 class="six-page-title">Alerts</h1></div>
            <?php if($unread_n>0):?><button class="six-btn six-btn-secondary" id="mark-all-read">Mark All Read</button><?php endif;?>
        </div>
        <div class="six-card"><div class="six-card-body" style="padding:10px">
            <?php if(empty($notifs)):?><div style="text-align:center;padding:40px;color:var(--text3)">All clear!</div>
            <?php else:?>
            <?php foreach($notifs as $n):?>
            <div class="six-notif-row <?php echo !$n->is_read?'unread':'';?>">
                <div class="six-notif-dot <?php echo $n->is_read?'read':'';?>"></div>
                <div style="flex:1">
                    <div class="six-notif-title"><?php echo esc_html($n->title);?></div>
                    <div class="six-notif-desc"><?php echo esc_html($n->message);?></div>
                    <div class="six-notif-time"><?php echo human_time_diff(strtotime($n->created_at),time()).' ago';?></div>
                </div>
            </div>
            <?php endforeach;?>
            <?php endif;?>
        </div></div>

    <?php /* ════════════ APPROVALS ════════════ */ elseif($active_tab==='approvals'):?>
        <div class="six-page-header"><div><h1 class="six-page-title">Approvals</h1><p class="six-page-sub"><?php echo $total_pending;?> pending</p></div></div>

        <?php if(!empty($pending_svcs)):?>
        <div class="six-card" style="margin-bottom:16px">
            <div class="six-card-header"><span class="six-card-title">Service Requests</span></div>
            <div class="six-card-body" style="padding:0">
            <table class="six-table"><thead><tr><th>Client</th><th>Service</th><th>Requested</th><th>Action</th></tr></thead><tbody>
            <?php foreach($pending_svcs as $ps):?>
            <tr>
                <td><strong><?php echo esc_html($ps->client_name);?></strong></td>
                <td><?php echo esc_html($ps->service_name);?></td>
                <td style="font-size:11px;color:var(--text3)"><?php echo date('M j',strtotime($ps->id?current_time('mysql'):'now'));?></td>
                <td><button class="six-btn six-btn-primary six-btn-sm six-approve-service" data-id="<?php echo $ps->id;?>">✓ Approve</button></td>
            </tr>
            <?php endforeach;?>
            </tbody></table>
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
        <?php if($total_pending===0):?><div class="six-card"><div class="six-card-body" style="text-align:center;padding:40px;color:var(--text3)">✓ Nothing pending.</div></div><?php endif;?>

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
                <div style="font-size:28px"><?php echo $mcc_configured?'✅':'⚠️';?></div>
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

        <div class="six-grid-2">
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
                        'six_gads_refresh_token'   => array('label'=>'MCC Refresh Token','type'=>'password','hint'=>'You already generated this ✓ — paste it here. Does NOT expire unless revoked.'),
                    );
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
                    <button class="six-btn six-btn-primary" id="save-mcc" style="margin-top:4px">💾 Save & Verify Connection</button>
                    <div id="mcc-result" style="margin-top:12px;font-size:12px"></div>
                </div>
            </div>

            <div class="six-card">
                <div class="six-card-header"><span class="six-card-title">How It Works</span></div>
                <div class="six-card-body" style="font-size:12px;color:var(--text2);line-height:1.9">
                    <div style="background:rgba(255,102,153,0.08);border:1px solid rgba(255,102,153,0.2);border-radius:8px;padding:12px;margin-bottom:14px">
                        <strong style="color:var(--pink)">Q: Do I need a refresh token per customer?</strong><br>
                        <strong style="color:var(--success)">NO.</strong> You need exactly one refresh token — for your MCC account.
                    </div>
                    <div style="background:rgba(131,197,237,0.08);border:1px solid rgba(131,197,237,0.2);border-radius:8px;padding:12px;margin-bottom:14px">
                        <strong style="color:var(--cyan)">Q: Can I query all customer accounts with one token?</strong><br>
                        <strong style="color:var(--success)">YES.</strong> The <code style="background:var(--dark3);padding:1px 5px;border-radius:3px">login-customer-id</code> header authenticates as your MCC, and the Customer ID in the request URL targets the sub-account.
                    </div>
                    <div><strong>Per client, advisors only enter:</strong><br>
                    <code style="background:var(--dark3);padding:4px 10px;border-radius:4px;display:inline-block;margin-top:4px;font-size:13px">Customer ID (e.g. 123-456-7890)</code></div>
                    <div style="margin-top:14px"><strong>Token refresh is fully automatic.</strong><br>
                    Access tokens (1hr lifetime) are refreshed silently. MCC refresh tokens don't expire unless you revoke access in Google Account settings.</div>
                    <div style="margin-top:14px"><strong>Daily sync runs at 3am</strong> for all clients with a Customer ID set. Manual sync available per client.</div>
                </div>
            </div>
        </div>

        <!-- Client status table -->
        <div class="six-card" style="margin-top:16px">
            <div class="six-card-header"><span class="six-card-title">Client Google Ads Status</span></div>
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
            echo '<div style="background:rgba(86,211,100,0.1);border:1px solid rgba(86,211,100,0.3);border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:var(--success)">✅ Google Calendar connected successfully!</div>';
        }
        if ( isset($_GET['gcal_error']) ) {
            echo '<div style="background:rgba(255,107,107,0.1);border:1px solid var(--danger);border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:var(--danger)">❌ Connection error: '.esc_html($_GET['gcal_error']).' — please try again.</div>';
        }

        // Handle disconnect
        if ( isset($_GET['gcal_disconnect']) ) {
            delete_user_meta($advisor_id,'six_gcal_refresh_token');
            delete_user_meta($advisor_id,'six_gcal_access_token');
            delete_user_meta($advisor_id,'six_gcal_token_expires');
            delete_user_meta($advisor_id,'six_gcal_email');
            wp_redirect(home_url('/advisor-portal/?tab=calendar')); exit;
        }

        // Handle OAuth callback
        // ── FIXED REDIRECT URI ────────────────────────────────────────────
        // Must be registered EXACTLY in Google Cloud Console → Credentials →
        // OAuth 2.0 Client IDs → Authorized Redirect URIs:
        //   https://6ixdevelopers.com/6ix-redesign/advisor-portal/gcal/
        //
        // We use a clean URI with NO query params. The advisor_id is passed
        // via the `state` parameter which Google returns unchanged.
        $gcal_redirect_uri = home_url('/advisor-portal/gcal/');

        if ( isset($_GET['gcal_auth']) ) {
            $client_id = get_option('six_google_client_id');
            if ($client_id) {
                // Encode advisor_id + nonce into state so we can verify on return
                $state = base64_encode(json_encode(array(
                    'advisor_id' => $advisor_id,
                    'nonce'      => wp_create_nonce('six_gcal_'.$advisor_id),
                )));
                $google_auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query(array(
                    'client_id'     => $client_id,
                    'redirect_uri'  => $gcal_redirect_uri,
                    'response_type' => 'code',
                    'scope'         => 'https://www.googleapis.com/auth/calendar.readonly https://www.googleapis.com/auth/userinfo.email',
                    'access_type'   => 'offline',
                    'prompt'        => 'consent',
                    'state'         => $state,
                ));
                wp_redirect($google_auth_url); exit;
            }
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
            <div style="width:80px;height:80px;border-radius:50%;background:var(--dark3);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:36px;margin:0 auto 24px">📅</div>
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
                    <?php if($gcal_email):?><span style="font-size:11px;color:var(--text3)">📧 <?php echo esc_html($gcal_email);?></span><?php endif;?>
                </div>
                <div class="six-card-body" style="padding:0">
                <?php if(empty($upcoming_events)):?>
                    <div style="padding:40px;text-align:center;color:var(--text3)">
                        <div style="font-size:32px;margin-bottom:12px">🗓</div>
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
                        $this_week  = array_filter($upcoming_events, fn($e)=>strtotime($e['start'])>=$week_start&&strtotime($e['start'])<=$week_end);
                        $today_ev   = array_filter($upcoming_events, fn($e)=>date('Y-m-d',strtotime($e['start']))===$today_str);
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
                        <span style="font-size:22px">✅</span>
                        <div>
                            <div style="font-size:12px;font-weight:600;color:var(--success)">Calendar Connected</div>
                            <?php if($gcal_email):?><div style="font-size:11px;color:var(--text3);margin-top:2px"><?php echo esc_html($gcal_email);?></div><?php endif;?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php endif; // gcal_connected ?>

    <?php endif;?>
    </main>
</div>

<script>
(function(){
'use strict';
const AJAX  = '<?php echo esc_js($ajax_url);?>';
const NONCE = '<?php echo esc_js($nonce);?>';
const INI   = '<?php echo esc_js($initials);?>';

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
document.querySelectorAll('.six-approve-service').forEach(function(btn){
    btn.addEventListener('click',function(){
        var id=this.dataset.id, row=this.closest('tr'); this.textContent='Approving…';this.disabled=true;
        post({action:'six_approve_service',service_id:id}).then(function(res){if(res.success){row&&row.remove();}});
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
        document.getElementById('metric-form-heading').textContent='✏️ Edit Metric';
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
        document.getElementById('metric-result').innerHTML=res.success?'<span style="color:var(--success)">✓ Saved!</span>':'<span style="color:var(--danger)">Error</span>';
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
        document.getElementById('rec-form-heading').textContent='✏️ Edit Recommendation';
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
        document.getElementById('rec-result').innerHTML=res.success?'<span style="color:var(--success)">✓ '+(res.data&&res.data.updated?'Updated!':'Sent to client!')+'</span>':'<span style="color:var(--danger)">Error</span>';
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
            ?'<span style="color:var(--success)">✓ Report published!</span>'
            :'<span style="color:var(--danger)">'+(res.data||'Upload failed')+'</span>';
        saveReport.textContent='📤 Publish Report';saveReport.disabled=false;
    });
});

// ── Google Ads: save Customer ID ─────────────────────────────────────────────
var saveCid=document.getElementById('save-gads-cid');
if(saveCid) saveCid.addEventListener('click',function(){
    var btn=this;btn.textContent='Saving…';btn.disabled=true;
    post({action:'six_save_client_gads',client_id:btn.dataset.client,six_gads_customer_id:(document.getElementById('gads-cid')||{}).value||''}).then(function(res){
        document.getElementById('gads-result').innerHTML=res.success?'<span style="color:var(--success)">✓ '+(res.data.message||'Saved')+'</span>':'<span style="color:var(--danger)">'+(res.data||'Error')+'</span>';
        btn.textContent='Save Customer ID';btn.disabled=false;
    });
});
var syncNow=document.getElementById('sync-gads-now');
if(syncNow) syncNow.addEventListener('click',function(){
    var btn=this;btn.textContent='Syncing…';btn.disabled=true;
    post({action:'six_sync_client_gads',client_id:btn.dataset.client}).then(function(res){
        document.getElementById('gads-result').innerHTML=res.success
            ?'<span style="color:var(--success)">✓ Synced! Metrics updated.</span>'
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
            ?'<span style="color:var(--success)">✅ '+(res.data.message||'Connected')+'</span>'
            :'<span style="color:var(--danger)">'+(res.data||'Verification failed')+'</span>';
        btn.textContent='💾 Save & Verify Connection'; btn.disabled=false;
        if(res.success) setTimeout(function(){location.reload();},1500);
    });
});

// ── Mark all notifications read ───────────────────────────────────────────────
var markAll=document.getElementById('mark-all-read');
if(markAll) markAll.addEventListener('click',function(){
    post({action:'six_mark_all_notifications_read'}).then(function(){location.reload();});
});

})();
</script>

<script>
(function(){
  var btn = document.getElementById('six-menu-toggle');
  var sidebar = document.querySelector('.six-sidebar');
  var overlay = document.getElementById('six-overlay');
  if(!btn || !sidebar) return;
  function openMenu(){ sidebar.classList.add('open'); overlay && overlay.classList.add('open'); document.body.style.overflow='hidden'; }
  function closeMenu(){ sidebar.classList.remove('open'); overlay && overlay.classList.remove('open'); document.body.style.overflow=''; }
  btn.addEventListener('click', function(){ sidebar.classList.contains('open') ? closeMenu() : openMenu(); });
  if(overlay) overlay.addEventListener('click', closeMenu);
  // Close on nav link click (mobile UX)
  sidebar.querySelectorAll('.six-nav-item').forEach(function(a){
    a.addEventListener('click', function(){ if(window.innerWidth<=768) closeMenu(); });
  });
})();
</script>