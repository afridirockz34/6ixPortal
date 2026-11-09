<?php
/**
 * BrightLocal API client.
 *
 * Covers the credential and transport layer only — storing the key, making an
 * authenticated request, and proving the key works. The local-pack and rank
 * grid reads that the GMB work needs are built on top of this.
 *
 * Auth, from the BrightLocal developer portal (developer.brightlocal.com):
 * a single API key sent in an `x-api-key` header against
 * https://api.brightlocal.com. There is no secret — the older legacy API took
 * an api-key/api-secret pair as form fields, and this is not that API.
 *
 * Note for whoever picks up the GMB work: BrightLocal also runs a remote MCP
 * server, which connects Claude to the same account directly the way the
 * Ahrefs connector does. Where a skill needs to ask BrightLocal a question,
 * that is the better route; this class is for what the PORTAL itself needs to
 * read on a schedule, without a conversation in the loop.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Six_BrightLocal {

	const BASE       = 'https://api.brightlocal.com';
	const OPTION_KEY = 'six_brightlocal_api_key';
	const TIMEOUT    = 20;

	/** The stored API key, or ''. */
	public static function key() {
		return (string) get_option( self::OPTION_KEY, '' );
	}

	/** Is there a key to try at all? */
	public static function configured() {
		return self::key() !== '';
	}

	/**
	 * One authenticated GET.
	 *
	 * Returns the decoded body on success, or array('error' => '...') — never
	 * a bare false, so a caller cannot mistake a failure for an empty result.
	 *
	 * @param string $path  Path under the base, e.g. '/manage/v1/clients'.
	 * @param array  $args  Query parameters.
	 */
	public static function get( $path, array $args = array() ) {
		$key = self::key();
		if ( $key === '' ) {
			return array( 'error' => 'No BrightLocal API key is saved. Add one under 6ix Portal → Integrations.' );
		}

		$url = self::BASE . '/' . ltrim( $path, '/' );
		if ( $args ) $url = add_query_arg( array_map( 'rawurlencode', $args ), $url );

		$res = wp_remote_get( $url, array(
			'timeout' => self::TIMEOUT,
			'headers' => array(
				'x-api-key' => $key,
				'Accept'    => 'application/json',
			),
		) );

		if ( is_wp_error( $res ) ) {
			return array( 'error' => $res->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );

		if ( $code === 401 || $code === 403 ) {
			return array( 'error' => 'BrightLocal rejected the API key (HTTP ' . $code . ').', 'status' => $code );
		}
		if ( $code < 200 || $code >= 300 ) {
			$detail = '';
			if ( is_array( $body ) ) {
				// Report whatever the API actually said rather than a generic
				// failure, so a wrong parameter is distinguishable from a
				// wrong key.
				foreach ( array( 'message', 'error', 'detail', 'title' ) as $f ) {
					if ( ! empty( $body[ $f ] ) && is_string( $body[ $f ] ) ) { $detail = $body[ $f ]; break; }
				}
			}
			return array( 'error' => 'BrightLocal returned HTTP ' . $code . ( $detail ? ': ' . $detail : '' ), 'status' => $code );
		}
		if ( ! is_array( $body ) ) {
			return array( 'error' => 'BrightLocal returned a response that was not JSON.' );
		}

		return $body;
	}

	/**
	 * Prove the saved key works, against the cheapest read there is: one page
	 * of one client. A key that cannot list clients cannot do anything else
	 * either, so this is a real check rather than a reachability ping.
	 *
	 * @return array{ok:bool,message:string,clients:?int}
	 */
	public static function test() {
		if ( ! self::configured() ) {
			return array( 'ok' => false, 'message' => 'No API key saved yet.', 'clients' => null );
		}

		$res = self::get( '/manage/v1/clients', array( 'num_per_page' => '1' ) );

		if ( isset( $res['error'] ) ) {
			return array( 'ok' => false, 'message' => $res['error'], 'clients' => null );
		}

		$total = isset( $res['total_count'] ) ? (int) $res['total_count'] : null;
		return array(
			'ok'      => true,
			'message' => $total === null
				? 'Connected.'
				: 'Connected — ' . number_format( $total ) . ' client' . ( $total === 1 ? '' : 's' ) . ' in this BrightLocal account.',
			'clients' => $total,
		);
	}
}
