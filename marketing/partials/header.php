<?php
/**
 * Marketing header — mirrors the original 6ixdevelopers.com header content
 * (logo + Canadian flag, Contact CTA, phone, About, Services dropdown, Home),
 * restyled in the new design system. Everything editable in WP Admin → 6ix Site.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$orig = 'https://6ixdevelopers.com/';
$brand_name = mk_opt( 'brand_name', '6ix Developers' );
$brand_logo = mk_opt( 'brand_logo', $orig . 'media/logo/new-logo.png' );
$flag_img   = mk_opt( 'brand_flag', $orig . 'media/canadian.png' );
$phone      = mk_opt( 'header_phone', '888-808-7265' );
$phone_href = 'tel:' . preg_replace( '/[^0-9+]/', '', mk_opt( 'header_phone_tel', '18888087265' ) );
$services   = mk_opt( 'nav_services', array(
    array( 'label' => 'Website Design', 'url' => '/website-design-agency-toronto' ),
    array( 'label' => 'Google Ads/PPC', 'url' => '/ppc-google-ads-management-toronto' ),
    array( 'label' => 'Social Media',   'url' => '/social-media-marketing-agency-toronto' ),
    array( 'label' => 'SEO Services',   'url' => '/seo-agency-toronto' ),
) );
$cta_label = mk_opt( 'header_cta_label', 'Contact us' );
$cta_url   = mk_opt( 'header_cta_url', '/contact-us' );
$mkurl = function ( $u ) { return ( strpos( $u, 'http' ) === 0 ) ? $u : home_url( $u ); };
?>
<header class="mk-header">
  <div class="mk-wrap">
    <nav class="mk-nav" id="mk-nav">
      <a class="mk-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" style="display:inline-flex;align-items:center;gap:10px">
        <?php if ( $brand_logo ) : ?>
          <img src="<?php echo esc_url( $brand_logo ); ?>" alt="<?php echo esc_attr( $brand_name ); ?>" style="max-height:38px;width:auto">
        <?php else :
          echo preg_replace( '/^(\S+)/', '<span>$1</span>', esc_html( $brand_name ), 1 );
        endif; ?>
        <?php if ( $flag_img ) : ?><img src="<?php echo esc_url( $flag_img ); ?>" alt="Canadian owned" style="max-height:22px;width:auto"><?php endif; ?>
      </a>
      <div class="mk-nav-links">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a>
        <div class="mk-drop">
          <a href="#" onclick="return false" aria-haspopup="true">Services
            <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-1px"><polyline points="6 9 12 15 18 9"/></svg>
          </a>
          <div class="mk-drop-menu">
            <?php foreach ( (array) $services as $s ) :
                if ( empty( $s['label'] ) ) continue; ?>
            <a href="<?php echo esc_url( $mkurl( $s['url'] ?? '#' ) ); ?>"><?php echo esc_html( $s['label'] ); ?></a>
            <?php endforeach; ?>
          </div>
        </div>
        <a href="<?php echo esc_url( home_url( '/about-us' ) ); ?>">About Us</a>
        <a href="<?php echo esc_url( $phone_href ); ?>" style="font-weight:700;color:var(--mk-t1)">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="var(--mk-pink)" stroke-width="2" style="vertical-align:-2px"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          <?php echo esc_html( $phone ); ?>
        </a>
        <div class="mk-nav-cta">
          <a class="mk-btn mk-btn-ghost" style="padding:9px 16px" href="<?php echo esc_url( mk_portal_url() ); ?>">Client Login</a>
          <a class="mk-btn mk-btn-primary" style="padding:9px 18px" href="<?php echo esc_url( $mkurl( $cta_url ) ); ?>"><?php echo esc_html( $cta_label ); ?></a>
        </div>
      </div>
      <button class="mk-nav-toggle" aria-label="Menu" onclick="document.getElementById('mk-nav').classList.toggle('mk-open')">
        <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
    </nav>
  </div>
</header>
