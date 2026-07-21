<?php
/**
 * Template Name: 6ix — Contact
 * Template Post Type: page
 *
 * Contact page. Details mirror the original 6ixdevelopers.com/contact-us; the
 * booking CTA routes into the portal onboarding flow.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

remove_action( 'wp_head', 'et_divi_load_scripts' );
remove_action( 'wp_head', 'et_load_custom_scripts' );

$email    = mk_opt( 'footer_email', 'help@6ixdevelopers.com' );
$tollfree = mk_opt( 'footer_tollfree', '888-808-7265' );
$toronto  = mk_opt( 'footer_toronto', '(416) 306-3443' );
$address  = mk_opt( 'footer_address', '1550 South Gateway Rd. Mississauga, Ontario, Canada' );
$map_url  = mk_opt( 'footer_map_url', 'https://g.page/6ixdevelopers?share' );
$tel = function ( $n ) { return 'tel:' . preg_replace( '/[^0-9+]/', '', '1' . $n ); };

header( 'Content-Type: text/html; charset=utf-8' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc_html( wp_get_document_title() ); ?></title>
<?php wp_head(); ?>
</head>
<body <?php body_class( 'six-mk-body' ); ?>>

<div id="six-mk-root" class="six-mk six-mk--contact">

  <?php include SIX_MK_DIR . 'partials/header.php'; ?>

  <!-- HERO -->
  <section class="mk-hero mk-hero-sm mk-glow">
    <div class="mk-aurora" aria-hidden="true"><span class="mk-aurora-a"></span><span class="mk-aurora-b"></span><span class="mk-aurora-c"></span></div>
    <div class="mk-wrap">
      <div class="mk-hero-inner">
        <span class="mk-eyebrow mk-hero-eyebrow">Contact Us</span>
        <h1>Book a Call<span class="mk-grad-text" style="display:block;font-size:.5em;margin-top:10px">Let's grow your business together</span></h1>
        <p class="mk-lead">Get your free online marketing consultation — no obligation, no pressure.</p>
        <div class="mk-hero-cta">
          <a class="mk-btn mk-btn-primary mk-btn-lg" href="<?php echo esc_url( mk_portal_url() ); ?>">Book your free call</a>
          <a class="mk-btn mk-btn-ghost mk-btn-lg" href="<?php echo esc_url( $tel( $tollfree ) ); ?>">Call <?php echo esc_html( $tollfree ); ?></a>
        </div>
      </div>
    </div>
  </section>

  <!-- CONTACT INFO -->
  <section class="mk-section">
    <div class="mk-wrap">
      <div class="mk-contact-grid">
        <div class="mk-card mk-contact-card">
          <span class="mk-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 6l-10 7L2 6"/></svg></span>
          <div><h3>Email Address</h3><a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a></div>
        </div>
        <div class="mk-card mk-contact-card">
          <span class="mk-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg></span>
          <div>
            <h3>Call Us</h3>
            <a href="<?php echo esc_url( $tel( $tollfree ) ); ?>">Toll free: <?php echo esc_html( $tollfree ); ?></a><br>
            <a href="<?php echo esc_url( $tel( $toronto ) ); ?>">Toronto: <?php echo esc_html( $toronto ); ?></a>
          </div>
        </div>
        <div class="mk-card mk-contact-card">
          <span class="mk-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></span>
          <div><h3>Visit Us</h3><a href="<?php echo esc_url( $map_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $address ); ?></a></div>
        </div>
        <div class="mk-card mk-contact-card">
          <span class="mk-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg></span>
          <div><h3>Book Online</h3><a href="<?php echo esc_url( mk_portal_url() ); ?>">Start your free consultation &rarr;</a></div>
        </div>
      </div>
    </div>
  </section>

  <!-- CONTACT FORM -->
  <section class="mk-section mk-section-sm mk-glow" id="contact-form-section">
    <div class="mk-wrap" style="max-width:760px">
      <div class="mk-sec-head mk-center"><h2>Send Us a Message</h2></div>
      <?php mk_form( 'contact' ); ?>
    </div>
  </section>

  <?php mk_portal_band( array( 'heading' => 'Prefer to see your numbers first?' ) ); ?>

  <!-- FINAL CTA -->
  <section class="mk-section mk-glow">
    <div class="mk-wrap mk-center" style="max-width:760px">
      <h2 class="mk-grad-text">Ready to find out what sets 6ix Developers apart?</h2>
      <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap;margin-top:10px">
        <a class="mk-btn mk-btn-primary mk-btn-lg" href="<?php echo esc_url( mk_portal_url() ); ?>">Get free consultation now</a>
      </div>
    </div>
  </section>

  <?php include SIX_MK_DIR . 'partials/footer.php'; ?>

</div>
<?php wp_footer(); ?>
</body>
</html>
<?php exit; ?>
