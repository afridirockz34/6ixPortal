<?php
/**
 * Template Name: 6ix — About
 * Template Post Type: page
 *
 * About page. Copy mirrors the original 6ixdevelopers.com/about-us verbatim;
 * the portal CTA band and final CTA are shared additions. Team roster is the
 * real roster from the original site — editable via the six_team option.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

remove_action( 'wp_head', 'et_divi_load_scripts' );
remove_action( 'wp_head', 'et_load_custom_scripts' );

// Two fundamental principles — verbatim from the original About page.
$principles = array(
    array( 'title' => 'Thorough Understanding', 'text' => "Before embarking on any project, we dive deep into understanding our client's business and industry landscape. This foundational step is essential for crafting an online presence that not only aligns with our client's vision but also resonates with their objectives." ),
    array( 'title' => 'Transparency and Collaboration', 'text' => 'We believe in transparency throughout the entire project lifecycle. By actively involving our clients and soliciting feedback at every stage, we ensure alignment with their goals and maintain a clear path toward success.' ),
);

// Real team roster from the original site (names preserved; titles tidied for
// presentation). Editable in WP Admin → 6ix Site via the "six_team" option.
$team = mk_opt( 'six_team', array(
    array( 'name' => 'Musab A.',    'role' => 'Founder & CEO' ),
    array( 'name' => 'Faheem A.',   'role' => 'Co-Founder & Lead Developer' ),
    array( 'name' => 'David G.',    'role' => 'Sales Leader' ),
    array( 'name' => 'Karim M.',    'role' => 'Sales Leader' ),
    array( 'name' => 'Mike S.',     'role' => 'Client Support' ),
    array( 'name' => 'Marc H.',     'role' => 'Client Support' ),
    array( 'name' => 'Nick F.',     'role' => 'Client Support' ),
    array( 'name' => 'Chad B.',     'role' => 'Social Media Coordinator' ),
    array( 'name' => 'Karen G.',    'role' => 'Social Media Coordinator' ),
    array( 'name' => 'Haya M.',     'role' => 'Social Media Coordinator' ),
    array( 'name' => 'Robyn W.',    'role' => 'Social Media Coordinator' ),
    array( 'name' => 'Misty J.',    'role' => 'Social Media Coordinator' ),
    array( 'name' => 'Ossama E.',   'role' => 'Google AdWords Certified Specialist' ),
    array( 'name' => 'Brittney G.', 'role' => 'Google Ads Expert' ),
    array( 'name' => 'Sundas A.',   'role' => 'Google Ads Expert' ),
    array( 'name' => 'Tatiana Z.',  'role' => 'Google Ads Expert' ),
    array( 'name' => 'Tania R.',    'role' => 'Google Ads Expert' ),
    array( 'name' => 'Aurel L.',    'role' => 'Google Ads Expert' ),
    array( 'name' => 'Mark R.',     'role' => 'Google Ads Expert' ),
    array( 'name' => 'Gary G.',     'role' => 'Google Ads Expert' ),
    array( 'name' => 'Robin D.',    'role' => 'Web Developer' ),
    array( 'name' => 'Kashif K.',   'role' => 'Web Developer' ),
    array( 'name' => 'Karthik C.',  'role' => 'Web Developer' ),
    array( 'name' => 'Joseph Z.',   'role' => 'Web Developer' ),
    array( 'name' => 'Alex K.',     'role' => 'SEO Expert' ),
    array( 'name' => 'Jessica G.',  'role' => 'Content Creator' ),
    array( 'name' => 'Ayesha G.',   'role' => 'Content Creator' ),
) );

// A soft colour cycle for the avatar tints (tonal variations of the brand).
$avatar_tints = array( 'a', 'b', 'c', 'd' );

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
    <div class="mk-aurora" aria-hidden="true"><span class="mk-aurora-a"></span><span class="mk-aurora-b"></span><span class="mk-aurora-c"></span></div>
    <div class="mk-wrap">
      <div class="mk-hero-inner">
        <span class="mk-eyebrow mk-hero-eyebrow">About Us</span>
        <h1>Our Journey<span class="mk-grad-text" style="display:block;font-size:.5em;margin-top:10px">Est. 2012 — Mississauga, Ontario</span></h1>
        <p class="mk-lead">Streamlining everything a business needs to grow online into a single, accessible platform.</p>
      </div>
    </div>
  </section>

  <!-- OUR JOURNEY (verbatim) -->
  <section class="mk-section">
    <div class="mk-wrap">
      <div class="mk-center" style="max-width:860px;margin:0 auto">
        <p class="mk-lead" style="margin-bottom:18px">Since our inception in Mississauga, Ontario in 2012, our mission has been clear: to streamline the multitude of services required for robust online development into a single, accessible platform.</p>
        <p class="mk-lead" style="margin-bottom:18px">Our ultimate goal? To support as many businesses as possible, going the extra mile for each and every client we serve.</p>
        <p class="mk-lead" style="margin-bottom:18px">To achieve this, our company abides by two fundamental principles:</p>
      </div>
      <div class="mk-grid mk-grid-2" style="margin-top:12px">
        <?php $pn = 1; foreach ( $principles as $p ) : ?>
        <div class="mk-card mk-card-accent mk-principle-card">
          <span class="mk-principle-num"><?php echo $pn++; ?></span>
          <h3><?php echo esc_html( $p['title'] ); ?></h3>
          <p style="margin-bottom:0"><?php echo esc_html( $p['text'] ); ?></p>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="mk-center" style="max-width:860px;margin:26px auto 0">
        <p class="mk-lead" style="margin-bottom:0">In essence, our success lies in our unwavering commitment to listening to our clients. And behind every achievement stands our exceptional team, dedicated to delivering excellence in every endeavor.</p>
      </div>
    </div>
  </section>

  <!-- TEAM -->
  <section class="mk-section mk-section-sm mk-glow">
    <div class="mk-wrap">
      <div class="mk-sec-head mk-center mk-full">
        <span class="mk-eyebrow" style="justify-content:center">Our Team</span>
        <h2>Meet The Humans Behind Your Success</h2>
        <p class="mk-lead">We Think Big. We Work Hard. We Get Results.</p>
      </div>
      <div class="mk-team-grid">
        <?php foreach ( (array) $team as $i => $m ) :
            $nm = $m['name'] ?? '';
            $initials = '';
            foreach ( preg_split( '/\s+/', trim( $nm ) ) as $w ) { if ( $w !== '' ) $initials .= strtoupper( $w[0] ); }
            $tint = $avatar_tints[ $i % count( $avatar_tints ) ];
        ?>
        <div class="mk-team-card">
          <?php if ( ! empty( $m['image'] ) ) : ?>
          <div class="mk-team-avatar"><img src="<?php echo esc_url( $m['image'] ); ?>" alt="<?php echo esc_attr( $nm ); ?>"></div>
          <?php else : ?>
          <div class="mk-team-avatar mk-team-avatar--<?php echo esc_attr( $tint ); ?>"><?php echo esc_html( $initials ); ?></div>
          <?php endif; ?>
          <div class="mk-team-name"><?php echo esc_html( $nm ); ?></div>
          <div class="mk-team-role"><?php echo esc_html( $m['role'] ?? '' ); ?></div>
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
