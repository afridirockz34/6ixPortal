<?php
/**
 * Template Name: 6ix — Service Page
 * Template Post Type: page
 *
 * One template drives all four service pages. Copy is chosen by the page slug
 * from six_service_pages() (see marketing/pages.php) and mirrors the original
 * site verbatim; the portal CTA band, testimonials slider and final CTA are
 * shared additions. Sections render only when the page provides their data.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

remove_action( 'wp_head', 'et_divi_load_scripts' );
remove_action( 'wp_head', 'et_load_custom_scripts' );

$slug = get_post_field( 'post_name', get_queried_object_id() );
$d    = six_service_page( $slug );
if ( ! $d ) { // Assigned to an unknown page — fall back to the first service.
    $all = six_service_pages();
    $d   = reset( $all );
}

$arrow = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>';

// Shared testimonials (same CPT-backed source as the homepage).
$testimonials = mk_testimonial_items( array(
    array( 'quote' => 'I am very thankful to 6ix Developers for their services. I am super happy with my website and Google Ads. They made me feel comfortable and kept me in the loop with the whole progress.', 'name' => 'Annie C.', 'role' => '' ),
    array( 'quote' => 'I will definitely recommend this company to everybody who wants a professional and perfect website for their business. I am so impressed with their work.', 'name' => 'Elidrissia H.', 'role' => '' ),
    array( 'quote' => '6ix Developers did a great job of meeting our needs and helping us design the site we wanted. Most recommended web developers.', 'name' => 'Barnard S.', 'role' => '' ),
) );

$ph = 'https://placehold.co/256x256/eef1f7/6b7688?text=Client';
$cs_slides = mk_success_items( array(
    array( 'title' => 'Criminal Law Firm',   'period' => '2024, Q3 - Q4', 'conv' => '16.50%', 'ctr' => '6.80%', 'cpl' => '$125.70', 'image' => $ph ),
    array( 'title' => 'Family Law Firm',     'period' => '2024, Q3 - Q4', 'conv' => '19.10%', 'ctr' => '7.40%', 'cpl' => '$104.84', 'image' => $ph ),
    array( 'title' => 'Employment Law Firm', 'period' => '2024, Q3 - Q4', 'conv' => '22.10%', 'ctr' => '6.30%', 'cpl' => '$61.21',  'image' => $ph ),
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

<div id="six-mk-root" class="six-mk six-mk--service">

  <?php include SIX_MK_DIR . 'partials/header.php'; ?>

  <!-- HERO -->
  <section class="mk-hero mk-hero-sm mk-glow">
    <div class="mk-aurora" aria-hidden="true"><span class="mk-aurora-a"></span><span class="mk-aurora-b"></span><span class="mk-aurora-c"></span></div>
    <div class="mk-wrap">
      <div class="mk-hero-inner">
        <?php if ( ! empty( $d['eyebrow'] ) ) : ?><span class="mk-eyebrow mk-hero-eyebrow"><?php echo esc_html( $d['eyebrow'] ); ?></span><?php endif; ?>
        <h1><?php echo esc_html( $d['title'] ); ?>
          <?php if ( ! empty( $d['subtitle'] ) ) : ?><span class="mk-grad-text" style="display:block;font-size:.6em;margin-top:10px"><?php echo esc_html( $d['subtitle'] ); ?></span><?php endif; ?>
        </h1>
        <?php if ( ! empty( $d['lead'] ) ) : ?>
        <p class="mk-lead"><?php echo esc_html( $d['lead'] ); ?>
          <?php if ( ! empty( $d['typing_words'] ) ) : ?><br><span class="mk-typing" id="mk-typing" data-words="<?php echo esc_attr( is_array( $d['typing_words'] ) ? implode( ',', $d['typing_words'] ) : $d['typing_words'] ); ?>"></span><?php endif; ?></p>
        <?php endif; ?>
        <div class="mk-hero-cta">
          <?php
          $hc1 = $d['hero_cta1'] ?? array( 'label' => 'Get your free consultation', 'url' => '/contact-us' );
          $hc2 = $d['hero_cta2'] ?? array( 'label' => 'See where you can grow', 'url' => '' );
          $hc1_url = ! empty( $hc1['url'] ) ? ( strpos( $hc1['url'], '#' ) === 0 ? $hc1['url'] : home_url( $hc1['url'] ) ) : mk_portal_url();
          $hc2_url = ! empty( $hc2['url'] ) ? ( strpos( $hc2['url'], '#' ) === 0 ? $hc2['url'] : home_url( $hc2['url'] ) ) : mk_portal_url();
          ?>
          <a class="mk-btn mk-btn-primary mk-btn-lg" href="<?php echo esc_url( $hc1_url ); ?>"><?php echo esc_html( $hc1['label'] ); ?></a>
          <a class="mk-btn mk-btn-ghost mk-btn-lg" href="<?php echo esc_url( $hc2_url ); ?>"><?php echo esc_html( $hc2['label'] ); ?></a>
        </div>
      </div>
    </div>
  </section>

  <!-- SIGN-UP OFFER TIERS (Google Ads) -->
  <?php if ( ! empty( $d['offer_tiers'] ) ) : ?>
  <section class="mk-section mk-section-sm">
    <div class="mk-wrap">
      <div class="mk-sec-head mk-center mk-full">
        <h2><?php echo esc_html( $d['offer_tiers']['heading'] ?? 'Choose a sign-up offer' ); ?></h2>
        <?php if ( ! empty( $d['offer_tiers']['intro'] ) ) : ?><p class="mk-lead"><?php echo esc_html( $d['offer_tiers']['intro'] ); ?></p><?php endif; ?>
      </div>
      <div class="mk-grid mk-grid-3">
        <?php foreach ( (array) $d['offer_tiers']['tiers'] as $i => $tier ) : ?>
        <div class="mk-card mk-card-accent mk-offer-card<?php echo ! empty( $tier['featured'] ) ? ' mk-offer-featured' : ''; ?>">
          <?php if ( ! empty( $tier['featured'] ) ) : ?><span class="mk-offer-tag">Most Popular</span><?php endif; ?>
          <span class="mk-offer-credit mk-grad-text"><?php echo esc_html( $tier['credit'] ); ?></span>
          <span class="mk-offer-label">in Google Ads credit</span>
          <p><?php echo esc_html( $tier['text'] ); ?></p>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="mk-center" style="margin-top:24px">
        <a class="mk-btn mk-btn-primary mk-btn-lg" href="<?php echo esc_url( mk_portal_url() ); ?>"><?php echo esc_html( $d['offer_tiers']['cta'] ?? 'Check your eligibility' ); ?> <?php echo $arrow; ?></a>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- ELIGIBILITY FORM (Google Ads $1800 credit) -->
  <?php if ( ! empty( $d['form_eligibility'] ) ) : ?>
  <section class="mk-section mk-section-sm mk-glow" id="eligibility">
    <div class="mk-wrap"><?php mk_form( 'eligibility' ); ?></div>
  </section>
  <?php endif; ?>

  <!-- INTRO -->
  <?php if ( ! empty( $d['intro'] ) ) : ?>
  <section class="mk-section">
    <div class="mk-wrap">
      <?php if ( ! empty( $d['intro_heading'] ) ) : ?>
      <div class="mk-sec-head mk-center mk-full"><h2><?php echo esc_html( $d['intro_heading'] ); ?></h2></div>
      <?php endif; ?>
      <div class="mk-center" style="max-width:840px;margin:0 auto">
        <?php foreach ( (array) $d['intro'] as $p ) : ?>
        <p class="mk-lead" style="margin-bottom:18px"><?php echo esc_html( $p ); ?></p>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- RESULTS (benefit cards) -->
  <?php if ( ! empty( $d['results'] ) ) : ?>
  <section class="mk-section mk-section-sm mk-glow">
    <div class="mk-wrap">
      <div class="mk-sec-head mk-center mk-full"><h2><?php echo esc_html( $d['results_heading'] ?? 'The Results That Matter' ); ?></h2></div>
      <div class="mk-grid mk-grid-3">
        <?php foreach ( (array) $d['results'] as $r ) : ?>
        <div class="mk-card mk-benefit">
          <span class="mk-ic"><?php echo mk_icon( $r['icon'] ?? 'spark' ); ?></span>
          <h3><?php echo esc_html( $r['title'] ); ?></h3>
          <p><?php echo esc_html( $r['text'] ); ?></p>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- PACKAGES -->
  <?php if ( ! empty( $d['packages'] ) ) : ?>
  <section class="mk-section mk-section-sm">
    <div class="mk-wrap">
      <div class="mk-sec-head mk-center mk-full">
        <h2><?php echo esc_html( $d['packages_heading'] ?? 'Packages' ); ?></h2>
        <?php if ( ! empty( $d['packages_intro'] ) ) : ?><p class="mk-lead"><?php echo esc_html( $d['packages_intro'] ); ?></p><?php endif; ?>
      </div>
      <div class="mk-grid mk-grid-3">
        <?php foreach ( (array) $d['packages'] as $pk ) : ?>
        <div class="mk-card mk-card-accent mk-price-card">
          <span class="mk-price-badge"><?php echo esc_html( $pk['badge'] ); ?></span>
          <span class="mk-price-size mk-grad-text"><?php echo esc_html( $pk['size'] ); ?></span>
          <?php if ( ! empty( $pk['text'] ) ) : ?><p><?php echo esc_html( $pk['text'] ); ?></p><?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- PACKAGES GUIDE ("What website is right for me?") -->
  <?php if ( ! empty( $d['packages_guide'] ) ) : ?>
  <section class="mk-section mk-section-sm">
    <div class="mk-wrap">
      <div class="mk-sec-head mk-center mk-full"><h2><?php echo esc_html( $d['packages_guide']['heading'] ?? 'What website is right for me?' ); ?></h2></div>
      <div class="mk-grid mk-grid-3">
        <?php foreach ( (array) ( $d['packages_guide']['items'] ?? array() ) as $g ) : ?>
        <div class="mk-card mk-guide-card">
          <span class="mk-guide-badge"><?php echo esc_html( $g['title'] ); ?></span>
          <p><?php echo esc_html( $g['text'] ); ?></p>
        </div>
        <?php endforeach; ?>
      </div>
      <?php if ( ! empty( $d['packages_guide']['qa'] ) ) : ?>
      <div class="mk-grid mk-grid-2" style="margin-top:22px">
        <?php foreach ( (array) $d['packages_guide']['qa'] as $qa ) : ?>
        <div class="mk-card mk-qa-card">
          <h3><?php echo esc_html( $qa['q'] ); ?></h3>
          <p style="margin-bottom:0"><?php echo esc_html( $qa['a'] ); ?></p>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- NARRATIVE SECTIONS (verbatim feature sections, alternating) -->
  <?php if ( ! empty( $d['sections'] ) ) : ?>
  <?php foreach ( (array) $d['sections'] as $si => $sec ) : ?>
  <section class="mk-section mk-section-sm<?php echo $si % 2 ? ' mk-glow' : ''; ?>">
    <div class="mk-wrap">
      <div class="mk-narrative<?php echo ! empty( $sec['image'] ) ? ' mk-narrative-split' : ' mk-narrative-center'; ?>">
        <div class="mk-narrative-text">
          <span class="mk-eyebrow"><?php echo esc_html( $sec['eyebrow'] ?? 'Website Design' ); ?></span>
          <h2><?php echo esc_html( $sec['title'] ); ?></h2>
          <?php foreach ( (array) $sec['paras'] as $p ) : ?><p><?php echo esc_html( $p ); ?></p><?php endforeach; ?>
        </div>
        <?php if ( ! empty( $sec['image'] ) ) : ?>
        <div class="mk-narrative-media"><img src="<?php echo esc_url( $sec['image'] ); ?>" alt="" loading="lazy"></div>
        <?php endif; ?>
      </div>
    </div>
  </section>
  <?php endforeach; ?>
  <?php endif; ?>

  <!-- FEATURES (narrative stack) -->
  <?php if ( ! empty( $d['features'] ) ) : ?>
  <section class="mk-section">
    <div class="mk-wrap">
      <?php if ( ! empty( $d['features_heading'] ) ) : ?>
      <div class="mk-sec-head mk-center mk-full"><h2><?php echo esc_html( $d['features_heading'] ); ?></h2></div>
      <?php endif; ?>
      <div class="mk-grid mk-grid-2" style="align-items:start">
        <?php foreach ( (array) $d['features'] as $f ) : ?>
        <div class="mk-card">
          <span class="mk-ic"><?php echo mk_icon( $f['icon'] ?? 'spark' ); ?></span>
          <h3><?php echo esc_html( $f['title'] ); ?></h3>
          <p style="margin-bottom:0"><?php echo esc_html( $f['text'] ); ?></p>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- BENEFITS grid (SEO) -->
  <?php if ( ! empty( $d['benefits'] ) ) : ?>
  <section class="mk-section mk-section-sm mk-glow">
    <div class="mk-wrap">
      <div class="mk-sec-head mk-center mk-full">
        <h2><?php echo esc_html( $d['benefits_heading'] ?? 'Why 6ix Developers' ); ?></h2>
        <?php if ( ! empty( $d['benefits_intro'] ) ) : ?><p class="mk-lead"><?php echo esc_html( $d['benefits_intro'] ); ?></p><?php endif; ?>
      </div>
      <div class="mk-grid mk-grid-3">
        <?php foreach ( (array) $d['benefits'] as $b ) : ?>
        <div class="mk-card mk-benefit">
          <span class="mk-ic"><?php echo mk_icon( $b['icon'] ?? 'spark' ); ?></span>
          <h3><?php echo esc_html( $b['title'] ); ?></h3>
          <p><?php echo esc_html( $b['text'] ); ?></p>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- CLIENT SUCCESS carousel -->
  <?php if ( ! empty( $d['show_success'] ) ) : ?>
  <section class="mk-section mk-section-sm">
    <div class="mk-wrap">
      <div class="mk-sec-head mk-center"><h2><?php echo esc_html( $d['success_heading'] ?? 'Client Success' ); ?></h2></div>
      <div class="mk-carousel" data-carousel data-autoplay="5000">
        <button class="mk-carousel-arrow mk-prev" data-prev aria-label="Previous"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg></button>
        <button class="mk-carousel-arrow mk-next" data-next aria-label="Next"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></button>
        <div class="mk-carousel-viewport">
          <div class="mk-carousel-track" data-track>
            <?php foreach ( (array) $cs_slides as $s ) : ?>
            <div class="mk-slide">
              <div class="mk-cs-card">
                <div class="mk-cs-img"><?php if ( ! empty( $s['image'] ) ) : ?><img src="<?php echo esc_url( $s['image'] ); ?>" alt="<?php echo esc_attr( $s['title'] ?? '' ); ?>"><?php endif; ?></div>
                <div>
                  <h3><?php echo esc_html( $s['title'] ?? '' ); ?></h3>
                  <div class="mk-cs-period"><?php echo esc_html( $s['period'] ?? '' ); ?></div>
                  <div class="mk-cs-metrics">
                    <div class="mk-cs-metric"><span class="mk-cs-num mk-grad-text"><?php echo esc_html( $s['conv'] ?? '' ); ?></span><span class="mk-cs-lbl">Conversion</span></div>
                    <div class="mk-cs-metric"><span class="mk-cs-num mk-grad-text"><?php echo esc_html( $s['ctr'] ?? '' ); ?></span><span class="mk-cs-lbl">Click-Through</span></div>
                    <div class="mk-cs-metric"><span class="mk-cs-num mk-grad-text"><?php echo esc_html( $s['cpl'] ?? '' ); ?></span><span class="mk-cs-lbl">Cost / Lead</span></div>
                  </div>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="mk-dots" data-dots></div>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- AUDIT intro blocks (Google Ads) -->
  <?php if ( ! empty( $d['audit_blocks'] ) ) : ?>
  <section class="mk-section mk-section-sm mk-glow">
    <div class="mk-wrap">
      <?php if ( ! empty( $d['audit_heading'] ) ) : ?>
      <div class="mk-sec-head mk-center mk-full"><span class="mk-eyebrow" style="justify-content:center">Comprehensive Google Ads Audit</span><h2><?php echo esc_html( $d['audit_heading'] ); ?></h2></div>
      <?php endif; ?>
      <div class="mk-dd-list">
        <?php foreach ( (array) $d['audit_blocks'] as $i => $ab ) : ?>
        <div class="mk-card mk-dd-card">
          <div class="mk-dd-text" style="<?php echo $i % 2 ? 'order:2' : ''; ?>">
            <?php if ( ! empty( $ab['title'] ) ) : ?><h3 style="font-size:clamp(1.35rem,2.1vw,1.7rem)"><?php echo esc_html( $ab['title'] ); ?></h3><?php endif; ?>
            <?php foreach ( (array) $ab['paras'] as $p ) : ?><p><?php echo esc_html( $p ); ?></p><?php endforeach; ?>
            <a class="mk-btn mk-btn-primary" href="<?php echo esc_url( home_url( '/contact-us' ) ); ?>" style="align-self:flex-start;margin-top:6px"><?php echo esc_html( $ab['cta'] ?? 'Request Google Ads Account Audit' ); ?></a>
          </div>
          <div class="mk-dd-media mk-dd-media-contain" style="<?php echo $i % 2 ? 'order:1' : ''; ?>">
            <?php if ( ! empty( $ab['image'] ) ) : ?><img src="<?php echo esc_url( $ab['image'] ); ?>" alt="" loading="lazy"><?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- AUDIT CHECKLIST (What's included) -->
  <?php if ( ! empty( $d['audit_checklist'] ) ) : ?>
  <section class="mk-section">
    <div class="mk-wrap">
      <div class="mk-sec-head mk-center mk-full"><h2><?php echo esc_html( $d['audit_checklist_heading'] ?? "What's included in our comprehensive Google Ads Account Audit" ); ?></h2></div>
      <div class="mk-grid mk-grid-2" style="align-items:start">
        <?php foreach ( (array) $d['audit_checklist'] as $ci ) : ?>
        <div class="mk-card mk-audit-item">
          <span class="mk-ic"><?php echo mk_icon( $ci['icon'] ?? 'seo' ); ?></span>
          <h3><?php echo esc_html( $ci['title'] ); ?></h3>
          <?php foreach ( (array) $ci['paras'] as $p ) : ?><p><?php echo esc_html( $p ); ?></p><?php endforeach; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php if ( empty( $d['form_audit'] ) ) : ?>
      <div class="mk-center" style="margin-top:24px">
        <a class="mk-btn mk-btn-primary mk-btn-lg" href="<?php echo esc_url( home_url( '/contact-us' ) ); ?>">Request Google Ads Account Audit <?php echo $arrow; ?></a>
      </div>
      <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- AUDIT REQUEST FORM -->
  <?php if ( ! empty( $d['form_audit'] ) ) : ?>
  <section class="mk-section mk-section-sm" id="audit">
    <div class="mk-wrap"><?php mk_form( 'audit' ); ?></div>
  </section>
  <?php endif; ?>

  <!-- HIGHLIGHT (pricing / offer) -->
  <?php if ( ! empty( $d['highlight'] ) ) : ?>
  <section class="mk-section mk-section-sm">
    <div class="mk-wrap">
      <div class="mk-highlight">
        <span class="mk-eyebrow">Offer</span>
        <h3 class="mk-grad-text" style="font-size:clamp(1.5rem,2.6vw,2rem)"><?php echo esc_html( $d['highlight']['heading'] ); ?></h3>
        <?php foreach ( (array) $d['highlight']['lines'] as $ln ) : ?>
        <p><?php echo esc_html( $ln ); ?></p>
        <?php endforeach; ?>
        <a class="mk-btn mk-btn-primary" style="margin-top:12px" href="<?php echo esc_url( ! empty( $d['highlight']['cta_url'] ) ? home_url( $d['highlight']['cta_url'] ) : mk_portal_url() ); ?>"><?php echo esc_html( $d['highlight']['cta'] ?? 'Check your eligibility' ); ?> <?php echo $arrow; ?></a>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- PROCESS: what we need + steps -->
  <?php if ( ! empty( $d['need'] ) || ! empty( $d['steps'] ) ) : ?>
  <section class="mk-section mk-glow">
    <div class="mk-wrap">
      <div class="mk-sec-head mk-center"><h2><?php echo esc_html( $d['steps_heading'] ?? 'How It Works' ); ?></h2></div>
      <div class="mk-grid mk-grid-2" style="align-items:start;gap:34px">
        <?php if ( ! empty( $d['need'] ) ) : ?>
        <div class="mk-card">
          <h3 style="margin-bottom:6px"><?php echo esc_html( $d['need']['heading'] ); ?></h3>
          <?php if ( ! empty( $d['need']['text'] ) ) : ?><p style="color:var(--mk-t2)"><?php echo esc_html( $d['need']['text'] ); ?></p><?php endif; ?>
          <ul class="mk-portal-features" style="margin-top:6px">
            <?php foreach ( (array) $d['need']['items'] as $it ) : ?>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg><span><?php echo esc_html( $it ); ?></span></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>
        <?php if ( ! empty( $d['steps'] ) ) : ?>
        <div>
          <h3 style="margin-bottom:16px"><?php echo esc_html( $d['steps']['heading'] ); ?></h3>
          <ol class="mk-steps">
            <?php foreach ( (array) $d['steps']['list'] as $st ) : ?>
            <li><div><strong><?php echo esc_html( $st['strong'] ); ?></strong><p><?php echo esc_html( $st['text'] ); ?></p></div></li>
            <?php endforeach; ?>
          </ol>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- PRICING (Grow Your Business) — original two-column layout, restyled -->
  <?php if ( ! empty( $d['pricing'] ) ) : ?>
  <section class="mk-section mk-section-sm" id="pricing">
    <div class="mk-wrap">
      <div class="mk-sec-head mk-center mk-full">
        <h2><?php echo esc_html( $d['pricing']['heading'] ?? 'Grow Your Business' ); ?></h2>
        <?php foreach ( (array) ( $d['pricing']['intro'] ?? array() ) as $p ) : ?><p class="mk-lead"><?php echo esc_html( $p ); ?></p><?php endforeach; ?>
      </div>
      <div class="mk-grow-grid">
        <!-- Left: calculator + fees -->
        <div class="mk-grow-left">
          <?php if ( ! empty( $d['pricing']['calculator'] ) ) : ?>
          <h3 class="mk-grow-calc-title"><?php echo esc_html( $d['pricing']['calc_heading'] ?? 'Find out your monthly management cost' ); ?></h3>
          <div class="mk-calc-row">
            <input type="text" id="mk-calc-field" inputmode="numeric" placeholder="Enter your monthly Google Ads budget">
          </div>
          <div class="mk-calc-out" id="mk-calc-out"></div>
          <div class="mk-grow-cta-row">
            <button type="button" class="mk-btn mk-btn-primary" onclick="mkCalcManagement()">Calculate Now</button>
            <a class="mk-btn mk-btn-ghost" href="<?php echo esc_url( home_url( '/contact-us' ) ); ?>"><?php echo esc_html( $d['pricing']['cta'] ?? 'Talk to a PPC Expert' ); ?></a>
          </div>
          <?php endif; ?>
          <div class="mk-grow-fees">
            <?php foreach ( (array) $d['pricing']['fees'] as $fee ) : ?>
            <div class="mk-grow-fee">
              <h4><?php echo esc_html( $fee['label'] ); ?></h4>
              <p><?php echo esc_html( $fee['note'] ?? $fee['value'] ?? '' ); ?></p>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <!-- Right: What's Included table card -->
        <?php if ( ! empty( $d['pricing']['included'] ) ) : ?>
        <div class="mk-included-card">
          <div class="mk-included-head"><?php echo esc_html( $d['pricing']['included_heading'] ?? "What's Included" ); ?></div>
          <div class="mk-included-body">
            <?php foreach ( (array) $d['pricing']['included'] as $row ) :
              $label = is_array( $row ) ? ( $row[0] ?? '' ) : $row;
              $val   = is_array( $row ) ? ( $row[1] ?? 'Yes' ) : 'Yes'; ?>
            <div class="mk-included-row"><span><?php echo esc_html( $label ); ?></span><strong><?php echo esc_html( $val ); ?></strong></div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </section>
  <script>
  function mkCalcManagement(){
    var v=parseFloat((document.getElementById('mk-calc-field').value||'').replace(/[^0-9.]/g,''))||0;
    var out=document.getElementById('mk-calc-out'); if(!out) return;
    var fee=Math.max(799, v*0.15);
    out.innerHTML = v>0
      ? 'Estimated management fee: <strong>$'+fee.toLocaleString(undefined,{maximumFractionDigits:0})+'/month</strong>'
      : 'Enter your monthly budget to see your estimated fee.';
  }
  </script>
  <?php endif; ?>

  <!-- QUOTE / CONSULTATION FORM -->
  <?php if ( ! empty( $d['form_quote'] ) ) : ?>
  <section class="mk-section mk-section-sm mk-glow" id="quote">
    <div class="mk-wrap"><?php mk_form( 'quote', (array) $d['form_quote'] ); ?></div>
  </section>
  <?php endif; ?>

  <!-- PORTAL CTA BAND -->
  <?php mk_portal_band( array(
      'heading' => 'See how your ' . strtolower( $d['menu'] ?? 'marketing' ) . ' stacks up.',
  ) ); ?>

  <!-- TESTIMONIALS -->
  <section class="mk-section mk-section-sm mk-glow">
    <div class="mk-wrap">
      <div class="mk-sec-head mk-center"><h2>What Our Clients Say</h2></div>
      <div class="mk-carousel" data-carousel data-autoplay="6000">
        <button class="mk-carousel-arrow mk-prev" data-prev aria-label="Previous"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg></button>
        <button class="mk-carousel-arrow mk-next" data-next aria-label="Next"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></button>
        <div class="mk-carousel-viewport">
          <div class="mk-carousel-track" data-track>
            <?php foreach ( (array) $testimonials as $t ) : $nm = $t['name'] ?? 'Client'; ?>
            <div class="mk-slide">
              <div class="mk-tst-card">
                <p>&ldquo;<?php echo esc_html( $t['quote'] ?? '' ); ?>&rdquo;</p>
                <div class="mk-tst-by">
                  <div class="mk-tst-av"><?php if ( ! empty( $t['image'] ) ) : ?><img src="<?php echo esc_url( $t['image'] ); ?>" alt="<?php echo esc_attr( $nm ); ?>"><?php else : echo esc_html( strtoupper( substr( $nm, 0, 1 ) ) ); endif; ?></div>
                  <div style="text-align:left">
                    <div class="mk-tst-name"><?php echo esc_html( $nm ); ?></div>
                    <?php if ( ! empty( $t['role'] ) ) : ?><div class="mk-tst-role"><?php echo esc_html( $t['role'] ); ?></div><?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="mk-dots" data-dots></div>
      </div>
    </div>
  </section>

  <!-- FAQ -->
  <?php if ( ! empty( $d['faq'] ) ) : ?>
  <section class="mk-section mk-section-sm">
    <div class="mk-wrap">
      <div class="mk-sec-head mk-center"><h2><?php echo esc_html( $d['faq_heading'] ?? 'Frequently Asked Questions' ); ?></h2></div>
      <div class="mk-faq">
        <?php foreach ( (array) $d['faq'] as $q ) : ?>
        <details>
          <summary><?php echo esc_html( $q['q'] ); ?></summary>
          <p><?php echo esc_html( $q['a'] ); ?></p>
        </details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

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

<script>
(function(){
  document.querySelectorAll('[data-carousel]').forEach(function(root){
    var track=root.querySelector('[data-track]'); var slides=track?track.children:[];
    if(!track||slides.length===0) return;
    var dotsWrap=root.querySelector('[data-dots]'); var viewport=root.querySelector('.mk-carousel-viewport'); var idx=0,n=slides.length,timer=null;
    function setH(){ if(viewport&&slides[idx]) viewport.style.height=slides[idx].offsetHeight+'px'; }
    var delay=parseInt(root.getAttribute('data-autoplay')||'0',10); var dots=[];
    if(dotsWrap){for(var i=0;i<n;i++){(function(i){var d=document.createElement('button');
      d.className='mk-dot'+(i===0?' mk-active':'');d.setAttribute('aria-label','Go to slide '+(i+1));
      d.addEventListener('click',function(){go(i);reset();});dotsWrap.appendChild(d);dots.push(d);})(i);}}
    function go(i){idx=(i+n)%n;track.style.transform='translateX('+(-idx*100)+'%)';
      dots.forEach(function(d,di){d.classList.toggle('mk-active',di===idx);});setH();}
    function next(){go(idx+1);} function prev(){go(idx-1);}
    function reset(){if(!delay)return;clearInterval(timer);timer=setInterval(next,delay);}
    var nb=root.querySelector('[data-next]'),pb=root.querySelector('[data-prev]');
    if(nb)nb.addEventListener('click',function(){next();reset();});
    if(pb)pb.addEventListener('click',function(){prev();reset();});
    if(n<2){if(nb)nb.style.display='none';if(pb)pb.style.display='none';if(dotsWrap)dotsWrap.style.display='none';}
    root.addEventListener('mouseenter',function(){clearInterval(timer);});
    root.addEventListener('mouseleave',reset);
    setH(); window.addEventListener('resize',setH); window.addEventListener('load',setH);
    reset();
  });
})();

// Hero typing effect (same behaviour as the homepage)
(function(){
  var el=document.getElementById('mk-typing'); if(!el) return;
  var words=(el.getAttribute('data-words')||'').split(',').map(function(w){return w.trim();}).filter(Boolean);
  if(!words.length) return;
  var wi=0,ci=0,del=false;
  (function tick(){
    var w=words[wi];
    el.textContent=w.slice(0,ci);
    if(!del && ci<w.length){ci++;setTimeout(tick,70);}
    else if(!del){del=true;setTimeout(tick,1400);}
    else if(ci>0){ci--;setTimeout(tick,32);}
    else{del=false;wi=(wi+1)%words.length;setTimeout(tick,300);}
  })();
})();
</script>
<?php wp_footer(); ?>
</body>
</html>
<?php exit; ?>
