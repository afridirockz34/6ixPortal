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
			$est  = self::estimates( $slug, $budgets[ $slug ] ?? 0, $cpc );

			foreach ( array_keys( $catalog[ $slug ]['metrics'] ) as $key ) {
				if ( $live !== null && isset( $live[ $key ] ) && $live[ $key ] !== '' ) {
					$raw = $live[ $key ]; $src = 'live';
				} elseif ( isset( $kpi[ $key ] ) && $kpi[ $key ]->kpi_value !== '' ) {
					$raw = preg_replace( '/[^0-9.]/', '', (string) $kpi[ $key ]->kpi_value ); $src = 'advisor';
				} else {
					$raw = $est[ $key ] ?? 0; $src = 'estimate';
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
					'unit'    => $meta['unit'] ?? '',
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
			// The two lines have to leave from the same point, or the chart
			// shows a step at the split that is fit error rather than
			// anything that happened. The claim being made is "your own
			// trend, carried on from the day we started", so the fitted line
			// is anchored to the measured figure on that day and its slope —
			// which is the whole content of the claim — is untouched.
			$anchor = 0.0;
			if ( $split !== null && $has_proj
			     && isset( $window[ $split ]['before'], $window[ $split ]['after'] ) ) {
				$anchor = (float) $window[ $split ]['after'] - (float) $window[ $split ]['before'];
			}

			foreach ( $window as $i => $pt ) {
				if ( $split === null || $i < $split ) {
					$window[ $i ]['before'] = null;
				} elseif ( $anchor ) {
					$window[ $i ]['before'] = max( 0, (int) round( $pt['before'] + $anchor ) );
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
				'metric'         => $metric_label[ $daily['metric'] ] ?? $daily['metric'],
				'measured_from'  => $daily['source'] ?? '',
				'fitted_on'      => (int) ( $daily['fitted_on'] ?? 0 ),
				'note'           => (string) ( $daily['note'] ?? '' ),
			);
		}

		if ( count( $measured ) >= 2 ) {
			// What actually happened. The "without us" line stays flat at the
			// baseline, because that is the claim being made: this is where
			// they were when we started and they have not moved since on
			// their own.
			foreach ( $measured as $m ) {
				$points[] = array(
					'date'   => $m['date'],
					'label'  => gmdate( 'j M', strtotime( $m['date'] ) ),
					'before' => $baseline,
					'after'  => max( $baseline, $m['value'] ),
				);
			}
			$source  = 'measured';
			$current = (int) end( $measured )['value'];
		} else {
			// No history yet. A modelled ramp from the baseline to today's
			// figure, on real weekly dates ending this week — the axis said
			// "Wk 1..Wk 12", which told a reader nothing about when any of it
			// happened.
			$raw = array();
			for ( $w = 1; $w <= 12; $w++ ) $raw[ $w ] = 1 / ( 1 + exp( -0.7 * ( $w - 6 ) ) );
			$lo = min( $raw ); $hi = max( $raw );
			$start = time() - 11 * $week;

			for ( $w = 1; $w <= 12; $w++ ) {
				$f      = ( $raw[ $w ] - $lo ) / max( $hi - $lo, 0.0001 );   // 0..1
				$before = (int) round( $baseline * ( 1 - 0.02 * abs( sin( $w / 2 ) ) ) );
				// The with-us line can never sit below the without-us line —
				// the ramp starts at the baseline, and the drift above only
				// moves downward, so an early-week wobble can't read as us
				// having made their traffic worse.
				$after  = (int) round( $baseline + ( $current - $baseline ) * $f );
				$ts     = $start + ( $w - 1 ) * $week;
				$points[] = array(
					'date'   => gmdate( 'Y-m-d', $ts ),
					'label'  => gmdate( 'j M', $ts ),
					'before' => $before,
					'after'  => max( $before, $after ),
				);
			}
			$source = $is_live ? 'live' : 'projected';
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
