<?php
/**
 * Onboarding Template — body content only.
 * Included by onboarding-page.php which provides the HTML wrapper.
 * Path: /wp-content/themes/6ixClaude/portal/templates/onboarding.php
 *
 * $js_data is already set by onboarding-page.php and output as sixPortal JS object.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Server-side: if user is logged in and already completed checkout, redirect
if ( is_user_logged_in() ) {
    $uid       = get_current_user_id();
    $completed = get_user_meta( $uid, 'six_checkout_completed', true );
    if ( $completed ) {
        wp_redirect( home_url( '/portal/' ) );
        exit;
    }
}
?>
<style>
/* ── Onboarding-specific styles ─────────────────────────────── */
:root {
  --pink: #FF6699;
  --blue: #3C6478;
  --cyan: #83C5ED;
  --dark1: #0D1117;
  --dark2: #111820;
  --dark3: #161F28;
  --dark4: #1C2733;
  --border: rgba(255,255,255,0.08);
  --text1: #F0F4F8;
  --text2: #B0BEC9;
  --text3: #647E8E;
  --success: #56D364;
  --warning: #E3B341;
  --danger: #FF6B6B;
  --radius: 14px;
  --transition: 0.25s cubic-bezier(0.4,0,0.2,1);
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'DM Sans', sans-serif;
  background: var(--dark1);
  color: var(--text1);
  min-height: 100vh;
  overflow-x: hidden;
}
body::before {
  content: '';
  position: fixed; inset: 0; z-index: 0; pointer-events: none;
  background:
    radial-gradient(ellipse 80% 60% at 10% 20%, rgba(255,102,153,0.07) 0%, transparent 60%),
    radial-gradient(ellipse 60% 80% at 90% 80%, rgba(131,197,237,0.06) 0%, transparent 60%);
}
.ob-wrapper {
  position: relative; z-index: 1;
  min-height: 100vh;
  display: grid;
  grid-template-columns: 380px 1fr;
}
@media (max-width: 900px) {
  .ob-wrapper { grid-template-columns: 1fr; }
  .ob-sidebar  { display: none; }
}
/* Sidebar */
.ob-sidebar {
  background: linear-gradient(160deg, var(--dark3) 0%, var(--dark2) 100%);
  border-right: 1px solid var(--border);
  padding: 48px 40px;
  display: flex; flex-direction: column; justify-content: space-between;
  position: sticky; top: 0; height: 100vh; overflow: hidden;
}
.ob-sidebar::after {
  content: ''; position: absolute; bottom: -100px; right: -100px;
  width: 400px; height: 400px; border-radius: 50%;
  background: radial-gradient(circle, rgba(255,102,153,0.06) 0%, transparent 70%);
  pointer-events: none;
}
.ob-logo { font-family: 'Syne', sans-serif; font-size: 22px; font-weight: 800; background: linear-gradient(135deg, var(--text1), var(--cyan)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
.ob-sidebar-content { flex: 1; display: flex; flex-direction: column; justify-content: center; }
.ob-sidebar-headline { font-family: 'Syne', sans-serif; font-size: 30px; font-weight: 700; line-height: 1.2; margin-bottom: 16px; }
.ob-sidebar-headline span { color: var(--pink); }
.ob-sidebar-desc { font-size: 13px; color: var(--text2); line-height: 1.7; margin-bottom: 36px; max-width: 280px; }
.ob-steps-nav { display: flex; flex-direction: column; }
.ob-step-item { display: flex; align-items: center; gap: 16px; padding: 13px 0; border-bottom: 1px solid var(--border); transition: var(--transition); }
.ob-step-item:last-child { border-bottom: none; }
.ob-step-num { width: 32px; height: 32px; border-radius: 50%; border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; color: var(--text3); flex-shrink: 0; transition: var(--transition); }
.ob-step-item.active .ob-step-num { background: var(--pink); border-color: var(--pink); color: white; box-shadow: 0 0 16px rgba(255,102,153,0.4); }
.ob-step-item.done .ob-step-num { background: var(--success); border-color: var(--success); color: white; }
.ob-step-label { font-size: 13px; color: var(--text3); font-weight: 500; transition: var(--transition); }
.ob-step-item.active .ob-step-label { color: var(--text1); font-weight: 600; }
.ob-step-item.done .ob-step-label { color: var(--success); }
.ob-sidebar-footer { font-size: 11px; color: var(--text3); line-height: 1.6; }
.ob-sidebar-footer a { color: var(--cyan); text-decoration: none; }
/* Main */
.ob-main { padding: 48px 40px; max-width: 620px; margin: 0 auto; width: 100%; }
@media (max-width: 600px) { .ob-main { padding: 28px 20px; } }
/* Progress */
.ob-progress { display: flex; align-items: center; gap: 12px; margin-bottom: 44px; }
.ob-progress-track { flex: 1; height: 3px; background: var(--dark4); border-radius: 2px; overflow: hidden; }
.ob-progress-fill { height: 100%; background: linear-gradient(90deg, var(--pink), var(--cyan)); border-radius: 2px; transition: width 0.6s cubic-bezier(0.4,0,0.2,1); }
.ob-progress-label { font-size: 11px; font-weight: 600; color: var(--text3); white-space: nowrap; font-family: 'Syne', sans-serif; letter-spacing: 0.5px; }
/* Panels */
.ob-panel { display: none; animation: obPanelIn 0.35s cubic-bezier(0.4,0,0.2,1) both; }
.ob-panel.active { display: block; }
@keyframes obPanelIn { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: translateY(0); } }
/* Typography */
.ob-eyebrow { font-family: 'Syne', sans-serif; font-size: 11px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: var(--pink); margin-bottom: 8px; }
.ob-title { font-family: 'Syne', sans-serif; font-size: 26px; font-weight: 700; line-height: 1.2; margin-bottom: 8px; letter-spacing: -0.5px; }
.ob-desc { font-size: 14px; color: var(--text2); line-height: 1.7; margin-bottom: 28px; }
/* Form */
.ob-fg { margin-bottom: 16px; }
.ob-label { display: block; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.8px; color: var(--text3); margin-bottom: 6px; }
.ob-input { width: 100%; background: var(--dark3); border: 1px solid var(--border); border-radius: 10px; padding: 12px 16px; color: var(--text1); font-family: 'DM Sans', sans-serif; font-size: 14px; outline: none; transition: var(--transition); -webkit-appearance: none; }
.ob-input:focus { border-color: rgba(255,102,153,0.5); background: var(--dark4); box-shadow: 0 0 0 3px rgba(255,102,153,0.08); }
.ob-input::placeholder { color: var(--text3); }
.ob-input option { background: var(--dark3); }
.ob-grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
@media (max-width: 520px) { .ob-grid2 { grid-template-columns: 1fr; } }
/* Chips */
.ob-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 6px; }
.ob-chip { padding: 8px 16px; border-radius: 100px; border: 1px solid var(--border); background: var(--dark3); font-size: 12px; color: var(--text2); cursor: pointer; transition: var(--transition); user-select: none; font-family: 'DM Sans', sans-serif; }
.ob-chip:hover { border-color: rgba(255,102,153,0.4); color: var(--text1); }
.ob-chip.selected { background: rgba(255,102,153,0.12); border-color: var(--pink); color: var(--pink); font-weight: 600; }
/* Buttons */
.ob-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 13px 28px; border-radius: 10px; font-family: 'DM Sans', sans-serif; font-size: 14px; font-weight: 600; cursor: pointer; border: none; transition: var(--transition); text-decoration: none; }
.ob-btn-primary { background: linear-gradient(135deg, var(--pink) 0%, #e6407a 100%); color: white; box-shadow: 0 4px 20px rgba(255,102,153,0.28); }
.ob-btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 24px rgba(255,102,153,0.42); }
.ob-btn-primary:disabled { opacity: 0.5; transform: none; cursor: not-allowed; }
.ob-btn-ghost { background: transparent; color: var(--text2); border: 1px solid var(--border); }
.ob-btn-ghost:hover { border-color: rgba(255,255,255,0.2); color: var(--text1); }
.ob-btn-row { display: flex; gap: 12px; align-items: center; margin-top: 28px; }
/* Divider */
.ob-hr { border: none; border-top: 1px solid var(--border); margin: 24px 0; }
.ob-section-sep { font-size: 11px; color: var(--text3); font-weight: 600; text-transform: uppercase; letter-spacing: 1px; margin: 20px 0 14px; }
/* Readiness Score */
.ob-score-card { background: linear-gradient(135deg, var(--dark3), var(--dark4)); border: 1px solid var(--border); border-radius: var(--radius); padding: 28px; text-align: center; margin-bottom: 20px; overflow: hidden; position: relative; }
.ob-score-card::before { content: ''; position: absolute; top: -40px; right: -40px; width: 200px; height: 200px; border-radius: 50%; background: radial-gradient(circle, rgba(255,102,153,0.08) 0%, transparent 70%); }
.ob-score-ring { width: 120px; height: 120px; margin: 0 auto 16px; position: relative; }
.ob-score-ring svg { transform: rotate(-90deg); }
.ob-ring-bg { fill: none; stroke: var(--dark4); stroke-width: 8; }
.ob-ring-fill { fill: none; stroke: url(#scoreGrad); stroke-width: 8; stroke-linecap: round; stroke-dasharray: 283; stroke-dashoffset: 283; transition: stroke-dashoffset 1.2s cubic-bezier(0.4,0,0.2,1); }
.ob-score-num { position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); font-family: 'Syne', sans-serif; font-size: 28px; font-weight: 800; color: var(--text1); }
.ob-score-title { font-family: 'Syne', sans-serif; font-size: 16px; font-weight: 700; margin-bottom: 4px; }
.ob-score-sub { font-size: 12px; color: var(--text2); margin-bottom: 20px; }
.ob-score-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; text-align: left; }
.ob-score-col-head { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
.ob-score-col-head.green { color: var(--success); }
.ob-score-col-head.orange { color: var(--warning); }
.ob-score-item { font-size: 11px; color: var(--text2); line-height: 1.5; margin-bottom: 5px; padding-left: 12px; position: relative; }
.ob-score-item::before { content: '•'; position: absolute; left: 0; }
.ob-score-item.green::before { color: var(--success); }
.ob-score-item.orange::before { color: var(--warning); }
/* Service cards */
.ob-svc-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px; }
@media (max-width: 520px) { .ob-svc-grid { grid-template-columns: 1fr; } }
.ob-svc-card { background: var(--dark3); border: 1px solid var(--border); border-radius: var(--radius); padding: 16px; cursor: pointer; transition: var(--transition); position: relative; overflow: hidden; }
.ob-svc-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: var(--c, var(--pink)); opacity: 0; transition: var(--transition); }
.ob-svc-card:hover::before { opacity: 0.6; }
.ob-svc-card.sel::before { opacity: 1; }
.ob-svc-card.sel { border-color: rgba(255,102,153,0.35); background: rgba(255,102,153,0.04); }
.ob-svc-check { position: absolute; top: 12px; right: 12px; width: 20px; height: 20px; border-radius: 50%; border: 1px solid var(--border); background: var(--dark4); display: flex; align-items: center; justify-content: center; font-size: 10px; transition: var(--transition); }
.ob-svc-card.sel .ob-svc-check { background: var(--pink); border-color: var(--pink); color: white; }
.ob-svc-icon { font-size: 22px; margin-bottom: 6px; }
.ob-svc-name { font-size: 13px; font-weight: 700; margin-bottom: 3px; font-family: 'Syne', sans-serif; }
.ob-svc-desc { font-size: 11px; color: var(--text3); line-height: 1.5; }
.ob-svc-budget { display: none; margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--border); }
.ob-svc-card.sel .ob-svc-budget { display: block; }
.ob-svc-budget-lbl { font-size: 10px; color: var(--text3); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; }
.ob-svc-budget-input { width: 100%; background: var(--dark4); border: 1px solid var(--border); border-radius: 8px; padding: 7px 10px; color: var(--text1); font-family: 'DM Sans', sans-serif; font-size: 13px; outline: none; }
.ob-svc-budget-input:focus { border-color: rgba(255,102,153,0.4); }
/* Projection */
.ob-projection { background: rgba(131,197,237,0.05); border: 1px solid rgba(131,197,237,0.2); border-radius: var(--radius); padding: 20px; margin-bottom: 20px; display: none; }
.ob-projection.show { display: block; }
.ob-proj-title { font-size: 11px; font-weight: 700; color: var(--cyan); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 14px; }
.ob-proj-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
@media (max-width: 460px) { .ob-proj-grid { grid-template-columns: 1fr; } }
.ob-proj-stat-val { font-family: 'Syne', sans-serif; font-size: 20px; font-weight: 800; color: var(--cyan); display: block; }
.ob-proj-stat-lbl { font-size: 11px; color: var(--text3); margin-top: 2px; }
/* Strategy */
.ob-strategy { background: var(--dark3); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; margin-bottom: 20px; }
.ob-strategy-hdr { background: var(--dark4); padding: 14px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 8px; }
.ob-strategy-hdr-title { font-family: 'Syne', sans-serif; font-size: 13px; font-weight: 700; }
.ob-strategy-row { display: flex; justify-content: space-between; align-items: center; padding: 11px 20px; border-bottom: 1px solid rgba(255,255,255,0.04); }
.ob-strategy-row:last-child { border-bottom: none; }
.ob-strategy-key { font-size: 12px; color: var(--text3); }
.ob-strategy-val { font-size: 13px; font-weight: 600; text-align: right; }
.ob-strategy-total { background: rgba(255,102,153,0.05); border-top: 1px solid rgba(255,102,153,0.2); padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; }
.ob-strategy-total-lbl { font-size: 13px; font-weight: 700; }
.ob-strategy-total-val { font-family: 'Syne', sans-serif; font-size: 22px; font-weight: 800; color: var(--pink); }
/* AI Suggestion */
.ob-ai-sug { padding: 14px 16px; background: var(--dark3); border: 1px solid var(--border); border-left: 3px solid var(--cyan); border-radius: var(--radius); margin-bottom: 10px; font-size: 13px; color: var(--text2); line-height: 1.6; }
/* Advisor reveal */
.ob-advisor-reveal { background: linear-gradient(135deg, var(--dark3), var(--dark4)); border: 1px solid rgba(131,197,237,0.25); border-radius: var(--radius); padding: 20px; display: flex; align-items: center; gap: 18px; margin-bottom: 24px; position: relative; overflow: hidden; }
.ob-advisor-reveal::before { content: ''; position: absolute; top: -20px; right: -20px; width: 120px; height: 120px; border-radius: 50%; background: radial-gradient(circle, rgba(131,197,237,0.08) 0%, transparent 70%); }
.ob-adv-avatar-wrap { position: relative; flex-shrink: 0; }
.ob-adv-avatar { width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, var(--blue), var(--cyan)); display: flex; align-items: center; justify-content: center; font-family: 'Syne', sans-serif; font-size: 18px; font-weight: 700; color: white; }
.ob-adv-online { position: absolute; bottom: 2px; right: 2px; width: 13px; height: 13px; border-radius: 50%; background: var(--success); border: 2px solid var(--dark3); }
.ob-adv-intro { font-size: 10px; color: var(--cyan); font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 3px; }
.ob-adv-name { font-family: 'Syne', sans-serif; font-size: 17px; font-weight: 700; margin-bottom: 2px; }
.ob-adv-role { font-size: 12px; color: var(--text2); margin-bottom: 7px; }
.ob-adv-tags { display: flex; flex-wrap: wrap; gap: 5px; }
.ob-adv-tag { padding: 3px 9px; border-radius: 100px; background: rgba(131,197,237,0.1); border: 1px solid rgba(131,197,237,0.2); font-size: 10px; color: var(--cyan); font-weight: 600; }
/* Contract */
.ob-contract { background: var(--dark3); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; margin-bottom: 18px; }
.ob-contract-hdr { padding: 13px 18px; border-bottom: 1px solid var(--border); background: var(--dark4); display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; }
.ob-contract-body { max-height: 160px; overflow-y: auto; padding: 16px 18px; font-size: 12px; color: var(--text3); line-height: 1.8; }
.ob-contract-body::-webkit-scrollbar { width: 3px; }
.ob-contract-body::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }
.ob-sign-row { padding: 14px 18px; border-top: 1px solid var(--border); display: flex; align-items: center; gap: 10px; }
.ob-sign-input { flex: 1; background: var(--dark4); border: 1px solid var(--border); border-radius: 8px; padding: 10px 13px; color: var(--pink); font-family: 'Syne', sans-serif; font-size: 16px; font-style: italic; outline: none; transition: var(--transition); }
.ob-sign-input:focus { border-color: rgba(255,102,153,0.4); }
.ob-sign-input::placeholder { font-style: normal; color: var(--text3); font-family: 'DM Sans', sans-serif; font-size: 12px; }
.ob-sign-lbl { font-size: 11px; color: var(--text3); white-space: nowrap; }
/* Stripe */
.ob-stripe-wrap { background: var(--dark3); border: 1px solid var(--border); border-radius: var(--radius); padding: 18px; margin-bottom: 20px; }
.ob-stripe-lbl { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.8px; color: var(--text3); margin-bottom: 10px; }
#ob-card-el { background: var(--dark4); border: 1px solid var(--border); border-radius: 10px; padding: 14px 16px; }
.ob-stripe-note { font-size: 11px; color: var(--text3); margin-top: 9px; display: flex; align-items: center; gap: 5px; }
.ob-stripe-note::before { content: '🔒'; font-size: 12px; }
/* Alert */
.ob-alert { padding: 11px 15px; border-radius: 8px; font-size: 13px; margin-bottom: 14px; display: none; }
.ob-alert.err { background: rgba(255,107,107,0.1); border: 1px solid rgba(255,107,107,0.3); color: var(--danger); }
.ob-alert.ok  { background: rgba(86,211,100,0.1);  border: 1px solid rgba(86,211,100,0.3);  color: var(--success); }
.ob-alert.show { display: block; }
/* Complete */
.ob-complete { text-align: center; padding: 40px 20px; }
.ob-complete-icon { width: 76px; height: 76px; border-radius: 50%; background: rgba(86,211,100,0.1); border: 1px solid rgba(86,211,100,0.3); margin: 0 auto 22px; display: flex; align-items: center; justify-content: center; font-size: 34px; }
.ob-complete-title { font-family: 'Syne', sans-serif; font-size: 26px; font-weight: 700; margin-bottom: 12px; }
.ob-complete-desc { font-size: 14px; color: var(--text2); line-height: 1.7; max-width: 380px; margin: 0 auto 28px; }
/* Spinner */
.ob-spin { display: inline-block; width: 15px; height: 15px; border: 2px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: obSpin 0.7s linear infinite; }
@keyframes obSpin { to { transform: rotate(360deg); } }
/* Login hint */
.ob-found-hint { font-size: 12px; color: var(--text3); padding: 9px 13px; background: var(--dark3); border-radius: 8px; border-left: 3px solid var(--cyan); margin-top: 10px; }
/* Password reveal toggle */
.ob-pw-wrap { position: relative; }
.ob-pw-toggle { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text3); cursor: pointer; font-size: 13px; padding: 0; }

/* Marketing question cards */
.ob-mq-card { margin-bottom: 0; padding: 20px; background: var(--dark3); border: 1px solid var(--border); border-radius: var(--radius); animation: obPanelIn 0.3s ease both; }
.ob-mq-sub  { border-left: 3px solid var(--cyan); }
.ob-mq-num  { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: var(--pink); margin-bottom: 8px; font-family: 'Syne', sans-serif; }
.ob-mq-question { font-size: 15px; font-weight: 600; color: var(--text1); line-height: 1.4; font-family: 'Syne', sans-serif; }
.ob-mq-options { display: flex; flex-direction: column; gap: 8px; }
.ob-mq-option {
  display: flex; align-items: center; gap: 14px;
  padding: 12px 16px; background: var(--dark4); border: 1px solid var(--border);
  border-radius: 10px; cursor: pointer; transition: var(--transition);
}
.ob-mq-option:hover { border-color: rgba(255,102,153,0.4); background: rgba(255,102,153,0.04); }
.ob-mq-option.selected { border-color: var(--pink); background: rgba(255,102,153,0.08); }
.ob-mq-opt-icon { font-size: 22px; flex-shrink: 0; width: 36px; text-align: center; }
.ob-mq-opt-desc { font-size: 11px; color: var(--text3); margin-top: 2px; }
.ob-mq-step-line { width: 2px; height: 16px; background: var(--border); margin: 4px 0 4px 29px; }

</style>

<svg width="0" height="0" style="position:absolute">
  <defs>
    <linearGradient id="scoreGrad" x1="0%" y1="0%" x2="100%" y2="0%">
      <stop offset="0%" style="stop-color:#FF6699"/>
      <stop offset="100%" style="stop-color:#83C5ED"/>
    </linearGradient>
  </defs>
</svg>

<div class="ob-wrapper">

  <!-- ── Sidebar ─────────────────────────────────── -->
  <aside class="ob-sidebar">
    <div class="ob-logo">6ix Developers</div>
    <div class="ob-sidebar-content">
      <h2 class="ob-sidebar-headline">Your <span>marketing strategy</span> starts here.</h2>
      <p class="ob-sidebar-desc">We'll learn about your business, match you with a dedicated advisor, and build your personalised growth plan in minutes.</p>
      <div class="ob-steps-nav" id="ob-sidebar-steps">
        <div class="ob-step-item" data-step="1"><div class="ob-step-num">1</div><div class="ob-step-label">Business Profile</div></div>
        <div class="ob-step-item" data-step="2"><div class="ob-step-num">2</div><div class="ob-step-label">Services &amp; Budget</div></div>
        <div class="ob-step-item" data-step="3"><div class="ob-step-num">3</div><div class="ob-step-label">Strategy Confirmation</div></div>
        <div class="ob-step-item" data-step="4"><div class="ob-step-num">4</div><div class="ob-step-label">Agreement &amp; Payment</div></div>
      </div>
    </div>
    <div class="ob-sidebar-footer">
      Secured by SSL · <a href="<?php echo esc_url(get_privacy_policy_url()); ?>">Privacy Policy</a><br>
      Questions? <a href="mailto:<?php echo esc_attr(get_option('admin_email')); ?>">Contact us</a>
    </div>
  </aside>

  <!-- ── Main ───────────────────────────────────── -->
  <div class="ob-main">

    <div class="ob-progress" id="ob-progress" style="display:none">
      <div class="ob-progress-track"><div class="ob-progress-fill" id="ob-progress-fill" style="width:0%"></div></div>
      <span class="ob-progress-label" id="ob-progress-label">Step 1 / 4</span>
    </div>

    <!-- ── LOGIN ──────────────────────────────────── -->
    <div class="ob-panel active" id="ob-login">
      <?php if ( is_user_logged_in() ) : $u = wp_get_current_user(); ?>
        <!-- Already logged in but not completed — go straight to step 1 -->
        <script>document.addEventListener('DOMContentLoaded',function(){ OB.resumeLoggedIn(<?php echo get_current_user_id(); ?>, <?php echo wp_json_encode($u->user_email); ?>, <?php echo intval(get_user_meta(get_current_user_id(),'six_checkout_step',true)?:1); ?>); });</script>
      <?php else : ?>
      <div style="font-family:'Syne',sans-serif;font-size:20px;font-weight:800;background:linear-gradient(135deg,var(--text1),var(--cyan));-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin-bottom:36px">6ix Developers</div>
      <div class="ob-eyebrow">Welcome</div>
      <h1 class="ob-title">Let's get started.</h1>
      <p class="ob-desc">Enter your email — we'll log you in or set up your new account automatically.</p>
      <div class="ob-alert err" id="ob-login-err"></div>
      <div class="ob-fg">
        <label class="ob-label">Email Address</label>
        <input class="ob-input" type="email" id="ob-email" placeholder="you@company.com" autocomplete="email">
      </div>
      <div id="ob-pw-section" style="display:none">
        <div class="ob-found-hint" id="ob-found-hint"></div>
        <div class="ob-fg" style="margin-top:14px">
          <label class="ob-label">Password</label>
          <div class="ob-pw-wrap">
            <input class="ob-input" type="password" id="ob-password" placeholder="Your password" autocomplete="current-password" style="padding-right:44px">
            <button class="ob-pw-toggle" type="button" onclick="togglePw()" title="Show/hide password">👁</button>
          </div>
        </div>
        <div style="text-align:right;margin-top:6px">
          <a href="#" style="font-size:12px;color:var(--cyan);text-decoration:none" onclick="OB.showForgotPw();return false">Forgot password?</a>
        </div>
        <div id="ob-forgot-wrap" style="display:none;margin-top:12px;background:var(--dark4);border:1px solid var(--border);border-radius:10px;padding:14px">
          <div style="font-size:12px;color:var(--text2);margin-bottom:10px">Enter your email and we'll send a reset link.</div>
          <div style="display:flex;gap:8px">
            <input class="ob-input" id="ob-forgot-email" placeholder="your@email.com" type="email" style="flex:1">
            <button class="ob-btn ob-btn-primary" style="padding:10px 16px;font-size:13px" onclick="OB.sendReset()">Send</button>
          </div>
          <div id="ob-forgot-msg" style="font-size:12px;margin-top:8px"></div>
        </div>
      </div>
      <div class="ob-btn-row" style="margin-top:20px">
        <button class="ob-btn ob-btn-primary" style="flex:1" id="ob-login-btn" onclick="OB.handleEmailStep()">Continue →</button>
      </div>
      <?php endif; ?>
    </div>

    <!-- ── STEP 1: Business Profile ───────────────── -->
    <div class="ob-panel" id="ob-step1">
      <div class="ob-eyebrow">Step 1 of 4</div>
      <h2 class="ob-title">Tell us about your business.</h2>
      <p class="ob-desc">Under 2 minutes. This powers your personalised marketing plan.</p>
      <div class="ob-grid2">
        <div class="ob-fg"><label class="ob-label">First Name</label><input class="ob-input" id="s1-first" placeholder="Alex"></div>
        <div class="ob-fg"><label class="ob-label">Last Name</label><input class="ob-input" id="s1-last" placeholder="Johnson"></div>
      </div>
      <div class="ob-fg"><label class="ob-label">Email</label><input class="ob-input" id="s1-email" type="email" readonly style="opacity:0.55;cursor:not-allowed"></div>
      <div class="ob-fg"><label class="ob-label">Phone</label><input class="ob-input" id="s1-phone" placeholder="+1 (416) 555-0100" type="tel"></div>
      <div class="ob-fg" id="s1-password-wrap">
        <label class="ob-label">Create a Password</label>
        <div class="ob-pw-wrap">
          <input class="ob-input" type="password" id="s1-password" placeholder="Minimum 8 characters" autocomplete="new-password" style="padding-right:44px">
          <button class="ob-pw-toggle" type="button" onclick="togglePw2()" title="Show/hide">👁</button>
        </div>
        <div style="font-size:11px;color:var(--text3);margin-top:4px">You'll use this to log back in.</div>
      </div>
      <hr class="ob-hr">
      <div class="ob-fg"><label class="ob-label">Business Name</label><input class="ob-input" id="s1-biz" placeholder="Acme Corp"></div>
      <div class="ob-grid2">
        <div class="ob-fg"><label class="ob-label">Website</label><input class="ob-input" id="s1-website" placeholder="https://yoursite.com"></div>
        <div class="ob-fg"><label class="ob-label">Industry</label>
          <select class="ob-input" id="s1-industry">
            <option value="">Select…</option>
            <option>SaaS / Technology</option><option>E-commerce / Retail</option><option>Healthcare / Wellness</option>
            <option>Real Estate</option><option>Finance / Insurance</option><option>Home Services</option>
            <option>Hospitality / Food</option><option>Professional Services</option><option>Education</option><option>Other</option>
          </select>
        </div>
      </div>
      <div class="ob-grid2">
        <div class="ob-fg"><label class="ob-label">Location</label><input class="ob-input" id="s1-location" placeholder="Toronto, ON"></div>
        <div class="ob-fg"><label class="ob-label">Employees</label>
          <select class="ob-input" id="s1-employees">
            <option value="">Select…</option><option>1 (Solo)</option><option>2–10</option><option>11–50</option><option>51–200</option><option>200+</option>
          </select>
        </div>
      </div>
      <div class="ob-fg"><label class="ob-label">Annual Revenue Range</label>
        <div class="ob-chips" data-group="s1-revenue">
          <div class="ob-chip" data-val="under10k">Under $10k</div>
          <div class="ob-chip" data-val="10k-50k">$10k – $50k</div>
          <div class="ob-chip" data-val="50k-250k">$50k – $250k</div>
          <div class="ob-chip" data-val="250k+">$250k+</div>
        </div>
      </div>
      <hr class="ob-hr">

      <!-- Marketing Intelligence — interactive card-by-card flow -->
      <div class="ob-section-sep">Quick Marketing Snapshot</div>
      <div style="font-size:13px;color:var(--text2);margin-bottom:20px;line-height:1.6">Just 4 quick questions — this helps us build your personalised strategy.</div>

      <!-- Q1: Running campaigns? -->
      <div class="ob-mq-card" id="mq1">
        <div class="ob-mq-num">1 of 4</div>
        <div class="ob-mq-question">Are you currently running any digital marketing campaigns?</div>
        <div class="ob-chips" data-group="s1-runs-ads" data-single="1" style="margin-top:12px">
          <div class="ob-chip ob-mq-chip" data-val="yes" data-next="mq1b">✅ Yes, actively running</div>
          <div class="ob-chip ob-mq-chip" data-val="no"  data-next="mq2">🚫 Not yet</div>
        </div>
      </div>

      <!-- Q1b: Which platforms (only if yes) -->
      <div class="ob-mq-card ob-mq-sub" id="mq1b" style="display:none">
        <div class="ob-mq-question">Which platforms are you using? <span style="font-size:11px;color:var(--text3)">(Select all that apply)</span></div>
        <div class="ob-chips" data-group="s1-platforms" style="margin-top:12px">
          <div class="ob-chip ob-platform-chip" data-val="google">📊 Google Ads</div>
          <div class="ob-chip ob-platform-chip" data-val="fb">📘 Facebook / Instagram</div>
          <div class="ob-chip ob-platform-chip" data-val="tiktok">🎵 TikTok Ads</div>
          <div class="ob-chip ob-platform-chip" data-val="seo">🔍 SEO</div>
          <div class="ob-chip ob-platform-chip" data-val="email">✉️ Email Marketing</div>
        </div>
        <div style="margin-top:14px"><button class="ob-btn ob-btn-ghost" style="font-size:12px;padding:8px 16px" onclick="OB.mq_next('mq2')">Continue →</button></div>
      </div>

      <!-- Q2: Primary goal -->
      <div class="ob-mq-card" id="mq2" style="display:none">
        <div class="ob-mq-num">2 of 4</div>
        <div class="ob-mq-question">What is your primary marketing goal?</div>
        <div class="ob-mq-options" style="margin-top:12px">
          <div class="ob-mq-option" data-group="s1-goal" data-val="leads" data-next="mq3">
            <span class="ob-mq-opt-icon">🎯</span>
            <div><strong>Lead Generation</strong><div class="ob-mq-opt-desc">Attract new prospects and fill my pipeline</div></div>
          </div>
          <div class="ob-mq-option" data-group="s1-goal" data-val="sales" data-next="mq3">
            <span class="ob-mq-opt-icon">🛒</span>
            <div><strong>Online Sales</strong><div class="ob-mq-opt-desc">Drive e-commerce or direct sales online</div></div>
          </div>
          <div class="ob-mq-option" data-group="s1-goal" data-val="awareness" data-next="mq3">
            <span class="ob-mq-opt-icon">📢</span>
            <div><strong>Brand Awareness</strong><div class="ob-mq-opt-desc">Get my name out there and build recognition</div></div>
          </div>
          <div class="ob-mq-option" data-group="s1-goal" data-val="local" data-next="mq3">
            <span class="ob-mq-opt-icon">📍</span>
            <div><strong>Local Growth</strong><div class="ob-mq-opt-desc">Attract more customers in my area</div></div>
          </div>
        </div>
      </div>

      <!-- Q3: Biggest challenge -->
      <div class="ob-mq-card" id="mq3" style="display:none">
        <div class="ob-mq-num">3 of 4</div>
        <div class="ob-mq-question">What's your biggest marketing challenge right now?</div>
        <div class="ob-mq-options" style="margin-top:12px">
          <div class="ob-mq-option" data-group="s1-challenge" data-val="leads" data-next="mq4">
            <span class="ob-mq-opt-icon">🔌</span>
            <div><strong>Generating Leads</strong><div class="ob-mq-opt-desc">Hard to find qualified prospects</div></div>
          </div>
          <div class="ob-mq-option" data-group="s1-challenge" data-val="traffic" data-next="mq4">
            <span class="ob-mq-opt-icon">📉</span>
            <div><strong>Low Website Traffic</strong><div class="ob-mq-opt-desc">Not enough people finding my site</div></div>
          </div>
          <div class="ob-mq-option" data-group="s1-challenge" data-val="conversion" data-next="mq4">
            <span class="ob-mq-opt-icon">⚡</span>
            <div><strong>Low Conversion Rate</strong><div class="ob-mq-opt-desc">Visitors don't become customers</div></div>
          </div>
          <div class="ob-mq-option" data-group="s1-challenge" data-val="costs" data-next="mq4">
            <span class="ob-mq-opt-icon">💸</span>
            <div><strong>High Advertising Costs</strong><div class="ob-mq-opt-desc">Spending too much with poor ROI</div></div>
          </div>
        </div>
      </div>

      <!-- Q4: Budget -->
      <div class="ob-mq-card" id="mq4" style="display:none">
        <div class="ob-mq-num">4 of 4</div>
        <div class="ob-mq-question">What's your current monthly marketing budget?</div>
        <div class="ob-chips" data-group="s1-budget" data-single="1" style="margin-top:12px">
          <div class="ob-chip ob-mq-chip ob-mq-last" data-val="500-2000">$500 – $2,000</div>
          <div class="ob-chip ob-mq-chip ob-mq-last" data-val="2000-5000">$2,000 – $5,000</div>
          <div class="ob-chip ob-mq-chip ob-mq-last" data-val="5000+">$5,000+</div>
        </div>
        <div id="mq-complete" style="display:none;margin-top:14px;padding:12px 16px;background:rgba(86,211,100,0.08);border:1px solid rgba(86,211,100,0.2);border-radius:10px;font-size:13px;color:var(--success)">
          ✓ Great — we have everything we need to score your marketing readiness.
        </div>
      </div>
      <div class="ob-btn-row">
        <button class="ob-btn ob-btn-primary" style="flex:1" onclick="OB.completeStep1()">Analyse My Business →</button>
      </div>
    </div>

    <!-- ── READINESS SCORE ─────────────────────────── -->
    <div class="ob-panel" id="ob-score">
      <div class="ob-eyebrow">Your Results</div>
      <h2 class="ob-title">Marketing Readiness Score</h2>
      <p class="ob-desc">Based on your profile — here's where you stand and where we can take you.</p>
      <div class="ob-score-card">
        <div class="ob-score-ring">
          <svg viewBox="0 0 100 100" width="120" height="120">
            <circle class="ob-ring-bg" cx="50" cy="50" r="45"/>
            <circle class="ob-ring-fill" id="ob-ring-fill" cx="50" cy="50" r="45"/>
          </svg>
          <div class="ob-score-num" id="ob-score-num">0</div>
        </div>
        <div class="ob-score-title" id="ob-score-title">Calculating…</div>
        <div class="ob-score-sub" id="ob-score-sub"></div>
        <div class="ob-score-cols">
          <div><div class="ob-score-col-head green">✓ Strengths</div><div id="ob-strengths"></div></div>
          <div><div class="ob-score-col-head orange">⚡ Opportunities</div><div id="ob-opps"></div></div>
        </div>
      </div>
      <div id="ob-score-sugs"></div>
      <div class="ob-btn-row">
        <button class="ob-btn ob-btn-primary" style="flex:1" onclick="OB.goStep(2)">Build My Growth Plan →</button>
      </div>
    </div>

    <!-- ── STEP 2: Services & Budget ──────────────── -->
    <div class="ob-panel" id="ob-step2">
      <div class="ob-eyebrow">Step 2 of 4</div>
      <h2 class="ob-title">Choose your services.</h2>
      <p class="ob-desc">Select what you'd like to activate and set a monthly budget for each.</p>
      <div class="ob-svc-grid">
        <div class="ob-svc-card" data-svc="google-ads" style="--c:#4285F4"><div class="ob-svc-check">✓</div><div class="ob-svc-icon">📊</div><div class="ob-svc-name">Google Ads</div><div class="ob-svc-desc">Reach customers actively searching for your products.</div><div class="ob-svc-budget"><div class="ob-svc-budget-lbl">Monthly Budget</div><input class="ob-svc-budget-input" type="number" placeholder="e.g. 2000" min="0" onclick="event.stopPropagation()"></div></div>
        <div class="ob-svc-card" data-svc="seo" style="--c:#56D364"><div class="ob-svc-check">✓</div><div class="ob-svc-icon">🔍</div><div class="ob-svc-name">SEO</div><div class="ob-svc-desc">Build long-term organic traffic and search rankings.</div><div class="ob-svc-budget"><div class="ob-svc-budget-lbl">Monthly Budget</div><input class="ob-svc-budget-input" type="number" placeholder="e.g. 1200" min="0" onclick="event.stopPropagation()"></div></div>
        <div class="ob-svc-card" data-svc="social-media" style="--c:#FF6699"><div class="ob-svc-check">✓</div><div class="ob-svc-icon">📱</div><div class="ob-svc-name">Social Media</div><div class="ob-svc-desc">Grow your audience on Instagram, LinkedIn & more.</div><div class="ob-svc-budget"><div class="ob-svc-budget-lbl">Monthly Budget</div><input class="ob-svc-budget-input" type="number" placeholder="e.g. 800" min="0" onclick="event.stopPropagation()"></div></div>
        <div class="ob-svc-card" data-svc="brand-dev" style="--c:#E3B341"><div class="ob-svc-check">✓</div><div class="ob-svc-icon">🎨</div><div class="ob-svc-name">Brand Development</div><div class="ob-svc-desc">Build a compelling brand identity for your market.</div><div class="ob-svc-budget"><div class="ob-svc-budget-lbl">Monthly Budget</div><input class="ob-svc-budget-input" type="number" placeholder="e.g. 600" min="0" onclick="event.stopPropagation()"></div></div>
        <div class="ob-svc-card" data-svc="website" style="--c:#a855f7;grid-column:span 2"><div class="ob-svc-check">✓</div><div class="ob-svc-icon">🌐</div><div class="ob-svc-name">Website Development</div><div class="ob-svc-desc">Optimize or rebuild your site for conversions and performance.</div><div class="ob-svc-budget"><div class="ob-svc-budget-lbl">Monthly Budget</div><input class="ob-svc-budget-input" type="number" placeholder="e.g. 1500" min="0" onclick="event.stopPropagation()"></div></div>
      </div>
      <div class="ob-projection" id="ob-projection">
        <div class="ob-proj-title">📈 Estimated Monthly Impact</div>
        <div class="ob-proj-grid">
          <div><span class="ob-proj-stat-val" id="ob-proj-traffic">—</span><div class="ob-proj-stat-lbl">New Visitors / mo</div></div>
          <div><span class="ob-proj-stat-val" id="ob-proj-leads">—</span><div class="ob-proj-stat-lbl">Est. New Leads</div></div>
          <div><span class="ob-proj-stat-val" id="ob-proj-budget">$0</span><div class="ob-proj-stat-lbl">Total Monthly Budget</div></div>
        </div>
      </div>
      <div class="ob-alert err" id="ob-step2-err"></div>
      <div class="ob-btn-row">
        <button class="ob-btn ob-btn-ghost" onclick="OB.goStep(1)">← Back</button>
        <button class="ob-btn ob-btn-primary" style="flex:1" onclick="OB.completeStep2()">Review My Strategy →</button>
      </div>
    </div>

    <!-- ── STEP 3: Strategy ───────────────────────── -->
    <div class="ob-panel" id="ob-step3">
      <div class="ob-eyebrow">Step 3 of 4</div>
      <h2 class="ob-title">Your marketing strategy.</h2>
      <p class="ob-desc">Here's the plan we've built. Review and confirm to proceed.</p>
      <div id="ob-strategy-card"></div>
      <div id="ob-step3-sugs"></div>
      <div class="ob-btn-row">
        <button class="ob-btn ob-btn-ghost" onclick="OB.goStep(2)">← Adjust</button>
        <button class="ob-btn ob-btn-primary" style="flex:1" onclick="OB.completeStep3()">Confirm Strategy →</button>
      </div>
    </div>

    <!-- ── STEP 4: Agreement & Payment ────────────── -->
    <div class="ob-panel" id="ob-step4">
      <div class="ob-eyebrow">Step 4 of 4</div>
      <h2 class="ob-title">Agreement &amp; payment.</h2>
      <p class="ob-desc">Your advisor is ready. Sign the agreement and add a payment method to complete your account.</p>
      <div class="ob-advisor-reveal" id="ob-advisor-card"></div>
      <div class="ob-contract">
        <div class="ob-contract-hdr"><span>📄</span><span>6ix Developers Service Agreement</span></div>
        <div class="ob-contract-body">
          <strong>SERVICE AGREEMENT</strong><br><br>
          This Service Agreement ("Agreement") is between <strong>6ix Developers Inc.</strong> ("Agency") and the client ("Client") identified during onboarding.<br><br>
          <strong>1. Services.</strong> Agency will provide the marketing services selected by Client during onboarding, as updated with mutual consent.<br><br>
          <strong>2. Payment.</strong> Client agrees to pay the monthly budgets selected per service. Billing occurs on the first of each month. Services may be cancelled with 30 days written notice.<br><br>
          <strong>3. Term.</strong> This Agreement begins on the date of digital signature and continues month-to-month unless otherwise agreed.<br><br>
          <strong>4. Confidentiality.</strong> Both parties agree to maintain confidentiality of proprietary information shared during the engagement.<br><br>
          <strong>5. Performance.</strong> Agency will provide monthly reports and make commercially reasonable efforts to achieve agreed goals. Results are not guaranteed.<br><br>
          <strong>6. Payment Method.</strong> The card provided today is verified only — it will not be charged until service activation is confirmed.<br><br>
          <strong>7. Governing Law.</strong> This Agreement is governed by the laws of the Province of Ontario, Canada.<br><br>
          By typing your full name below you agree to these terms.
        </div>
        <div class="ob-sign-row">
          <input class="ob-sign-input" id="ob-signature" placeholder="Type your full name to sign" autocomplete="off">
          <span class="ob-sign-lbl">Digital Signature</span>
        </div>
      </div>
      <div class="ob-stripe-wrap">
        <div class="ob-stripe-lbl">Payment Method</div>
        <div id="ob-card-el"></div>
        <div class="ob-stripe-note">Card verified only — not charged until service activation.</div>
        <div id="ob-stripe-err" style="color:var(--danger);font-size:12px;margin-top:6px"></div>
      </div>
      <div class="ob-alert err" id="ob-step4-err"></div>
      <div class="ob-btn-row">
        <button class="ob-btn ob-btn-ghost" onclick="OB.goStep(3)">← Back</button>
        <button class="ob-btn ob-btn-primary" style="flex:1" id="ob-complete-btn" onclick="OB.completeOnboarding()">Complete Setup →</button>
      </div>
    </div>

    <!-- ── COMPLETE ───────────────────────────────── -->
    <div class="ob-panel" id="ob-complete">
      <div class="ob-complete">
        <div class="ob-complete-icon">🎉</div>
        <h2 class="ob-complete-title">You're all set!</h2>
        <p class="ob-complete-desc">Welcome to 6ix Developers. Your advisor has been notified and will be in touch within one business day. Your dashboard is ready.</p>
        <a href="<?php echo esc_url(home_url('/portal/')); ?>" class="ob-btn ob-btn-primary" style="font-size:15px;padding:15px 36px">Go to My Dashboard →</a>
        <div style="margin-top:18px;font-size:12px;color:var(--text3)">Confirmation sent to <span id="ob-done-email" style="color:var(--cyan)"></span></div>
      </div>
    </div>

  </div><!-- /.ob-main -->
</div><!-- /.ob-wrapper -->

<script>
(function(){
'use strict';

// ── State ──────────────────────────────────────────────────────────────────
var S = {
  step: 0,
  userId: <?php echo intval( $js_data['user_id'] ?? 0 ); ?>,
  email:  <?php echo wp_json_encode( $js_data['email'] ?? '' ); ?>,
  advisor: null,
  step1: {},
  services: {},
  score: 0,
  nonce: (typeof sixPortal !== 'undefined' ? sixPortal.nonce : ''),
  ajax:  (typeof sixPortal !== 'undefined' ? sixPortal.ajax_url : '<?php echo esc_js(admin_url('admin-ajax.php')); ?>'),
  stripe_key: (typeof sixPortal !== 'undefined' ? sixPortal.stripe_key : '<?php echo esc_js(get_option('six_stripe_publishable_key','')); ?>'),
};

// ── Helpers ────────────────────────────────────────────────────────────────
function $(id){ return document.getElementById(id); }
function val(id){ return ($( id)||{}).value || ''; }
function chips(grp){ return Array.from(document.querySelectorAll('[data-group="'+grp+'"] .ob-chip.selected')).map(function(c){return c.dataset.val;}); }
function showAlert(id, msg){ var el=$(id); if(!el)return; el.textContent=msg; el.classList.add('show'); setTimeout(function(){el.classList.remove('show');},5000); }

function post(data){
  return fetch(S.ajax,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:new URLSearchParams(Object.assign({nonce:S.nonce},data))
  }).then(function(r){return r.json();}).catch(function(){return {success:false,data:'Network error'};});
}

function showPanel(id){
  document.querySelectorAll('.ob-panel').forEach(function(p){p.classList.remove('active');});
  var el=$(id); if(el) el.classList.add('active');
  window.scrollTo({top:0,behavior:'smooth'});
}

function setProgress(n){
  var pr=$('ob-progress'), fill=$('ob-progress-fill'), lbl=$('ob-progress-label');
  if(n >= 1){ pr.style.display='flex'; fill.style.width=((n/4)*100)+'%'; lbl.textContent='Step '+n+' / 4'; }
  document.querySelectorAll('.ob-step-item').forEach(function(el){
    var s=parseInt(el.dataset.step);
    el.classList.remove('active','done');
    if(s===n) el.classList.add('active'); else if(s<n) el.classList.add('done');
  });
}

function animNum(id,to,ms){
  var el=$(id), start=performance.now();
  var tick=function(now){ var t=Math.min((now-start)/ms,1); el.textContent=Math.round(to*t); if(t<1)requestAnimationFrame(tick); };
  requestAnimationFrame(tick);
}

// ── Expose public API ──────────────────────────────────────────────────────
window.OB = {

  // Called for already-logged-in users
  resumeLoggedIn: function(uid, email, step){
    S.userId = uid; S.email = email;
    var adv = null;
    post({action:'six_get_advisor_for_user', user_id: uid}).then(function(r){
      if(r.success) { S.advisor = r.data; }
      OB.goStep(step || 1);
    });
  },

  // ── Email step ─────────────────────────────────────────────────────────
  handleEmailStep: function(){
    var email = val('ob-email');
    var pwSection = $('ob-pw-section');
    var btn = $('ob-login-btn');
    if(!email || !email.includes('@')){ showAlert('ob-login-err','Please enter a valid email address.'); return; }

    // If password section already showing — do login
    if(pwSection && pwSection.style.display !== 'none'){
      OB.doLogin(); return;
    }

    btn.innerHTML='<span class="ob-spin"></span>'; btn.disabled=true;
    post({action:'six_check_email', email}).then(function(r){
      btn.disabled=false; S.email = email;
      if(r.data && r.data.exists){
        // Existing user → show password field, hide create-password field in step 1
        $('ob-found-hint').textContent = '✓ Account found for '+email;
        pwSection.style.display='block';
        btn.textContent='Log In →';
        btn.onclick = OB.doLogin;
        $('ob-password').focus();
        OB._isNewUser = false;
        // Hide password creation in step 1 for existing users
        var pwWrap = $('s1-password-wrap'); if(pwWrap) pwWrap.style.display='none';
      } else {
        // New user → create account
        btn.innerHTML='<span class="ob-spin"></span> Creating account…'; btn.disabled=true;
        post({action:'six_create_partial_account', email}).then(function(res){
          btn.disabled=false;
          if(res.success){
            S.userId  = res.data.user_id;
            S.advisor = res.data.advisor;
            // Update nonce — user is now logged in on the server, old guest nonce
            // won't pass check_ajax_referer for authenticated endpoints
            if(res.data.nonce) S.nonce = res.data.nonce;
            $('s1-email').value = email;
            OB.goStep(1);
          } else {
            btn.textContent='Continue →';
            showAlert('ob-login-err', res.data||'Could not create account. Please try again.');
          }
        });
      }
    });
  },

  // ── Login existing user ────────────────────────────────────────────────
  doLogin: function(){
    var email = val('ob-email'), pw = val('ob-password');
    var btn = $('ob-login-btn');
    btn.innerHTML='<span class="ob-spin"></span> Signing in…'; btn.disabled=true;
    post({action:'six_portal_login', email, password:pw}).then(function(r){
      btn.disabled=false;
      if(r.success){
        // Update nonce immediately for all roles
        if(r.data.nonce) S.nonce = r.data.nonce;

        // Non-customers (advisors, sales, admin) get redirected away from onboarding
        if(r.data.redirect_url){
          window.location.href = r.data.redirect_url;
          return;
        }

        // Customer flow
        S.userId = r.data.user_id; S.email = email;
        if(r.data.has_completed_checkout){
          window.location.replace('<?php echo esc_js(home_url('/portal/')); ?>');
        } else {
          post({action:'six_get_advisor_for_user', user_id:S.userId}).then(function(ar){
            if(ar.success) S.advisor = ar.data;
            $('s1-email').value = email;
            OB.goStep(r.data.resume_step || 1);
          });
        }
      } else {
        btn.textContent='Log In →';
        showAlert('ob-login-err', r.data||'Incorrect email or password.');
      }
    });
  },

  // ── Step navigation ────────────────────────────────────────────────────
  goStep: function(n){
    S.step = n;
    setProgress(n);
    var map={1:'ob-step1','1b':'ob-score',2:'ob-step2',3:'ob-step3',4:'ob-step4'};
    showPanel(map[n]||'ob-step'+n);
    post({action:'six_save_checkout_step', step:n, user_id:S.userId});
  },

  // ── Step 1 ─────────────────────────────────────────────────────────────
  completeStep1: function(){
    var d = {
      first:    val('s1-first'), last: val('s1-last'), email: val('s1-email'),
      phone:    val('s1-phone'), password: val('s1-password'), bizname: val('s1-biz'),
      website:  val('s1-website'), industry: val('s1-industry'),
      location: val('s1-location'), employees: val('s1-employees'),
      revenue:     chips('s1-revenue').join(','),
      runs_ads:    chips('s1-runs-ads').join(','),
      platforms:   chips('s1-platforms').join(','),
      mktg_budget: chips('s1-budget').join(','),
      goal:        chips('s1-goal').join(','),
      challenge:   chips('s1-challenge').join(','),
    };
    if(!d.first || !d.last){ alert('Please enter your name.'); return; }
    if(!d.bizname){ alert('Please enter your business name.'); return; }
    S.step1 = d;
    // Save password if new user
    if(OB._isNewUser && d.password && d.password.length >= 8){
      post({action:'six_set_user_password', user_id:S.userId, password:d.password});
    } else if(OB._isNewUser && d.password && d.password.length > 0 && d.password.length < 8){
      alert('Password must be at least 8 characters.'); return;
    }
    post({action:'six_save_checkout_step', step:1, data:JSON.stringify(d), user_id:S.userId});
    OB.buildScore(d);
  },

  // ── Readiness Score ────────────────────────────────────────────────────
  buildScore: function(d){
    var score=20, str=[], opp=[];
    if(d.website)              { score+=10; str.push('Active website detected'); }
    else                        { opp.push('No website — limits digital reach'); }
    if(d.runs_ads==='yes')     { score+=15; str.push('Already running digital campaigns'); }
    else                        { opp.push('No current campaigns — significant untapped reach'); }
    if(d.platforms)             { score+=10; str.push('Active on '+d.platforms.split(',').length+' platform(s)'); }
    if(d.mktg_budget==='5000+'){ score+=15; str.push('Strong existing marketing budget'); }
    else if(d.mktg_budget==='2000-5000'){ score+=10; }
    else if(d.mktg_budget==='0'){ opp.push('No current marketing spend'); }
    if(!d.platforms||d.platforms.indexOf('seo')===-1){ opp.push('SEO not active — compounding traffic opportunity'); }
    if(!d.platforms||d.platforms.indexOf('google')===-1){ opp.push('Google Ads could drive immediate qualified leads'); }
    if(d.revenue==='250k+'){ score+=10; str.push('Strong revenue base to scale from'); }
    if(d.goal){ score+=5; str.push('Clear goal: '+d.goal.replace('-',' ')); }
    if(d.challenge){ opp.push('Key challenge: '+d.challenge.replace('-',' ')); }
    score = Math.min(score,99);
    S.score = score;

    $('ob-strengths').innerHTML  = str.slice(0,3).map(function(s){return '<div class="ob-score-item green">'+s+'</div>';}).join('');
    $('ob-opps').innerHTML       = opp.slice(0,3).map(function(s){return '<div class="ob-score-item orange">'+s+'</div>';}).join('');
    var title = score>=70?'Strong Foundation':score>=50?'Growing Potential':'High Opportunity';
    $('ob-score-title').textContent = title;
    $('ob-score-sub').textContent   = score>=70?"You're ahead of most businesses at your stage.":'There\'s significant upside in the right areas.';

    showPanel('ob-score');
    setTimeout(function(){
      var offset = 283 - (score/100)*283;
      $('ob-ring-fill').style.strokeDashoffset = offset;
      animNum('ob-score-num', score, 1200);
    },80);

    OB.buildAiSugs('ob-score-sugs', d);
    post({action:'six_save_checkout_step', step:'1b', score:score, user_id:S.userId});
  },

  // ── AI Suggestions ─────────────────────────────────────────────────────
  buildAiSugs: function(containerId, d, max){
    max = max||2;
    var el=$(containerId); if(!el) return;
    var s=[];
    if(d.goal==='leads'&&(!d.platforms||d.platforms.indexOf('google')===-1))
      s.push('💡 Your goal is lead generation. Google Ads can deliver qualified leads immediately.');
    if(!d.platforms||d.platforms.indexOf('seo')===-1)
      s.push('📈 SEO is not in your current mix. Starting now means compounding results in 3–6 months.');
    if(d.challenge==='conversion')
      s.push('🎯 Your main challenge is conversions. A landing page audit typically improves results in 30 days.');
    if(d.challenge==='traffic'&&(!d.platforms||d.platforms.indexOf('seo')===-1))
      s.push('🔍 Low traffic + no SEO = a strong case for a combined organic + paid approach.');
    if(d.mktg_budget==='0')
      s.push('🚀 Starting with a focused $500–$1,000 budget on one channel often outperforms spreading thin.');
    el.innerHTML = s.slice(0,max).map(function(t){return '<div class="ob-ai-sug">'+t+'</div>';}).join('');
  },

  // ── Step 2 ─────────────────────────────────────────────────────────────
  completeStep2: function(){
    var sel = document.querySelectorAll('.ob-svc-card.sel');
    if(sel.length===0){ showAlert('ob-step2-err','Please select at least one service.'); return; }
    S.services={};
    sel.forEach(function(c){
      S.services[c.dataset.svc] = parseFloat(c.querySelector('.ob-svc-budget-input').value||0);
    });
    post({action:'six_save_checkout_step', step:2, data:JSON.stringify(S.services), user_id:S.userId});
    OB.buildStrategy();
    OB.goStep(3);
  },

  // ── Step 3 ─────────────────────────────────────────────────────────────
  buildStrategy: function(){
    var names={'google-ads':'Google Ads','seo':'SEO','social-media':'Social Media','brand-dev':'Brand Development','website':'Website Development'};
    var keys=Object.keys(S.services), total=Object.values(S.services).reduce(function(a,b){return a+b;},0);
    var rows=keys.map(function(k){
      return '<div class="ob-strategy-row"><div class="ob-strategy-key">'+(names[k]||k)+'</div><div class="ob-strategy-val">'+(S.services[k]>0?'$'+S.services[k].toLocaleString()+'/mo':'Included')+'</div></div>';
    }).join('');
    $('ob-strategy-card').innerHTML =
      '<div class="ob-strategy"><div class="ob-strategy-hdr"><span>🗺️</span><div class="ob-strategy-hdr-title">Your Marketing Strategy Plan</div></div>'+
      '<div class="ob-strategy-row"><div class="ob-strategy-key">Primary Channel</div><div class="ob-strategy-val">'+(names[keys[0]]||'—')+'</div></div>'+
      '<div class="ob-strategy-row"><div class="ob-strategy-key">Support Channels</div><div class="ob-strategy-val">'+(keys.slice(1).map(function(k){return names[k];}).join(', ')||'—')+'</div></div>'+
      '<div class="ob-strategy-row"><div class="ob-strategy-key">Goal</div><div class="ob-strategy-val" style="text-transform:capitalize">'+(S.step1.goal||'—').replace('-',' ')+'</div></div>'+
      rows+
      '<div class="ob-strategy-total"><div class="ob-strategy-total-lbl">Estimated Monthly Budget</div><div class="ob-strategy-total-val">$'+total.toLocaleString()+'/mo</div></div></div>';
    OB.buildAiSugs('ob-step3-sugs', S.step1, 2);
  },

  completeStep3: function(){
    post({action:'six_save_checkout_step', step:3, user_id:S.userId});
    OB.buildAdvisorCard();
    OB.initStripe();
    OB.goStep(4);
  },

  // ── Step 4 ─────────────────────────────────────────────────────────────
  buildAdvisorCard: function(){
    var a = S.advisor || {name:'Your Advisor',role:'Senior Account Manager',initials:'AM',expertise:['Google Ads','SEO','Strategy']};
    var tags=(a.expertise||['Google Ads','Growth Strategy']).map(function(t){return '<span class="ob-adv-tag">'+t+'</span>';}).join('');
    $('ob-advisor-card').innerHTML =
      '<div class="ob-adv-avatar-wrap"><div class="ob-adv-avatar">'+(a.initials||'AM')+'</div><div class="ob-adv-online"></div></div>'+
      '<div><div class="ob-adv-intro">Your Dedicated Advisor</div>'+
      '<div class="ob-adv-name">'+(a.name||'Your Advisor')+'</div>'+
      '<div class="ob-adv-role">'+(a.role||'Account Manager · 6ix Developers')+'</div>'+
      '<div class="ob-adv-tags">'+tags+'</div></div>';
  },

  _stripe: null, _card: null,
  initStripe: function(){
    if(!S.stripe_key||OB._stripe) return;
    OB._stripe = Stripe(S.stripe_key);
    var els = OB._stripe.elements({appearance:{theme:'night',variables:{colorPrimary:'#FF6699',colorBackground:'#1C2733',colorText:'#F0F4F8',borderRadius:'10px'}}});
    OB._card = els.create('card',{hidePostalCode:true});
    OB._card.mount('#ob-card-el');
    OB._card.on('change',function(e){$('ob-stripe-err').textContent=e.error?e.error.message:'';});
  },

  completeOnboarding: async function(){
    var sig = val('ob-signature');
    if(!sig){ showAlert('ob-step4-err','Please type your full name to sign the agreement.'); return; }
    var btn=$('ob-complete-btn');
    btn.innerHTML='<span class="ob-spin"></span> Processing…'; btn.disabled=true;

    var pmId = null;
    if(OB._stripe && OB._card){
      var secret = await post({action:'six_stripe_setup', user_id:S.userId}).then(function(r){return r.data&&r.data.client_secret||'';});
      if(secret){
        var result = await OB._stripe.confirmCardSetup(secret,{payment_method:{card:OB._card,billing_details:{name:sig,email:S.email}}});
        if(result.error){ $('ob-stripe-err').textContent=result.error.message; btn.innerHTML='Complete Setup →'; btn.disabled=false; return; }
        pmId = result.setupIntent.payment_method;
      }
    }

    post({action:'six_complete_onboarding', user_id:S.userId, signature:sig,
      payment_method_id:pmId||'', services:JSON.stringify(S.services),
      step1_data:JSON.stringify(S.step1), score:S.score
    }).then(function(r){
      btn.innerHTML='Complete Setup →'; btn.disabled=false;
      if(r.success){
        // Clear abandoned checkout flag (no longer abandoned)
        S.step = 5; // prevents beforeunload from firing abandoned beacon
        $('ob-done-email').textContent = S.email;
        showPanel('ob-complete');
        $('ob-progress').style.display='none';
        // Redirect to dashboard — use replace() to avoid back-button loop
        // Short delay lets Safari process the auth cookie before redirect
        setTimeout(function(){
          window.location.replace('<?php echo esc_js(home_url('/portal/')); ?>');
        }, 2500);
      } else {
        // Show the actual server error to help with debugging
        var msg = r.data || 'Something went wrong. Please try again.';
        showAlert('ob-step4-err', msg);
        console.error('six_complete_onboarding error:', r);
      }
    }).catch(function(err){
      btn.innerHTML='Complete Setup →'; btn.disabled=false;
      showAlert('ob-step4-err', 'Network error — please check your connection and try again.');
      console.error('six_complete_onboarding network error:', err);
    });
  }
};

// ── Chip click handler ─────────────────────────────────────────────────────
document.querySelectorAll('.ob-chips').forEach(function(group){
  group.addEventListener('click',function(e){
    var chip=e.target.closest('.ob-chip'); if(!chip) return;
    if(group.dataset.single==='1') group.querySelectorAll('.ob-chip').forEach(function(c){c.classList.remove('selected');});
    chip.classList.toggle('selected');
    if(group.dataset.group==='s1-runs-ads'){
      var show = chip.dataset.val==='yes'&&chip.classList.contains('selected');
      $('s1-platforms-wrap').style.display = show?'block':'none';
    }
  });
});

// ── Service card click ─────────────────────────────────────────────────────
document.querySelectorAll('.ob-svc-card').forEach(function(card){
  card.addEventListener('click',function(){
    this.classList.toggle('sel');
    OB.updateProjection();
  });
});

OB.updateProjection = function(){
  var sel=document.querySelectorAll('.ob-svc-card.sel'), total=0;
  sel.forEach(function(c){ total+=parseFloat(c.querySelector('.ob-svc-budget-input').value||0); });
  var proj=$('ob-projection');
  if(sel.length===0){ proj.classList.remove('show'); return; }
  proj.classList.add('show');
  var traffic=Math.round(sel.length*400+total*0.3);
  var leads=Math.round(sel.length*12+total*0.015);
  $('ob-proj-traffic').textContent='+'+traffic.toLocaleString();
  $('ob-proj-leads').textContent=leads+'–'+(leads*2);
  $('ob-proj-budget').textContent='$'+total.toLocaleString();
};

document.querySelectorAll('.ob-svc-budget-input').forEach(function(i){ i.addEventListener('input',OB.updateProjection); });

// ── Password toggle ────────────────────────────────────────────────────────
// Hide password field for existing users (they already have a password)
OB._isNewUser = true;
window.togglePw2 = function(){
  var i=$('s1-password'); if(i) i.type = i.type==='password'?'text':'password';
};
window.OB.showForgotPw = function(){
  var w=$('ob-forgot-wrap');
  var em=val('ob-email');
  if(w){ w.style.display='block'; var fe=$('ob-forgot-email'); if(fe&&em) fe.value=em; }
};
window.OB.sendReset = function(){
  var email=val('ob-forgot-email');
  var msg=$('ob-forgot-msg');
  if(!email||!email.includes('@')){ if(msg) msg.innerHTML='<span style="color:var(--danger)">Enter a valid email.</span>'; return; }
  post({action:'six_send_password_reset',email}).then(function(r){
    if(msg) msg.innerHTML=r.success
      ?'<span style="color:var(--success)">✓ Reset link sent! Check your email.</span>'
      :'<span style="color:var(--danger)">'+(r.data||'Error')+'</span>';
  });
};
window.togglePw = function(){
  var i=$('ob-password'); i.type = i.type==='password'?'text':'password';
};

// ── Enter key support ──────────────────────────────────────────────────────
var emailEl=$('ob-email'), pwEl=$('ob-password');
if(emailEl) emailEl.addEventListener('keydown',function(e){ if(e.key==='Enter') OB.handleEmailStep(); });
if(pwEl)    pwEl.addEventListener('keydown',function(e){ if(e.key==='Enter') OB.doLogin(); });

// ── Abandoned checkout ─────────────────────────────────────────────────────
// S.step === 5 means completed — we set that in completeOnboarding so this
// doesn't fire after a successful completion.
function six_track_abandon(){
  if(!S.userId || S.step < 1 || S.step >= 5) return;
  var params = new URLSearchParams({
    action:'six_track_abandoned_checkout', nonce:S.nonce,
    user_id:S.userId, step:S.step, score:S.score
  });
  // sendBeacon is fire-and-forget (best for beforeunload)
  if(navigator.sendBeacon){
    navigator.sendBeacon(S.ajax, params);
  } else {
    // Synchronous XHR fallback for older browsers
    var xhr = new XMLHttpRequest();
    xhr.open('POST', S.ajax, false); // false = synchronous
    xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
    xhr.send(params.toString());
  }
}
window.addEventListener('beforeunload', six_track_abandon);
// Also fire on visibility change (mobile browsers often don't fire beforeunload)
document.addEventListener('visibilitychange', function(){
  if(document.visibilityState === 'hidden') six_track_abandon();
});


// ── Marketing questions interactive flow ──────────────────────────────────
window.OB = window.OB || {};
OB.mq_next = function(nextId){
  var el = document.getElementById(nextId);
  if(el){ el.style.display='block'; el.scrollIntoView({behavior:'smooth',block:'nearest'}); }
};
// Handle mq-chip click (goes to next card on select)
document.querySelectorAll('.ob-mq-chip').forEach(function(chip){
  chip.addEventListener('click',function(){
    var next = this.dataset.next;
    if(next) setTimeout(function(){ OB.mq_next(next); }, 200);
  });
});
// Handle ob-mq-option click (option cards)
document.querySelectorAll('.ob-mq-option').forEach(function(opt){
  opt.addEventListener('click',function(){
    var group = this.dataset.group;
    var val   = this.dataset.val;
    var next  = this.dataset.next;
    // Deselect others in same group
    document.querySelectorAll('.ob-mq-option[data-group="'+group+'"]').forEach(function(o){
      o.classList.remove('selected');
      // Also deselect any hidden chip with same group
      var hc = document.querySelector('[data-group="'+group+'"] .ob-chip.selected');
      if(hc) hc.classList.remove('selected');
    });
    this.classList.add('selected');
    // Keep hidden chip group in sync for data gathering
    var hiddenGroup = document.querySelector('[data-group="'+group+'"]');
    if(hiddenGroup){
      hiddenGroup.querySelectorAll('.ob-chip').forEach(function(chip){
        chip.classList.toggle('selected', chip.dataset.val===val);
      });
    }
    if(next) setTimeout(function(){ OB.mq_next(next); }, 260);
  });
});
// Handle last-step chips (show complete message)
document.querySelectorAll('.ob-mq-last').forEach(function(chip){
  chip.addEventListener('click',function(){
    setTimeout(function(){
      var done = document.getElementById('mq-complete');
      if(done) done.style.display='block';
    }, 200);
  });
});

})();
</script>
