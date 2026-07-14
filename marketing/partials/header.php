<?php
/**
 * Marketing header — sticky AI-gradient nav. Menu items, brand and CTA are all
 * editable in WP Admin → 6ix Site. Falls back to the current site's nav.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$brand_name = mk_opt( 'brand_name', '6ix Developers' );
$brand_logo = mk_opt( 'brand_logo', '' );
$nav_items  = mk_opt( 'nav_items', array(
    array( 'label' => 'Website Design', 'url' => '/website-design-agency-toronto' ),
    array( 'label' => 'Google Ads',     'url' => '/ppc-google-ads-management-toronto' ),
    array( 'label' => 'Social Media',   'url' => '/social-media-marketing-agency-toronto' ),
    array( 'label' => 'SEO',            'url' => '/seo-agency-toronto' ),
    array( 'label' => 'About',          'url' => '/about-us' ),
    array( 'label' => 'Contact',        'url' => '/contact-us' ),
) );
$cta_label = mk_opt( 'header_cta_label', 'Free consultation' );
$cta_url   = mk_opt( 'header_cta_url', '/contact-us' );
?>
<header class="mk-header">
  <div class="mk-wrap">
    <nav class="mk-nav" id="mk-nav">
      <a class="mk-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
        <?php if ( $brand_logo ) : ?>
          <img src="<?php echo esc_url( $brand_logo ); ?>" alt="<?php echo esc_attr( $brand_name ); ?>" style="max-height:34px">
        <?php else :
          // Render "6ix" gradient + the rest plain
          $bn = esc_html( $brand_name );
          echo preg_replace( '/^(\S+)/', '<span>$1</span>', $bn, 1 );
        endif; ?>
      </a>
      <div class="mk-nav-links">
        <?php foreach ( (array) $nav_items as $item ) :
            $label = is_array( $item ) ? ( $item['label'] ?? '' ) : '';
            $u     = is_array( $item ) ? ( $item['url'] ?? '#' ) : '#';
            if ( ! $label ) continue;
            $href = ( strpos( $u, 'http' ) === 0 ) ? $u : home_url( $u ); ?>
        <a href="<?php echo esc_url( $href ); ?>"><?php echo esc_html( $label ); ?></a>
        <?php endforeach; ?>
        <div class="mk-nav-cta">
          <a class="mk-btn mk-btn-ghost" style="padding:9px 16px" href="<?php echo esc_url( mk_portal_url() ); ?>">Client Login</a>
          <a class="mk-btn mk-btn-primary" style="padding:9px 18px" href="<?php echo esc_url( ( strpos( $cta_url, 'http' ) === 0 ) ? $cta_url : home_url( $cta_url ) ); ?>"><?php echo esc_html( $cta_label ); ?></a>
        </div>
      </div>
      <button class="mk-nav-toggle" aria-label="Menu" onclick="document.getElementById('mk-nav').classList.toggle('mk-open')">
        <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
    </nav>
  </div>
</header>
