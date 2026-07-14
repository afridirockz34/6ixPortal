<?php
/**
 * Marketing header — transparent over the hero, turns navy (#031523) on
 * scroll with a logo swap. Phone shown as a button. Mobile = right-side
 * drawer. Content editable in WP Admin → 6ix Site.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$orig = 'https://6ixdevelopers.com/';
$brand_name  = mk_opt( 'brand_name', '6ix Developers' );
$logo_top    = mk_opt( 'brand_logo',          $orig . 'media/logo/new-logo.png' );        // over dark hero
$logo_scroll = mk_opt( 'brand_logo_scrolled', $orig . 'media/logo/new-logo-white.png' );  // over navy bar
$phone       = mk_opt( 'header_phone', '888-808-7265' );
$phone_href  = 'tel:' . preg_replace( '/[^0-9+]/', '', mk_opt( 'header_phone_tel', '18888087265' ) );
$services    = mk_opt( 'nav_services', array(
    array( 'label' => 'Website Design', 'url' => '/website-design-agency-toronto' ),
    array( 'label' => 'Google Ads/PPC', 'url' => '/ppc-google-ads-management-toronto' ),
    array( 'label' => 'Social Media',   'url' => '/social-media-marketing-agency-toronto' ),
    array( 'label' => 'SEO Services',   'url' => '/seo-agency-toronto' ),
) );
$contact_label = mk_opt( 'header_contact_label', 'Contact us' );
$contact_url   = mk_opt( 'header_contact_url', '/contact-us' );
$login_label   = mk_opt( 'header_login_label', 'Client Login' );
$mkurl = function ( $u ) { return ( strpos( $u, 'http' ) === 0 ) ? $u : home_url( $u ); };

// Login modal needs to talk to the same AJAX auth the /get-started/ page uses.
$mk_ajax_url    = admin_url( 'admin-ajax.php' );
$mk_login_nonce = wp_create_nonce( 'six_nonce' );
$mk_portal_url  = home_url( '/get-started/' );
?>
<header class="mk-header" id="mk-header">
  <div class="mk-wrap">
    <nav class="mk-nav" id="mk-nav">
      <a class="mk-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( $brand_name ); ?>">
        <img class="mk-logo-default"  src="<?php echo esc_url( $logo_top ); ?>"    alt="<?php echo esc_attr( $brand_name ); ?>">
        <img class="mk-logo-scrolled" src="<?php echo esc_url( $logo_scroll ); ?>" alt="<?php echo esc_attr( $brand_name ); ?>">
      </a>

      <button class="mk-nav-toggle" aria-label="Open menu" onclick="document.getElementById('mk-nav').classList.add('mk-open')">
        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>

      <div class="mk-nav-scrim" onclick="document.getElementById('mk-nav').classList.remove('mk-open')"></div>

      <div class="mk-nav-links">
        <button class="mk-nav-close" aria-label="Close menu" onclick="document.getElementById('mk-nav').classList.remove('mk-open')">
          <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2"><line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/></svg>
        </button>
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a>
        <div class="mk-drop">
          <a href="#" onclick="return false" aria-haspopup="true">Services
            <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-1px"><polyline points="6 9 12 15 18 9"/></svg>
          </a>
          <div class="mk-drop-menu">
            <?php foreach ( (array) $services as $s ) : if ( empty( $s['label'] ) ) continue; ?>
            <a href="<?php echo esc_url( $mkurl( $s['url'] ?? '#' ) ); ?>"><?php echo esc_html( $s['label'] ); ?></a>
            <?php endforeach; ?>
          </div>
        </div>
        <a href="<?php echo esc_url( home_url( '/about-us' ) ); ?>">About Us</a>
        <a href="<?php echo esc_url( $mkurl( $contact_url ) ); ?>"><?php echo esc_html( $contact_label ); ?></a>
        <div class="mk-nav-cta">
          <a class="mk-phone-btn" href="<?php echo esc_url( $phone_href ); ?>">
            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            <?php echo esc_html( $phone ); ?>
          </a>
          <button type="button" class="mk-btn mk-btn-primary" style="padding:9px 20px" data-mk-login-open><?php echo esc_html( $login_label ); ?></button>
        </div>
      </div>
    </nav>
  </div>
</header>

<!-- Client Login modal — mirrors the /get-started/ auth logic in a small box -->
<div class="mk-login-modal" id="mk-login-modal" aria-hidden="true">
  <div class="mk-login-scrim" data-mk-login-close></div>
  <div class="mk-login-box" role="dialog" aria-modal="true" aria-labelledby="mk-login-title">
    <button type="button" class="mk-login-x" aria-label="Close" data-mk-login-close>
      <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/></svg>
    </button>
    <h3 id="mk-login-title">Client Login</h3>
    <p class="mk-login-sub">Sign in to your marketing dashboard.</p>

    <div class="mk-login-social"><?php echo do_shortcode( '[nextend_social_login provider="google" style="fullwidth"]' ); ?></div>
    <div class="mk-login-or"><span>or</span></div>

    <form class="mk-login-form" id="mk-login-form" novalidate>
      <label class="mk-login-lbl" for="mk-login-email">Email address</label>
      <input type="email" id="mk-login-email" class="mk-login-input" autocomplete="email" placeholder="you@company.com" required>

      <div class="mk-login-pwwrap" id="mk-login-pwwrap" hidden>
        <label class="mk-login-lbl" for="mk-login-pw">Password</label>
        <input type="password" id="mk-login-pw" class="mk-login-input" autocomplete="current-password" placeholder="Your password">
        <a class="mk-login-forgot" href="<?php echo esc_url( $mk_portal_url ); ?>">Forgot password?</a>
      </div>

      <div class="mk-login-err" id="mk-login-err" role="alert"></div>
      <button type="submit" class="mk-btn mk-btn-primary mk-login-submit" id="mk-login-submit">Continue</button>
    </form>

    <p class="mk-login-foot">New to 6ix Developers? <a href="<?php echo esc_url( $mk_portal_url ); ?>">Get started free</a></p>
  </div>
</div>
<script>
(function(){
  var modal   = document.getElementById('mk-login-modal');
  if(!modal) return;
  var form    = document.getElementById('mk-login-form');
  var emailEl = document.getElementById('mk-login-email');
  var pwWrap  = document.getElementById('mk-login-pwwrap');
  var pwEl    = document.getElementById('mk-login-pw');
  var errEl   = document.getElementById('mk-login-err');
  var submit  = document.getElementById('mk-login-submit');
  var AJAX    = <?php echo wp_json_encode( $mk_ajax_url ); ?>;
  var NONCE   = <?php echo wp_json_encode( $mk_login_nonce ); ?>;
  var PORTAL  = <?php echo wp_json_encode( $mk_portal_url ); ?>;
  var stage   = 'email'; // 'email' -> 'password'

  function open(){ modal.classList.add('mk-on'); modal.setAttribute('aria-hidden','false'); document.body.style.overflow='hidden'; setTimeout(function(){emailEl.focus();},120); }
  function close(){ modal.classList.remove('mk-on'); modal.setAttribute('aria-hidden','true'); document.body.style.overflow=''; }
  function err(m){ errEl.textContent=m||''; errEl.classList.toggle('mk-on', !!m); }
  function busy(b){ submit.disabled=b; submit.textContent=b?'Please wait…':(stage==='email'?'Continue':'Sign in'); }

  document.querySelectorAll('[data-mk-login-open]').forEach(function(b){ b.addEventListener('click', function(e){ e.preventDefault(); open(); }); });
  modal.querySelectorAll('[data-mk-login-close]').forEach(function(b){ b.addEventListener('click', close); });
  document.addEventListener('keydown', function(e){ if(e.key==='Escape' && modal.classList.contains('mk-on')) close(); });

  function post(data){
    return fetch(AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:new URLSearchParams(Object.assign({nonce:NONCE},data))
    }).then(function(r){return r.json();}).catch(function(){return{success:false,data:'Network error'};});
  }

  form.addEventListener('submit', function(e){
    e.preventDefault();
    err('');
    var email=(emailEl.value||'').trim();
    if(!email || email.indexOf('@')<1){ err('Please enter a valid email address.'); return; }

    if(stage==='email'){
      busy(true);
      post({action:'six_check_email',email:email}).then(function(r){
        busy(false);
        if(r && r.success && r.data && r.data.exists){
          // Account exists → reveal the password field (same as /get-started/)
          stage='password';
          pwWrap.hidden=false;
          emailEl.setAttribute('readonly','readonly');
          submit.textContent='Sign in';
          setTimeout(function(){pwEl.focus();},60);
        } else {
          // No account (or Google account) → send them to onboarding
          window.location.href = PORTAL + '?email=' + encodeURIComponent(email);
        }
      });
      return;
    }

    // stage === 'password'
    var pw=(pwEl.value||'').trim();
    if(!pw){ err('Please enter your password.'); return; }
    busy(true);
    post({action:'six_portal_login',email:email,password:pw}).then(function(r){
      busy(false);
      if(!(r && r.success)){ err((r&&r.data)||'Incorrect email or password.'); return; }
      if(r.data.redirect_url){ window.location.href=r.data.redirect_url; return; }
      if(r.data.has_completed_checkout){ window.location.href='<?php echo esc_js( home_url( '/portal/' ) ); ?>'; return; }
      // Mid-onboarding customer → resume in the get-started flow
      window.location.href = PORTAL;
    });
  });
})();
</script>
<script>
(function(){
  var h=document.getElementById('mk-header');
  if(!h) return;
  var onScroll=function(){ h.classList.toggle('mk-scrolled', window.scrollY>40); };
  onScroll(); window.addEventListener('scroll', onScroll, {passive:true});
})();
</script>
