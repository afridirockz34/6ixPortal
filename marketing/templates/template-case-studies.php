<?php
/**
 * Template Name: 6ix — Case Studies
 * Template Post Type: page
 *
 * Case Studies listing. Renders every published Case Study as a card in the
 * same style used on the home page, each linking to its brochure-style single
 * view. New stories added in WP Admin → Case Studies appear here automatically.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

remove_action( 'wp_head', 'et_divi_load_scripts' );
remove_action( 'wp_head', 'et_load_custom_scripts' );

$items = function_exists( 'six_cs_items' ) ? six_cs_items( -1 ) : array();

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

<div id="six-mk-root" class="six-mk six-mk--case-studies">

  <?php include SIX_MK_DIR . 'partials/header.php'; ?>

  <!-- HERO -->
  <section class="mk-hero mk-hero-sm mk-glow">
    <div class="mk-aurora" aria-hidden="true"><span class="mk-aurora-a"></span><span class="mk-aurora-b"></span><span class="mk-aurora-c"></span></div>
    <div class="mk-wrap">
      <div class="mk-hero-inner">
        <span class="mk-eyebrow mk-hero-eyebrow">Case Studies</span>
        <h1>Real Businesses,<span class="mk-grad-text" style="display:block;font-size:.62em;margin-top:8px">Real Results</span></h1>
        <p class="mk-lead">A closer look at how we've helped businesses grow — the objectives, the work, and the numbers behind each partnership.</p>
      </div>
    </div>
  </section>

  <section class="mk-section" style="padding-top:40px">
    <div class="mk-wrap">
      <?php if ( ! empty( $items ) ) : ?>
      <div class="mk-grid mk-grid-3 mk-cstudy-grid">
        <?php foreach ( $items as $cs ) six_cs_card( $cs ); ?>
      </div>
      <?php else : ?>
      <div class="mk-center" style="max-width:560px;margin:0 auto">
        <p class="mk-lead">New case studies are on the way — check back soon.</p>
        <a class="mk-btn mk-btn-primary mk-btn-lg" href="<?php echo esc_url( mk_portal_url() ); ?>" style="margin-top:12px">See how your business is doing</a>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <?php mk_portal_band( array( 'heading' => 'Could your business be our next success story?' ) ); ?>

  <!-- FINAL CTA -->
  <section class="mk-section mk-glow">
    <div class="mk-wrap mk-center" style="max-width:760px">
      <h2 class="mk-grad-text">Ready to find out what sets 6ix Developers apart?</h2>
      <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap;margin-top:10px">
        <a class="mk-btn mk-btn-primary mk-btn-lg" href="<?php echo esc_url( home_url( '/contact-us' ) ); ?>">Get free consultation now</a>
        <a class="mk-btn mk-btn-ghost mk-btn-lg" href="<?php echo esc_url( mk_portal_url() ); ?>">Find out how your business is doing</a>
      </div>
    </div>
  </section>

  <?php include SIX_MK_DIR . 'partials/footer.php'; ?>

</div>
<?php wp_footer(); ?>
</body>
</html>
<?php exit; ?>
