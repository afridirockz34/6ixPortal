<?php
/**
 * Advisor dashboard — "Priority Leads" tab.
 * Included from advisor-dashboard.php's tab chain when ?tab=priority-leads.
 *
 * Renders Six_Growth_Engine::get_priority_leads() — every onboarding lead
 * that hasn't completed checkout, ranked Hot/Warm/Cold by intent score, with
 * the same AI recommendation and drop-risk signal the automation itself
 * uses. Every row links straight into that lead's client profile
 * (?tab=clients&client=ID) so "who do I call next" and "here's everything
 * about them" are one click apart.
 *
 * This is the onboarding track only — website-form/Meta leads live in a
 * separate table with their own queue (the Form Submissions tab).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$pl_advisor_id = current_user_can( 'manage_options' ) ? 0 : get_current_user_id();
$pl_all_leads  = class_exists( 'Six_Growth_Engine' ) ? Six_Growth_Engine::get_priority_leads( $pl_advisor_id, 100 ) : array();

$pl_counts = array( 'hot' => 0, 'warm' => 0, 'cold' => 0 );
foreach ( $pl_all_leads as $l ) {
	if ( isset( $pl_counts[ $l['priority'] ] ) ) $pl_counts[ $l['priority'] ]++;
}

$pl_filter = isset( $_GET['priority'] ) ? sanitize_key( $_GET['priority'] ) : '';
$pl_leads  = ( $pl_filter && in_array( $pl_filter, array( 'hot', 'warm', 'cold' ), true ) )
	? array_values( array_filter( $pl_all_leads, fn( $l ) => $l['priority'] === $pl_filter ) )
	: $pl_all_leads;

function six_pl_priority_badge( $priority ) {
	$map = array(
		'hot'  => array( '#d93b3b', 'Hot' ),
		'warm' => array( '#c17b1a', 'Warm' ),
		'cold' => array( '#5b7480', 'Cold' ),
	);
	[$c, $label] = $map[ $priority ] ?? array( '#888', ucfirst( $priority ) );
	return '<span style="display:inline-block;padding:2px 10px;border-radius:100px;font-size:11.5px;font-weight:700;color:#fff;background:' . esc_attr( $c ) . '">' . esc_html( $label ) . '</span>';
}
?>

<div class="six-page-header">
	<div>
		<h1 class="six-page-title">Priority Leads</h1>
		<p class="six-page-sub">Every onboarding lead who hasn't finished checkout, ranked by intent score — the same score and AI read the automation itself acts on.</p>
	</div>
</div>

<div class="six-card" style="padding:16px 20px;margin-bottom:16px;display:flex;flex-wrap:wrap;gap:10px;align-items:center">
	<a href="?tab=priority-leads" class="six-btn <?php echo $pl_filter === '' ? 'six-btn-primary' : 'six-btn-secondary'; ?> six-btn-sm">All (<?php echo count( $pl_all_leads ); ?>)</a>
	<a href="?tab=priority-leads&priority=hot" class="six-btn <?php echo $pl_filter === 'hot' ? 'six-btn-primary' : 'six-btn-secondary'; ?> six-btn-sm" style="<?php echo $pl_filter==='hot'?'background:#d93b3b;border-color:#d93b3b':''; ?>">🔥 Hot (<?php echo $pl_counts['hot']; ?>)</a>
	<a href="?tab=priority-leads&priority=warm" class="six-btn <?php echo $pl_filter === 'warm' ? 'six-btn-primary' : 'six-btn-secondary'; ?> six-btn-sm" style="<?php echo $pl_filter==='warm'?'background:#c17b1a;border-color:#c17b1a':''; ?>">Warm (<?php echo $pl_counts['warm']; ?>)</a>
	<a href="?tab=priority-leads&priority=cold" class="six-btn <?php echo $pl_filter === 'cold' ? 'six-btn-primary' : 'six-btn-secondary'; ?> six-btn-sm">Cold (<?php echo $pl_counts['cold']; ?>)</a>
</div>

<div class="six-card" style="overflow-x:auto">
	<table class="six-table">
		<thead><tr><th>Lead</th><th>Priority</th><th>Score</th><th>Step</th><th>Business</th><th>Budget</th><th>Last active</th><th>AI read</th><th></th></tr></thead>
		<tbody>
		<?php if ( ! $pl_leads ) : ?>
		<tr><td colspan="9" style="text-align:center;color:var(--text3);padding:30px">No leads match this filter right now.</td></tr>
		<?php endif; ?>
		<?php foreach ( $pl_leads as $lead ) : ?>
		<tr>
			<td>
				<div style="font-weight:600"><?php echo esc_html( $lead['name'] ); ?></div>
				<div style="font-size:11px;color:var(--text3)"><?php echo esc_html( $lead['email'] ); ?><?php if ( $lead['phone'] ) : ?> · <?php echo esc_html( $lead['phone'] ); ?><?php endif; ?></div>
			</td>
			<td><?php echo six_pl_priority_badge( $lead['priority'] ); ?></td>
			<td class="six-num" style="font-variant-numeric:tabular-nums"><?php echo intval( $lead['score'] ); ?>/100</td>
			<td><?php echo intval( $lead['step'] ); ?>/5<?php if ( $lead['abandoned'] ) : ?><div style="font-size:10.5px;color:var(--danger,#dc2626)">abandoned</div><?php endif; ?></td>
			<td><?php echo esc_html( $lead['business'] ?: '—' ); ?></td>
			<td><?php echo esc_html( $lead['budget'] ?: '—' ); ?></td>
			<td style="white-space:nowrap;font-size:12px"><?php echo $lead['last_event'] ? esc_html( date_i18n( 'M j, g:i a', strtotime( $lead['last_event'] ) ) ) : '—'; ?></td>
			<td style="max-width:260px;font-size:12px;color:var(--text2)"><?php echo esc_html( $lead['ai_rec'] ); ?></td>
			<td style="white-space:nowrap">
				<a class="six-btn six-btn-primary six-btn-sm" href="?tab=clients&client=<?php echo intval( $lead['user_id'] ); ?>">Profile →</a>
				<?php if ( $lead['odoo_lead'] && get_option( 'six_odoo_url' ) ) : ?>
				<a class="six-btn six-btn-secondary six-btn-sm" target="_blank" rel="noopener" href="<?php echo esc_url( rtrim( get_option( 'six_odoo_url' ), '/' ) . '/odoo/crm/' . intval( $lead['odoo_lead'] ) ); ?>">Odoo</a>
				<?php endif; ?>
			</td>
		</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
