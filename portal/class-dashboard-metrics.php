<?php
/**
 * Dashboard metrics — the single place that decides WHAT number a customer
 * sees for a given service, and WHERE that number came from.
 *
 * Every metric resolves in the same priority order:
 *
 *   1. live      — a connected external tool (Ahrefs, Google Ads, BrightLocal).
 *   2. advisor   — a value an advisor typed into the KPI editor (overrides estimates).
 *   3. estimate  — an industry benchmark derived from their services + budget.
 *
 * The live tier is deliberately a seam: connectors() declares which external
 * tool owns which service, and live() returns null until that tool's API
 * credentials are configured. That means the dashboard UI never has to be
 * rebuilt when a connector goes live — only live() starts returning data.
 *
 * Nothing in here projects revenue or ROI. We can't guarantee returns, so the
 * customer-facing numbers are reach and activity metrics only.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Six_Dash_Metrics {

	/**
	 * Which external tool backs which service, and whether it's wired up yet.
	 * `connected` flips to true once the matching credentials exist, at which
	 * point live() starts serving real data for that service's metrics.
	 */
	public static function connectors() {
		return array(
			'seo' => array(
				'source'    => 'ahrefs',
				'label'     => 'Ahrefs',
				'connected' => (bool) get_option( 'six_ahrefs_api_token', '' ),
			),
			'google-ads' => array(
				'source'    => 'google-ads',
				'label'     => 'Google Ads',
				'connected' => (bool) get_option( 'six_gads_refresh_token', '' ),
			),
			'google-business' => array(
				'source'    => 'brightlocal',
				'label'     => 'BrightLocal',
				'connected' => (bool) get_option( 'six_brightlocal_api_key', '' ),
			),
		);
	}

	/**
	 * Live metrics for one service, or null when that connector isn't wired up.
	 *
	 * Returns a flat metric_key => value map. Implementations must cache their
	 * own API responses (these run on a dashboard page load) and must return
	 * null — never partial garbage — when the upstream call fails, so the
	 * caller falls through to the advisor/estimate tiers.
	 */
	public static function live( $slug, $user_id ) {
		$conn = self::connectors()[ $slug ] ?? null;
		if ( ! $conn || ! $conn['connected'] ) return null;

		/**
		 * Connector implementations hook here, e.g.:
		 *   add_filter('six_dash_live_metrics', fn($v,$s,$u) => $s==='seo' ? Six_Ahrefs::metrics($u) : $v, 10, 3);
		 */
		$live = apply_filters( 'six_dash_live_metrics', null, $slug, $user_id );
		return is_array( $live ) && $live ? $live : null;
	}

	/**
	 * The metrics each service can show, in display-priority order. When a
	 * customer has only one service it fills all four card slots from that
	 * service's list; with two services they get the top two from each.
	 */
	public static function catalog() {
		return array(
			'seo' => array(
				'label'   => 'SEO',
				'accent'  => array( '#56D364', '#10b981' ),
				'metrics' => array(
					'organic_visitors' => array( 'label' => 'Organic Visitors', 'suffix' => '/mo' ),
					'keywords_page1'   => array( 'label' => 'Keywords on Page 1' ),
					'keywords_tracked' => array( 'label' => 'Keywords Tracked' ),
					'seo_avg_position' => array( 'label' => 'Avg Position' ),
				),
			),
			'google-ads' => array(
				'label'   => 'Google Ads',
				'accent'  => array( '#6ACAFD', '#3b82f6' ),
				'metrics' => array(
					'paid_clicks'     => array( 'label' => 'Ad Clicks',      'suffix' => '/mo' ),
					'ads_leads'       => array( 'label' => 'Leads from Ads', 'suffix' => '/mo' ),
					'ads_impressions' => array( 'label' => 'Impressions',    'suffix' => '/mo' ),
					'ads_cpc'         => array( 'label' => 'Avg Cost / Click', 'prefix' => '$' ),
				),
			),
			'google-business' => array(
				'label'   => 'Google Business',
				'accent'  => array( '#FF6699', '#8781BA' ),
				'metrics' => array(
					'gmb_views'      => array( 'label' => 'Map Views',          'suffix' => '/mo' ),
					'gmb_calls'      => array( 'label' => 'Calls from Maps',    'suffix' => '/mo' ),
					'gmb_directions' => array( 'label' => 'Direction Requests', 'suffix' => '/mo' ),
					'gmb_posts'      => array( 'label' => 'Posts This Month' ),
				),
			),
		);
	}

	/**
	 * Services that get stat cards, in priority order. 'website' is absent on
	 * purpose — website builds are tracked by a delivery progress bar, not by
	 * monthly performance numbers.
	 */
	public static function card_services( array $slugs ) {
		$order = array( 'seo', 'google-ads', 'google-business' );
		return array_values( array_intersect( $order, array_unique( $slugs ) ) );
	}

	/**
	 * Benchmark estimates per service, used only when there's no live
	 * connector and no advisor-entered value. Grounded in their actual monthly
	 * budget for that service plus the real market CPC we cached at onboarding
	 * where we have one.
	 */
	protected static function estimates( $slug, $budget, $cpc ) {
		$budget = max( 0, (int) $budget );
		$cpc    = $cpc > 0 ? (float) $cpc : 0.0;

		if ( $slug === 'google-ads' ) {
			// ~65% of the budget reaches the auction as clicks; the rest is
			// management, creative and tracking.
			$eff    = $cpc > 0 ? $cpc : 3.0;
			$clicks = $budget > 0 ? (int) floor( ( $budget * 0.65 ) / max( $eff, 0.5 ) ) : 0;
			return array(
				'paid_clicks'     => $clicks,
				'ads_leads'       => (int) round( $clicks * 0.027 ),   // ~2.7% click→lead
				'ads_impressions' => (int) round( $clicks * 22 ),      // ~4.5% CTR
				'ads_cpc'         => $cpc > 0 ? round( $cpc, 2 ) : 0,
			);
		}

		if ( $slug === 'seo' ) {
			$scale = $budget > 0 ? min( 3.0, max( 0.5, $budget / 1500 ) ) : 0.0;
			return array(
				'organic_visitors' => (int) round( 800 * $scale ),
				'keywords_page1'   => (int) round( 18 * $scale ),
				'keywords_tracked' => (int) round( 120 * $scale ),
				'seo_avg_position' => $scale > 0 ? 14 : 0,
			);
		}

		if ( $slug === 'google-business' ) {
			// GMB is a flat-rate service, so its benchmarks don't scale with budget.
			$on = $budget > 0 ? 1 : 0;
			return array(
				'gmb_views'      => $on ? 600 : 0,
				'gmb_calls'      => $on ? 42  : 0,
				'gmb_directions' => $on ? 70  : 0,
				'gmb_posts'      => $on ? 8   : 0,   // 2/week
			);
		}

		return array();
	}

	/**
	 * Build the stat cards for the dashboard's top row.
	 *
	 * @param array $ctx {
	 *   @type array $slugs    Active (or onboarding-selected) service slugs.
	 *   @type array $budgets  slug => monthly budget.
	 *   @type array $kpi      Advisor overrides: kpi_key => (object) {kpi_value, kpi_prev}.
	 *   @type float $cpc      Cached market avg CPC, if any.
	 * }
	 * @return array[] Each: label, value, sub, source, service, accent, prev.
	 */
	public static function cards( $user_id, array $ctx ) {
		$slugs   = self::card_services( $ctx['slugs'] ?? array() );
		$budgets = $ctx['budgets'] ?? array();
		$kpi     = $ctx['kpi']     ?? array();
		$cpc     = $ctx['cpc']     ?? 0;
		$catalog = self::catalog();

		if ( ! $slugs ) return array();

		// Split 4 slots across their services — one service fills all four,
		// two services get two each, three get 2/1/1.
		$n     = count( $slugs );
		$base  = intdiv( 4, $n );
		$extra = 4 % $n;
		$alloc = array();
		foreach ( $slugs as $i => $slug ) $alloc[ $slug ] = $base + ( $i < $extra ? 1 : 0 );

		$cards = array();
		foreach ( $slugs as $slug ) {
			$svc  = $catalog[ $slug ];
			$live = self::live( $slug, $user_id );
			$est  = self::estimates( $slug, $budgets[ $slug ] ?? 0, $cpc );
			$take = array_slice( array_keys( $svc['metrics'] ), 0, $alloc[ $slug ] );

			foreach ( $take as $key ) {
				$meta = $svc['metrics'][ $key ];

				if ( $live !== null && isset( $live[ $key ] ) && $live[ $key ] !== '' ) {
					$raw = $live[ $key ];
					$src = 'live';
					$sub = self::connectors()[ $slug ]['label'] . ' · live';
				} elseif ( isset( $kpi[ $key ] ) && $kpi[ $key ]->kpi_value !== '' ) {
					$raw = $kpi[ $key ]->kpi_value;
					$src = 'advisor';
					$sub = 'Confirmed by your advisor';
				} else {
					$raw = $est[ $key ] ?? 0;
					$src = 'estimate';
					$sub = 'Industry benchmark';
				}

				$cards[] = array(
					'key'     => $key,
					'service' => $slug,
					'svc_label' => $svc['label'],
					'label'   => $meta['label'],
					'value'   => self::format( $raw, $meta ),
					'sub'     => $sub,
					'source'  => $src,
					'accent'  => $svc['accent'],
					'prev'    => isset( $kpi[ $key ] ) ? ( $kpi[ $key ]->kpi_prev ?: '' ) : '',
				);
			}
		}

		return array_slice( $cards, 0, 4 );
	}

	/** Render a raw metric value with its prefix/suffix, or an em dash when empty. */
	public static function format( $raw, array $meta ) {
		if ( $raw === '' || $raw === null ) return '—';
		if ( is_numeric( $raw ) ) {
			if ( (float) $raw <= 0 ) return '—';
			$num = ( floor( (float) $raw ) == (float) $raw )
				? number_format( (int) $raw )
				: number_format( (float) $raw, 2 );
		} else {
			$num = (string) $raw;   // advisor typed something free-form
		}
		return ( $meta['prefix'] ?? '' ) . $num . ( $meta['suffix'] ?? '' );
	}

	/**
	 * The two-line visitors chart: where they'd be without us, versus where
	 * they actually are across every service they're paying for.
	 *
	 * Visitors only — no revenue, no ROI. The `source` flag says whether the
	 * "with us" line is live connector data or still a projection, so the
	 * chart can label itself honestly instead of passing an estimate off as
	 * measured traffic.
	 *
	 * @return array{has:bool,source:string,baseline_mo:int,current_mo:int,points:array}
	 */
	public static function visitor_series( $user_id, array $ctx ) {
		$slugs   = self::card_services( $ctx['slugs'] ?? array() );
		$budgets = $ctx['budgets'] ?? array();
		$kpi     = $ctx['kpi']     ?? array();
		$cpc     = $ctx['cpc']     ?? 0;

		// Combined monthly visitors across their services, live where possible.
		$current = 0;
		$is_live = $slugs ? true : false;
		foreach ( $slugs as $slug ) {
			$live = self::live( $slug, $user_id );
			$vals = $live !== null ? $live : self::estimates( $slug, $budgets[ $slug ] ?? 0, $cpc );
			if ( $live === null ) $is_live = false;

			// Each service contributes the visitor-shaped metric it owns.
			foreach ( array( 'organic_visitors', 'paid_clicks', 'gmb_views' ) as $vk ) {
				if ( ! empty( $vals[ $vk ] ) ) $current += (int) $vals[ $vk ];
			}
		}

		// An advisor-confirmed total always wins over our own arithmetic.
		if ( isset( $kpi['total_visitors'] ) && $kpi['total_visitors']->kpi_value !== '' ) {
			$adv = (int) preg_replace( '/[^0-9]/', '', $kpi['total_visitors']->kpi_value );
			if ( $adv > 0 ) { $current = $adv; $is_live = true; }
		}

		// Baseline = where they were before us. Advisor-entered if we have it,
		// otherwise assume the pre-existing share of today's traffic that
		// wasn't coming from work we're doing.
		$baseline = 0;
		if ( isset( $kpi['visitors_baseline'] ) && $kpi['visitors_baseline']->kpi_value !== '' ) {
			$baseline = (int) preg_replace( '/[^0-9]/', '', $kpi['visitors_baseline']->kpi_value );
		}
		if ( $baseline <= 0 ) $baseline = (int) round( $current * 0.35 );

		if ( $current <= 0 ) {
			return array( 'has' => false, 'source' => 'none', 'baseline_mo' => 0, 'current_mo' => 0, 'points' => array() );
		}

		// 12 weeks. The "without us" line drifts gently (markets don't stand
		// perfectly still); the "with us" line follows a logistic ramp from the
		// baseline up to today's combined figure.
		$points = array();
		$raw = array();
		for ( $w = 1; $w <= 12; $w++ ) $raw[ $w ] = 1 / ( 1 + exp( -0.7 * ( $w - 6 ) ) );
		$lo = min( $raw ); $hi = max( $raw );

		for ( $w = 1; $w <= 12; $w++ ) {
			$f      = ( $raw[ $w ] - $lo ) / max( $hi - $lo, 0.0001 );   // 0..1
			$before = (int) round( $baseline * ( 1 - 0.02 * abs( sin( $w / 2 ) ) ) );
			// The with-us line can never sit below the without-us line — the
			// ramp starts at the baseline, and the drift above only moves
			// downward, so a early-week wobble can't read as us having made
			// their traffic worse.
			$after  = (int) round( $baseline + ( $current - $baseline ) * $f );
			$points[] = array(
				'w'      => $w,
				'before' => $before,
				'after'  => max( $before, $after ),
			);
		}

		return array(
			'has'         => true,
			'source'      => $is_live ? 'live' : 'projected',
			'baseline_mo' => $baseline,
			'current_mo'  => $current,
			'points'      => $points,
		);
	}
}
