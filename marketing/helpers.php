<?php
/**
 * 6ix Developers — Marketing helpers
 *
 * Thin wrappers so templates read cleanly and render correctly even before
 * ACF is installed or fields are filled (progressive enhancement: every call
 * takes a sensible default that mirrors the current site copy).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Get an editable field for a marketing template, with a fallback default.
 *
 * Two independent storage tiers are checked, in order, so pages are
 * editable whether or not ACF is installed:
 *   1. Plain post meta, key 'six_field_{$name}', on the resolved post — this
 *      is what the native meta-box editors (e.g. the Home page's "Homepage
 *      Content" box) save to, so "Edit" in wp-admin actually changes the
 *      live page. Array values are stored as JSON and decoded automatically.
 *      Checked FIRST: this is the system every current editor screen writes
 *      to, and it must always win once someone has used it.
 *   2. ACF (only if the plugin is active AND nothing was found in tier 1) —
 *      kept as a fallback for any field that has a real ACF field group
 *      configured but no plain-postmeta editor of its own yet.
 *
 * Historically ACF was checked first, which caused a real bug: this theme
 * also registers a code-side ACF field group for the Home page
 * (marketing/acf-fields.php) whose field names happen to match the plain
 * meta-box system 1:1. Whenever ACF was active, any value it returned —
 * including stale or blank values from a meta box the user never
 * consciously used — silently won over correctly-saved edits, making saves
 * from the "Homepage Content" meta box appear to do nothing. Checking
 * plain postmeta first fixes that at the root, regardless of whether ACF
 * is active.
 *
 * @param string    $name    Field name.
 * @param mixed     $default Value to use when nothing is set anywhere.
 * @param mixed     $post_id Post ID to check, or 'option' for a site-wide
 *                           value — a real WP option (see mk_update_opt()),
 *                           not tied to whichever post/page happens to be
 *                           queried when this is called.
 */
function mk_field( $name, $default = '', $post_id = false ) {
    // Site-wide ('option') fields are a different storage shape entirely —
    // there's no single post to attach postmeta to. Previously this branch
    // fell through to get_queried_object_id(), which meant a "site-wide"
    // value actually varied per page (whatever page happened to be
    // rendering when it was read) — a real bug, not just imprecise: it made
    // this tier unusable for its stated purpose. mk_opt()/mk_update_opt()
    // are the only intended callers of this branch.
    if ( $post_id === 'option' ) {
        $raw = get_option( 'six_field_option_' . $name, '' );
        if ( $raw !== '' && $raw !== false ) {
            if ( is_string( $raw ) && strlen( $raw ) && ( $raw[0] === '[' || $raw[0] === '{' ) ) {
                $decoded = json_decode( $raw, true );
                if ( json_last_error() === JSON_ERROR_NONE ) {
                    if ( ! ( is_array( $decoded ) && empty( $decoded ) ) ) return $decoded;
                } else {
                    return $raw;
                }
            } else {
                return $raw;
            }
        }
        if ( function_exists( 'get_field' ) ) {
            $val = get_field( $name, 'option' );
            if ( ! ( $val === null || $val === '' || $val === false || ( is_array( $val ) && empty( $val ) ) ) ) {
                return $val;
            }
        }
        return $default;
    }

    $pid = ( $post_id !== false ) ? intval( $post_id ) : get_queried_object_id();
    if ( $pid ) {
        $raw = get_post_meta( $pid, 'six_field_' . $name, true );
        if ( $raw !== '' && $raw !== false ) {
            // Arrays (repeaters) are stored as a JSON string.
            if ( is_string( $raw ) && strlen( $raw ) && ( $raw[0] === '[' || $raw[0] === '{' ) ) {
                $decoded = json_decode( $raw, true );
                if ( json_last_error() === JSON_ERROR_NONE ) {
                    // A saved-but-empty array ('[]') is never useful to a
                    // visitor — fall through instead of rendering a blank
                    // section, whatever caused it to end up empty (the save
                    // handlers also guard against ever writing this in the
                    // first place; this is a second, display-layer net).
                    if ( ! ( is_array( $decoded ) && empty( $decoded ) ) ) return $decoded;
                } else {
                    return $raw;
                }
            } else {
                return $raw;
            }
        }
    }

    if ( function_exists( 'get_field' ) ) {
        $val = $post_id !== false ? get_field( $name, $post_id ) : get_field( $name );
        if ( ! ( $val === null || $val === '' || $val === false || ( is_array( $val ) && empty( $val ) ) ) ) {
            return $val;
        }
    }

    return $default;
}

/** Set a site-wide field written by mk_opt()/mk_field(..., 'option'). Arrays are JSON-encoded. */
function mk_update_opt( $name, $value ) {
    if ( is_array( $value ) ) $value = wp_json_encode( $value );
    return update_option( 'six_field_option_' . $name, $value );
}

/** Site-wide option field (brand, nav, footer) with fallback. */
function mk_opt( $name, $default = '' ) {
    return mk_field( $name, $default, 'option' );
}

/** Echo an escaped field value. */
function mk_e( $name, $default = '' ) { echo esc_html( mk_field( $name, $default ) ); }

/** Portal / get-started URL (single source of truth). */
function mk_portal_url() { return home_url( '/get-started/' ); }

/**
 * Render the reusable "Marketing OS" portal CTA band. Dropped into every page
 * at a consistent scroll position to push visitors into the portal.
 * Pass per-page overrides so a service page can tease its own insight.
 */
function mk_portal_band( $args = array() ) {
    $a = wp_parse_args( $args, array(
        'eyebrow'  => mk_opt( 'portal_band_eyebrow', 'Your Marketing OS' ),
        'heading'  => mk_opt( 'portal_band_heading', 'Get to know the marketing side of your business.' ),
        'text'     => mk_opt( 'portal_band_text', 'See exactly how you stack up against your competitors, where your leads come from, and what to fix next — in one live dashboard built for your business.' ),
        'features' => mk_opt( 'portal_band_features', array(
            array( 'feature' => 'Compare yourself to local competitors' ),
            array( 'feature' => 'A live Business Growth Score' ),
            array( 'feature' => 'A tailored 60-day growth plan' ),
        ) ),
        'cta'      => mk_opt( 'portal_band_cta', 'Get started free' ),
        'score'    => 78,
    ) );
    $check = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
    ?>
    <section class="mk-section mk-section-sm">
      <div class="mk-wrap">
        <div class="mk-portal-band">
          <div class="mk-portal-grid">
            <div>
              <span class="mk-eyebrow"><?php echo esc_html( $a['eyebrow'] ); ?></span>
              <h2 class="mk-grad-text"><?php echo esc_html( $a['heading'] ); ?></h2>
              <p><?php echo esc_html( $a['text'] ); ?></p>
              <ul class="mk-portal-features">
                <?php foreach ( (array) $a['features'] as $f ) :
                    $label = is_array( $f ) ? ( $f['feature'] ?? '' ) : $f;
                    if ( ! $label ) continue; ?>
                <li><?php echo $check; ?><span><?php echo esc_html( $label ); ?></span></li>
                <?php endforeach; ?>
              </ul>
              <a class="mk-btn mk-btn-primary mk-btn-lg" href="<?php echo esc_url( mk_portal_url() ); ?>">
                <?php echo esc_html( $a['cta'] ); ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
              </a>
            </div>
            <div class="mk-portal-viz" aria-hidden="true">
              <div class="mk-portal-ring">
                <div class="mk-portal-ring-num mk-grad-text"><?php echo intval( $a['score'] ); ?></div>
                <div style="font-size:.85rem;color:var(--mk-t3)">Business<br>Growth Score</div>
              </div>
              <?php
              $rows = array(
                  array( 'Getting found on Google', 72 ),
                  array( 'Ad performance', 84 ),
                  array( 'Local visibility', 66 ),
                  array( 'Website experience', 80 ),
              );
              foreach ( $rows as $r ) : ?>
              <div style="margin-top:14px">
                <div style="display:flex;justify-content:space-between;font-size:.82rem;color:var(--mk-t2)">
                  <span><?php echo esc_html( $r[0] ); ?></span><span><?php echo intval( $r[1] ); ?>%</span>
                </div>
                <div class="mk-portal-bar"><span style="width:<?php echo intval( $r[1] ); ?>%"></span></div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </section>
    <?php
}

/**
 * Raw path data for the marketing icon set (name => inner SVG markup), single
 * source of truth for both mk_icon() (front-end render) and any admin UI that
 * needs the same icons available for picking (e.g. the Case Study repeater
 * fields) — kept in one place so the two never drift apart.
 */
function mk_icon_list() {
    return array(
        'website'  => '<path d="M2 3h20v14H2z"/><path d="M8 21h8M12 17v4"/>',
        'ads'      => '<path d="M3 11l18-5v12L3 13v-2z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
        'seo'      => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
        'social'   => '<path d="M18 8a3 3 0 1 0-2.8-4M6 12a3 3 0 1 0 0 0zM18 16a3 3 0 1 0 0 0z"/><path d="M8.6 10.7l6.8-3.4M8.6 13.3l6.8 3.4"/>',
        'spark'    => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4"/><path d="M12 8l1.5 2.5L16 12l-2.5 1.5L12 16l-1.5-2.5L8 12l2.5-1.5z"/>',
        'shield'   => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/>',
        'chart'    => '<path d="M3 3v18h18"/><path d="M7 14l3-3 3 3 5-6"/>',
        'target'   => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
        // Extended, distinct set — so sections don't reuse the same glyph.
        'search'   => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/><path d="M8 11h6M11 8v6"/>',
        'trending' => '<polyline points="3 17 9 11 13 15 21 7"/><polyline points="15 7 21 7 21 13"/>',
        'gauge'    => '<path d="M12 13l4-4"/><path d="M4 18a8 8 0 1 1 16 0"/><circle cx="12" cy="18" r="1"/>',
        'award'    => '<circle cx="12" cy="9" r="6"/><polyline points="8.2 13.5 7 22 12 19 17 22 15.8 13.5"/>',
        'users'    => '<path d="M17 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9.5" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'pen'      => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'layers'   => '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 12 12 17 22 12"/><polyline points="2 17 12 22 22 17"/>',
        'rocket'   => '<path d="M5 13c-1.5.7-3 2.5-3 6 3.5 0 5.3-1.5 6-3"/><path d="M15 5s5 0 5 5c0 0-3 8-9 11 0 0-3-2-4-4 3-6 8-12 8-12z"/><circle cx="14.5" cy="9.5" r="1.5"/>',
        'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18 14 14 0 0 1 0-18z"/>',
        'bulb'     => '<path d="M9 18h6"/><path d="M10 22h4"/><path d="M8 14a5 5 0 1 1 8 0c-.7 1-1.5 1.6-1.5 3h-5c0-1.4-.8-2-1.5-3z"/>',
        'clock'    => '<circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 16 14"/>',
        'link'     => '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7L12 18"/>',
    );
}

/** Small inline SVG icon set for marketing cards (no external deps). */
function mk_icon( $name ) {
    $icons = mk_icon_list();
    $p = $icons[ $name ] ?? $icons['spark'];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">' . $p . '</svg>';
}

/**
 * Calls to action written for the service whose page they sit on.
 *
 * Every service page used to close with the same "Get your free
 * consultation" — a generic ask that gives the reader no reason to click
 * from one page rather than another. These are specific to the service and
 * all point at the onboarding form, which is the thing that actually
 * qualifies a lead: a few questions, then we show them something real about
 * their own site.
 *
 * Three shapes, because the same ask repeated three times down one page
 * reads as a single banner nobody clicks:
 *   hero  — the opening ask, benefit-first. A label only: the hero already
 *           carries the page's lead paragraph, and a third line of body
 *           text under the buttons was unreadable over the photo heroes
 *           and redundant with the band below, which says the same thing
 *           where there is room for it.
 *   band  — mid-page, framed as "see where you stand"
 *   final — the closing ask, the most concrete of the three
 *
 * @param string $slug The page slug.
 * @return array{hero:array{label:string},band:array{heading:string,text:string,cta:string},final:array{heading:string,cta:string}}
 */
function six_service_ctas( $slug ) {
    $map = array(
        'seo-agency-toronto' => array(
            'hero'  => array(
                'label' => 'Show me what I’m missing',
            ),
            'band'  => array(
                'heading' => 'Find out where your search traffic is going instead.',
                'text'    => 'A few questions about your business and your market, and we’ll show you what your site ranks for today, who is above you, and how many visitors a month that gap is worth.',
                'cta'     => 'See where you stand',
            ),
            'final' => array(
                'heading' => 'Want to see what your site ranks for right now?',
                'cta'     => 'Get your free SEO audit',
            ),
        ),
        'ppc-google-ads-management-toronto' => array(
            'hero'  => array(
                'label' => 'Audit my Google Ads',
            ),
            'band'  => array(
                'heading' => 'Find out what your ads are really costing per lead.',
                'text'    => 'Tell us what you sell and where, and we’ll show you the real cost per click in your market, what your budget should be producing, and where spend is leaking.',
                'cta'     => 'Check my ad spend',
            ),
            'final' => array(
                'heading' => 'Running ads already? See what they should be costing you.',
                'cta'     => 'Get your free Google Ads audit',
            ),
        ),
        'ppc-agency-toronto' => array(
            'hero'  => array(
                'label' => 'Check my cost per lead',
            ),
            'band'  => array(
                'heading' => 'See what a lead should cost you in your market.',
                'text'    => 'Real click costs for your services and your cities, against what you are paying now.',
                'cta'     => 'Check my cost per lead',
            ),
            'final' => array(
                'heading' => 'Know what you should be paying for a lead?',
                'cta'     => 'Get your free paid-search audit',
            ),
        ),
        'website-design-agency-toronto' => array(
            'hero'  => array(
                'label' => 'Review my current site',
            ),
            'band'  => array(
                'heading' => 'See what your current site is costing you.',
                'text'    => 'We’ll check how it loads, how it reads on a phone, and where visitors are giving up before they contact you.',
                'cta'     => 'Review my site',
            ),
            'final' => array(
                'heading' => 'Curious what your current site is losing you?',
                'cta'     => 'Get your free website review',
            ),
        ),
        'social-media-marketing-agency-toronto' => array(
            'hero'  => array(
                'label' => 'See where my audience is',
            ),
            'band'  => array(
                'heading' => 'See which channels are worth your time.',
                'text'    => 'Tell us who buys from you and we’ll show you where those people actually are — and what it costs to reach them there.',
                'cta'     => 'See where my audience is',
            ),
            'final' => array(
                'heading' => 'Not sure which channels are worth it?',
                'cta'     => 'Get your free channel review',
            ),
        ),
        'digital-marketing-agency-toronto' => array(
            'hero'  => array(
                'label' => 'Show me where to start',
            ),
            'band'  => array(
                'heading' => 'Find out which channel would move fastest for you.',
                'text'    => 'A few questions about your business, your market and your goals, and we’ll show you where the quickest return is — and where it is not.',
                'cta'     => 'Show me where to start',
            ),
            'final' => array(
                'heading' => 'Not sure where your budget should go first?',
                'cta'     => 'Get your free growth review',
            ),
        ),
    );

    // A service page we have not written copy for still gets something
    // specific rather than falling back to "free consultation".
    return $map[ $slug ] ?? array(
        'hero'  => array(
            'label' => 'See where you stand',
        ),
        'band'  => array(
            'heading' => 'See where you stand against your competitors.',
            'text'    => 'A few questions about your business and your market, and we’ll show you what is working, what is not, and what it is worth fixing first.',
            'cta'     => 'See where you stand',
        ),
        'final' => array(
            'heading' => 'Want to see where you actually stand?',
            'cta'     => 'Get your free review',
        ),
    );
}
