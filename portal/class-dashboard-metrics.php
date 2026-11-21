<?php
/**
 * Dashboard metrics — the single place that decides WHAT number a customer
 * sees for a given service, and WHERE that number came from.
 *
 * Every metric resolves in the same priority order:
 *
 *   1. live      — a connected external tool (Ahrefs, Google Ads, BrightLocal).
 *   2. advisor   — a value an advisor typed into the KPI editor (overrides estimates).
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
	/**
	 * Is a smaller number better for this metric? Average position and cost
	 * per click improve as they fall; everything else in the catalog improves
	 * as it rises. Anything comparing or trending a metric must ask this —
	 * a position sliding from 3 to 8 is not a 167% gain.
	 */
	/**
	 * How far along a metric is toward its target.
	 *
	 * This exists because the dashboard was computing `current / target` for
	 * every metric, which is backwards for the ones that improve by falling.
	 * An average position of 11.3 against a target of 6 was rendering as a
	 * full green bar at 100%, and so was a page load of 1,200ms against a
	 * target of 400. Both say "done" about work that has not started.
	 *
	 * Two honest readings, in order of preference:
	 *
	 *  - With a starting point, progress is the distance travelled from where
	 *    we began toward where we said we would get: (start − current) ÷
	 *    (start − target). This is the one a client can argue with.
	 *  - Without one, the best available is the ratio in whichever direction
	 *    the metric improves, which at least moves the right way and only
	 *    reaches 100% when the target is actually met.
	 *
	 * A metric with no target returns null rather than 0. "Nobody set a
	 * target" and "no progress" are different facts, and an empty bar at 0%
	 * is the dashboard asserting the second when it means the first.
	 *
	 * @param mixed $start Where the metric stood when the work began, if known.
	 * @return array{pct:int|null,met:bool,direction:string,reason:string}
	 */
	public static function progress( $current, $target, $lower_better = false, $start = null ) {
		$c = self::number( $current );
		$t = self::number( $target );
		$s = self::number( $start );

		if ( $t === null ) {
			return array( 'pct' => null, 'met' => false,
			              'direction' => $lower_better ? 'lower' : 'higher',
			              'reason' => 'no target set' );
		}
		if ( $c === null ) {
			return array( 'pct' => null, 'met' => false,
			              'direction' => $lower_better ? 'lower' : 'higher',
			              'reason' => 'nothing measured yet' );
		}

		$met = $lower_better ? ( $c <= $t ) : ( $c >= $t );
		if ( $met ) {
			return array( 'pct' => 100, 'met' => true,
			              'direction' => $lower_better ? 'lower' : 'higher',
			              'reason' => 'target met' );
		}

		// Travelled from the start, where there is a start to travel from and
		// it sits on the far side of the target.
		if ( $s !== null && abs( $s - $t ) > 0.0001
		     && ( $lower_better ? $s > $t : $s < $t ) ) {
			$pct = ( $s - $c ) / ( $s - $t ) * 100;
			return array(
				'pct'       => (int) max( 0, min( 99, round( $pct ) ) ),
				'met'       => false,
				'direction' => $lower_better ? 'lower' : 'higher',
				'reason'    => 'measured from the starting point',
			);
		}

		// No usable start: the ratio, pointed the way the metric improves.
		$pct = $lower_better
			? ( $c > 0 ? ( $t / $c ) * 100 : 0 )
			: ( $t > 0 ? ( $c / $t ) * 100 : 0 );

		return array(
			'pct'       => (int) max( 0, min( 99, round( $pct ) ) ),
			'met'       => false,
			'direction' => $lower_better ? 'lower' : 'higher',
			'reason'    => 'no starting point recorded, so this is the ratio to target',
		);
	}

	/**
	 * Whether a change is an improvement, and by how much.
	 *
	 * The dashboard was drawing every fall in red and every rise in green,
	 * which paints an average position improving from 18 to 11 as a loss. The
	 * percentage is the size of the move; `good` is what decides the colour.
	 *
	 * @return array{pct:int|null,good:bool|null,arrow:string}
	 */
	public static function change( $current, $previous, $lower_better = false ) {
		$c = self::number( $current );
		$p = self::number( $previous );
		if ( $c === null || $p === null || abs( $p ) < 0.0001 ) {
			return array( 'pct' => null, 'good' => null, 'arrow' => '' );
		}
		$delta = ( ( $c - $p ) / abs( $p ) ) * 100;
		$rose  = $delta > 0;
		return array(
			'pct'   => (int) round( abs( $delta ) ),
			'good'  => abs( $delta ) < 0.5 ? null : ( $lower_better ? ! $rose : $rose ),
			'arrow' => $rose ? '↑' : '↓',
		);
	}

	/** A figure out of whatever the advisor typed, or null. Never 0 by default. */
	public static function number( $raw ) {
		if ( $raw === null || $raw === '' ) return null;
		if ( is_numeric( $raw ) ) return (float) $raw;
		$clean = preg_replace( '/[^0-9.\-]/', '', (string) $raw );
		// "–", "n/a" and "" all clean down to nothing, and all of them mean
		// "no figure" rather than zero.
		if ( $clean === '' || $clean === '-' || $clean === '.' ) return null;
		return (float) $clean;
	}

	public static function lower_is_better( $key_or_label ) {
		$needle = strtolower( trim( (string) $key_or_label ) );
		if ( $needle === '' ) return false;
		foreach ( self::catalog() as $svc ) {
			foreach ( $svc['metrics'] as $key => $m ) {
				if ( $needle === $key || $needle === strtolower( $m['label'] ) ) {
					return ! empty( $m['lower_better'] );
				}
			}
		}
		// The rows the customer's service page renders come from six_metrics,
		// which stores a label an advisor typed rather than a catalog key. A
		// label that does not match the catalog exactly still has to be
		// pointed the right way, or its progress bar fills up as the number
		// gets worse.
		foreach ( array( 'position', 'rank', 'issues', 'errors', 'load', 'speed',
		                 'cost', 'cpc', 'bounce', 'spend', 'time to' ) as $hint ) {
			if ( strpos( $needle, $hint ) !== false ) return true;
		}
		return false;
	}

	/**
	 * Every metric carries a 'unit': the period or quantity the number is in,
	 * written out. "1,240" on its own is unreadable — a month's visitors, a
	 * day's, a running total? The suffix is the compact form shown beside the
	 * figure; the unit is the plain-language one shown under it and beside the
	 * box an advisor types it into, so the number that reaches the customer
	 * means what the advisor meant.
	 */
	public static function catalog() {
		return array(
			'seo' => array(
				'label'   => 'SEO',
				'accent'  => array( '#56D364', '#10b981' ),
				'metrics' => array(
					'organic_visitors' => array( 'label' => 'Organic Visitors', 'suffix' => '/mo', 'unit' => 'visitors per month' ),
					'keywords_page1'   => array( 'label' => 'Keywords on Page 1', 'unit' => 'keywords, right now' ),
					'keywords_tracked' => array( 'label' => 'Keywords Tracked',   'unit' => 'keywords, right now' ),
					'seo_avg_position' => array( 'label' => 'Avg Position', 'lower_better' => true, 'unit' => 'average rank across tracked keywords' ),
					// Site health. Both improve by FALLING, and both sit at the
					// end of this list deliberately: cards() fills its stat
					// slots from the first keys, so these become targetable
					// without pushing a visitor count off the top row.
					// Before they were here they had no key at all, which is
					// why "On-page Issues" could never hold a target — the
					// metrics wizard writes by key, and there was none to write.
					'onpage_issues'    => array( 'label' => 'On-page Issues', 'lower_better' => true, 'unit' => 'issues outstanding' ),
					'page_load_ms'     => array( 'label' => 'Page Load (ms)', 'suffix' => 'ms', 'lower_better' => true, 'unit' => 'milliseconds to first render' ),
				),
			),
			'google-ads' => array(
				'label'   => 'Google Ads',
				'accent'  => array( '#6ACAFD', '#3b82f6' ),
				'metrics' => array(
					'paid_clicks'     => array( 'label' => 'Ad Clicks',      'suffix' => '/mo', 'unit' => 'clicks per month' ),
					'ads_leads'       => array( 'label' => 'Leads from Ads', 'suffix' => '/mo', 'unit' => 'leads per month' ),
					'ads_impressions' => array( 'label' => 'Impressions',    'suffix' => '/mo', 'unit' => 'impressions per month' ),
					'ads_cpc'         => array( 'label' => 'Avg Cost / Click', 'prefix' => '$', 'lower_better' => true, 'unit' => 'dollars per click' ),
				),
			),
			'google-business' => array(
				'label'   => 'Google Business',
				'accent'  => array( '#FF6699', '#8781BA' ),
				'metrics' => array(
					'gmb_views'      => array( 'label' => 'Map Views',          'suffix' => '/mo', 'unit' => 'views per month' ),
					'gmb_calls'      => array( 'label' => 'Calls from Maps',    'suffix' => '/mo', 'unit' => 'calls per month' ),
					'gmb_directions' => array( 'label' => 'Direction Requests', 'suffix' => '/mo', 'unit' => 'requests per month' ),
					'gmb_posts'      => array( 'label' => 'Posts This Month',   'unit' => 'posts per month' ),
					// What BrightLocal can actually measure. Average map rank
					// improves by falling, which lower_is_better() has to know
					// or the card paints progress backwards.
					'gmb_avg_rank'     => array( 'label' => 'Average Map Rank', 'unit' => 'across the local grid', 'lower_better' => true ),
					'gmb_grid_coverage'=> array( 'label' => 'Map Coverage',     'suffix' => '%', 'unit' => 'of the grid where they appear' ),
				),
			),
		);
	}

	/**
	 * Metrics that belong to the client rather than to one service — the
	 * graph's starting line, and the business figures an advisor confirms.
	 * They have no card of their own but are stored and edited the same way.
	 */
	public static function extra_metrics() {
		return array(
			'visitors_baseline' => array( 'label' => 'Visitors Before Us', 'unit' => 'visitors per month, before we started',
			                              'hint' => 'The baseline the visitors graph is drawn from.' ),
			'total_visitors'    => array( 'label' => 'Total Visitors',  'unit' => 'sessions per month, all sources' ),
			'new_customers'     => array( 'label' => 'New Customers',   'unit' => 'new customers per month' ),
			'sales_revenue'     => array( 'label' => 'Sales Revenue',   'prefix' => '$', 'unit' => 'dollars per month' ),
		);
	}

	/**
	 * Every metric an advisor can set for this client, flattened and keyed, so
	 * the advisor UI is generated from the same catalog the customer's cards
	 * are rendered from.
	 *
	 * This is the whole point: a hand-written list of advisor fields drifts
	 * from the cards, and then an advisor fills in four boxes that reach
	 * nothing while the metric the customer actually sees has no box at all.
	 *
	 * @param array $slugs  The client's active services.
	 * @param bool  $extras Include the client-level metrics too.
	 * @return array[] key => label, unit, hint, service, service_label, prefix, suffix, on_card
	 */
	public static function editable_metrics( array $slugs, $extras = true ) {
		$out = array();
		$catalog = self::catalog();
		foreach ( self::card_services( $slugs ) as $slug ) {
			foreach ( $catalog[ $slug ]['metrics'] as $key => $m ) {
				$out[ $key ] = array(
					'key'           => $key,
					'label'         => $m['label'],
					'unit'          => $m['unit'] ?? '',
					'hint'          => '',
					'service'       => $slug,
					'service_label' => $catalog[ $slug ]['label'],
					'prefix'        => $m['prefix'] ?? '',
					'suffix'        => $m['suffix'] ?? '',
					'on_card'       => true,
				);
			}
		}
		if ( $extras ) {
			foreach ( self::extra_metrics() as $key => $m ) {
				$out[ $key ] = array(
					'key'           => $key,
					'label'         => $m['label'],
					'unit'          => $m['unit'] ?? '',
					'hint'          => $m['hint'] ?? '',
					'service'       => '',
					'service_label' => 'Your business',
					'prefix'        => $m['prefix'] ?? '',
					'suffix'        => $m['suffix'] ?? '',
					'on_card'       => false,
				);
			}
		}
		return $out;
	}

	/**
	 * The key in the metric catalog whose label matches this one.
	 *
	 * The detail rows in six_metrics are keyed on the label an advisor typed,
	 * and the daily readings in six_daily_metrics are keyed on the catalog's
	 * own key. Joining them is the only way a target graph gets a real line
	 * under it rather than two points.
	 */
	public static function key_for( $label ) {
		$needle = strtolower( trim( (string) $label ) );
		if ( $needle === '' ) return '';
		foreach ( self::catalog() as $svc ) {
			foreach ( $svc['metrics'] as $key => $m ) {
				if ( $needle === $key || $needle === strtolower( $m['label'] ) ) return $key;
			}
		}
		foreach ( self::extra_metrics() as $key => $m ) {
			if ( $needle === $key || $needle === strtolower( $m['label'] ) ) return $key;
		}
		return '';
	}

	/**
	 * One metric's journey towards its target, as a drawable series.
	 *
	 * Replaces the progress bar, which could say 62% and nothing else — not
	 * whether the number is moving, not how fast, not whether it moved at all
	 * since the work started.
	 *
	 * What this will not do is draw a line it does not have. Where the daily
	 * sync has readings, the line is those readings. Where it does not, the
	 * series is exactly the points we know — the starting figure and today's
	 * — and `sparse` says so, so the chart can draw two dots and a dashed
	 * connector instead of a confident curve through invented days. A smooth
	 * thirty-day line interpolated from two numbers is a measurement the
	 * client never gave us, on a chart they will read as one.
	 *
	 * Geometry, from the brief: the target sits at TARGET_AT of the plot
	 * height, and every value is placed against it, so a metric halfway to
	 * its target draws at roughly 42% — inside the 40-50% band asked for —
	 * without anybody inventing a scale. `y` is a 0-1 fraction of plot
	 * height, already pointing the way the metric improves, so a falling
	 * average position climbs the chart.
	 *
	 * @return array{has:bool,...}
	 */
	const TARGET_AT = 0.85;   // where the target circle sits, as plot height
	const PLOT_CAP  = 0.95;   // nothing is ever drawn above this

	public static function target_series( $user_id, $label, $current, $target,
	                                      $previous = null, $days = 90 ) {
		$lower = self::lower_is_better( $label );
		$c     = self::number( $current );
		$t     = self::number( $target );
		$p     = self::number( $previous );

		if ( $c === null ) {
			return array( 'has' => false, 'reason' => 'nothing measured yet' );
		}

		$key    = self::key_for( $label );
		$daily  = ( $key && class_exists( 'Six_Tracking' ) )
			? Six_Tracking::daily( $user_id, $key, $days ) : array();

		$points = array();
		if ( count( $daily ) >= 3 ) {
			// Real readings. Thinned to at most 30 marks so a 90-day series
			// stays legible, keeping the first and last exactly.
			$step = max( 1, (int) ceil( count( $daily ) / 30 ) );
			foreach ( $daily as $i => $row ) {
				if ( $i % $step && $i !== count( $daily ) - 1 ) continue;
				$points[] = array(
					'date'  => $row['date'],
					'label' => date_i18n( 'j M', strtotime( $row['date'] ) ),
					'value' => (float) $row['value'],
				);
			}
			$sparse = false;
		} else {
			// Two known figures, or one. Drawn as the dots they are.
			if ( $p !== null ) {
				$points[] = array( 'date' => '', 'label' => 'Before', 'value' => $p );
			}
			$points[] = array( 'date' => '', 'label' => 'Now', 'value' => $c );
			$sparse = true;
		}

		// ── The scale ────────────────────────────────────────────────────
		// Higher-is-better: the axis runs from zero, with the target at
		// TARGET_AT. Lower-is-better: the axis runs from the worst figure we
		// have seen up to the target, so better is still up — an average
		// position of 18 improving to 11 has to climb, not fall.
		$values = array_column( $points, 'value' );
		$values[] = $c;
		if ( $t !== null ) $values[] = $t;

		if ( $lower ) {
			$worst = max( $values );
			$best  = $t !== null ? min( $t, min( $values ) ) : min( $values );
			$span  = $worst - $best;
			$place = function ( $v ) use ( $worst, $span ) {
				if ( $span <= 0.0001 ) return self::TARGET_AT;
				return min( self::PLOT_CAP, max( 0.0, ( $worst - $v ) / $span * self::TARGET_AT ) );
			};
		} else {
			$top   = $t !== null && $t > 0 ? (float) $t : max( $values );
			$place = function ( $v ) use ( $top ) {
				if ( $top <= 0.0001 ) return 0.0;
				return min( self::PLOT_CAP, max( 0.0, $v / $top * self::TARGET_AT ) );
			};
		}

		foreach ( $points as $i => $pt ) {
			$points[ $i ]['y'] = round( $place( $pt['value'] ), 4 );
		}

		$prog = self::progress( $current, $target, $lower, $previous );

		return array(
			'has'       => true,
			'label'     => (string) $label,
			'unit'      => self::unit_for( $label ),
			'points'    => $points,
			'sparse'    => $sparse,
			'measured'  => ! $sparse,
			'current'   => $c,
			'target'    => $t,
			'target_y'  => $t === null ? null : round( $place( $t ), 4 ),
			'lower'     => $lower,
			'pct'       => $prog['pct'],
			'met'       => $prog['met'],
			'reason'    => $prog['reason'],
			// Short enough to sit on one line beside the target. The longer
			// version is the card's tooltip: a footer that wraps onto two
			// lines pushes into the target figure beside it.
			'note'      => $sparse ? 'Two readings so far' : '',
			'note_long' => $sparse
				? 'Only two readings exist for this metric. The line fills in day by day once the daily sync has more.'
				: '',
		);
	}

	/**
	 * The unit text for a metric, by key OR by label.
	 *
	 * By label too because six_metrics rows are keyed on the advisor's typed
	 * label, and the rows a measurement wrote carry the catalog's own label —
	 * so "Organic Visitors" in that table can still be shown with its unit
	 * instead of as a bare number.
	 */
	public static function unit_for( $key_or_label ) {
		$needle = strtolower( trim( (string) $key_or_label ) );
		if ( $needle === '' ) return '';
		foreach ( self::catalog() as $svc ) {
			foreach ( $svc['metrics'] as $key => $m ) {
				if ( $needle === $key || $needle === strtolower( $m['label'] ) ) return $m['unit'] ?? '';
			}
		}
		foreach ( self::extra_metrics() as $key => $m ) {
			if ( $needle === $key || $needle === strtolower( $m['label'] ) ) return $m['unit'] ?? '';
		}
		return '';
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
	 * The raw numeric value of every metric for this client, before any
	 * formatting — what a snapshot stores and what a comparison does
	 * arithmetic on. Same resolution order as the cards (live → advisor →
	 * estimate) and reports which tier each value came from, so a comparison
	 * can tell a measured figure from a benchmark.
	 *
	 * @return array{values:array<string,float>,sources:array<string,string>}
	 */
	public static function raw( $user_id, array $ctx ) {
		$slugs   = self::card_services( $ctx['slugs'] ?? array() );
		$budgets = $ctx['budgets'] ?? array();
		$kpi     = $ctx['kpi']     ?? array();
		$cpc     = $ctx['cpc']     ?? 0;
		$catalog = self::catalog();

		$values = array(); $sources = array();
		foreach ( $slugs as $slug ) {
			$live = self::live( $slug, $user_id );

			foreach ( array_keys( $catalog[ $slug ]['metrics'] ) as $key ) {
				// Measured, or entered by a person, or absent. There is no
				// third source any more: an industry benchmark sitting in the
				// same field as a measurement is indistinguishable from one.
				if ( $live !== null && isset( $live[ $key ] ) && $live[ $key ] !== '' ) {
					$raw = $live[ $key ]; $src = 'live';
				} elseif ( isset( $kpi[ $key ] ) && $kpi[ $key ]->kpi_value !== '' ) {
					$raw = preg_replace( '/[^0-9.]/', '', (string) $kpi[ $key ]->kpi_value ); $src = 'advisor';
				} else {
					continue;
				}
				if ( ! is_numeric( $raw ) ) continue;   // free-form advisor text isn't comparable
				$values[ $key ]  = (float) $raw;
				$sources[ $key ] = $src;
			}
		}
		return array( 'values' => $values, 'sources' => $sources );
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
	/**
	 * Which four metrics the advisor chose for this client's overview.
	 *
	 * Stored as `service:key` so the same key under two services stays
	 * distinct. An empty list means nobody has chosen, and the first few
	 * metrics of each active service are used — which is what the dashboard
	 * always did.
	 */
	public static function chosen_cards( $user_id ) {
		$raw = get_user_meta( (int) $user_id, 'six_overview_cards', true );
		if ( ! is_array( $raw ) ) $raw = $raw ? (array) maybe_unserialize( $raw ) : array();
		$out = array();
		foreach ( $raw as $entry ) {
			$entry = (string) $entry;
			if ( strpos( $entry, ':' ) === false ) continue;
			list( $slug, $key ) = explode( ':', $entry, 2 );
			$slug = sanitize_key( $slug ); $key = sanitize_key( $key );
			if ( $slug && $key ) $out[] = array( 'slug' => $slug, 'key' => $key );
		}
		return array_slice( $out, 0, 4 );
	}

	/** Every metric an advisor could put on the overview, for the picker. */
	public static function card_choices( array $slugs ) {
		$catalog = self::catalog();
		$out = array();
		foreach ( self::card_services( $slugs ) as $slug ) {
			if ( empty( $catalog[ $slug ] ) ) continue;
			foreach ( $catalog[ $slug ]['metrics'] as $key => $meta ) {
				$out[] = array(
					'id'      => $slug . ':' . $key,
					'slug'    => $slug,
					'key'     => $key,
					'label'   => $meta['label'],
					'unit'    => $meta['unit'] ?? '',
					'service' => $catalog[ $slug ]['label'],
				);
			}
		}
		return $out;
	}

	public static function cards( $user_id, array $ctx ) {
		$slugs   = self::card_services( $ctx['slugs'] ?? array() );
		$kpi     = $ctx['kpi']     ?? array();
		$catalog = self::catalog();

		if ( ! $slugs ) return array();

		// What the advisor picked, if they picked. Otherwise the old split:
		// one service fills all four slots, two get two each, three get 2/1/1.
		$wanted = array();
		foreach ( self::chosen_cards( $user_id ) as $c ) {
			if ( in_array( $c['slug'], $slugs, true ) && isset( $catalog[ $c['slug'] ]['metrics'][ $c['key'] ] ) ) {
				$wanted[] = $c;
			}
		}
		if ( ! $wanted ) {
			$n     = count( $slugs );
			$base  = intdiv( 4, $n );
			$extra = 4 % $n;
			foreach ( $slugs as $i => $slug ) {
				$take = array_slice( array_keys( $catalog[ $slug ]['metrics'] ), 0, $base + ( $i < $extra ? 1 : 0 ) );
				foreach ( $take as $key ) $wanted[] = array( 'slug' => $slug, 'key' => $key );
			}
		}

		$live_cache = array();
		$cards = array();
		foreach ( $wanted as $c ) {
			$slug = $c['slug']; $key = $c['key'];
			$svc  = $catalog[ $slug ];
			$meta = $svc['metrics'][ $key ] ?? null;
			if ( ! $meta ) continue;

			if ( ! array_key_exists( $slug, $live_cache ) ) $live_cache[ $slug ] = self::live( $slug, $user_id );
			$live = $live_cache[ $slug ];

			/* Two sources, and no third.
			 *
			 * There used to be a fallback to an industry benchmark, which is
			 * how a client with a connected Google Business Profile was shown
			 * "600 map views per month" — a number from a table, sitting in
			 * the same card, in the same type, as the figures their own
			 * accounts report. A client cannot tell those apart, and the one
			 * they can check is the one that makes the other a lie.
			 *
			 * So: their own connected account, or a figure their advisor
			 * entered, or nothing. Nothing is a card that says it is not
			 * connected yet, which is true and is also an instruction. */
			if ( $live !== null && isset( $live[ $key ] ) && $live[ $key ] !== '' ) {
				$raw = $live[ $key ];
				$src = 'live';
				$sub = self::connectors()[ $slug ]['label'] . ' · live';
			} elseif ( isset( $kpi[ $key ] ) && $kpi[ $key ]->kpi_value !== '' ) {
				$raw = $kpi[ $key ]->kpi_value;
				$src = 'advisor';
				$sub = 'Confirmed by your advisor';
			} else {
				$raw = '';
				$src = 'none';
				$sub = self::connectors()[ $slug ]['connected'] ?? false
					? 'Nothing reported for this yet'
					: 'Connect ' . ( self::connectors()[ $slug ]['label'] ?? $svc['label'] ) . ' to measure this';
			}

			$cards[] = array(
				'key'     => $key,
				'service' => $slug,
				'svc_label' => $svc['label'],
				'label'   => $meta['label'],
				'value'   => self::format( $raw, $meta ),
				'unit'    => $meta['unit'] ?? '',
				'sub'     => $sub,
				'source'  => $src,
				'accent'  => $svc['accent'],
				'prev'    => isset( $kpi[ $key ] ) ? ( $kpi[ $key ]->kpi_prev ?: '' ) : '',
			);
		}

		return array_slice( $cards, 0, 4 );
	}

	/**
	 * Render a raw metric value with its prefix/suffix, or an em dash when
	 * there is no value at all.
	 *
	 * Zero is a value. It used to render as an em dash, which the card then
	 * captioned "No data yet" — so a client with nought conversions this
	 * month was told we had not measured, and an advisor who typed 0 watched
	 * it vanish. Only an empty string and null are missing. A negative figure
	 * is shown as it is: it is either real, or a data problem somebody needs
	 * to see rather than one the dashboard quietly hides.
	 */
	public static function format( $raw, array $meta ) {
		if ( $raw === '' || $raw === null ) return '—';
		if ( is_numeric( $raw ) ) {
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
	/**
	 * A trailing seven-day mean, so the line reads as a trend and not as a
	 * week's worth of weekday-and-weekend.
	 *
	 * Trailing rather than centred: a centred window needs days that have
	 * not happened yet, and filling them in is how a chart ends on a figure
	 * nobody measured. The first few days average over what exists, so the
	 * line starts slightly rougher than it ends, which is honest.
	 */
	public static function weekly_mean( array $values, $window = 7 ) {
		$out = array();
		$n   = count( $values );
		for ( $i = 0; $i < $n; $i++ ) {
			$from  = max( 0, $i - $window + 1 );
			$slice = array_slice( $values, $from, $i - $from + 1 );
			$slice = array_filter( $slice, function ( $v ) { return $v !== null && $v !== ''; } );
			$out[] = $slice ? (float) ( array_sum( $slice ) / count( $slice ) ) : 0.0;
		}
		return $out;
	}

	/**
	 * Which point in the window is the day the work started.
	 *
	 * Three cases, and the middle one is the one that used to be wrong. The
	 * day falls inside the window: split there. It is before the window, so
	 * every day on screen is already "with us": split at the first point,
	 * and both lines run the whole width. There is no start date at all:
	 * null, and the chart draws one line with no comparison, because there
	 * is nothing honest to compare against.
	 *
	 * @return int|null index into $points
	 */
	public static function split_index( array $points, $started_on ) {
		$started_on = trim( (string) $started_on );
		if ( $started_on === '' || ! $points ) return null;
		$start = substr( $started_on, 0, 10 );

		$first = $points[0]['date'] ?? '';
		$last  = $points[ count( $points ) - 1 ]['date'] ?? '';
		if ( $last && $start > $last ) return null;        // starts after the window
		if ( $first && $start <= $first ) return 0;        // started before it

		foreach ( $points as $i => $pt ) {
			if ( ( $pt['date'] ?? '' ) >= $start ) return $i;
		}
		return null;
	}

	public static function visitor_series( $user_id, array $ctx ) {
		$slugs   = self::card_services( $ctx['slugs'] ?? array() );
		$budgets = $ctx['budgets'] ?? array();
		$kpi     = $ctx['kpi']     ?? array();
		$cpc     = $ctx['cpc']     ?? 0;

		// Combined monthly visitors across their services. Measured only —
		// there is no benchmark fallback, because a modelled figure and a
		// measured one looked identical on the card and only one of them
		// could be checked.
		$current = 0;
		$is_live = $slugs ? true : false;
		foreach ( $slugs as $slug ) {
			$live = self::live( $slug, $user_id );
			if ( $live === null ) { $is_live = false; continue; }

			// Each service contributes the visitor-shaped metric it owns.
			foreach ( array( 'organic_visitors', 'paid_clicks', 'gmb_views' ) as $vk ) {
				if ( isset( $live[ $vk ] ) && is_numeric( $live[ $vk ] ) ) $current += (int) $live[ $vk ];
			}
		}

		// An advisor-confirmed total always wins over our own arithmetic.
		if ( isset( $kpi['total_visitors'] ) && $kpi['total_visitors']->kpi_value !== '' ) {
			$adv = (int) preg_replace( '/[^0-9]/', '', $kpi['total_visitors']->kpi_value );
			if ( $adv > 0 ) { $current = $adv; $is_live = true; }
		}

		// Where they were before us, only where somebody actually recorded
		// it. It used to default to 35% of today's traffic, which is a number
		// with no source that every "+N vs before" on the dashboard was then
		// computed against.
		$baseline = 0;
		if ( isset( $kpi['visitors_baseline'] ) && $kpi['visitors_baseline']->kpi_value !== '' ) {
			$baseline = (int) preg_replace( '/[^0-9]/', '', $kpi['visitors_baseline']->kpi_value );
		}

		$points = array();

		/* The daily series first, before anything is given up on.
		 *
		 * This used to sit *after* an early return that fired when no
		 * connector reported a visitor count — so a client whose history had
		 * been backfilled, whose start date was set and whose numbers were
		 * synced still got no chart at all, because `live()` returned null
		 * and the function gave up three lines before reading the table that
		 * had the answer in it. Backfilled readings are the best data we
		 * have; nothing should be decided before they are looked at.
		 */
		// ── Day by day, where the daily sync has readings ────────────────
		//
		// This is the honest version of the claim the card makes, and the
		// reason the chart used to show two straight lines: the weekly
		// snapshot table holds at most one row a week, so a new client had
		// two points and the "without us" line was a constant drawn at the
		// baseline. Neither line could move.
		//
		// Now the measured line is the actual daily figure from Search
		// Console or GA4, and the "without us" line is their own pre-
		// engagement trend fitted and extended — not an assumption that they
		// would have stayed exactly still.
		$daily = class_exists( 'Six_Tracking' ) ? Six_Tracking::graph_series( $user_id, '', 180 ) : array( 'has' => false );
		if ( ! empty( $daily['has'] ) && count( $daily['dates'] ) >= 7 ) {
			$has_proj = ! empty( $daily['projected'] );

			// Smoothed over seven days before it is drawn. The raw daily
			// figure for a small site swings by half its value between a
			// Tuesday and a Sunday, and a chart of that noise reads as
			// instability rather than as a week with a weekend in it. The
			// sums below still use the raw numbers — this is how the line is
			// drawn, not what the figures are.
			$smooth_after  = self::weekly_mean( $daily['actual'] );
			$smooth_before = $has_proj ? self::weekly_mean( $daily['projected'] ) : array();

			foreach ( $daily['dates'] as $i => $d ) {
				$after  = (float) ( $smooth_after[ $i ] ?? 0 );
				$before = $has_proj ? (float) ( $smooth_before[ $i ] ?? 0 ) : null;
				$points[] = array(
					'date'   => $d,
					'label'  => gmdate( 'j M', strtotime( $d ) ),
					'before' => $before === null ? null : (int) round( $before ),
					'after'  => (int) round( $after ),
					'raw'    => (int) round( (float) ( $daily['actual'][ $i ] ?? 0 ) ),
				);
			}

			// The headline stays a monthly figure, because that is what a
			// client recognises — but it is now the trailing 30 days of real
			// daily readings rather than a single scraped total, and the
			// baseline is the same window off the fitted line, so the "+N vs
			// before" compares like with like.
			$tail       = array_slice( $points, -30 );
			$current    = (int) round( array_sum( array_column( $tail, 'raw' ) ) );
			$base_tail  = $has_proj ? array_sum( array_column( $tail, 'before' ) ) : 0;
			$baseline   = $has_proj ? (int) round( $base_tail ) : 0;

			// The chart shows the last 30 days only. Everything before that
			// was fitted on, not drawn — a client looking at this month does
			// not want six months of axis to read past first.
			$window = $tail;
			$start  = class_exists( 'Six_Tracking' ) ? Six_Tracking::engagement_start( $user_id ) : '';
			$split  = self::split_index( $window, $start );

			// Before the work started there is one line, because there was
			// nothing to compare against yet. The two lines exist only from
			// the day the service was switched on.
			//
			// There used to be an anchor here that shifted the comparison
			// line up to meet the measured figure on the split day, so the
			// two left from exactly the same point. That made sense when the
			// line was a fitted slope. It does not now: the line is the level
			// the client was holding before we started, and sliding it to
			// match one particular day's reading stops it being that level.
			//
			// A small step at the split is the truth — "you were averaging
			// 180 a day, and on the day we began you happened to be at 195"
			// — and erasing it would be drawing something that did not
			// happen on the most prominent claim the portal makes.
			foreach ( $window as $i => $pt ) {
				if ( $split === null || $i < $split ) {
					$window[ $i ]['before'] = null;
				}
				unset( $window[ $i ]['raw'] );
			}
			$points = array_values( $window );

			$metric_label = array(
				'organic_sessions' => 'organic sessions',
				'sessions'         => 'sessions',
				'gsc_clicks'       => 'clicks from Google',
			);

			return array(
				'has'            => true,
				'source'         => 'measured',
				'daily'          => true,
				'baseline_mo'    => $baseline,
				'current_mo'     => $current,
				'last_month'     => self::last_calendar_month( $points ),
				'target'         => self::visitor_target( $user_id ),
				'points'         => $points,
				'split_at'       => $split,
				'split_on'       => $split === null ? '' : ( $points[ $split ]['date'] ?? '' ),
				'started_on'     => $start,
				'window_days'    => count( $points ),
				'projected_from' => $has_proj && $split !== null ? 'fit' : 'none',
				// Which of the three requirements is missing, so the caption
				// can name it rather than saying the line is simply absent.
				'blocker'        => $split === null && $has_proj ? 'no_start' : (string) ( $daily['blocker'] ?? '' ),
				'metric'         => $metric_label[ $daily['metric'] ] ?? $daily['metric'],
				'measured_from'  => $daily['source'] ?? '',
				'fitted_on'      => (int) ( $daily['fitted_on'] ?? 0 ),
				// The flat level the dashed line holds, so the caption can
				// state it rather than describing a slope that is no longer
				// how the line is built.
				'hold_level'     => (int) round( (float) ( $daily['level'] ?? 0 ) ),
				// Which tool the number came from, decided by the metric that
				// won rather than by a source column that may say something
				// else. The caption names it, and a caption that names the
				// wrong tool is worse than one that names none.
				'measured_tool'  => in_array( $daily['metric'] ?? '', array( 'organic_sessions', 'sessions' ), true )
					? 'Google Analytics' : 'Google Search Console',
				'note'           => (string) ( $daily['note'] ?? '' ),
			);
		}


		if ( $current <= 0 ) {
			return array(
				'has'    => false,
				'source' => 'none',
				'baseline_mo' => 0, 'current_mo' => 0, 'points' => array(),
				'note'   => 'Nothing is reporting visitors yet. Connect Google Analytics or Search Console '
				          . 'under Data Sources, or your advisor can enter the figure by hand.',
			);
		}

		// ── Real captured history, when we have any ──────────────────────
		// Snapshots are the actual measured figure on an actual date. Where
		// two or more exist they are plotted directly, because a real series
		// beats a modelled one and the dates on the axis are then true.
		$measured = array();
		if ( class_exists( 'Six_Performance' ) ) {
			$hist = Six_Performance::history( $user_id, 26 );
			foreach ( array_reverse( $hist ) as $h ) {     // oldest first
				$v = 0;
				foreach ( array( 'organic_visitors', 'paid_clicks', 'gmb_views' ) as $vk ) {
					if ( ! empty( $h['values'][ $vk ] ) ) $v += (int) $h['values'][ $vk ];
				}
				if ( $v > 0 ) $measured[] = array( 'date' => substr( $h['captured_at'], 0, 10 ), 'value' => $v );
			}
		}

		$points = array();
		$week   = 7 * DAY_IN_SECONDS;

		if ( count( $measured ) >= 2 ) {
			// Real snapshots on real dates. The "without us" line used to be
			// drawn flat at the baseline here, which asserted that the client
			// would not have moved at all on their own — a claim nobody
			// measured. It is drawn only where an advisor recorded where
			// they stood before we started.
			foreach ( $measured as $m ) {
				$points[] = array(
					'date'   => $m['date'],
					'label'  => gmdate( 'j M', strtotime( $m['date'] ) ),
					'before' => $baseline > 0 ? $baseline : null,
					'after'  => (int) $m['value'],
				);
			}
			$source  = 'measured';
			$current = (int) end( $measured )['value'];
		} else {
			// Fewer than two readings. There used to be a modelled ramp here:
			// twelve weeks of a sigmoid curve from an invented baseline up to
			// today's figure, on real dates, drawn in the same ink as a
			// measurement. It is gone. A chart with nothing behind it is not
			// drawn at all, and the panel says what to connect.
			return array(
				'has'    => false,
				'source' => 'none',
				'baseline_mo' => 0, 'current_mo' => $current, 'points' => array(),
				'note'   => 'Only one reading so far. The chart appears once there are at least two — '
				          . 'daily once Google Analytics or Search Console is connected under Data Sources.',
			);
		}

		return array(
			'has'            => true,
			'source'         => $source,
			'daily'          => false,
			'baseline_mo'    => $baseline,
			'current_mo'     => $current,
			'last_month'     => self::last_calendar_month( array_map( function ( $m ) {
				return array( 'date' => $m['date'], 'after' => $m['value'] );
			}, $measured ) ),
			'target'         => self::visitor_target( $user_id ),
			'points'         => $points,
			// Flat, and labelled as such. The old chart drew this same line
			// without saying it was an assumption rather than a measurement.
			'projected_from' => $measured ? 'baseline' : 'model',
		);
	}

	/**
	 * Last full calendar month's figure — what a client recognises.
	 *
	 * "Last month" beats "the trailing 30 days" in a conversation, so the card
	 * shows both. Daily points are summed; weekly snapshots are averaged,
	 * because each one is already a monthly rate rather than a day's count.
	 *
	 * @param array $points Each: date (Y-m-d), after.
	 */
	protected static function last_calendar_month( array $points, $daily = true ) {
		if ( ! $points ) return null;
		$from = gmdate( 'Y-m-01', strtotime( 'first day of last month' ) );
		$to   = gmdate( 'Y-m-t',  strtotime( 'last day of last month' ) );
		$vals = array();
		foreach ( $points as $p ) {
			$d = $p['date'] ?? '';
			if ( $d >= $from && $d <= $to ) $vals[] = (float) ( $p['after'] ?? 0 );
		}
		if ( ! $vals ) return null;
		// A daily series sums to a month; a series of monthly rates averages.
		$is_daily = count( $vals ) > 6;
		return array(
			'value' => (int) round( $is_daily ? array_sum( $vals ) : array_sum( $vals ) / count( $vals ) ),
			'label' => gmdate( 'F Y', strtotime( $from ) ),
		);
	}

	/**
	 * Where the strategy says visitors should get to.
	 *
	 * Written by save_strategy_projections when a strategy is approved, so
	 * this is a target somebody committed to in writing rather than a number
	 * generated here. Null until one exists, and the chart omits it.
	 */
	protected static function visitor_target( $user_id ) {
		if ( ! class_exists( 'Six_Performance' ) ) return null;
		$p = Six_Performance::projection_due( $user_id, 'organic_visitors', gmdate( 'Y-m-d', strtotime( '+10 years' ) ) );
		if ( ! $p || (float) $p->projected_value <= 0 ) return null;
		return array(
			'value' => (int) round( (float) $p->projected_value ),
			'date'  => (string) $p->target_date,
			'label' => gmdate( 'M Y', strtotime( (string) $p->target_date ) ),
		);
	}
}
