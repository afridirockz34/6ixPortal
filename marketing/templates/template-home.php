<?php
/**
 * Template Name: 6ix — Home
 * Template Post Type: page
 *
 * Redesigned homepage. Section order and copy mirror the original site
 * verbatim (SEO-preserving); the Marketing OS band, blog teaser and portal
 * CTAs are ADDITIONS. Every section is editable via ACF; defaults below keep
 * the page complete before any field is filled.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

remove_action( 'wp_head', 'et_divi_load_scripts' );
remove_action( 'wp_head', 'et_load_custom_scripts' );

$orig = 'https://6ixdevelopers.com/';

// ── Original copy as defaults (verbatim) ────────────────────────────────
$typing_words = mk_field( 'hero_typing_words', 'PPC Management, Search Engine Marketing, Paid Social Media Advertising, Website Page Speed Optimization, Email Marketing' );

$svc_cards = mk_field( 'svc_cards', array(
    array( 'image' => $orig.'media/icons/ps-web.png', 'title' => 'Website Design',  'text' => 'Professional websites designed to run optimally across all devices', 'link' => '/website-design-agency-toronto' ),
    array( 'image' => $orig.'media/icons/ps-ads.png', 'title' => 'Google Ads/PPC',  'text' => 'Managed by Google Ads specialists to help your business rank #1', 'link' => '/ppc-google-ads-management-toronto' ),
    array( 'image' => $orig.'media/icons/ps-seo.png', 'title' => 'SEO',             'text' => 'Rank higher in organic search results and drive more traffic to your website', 'link' => '/seo-agency-toronto' ),
    array( 'image' => $orig.'media/icons/ps-smm.png', 'title' => 'Social Media',    'text' => 'Customized campaigns and blogs to grow your online presence', 'link' => '/social-media-marketing-agency-toronto' ),
) );

// Client Success — from the "Client Success" post type (add/edit/delete in
// wp-admin). Falls back to the original data until stories are created.
$ph = 'https://placehold.co/256x256/eef1f7/6b7688?text=Client';
$cs_slides = mk_success_items( array(
    array( 'title' => 'Criminal Law Firm',               'period' => '2024, Q3 - Q4', 'conv' => '16.50%', 'ctr' => '6.80%',  'cpl' => '$125.70', 'image' => $ph ),
    array( 'title' => 'Family Law Firm',                 'period' => '2024, Q3 - Q4', 'conv' => '19.10%', 'ctr' => '7.40%',  'cpl' => '$104.84', 'image' => $ph ),
    array( 'title' => 'Employment Law Firm',             'period' => '2024, Q3 - Q4', 'conv' => '22.10%', 'ctr' => '6.30%',  'cpl' => '$61.21',  'image' => $ph ),
    array( 'title' => 'Mortgage Agency',                 'period' => '2024, Q3 - Q4', 'conv' => '18.80%', 'ctr' => '24.10%', 'cpl' => '$19.64',  'image' => $ph ),
    array( 'title' => 'Custom Apparel Printing Company', 'period' => '2024, Q3 - Q4', 'conv' => '8.70%',  'ctr' => '8.30%',  'cpl' => '$35.76',  'image' => $ph ),
    array( 'title' => 'Auto Mechanic Shop',              'period' => '2024, Q3 - Q4', 'conv' => '16.20%', 'ctr' => '10.30%', 'cpl' => '$25.84',  'image' => $ph ),
    array( 'title' => 'Restaurant',                      'period' => '2024, Q3 - Q4', 'conv' => '9.04%',  'ctr' => '22.04%', 'cpl' => '$9.95',   'image' => $ph ),
) );

$deepdives = mk_field( 'deepdives', array(
    array( 'eyebrow' => 'Website Design', 'title' => 'Responsive Website Design',
        'text' => "Our website designs are optimized for lead generation and lead capturing. Our website designs are flexible and responsive on multiple platforms, devices, and browsers. While designing a website, we consider all important SEO aspects to help your website rank faster and higher than your competitors on Google search. Our striking website designs are developed in-house by experienced web designers. They are specifically designed for your potential clients to take action on the website, whether it's making a phone call or submitting a form.",
        'cta_label' => 'Learn More', 'cta_url' => '/website-design-agency-toronto', 'image' => 'https://placehold.co/640x460/E8547A/ffffff?text=Website+Design' ),
    array( 'eyebrow' => 'Google Ads', 'title' => 'Google Ads/PPC',
        'text' => 'Google Ads, also known as PPC, is one of the most versatile and scalable platforms to advertise your business. This form of business advertising is primarily used to bring immediate return on investment. Our Google Ads/PPC certified experts specialize in designing a marketing solution for your business that best suits your needs. Regardless of your marketing budget size, we ensure that every dollar spent on marketing returns the best possible results for your business to leverage.',
        'cta_label' => 'Learn More', 'cta_url' => '/ppc-google-ads-management-toronto', 'image' => 'https://placehold.co/640x460/A855F7/ffffff?text=Google+Ads' ),
    array( 'eyebrow' => 'SEO', 'title' => 'SEO (Search Engine Marketing)',
        'text' => 'When we work with an SEO client, we ensure that the deliverables are set for long-term and consistent returns. Research shows that Google processes more than 3.5 billion searches every day. With the right exposure at the right time, your business can dominate the industry. Certified and experienced SEO experts in your industry at 6ix Developers can take your business to the next level. We achieve this by making your website rank on the first page of Google search. Customer satisfaction is our #1 priority. We have helped many businesses rank #1 on Google. Yours could be next!',
        'cta_label' => 'Learn More', 'cta_url' => '/seo-agency-toronto', 'image' => 'https://placehold.co/640x460/3C8FB5/ffffff?text=SEO' ),
    array( 'eyebrow' => 'Social Media', 'title' => 'Social Media Marketing/Management',
        'text' => "Social media is one of the most cost-effective yet powerful platforms to promote your services. Our Social Media specialists build a community on social media platforms that shares the same interests as your business. Being in front of your potential prospects all the time keeps your business/brand in their minds. So when they are ready to find someone, your business is the first name that comes to mind. If you don't currently have social media for your business or are just starting, hire us to jump-start your presence and compete with the top competitors in your industry.",
        'cta_label' => 'Learn More', 'cta_url' => '/social-media-marketing-agency-toronto', 'image' => 'https://placehold.co/640x460/83C5ED/0b2233?text=Social+Media' ),
) );

$testimonials = mk_testimonial_items( array(
    array( 'quote' => 'I am very thankful to 6ix Developers for their services. I am super happy with my website and Google Ads. Coming from a bad experience, they made me feel comfortable and kept me in the loop with the whole progress of the website. Also I would like to thank Musab for suggesting and building a business plan for me and setting my business up with Google Ads. Much appreciated.', 'name' => 'Annie C.', 'role' => '' ),
    array( 'quote' => 'I will definitely recommend this company to everybody who wants a professional and perfect website for their business. I am so impressed with their work, and my website came out perfect.', 'name' => 'Elidrissia H.', 'role' => '' ),
    array( 'quote' => '6ix Developers did a great job of meeting our needs and helping us design the site we wanted. They were able to implement all of our requests, and contributed great ideas. Thanks a lot. Most recommended web developers.', 'name' => 'Barnard S.', 'role' => '' ),
    array( 'quote' => "6ix Developers has handled our SEO for over five years now, and have been a key partner in our growth. We were a startup when we first started working together, and they respected our smaller budget and worked to get us the best return on investment. Now that we're established, we know that we are in good hands as we market our company in a very competitive online environment. 5 stars for 6ix Developers.", 'name' => 'Momi K.', 'role' => '' ),
) );

$logos = mk_field( 'client_logos', array(
    array( 'image' => $orig.'media/clients/1..jpg' ),
    array( 'image' => $orig.'media/clients/4..jpg' ),
    array( 'image' => $orig.'media/clients/5..jpg' ),
    array( 'image' => $orig.'media/clients/6..jpg' ),
    array( 'image' => $orig.'media/clients/11..jpeg' ),
    array( 'image' => $orig.'media/clients/13..jpg' ),
    array( 'image' => $orig.'media/clients/14..jpg' ),
) );

$blog_posts = get_posts( array( 'numberposts' => intval( mk_field( 'blog_count', 3 ) ), 'post_status' => 'publish' ) );

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

  <!-- HERO (original copy, verbatim) -->
  <section class="mk-hero mk-glow">
    <div class="mk-aurora" aria-hidden="true"><span class="mk-aurora-a"></span><span class="mk-aurora-b"></span><span class="mk-aurora-c"></span></div>
    <div class="mk-wrap">
      <div class="mk-hero-inner">
        <h1><?php echo esc_html( mk_field( 'hero_heading', 'Discover The Difference' ) ); ?>
            <span class="mk-grad-text" style="display:block;font-size:.62em;margin-top:8px"><?php echo esc_html( mk_field( 'hero_subheading', '6ix Developers can make' ) ); ?></span></h1>
        <p class="mk-lead"><?php echo esc_html( mk_field( 'hero_lead', 'Elevate your marketing through industry-leading:' ) ); ?><br>
          <span class="mk-typing" id="mk-typing" data-words="<?php echo esc_attr( $typing_words ); ?>"></span></p>
        <div class="mk-hero-cta">
          <a class="mk-btn mk-btn-primary mk-btn-lg" href="<?php echo esc_url( home_url( mk_field( 'hero_cta1_url', '/contact-us' ) ) ); ?>"><?php mk_e( 'hero_cta1_label', 'Get your free consultation' ); ?></a>
          <a class="mk-btn mk-btn-ghost mk-btn-lg" href="<?php echo esc_url( mk_portal_url() ); ?>"><?php mk_e( 'hero_cta2_label', 'Find out how your business is doing' ); ?></a>
        </div>
      </div>
    </div>
  </section>

  <!-- HOW 6IX DEVELOPERS CAN HELP YOUR BUSINESS (original copy + icons) -->
  <section class="mk-section" style="padding-top:64px">
    <div class="mk-wrap">
      <div class="mk-sec-head mk-center mk-full">
        <h2><?php echo esc_html( mk_field( 'svc_heading', 'How 6ix Developers Can Help Your Business' ) ); ?></h2>
        <p class="mk-lead"><?php echo esc_html( mk_field( 'svc_intro', "6ix Developers is a full stack digital marketing agency with experienced and Google-certified staff. Our team members are specialized in Website Designs that are optimized for lead generation and lead capturing. We have Google Ads (aka. PPC) experts who are Google certified and experienced enough to take your business to another level. Our SEO team can help your business with organic ranking on Google and other search engines. Our Social Media team can show your business the opportunities it deserves. Secret to your success is in our expert team's hands who is fully invested in learning and understanding your business to help it grow exponentially." ) ); ?></p>
      </div>
      <div class="mk-grid mk-grid-4">
        <?php foreach ( (array) $svc_cards as $c ) : ?>
        <a class="mk-card mk-card-accent mk-svc-card" href="<?php echo esc_url( home_url( $c['link'] ?? '#' ) ); ?>">
          <?php if ( ! empty( $c['image'] ) ) : ?>
          <div class="mk-card-img"><img src="<?php echo esc_url( $c['image'] ); ?>" alt="<?php echo esc_attr( $c['title'] ?? '' ); ?>"></div>
          <?php endif; ?>
          <h3><?php echo esc_html( $c['title'] ?? '' ); ?></h3>
          <p><?php echo esc_html( $c['text'] ?? '' ); ?></p>
          <span class="mk-card-link">Learn More
            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          </span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- CLIENT SUCCESS — auto-scrolling slider (one at a time), editable via CPT -->
  <section class="mk-section mk-section-sm mk-glow">
    <div class="mk-wrap">
      <div class="mk-sec-head mk-center">
        <span class="mk-eyebrow" style="justify-content:center"><?php mk_e( 'cs_eyebrow', 'We are Diverse & Experienced' ); ?></span>
        <h2><?php echo esc_html( mk_field( 'cs_heading', 'Client Success' ) ); ?></h2>
      </div>
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

  <!-- PORTAL CTA BAND (addition — pushes visitors to the portal) -->
  <?php mk_portal_band(); ?>

  <!-- OUR COMMITMENT (original copy) -->
  <section class="mk-section mk-section-sm">
    <div class="mk-wrap">
      <div class="mk-card mk-card-accent" style="padding:40px">
        <h2 style="font-size:clamp(1.6rem,2.6vw,2.2rem)"><?php echo esc_html( mk_field( 'commit_heading', 'Our Commitment To Helping Other Businesses' ) ); ?></h2>
        <p><?php echo esc_html( mk_field( 'commit_p1', "We strive to fully understand our client's business and industry before we begin a project. This is critical to build an online presence that meets our clients vision, and is relevant to their goals." ) ); ?></p>
        <p><?php echo esc_html( mk_field( 'commit_p2', 'We are transparent, and involve our clients through the whole process. We continuously seek feedback to ensure we are on the right track.' ) ); ?></p>
        <p style="font-weight:700;color:var(--mk-t1)"><?php echo esc_html( mk_field( 'commit_q', 'Could your business benefit from our services?' ) ); ?></p>
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:8px">
          <a class="mk-btn mk-btn-primary" href="<?php echo esc_url( home_url( '/contact-us' ) ); ?>"><?php mk_e( 'commit_cta1', 'Get free consultation' ); ?></a>
          <a class="mk-btn mk-btn-ghost" href="<?php echo esc_url( home_url( '/about-us' ) ); ?>"><?php mk_e( 'commit_cta2', 'Find out more about us' ); ?></a>
        </div>
      </div>
    </div>
  </section>

  <!-- WE CAN HELP YOUR BUSINESS WITH (original copy; equal columns + placeholder images) -->
  <section class="mk-section" style="padding-top:48px">
    <div class="mk-wrap">
      <div class="mk-sec-head mk-center">
        <h2><?php echo esc_html( mk_field( 'dd_heading', 'We Can Help Your Business With' ) ); ?></h2>
      </div>
      <div class="mk-dd-list">
      <?php foreach ( (array) $deepdives as $i => $d ) : ?>
      <div class="mk-card mk-dd-card">
        <div class="mk-dd-text" style="<?php echo $i % 2 ? 'order:2' : ''; ?>">
          <span class="mk-eyebrow"><?php echo esc_html( $d['eyebrow'] ?? '' ); ?></span>
          <h3 style="font-size:clamp(1.4rem,2.2vw,1.85rem)"><?php echo esc_html( $d['title'] ?? '' ); ?></h3>
          <p><?php echo esc_html( $d['text'] ?? '' ); ?></p>
          <?php if ( ! empty( $d['cta_label'] ) ) : ?>
          <a class="mk-btn mk-btn-ghost" href="<?php echo esc_url( home_url( $d['cta_url'] ?? '#' ) ); ?>" style="align-self:flex-start;margin-top:6px"><?php echo esc_html( $d['cta_label'] ); ?></a>
          <?php endif; ?>
        </div>
        <div class="mk-dd-media" style="<?php echo $i % 2 ? 'order:1' : ''; ?>">
          <?php if ( ! empty( $d['image'] ) ) : ?><img src="<?php echo esc_url( $d['image'] ); ?>" alt="<?php echo esc_attr( $d['title'] ?? '' ); ?>"><?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- WHAT OUR CLIENTS SAY — testimonial slider (editable via CPT) -->
  <section class="mk-section mk-section-sm mk-glow">
    <div class="mk-wrap">
      <div class="mk-sec-head mk-center">
        <h2><?php echo esc_html( mk_field( 'tst_heading', 'What Our Clients Say' ) ); ?></h2>
      </div>
      <div class="mk-carousel" data-carousel data-autoplay="6000">
        <button class="mk-carousel-arrow mk-prev" data-prev aria-label="Previous"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg></button>
        <button class="mk-carousel-arrow mk-next" data-next aria-label="Next"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></button>
        <div class="mk-carousel-viewport">
          <div class="mk-carousel-track" data-track>
            <?php foreach ( (array) $testimonials as $t ) :
                $nm = $t['name'] ?? 'Client'; ?>
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

  <?php if ( ! empty( $blog_posts ) ) : ?>
  <!-- FROM THE BLOG (addition) -->
  <section class="mk-section mk-section-sm">
    <div class="mk-wrap">
      <div class="mk-center" style="max-width:640px;margin:0 auto 34px">
        <span class="mk-eyebrow" style="justify-content:center">Insights</span>
        <h2><?php echo esc_html( mk_field( 'blog_heading', 'From the Blog' ) ); ?></h2>
      </div>
      <div class="mk-grid mk-grid-3">
        <?php foreach ( $blog_posts as $bp ) : ?>
        <a class="mk-card mk-post" href="<?php echo esc_url( get_permalink( $bp ) ); ?>">
          <div class="mk-post-thumb"><?php if ( has_post_thumbnail( $bp ) ) echo get_the_post_thumbnail( $bp, 'medium_large' ); ?></div>
          <div class="mk-post-body">
            <span class="mk-post-date"><?php echo esc_html( get_the_date( '', $bp ) ); ?></span>
            <h3><?php echo esc_html( get_the_title( $bp ) ); ?></h3>
            <p><?php echo esc_html( wp_trim_words( get_the_excerpt( $bp ), 22 ) ); ?></p>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- FINAL CTA (original copy) -->
  <section class="mk-section mk-glow">
    <div class="mk-wrap mk-center" style="max-width:760px">
      <h2 class="mk-grad-text"><?php echo esc_html( mk_field( 'final_heading', 'Ready to find out what sets 6ix Developers apart?' ) ); ?></h2>
      <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap;margin-top:10px">
        <a class="mk-btn mk-btn-primary mk-btn-lg" href="<?php echo esc_url( home_url( mk_field( 'final_cta_url', '/contact-us' ) ) ); ?>"><?php mk_e( 'final_cta_label', 'Get free consultation now' ); ?></a>
        <a class="mk-btn mk-btn-ghost mk-btn-lg" href="<?php echo esc_url( mk_portal_url() ); ?>">See where your business can grow</a>
      </div>
    </div>
  </section>

  <?php include SIX_MK_DIR . 'partials/footer.php'; ?>

</div>

<script>
// Sliders: one slide at a time, auto-scrolling, arrows + dots.
(function(){
  document.querySelectorAll('[data-carousel]').forEach(function(root){
    var track=root.querySelector('[data-track]');
    var slides=track?track.children:[];
    if(!track||slides.length===0) return;
    var dotsWrap=root.querySelector('[data-dots]');
    var viewport=root.querySelector('.mk-carousel-viewport');
    var idx=0, n=slides.length, timer=null;
    function setH(){ if(viewport&&slides[idx]) viewport.style.height=slides[idx].offsetHeight+'px'; }
    var delay=parseInt(root.getAttribute('data-autoplay')||'0',10);

    var dots=[];
    if(dotsWrap){
      for(var i=0;i<n;i++){(function(i){
        var d=document.createElement('button');
        d.className='mk-dot'+(i===0?' mk-active':'');
        d.setAttribute('aria-label','Go to slide '+(i+1));
        d.addEventListener('click',function(){go(i);reset();});
        dotsWrap.appendChild(d);dots.push(d);
      })(i);}
    }
    function go(i){
      idx=(i+n)%n;
      track.style.transform='translateX('+(-idx*100)+'%)';
      dots.forEach(function(d,di){d.classList.toggle('mk-active',di===idx);});
      setH();
    }
    function next(){go(idx+1);} function prev(){go(idx-1);}
    function reset(){ if(!delay)return; clearInterval(timer); timer=setInterval(next,delay); }
    var nb=root.querySelector('[data-next]'), pb=root.querySelector('[data-prev]');
    if(nb)nb.addEventListener('click',function(){next();reset();});
    if(pb)pb.addEventListener('click',function(){prev();reset();});
    if(n<2){ if(nb)nb.style.display='none'; if(pb)pb.style.display='none'; if(dotsWrap)dotsWrap.style.display='none'; }
    root.addEventListener('mouseenter',function(){clearInterval(timer);});
    root.addEventListener('mouseleave',reset);
    setH(); window.addEventListener('resize',setH); window.addEventListener('load',setH);
    reset();
  });
})();

// Hero typing effect (same behaviour as the original site)
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
