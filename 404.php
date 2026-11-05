<?php
/**
 * 404 — page not found.
 *
 * There was no 404.php in this theme at all, so WordPress fell back to
 * index.php and the result was unstyled markup on a white band. This uses the
 * marketing design system, the same header and footer as every other public
 * page, and — more usefully — actually tries to get the visitor where they
 * were going instead of just apologizing.
 *
 * Three routes out, in the order a lost visitor is likely to want them:
 *   1. Search, because a mistyped or moved URL is usually a known page.
 *   2. The services they were probably looking for, named explicitly.
 *   3. A person to talk to.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

remove_action( 'wp_head', 'et_divi_load_scripts' );
remove_action( 'wp_head', 'et_load_custom_scripts' );

status_header( 404 );
nocache_headers();

/**
 * Guess what they wanted from the URL they asked for.
 *
 * A visitor landing on /seo-toronto/ or a dead /services/seo/ link is after
 * the SEO page, and saying so beats a generic list. Matching is on the
 * requested path only — never on anything that could be reflected back into
 * the page.
 */
$req_path = isset( $_SERVER['REQUEST_URI'] ) ? strtolower( (string) wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) ) : '';

$suggest = array();
$service_map = array(
    'seo-agency-toronto'                    => array( 'SEO',                     array( 'seo', 'organic', 'ranking', 'rank' ) ),
    'ppc-google-ads-management-toronto'     => array( 'Google Ads',              array( 'ppc', 'google-ads', 'googleads', 'adwords', 'paid', 'sem' ) ),
    'website-design-agency-toronto'         => array( 'Website Design',          array( 'web', 'website', 'design', 'development', 'wordpress' ) ),
    'social-media-marketing-agency-toronto' => array( 'Social Media Marketing',  array( 'social', 'instagram', 'facebook', 'meta' ) ),
    'digital-marketing-agency-toronto'      => array( 'Digital Marketing',       array( 'digital', 'marketing', 'agency' ) ),
);
foreach ( $service_map as $slug => $entry ) {
    foreach ( $entry[1] as $needle ) {
        if ( $req_path && strpos( $req_path, $needle ) !== false ) {
            $suggest[ $slug ] = $entry[0];
            break;
        }
    }
}
// Blog-ish or case-study-ish URLs get the matching index instead.
$extra_suggest = array();
if ( $req_path && preg_match( '#(case-stud|portfolio|client|result)#', $req_path ) ) {
    $extra_suggest['case-studies'] = 'Case Studies';
}
if ( $req_path && preg_match( '#(blog|news|article|post|guide)#', $req_path ) ) {
    $extra_suggest['blog'] = 'Blog';
}

$links = array(
    array( 'url' => home_url( '/' ),                                      'label' => 'Home',               'desc' => 'Start from the top' ),
    array( 'url' => home_url( '/seo-agency-toronto/' ),                   'label' => 'SEO',                'desc' => 'Organic rankings and local search' ),
    array( 'url' => home_url( '/ppc-google-ads-management-toronto/' ),    'label' => 'Google Ads',         'desc' => 'Paid search and lead campaigns' ),
    array( 'url' => home_url( '/website-design-agency-toronto/' ),        'label' => 'Website Design',     'desc' => 'Sites built to convert' ),
    array( 'url' => home_url( '/case-studies/' ),                         'label' => 'Case Studies',       'desc' => 'Real client results' ),
    array( 'url' => home_url( '/contact-us/' ),                           'label' => 'Contact',            'desc' => 'Talk to a person' ),
);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, follow">
<title><?php echo esc_html( 'Page not found — ' . get_bloginfo( 'name' ) ); ?></title>
<?php wp_head(); ?>
<style>
/* Scoped to this page only. Everything else comes from marketing.css. */
.mk-404{padding:clamp(56px,9vw,110px) 0 clamp(64px,8vw,104px);}
.mk-404-in{max-width:960px;margin:0 auto;padding:0 16px;}
.mk-404-code{font-family:var(--mk-num);font-size:clamp(84px,19vw,190px);line-height:.84;font-weight:800;
  letter-spacing:-.045em;background:var(--mk-grad);-webkit-background-clip:text;background-clip:text;
  -webkit-text-fill-color:transparent;color:var(--mk-pink);margin:0 0 6px;}
.mk-404-eye{font-family:var(--mk-body);font-size:.78rem;font-weight:800;letter-spacing:.14em;
  text-transform:uppercase;color:var(--mk-t3);margin:0 0 20px;}
.mk-404-h1{font-family:var(--mk-head);font-size:clamp(1.65rem,4.4vw,2.6rem);line-height:1.14;
  font-weight:800;letter-spacing:-.02em;color:var(--mk-t1);margin:0 0 14px;max-width:22ch;}
.mk-404-lede{font-size:1.04rem;line-height:1.72;color:var(--mk-t2);margin:0 0 30px;max-width:56ch;}
.mk-404-guess{display:flex;flex-wrap:wrap;gap:10px;align-items:center;background:var(--mk-surface);
  border:1px solid var(--mk-border);border-left:3px solid var(--mk-pink);border-radius:12px;
  padding:16px 18px;margin:0 0 30px;}
.mk-404-guess-l{font-size:.93rem;color:var(--mk-t2);font-weight:600;}
.mk-404-search{display:flex;gap:10px;margin:0 0 42px;max-width:560px;}
.mk-404-search input[type="search"]{flex:1;min-width:0;font-family:var(--mk-body);font-size:1rem;
  padding:14px 16px;border:1px solid var(--mk-border2);border-radius:10px;background:var(--mk-bg2);
  color:var(--mk-t1);}
.mk-404-search input[type="search"]:focus{outline:none;border-color:var(--mk-pink);
  box-shadow:0 0 0 3px rgba(255,102,153,.16);}
.mk-404-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(224px,1fr));gap:12px;}
.mk-404-card{display:block;text-decoration:none;background:var(--mk-surface);border:1px solid var(--mk-border);
  border-radius:12px;padding:17px 18px;transition:border-color .18s ease,transform .18s ease;}
.mk-404-card:hover,.mk-404-card:focus-visible{border-color:var(--mk-pink);transform:translateY(-2px);}
.mk-404-card b{display:block;font-family:var(--mk-head);font-size:1rem;font-weight:800;
  color:var(--mk-t1);margin-bottom:3px;}
.mk-404-card span{font-size:.86rem;color:var(--mk-t3);line-height:1.5;}
.mk-404 .mk-btn-sm{font-size:.86rem;padding:9px 15px;min-width:0;}
.mk-404 .screen-reader-text{position:absolute;width:1px;height:1px;padding:0;margin:-1px;
  overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;border:0;}
.mk-404-sep{font-family:var(--mk-head);font-size:.82rem;font-weight:800;letter-spacing:.1em;
  text-transform:uppercase;color:var(--mk-t3);margin:0 0 14px;}
@media (prefers-reduced-motion:reduce){.mk-404-card{transition:none;}.mk-404-card:hover{transform:none;}}
@media (max-width:560px){.mk-404-search{flex-direction:column;}.mk-404-search .mk-btn{width:100%;}}
</style>
</head>
<body <?php body_class( 'six-mk-body' ); ?>>

<div id="six-mk-root" class="six-mk six-mk--404">

  <?php include SIX_MK_DIR . 'partials/header.php'; ?>

  <main class="mk-404">
    <div class="mk-404-in">

      <p class="mk-404-eye">Error 404</p>
      <p class="mk-404-code" aria-hidden="true">404</p>

      <h1 class="mk-404-h1">We couldn&rsquo;t find that page.</h1>
      <p class="mk-404-lede">
        The link may be out of date, or the address might have a typo in it.
        Nothing is broken on your end &mdash; let&rsquo;s get you to the right place.
      </p>

      <?php if ( $suggest || $extra_suggest ) : ?>
        <div class="mk-404-guess">
          <span class="mk-404-guess-l">Looking for</span>
          <?php foreach ( array_slice( $suggest, 0, 2 ) as $slug => $label ) : ?>
            <a class="mk-btn mk-btn-primary mk-btn-sm" href="<?php echo esc_url( home_url( '/' . $slug . '/' ) ); ?>"><?php echo esc_html( $label ); ?></a>
          <?php endforeach; ?>
          <?php foreach ( array_slice( $extra_suggest, 0, 1 ) as $slug => $label ) : ?>
            <a class="mk-btn mk-btn-ghost mk-btn-sm" href="<?php echo esc_url( home_url( '/' . $slug . '/' ) ); ?>"><?php echo esc_html( $label ); ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form class="mk-404-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
        <label class="screen-reader-text" for="mk-404-s">Search this site</label>
        <input type="search" id="mk-404-s" name="s" placeholder="Search for a page or topic&hellip;" value="<?php echo esc_attr( get_search_query() ); ?>">
        <button class="mk-btn mk-btn-primary" type="submit">Search</button>
      </form>

      <p class="mk-404-sep">Or jump to</p>
      <div class="mk-404-grid">
        <?php foreach ( $links as $l ) : ?>
          <a class="mk-404-card" href="<?php echo esc_url( $l['url'] ); ?>">
            <b><?php echo esc_html( $l['label'] ); ?></b>
            <span><?php echo esc_html( $l['desc'] ); ?></span>
          </a>
        <?php endforeach; ?>
      </div>

    </div>
  </main>

  <?php
  // The same closing band every marketing page ends on, so a 404 still offers
  // the next step rather than dead-ending.
  if ( function_exists( 'mk_portal_band' ) ) {
      mk_portal_band( array(
          'eyebrow' => 'Since you’re here',
          'heading' => 'Want to know how your site is actually performing?',
          'text'    => 'Answer a few questions and we’ll show you where your search traffic is leaking, and what it would take to fix it.',
          'cta'     => 'Get your free audit',
      ) );
  }
  ?>

  <?php include SIX_MK_DIR . 'partials/footer.php'; ?>

</div>
<?php wp_footer(); ?>
</body>
</html>
