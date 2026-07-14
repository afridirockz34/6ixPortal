<?php
/**
 * Template Name: 6ix — About
 * Template Post Type: page
 *
 * About page. Copy mirrors the original 6ixdevelopers.com/about-us verbatim;
 * the portal CTA band and final CTA are shared additions.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

remove_action( 'wp_head', 'et_divi_load_scripts' );
remove_action( 'wp_head', 'et_load_custom_scripts' );

$principles = array(
    array( 'icon' => 'seo',    'title' => 'Thorough Understanding', 'text' => "Before embarking on any project, we dive deep into understanding our client's business and industry landscape. This foundational step is essential for crafting an online presence that not only aligns with our client's vision but also resonates with their objectives." ),
    array( 'icon' => 'shield', 'title' => 'Transparency & Collaboration', 'text' => 'We believe in transparency throughout the entire project lifecycle. By actively involving our clients and soliciting feedback at every stage, we ensure alignment with their goals and maintain a clear path toward success.' ),
);

// The disciplines that make up the team (from the original team roster).
$roles = array(
    array( 'icon' => 'spark',   'role' => 'Founder & CEO' ),
    array( 'icon' => 'website', 'role' => 'Co-Founder & Lead Developer' ),
    array( 'icon' => 'chart',   'role' => 'Sales Leaders' ),
    array( 'icon' => 'social',  'role' => 'Client Support' ),
    array( 'icon' => 'social',  'role' => 'Social Media Coordinators' ),
    array( 'icon' => 'ads',     'role' => 'Google Ads Certified Specialists' ),
    array( 'icon' => 'website', 'role' => 'Web Developers' ),
    array( 'icon' => 'seo',     'role' => 'SEO Experts' ),
);

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

<div id="six-mk-root" class="six-mk six-mk--about">

  <?php include SIX_MK_DIR . 'partials/header.php'; ?>

  <!-- HERO -->
  <section class="mk-hero mk-hero-sm mk-glow">
    <div class="mk-aurora" aria-hidden="true"><span class="mk-aurora-a"></span><span class="mk-aurora-b"></span></div>
    <div class="mk-wrap">
      <div class="mk-hero-inner">
        <span class="mk-eyebrow mk-hero-eyebrow">About Us</span>
        <h1>Our Journey<span class="mk-grad-text" style="display:block;font-size:.5em;margin-top:10px">Est. 2012 — Mississauga, Ontario</span></h1>
        <p class="mk-lead">Streamlining everything a business needs to grow online into a single, accessible platform.</p>
      </div>
    </div>
  </section>

  <!-- JOURNEY -->
  <section class="mk-section">
    <div class="mk-wrap">
      <div class="mk-center" style="max-width:840px;margin:0 auto">
        <p class="mk-lead" style="margin-bottom:18px">Since our inception in Mississauga, Ontario in 2012, our mission has been clear: to streamline the multitude of services required for robust online development into a single, accessible platform.</p>
        <p class="mk-lead" style="margin-bottom:18px">Our ultimate goal? To support as many businesses as possible, going the extra mile for each and every client we serve. In essence, our success lies in our unwavering commitment to listening to our clients — and behind every achievement stands our exceptional team, dedicated to delivering excellence in every endeavor.</p>
      </div>
      <div class="mk-grid mk-grid-2" style="margin-top:26px">
        <?php foreach ( $principles as $p ) : ?>
        <div class="mk-card mk-card-accent">
          <span class="mk-ic"><?php echo mk_icon( $p['icon'] ); ?></span>
          <h3><?php echo esc_html( $p['title'] ); ?></h3>
          <p style="margin-bottom:0"><?php echo esc_html( $p['text'] ); ?></p>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- TEAM -->
  <section class="mk-section mk-section-sm mk-glow">
    <div class="mk-wrap">
      <div class="mk-sec-head mk-center mk-full">
        <h2>Meet The Humans Behind Your Success</h2>
        <p class="mk-lead">We Think Big. We Work Hard. We Get Results.</p>
      </div>
      <div class="mk-team-grid">
        <?php foreach ( $roles as $r ) : ?>
        <div class="mk-role">
          <span class="mk-role-ic"><?php echo mk_icon( $r['icon'] ); ?></span>
          <strong><?php echo esc_html( $r['role'] ); ?></strong>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <?php mk_portal_band( array( 'heading' => 'Could your business benefit from our team?' ) ); ?>

  <!-- FINAL CTA -->
  <section class="mk-section mk-glow">
    <div class="mk-wrap mk-center" style="max-width:760px">
      <h2 class="mk-grad-text">Ready to find out what sets 6ix Developers apart?</h2>
      <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap;margin-top:10px">
        <a class="mk-btn mk-btn-primary mk-btn-lg" href="<?php echo esc_url( home_url( '/contact-us' ) ); ?>">Get free consultation now</a>
        <a class="mk-btn mk-btn-ghost mk-btn-lg" href="<?php echo esc_url( mk_portal_url() ); ?>">Get to know your marketing</a>
      </div>
    </div>
  </section>

  <?php include SIX_MK_DIR . 'partials/footer.php'; ?>

</div>
<?php wp_footer(); ?>
</body>
</html>
<?php exit; ?>
