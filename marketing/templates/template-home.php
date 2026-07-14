<?php
/**
 * Template Name: 6ix — Home
 * Template Post Type: page
 *
 * Redesigned homepage. Every section pulls from ACF fields (WP Admin →
 * edit the Home page) and falls back to sensible defaults so it renders
 * fully even before the fields are filled in.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Self-contained document (like portal-page.php) so Divi's header/footer
// shell never renders — this template owns the whole page. wp_head/wp_footer
// still fire so SEO, analytics and other plugins keep working.
remove_action( 'wp_head', 'et_divi_load_scripts' );
remove_action( 'wp_head', 'et_load_custom_scripts' );

// Default content — mirrors the current site's structure.
$svc_cards = mk_field( 'svc_cards', array(
    array( 'icon' => 'website', 'title' => 'Website Design', 'text' => 'Fast, responsive, conversion-focused websites that turn visitors into customers.', 'link' => '/website-design-agency-toronto' ),
    array( 'icon' => 'ads',     'title' => 'Google Ads / PPC', 'text' => 'High-intent campaigns engineered for lower cost per lead and measurable ROI.', 'link' => '/ppc-google-ads-management-toronto' ),
    array( 'icon' => 'seo',     'title' => 'SEO', 'text' => 'Rank for the searches that matter and build compounding organic traffic.', 'link' => '/seo-agency-toronto' ),
    array( 'icon' => 'social',  'title' => 'Social Media', 'text' => 'Content and community management that keeps your brand top of mind.', 'link' => '/social-media-marketing-agency-toronto' ),
) );
$stats = mk_field( 'stats', array(
    array( 'num' => '3.2x', 'lbl' => 'Average return on ad spend' ),
    array( 'num' => '-38%', 'lbl' => 'Lower cost per lead' ),
    array( 'num' => '7+',   'lbl' => 'Industries served' ),
    array( 'num' => '90d',  'lbl' => 'To measurable results' ),
) );
$deepdives = mk_field( 'deepdives', array(
    array( 'eyebrow' => 'Websites', 'title' => 'Responsive websites built to convert', 'text' => 'We design and build fast, mobile-first sites with clear calls to action and the tracking to prove what works.', 'cta_label' => 'Explore web design', 'cta_url' => '/website-design-agency-toronto' ),
    array( 'eyebrow' => 'Google Ads', 'title' => 'Paid search that pays for itself', 'text' => 'Tightly-structured campaigns focused on high-intent keywords, strong landing pages, and constant optimisation.', 'cta_label' => 'Explore Google Ads', 'cta_url' => '/ppc-google-ads-management-toronto' ),
    array( 'eyebrow' => 'SEO', 'title' => 'Organic growth that compounds', 'text' => 'Technical fixes, content and local SEO that build durable rankings for the terms your customers search.', 'cta_label' => 'Explore SEO', 'cta_url' => '/seo-agency-toronto' ),
    array( 'eyebrow' => 'Social', 'title' => 'Social that builds real brand', 'text' => 'Consistent, on-brand content and community management that keeps you visible where your audience spends time.', 'cta_label' => 'Explore social', 'cta_url' => '/social-media-marketing-agency-toronto' ),
) );
$testimonials = mk_field( 'testimonials', array(
    array( 'quote' => 'They understood our business before touching a single campaign. The results spoke for themselves.', 'name' => 'Client', 'role' => 'Local business owner' ),
    array( 'quote' => 'Our website finally looks and performs like the brand we always wanted. Leads are up and steady.', 'name' => 'Client', 'role' => 'Services company' ),
    array( 'quote' => 'Transparent, responsive and genuinely invested in our growth. A real partner, not a vendor.', 'name' => 'Client', 'role' => 'Retail brand' ),
) );

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

<div id="six-mk-root" class="six-mk six-mk--home">

  <?php include SIX_MK_DIR . 'partials/header.php'; ?>

  <!-- HERO -->
  <section class="mk-hero mk-glow">
    <div class="mk-wrap">
      <div class="mk-hero-inner">
        <span class="mk-eyebrow"><?php mk_e( 'hero_eyebrow', 'Toronto digital marketing, reimagined' ); ?></span>
        <h1><?php echo esc_html( mk_field( 'hero_heading', 'Discover the difference AI-driven marketing makes.' ) ); ?></h1>
        <p class="mk-lead"><?php echo esc_html( mk_field( 'hero_sub', 'We build the websites, campaigns and content that grow local businesses — and give you a live dashboard to see it all working.' ) ); ?></p>
        <div class="mk-hero-cta">
          <a class="mk-btn mk-btn-primary mk-btn-lg" href="<?php echo esc_url( home_url( mk_field( 'hero_cta1_url', '/contact-us' ) ) ); ?>"><?php mk_e( 'hero_cta1_label', 'Get your free consultation' ); ?></a>
          <a class="mk-btn mk-btn-ghost mk-btn-lg" href="<?php echo esc_url( mk_portal_url() ); ?>">
            <?php echo mk_icon( 'spark' ); ?><?php mk_e( 'hero_cta2_label', 'Explore the Marketing OS' ); ?>
          </a>
        </div>
        <div class="mk-hero-badges">
          <span><?php echo mk_icon( 'shield' ); ?> No long-term contracts</span>
          <span><?php echo mk_icon( 'chart' ); ?> Transparent reporting</span>
          <span><?php echo mk_icon( 'target' ); ?> Local-market focus</span>
        </div>
      </div>
    </div>
  </section>

  <!-- SERVICES -->
  <section class="mk-section">
    <div class="mk-wrap">
      <div class="mk-center" style="max-width:720px;margin:0 auto 44px">
        <span class="mk-eyebrow" style="justify-content:center">What we do</span>
        <h2><?php echo esc_html( mk_field( 'svc_heading', 'Everything you need to grow, under one roof.' ) ); ?></h2>
        <p class="mk-lead"><?php echo esc_html( mk_field( 'svc_sub', 'A full-stack team across web, paid, search and social — working from the same data.' ) ); ?></p>
      </div>
      <div class="mk-grid mk-grid-4">
        <?php foreach ( (array) $svc_cards as $c ) :
            $href = home_url( $c['link'] ?? '#' ); ?>
        <a class="mk-card mk-card-accent" href="<?php echo esc_url( $href ); ?>">
          <div class="mk-card-ico"><?php echo mk_icon( $c['icon'] ?? 'spark' ); ?></div>
          <h3><?php echo esc_html( $c['title'] ?? '' ); ?></h3>
          <p><?php echo esc_html( $c['text'] ?? '' ); ?></p>
          <span class="mk-card-link">Learn more
            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          </span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- PORTAL CTA BAND (primary conversion driver) -->
  <?php mk_portal_band(); ?>

  <!-- RESULTS / STATS -->
  <section class="mk-section mk-section-sm mk-glow">
    <div class="mk-wrap">
      <div class="mk-center" style="max-width:640px;margin:0 auto 40px">
        <h2><?php echo esc_html( mk_field( 'stats_heading', 'Real results for real local businesses.' ) ); ?></h2>
      </div>
      <div class="mk-grid mk-grid-4">
        <?php foreach ( (array) $stats as $s ) : ?>
        <div class="mk-card mk-stat">
          <div class="mk-stat-num mk-grad-text"><?php echo esc_html( $s['num'] ?? '' ); ?></div>
          <div class="mk-stat-lbl"><?php echo esc_html( $s['lbl'] ?? '' ); ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- DEEP DIVES -->
  <section class="mk-section">
    <div class="mk-wrap" style="display:flex;flex-direction:column;gap:22px">
      <?php foreach ( (array) $deepdives as $i => $d ) : ?>
      <div class="mk-card" style="display:grid;grid-template-columns:1fr 1fr;gap:28px;align-items:center;padding:34px">
        <div style="<?php echo $i % 2 ? 'order:2' : ''; ?>">
          <span class="mk-eyebrow"><?php echo esc_html( $d['eyebrow'] ?? '' ); ?></span>
          <h3 style="font-size:clamp(1.5rem,2.4vw,2rem)"><?php echo esc_html( $d['title'] ?? '' ); ?></h3>
          <p style="color:var(--mk-t2)"><?php echo esc_html( $d['text'] ?? '' ); ?></p>
          <?php if ( ! empty( $d['cta_label'] ) ) : ?>
          <a class="mk-btn mk-btn-ghost" href="<?php echo esc_url( home_url( $d['cta_url'] ?? '#' ) ); ?>" style="margin-top:8px"><?php echo esc_html( $d['cta_label'] ); ?></a>
          <?php endif; ?>
        </div>
        <div style="<?php echo $i % 2 ? 'order:1' : ''; ?>">
          <?php if ( ! empty( $d['image'] ) ) : ?>
            <img src="<?php echo esc_url( $d['image'] ); ?>" alt="" style="border-radius:14px">
          <?php else : ?>
            <div style="aspect-ratio:16/10;border-radius:14px;background:var(--mk-grad-soft);border:1px solid var(--mk-border);display:flex;align-items:center;justify-content:center;color:var(--mk-purple)"><?php echo mk_icon( 'spark' ); ?></div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- TESTIMONIALS -->
  <section class="mk-section mk-section-sm">
    <div class="mk-wrap">
      <div class="mk-center" style="max-width:640px;margin:0 auto 40px">
        <span class="mk-eyebrow" style="justify-content:center">Testimonials</span>
        <h2><?php echo esc_html( mk_field( 'tst_heading', 'Loved by the businesses we grow.' ) ); ?></h2>
      </div>
      <div class="mk-grid mk-grid-3">
        <?php foreach ( (array) $testimonials as $t ) :
            $nm = $t['name'] ?? 'Client'; ?>
        <div class="mk-quote">
          <p>&ldquo;<?php echo esc_html( $t['quote'] ?? '' ); ?>&rdquo;</p>
          <div class="mk-quote-by">
            <div class="mk-quote-av"><?php echo esc_html( strtoupper( substr( $nm, 0, 1 ) ) ); ?></div>
            <div>
              <div class="mk-quote-name"><?php echo esc_html( $nm ); ?></div>
              <div class="mk-quote-role"><?php echo esc_html( $t['role'] ?? '' ); ?></div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- FINAL CTA -->
  <section class="mk-section mk-glow">
    <div class="mk-wrap mk-center" style="max-width:720px">
      <h2 class="mk-grad-text"><?php echo esc_html( mk_field( 'final_heading', 'Ready to grow with a team that shows its work?' ) ); ?></h2>
      <p class="mk-lead" style="margin:0 auto 26px"><?php echo esc_html( mk_field( 'final_text', 'Book a free consultation and see your tailored plan — no commitment.' ) ); ?></p>
      <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap">
        <a class="mk-btn mk-btn-primary mk-btn-lg" href="<?php echo esc_url( home_url( mk_field( 'final_cta_url', '/contact-us' ) ) ); ?>"><?php mk_e( 'final_cta_label', 'Get free consultation' ); ?></a>
        <a class="mk-btn mk-btn-ghost mk-btn-lg" href="<?php echo esc_url( mk_portal_url() ); ?>">See your marketing score</a>
      </div>
    </div>
  </section>

  <?php include SIX_MK_DIR . 'partials/footer.php'; ?>

</div>

<?php wp_footer(); ?>
</body>
</html>
<?php exit; ?>
