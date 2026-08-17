<?php
/**
 * Single Case Study — brochure-style layout.
 *
 * Loaded via the template_include filter in cpt-casestudy.php for any
 * single `six_case_study`. Mirrors the trifold reference layout (background +
 * objectives + achievements + key results split into side-by-side panels) but
 * rendered entirely in the 6ix marketing design system: navy panels, hot-pink
 * accents, the brand gradient, and the Montserrat/Mulish/Inter type stack.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

remove_action( 'wp_head', 'et_divi_load_scripts' );
remove_action( 'wp_head', 'et_load_custom_scripts' );

$cs = function_exists( 'six_cs_get' ) ? six_cs_get( get_queried_object_id() ) : null;
if ( ! $cs ) { $cs = array( 'title' => get_the_title(), 'subtitle' => '', 'headline' => '', 'background' => '', 'objectives' => array(), 'achievements' => array(), 'results' => array(), 'image' => '' ); }

// Direction arrows for key results.
$arrow_up   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="20" x2="12" y2="5"/><polyline points="6 11 12 5 18 11"/></svg>';
$arrow_down = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="4" x2="12" y2="19"/><polyline points="6 13 12 19 18 13"/></svg>';

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

<div id="six-mk-root" class="six-mk six-mk--case-study">

  <?php
  // This page opens straight into a light section with no dark hero band
  // beneath it, so the header must render solid from the start (see the note
  // in header.php) instead of the usual transparent-until-scroll behaviour.
  $six_mk_header_solid = true;
  include SIX_MK_DIR . 'partials/header.php';
  ?>

  <section class="mk-section mk-cs-hero-sec" style="padding-top:120px">
    <div class="mk-wrap">
      <a class="mk-cs-back" href="<?php echo esc_url( home_url( '/case-studies' ) ); ?>">
        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        All case studies
      </a>

      <div class="mk-cs-panels">

        <!-- ── LEFT PANEL (navy): identity + background ── -->
        <aside class="mk-cs-rail mk-cs-rail--navy">
          <div class="mk-cs-idhead"<?php if ( ! empty( $cs['image'] ) ) : ?> style="background-image:linear-gradient(180deg,rgba(3,21,35,.35),rgba(3,21,35,.94)),url('<?php echo esc_url( $cs['image'] ); ?>')"<?php endif; ?>>
            <div class="mk-cs-brandmark">6ix</div>
            <?php if ( ! empty( $cs['subtitle'] ) ) : ?><span class="mk-cs-eyebrow"><?php echo esc_html( $cs['subtitle'] ); ?></span><?php endif; ?>
            <h1 class="mk-cs-title"><?php echo esc_html( $cs['title'] ); ?></h1>
          </div>
          <?php if ( ! empty( $cs['background'] ) ) : ?>
          <div class="mk-cs-bg">
            <div class="mk-cs-quote" aria-hidden="true">&ldquo;</div>
            <h2 class="mk-cs-rail-h">Background</h2>
            <p><?php echo esc_html( $cs['background'] ); ?></p>
          </div>
          <?php endif; ?>
        </aside>

        <!-- ── CENTER PANEL (surface): headline + objectives + achievements ── -->
        <div class="mk-cs-center">
          <?php if ( ! empty( $cs['headline'] ) ) : ?>
          <div class="mk-cs-headline"><?php echo esc_html( $cs['headline'] ); ?></div>
          <?php endif; ?>

          <?php if ( ! empty( $cs['objectives'] ) ) : ?>
          <div class="mk-cs-block">
            <h2 class="mk-cs-h">Objectives</h2>
            <ul class="mk-cs-obj">
              <?php foreach ( $cs['objectives'] as $o ) : ?>
              <li>
                <span class="mk-cs-obj-ico"><?php echo mk_icon( $o['icon'] ?? 'target' ); ?></span>
                <span><?php echo esc_html( $o['text'] ?? '' ); ?></span>
              </li>
              <?php endforeach; ?>
            </ul>
          </div>
          <?php endif; ?>

          <?php if ( ! empty( $cs['achievements'] ) ) : ?>
          <div class="mk-cs-block">
            <h2 class="mk-cs-h">Achievements</h2>
            <ul class="mk-cs-ach">
              <?php foreach ( $cs['achievements'] as $a ) : ?>
              <li>
                <span class="mk-cs-ach-ico"><?php echo mk_icon( $a['icon'] ?? 'search' ); ?></span>
                <span class="mk-cs-ach-txt"><?php echo esc_html( $a['text'] ?? '' ); ?></span>
              </li>
              <?php endforeach; ?>
            </ul>
          </div>
          <?php endif; ?>
        </div>

        <!-- ── RIGHT PANEL (navy): key results ── -->
        <?php if ( ! empty( $cs['results'] ) ) : ?>
        <aside class="mk-cs-rail mk-cs-rail--navy mk-cs-rail--results">
          <h2 class="mk-cs-rail-h mk-cs-rail-h--center">Key Results</h2>
          <div class="mk-cs-kr-list">
            <?php foreach ( $cs['results'] as $r ) :
                $dir = $r['dir'] ?? 'up'; ?>
            <div class="mk-cs-kr">
              <?php if ( $dir !== 'none' ) : ?>
              <span class="mk-cs-kr-arrow mk-cs-kr-arrow--<?php echo esc_attr( $dir ); ?>"><?php echo $dir === 'down' ? $arrow_down : $arrow_up; ?></span>
              <?php endif; ?>
              <?php if ( ! empty( $r['label'] ) ) : ?><div class="mk-cs-kr-label"><?php echo esc_html( $r['label'] ); ?></div><?php endif; ?>
              <div class="mk-cs-kr-value"><?php echo esc_html( $r['value'] ); ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </aside>
        <?php endif; ?>

      </div>
    </div>
  </section>

  <?php mk_portal_band( array( 'heading' => 'Could your business be our next success story?' ) ); ?>

  <!-- FINAL CTA -->
  <section class="mk-section mk-glow">
    <div class="mk-wrap mk-center" style="max-width:760px">
      <h2 class="mk-grad-text">Ready to see results like these?</h2>
      <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap;margin-top:10px">
        <a class="mk-btn mk-btn-primary mk-btn-lg" href="<?php echo esc_url( home_url( '/contact-us' ) ); ?>">Get free consultation now</a>
        <a class="mk-btn mk-btn-ghost mk-btn-lg" href="<?php echo esc_url( home_url( '/case-studies' ) ); ?>">See more case studies</a>
      </div>
    </div>
  </section>

  <?php include SIX_MK_DIR . 'partials/footer.php'; ?>

</div>
<?php wp_footer(); ?>
</body>
</html>
<?php exit; ?>
