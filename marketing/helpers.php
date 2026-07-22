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
 * Get an ACF field with a fallback default. Safe when ACF is inactive.
 *
 * @param string $name    ACF field name
 * @param mixed  $default Value to use when the field is empty/unavailable
 * @param mixed  $post_id Optional ACF post/option id (e.g. 'option')
 */
function mk_field( $name, $default = '', $post_id = false ) {
    if ( ! function_exists( 'get_field' ) ) return $default;
    $val = $post_id !== false ? get_field( $name, $post_id ) : get_field( $name );
    if ( $val === null || $val === '' || $val === false || ( is_array( $val ) && empty( $val ) ) ) {
        return $default;
    }
    return $val;
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

/** Small inline SVG icon set for marketing cards (no external deps). */
function mk_icon( $name ) {
    $icons = array(
        'website'  => '<path d="M2 3h20v14H2z"/><path d="M8 21h8M12 17v4"/>',
        'ads'      => '<path d="M3 11l18-5v12L3 13v-2z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
        'seo'      => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
        'social'   => '<path d="M18 8a3 3 0 1 0-2.8-4M6 12a3 3 0 1 0 0 0zM18 16a3 3 0 1 0 0 0z"/><path d="M8.6 10.7l6.8-3.4M8.6 13.3l6.8 3.4"/>',
        'spark'    => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4"/><path d="M12 8l1.5 2.5L16 12l-2.5 1.5L12 16l-1.5-2.5L8 12l2.5-1.5z"/>',
        'shield'   => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/>',
        'chart'    => '<path d="M3 3v18h18"/><path d="M7 14l3-3 3 3 5-6"/>',
        'target'   => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
    );
    $p = $icons[ $name ] ?? $icons['spark'];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">' . $p . '</svg>';
}
