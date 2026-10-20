<?php
/**
 * Website delivery projects and the advisor-controlled website-maintenance
 * add-on.
 *
 * A website build is a one-off project, not a monthly retainer, so it isn't
 * measured by traffic numbers like SEO or Google Ads. The customer sees a
 * delivery progress bar built from milestones their advisor sets; the advisor
 * owns the milestone list from the client's profile.
 *
 * Website maintenance is separate again: it's a recurring add-on that ONLY an
 * advisor can switch on from the advisor dashboard. When it's on it shows up
 * under the customer's services; it is never self-serve.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Six_Website_Project {

	const MAINTENANCE_META = 'six_website_maintenance';
	const MAINTENANCE_FEE  = 'six_website_maintenance_fee';

	/** Default milestone set seeded for a new website build. */
	public static function default_milestones() {
		return array(
			'Discovery & content gathering',
			'Design concepts',
			'Design approved',
			'Build & development',
			'Review & revisions',
			'Launch',
		);
	}

	protected static function table() {
		global $wpdb;
		return $wpdb->prefix . 'six_website_milestones';
	}

	/** True when this client has a website build we should show progress for. */
	public static function has_project( $client_id ) {
		global $wpdb;
		$svc = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}six_client_services
			  WHERE client_id=%d AND service_slug='website' AND status IN ('active','pending')",
			$client_id
		) );
		if ( $svc > 0 ) return true;
		// Also count a build that only exists as milestones (advisor added it
		// manually before the service row was approved).
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM " . self::table() . " WHERE client_id=%d", $client_id
		) ) > 0;
	}

	/** Every milestone for a client, in display order. */
	public static function milestones( $client_id ) {
		global $wpdb;
		$t = self::table();
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t}'" ) !== $t ) return array();
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$t} WHERE client_id=%d ORDER BY sort_order ASC, id ASC", $client_id
		) );
	}

	/** Create the default milestone list for a client that has none yet. */
	public static function seed( $client_id ) {
		global $wpdb;
		if ( self::milestones( $client_id ) ) return false;
		$i = 0;
		foreach ( self::default_milestones() as $title ) {
			$wpdb->insert( self::table(), array(
				'client_id'  => $client_id,
				'title'      => $title,
				'status'     => 'pending',
				'sort_order' => $i++,
			) );
		}
		return true;
	}

	/**
	 * Delivery progress as a percentage of completed milestones.
	 *
	 * @return array{total:int,done:int,percent:int,current:string}
	 */
	public static function progress( $client_id ) {
		$rows  = self::milestones( $client_id );
		$total = count( $rows );
		if ( ! $total ) return array( 'total' => 0, 'done' => 0, 'percent' => 0, 'current' => '' );

		$done = 0; $current = '';
		foreach ( $rows as $r ) {
			if ( $r->status === 'done' ) { $done++; continue; }
			if ( $current === '' ) $current = $r->title;   // first unfinished one
		}
		return array(
			'total'   => $total,
			'done'    => $done,
			'percent' => (int) round( ( $done / $total ) * 100 ),
			'current' => $current ?: 'Complete',
		);
	}

	/** Advisor-only: is the recurring maintenance add-on switched on? */
	public static function maintenance_on( $client_id ) {
		return (bool) get_user_meta( $client_id, self::MAINTENANCE_META, true );
	}

	/** Advisor-only: the agreed monthly fee for maintenance, if one is set. */
	public static function maintenance_fee( $client_id ) {
		return (float) get_user_meta( $client_id, self::MAINTENANCE_FEE, true );
	}

	/**
	 * Turn website maintenance on or off for a client. Only ever called from
	 * an advisor-authenticated endpoint — customers cannot self-serve this.
	 */
	public static function set_maintenance( $client_id, $on, $fee = null ) {
		$on = (bool) $on;
		update_user_meta( $client_id, self::MAINTENANCE_META, $on ? 1 : 0 );
		if ( $fee !== null ) update_user_meta( $client_id, self::MAINTENANCE_FEE, max( 0, (float) $fee ) );

		if ( class_exists( 'Six_Odoo' ) ) {
			$lead_id = intval( get_user_meta( $client_id, 'six_odoo_lead_id', true ) );
			if ( $lead_id ) {
				Six_Odoo::post_note( $lead_id, 'Website maintenance ' . ( $on ? 'enabled' : 'disabled' )
					. ( $fee !== null ? ' at $' . number_format( (float) $fee, 2 ) . '/mo' : '' ) );
			}
		}
		return $on;
	}
}
