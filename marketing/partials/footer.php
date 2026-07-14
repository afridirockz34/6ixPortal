<?php
/**
 * Marketing footer — editable columns, contact and social in WP Admin → 6ix Site.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$about = mk_opt( 'footer_about', 'A full-stack digital marketing agency helping local businesses grow with websites, Google Ads, SEO and social — now with a live marketing dashboard.' );
$cols  = mk_opt( 'footer_cols', array(
    array( 'title' => 'Services', 'links' => array(
        array( 'label' => 'Website Design', 'url' => '/website-design-agency-toronto' ),
        array( 'label' => 'Google Ads',     'url' => '/ppc-google-ads-management-toronto' ),
        array( 'label' => 'SEO',            'url' => '/seo-agency-toronto' ),
        array( 'label' => 'Social Media',   'url' => '/social-media-marketing-agency-toronto' ),
    ) ),
    array( 'title' => 'Company', 'links' => array(
        array( 'label' => 'About Us',   'url' => '/about-us' ),
        array( 'label' => 'Contact Us', 'url' => '/contact-us' ),
        array( 'label' => 'Client Portal', 'url' => '/get-started/' ),
    ) ),
) );
$email  = mk_opt( 'footer_email', 'hello@6ixdevelopers.com' );
$phone  = mk_opt( 'footer_phone', '' );
$social = mk_opt( 'footer_social', array() );
$brand  = mk_opt( 'brand_name', '6ix Developers' );
?>
<footer class="mk-footer">
  <div class="mk-wrap">
    <div class="mk-footer-grid">
      <div>
        <div class="mk-logo" style="margin-bottom:14px"><?php echo preg_replace( '/^(\S+)/', '<span>$1</span>', esc_html( $brand ), 1 ); ?></div>
        <p style="color:var(--mk-t3);font-size:.95rem;max-width:320px"><?php echo esc_html( $about ); ?></p>
        <a class="mk-btn mk-btn-primary" style="margin-top:18px;padding:11px 20px" href="<?php echo esc_url( mk_portal_url() ); ?>">Open your dashboard</a>
      </div>
      <?php foreach ( (array) $cols as $col ) :
          $ct = is_array( $col ) ? ( $col['title'] ?? '' ) : '';
          $ls = is_array( $col ) ? ( $col['links'] ?? array() ) : array(); ?>
      <div>
        <h4><?php echo esc_html( $ct ); ?></h4>
        <?php foreach ( (array) $ls as $l ) :
            $ll = $l['label'] ?? ''; $lu = $l['url'] ?? '#';
            if ( ! $ll ) continue;
            $href = ( strpos( $lu, 'http' ) === 0 ) ? $lu : home_url( $lu ); ?>
        <a href="<?php echo esc_url( $href ); ?>"><?php echo esc_html( $ll ); ?></a>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
      <div>
        <h4>Get in touch</h4>
        <?php if ( $email ) : ?><a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a><?php endif; ?>
        <?php if ( $phone ) : ?><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a><?php endif; ?>
        <?php if ( ! empty( $social ) ) : ?>
        <div style="display:flex;gap:14px;margin-top:12px">
          <?php foreach ( (array) $social as $s ) :
              $sl = $s['label'] ?? ''; $su = $s['url'] ?? '#';
              if ( ! $sl ) continue; ?>
          <a href="<?php echo esc_url( $su ); ?>" target="_blank" rel="noopener" style="font-size:.9rem"><?php echo esc_html( $sl ); ?></a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <div class="mk-footer-bottom">
      <span>&copy; <?php echo date( 'Y' ); ?> <?php echo esc_html( $brand ); ?>. All rights reserved.</span>
      <span>Built with the 6ix Marketing OS.</span>
    </div>
  </div>
</footer>
