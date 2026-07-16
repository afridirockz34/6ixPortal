<?php
/**
 * Sales Dashboard v2
 * Upload to: /wp-content/themes/6ixClaude/portal/templates/sales-dashboard.php
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! Six_Roles::is_sales() && ! Six_Roles::is_advisor() ) { wp_redirect( home_url('/portal/') ); exit; }

$user_id    = get_current_user_id();
$user       = wp_get_current_user();
$initials   = six_get_initials( $user->display_name );
$active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'pipeline';
$nonce      = wp_create_nonce('six_nonce');

global $wpdb;

// Abandoned leads — direct SQL for reliability.
// Finds any user who has six_abandoned_at_step set AND either:
//   (a) six_checkout_completed is not set (brand new user who bailed out), or
//   (b) six_checkout_completed = 0
$abandoned_ids = $wpdb->get_col(
    "SELECT DISTINCT u.ID
     FROM {$wpdb->users} u
     INNER JOIN {$wpdb->usermeta} m_abandoned
         ON m_abandoned.user_id = u.ID
         AND m_abandoned.meta_key = 'six_abandoned_at_step'
     LEFT JOIN {$wpdb->usermeta} m_completed
         ON m_completed.user_id = u.ID
         AND m_completed.meta_key = 'six_checkout_completed'
     WHERE m_completed.meta_value IS NULL
        OR m_completed.meta_value = '0'
     ORDER BY m_abandoned.meta_value DESC"
);
$abandoned = $abandoned_ids ? get_users( array( 'include' => $abandoned_ids, 'orderby' => 'ID' ) ) : array();

$hot_leads  = array();
$warm_leads = array();
$cold_leads = array();

foreach ( $abandoned as $u ) {
    $score = intval( get_user_meta($u->ID,'six_abandoned_score',true) );
    $step  = intval( get_user_meta($u->ID,'six_abandoned_at_step',true) );
    $time  = get_user_meta($u->ID,'six_abandoned_at',true);
    $checkout = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d",$u->ID));
    $lead = array(
        'id'           => $u->ID,
        'name'         => $u->display_name ?: $u->user_email,
        'email'        => $u->user_email,
        'phone'        => get_user_meta($u->ID,'billing_phone',true),
        'score'        => $score,
        'step'         => $step,
        'biz'          => $checkout->business_name ?? '',
        'industry'     => $checkout->industry ?? '',
        'goal'         => $checkout->goal ?? '',
        'budget'       => $checkout->mktg_budget ?? '',
        'abandoned_at' => $time,
    );
    if ($score >= 70)      $hot_leads[]  = $lead;
    elseif ($score >= 40)  $warm_leads[] = $lead;
    else                   $cold_leads[] = $lead;
}

// Completed onboardings (recent)
$completed = get_users( array( 'meta_key' => 'six_checkout_completed', 'meta_value' => '1', 'number' => 20 ) );

$total_pipeline = count($abandoned);
$total_hot      = count($hot_leads);
?>
<div class="six-topbar">
    <div class="six-logo">6ix Developers</div>
    <div class="six-role-badge" style="background:rgba(227,179,65,0.15);color:var(--warning);border-color:rgba(227,179,65,0.3)">Sales</div>
    <button class="six-mobile-menu-btn" id="six-menu-toggle" aria-label="Menu"></button>
    <div class="six-topbar-right">
        <span class="six-user-name"><?php echo esc_html($user->display_name);?></span>
        <div class="six-avatar"><?php echo esc_html($initials);?></div>
    </div>
</div>

<div class="six-layout">
    <nav class="six-sidebar">
        <?php
        $sp = home_url('/sales-portal/');
        $stabs = array('pipeline'=>' Lead Pipeline','abandoned'=>'◈ Abandoned Checkouts','call-queue'=>'◎ Call Queue','converted'=>'◻ Converted Clients');
        ?>
        <div class="six-nav-section">
            <div class="six-nav-label">Sales Pipeline</div>
            <?php foreach($stabs as $stab=>$slabel):?>
            <a href="<?php echo esc_url($sp.'?tab='.$stab); ?>" class="six-nav-item <?php echo $active_tab===$stab?'active':'';?>">
                <span class="six-nav-icon"><?php echo explode(' ',$slabel)[0];?></span>
                <?php echo substr($slabel,2);?>
                <?php if($stab==='abandoned'&&$total_pipeline>0):?><span class="six-badge"><?php echo $total_pipeline;?></span><?php endif;?>
                <?php if($stab==='call-queue'&&$total_hot>0):?><span class="six-badge" style="background:var(--danger)"><?php echo $total_hot;?></span><?php endif;?>
            </a>
            <?php endforeach;?>
        </div>
        <div class="six-nav-section" style="margin-top:auto;padding-top:16px;border-top:1px solid var(--border)">
            <a href="<?php echo esc_url(wp_logout_url(home_url('/get-started/'))); ?>" class="six-nav-item" style="color:var(--text3)"><span class="six-nav-icon">↩</span> Log Out</a>
        </div>
    </nav>

    <main class="six-main">

    <?php if($active_tab==='pipeline'): ?>
        <div class="six-page-header">
            <div><h1 class="six-page-title">Lead Pipeline</h1><p class="six-page-sub"><?php echo $total_pipeline;?> active leads</p></div>
        </div>
        <div class="six-stats-grid" style="margin-bottom:24px">
            <div class="six-stat-card" style="border-color:rgba(255,107,107,0.3)">
                <div class="six-stat-label" style="color:var(--danger)"> Hot Leads</div>
                <div class="six-stat-val" style="color:var(--danger)"><?php echo count($hot_leads);?></div>
                <div style="font-size:10px;color:var(--text3);margin-top:4px">Score 70–100</div>
            </div>
            <div class="six-stat-card" style="border-color:rgba(227,179,65,0.3)">
                <div class="six-stat-label" style="color:var(--warning)"> Warm Leads</div>
                <div class="six-stat-val" style="color:var(--warning)"><?php echo count($warm_leads);?></div>
                <div style="font-size:10px;color:var(--text3);margin-top:4px">Score 40–69</div>
            </div>
            <div class="six-stat-card" style="border-color:rgba(131,197,237,0.3)">
                <div class="six-stat-label" style="color:var(--cyan)"> Cold Leads</div>
                <div class="six-stat-val" style="color:var(--cyan)"><?php echo count($cold_leads);?></div>
                <div style="font-size:10px;color:var(--text3);margin-top:4px">Score under 40</div>
            </div>
            <div class="six-stat-card pink">
                <div class="six-stat-label"> Converted</div>
                <div class="six-stat-val"><?php echo count($completed);?></div>
                <div style="font-size:10px;color:var(--text3);margin-top:4px">Completed onboarding</div>
            </div>
        </div>

        <?php foreach(array(array('label'=>' Hot Leads','color'=>'var(--danger)','leads'=>$hot_leads,'border'=>'rgba(255,107,107,0.3)'),array('label'=>' Warm Leads','color'=>'var(--warning)','leads'=>$warm_leads,'border'=>'rgba(227,179,65,0.3)'),array('label'=>' Cold Leads','color'=>'var(--cyan)','leads'=>$cold_leads,'border'=>'rgba(131,197,237,0.3)')) as $group):
            if(empty($group['leads'])) continue;?>
        <div class="six-card" style="margin-bottom:16px;border-color:<?php echo $group['border'];?>">
            <div class="six-card-header"><span class="six-card-title" style="color:<?php echo $group['color'];?>"><?php echo $group['label'];?></span></div>
            <div class="six-card-body" style="padding:0">
            <table class="six-table">
                <thead><tr><th>Lead</th><th>Business</th><th>Score</th><th>Abandoned At</th><th>Goal</th><th>Budget</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach($group['leads'] as $lead):?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <div class="six-client-initials"><?php echo esc_html(six_get_initials($lead['name']));?></div>
                            <div>
                                <strong><?php echo esc_html($lead['name']);?></strong>
                                <div style="font-size:11px;color:var(--text3)"><?php echo esc_html($lead['email']);?></div>
                            </div>
                        </div>
                    </td>
                    <td><?php echo esc_html($lead['biz']?:($lead['industry']?:'—'));?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px">
                            <div class="six-progress-track" style="width:60px"><div class="six-progress-fill" style="width:<?php echo $lead['score'];?>%;background:<?php echo $group['color'];?>"></div></div>
                            <span style="font-size:12px;font-weight:700;color:<?php echo $group['color'];?>"><?php echo $lead['score'];?></span>
                        </div>
                    </td>
                    <td style="font-size:11px;color:var(--text3)">Step <?php echo $lead['step'];?><?php echo $lead['abandoned_at']?' · '.human_time_diff(strtotime($lead['abandoned_at']),time()).' ago':'';?></td>
                    <td style="font-size:12px;text-transform:capitalize"><?php echo esc_html(str_replace('-',' ',$lead['goal'])?:'—');?></td>
                    <td style="font-size:12px"><?php echo esc_html($lead['budget']?:'—');?></td>
                    <td>
                        <div style="display:flex;gap:6px;flex-wrap:wrap">
                            <?php if($lead['phone']):?>
                            <a href="tel:<?php echo esc_attr($lead['phone']);?>" class="six-btn six-btn-primary six-btn-sm"> Call</a>
                            <?php endif;?>
                            <a href="mailto:<?php echo esc_attr($lead['email']);?>" class="six-btn six-btn-secondary six-btn-sm"> Email</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach;?>
                </tbody>
            </table>
            </div>
        </div>
        <?php endforeach;?>
        <?php if($total_pipeline===0):?>
        <div class="six-card"><div class="six-card-body" style="text-align:center;padding:40px;color:var(--text3)">
            <div style="font-size:30px;margin-bottom:12px"></div>
            No abandoned leads right now. The pipeline will populate as prospects begin onboarding.
        </div></div>
        <?php endif;?>

    <?php elseif($active_tab==='abandoned'): ?>
        <div class="six-page-header"><div><h1 class="six-page-title">Abandoned Checkouts</h1><p class="six-page-sub">Sorted by readiness score</p></div></div>
        <div class="six-card"><div class="six-card-body" style="padding:0">
        <?php $all_leads = array_merge($hot_leads,$warm_leads,$cold_leads);?>
        <?php if(empty($all_leads)):?>
        <div style="padding:40px;text-align:center;color:var(--text3)">No abandoned checkouts.</div>
        <?php else:?>
        <table class="six-table">
            <thead><tr><th>Lead</th><th>Score</th><th>Step Reached</th><th>Industry</th><th>Budget</th><th>Last Seen</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach($all_leads as $lead):
                $type_color=$lead['score']>=70?'var(--danger)':($lead['score']>=40?'var(--warning)':'var(--cyan)');
                $type_label=$lead['score']>=70?'Hot':($lead['score']>=40?'Warm':'Cold');
            ?>
            <tr>
                <td>
                    <div>
                        <strong><?php echo esc_html($lead['name']);?></strong>
                        <span style="font-size:10px;padding:2px 7px;border-radius:20px;background:rgba(255,255,255,0.05);color:<?php echo $type_color;?>;margin-left:6px;font-weight:700"><?php echo $type_label;?></span>
                        <div style="font-size:11px;color:var(--text3)"><?php echo esc_html($lead['email']);?></div>
                    </div>
                </td>
                <td><span style="font-size:15px;font-weight:800;color:<?php echo $type_color;?>"><?php echo $lead['score'];?></span><span style="font-size:10px;color:var(--text3)">/100</span></td>
                <td><span class="six-tag">Step <?php echo $lead['step'];?></span></td>
                <td style="font-size:12px;color:var(--text2)"><?php echo esc_html($lead['industry']?:'—');?></td>
                <td style="font-size:12px"><?php echo esc_html($lead['budget']?:'—');?></td>
                <td style="font-size:11px;color:var(--text3)"><?php echo $lead['abandoned_at']?human_time_diff(strtotime($lead['abandoned_at']),time()).' ago':'—';?></td>
                <td>
                    <div style="display:flex;gap:6px;flex-wrap:wrap">
                        <?php if($lead['phone']):?><a href="tel:<?php echo esc_attr($lead['phone']);?>" class="six-btn six-btn-primary six-btn-sm" title="Call <?php echo esc_attr($lead['name']);?>"> Call</a><?php endif;?>
                        <a href="mailto:<?php echo esc_attr($lead['email']);?>" class="six-btn six-btn-secondary six-btn-sm"> Email</a>
                    </div>
                </td>
            </tr>
            <?php endforeach;?>
            </tbody>
        </table>
        <?php endif;?>
        </div></div>

    <?php elseif($active_tab==='call-queue'): ?>
        <div class="six-page-header">
            <div><h1 class="six-page-title">Call Queue</h1><p class="six-page-sub">Hot leads prioritised for outreach</p></div>
        </div>
        <?php if(empty($hot_leads)):?>
        <div class="six-card"><div class="six-card-body" style="text-align:center;padding:40px;color:var(--text3)">No hot leads right now. Hot leads are those with a score of 70+.</div></div>
        <?php else:?>
        <?php foreach($hot_leads as $i=>$lead):?>
        <div class="six-card" style="margin-bottom:12px">
            <div class="six-card-body" style="display:flex;align-items:center;gap:20px;flex-wrap:wrap">
                <div style="font-family:'Montserrat',sans-serif;font-size:20px;font-weight:800;color:var(--danger);min-width:28px;text-align:center"><?php echo $i+1;?></div>
                <div class="six-client-initials" style="width:44px;height:44px;font-size:14px"><?php echo esc_html(six_get_initials($lead['name']));?></div>
                <div style="flex:1;min-width:200px">
                    <div style="font-weight:700;font-size:14px"><?php echo esc_html($lead['name']);?></div>
                    <div style="font-size:12px;color:var(--text3)"><?php echo esc_html($lead['email']);?><?php if($lead['biz']) echo ' · '.esc_html($lead['biz']);?></div>
                    <div style="margin-top:6px;display:flex;gap:8px;flex-wrap:wrap">
                        <?php if($lead['goal']):?><span class="six-tag">Goal: <?php echo esc_html(str_replace('-',' ',$lead['goal']));?></span><?php endif;?>
                        <?php if($lead['industry']):?><span class="six-tag"><?php echo esc_html($lead['industry']);?></span><?php endif;?>
                        <?php if($lead['budget']):?><span class="six-tag">Budget: <?php echo esc_html($lead['budget']);?></span><?php endif;?>
                    </div>
                </div>
                <div style="text-align:right">
                    <div style="font-size:28px;font-weight:800;color:var(--danger);font-family:'Montserrat',sans-serif"><?php echo $lead['score'];?></div>
                    <div style="font-size:10px;color:var(--text3)">Readiness Score</div>
                </div>
                <div style="display:flex;gap:8px">
                    <?php if($lead['phone']):?><a href="tel:<?php echo esc_attr($lead['phone']);?>" class="six-btn six-btn-primary" style="font-size:13px"> Call Now</a><?php endif;?>
                    <a href="mailto:<?php echo esc_attr($lead['email']);?>" class="six-btn six-btn-secondary" style="font-size:13px"> Email</a>
                </div>
            </div>
        </div>
        <?php endforeach;?>
        <?php endif;?>

    <?php elseif($active_tab==='converted'): ?>
        <div class="six-page-header"><div><h1 class="six-page-title">Converted Clients</h1><p class="six-page-sub"><?php echo count($completed);?> total</p></div></div>
        <div class="six-card"><div class="six-card-body" style="padding:0">
        <table class="six-table">
            <thead><tr><th>Client</th><th>Business</th><th>Score</th><th>Services</th><th>Onboarded</th></tr></thead>
            <tbody>
            <?php foreach($completed as $u):
                $checkout=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d",$u->ID));
                $svc_count=intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}six_client_services WHERE client_id=%d",$u->ID)));
                $score=intval(get_user_meta($u->ID,'six_checkout_score',true));
            ?>
            <tr>
                <td><div style="display:flex;align-items:center;gap:10px"><div class="six-client-initials"><?php echo esc_html(six_get_initials($u->display_name));?></div><div><strong><?php echo esc_html($u->display_name);?></strong><div style="font-size:11px;color:var(--text3)"><?php echo esc_html($u->user_email);?></div></div></div></td>
                <td><?php echo esc_html($checkout->business_name??'—');?></td>
                <td><span style="font-weight:700;color:var(--success)"><?php echo $score;?>/100</span></td>
                <td><?php echo $svc_count;?> service<?php echo $svc_count===1?'':'s';?></td>
                <td style="font-size:11px;color:var(--text3)"><?php echo $checkout&&$checkout->updated_at?date('M j, Y',strtotime($checkout->updated_at)):'—';?></td>
            </tr>
            <?php endforeach;?>
            </tbody>
        </table>
        <?php if(empty($completed)):?><div style="padding:30px;text-align:center;color:var(--text3)">No converted clients yet.</div><?php endif;?>
        </div></div>

    <?php endif; ?>
    </main>
</div>

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