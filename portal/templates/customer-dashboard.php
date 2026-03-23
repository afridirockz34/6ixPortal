<?php
/**
 * Customer Dashboard v3
 * Upload to: /wp-content/themes/6ixClaude/portal/templates/customer-dashboard.php
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$user_id    = get_current_user_id();
$user       = wp_get_current_user();
$initials   = six_get_initials( $user->display_name );
$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'overview';
$nonce      = wp_create_nonce( 'six_nonce' );
$ajax_url   = admin_url( 'admin-ajax.php' );

global $wpdb;

$advisor_id  = $wpdb->get_var( $wpdb->prepare( "SELECT advisor_id FROM {$wpdb->prefix}six_assignments WHERE client_id=%d", $user_id ) );
$advisor     = $advisor_id ? get_userdata( $advisor_id ) : null;
$services    = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}six_client_services WHERE client_id=%d ORDER BY status DESC", $user_id ) );
$metrics     = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}six_metrics WHERE client_id=%d ORDER BY service_slug,label", $user_id ) );
$recs        = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}six_recommendations WHERE client_id=%d AND status='active' ORDER BY created_at DESC LIMIT 5", $user_id ) );
$reports     = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}six_reports WHERE client_id=%d ORDER BY created_at DESC", $user_id ) );
$checkout    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d", $user_id ) );
$unread_msgs = class_exists('Six_Messaging')     ? Six_Messaging::get_unread_count( $user_id )   : 0;
$notifs      = class_exists('Six_Notifications') ? Six_Notifications::get_for_user( $user_id, 5 ) : array();
$unread_n    = class_exists('Six_Notifications') ? Six_Notifications::get_unread_count( $user_id ) : 0;
$readiness   = $checkout ? intval( $checkout->score ?? 0 ) : 0;

$svc_colors = array( 'google-ads' => '#4285F4', 'seo' => '#56D364', 'social-media' => '#FF6699', 'brand-dev' => '#E3B341' );
$svc_icons  = array( 'google-ads' => '📊', 'seo' => '🔍', 'social-media' => '📱', 'brand-dev' => '🎨' );
?>
<div class="six-topbar">
    <div class="six-logo">6ix Developers</div>
    <div class="six-role-badge">Client Portal</div>
    <button class="six-mobile-menu-btn" id="six-menu-toggle" aria-label="Menu">☰</button>
    <div class="six-topbar-right">
        <?php if ( $unread_n > 0 ) : ?><span class="six-notif-bell" style="cursor:pointer">🔔 <span class="six-badge"><?php echo $unread_n; ?></span></span><?php endif; ?>
        <span class="six-user-name"><?php echo esc_html( $user->display_name ); ?></span>
        <div class="six-avatar"><?php echo esc_html( $initials ); ?></div>
    </div>
</div>

<div class="six-layout">
    <nav class="six-sidebar">
        <div class="six-nav-section">
            <div class="six-nav-label">Main</div>
            <a href="?tab=overview"  class="six-nav-item <?php echo $active_tab==='overview' ?'active':''; ?>"><span class="six-nav-icon">⬡</span> Overview</a>
            <a href="?tab=services"  class="six-nav-item <?php echo $active_tab==='services' ?'active':''; ?>"><span class="six-nav-icon">◈</span> Services</a>
            <a href="?tab=reports"   class="six-nav-item <?php echo $active_tab==='reports'  ?'active':''; ?>"><span class="six-nav-icon">◎</span> Reports</a>
            <a href="?tab=messages"  class="six-nav-item <?php echo $active_tab==='messages' ?'active':''; ?>">
                <span class="six-nav-icon">◻</span> Messages
                <?php if ($unread_msgs>0):?><span class="six-badge"><?php echo $unread_msgs;?></span><?php endif;?>
            </a>
        </div>
        <div class="six-nav-section">
            <div class="six-nav-label">Account</div>
            <a href="?tab=advisor"   class="six-nav-item <?php echo $active_tab==='advisor'  ?'active':''; ?>"><span class="six-nav-icon">◷</span> Advisor</a>
            <a href="?tab=billing"   class="six-nav-item <?php echo $active_tab==='billing'  ?'active':''; ?>"><span class="six-nav-icon">⬠</span> Billing</a>
            <a href="?tab=profile"   class="six-nav-item <?php echo $active_tab==='profile'  ?'active':''; ?>"><span class="six-nav-icon">◉</span> Profile</a>
        </div>
        <div class="six-sidebar-bottom" style="margin-top:auto">
            <a href="<?php echo esc_url(wp_logout_url(home_url('/get-started/'))); ?>" class="six-nav-item" style="color:var(--text3);margin-bottom:12px"><span class="six-nav-icon">↩</span> Log Out</a>
        </div>
        <?php if ( $advisor ) : ?>
        <div class="six-sidebar-bottom">
            <div class="six-advisor-card">
                <div class="six-advisor-avatar"><?php echo esc_html( six_get_initials( $advisor->display_name ) ); ?></div>
                <div class="six-advisor-info">
                    <div class="six-advisor-name"><?php echo esc_html( $advisor->display_name ); ?></div>
                    <div class="six-advisor-role">Your Advisor</div>
                </div>
                <span class="six-online-dot"></span>
            </div>
        </div>
        <?php endif; ?>
    </nav>

    <main class="six-main">

    <?php /* ════════════════════ OVERVIEW ════════════════════ */ if ( $active_tab === 'overview' ) : ?>

        <div class="six-page-header">
            <div>
                <h1 class="six-page-title">Good <?php echo date('H')<12?'morning':(date('H')<17?'afternoon':'evening'); ?>, <?php echo esc_html( $user->first_name ?: $user->display_name ); ?> 👋</h1>
                <p class="six-page-sub">Here's your marketing performance at a glance</p>
            </div>
            <a href="?tab=services" class="six-btn six-btn-primary">+ Add Service</a>
        </div>

        <!-- Readiness Banner -->
        <div class="six-readiness-banner">
            <div>
                <div class="six-readiness-label">Marketing Readiness Score</div>
                <div class="six-readiness-score"><?php echo $readiness; ?>%</div>
                <?php if ( $checkout && $checkout->business_name ) : ?>
                    <div class="six-readiness-sub">Business: <strong><?php echo esc_html( $checkout->business_name ); ?></strong></div>
                <?php else : ?>
                    <div class="six-readiness-sub"><a href="?tab=profile">Complete your profile</a> to improve your score</div>
                <?php endif; ?>
            </div>
            <div class="six-readiness-stats">
                <div class="six-readiness-stat">
                    <div class="six-readiness-stat-label">Active Services</div>
                    <div class="six-readiness-stat-val" style="color:var(--cyan)"><?php echo count( array_filter( $services, fn($s)=>$s->status==='active' ) ); ?></div>
                </div>
                <div class="six-readiness-stat">
                    <div class="six-readiness-stat-label">Metrics</div>
                    <div class="six-readiness-stat-val" style="color:var(--pink)"><?php echo count( $metrics ); ?></div>
                </div>
            </div>
        </div>

        <!-- Notifications -->
        <?php if ( ! empty( $notifs ) ) : ?>
        <div class="six-card" style="margin-bottom:16px">
            <div class="six-card-header">
                <span class="six-card-title">Recent Activity</span>
                <?php if ( $unread_n > 0 ) : ?><span class="six-badge"><?php echo $unread_n; ?> new</span><?php endif; ?>
            </div>
            <div class="six-card-body" style="padding:8px 12px">
                <?php foreach ( array_slice( $notifs, 0, 3 ) as $n ) : ?>
                <div class="six-notif-row <?php echo !$n->is_read ? 'unread' : ''; ?>">
                    <div class="six-notif-dot <?php echo $n->is_read ? 'read' : ''; ?>"></div>
                    <div>
                        <div class="six-notif-title"><?php echo esc_html( $n->title ); ?></div>
                        <div class="six-notif-desc"><?php echo esc_html( $n->message ); ?></div>
                        <div class="six-notif-time"><?php echo human_time_diff( strtotime( $n->created_at ), time() ) . ' ago'; ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Active Service Cards -->
        <?php if ( ! empty( $services ) ) : ?>
        <div class="six-grid-2" style="margin-bottom:16px">
            <?php foreach ( $services as $svc ) :
                $color       = $svc_colors[ $svc->service_slug ] ?? '#FF6699';
                $icon        = $svc_icons[ $svc->service_slug ] ?? '⚙️';
                $svc_metrics = array_filter( $metrics, fn($m) => $m->service_slug === $svc->service_slug );
                $pending_req = get_user_meta( $user_id, 'six_budget_req_' . $svc->id, true );
            ?>
            <div class="six-card">
                <div class="six-card-header">
                    <div style="display:flex;align-items:center;gap:10px">
                        <span style="font-size:20px"><?php echo $icon; ?></span>
                        <span class="six-card-title"><?php echo esc_html( $svc->service_name ); ?></span>
                    </div>
                    <span class="six-status-badge <?php echo $svc->status; ?>"><?php echo ucfirst( $svc->status ); ?></span>
                </div>
                <div class="six-card-body">
                    <?php if ( $svc->status === 'active' && ! empty( $svc_metrics ) ) : ?>
                        <?php foreach ( array_slice( array_values( $svc_metrics ), 0, 3 ) as $met ) :
                            $pct = 60;
                            $tar_f = floatval( preg_replace( '/[^0-9.]/', '', $met->target_value ) );
                            $cur_f = floatval( preg_replace( '/[^0-9.]/', '', $met->current_value ) );
                            if ( $tar_f > 0 ) $pct = min( 100, round( ($cur_f / $tar_f) * 100 ) );
                        ?>
                        <div class="six-metric-row">
                            <div class="six-metric-header">
                                <span class="six-metric-label"><?php echo esc_html( $met->label ); ?></span>
                                <span class="six-metric-vals"><?php echo esc_html( $met->previous_value ?: '—' ); ?> → <strong><?php echo esc_html( $met->current_value ); ?></strong></span>
                            </div>
                            <div class="six-progress-track"><div class="six-progress-fill" style="width:<?php echo $pct; ?>%;background:<?php echo $color; ?>"></div></div>
                        </div>
                        <?php endforeach; ?>
                    <?php elseif ( $svc->status === 'pending' ) : ?>
                        <div class="six-pending-msg">🔒 Awaiting advisor approval…</div>
                    <?php endif; ?>

                    <!-- Budget Section -->
                    <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border)">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
                            <span style="font-size:11px;color:var(--text3);font-weight:600;text-transform:uppercase;letter-spacing:0.5px">Monthly Budget</span>
                            <strong style="color:var(--cyan)"><?php echo $svc->budget > 0 ? '$' . number_format( $svc->budget, 0 ) . '/mo' : '<span style="color:var(--text3)">Not set</span>'; ?></strong>
                        </div>
                        <?php if ( $pending_req && ( $pending_req['status'] ?? '' ) === 'pending' ) : ?>
                            <div class="six-pending-msg" style="font-size:11px;padding:8px">⏳ Budget change of $<?php echo number_format( $pending_req['requested_budget'], 0 ); ?>/mo is pending advisor approval.</div>
                        <?php else : ?>
                            <div id="budget-form-<?php echo $svc->id; ?>" style="display:none">
                                <div style="display:flex;gap:8px;margin-top:6px">
                                    <input type="number" class="six-input six-budget-input" value="<?php echo esc_attr( intval( $svc->budget ) ); ?>" placeholder="e.g. 1500" min="0" style="flex:1;padding:6px 10px;font-size:12px">
                                    <button class="six-btn six-btn-primary six-btn-sm six-submit-budget"
                                            data-service-id="<?php echo $svc->id; ?>">Request</button>
                                    <button class="six-btn six-btn-ghost six-btn-sm" onclick="document.getElementById('budget-form-<?php echo $svc->id; ?>').style.display='none';document.getElementById('budget-trigger-<?php echo $svc->id; ?>').style.display=''">✕</button>
                                </div>
                                <div class="six-budget-msg" style="font-size:11px;margin-top:5px"></div>
                            </div>
                            <button id="budget-trigger-<?php echo $svc->id; ?>" class="six-btn six-btn-ghost six-btn-sm"
                                    onclick="document.getElementById('budget-form-<?php echo $svc->id; ?>').style.display='block';this.style.display='none'"
                                    style="font-size:11px;padding:4px 8px">
                                <?php echo $svc->budget > 0 ? '✏️ Request Change' : '+ Set Budget'; ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else : ?>
        <div class="six-card" style="margin-bottom:16px">
            <div class="six-card-body" style="text-align:center;padding:40px">
                <div style="font-size:40px;margin-bottom:12px">🚀</div>
                <div style="font-size:15px;font-weight:600;margin-bottom:8px">No services yet</div>
                <a href="?tab=services" class="six-btn six-btn-primary">Browse Services</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recommendations -->
        <?php if ( ! empty( $recs ) ) : ?>
        <div class="six-card">
            <div class="six-card-header"><span class="six-card-title">Advisor Recommendations</span></div>
            <div class="six-card-body">
                <?php foreach ( $recs as $rec ) : ?>
                <div class="six-rec" id="rec-<?php echo $rec->id; ?>">
                    <div class="six-rec-icon">💡</div>
                    <div style="flex:1">
                        <div class="six-rec-title"><?php echo esc_html( $rec->title ); ?></div>
                        <div class="six-rec-desc"><?php echo esc_html( $rec->description ); ?></div>
                        <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">
                            <?php if ( $rec->action_label ) : ?>
                            <button class="six-btn six-btn-primary six-btn-sm six-approve-rec"
                                    data-rec-id="<?php echo $rec->id; ?>">✓ <?php echo esc_html( $rec->action_label ); ?></button>
                            <?php endif; ?>
                            <button class="six-btn six-btn-ghost six-btn-sm six-dismiss-rec"
                                    data-rec-id="<?php echo $rec->id; ?>">Dismiss</button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    <?php /* ════════════════════ SERVICES ════════════════════ */ elseif ( $active_tab === 'services' ) : ?>

        <div class="six-page-header">
            <div><h1 class="six-page-title">Your Services</h1></div>
        </div>
        <div class="six-services-grid">
            <?php
            $all_svcs = array(
                array( 'slug' => 'google-ads',   'name' => 'Google Ads',               'desc' => 'Paid search & display campaigns', 'icon' => '📊' ),
                array( 'slug' => 'seo',          'name' => 'SEO',                      'desc' => 'Organic search growth',            'icon' => '🔍' ),
                array( 'slug' => 'social-media', 'name' => 'Social Media Marketing',   'desc' => 'Instagram, LinkedIn & more',       'icon' => '📱' ),
                array( 'slug' => 'brand-dev',    'name' => 'Brand Development',        'desc' => 'Logo, identity & guidelines',      'icon' => '🎨' ),
            );
            foreach ( $all_svcs as $def ) :
                $existing = null;
                foreach ( $services as $s ) { if ( $s->service_slug === $def['slug'] ) { $existing = $s; break; } }
                $color       = $svc_colors[ $def['slug'] ] ?? '#FF6699';
                $svc_metrics = $existing ? array_filter( $metrics, fn($m) => $m->service_slug === $def['slug'] ) : array();
                $pending_req = $existing ? get_user_meta( $user_id, 'six_budget_req_' . $existing->id, true ) : null;
            ?>
            <div class="six-card">
                <div class="six-card-header">
                    <div style="display:flex;align-items:center;gap:12px">
                        <div class="six-svc-icon" style="background:<?php echo $color; ?>22"><?php echo $def['icon']; ?></div>
                        <div>
                            <div class="six-card-title"><?php echo esc_html( $def['name'] ); ?></div>
                            <div style="font-size:11px;color:var(--text3)"><?php echo esc_html( $def['desc'] ); ?></div>
                        </div>
                    </div>
                    <?php if ( $existing ) : ?>
                        <span class="six-status-badge <?php echo $existing->status; ?>"><?php echo ucfirst( $existing->status ); ?></span>
                    <?php else : ?>
                        <span class="six-status-badge inactive">Not Active</span>
                    <?php endif; ?>
                </div>
                <div class="six-card-body">
                    <?php if ( $existing && $existing->status === 'active' && ! empty( $svc_metrics ) ) : ?>
                        <?php foreach ( array_slice( array_values( $svc_metrics ), 0, 4 ) as $met ) :
                            $pct = 60;
                            $tar_f = floatval( preg_replace( '/[^0-9.]/', '', $met->target_value ) );
                            $cur_f = floatval( preg_replace( '/[^0-9.]/', '', $met->current_value ) );
                            if ( $tar_f > 0 ) $pct = min( 100, round( ($cur_f / $tar_f) * 100 ) );
                        ?>
                        <div class="six-metric-row">
                            <div class="six-metric-header">
                                <span class="six-metric-label"><?php echo esc_html( $met->label ); ?></span>
                                <span class="six-metric-vals"><?php echo esc_html( $met->previous_value ?: '—' ); ?> → <strong><?php echo esc_html( $met->current_value ); ?></strong></span>
                            </div>
                            <div class="six-progress-track"><div class="six-progress-fill" style="width:<?php echo $pct; ?>%;background:<?php echo $color; ?>"></div></div>
                        </div>
                        <?php endforeach; ?>

                        <!-- Budget on service card -->
                        <div style="margin-top:14px;padding-top:12px;border-top:1px solid var(--border)">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                                <span style="font-size:11px;color:var(--text3);font-weight:600;text-transform:uppercase;letter-spacing:0.5px">Monthly Budget</span>
                                <strong style="color:var(--cyan)"><?php echo $existing->budget > 0 ? '$' . number_format( $existing->budget, 0 ) . '/mo' : 'Not set'; ?></strong>
                            </div>
                            <?php if ( $pending_req && ($pending_req['status']??'') === 'pending' ) : ?>
                                <div class="six-pending-msg" style="font-size:11px;padding:8px">⏳ Budget change of $<?php echo number_format( $pending_req['requested_budget'], 0 ); ?>/mo pending approval.</div>
                            <?php else : ?>
                                <div id="svc-budget-form-<?php echo $existing->id; ?>" style="display:none">
                                    <div style="display:flex;gap:8px;margin-top:6px">
                                        <input type="number" class="six-input six-budget-input" value="<?php echo esc_attr( intval( $existing->budget ) ); ?>" placeholder="e.g. 1500" min="0" style="flex:1;padding:6px 10px;font-size:12px">
                                        <button class="six-btn six-btn-primary six-btn-sm six-submit-budget" data-service-id="<?php echo $existing->id; ?>">Request</button>
                                        <button class="six-btn six-btn-ghost six-btn-sm" onclick="document.getElementById('svc-budget-form-<?php echo $existing->id; ?>').style.display='none';document.getElementById('svc-budget-trigger-<?php echo $existing->id; ?>').style.display=''">✕</button>
                                    </div>
                                    <div class="six-budget-msg" style="font-size:11px;margin-top:5px"></div>
                                </div>
                                <button id="svc-budget-trigger-<?php echo $existing->id; ?>" class="six-btn six-btn-ghost six-btn-sm"
                                        onclick="document.getElementById('svc-budget-form-<?php echo $existing->id; ?>').style.display='block';this.style.display='none'"
                                        style="font-size:11px;padding:4px 8px">✏️ <?php echo $existing->budget > 0 ? 'Request Change' : 'Set Budget'; ?></button>
                            <?php endif; ?>
                        </div>
                    <?php elseif ( $existing && $existing->status === 'pending' ) : ?>
                        <div class="six-pending-msg">🔒 Awaiting advisor approval.</div>
                    <?php elseif ( ! $existing ) : ?>
                        <div style="color:var(--text3);font-size:12px;margin-bottom:14px">Request this service and your advisor will review and activate it.</div>
                        <button class="six-btn six-btn-primary six-btn-sm six-request-service" data-service="<?php echo esc_attr( $def['slug'] ); ?>">Request Service</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

    <?php /* ════════════════════ MESSAGES ════════════════════ */ elseif ( $active_tab === 'messages' ) : ?>

        <div class="six-page-header"><div><h1 class="six-page-title">Messages</h1></div></div>
        <?php if ( $advisor ) :
            $conv = class_exists('Six_Messaging') ? Six_Messaging::get_conversation( $user_id, $advisor_id ) : array(); ?>
        <div class="six-card" style="max-width:720px">
            <div class="six-card-header">
                <div style="display:flex;align-items:center;gap:10px">
                    <div class="six-advisor-avatar" style="width:34px;height:34px"><?php echo esc_html( six_get_initials( $advisor->display_name ) ); ?></div>
                    <div><div style="font-weight:600;font-size:13px"><?php echo esc_html( $advisor->display_name ); ?></div><div style="font-size:11px;color:var(--success)">● Your Advisor</div></div>
                </div>
            </div>
            <div class="six-card-body">
                <div class="six-msg-thread" id="six-msg-thread">
                    <?php if ( empty( $conv ) ) : ?>
                        <div style="text-align:center;padding:30px;color:var(--text3)">No messages yet. Say hello! 👋</div>
                    <?php else : ?>
                        <?php foreach ( $conv as $msg ) : $is_mine = intval( $msg->sender_id ) === $user_id; ?>
                        <div class="six-msg <?php echo $is_mine ? 'mine' : ''; ?>">
                            <div class="six-msg-avatar" style="background:<?php echo $is_mine ? 'linear-gradient(135deg,var(--pink),#a855f7)' : 'linear-gradient(135deg,var(--blue),var(--cyan))'; ?>"><?php echo esc_html( six_get_initials( $msg->sender_name ) ); ?></div>
                            <div>
                                <div class="six-msg-bubble"><?php echo esc_html( $msg->message ); ?></div>
                                <div class="six-msg-time"><?php echo human_time_diff( strtotime( $msg->created_at ), time() ) . ' ago'; ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="six-msg-input-row">
                    <input class="six-msg-input" id="six-msg-input" placeholder="Type a message…" data-receiver="<?php echo $advisor_id; ?>">
                    <button class="six-btn six-btn-primary" id="six-msg-send">Send →</button>
                </div>
            </div>
        </div>
        <?php else : ?>
        <div class="six-card"><div class="six-card-body" style="text-align:center;padding:40px;color:var(--text3)">No advisor assigned yet.</div></div>
        <?php endif; ?>

    <?php /* ════════════════════ REPORTS ════════════════════ */ elseif ( $active_tab === 'reports' ) : ?>

        <div class="six-page-header"><div><h1 class="six-page-title">Reports</h1></div></div>
        <div class="six-card"><div class="six-card-body">
            <?php if ( empty( $reports ) ) : ?>
                <div style="text-align:center;padding:40px;color:var(--text3)"><div style="font-size:36px;margin-bottom:12px">📄</div>No reports yet. Your advisor will upload them here.</div>
            <?php else : ?>
                <?php foreach ( $reports as $rep ) : ?>
                <div class="six-report-row">
                    <div style="display:flex;align-items:center;gap:12px">
                        <div style="font-size:24px">📄</div>
                        <div>
                            <div style="font-weight:600;font-size:13px"><?php echo esc_html( $rep->title ); ?></div>
                            <div style="font-size:11px;color:var(--text3)"><?php echo date( 'M j, Y', strtotime( $rep->created_at ) ); ?><?php if ( $rep->file_size ) echo ' · ' . esc_html( $rep->file_size ); ?></div>
                        </div>
                    </div>
                    <?php if ( $rep->file_url ) : ?><a href="<?php echo esc_url( $rep->file_url ); ?>" class="six-btn six-btn-secondary six-btn-sm" target="_blank">↓ Download</a><?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div></div>

    <?php /* ════════════════════ ADVISOR ════════════════════ */ elseif ( $active_tab === 'advisor' ) : ?>

        <div class="six-page-header"><div><h1 class="six-page-title">Your Advisor</h1></div></div>
        <?php if ( $advisor ) : ?>
        <div class="six-grid-2">
            <div class="six-card"><div class="six-card-body" style="text-align:center;padding:32px">
                <div class="six-advisor-avatar-lg"><?php echo esc_html( six_get_initials( $advisor->display_name ) ); ?></div>
                <div style="font-size:18px;font-weight:700;margin:12px 0 4px"><?php echo esc_html( $advisor->display_name ); ?></div>
                <div style="color:var(--text3);font-size:13px;margin-bottom:16px">Account Manager · 6ix Developers</div>
                <a href="?tab=messages" class="six-btn six-btn-primary" style="width:100%;justify-content:center">💬 Send Message</a>
            </div></div>
            <div class="six-card">
                <div class="six-card-header"><span class="six-card-title">Book a Meeting</span></div>
                <div class="six-card-body">
                    <div class="six-form-group"><label class="six-label">Date & Time</label><input type="datetime-local" class="six-input" id="six-meeting-dt" min="<?php echo date('Y-m-d\TH:i'); ?>"></div>
                    <div class="six-form-group"><label class="six-label">Notes</label><textarea class="six-input" id="six-meeting-notes" rows="3" placeholder="What would you like to discuss?"></textarea></div>
                    <button class="six-btn six-btn-primary" id="six-book-meeting">📅 Book Meeting</button>
                    <div id="six-meeting-result" style="margin-top:12px;font-size:13px"></div>
                </div>
            </div>
        </div>
        <?php else : ?><div class="six-card"><div class="six-card-body" style="text-align:center;padding:40px;color:var(--text3)">No advisor assigned yet.</div></div><?php endif; ?>

    <?php /* ════════════════════ BILLING ════════════════════ */ elseif ( $active_tab === 'billing' ) : ?>

        <div class="six-page-header"><div><h1 class="six-page-title">Billing</h1></div></div>
        <div class="six-grid-2">
            <div class="six-card">
                <div class="six-card-header"><span class="six-card-title">Payment Method</span></div>
                <div class="six-card-body">
                    <?php
                    $has_card      = get_user_meta( $user_id, 'six_stripe_payment_method', true );
                    $customer_id   = get_user_meta( $user_id, 'six_stripe_customer_id', true );
                    $card_details  = null;
                    $last4 = '••••'; $brand = 'Card'; $exp = '';
                    if ( $has_card && $customer_id && class_exists('Six_Stripe') ) {
                        // Fetch card details from Stripe
                        $pm_resp = Six_Stripe::get_payment_method_details( $has_card );
                        if ( $pm_resp && ! empty($pm_resp['card']) ) {
                            $last4 = $pm_resp['card']['last4'] ?? '••••';
                            $brand = ucfirst( $pm_resp['card']['brand'] ?? 'Card' );
                            $exp   = sprintf('%02d/%s', $pm_resp['card']['exp_month']??0, substr($pm_resp['card']['exp_year']??'',2));
                        }
                    }
                    ?>
                    <?php if ( $has_card ) : ?>
                    <div class="six-credit-card">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px">
                            <div class="six-card-chip"></div>
                            <div style="font-size:13px;font-weight:700;color:rgba(255,255,255,0.8)"><?php echo esc_html($brand); ?></div>
                        </div>
                        <div class="six-card-number">•••• •••• •••• <?php echo esc_html($last4); ?></div>
                        <div class="six-card-meta">
                            <div><div class="six-card-meta-label">Card Holder</div><div class="six-card-meta-val"><?php echo esc_html($user->display_name); ?></div></div>
                            <?php if($exp): ?><div><div class="six-card-meta-label">Expires</div><div class="six-card-meta-val"><?php echo esc_html($exp); ?></div></div><?php endif; ?>
                        </div>
                    </div>
                    <button class="six-btn six-btn-ghost six-btn-sm" style="width:100%;justify-content:center;margin-top:8px" id="six-update-card">Update Card</button>
                    <?php else : ?>
                    <div style="background:var(--dark3);border:1px dashed var(--border);border-radius:10px;padding:20px;text-align:center;margin-bottom:16px">
                        <div style="font-size:13px;color:var(--text2);margin-bottom:12px">No payment method saved yet.</div>
                        <button class="six-btn six-btn-primary" id="six-add-card">+ Add Card</button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="six-card">
                <div class="six-card-header"><span class="six-card-title">Active Services Summary</span></div>
                <div class="six-card-body">
                    <?php
                    $total       = 0;
                    $active_svcs = array_filter( $services, fn($s) => $s->status === 'active' );
                    if ( empty( $active_svcs ) ) : ?>
                        <div style="color:var(--text3);font-size:13px">No active services yet.</div>
                    <?php else : ?>
                    <table style="width:100%;font-size:12px;border-collapse:collapse">
                        <thead><tr>
                            <th style="text-align:left;padding:6px 0;color:var(--text3);font-size:10px;text-transform:uppercase;letter-spacing:0.5px;border-bottom:1px solid var(--border)">Service</th>
                            <th style="text-align:right;padding:6px 0;color:var(--text3);font-size:10px;text-transform:uppercase;letter-spacing:0.5px;border-bottom:1px solid var(--border)">Approved Budget</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ( $active_svcs as $s ) : ?>
                        <tr style="border-bottom:1px solid rgba(255,255,255,0.04)">
                            <td style="padding:10px 0;color:var(--text2)">
                                <?php echo esc_html( $s->service_name ); ?>
                                <?php
                                $pr = get_user_meta( $user_id, 'six_budget_req_' . $s->id, true );
                                if ( $pr && ($pr['status']??'') === 'pending' ) echo '<span style="font-size:10px;color:var(--warning);margin-left:6px">⏳ change pending</span>';
                                ?>
                            </td>
                            <td style="padding:10px 0;text-align:right;font-weight:700">
                                <?php if ( $s->budget > 0 ) { $total += $s->budget; echo '$' . number_format( $s->budget, 0 ) . '/mo'; } else { echo '<span style="color:var(--text3)">Not set</span>'; } ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if ( $total > 0 ) : ?>
                    <div style="display:flex;justify-content:space-between;padding:12px 0 0;font-size:14px;font-weight:700;border-top:2px solid var(--border);margin-top:4px">
                        <span>Total Monthly Spend</span>
                        <span style="color:var(--pink)">$<?php echo number_format( $total, 0 ); ?>/mo</span>
                    </div>
                    <?php endif; endif; ?>
                </div>
            </div>
        </div>

        <!-- Billing History from Stripe -->
        <?php if($customer_id && class_exists('Six_Stripe')):
            $invoices = Six_Stripe::get_invoices($user_id);
        ?>
        <?php if(!empty($invoices)): ?>
        <div class="six-card" style="margin-top:16px">
            <div class="six-card-header"><span class="six-card-title">Billing History</span></div>
            <div class="six-card-body" style="padding:0">
                <table class="six-table">
                    <thead><tr><th>Date</th><th>Description</th><th>Amount</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach(array_slice($invoices,0,10) as $inv):
                        $amt    = '$'.number_format(($inv['amount_paid']??0)/100,2);
                        $date   = date('M j, Y',$inv['created']??time());
                        $status = $inv['status']??'draft';
                        $sc     = $status==='paid'?'var(--success)':($status==='open'?'var(--warning)':'var(--text3)');
                    ?>
                    <tr>
                        <td style="font-size:12px;color:var(--text3)"><?php echo esc_html($date);?></td>
                        <td style="font-size:12px"><?php echo esc_html($inv['description']??($inv['number']??'Invoice'));?></td>
                        <td style="font-weight:700"><?php echo esc_html($amt);?></td>
                        <td><span style="font-size:11px;font-weight:600;color:<?php echo $sc;?>;text-transform:capitalize"><?php echo esc_html($status);?></span></td>
                        <td><?php if(!empty($inv['hosted_invoice_url'])):?><a href="<?php echo esc_url($inv['hosted_invoice_url']);?>" target="_blank" class="six-btn six-btn-ghost six-btn-sm" style="font-size:11px">View</a><?php endif;?></td>
                    </tr>
                    <?php endforeach;?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        <!-- Upcoming payments -->
        <?php
        $upcoming_total = 0;
        $active_svcs_billing = array_filter($services, fn($s) => $s->status==='active' && $s->budget > 0);
        foreach($active_svcs_billing as $s) $upcoming_total += $s->budget;
        if($upcoming_total > 0):
        $next_billing = date('M 1, Y', strtotime('first day of next month'));
        ?>
        <div class="six-card" style="margin-top:16px;border-color:rgba(131,197,237,0.25)">
            <div class="six-card-body" style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
                <div>
                    <div style="font-size:11px;color:var(--text3);font-weight:600;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px">Next Billing Date</div>
                    <div style="font-size:16px;font-weight:700;color:var(--cyan)"><?php echo esc_html($next_billing);?></div>
                </div>
                <div>
                    <div style="font-size:11px;color:var(--text3);font-weight:600;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px">Amount Due</div>
                    <div style="font-size:22px;font-weight:800;font-family:'Syne',sans-serif;color:var(--pink)">$<?php echo number_format($upcoming_total,0);?></div>
                </div>
                <div style="font-size:12px;color:var(--text3)">Based on <?php echo count($active_svcs_billing);?> active service<?php echo count($active_svcs_billing)>1?'s':'';?></div>
            </div>
        </div>
        <?php endif; endif; ?>

    <?php /* ════════════════════ PROFILE ════════════════════ */ elseif ( $active_tab === 'profile' ) : ?>

        <div class="six-page-header">
            <div><h1 class="six-page-title">Profile</h1><p class="six-page-sub">Your account and business information</p></div>
        </div>
        <div id="profile-saved-msg" style="display:none;background:rgba(86,211,100,0.1);border:1px solid rgba(86,211,100,0.3);color:var(--success);padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">✓ Profile saved successfully.</div>
        <div class="six-grid-2">
            <div class="six-card">
                <div class="six-card-header"><span class="six-card-title">Personal Information</span></div>
                <div class="six-card-body">
                    <div class="six-form-group"><label class="six-label">First Name</label><input class="six-input" id="prof-first" value="<?php echo esc_attr( $user->first_name ); ?>" placeholder="First name"></div>
                    <div class="six-form-group"><label class="six-label">Last Name</label><input class="six-input" id="prof-last" value="<?php echo esc_attr( $user->last_name ); ?>" placeholder="Last name"></div>
                    <div class="six-form-group"><label class="six-label">Email</label><input class="six-input" value="<?php echo esc_attr( $user->user_email ); ?>" readonly style="opacity:0.5;cursor:not-allowed"></div>
                    <div class="six-form-group"><label class="six-label">Phone</label><input class="six-input" id="prof-phone" value="<?php echo esc_attr( get_user_meta( $user_id, 'billing_phone', true ) ); ?>" placeholder="+1 (416) 555-0100"></div>
                </div>
            </div>
            <div class="six-card">
                <div class="six-card-header"><span class="six-card-title">Business Information</span></div>
                <div class="six-card-body">
                    <div class="six-form-group"><label class="six-label">Company Name</label><input class="six-input" id="prof-biz" value="<?php echo esc_attr( $checkout->business_name ?? '' ); ?>" placeholder="Your company"></div>
                    <div class="six-form-group"><label class="six-label">Industry</label><input class="six-input" id="prof-industry" value="<?php echo esc_attr( $checkout->industry ?? '' ); ?>" placeholder="e.g. SaaS, Retail, Healthcare"></div>
                    <div class="six-form-group"><label class="six-label">Monthly Revenue</label><input class="six-input" id="prof-revenue" value="<?php echo esc_attr( $checkout->monthly_revenue ?? '' ); ?>" placeholder="e.g. $50,000–$100,000"></div>
                </div>
            </div>
        </div>
        <div style="display:flex;justify-content:flex-end;margin-top:4px">
            <button class="six-btn six-btn-primary" id="save-profile" style="padding:10px 28px;font-size:13px">💾 Save Profile</button>
        </div>

    <?php endif; ?>

    </main>
</div>

<script>
(function(){
'use strict';
const AJAX  = '<?php echo esc_js( $ajax_url ); ?>';
const NONCE = '<?php echo esc_js( $nonce ); ?>';
const INI   = '<?php echo esc_js( $initials ); ?>';

// ── Budget: submit request ──────────────────────────────────────────────────
document.querySelectorAll('.six-submit-budget').forEach(function(btn){
    btn.addEventListener('click', function(){
        const svcId = this.dataset.serviceId;
        const form  = this.closest('div');
        const input = form.querySelector('.six-budget-input');
        const msgEl = form.querySelector('.six-budget-msg');
        const budget = parseFloat(input.value);
        if (!budget || budget <= 0) { if(msgEl) msgEl.innerHTML='<span style="color:var(--danger)">Enter a valid amount.</span>'; return; }
        this.textContent = 'Sending…'; this.disabled = true;
        fetch(AJAX, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body: new URLSearchParams({action:'six_request_budget_change', nonce:NONCE, service_id:svcId, new_budget:budget})
        }).then(r=>r.json()).then(res=>{
            if (res.success) {
                const section = form.closest('[style*="border-top"]') || form.parentNode;
                form.style.display = 'none';
                // Hide trigger button
                const trig = document.getElementById('budget-trigger-'+svcId) || document.getElementById('svc-budget-trigger-'+svcId);
                if (trig) trig.style.display = 'none';
                const pend = document.createElement('div');
                pend.className = 'six-pending-msg';
                pend.style.cssText = 'font-size:11px;padding:8px;margin-top:6px';
                pend.textContent = '⏳ Budget change of $'+budget.toLocaleString()+'/mo sent to your advisor for approval.';
                form.parentNode.insertBefore(pend, form);
            } else {
                if(msgEl) msgEl.innerHTML='<span style="color:var(--danger)">'+(res.data||'Error')+'</span>';
                this.textContent = 'Request'; this.disabled = false;
            }
        });
    });
});

// ── Request Service ─────────────────────────────────────────────────────────
document.querySelectorAll('.six-request-service').forEach(function(btn){
    btn.addEventListener('click', function(){
        const service = this.dataset.service;
        this.textContent = 'Requesting…'; this.disabled = true;
        fetch(AJAX, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body: new URLSearchParams({action:'six_request_service', nonce:NONCE, service})
        }).then(r=>r.json()).then(res=>{
            if (res.success) { this.textContent = '✓ Requested'; this.style.background = 'var(--success)'; }
            else { this.textContent = 'Request Service'; this.disabled = false; }
        });
    });
});

// ── Approve recommendation ──────────────────────────────────────────────────
document.querySelectorAll('.six-approve-rec').forEach(function(btn){
    btn.addEventListener('click', function(){
        const recId = this.dataset.recId;
        this.disabled = true;
        fetch(AJAX, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body: new URLSearchParams({action:'six_approve_recommendation', nonce:NONCE, rec_id:recId})
        }).then(r=>r.json()).then(res=>{
            if (res.success) {
                const row = document.getElementById('rec-'+recId);
                if (row) { row.style.opacity='0.4'; row.querySelector('.six-rec-title').textContent += ' ✓ Approved'; }
            }
        });
    });
});

// ── Dismiss recommendation ──────────────────────────────────────────────────
document.querySelectorAll('.six-dismiss-rec').forEach(function(btn){
    btn.addEventListener('click', function(){
        const recId = this.dataset.recId;
        this.disabled = true;
        fetch(AJAX, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body: new URLSearchParams({action:'six_dismiss_recommendation', nonce:NONCE, rec_id:recId})
        }).then(r=>r.json()).then(res=>{
            if (res.success) {
                const row = document.getElementById('rec-'+recId);
                if (row) { row.style.transition='opacity 0.3s'; row.style.opacity='0'; setTimeout(()=>row.remove(), 320); }
            } else {
                this.disabled = false;
            }
        });
    });
});

// ── Send message ────────────────────────────────────────────────────────────
var msgInput = document.getElementById('six-msg-input');
var msgSend  = document.getElementById('six-msg-send');
if (msgSend && msgInput) {
    var sendMsg = function(){
        var msg      = msgInput.value.trim();
        var receiver = msgInput.dataset.receiver;
        if (!msg || !receiver) return;
        fetch(AJAX, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body: new URLSearchParams({action:'six_send_message', nonce:NONCE, receiver_id:receiver, message:msg})
        }).then(r=>r.json()).then(res=>{
            if (res.success) {
                var thread = document.getElementById('six-msg-thread');
                var div = document.createElement('div');
                div.className = 'six-msg mine';
                div.innerHTML = '<div class="six-msg-avatar" style="background:linear-gradient(135deg,var(--pink),#a855f7)">'+INI+'</div><div><div class="six-msg-bubble">'+msg.replace(/</g,'&lt;')+'</div><div class="six-msg-time">just now</div></div>';
                thread.appendChild(div); thread.scrollTop = thread.scrollHeight; msgInput.value = '';
            }
        });
    };
    msgSend.addEventListener('click', sendMsg);
    msgInput.addEventListener('keydown', function(e){ if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();sendMsg();} });
}
var t = document.getElementById('six-msg-thread');
if (t) t.scrollTop = t.scrollHeight;

// ── Save profile ────────────────────────────────────────────────────────────
var saveBtn = document.getElementById('save-profile');
if (saveBtn) {
    saveBtn.addEventListener('click', function(){
        this.textContent = 'Saving…'; this.disabled = true;
        fetch(AJAX, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                action: 'six_save_profile', nonce: NONCE,
                first_name:      (document.getElementById('prof-first')    || {}).value || '',
                last_name:       (document.getElementById('prof-last')     || {}).value || '',
                phone:           (document.getElementById('prof-phone')    || {}).value || '',
                business_name:   (document.getElementById('prof-biz')      || {}).value || '',
                industry:        (document.getElementById('prof-industry')  || {}).value || '',
                monthly_revenue: (document.getElementById('prof-revenue')   || {}).value || '',
            })
        }).then(r=>r.json()).then(res=>{
            this.textContent = '💾 Save Profile'; this.disabled = false;
            var msg = document.getElementById('profile-saved-msg');
            if (msg) { msg.style.display = res.success ? 'block' : 'none'; if(res.success) setTimeout(()=>msg.style.display='none', 3000); }
        });
    });
}

// ── Book meeting ────────────────────────────────────────────────────────────
var bookBtn = document.getElementById('six-book-meeting');
if (bookBtn) {
    bookBtn.addEventListener('click', function(){
        var dt    = (document.getElementById('six-meeting-dt')    || {}).value;
        var notes = (document.getElementById('six-meeting-notes') || {}).value;
        var res   = document.getElementById('six-meeting-result');
        if (!dt) { if(res) res.innerHTML='<span style="color:var(--danger)">Please select a date and time.</span>'; return; }
        if(res) res.innerHTML = 'Booking…';
        fetch(AJAX, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body: new URLSearchParams({action:'six_book_meeting', nonce:NONCE, start:new Date(dt).toISOString(), notes})
        }).then(r=>r.json()).then(function(data){
            if(res) res.innerHTML = data.success
                ? '<span style="color:var(--success)">✓ Meeting booked!'+(data.data.meet_link?'  <a href="'+data.data.meet_link+'" target="_blank" style="color:var(--cyan)">Join link</a>':'')+'</span>'
                : '<span style="color:var(--danger)">Could not book — try again.</span>';
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