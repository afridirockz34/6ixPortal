<?php
/**
 * Marketing header — transparent over the hero, turns navy (#031523) on
 * scroll with a logo swap. Phone shown as a button. Mobile = right-side
 * drawer. Content editable in WP Admin → 6ix Site.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$orig = 'https://6ixdevelopers.com/';
$brand_name  = mk_opt( 'brand_name', '6ix Developers' );
$logo_top    = mk_opt( 'brand_logo',          $orig . 'media/logo/new-logo.png' );        // over dark hero
$logo_scroll = mk_opt( 'brand_logo_scrolled', $orig . 'media/logo/new-logo-white.png' );  // over navy bar
$phone       = mk_opt( 'header_phone', '888-808-7265' );
$phone_href  = 'tel:' . preg_replace( '/[^0-9+]/', '', mk_opt( 'header_phone_tel', '18888087265' ) );
$services    = mk_opt( 'nav_services', array(
    array( 'label' => 'Website Design', 'url' => '/website-design-agency-toronto' ),
    array( 'label' => 'Google Ads/PPC', 'url' => '/ppc-google-ads-management-toronto' ),
    array( 'label' => 'Social Media',   'url' => '/social-media-marketing-agency-toronto' ),
    array( 'label' => 'SEO Services',   'url' => '/seo-agency-toronto' ),
) );
$cta_label = mk_opt( 'header_cta_label', 'Contact us' );
$cta_url   = mk_opt( 'header_cta_url', '/contact-us' );
$mkurl = function ( $u ) { return ( strpos( $u, 'http' ) === 0 ) ? $u : home_url( $u ); };
?>
<header class="mk-header" id="mk-header">
  <div class="mk-wrap">
    <nav class="mk-nav" id="mk-nav">
      <a class="mk-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( $brand_name ); ?>">
        <img class="mk-logo-default"  src="<?php echo esc_url( $logo_top ); ?>"    alt="<?php echo esc_attr( $brand_name ); ?>">
        <img class="mk-logo-scrolled" src="<?php echo esc_url( $logo_scroll ); ?>" alt="<?php echo esc_attr( $brand_name ); ?>">
      </a>

      <button class="mk-nav-toggle" aria-label="Open menu" onclick="document.getElementById('mk-nav').classList.add('mk-open')">
        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>

      <div class="mk-nav-scrim" onclick="document.getElementById('mk-nav').classList.remove('mk-open')"></div>

      <div class="mk-nav-links">
        <button class="mk-nav-close" aria-label="Close menu" onclick="document.getElementById('mk-nav').classList.remove('mk-open')">
          <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2"><line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/></svg>
        </button>
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a>
        <div class="mk-drop">
          <a href="#" onclick="return false" aria-haspopup="true">Services
            <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-1px"><polyline points="6 9 12 15 18 9"/></svg>
          </a>
          <div class="mk-drop-menu">
            <?php foreach ( (array) $services as $s ) : if ( empty( $s['label'] ) ) continue; ?>
            <a href="<?php echo esc_url( $mkurl( $s['url'] ?? '#' ) ); ?>"><?php echo esc_html( $s['label'] ); ?></a>
            <?php endforeach; ?>
          </div>
        </div>
        <a href="<?php echo esc_url( home_url( '/about-us' ) ); ?>">About Us</a>
        <div class="mk-nav-cta">
          <a class="mk-phone-btn" href="<?php echo esc_url( $phone_href ); ?>">
            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            <?php echo esc_html( $phone ); ?>
          </a>
          <a class="mk-btn mk-btn-primary" style="padding:9px 20px" href="<?php echo esc_url( $mkurl( $cta_url ) ); ?>"><?php echo esc_html( $cta_label ); ?></a>
        </div>
      </div>
    </nav>
  </div>
</header>
<script>
(function(){
  var h=document.getElementById('mk-header');
  if(!h) return;
  var onScroll=function(){ h.classList.toggle('mk-scrolled', window.scrollY>40); };
  onScroll(); window.addEventListener('scroll', onScroll, {passive:true});
})();
</script>
