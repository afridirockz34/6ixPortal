<?php
/**
 * Marketing footer — mirrors the original 6ixdevelopers.com footer content
 * (contact block, link list, socials, legal row), restyled. Editable in
 * WP Admin → 6ix Site.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$brand   = mk_opt( 'brand_name', '6ix Developers' );
$address = mk_opt( 'footer_address', '1550 South Gateway Rd. Mississauga, Ontario, Canada' );
$map_url = mk_opt( 'footer_map_url', 'https://g.page/6ixdevelopers?share' );
$email   = mk_opt( 'footer_email', 'help@6ixdevelopers.com' );
$tollfree= mk_opt( 'footer_tollfree', '888-808-7265' );
$toronto = mk_opt( 'footer_toronto', '(416) 306-3443' );
$links   = mk_opt( 'footer_links', array(
    array( 'label' => 'Home',                     'url' => '/' ),
    array( 'label' => 'Website Design',           'url' => '/website-design-agency-toronto' ),
    array( 'label' => 'Google Ads',               'url' => '/ppc-google-ads-management-toronto' ),
    array( 'label' => 'SEO Services',             'url' => '/seo-agency-toronto' ),
    array( 'label' => 'Social Media',             'url' => '/social-media-marketing-agency-toronto' ),
    array( 'label' => 'Digital Marketing',        'url' => '/digital-marketing-agency-toronto' ),
    array( 'label' => 'PPC Agency Toronto',       'url' => '/ppc-agency-toronto' ),
    array( 'label' => 'About Us',                 'url' => '/about-us' ),
    array( 'label' => 'Contact Us',               'url' => '/contact-us' ),
) );
$social  = mk_opt( 'footer_social', array(
    array( 'label' => 'Facebook',  'url' => 'https://web.facebook.com/6ixDevelopers/' ),
    array( 'label' => 'Instagram', 'url' => 'https://www.instagram.com/6ixdevelopers/' ),
    array( 'label' => 'Email',     'url' => 'mailto:help@6ixdevelopers.com' ),
) );
$legal   = mk_opt( 'footer_legal', array(
    array( 'label' => 'Privacy Policy',     'url' => '/privacy-policy' ),
    array( 'label' => 'Terms & Conditions', 'url' => '/terms-and-conditions' ),
    array( 'label' => 'Terms of Service',   'url' => '/terms-of-service' ),
) );
$partner_img = mk_opt( 'footer_partner_badge', 'https://6ixdevelopers.com/media/icons/google-partner.jpg' );
$partner_url = mk_opt( 'footer_partner_url', 'https://www.google.com/partners/agency?id=8013163615' );
$established = mk_opt( 'footer_established', 'Est. 2012' );
$mkurl = function ( $u ) { return ( strpos( $u, 'http' ) === 0 ) ? $u : home_url( $u ); };
?>
<footer class="mk-footer">
  <div class="mk-wrap">
    <div class="mk-footer-grid">
      <div>
        <h4>Contact Us</h4>
        <a href="<?php echo esc_url( $map_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $address ); ?></a>
        <a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
        <a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', '1' . $tollfree ) ); ?>">Toll free: <?php echo esc_html( $tollfree ); ?></a>
        <a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', '1' . $toronto ) ); ?>">Toronto: <?php echo esc_html( $toronto ); ?></a>
      </div>
      <div>
        <h4>Quick Links</h4>
        <?php foreach ( array_slice( (array) $links, 0, 4 ) as $l ) : if ( empty( $l['label'] ) ) continue; ?>
        <a href="<?php echo esc_url( $mkurl( $l['url'] ?? '#' ) ); ?>"><?php echo esc_html( $l['label'] ); ?></a>
        <?php endforeach; ?>
      </div>
      <div>
        <h4>&nbsp;</h4>
        <?php foreach ( array_slice( (array) $links, 4 ) as $l ) : if ( empty( $l['label'] ) ) continue; ?>
        <a href="<?php echo esc_url( $mkurl( $l['url'] ?? '#' ) ); ?>"><?php echo esc_html( $l['label'] ); ?></a>
        <?php endforeach; ?>
      </div>
      <div>
        <h4>Follow Us</h4>
        <?php foreach ( (array) $social as $s ) : if ( empty( $s['label'] ) ) continue; ?>
        <a href="<?php echo esc_url( $s['url'] ?? '#' ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $s['label'] ); ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="mk-footer-bottom">
      <span><?php echo esc_html( $established ); ?> &nbsp;&copy; <?php echo date( 'Y' ); ?> <?php echo esc_html( $brand ); ?>. All Rights Reserved.</span>
      <span>
        <?php $out = array();
        foreach ( (array) $legal as $lg ) {
            if ( empty( $lg['label'] ) ) continue;
            $out[] = '<a href="' . esc_url( $mkurl( $lg['url'] ?? '#' ) ) . '" style="display:inline">' . esc_html( $lg['label'] ) . '</a>';
        }
        echo implode( ' &nbsp;|&nbsp; ', $out ); ?>
      </span>
    </div>
  </div>
</footer>
