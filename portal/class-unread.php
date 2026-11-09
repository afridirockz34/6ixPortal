<?php
/**
 * "There is something new in here" markers for the customer's sidebar.
 *
 * A notification tells the client something happened, once, and then it is
 * gone from view. It does not tell them, three days later, that the report
 * they never opened is still sitting there. That is what these are for: a dot
 * beside the nav item, which stays until they open that section.
 *
 * The rule is the same for every section: compare the newest thing in it
 * against the last time this client looked at it. No per-item read state, no
 * second table — the timestamp the section already stores is enough, and
 * "opened the tab" is a better definition of read than anything we could ask
 * the client to click.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Six_Unread {

	/** Where each section's "last opened" time is kept, per client. */
	const META_PREFIX = 'six_seen_';

	/**
	 * The sections that can carry a dot. Anything not listed here never gets
	 * one, so a new tab does not silently acquire an indicator nothing clears.
	 */
	public static function sections() {
		return array( 'reports', 'metrics', 'messages' );
	}

	/** When this client last opened a section, as a MySQL datetime, or ''. */
	public static function last_seen( $client_id, $section ) {
		return (string) get_user_meta( (int) $client_id, self::META_PREFIX . $section, true );
	}

	/**
	 * Mark a section read. Called when the client opens the tab, which is the
	 * only thing that clears a dot. Pass the service slug for a per-service
	 * metrics marker, so opening one service does not clear the rest.
	 */
	public static function mark_seen( $client_id, $section, $slug = '' ) {
		if ( ! in_array( $section, self::sections(), true ) ) return false;
		$key = $slug !== '' ? $section . '_' . $slug : $section;
		return (bool) update_user_meta( (int) $client_id, self::META_PREFIX . $key, current_time( 'mysql' ) );
	}

	/**
	 * The newest thing in a section, as a MySQL datetime, or '' if it is empty.
	 *
	 * 'metrics' takes an optional service slug: each service is its own nav
	 * item, so each gets its own dot rather than one shared marker that a
	 * client clears by opening whichever service they happened to click.
	 */
	public static function latest( $client_id, $section, $slug = '' ) {
		global $wpdb;
		$client_id = (int) $client_id;
		if ( ! $client_id ) return '';

		if ( $section === 'reports' ) {
			$tbl = $wpdb->prefix . 'six_reports';
			if ( ! self::have( $tbl ) ) return '';
			return (string) $wpdb->get_var( $wpdb->prepare(
				"SELECT MAX(created_at) FROM {$tbl} WHERE client_id=%d", $client_id ) );
		}

		if ( $section === 'metrics' ) {
			$tbl = $wpdb->prefix . 'six_metrics';
			if ( ! self::have( $tbl ) ) return '';
			if ( $slug !== '' ) {
				return (string) $wpdb->get_var( $wpdb->prepare(
					"SELECT MAX(updated_at) FROM {$tbl} WHERE client_id=%d AND service_slug=%s",
					$client_id, $slug ) );
			}
			return (string) $wpdb->get_var( $wpdb->prepare(
				"SELECT MAX(updated_at) FROM {$tbl} WHERE client_id=%d", $client_id ) );
		}

		if ( $section === 'messages' ) {
			$tbl = $wpdb->prefix . 'six_messages';
			if ( ! self::have( $tbl ) ) return '';
			// Only what somebody sent TO them. A client's own last message is
			// not news to the client.
			return (string) $wpdb->get_var( $wpdb->prepare(
				"SELECT MAX(created_at) FROM {$tbl} WHERE receiver_id=%d", $client_id ) );
		}

		return '';
	}

	/**
	 * Does this section have something the client has not seen?
	 *
	 * A client who has never opened the section sees a dot as soon as there is
	 * anything in it — which is right: they have not read it.
	 */
	public static function has_new( $client_id, $section, $slug = '' ) {
		$latest = self::latest( $client_id, $section, $slug );
		if ( $latest === '' || $latest === null ) return false;

		$key  = $slug !== '' ? $section . '_' . $slug : $section;
		$seen = self::last_seen( $client_id, $key );
		if ( $seen === '' ) return true;

		return strtotime( $latest ) > strtotime( $seen );
	}

	/**
	 * The dot itself. Returns '' when there is nothing new, so it can be
	 * echoed inline. The label is on the element rather than in a hidden
	 * span, so a screen reader announces it and nothing renders as stray text
	 * if the stylesheet is missing.
	 */
	public static function dot( $client_id, $section, $slug = '' ) {
		if ( ! self::has_new( $client_id, $section, $slug ) ) return '';
		return '<span class="six-nav-dot" role="img" aria-label="Unread" title="Something new here"></span>';
	}

	/** Does a table exist? Cached per request; a missing one simply means no dot. */
	private static function have( $table ) {
		if ( function_exists( 'six_table_exists' ) ) return six_table_exists( $table );
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}
}
