<?php
/**
 * Advisor → Connect Claude.
 *
 * Everything an advisor needs to point a Claude session at this portal for
 * SEO work: the endpoint, a token they can mint themselves, and the setup
 * steps. Previously this lived only in WP Admin → API Tokens, which advisors
 * do not have access to, so connecting Claude meant asking an administrator.
 *
 * The token's plaintext is shown exactly once, at the moment it is minted —
 * only a SHA-256 hash is stored, so there is no way to retrieve it later and
 * the page says so rather than letting someone assume they can come back for
 * it.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$mcp_url  = rest_url( 'six/v1/mcp' );
$rest_url = rest_url( 'six/v1/' );
$tokens   = class_exists( 'Six_Profile_API' ) ? (array) get_option( Six_Profile_API::TOKENS_OPT, array() ) : array();
$live     = array_values( array_filter( $tokens, function ( $t ) { return empty( $t['revoked'] ); } ) );
?>

<div class="six-page-head" style="margin-bottom:22px">
    <h1 class="six-page-title" style="margin:0">Connect Claude</h1>
    <p style="font-size:13px;color:var(--text3);margin:6px 0 0;max-width:62ch">
        Point a Claude session at this portal so the SEO skills can read a client&rsquo;s profile,
        check their performance and file what they produce &mdash; without you pasting anything in by hand.
    </p>
</div>

<!-- ── Endpoint ─────────────────────────────────────────────────────────── -->
<div style="background:var(--dark2);border:1px solid var(--border);border-radius:14px;padding:20px;margin-bottom:16px">
    <div style="font-size:13px;font-weight:700;margin-bottom:4px">1. The endpoint</div>
    <div style="font-size:12px;color:var(--text3);margin-bottom:12px">
        In Claude, go to <strong>Settings &rarr; Connectors &rarr; Add custom connector</strong> and paste this URL.
    </div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <input class="six-input" id="six-mcp-url" readonly value="<?php echo esc_attr( $mcp_url ); ?>"
               style="flex:1;min-width:260px;font-family:monospace;font-size:12px;padding:9px 11px">
        <button class="six-btn six-btn-ghost six-btn-sm" data-copy="six-mcp-url" style="font-size:11px">Copy</button>
    </div>
    <div style="font-size:11px;color:var(--text3);margin-top:10px;line-height:1.6">
        It has to be reachable from the public internet over HTTPS &mdash; Anthropic&rsquo;s servers call it, not your browser.
        A staging site behind a password or on localhost will not connect.
    </div>
</div>

<!-- ── Token ────────────────────────────────────────────────────────────── -->
<div style="background:var(--dark2);border:1px solid var(--border);border-radius:14px;padding:20px;margin-bottom:16px">
    <div style="font-size:13px;font-weight:700;margin-bottom:4px">2. A token</div>
    <div style="font-size:12px;color:var(--text3);margin-bottom:14px">
        In the same dialog, open <strong>Advanced settings &rarr; Request headers</strong> and add
        <code style="background:var(--dark3);padding:1px 5px;border-radius:4px">Authorization</code>
        with the value <code style="background:var(--dark3);padding:1px 5px;border-radius:4px">Bearer &lt;your token&gt;</code>.
    </div>

    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:14px">
        <input class="six-input" id="six-tok-label" placeholder="What is it for? e.g. &ldquo;Claude &mdash; Jamie&rdquo;"
               style="flex:1;min-width:220px;font-size:12px;padding:9px 11px">
        <select class="six-input" id="six-tok-scope" style="font-size:12px;padding:9px 11px;max-width:230px">
            <option value="read">Read only &mdash; audits and reports</option>
            <option value="write" selected>Read and write &mdash; also files results</option>
        </select>
        <button class="six-btn six-btn-primary six-btn-sm" id="six-tok-make" style="font-size:12px">Create token</button>
    </div>
    <div style="font-size:11px;color:var(--text3);line-height:1.6;margin-bottom:4px">
        Read-only is enough to run an audit. The strategy and monitoring skills need write, because they
        save projections, snapshots and finished documents back onto the client&rsquo;s record.
    </div>

    <div id="six-tok-out" style="display:none;margin-top:14px"></div>

    <?php if ( $live ) : ?>
    <div style="margin-top:18px;border-top:1px solid var(--border);padding-top:14px">
        <div style="font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.6px;margin-bottom:8px">
            Active tokens (<?php echo count( $live ); ?>)
        </div>
        <?php foreach ( $live as $t ) : ?>
            <div style="display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:1px solid rgba(255,255,255,.04);font-size:12px">
                <div style="flex:1;min-width:0"><?php echo esc_html( $t['label'] ?? 'Untitled' ); ?></div>
                <div style="color:var(--text3);font-size:11px"><?php echo esc_html( $t['scope'] ?? 'read' ); ?></div>
                <div style="color:var(--text3);font-size:11px"><?php echo esc_html( substr( (string) ( $t['created'] ?? '' ), 0, 10 ) ); ?></div>
                <button class="six-btn six-btn-ghost six-btn-sm six-tok-revoke" data-id="<?php echo esc_attr( $t['id'] ?? '' ); ?>"
                        style="font-size:10px;color:var(--danger,#ff6b6b)">Revoke</button>
            </div>
        <?php endforeach; ?>
        <div style="font-size:11px;color:var(--text3);margin-top:9px">
            Only the hash of each token is stored, so none of these can be shown again. If one is lost, revoke it and make another.
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ── What it can do ───────────────────────────────────────────────────── -->
<div style="background:var(--dark2);border:1px solid var(--border);border-radius:14px;padding:20px;margin-bottom:16px">
    <div style="font-size:13px;font-weight:700;margin-bottom:10px">3. What Claude can then do</div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:10px">
        <?php
        $tools = array(
            array( 'Find a client',            'Search by business name, person or email.' ),
            array( 'Read their profile',       'Every onboarding and questionnaire answer, grouped by section.' ),
            array( 'Update their profile',     'File answers that came back by email. Needs write.' ),
            array( 'Send the questionnaire',   'Issues the strategy questionnaire and returns the link. Needs write.' ),
            array( 'Check performance',        'Now against baseline, last check, and the targets the strategy set.' ),
            array( 'Capture a snapshot',       'Record where they stand today. Needs write.' ),
            array( 'Save strategy targets',    'What the plan commits to, so later checks can measure it. Needs write.' ),
            array( 'List and save artifacts',  'Audits, strategies and reports kept on the client record. Saving needs write.' ),
        );
        foreach ( $tools as $t ) : ?>
            <div style="background:var(--dark3);border:1px solid var(--border);border-radius:10px;padding:11px 13px">
                <div style="font-size:12px;font-weight:700;margin-bottom:3px"><?php echo esc_html( $t[0] ); ?></div>
                <div style="font-size:11px;color:var(--text3);line-height:1.5"><?php echo esc_html( $t[1] ); ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    <div style="font-size:11px;color:var(--text3);margin-top:12px;line-height:1.6">
        Pair it with the <strong>seo-audit-report</strong>, <strong>seo-strategy-builder</strong> and
        <strong>seo-content-production</strong> skills. They look for these tools automatically, and fall back to
        working from a document when the connector is not there.
    </div>
</div>

<!-- ── REST, for anything that is not Claude ────────────────────────────── -->
<details style="background:var(--dark2);border:1px solid var(--border);border-radius:14px;padding:16px 20px">
    <summary style="font-size:12px;font-weight:600;cursor:pointer;color:var(--text2)">Using something other than Claude?</summary>
    <div style="font-size:12px;color:var(--text3);margin-top:12px;line-height:1.7">
        The same data is available as a plain REST API at
        <code style="background:var(--dark3);padding:1px 5px;border-radius:4px"><?php echo esc_html( $rest_url ); ?></code>,
        with the same bearer token. <code style="background:var(--dark3);padding:1px 5px;border-radius:4px">GET /clients</code>,
        <code style="background:var(--dark3);padding:1px 5px;border-radius:4px">GET /clients/{id}/profile</code>,
        <code style="background:var(--dark3);padding:1px 5px;border-radius:4px">PATCH /clients/{id}/profile</code>,
        <code style="background:var(--dark3);padding:1px 5px;border-radius:4px">GET|POST /clients/{id}/artifacts</code>.
    </div>
</details>

<script>
(function(){
  var out = document.getElementById('six-tok-out');

  function esc(s){ return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

  // Copy buttons, including ones added after load.
  document.addEventListener('click', function(e){
    var b = e.target.closest('[data-copy]');
    if(!b) return;
    var f = document.getElementById(b.getAttribute('data-copy'));
    if(!f) return;
    f.removeAttribute('readonly'); f.select(); f.setSelectionRange(0, 99999);
    var ok=false; try{ ok=document.execCommand('copy'); }catch(err){}
    f.setAttribute('readonly','readonly');
    var t=b.textContent; b.textContent = ok ? 'Copied' : 'Select and copy';
    setTimeout(function(){ b.textContent=t; }, 1600);
  });

  document.getElementById('six-tok-make').addEventListener('click', function(){
    var btn=this, label=(document.getElementById('six-tok-label').value||'').trim();
    var scope=document.getElementById('six-tok-scope').value;
    if(!label){ alert('Give the token a name, so you can tell which one to revoke later.'); return; }
    btn.disabled=true; btn.textContent='Creating…';
    post({action:'six_advisor_create_token', label:label, scope:scope}).then(function(r){
      btn.disabled=false; btn.textContent='Create token';
      if(!r||!r.success){ out.style.display='block'; out.innerHTML='<div style="font-size:12px;color:#ff6b6b">'+esc((r&&r.data)||'Could not create the token.')+'</div>'; return; }
      out.style.display='block';
      out.innerHTML =
        '<div style="background:rgba(86,211,100,.08);border:1px solid rgba(86,211,100,.3);border-radius:10px;padding:13px 15px">'
        + '<div style="font-size:12px;font-weight:700;color:#56D364;margin-bottom:7px">Copy this now &mdash; it is not shown again</div>'
        + '<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">'
        +   '<input class="six-input" id="six-new-tok" readonly value="'+esc(r.data.token)+'" style="flex:1;min-width:260px;font-family:monospace;font-size:12px;padding:9px 11px">'
        +   '<button class="six-btn six-btn-primary six-btn-sm" data-copy="six-new-tok" style="font-size:11px">Copy</button>'
        + '</div>'
        + '<div style="font-size:11px;color:var(--text3);margin-top:9px;line-height:1.6">'
        +   'In Claude&rsquo;s connector dialog, add a request header <code>Authorization</code> with the value '
        +   '<code>Bearer '+esc(r.data.token.slice(0,12))+'&hellip;</code> &mdash; the whole token, not the shortened version shown here.'
        + '</div></div>';
      document.getElementById('six-new-tok').focus();
    }).catch(function(){
      btn.disabled=false; btn.textContent='Create token';
      out.style.display='block'; out.innerHTML='<div style="font-size:12px;color:#ff6b6b">Network error.</div>';
    });
  });

  document.querySelectorAll('.six-tok-revoke').forEach(function(b){
    b.addEventListener('click', function(){
      if(!confirm('Revoke this token? Any Claude session using it stops working immediately.')) return;
      var row=b.closest('div'); b.disabled=true; b.textContent='…';
      post({action:'six_advisor_revoke_token', id:b.getAttribute('data-id')}).then(function(r){
        if(r&&r.success){ row.style.opacity='.4'; b.textContent='Revoked'; }
        else { b.disabled=false; b.textContent='Revoke'; }
      }).catch(function(){ b.disabled=false; b.textContent='Revoke'; });
    });
  });
})();
</script>
