<?php
/**
 * 6ixDevPortal — index.php (standalone-theme fallback)
 *
 * Renders the blog archive and single posts in the marketing design system.
 * Any content without a more specific template lands here.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$mk_helpers = get_stylesheet_directory() . '/marketing/helpers.php';
if ( file_exists( $mk_helpers ) && ! function_exists( 'mk_field' ) ) require_once $mk_helpers;
if ( ! defined( 'SIX_MK_DIR' ) ) {
    define( 'SIX_MK_DIR', get_stylesheet_directory() . '/marketing/' );
    define( 'SIX_MK_URL', get_stylesheet_directory_uri() . '/marketing/' );
}

// Make sure the marketing CSS loads even though this isn't a page template
add_action( 'wp_head', function () {
    $css = SIX_MK_DIR . 'assets/marketing.css';
    if ( file_exists( $css ) ) {
        echo '<link rel="stylesheet" href="' . esc_url( SIX_MK_URL . 'assets/marketing.css?v=' . filemtime( $css ) ) . '">' . "\n";
        echo '<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Mulish:ital,wght@0,400;0,600;0,700;0,800;1,400&family=Inter:wght@400;600;800&display=swap" rel="stylesheet">' . "\n";
    }
}, 1 );
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

<div class="six-mk">
  <?php
  // A single post opens straight into the hero band below with no
  // separate dark section above it, so the header must render solid from
  // the start (see the note in header.php) instead of the usual
  // transparent-until-scroll behaviour.
  if ( is_singular() ) $six_mk_header_solid = true;
  include SIX_MK_DIR . 'partials/header.php';
  ?>

  <?php if ( is_singular() ) : while ( have_posts() ) : the_post();
    $mk_blog_thumb = has_post_thumbnail() ? get_the_post_thumbnail_url( get_the_ID(), 'full' ) : '';
    $mk_blog_hero_bg = $mk_blog_thumb
        ? 'linear-gradient(180deg,rgba(3,21,35,.45),rgba(3,21,35,.82)),url(\'' . esc_url( $mk_blog_thumb ) . '\')'
        : 'var(--mk-grad)';
    $mk_blog_desc = get_the_excerpt() ?: wp_trim_words( get_the_content(), 30 );
  ?>
  <!-- SINGLE POST / PAGE — featured image as hero background, title +
       meta description over it, body content in a plain section below. -->
  <section class="mk-blog-hero" style="background-image:<?php echo esc_attr( $mk_blog_hero_bg ); ?>">
    <div class="mk-wrap">
      <div class="mk-blog-hero-inner">
        <span class="mk-eyebrow"><?php echo esc_html( get_the_date() ); ?></span>
        <h1><?php the_title(); ?></h1>
        <?php if ( $mk_blog_desc ) : ?><p class="mk-blog-hero-desc"><?php echo esc_html( $mk_blog_desc ); ?></p><?php endif; ?>
      </div>
    </div>
  </section>
  <article class="mk-section">
    <div class="mk-wrap" style="max-width:820px">
      <div class="mk-post-content" style="font-size:1.05rem;line-height:1.75">
        <?php the_content(); ?>
      </div>
    </div>
  </article>
  <?php endwhile; else : ?>
  <!-- BLOG ARCHIVE -->
  <section class="mk-section mk-glow" style="padding-top:64px">
    <div class="mk-wrap">
      <div class="mk-center" style="max-width:680px;margin:0 auto 44px">
        <span class="mk-eyebrow" style="justify-content:center">Insights</span>
        <h1 style="font-size:clamp(2rem,4vw,3rem)"><?php echo is_home() || is_front_page() ? 'From the Blog' : esc_html( wp_get_document_title() ); ?></h1>
        <p class="mk-lead">Marketing insights, playbooks and news from the 6ix Developers team.</p>
      </div>
      <?php if ( have_posts() ) : ?>
      <div class="mk-grid mk-grid-3">
        <?php while ( have_posts() ) : the_post(); ?>
        <a class="mk-card mk-post" href="<?php the_permalink(); ?>">
          <div class="mk-post-thumb"><?php if ( has_post_thumbnail() ) the_post_thumbnail( 'medium_large' ); ?></div>
          <div class="mk-post-body">
            <span class="mk-post-date"><?php echo esc_html( get_the_date() ); ?></span>
            <h3><?php the_title(); ?></h3>
            <p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></p>
          </div>
        </a>
        <?php endwhile; ?>
      </div>
      <div style="display:flex;justify-content:center;gap:12px;margin-top:36px">
        <?php previous_posts_link( '&larr; Newer posts' ); ?>
        <?php next_posts_link( 'Older posts &rarr;' ); ?>
      </div>
      <?php else : ?>
      <div class="mk-card mk-center" style="padding:56px">
        <h3>No posts yet</h3>
        <p style="color:var(--mk-t3)">Articles will appear here as soon as they're published.</p>
      </div>
      <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php mk_portal_band(); ?>
  <?php include SIX_MK_DIR . 'partials/footer.php'; ?>
</div>

<?php wp_footer(); ?>
</body>
</html>
