<?php
/**
 * The questionnaire as a standalone page, opened from a shareable link.
 *
 * A client who has signed still has to answer the strategy questions, and
 * requiring a portal password to do it is the surest way for that never to
 * happen. An advisor copies a link from the client's profile and emails it;
 * this renders the same questionnaire, saves to the same columns, and is
 * reachable without logging in.
 *
 * What the link does NOT do: grant a session, or reach anything other than
 * that one client's questionnaire. It carries no credentials and sets no
 * auth cookie.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** Register /seo-questionnaire/ without needing a WP page to exist. */
add_action( 'init', function () {
	add_rewrite_rule( '^seo-questionnaire/?$', 'index.php?six_questionnaire=1', 'top' );
	// Flush once when the rule is first introduced, rather than on every load.
	if ( get_option( 'six_questionnaire_rewrite' ) !== '1' ) {
		flush_rewrite_rules( false );
		update_option( 'six_questionnaire_rewrite', '1' );
	}
} );

add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'six_questionnaire';
	return $vars;
} );

add_action( 'template_redirect', function () {
	if ( ! get_query_var( 'six_questionnaire' ) ) return;

	$token     = isset( $_GET['k'] ) ? sanitize_text_field( wp_unslash( $_GET['k'] ) ) : '';
	$client_id = function_exists( 'six_questionnaire_token_client' )
		? six_questionnaire_token_client( $token ) : 0;

	// A signed-in client can open the page without a token — useful when they
	// followed an expired link and then logged in.
	if ( ! $client_id && is_user_logged_in() ) {
		$me = get_current_user_id();
		if ( class_exists( 'Six_Roles' ) && Six_Roles::get_portal_role( $me ) === 'six_customer' ) {
			$client_id = $me;
		}
	}

	status_header( $client_id ? 200 : 403 );
	nocache_headers();
	six_render_questionnaire_page( $client_id );
	exit;
} );

function six_render_questionnaire_page( $client_id ) {
	$user = $client_id ? get_userdata( $client_id ) : null;

	if ( ! $user ) {
		six_questionnaire_page_shell( 'Link expired', function () {
			echo '<p style="font-size:14px;color:#3D4757;line-height:1.7">This questionnaire link has expired or isn\'t valid any more.</p>'
			   . '<p style="font-size:14px;color:#3D4757;line-height:1.7">Ask your advisor to send a fresh one, or sign in to your portal and open <strong>Questionnaire</strong> there.</p>'
			   . '<p style="margin-top:24px"><a href="' . esc_url( home_url( '/portal/' ) ) . '" style="display:inline-block;background:#FF6699;color:#fff;text-decoration:none;font-weight:700;font-size:14px;padding:12px 22px;border-radius:8px">Sign in to the portal</a></p>';
		} );
		return;
	}

	global $wpdb;
	$slugs = (array) $wpdb->get_col( $wpdb->prepare(
		"SELECT service_slug FROM {$wpdb->prefix}six_client_services WHERE client_id=%d AND status='active'",
		$client_id
	) );

	$schema   = six_seo_questionnaire_for_client( $slugs, 'strategy' );
	$answers  = six_seo_questionnaire_answers( $client_id, $slugs, 'strategy' );
	$progress = six_seo_questionnaire_progress( $client_id, $slugs, 'strategy' );
	$nonce    = wp_create_nonce( 'six_questionnaire_public_' . $client_id );
	$biz      = '';
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT business_name FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d", $client_id ) );
	if ( $row ) $biz = $row->business_name;

	six_questionnaire_page_shell( 'SEO Strategy Questionnaire', function () use ( $schema, $answers, $progress, $client_id, $nonce, $biz ) {
		?>
		<p style="font-size:14.5px;color:#3D4757;line-height:1.7;margin:0 0 6px">
			<?php echo $biz ? 'For <strong>' . esc_html( $biz ) . '</strong>. ' : ''; ?>Everything we need to build your SEO strategy.
		</p>
		<p style="font-size:13.5px;color:#627080;line-height:1.7;margin:0 0 26px">
			It saves as you type, so you can close this and come back to the same link. Anything you'd rather talk through, leave blank and we'll cover it on the call.
		</p>

		<div style="background:#F1F3F8;border-radius:12px;padding:16px 18px;margin-bottom:28px">
			<div style="display:flex;align-items:baseline;justify-content:space-between;gap:12px;margin-bottom:9px">
				<span style="font-size:13px;color:#3D4757" id="q-status"><?php echo intval( $progress['done'] ); ?> of <?php echo intval( $progress['total'] ); ?> answered</span>
				<span style="font-size:18px;font-weight:800;color:#101826" id="q-pct"><?php echo intval( $progress['percent'] ); ?>%</span>
			</div>
			<div style="height:7px;border-radius:5px;background:#E4E8F0;overflow:hidden">
				<div id="q-bar" style="height:100%;width:<?php echo intval( $progress['percent'] ); ?>%;border-radius:5px;background:linear-gradient(90deg,#FF6699,#6ACAFD);transition:width .5s"></div>
			</div>

			<?php /* A bare percentage is a dead end — at 95% there is no way to
			         tell which questions are blank. Name them. */ ?>
			<div id="q-outstanding" style="margin-top:14px;<?php echo empty( $progress['outstanding'] ) ? 'display:none' : ''; ?>">
				<div style="font-size:12.5px;font-weight:700;color:#101826;margin-bottom:6px" id="q-outstanding-head">Still to answer<?php echo empty( $progress['outstanding'] ) ? '' : ' (' . count( $progress['outstanding'] ) . ')'; ?></div>
				<div id="q-outstanding-list" style="display:flex;flex-direction:column;gap:4px">
					<?php foreach ( (array) $progress['outstanding'] as $o ) : ?>
					<a href="#q_<?php echo esc_attr( $o['key'] ); ?>" data-jump="<?php echo esc_attr( $o['key'] ); ?>" style="font-size:12.5px;color:#3D4757;text-decoration:none;display:flex;gap:6px">
						<span style="color:#627080"><?php echo esc_html( $o['section'] ); ?> —</span>
						<span style="text-decoration:underline;text-underline-offset:2px"><?php echo esc_html( $o['label'] ); ?></span>
					</a>
					<?php endforeach; ?>
				</div>
			</div>
			<div id="q-submitted-note" style="margin-top:12px;font-size:12.5px;color:#1B9E52;<?php echo six_strategy_questionnaire_submitted( $client_id ) ? '' : 'display:none'; ?>">Submitted — your advisor has it. You can still change an answer here.</div>
		</div>

		<form id="six-questionnaire">
		<?php foreach ( $schema as $section ) :
			// Everything the audit already captured at signup is filled in
			// and shown read-only context rather than asked again here.
			$visible = array_filter( $section['fields'], function ( $f ) use ( $answers ) {
				return empty( $answers[ $f['key'] ]['from_onboarding'] ) || empty( $answers[ $f['key'] ]['answered'] );
			} );
			if ( ! $visible ) continue; ?>
			<h2 style="font-size:17px;font-weight:800;color:#101826;margin:34px 0 4px"><?php echo esc_html( $section['title'] ); ?></h2>
			<?php if ( ! empty( $section['intro'] ) ) : ?>
			<p style="font-size:13px;color:#627080;line-height:1.6;margin:0 0 18px"><?php echo esc_html( $section['intro'] ); ?></p>
			<?php endif; ?>

			<?php foreach ( $visible as $f ) :
				$ans = $answers[ $f['key'] ] ?? array( 'value' => '' );
				$val = $ans['value'];
				$fid = 'q_' . esc_attr( $f['key'] ); ?>
			<div style="margin-bottom:22px">
				<label for="<?php echo $fid; ?>" style="display:block;font-size:13.5px;font-weight:600;color:#101826;margin-bottom:5px">
					<?php echo esc_html( $f['label'] ); ?>
					<?php if ( ! empty( $f['optional'] ) ) : ?><span style="font-weight:400;font-size:11.5px;color:#627080">optional</span><?php endif; ?>
				</label>
				<?php if ( ! empty( $f['help'] ) ) : ?>
				<div style="font-size:12px;color:#627080;line-height:1.5;margin-bottom:7px"><?php echo esc_html( $f['help'] ); ?></div>
				<?php endif; ?>
				<?php $style = 'width:100%;box-sizing:border-box;padding:11px 13px;font:inherit;font-size:14px;color:#101826;background:#fff;border:1.5px solid #d7dce4;border-radius:8px'; ?>
				<?php if ( ( $f['type'] ?? 'text' ) === 'textarea' ) : ?>
				<textarea id="<?php echo $fid; ?>" data-q="<?php echo esc_attr( $f['key'] ); ?>" rows="3" style="<?php echo $style; ?>;resize:vertical" placeholder="<?php echo esc_attr( $f['placeholder'] ?? '' ); ?>"><?php echo esc_textarea( $val ); ?></textarea>
				<?php elseif ( ( $f['type'] ?? '' ) === 'select' ) : ?>
				<select id="<?php echo $fid; ?>" data-q="<?php echo esc_attr( $f['key'] ); ?>" style="<?php echo $style; ?>">
					<option value="">Choose one…</option>
					<?php foreach ( (array) $f['options'] as $opt ) : ?>
					<option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $val, $opt ); ?>><?php echo esc_html( $opt ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php else : ?>
				<input id="<?php echo $fid; ?>" data-q="<?php echo esc_attr( $f['key'] ); ?>" type="text" value="<?php echo esc_attr( $val ); ?>" placeholder="<?php echo esc_attr( $f['placeholder'] ?? '' ); ?>" style="<?php echo $style; ?>">
				<?php endif; ?>
			</div>
			<?php endforeach; ?>
		<?php endforeach; ?>
		</form>

		<div style="position:sticky;bottom:0;background:#fff;border-top:1px solid #E4E8F0;padding:14px 0;margin-top:30px;display:flex;align-items:center;gap:14px;flex-wrap:wrap">
			<button id="q-submit" style="background:#FF6699;color:#fff;border:0;font:inherit;font-weight:700;font-size:14px;padding:12px 24px;border-radius:8px;cursor:pointer"><?php echo six_strategy_questionnaire_submitted( $client_id ) ? 'Submit changes' : 'Submit questionnaire'; ?></button>
			<button id="q-save" style="background:#fff;color:#3D4757;border:1.5px solid #d7dce4;font:inherit;font-weight:600;font-size:14px;padding:11px 20px;border-radius:8px;cursor:pointer">Save and finish later</button>
			<span id="q-msg" style="font-size:13px;color:#1B9E52"></span>
		</div>

		<script>
		(function(){
		  var AJAX=<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		  var CLIENT=<?php echo (int) $client_id; ?>;
		  var NONCE=<?php echo wp_json_encode( $nonce ); ?>;
		  var TOKEN=<?php echo wp_json_encode( isset($_GET['k']) ? sanitize_text_field( wp_unslash($_GET['k']) ) : '' ); ?>;
		  var form=document.getElementById('six-questionnaire'), dirty={}, timer=null;
		  var msg=document.getElementById('q-msg');
		  var outWrap=document.getElementById('q-outstanding');
		  var outHead=document.getElementById('q-outstanding-head');
		  var outList=document.getElementById('q-outstanding-list');

		  function collect(all){
		    var out={};
		    form.querySelectorAll('[data-q]').forEach(function(el){
		      var k=el.getAttribute('data-q');
		      if(all||dirty[k]) out[k]=el.value;
		    });
		    return out;
		  }
		  function say(t,bad){ msg.style.color=bad?'#D93B3B':'#1B9E52'; msg.textContent=t; }
		  function mark(k,bad){ var el=document.getElementById('q_'+k); if(el) el.style.borderColor=bad?'#D93B3B':'#d7dce4'; }
		  function clearMarks(){ form.querySelectorAll('[data-q]').forEach(function(el){ el.style.borderColor='#d7dce4'; }); }
		  function jumpTo(k){
		    var el=document.getElementById('q_'+k); if(!el) return;
		    el.scrollIntoView({behavior:'smooth',block:'center'});
		    if(el.focus) try{ el.focus({preventScroll:true}); }catch(e){ el.focus(); }
		  }
		  function showProgress(p){
		    if(!p) return;
		    document.getElementById('q-pct').textContent=p.percent+'%';
		    document.getElementById('q-bar').style.width=p.percent+'%';
		    document.getElementById('q-status').textContent=p.done+' of '+p.total+' answered';
		    if(!outWrap) return;
		    var items=p.outstanding||[];
		    outWrap.style.display=items.length?'':'none';
		    outHead.textContent='Still to answer'+(items.length?' ('+items.length+')':'');
		    outList.textContent='';
		    items.forEach(function(o){
		      var a=document.createElement('a');
		      a.href='#q_'+o.key; a.setAttribute('data-jump',o.key);
		      a.style.cssText='font-size:12.5px;color:#3D4757;text-decoration:none;display:flex;gap:6px';
		      var s=document.createElement('span'); s.style.color='#627080'; s.textContent=o.section+' —';
		      var l=document.createElement('span'); l.style.cssText='text-decoration:underline;text-underline-offset:2px'; l.textContent=o.label;
		      a.appendChild(s); a.appendChild(l); outList.appendChild(a);
		    });
		  }
		  // An answer the database refused is named, never reported as saved.
		  function reportFailures(failed){
		    var keys=Object.keys(failed||{});
		    if(!keys.length) return false;
		    keys.forEach(function(k){ mark(k,true); });
		    say(keys.length+(keys.length===1?' answer could not be saved':' answers could not be saved')+' — we have been told about it.',true);
		    jumpTo(keys[0]);
		    return true;
		  }
		  function save(all){
		    var answers=collect(all);
		    if(!Object.keys(answers).length) return;
		    fetch(AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
		      body:new URLSearchParams({action:'six_save_questionnaire_public',client_id:CLIENT,
		        nonce:NONCE,token:TOKEN,answers:JSON.stringify(answers)})
		    }).then(function(r){return r.json();}).then(function(r){
		      if(!(r&&r.success)){ say('Could not save — please try again',true); return; }
		      var d=r.data||{};
		      (d.saved||[]).forEach(function(k){ mark(k,false); delete dirty[k]; });
		      showProgress(d.progress);
		      if(reportFailures(d.failed)) return;
		      say('Saved',false);
		      setTimeout(function(){ if(msg.textContent==='Saved') msg.textContent=''; },2600);
		    }).catch(function(){ say('Could not save — please try again',true); });
		  }
		  form.addEventListener('input',function(e){
		    var k=e.target.getAttribute&&e.target.getAttribute('data-q'); if(!k) return;
		    dirty[k]=true; clearTimeout(timer); timer=setTimeout(function(){save(false);},1200);
		  });
		  form.addEventListener('change',function(e){
		    var k=e.target.getAttribute&&e.target.getAttribute('data-q');
		    if(k){ dirty[k]=true; save(false); }
		  });
		  document.getElementById('q-save').addEventListener('click',function(e){ e.preventDefault(); save(true); });

		  if(outWrap) outWrap.addEventListener('click',function(e){
		    var a=e.target.closest&&e.target.closest('[data-jump]');
		    if(!a) return;
		    e.preventDefault(); jumpTo(a.getAttribute('data-jump'));
		  });

		  var btn=document.getElementById('q-submit');
		  btn.addEventListener('click',function(e){
		    e.preventDefault();
		    btn.disabled=true;
		    var label=btn.textContent;
		    btn.textContent='Submitting…';
		    fetch(AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
		      body:new URLSearchParams({action:'six_submit_questionnaire_public',client_id:CLIENT,
		        nonce:NONCE,token:TOKEN,answers:JSON.stringify(collect(true))})
		    }).then(function(r){return r.json();}).then(function(r){
		      var d=(r&&r.data)||{};
		      showProgress(d.progress);
		      if(r&&r.success){
		        dirty={};
		        if(reportFailures(d.failed)) return;
		        clearMarks();
		        var note=document.getElementById('q-submitted-note');
		        if(note){ note.textContent='Submitted — your advisor has it. You can still change an answer here.'; note.style.display=''; }
		        btn.textContent='Submit changes';
		        say(d.resubmitted?'Your changes have been sent to your advisor.':'Thank you — your advisor has been notified.',false);
		      } else {
		        btn.textContent=label;
		        if(reportFailures(d.failed)) return;
		        say(d.error||(typeof r.data==='string'?r.data:'Could not submit — please try again'),true);
		        if(d.outstanding&&d.outstanding.length) jumpTo(d.outstanding[0].key);
		      }
		    }).catch(function(){ btn.textContent=label; say('Could not submit — please try again',true); })
		      .finally(function(){ btn.disabled=false; });
		  });
		})();
		</script>
		<?php
	} );
}

/** Minimal standalone chrome — this page is seen by clients, outside the portal. */
function six_questionnaire_page_shell( $title, $body ) {
	$logo = function_exists( 'mk_opt' )
		? mk_opt( 'brand_logo', 'https://6ixdevelopers.com/media/logo/new-logo.png' )
		: 'https://6ixdevelopers.com/media/logo/new-logo.png';
	?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( $title ); ?> — 6ix Developers</title>
<style>
*{box-sizing:border-box}
body{margin:0;background:#F1F3F8;color:#101826;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;line-height:1.5}
.wrap{max-width:720px;margin:0 auto;padding:40px 20px 60px}
.card{background:#fff;border-radius:16px;padding:36px 34px;box-shadow:0 1px 4px rgba(15,25,35,.06),0 8px 30px rgba(15,25,35,.05)}
h1{font-size:25px;font-weight:800;letter-spacing:-.02em;margin:0 0 14px}
input:focus,textarea:focus,select:focus{outline:none;border-color:#FF6699;box-shadow:0 0 0 4px rgba(255,102,153,.12)}
@media(max-width:600px){.card{padding:26px 20px;border-radius:12px}.wrap{padding:22px 14px 50px}h1{font-size:21px}}
</style>
</head>
<body>
<div class="wrap">
  <img src="<?php echo esc_url( $logo ); ?>" alt="6ix Developers" style="height:38px;width:auto;display:block;margin:0 auto 26px">
  <div class="card">
    <h1><?php echo esc_html( $title ); ?></h1>
    <?php $body(); ?>
  </div>
  <p style="text-align:center;font-size:11.5px;color:#627080;margin-top:22px">
    6ix Developers · <a href="https://6ixdevelopers.com" style="color:#627080">6ixdevelopers.com</a>
  </p>
</div>
</body>
</html><?php
}
