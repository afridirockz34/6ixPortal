<?php
/**
 * 6ix Developers — Internal Product Hub
 * Template loaded by portal-page.php when slug = 'internal-hub'
 * WordPress provides proper nonce + ajax_url so all team members share the same DB.
 *
 * SETUP: Create a WordPress page with slug  internal-hub
 *        Set Page Template to "Portal Page" (portal-page.php handles auth + routing)
 *        Restrict access to Editors / Advisors / Sales via portal-page.php role check
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Generate a fresh nonce for this page load
$hub_nonce    = wp_create_nonce( 'six_hub_nonce' );
$hub_ajax_url = admin_url( 'admin-ajax.php' );
$hub_user     = wp_get_current_user();
$hub_name     = $hub_user->display_name ?: $hub_user->user_login;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Portal Intelligence Hub — 6ix Developers</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=IBM+Plex+Sans:wght@300;400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --brand:     #C0392B;
  --brand-dk:  #962d23;
  --accent:    #2C5F8A;
  --gold:      #9B7E46;
  --success:   #2A6B47;
  --warn:      #7A5200;
  --page:      #F8F7F5;
  --surface:   #FFFFFF;
  --surface2:  #F2F1EF;
  --surface3:  #ECEAE7;
  --border:    #E0DDD8;
  --border2:   #C8C5C0;
  --ink:       #1A1816;
  --ink2:      #4A4540;
  --ink3:      #7A7570;
  --ink4:      #A8A5A0;
  --fhead:     'Playfair Display', Georgia, serif;
  --fbody:     'IBM Plex Sans', system-ui, sans-serif;
  --fmono:     'IBM Plex Mono', monospace;
  --sw:        252px;
  --th:        54px;
}
html { -webkit-font-smoothing: antialiased; }
body { font-family: var(--fbody); background: var(--page); color: var(--ink); font-size: 14px; line-height: 1.6; min-height: 100vh; }

/* ── Topbar ── */
.topbar { position: fixed; top: 0; left: 0; right: 0; height: var(--th); background: var(--ink); display: flex; align-items: center; z-index: 300; }
.tb-brand { width: var(--sw); flex-shrink: 0; padding: 0 24px; display: flex; align-items: center; gap: 10px; border-right: 1px solid rgba(255,255,255,0.07); height: 100%; }
.tb-mark { width: 24px; height: 24px; border: 1px solid rgba(255,255,255,0.35); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.tb-mark svg { width: 11px; height: 11px; stroke: rgba(255,255,255,0.8); fill: none; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }
.tb-name { font-size: 11px; font-weight: 600; letter-spacing: 0.14em; text-transform: uppercase; color: rgba(255,255,255,0.85); }
.tb-sep { width: 1px; height: 14px; background: rgba(255,255,255,0.12); }
.tb-section { font-size: 11px; color: rgba(255,255,255,0.38); letter-spacing: 0.04em; }
.tb-right { margin-left: auto; padding: 0 24px; display: flex; align-items: center; gap: 16px; }
.tb-pill { font-size: 9px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; padding: 3px 10px; background: rgba(192,57,43,0.25); color: #F5A49A; border: 1px solid rgba(192,57,43,0.35); }
.tb-date { font-size: 11px; color: rgba(255,255,255,0.3); font-family: var(--fmono); }

/* ── Sidebar ── */
.sidebar { position: fixed; top: var(--th); left: 0; width: var(--sw); height: calc(100vh - var(--th)); background: var(--surface); border-right: 1px solid var(--border); overflow-y: auto; padding: 24px 0 32px; z-index: 200; }
.sidebar::-webkit-scrollbar { width: 3px; }
.sidebar::-webkit-scrollbar-thumb { background: var(--border2); }
.nav-group { margin-bottom: 24px; }
.nav-group-label { display: block; font-size: 9px; font-weight: 600; letter-spacing: 0.15em; text-transform: uppercase; color: var(--ink4); padding: 0 20px 8px; border-bottom: 1px solid var(--border); margin-bottom: 2px; }
.nav-link { display: flex; align-items: center; gap: 9px; padding: 8px 20px; font-size: 13px; font-weight: 400; color: var(--ink3); cursor: pointer; border-left: 2px solid transparent; transition: all 0.1s; user-select: none; }
.nav-link svg { width: 13px; height: 13px; flex-shrink: 0; stroke: currentColor; fill: none; stroke-width: 1.75; stroke-linecap: round; stroke-linejoin: round; opacity: 0.55; }
.nav-link:hover { color: var(--ink); background: var(--surface2); }
.nav-link.active { color: var(--brand); border-left-color: var(--brand); background: #FEF5F4; font-weight: 500; }
.nav-link.active svg { opacity: 1; }
.nav-count { margin-left: auto; font-size: 9px; font-weight: 700; background: var(--brand); color: #fff; padding: 1px 6px; min-width: 16px; text-align: center; display: none; }
.nav-count.show { display: block; }

/* ── Layout ── */
.main { margin-left: var(--sw); padding-top: var(--th); min-height: 100vh; }
.content { max-width: 800px; padding: 52px 56px 88px; }
.section { display: none; }
.section.active { display: block; animation: fi 0.18s ease; }
@keyframes fi { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }

/* ── Page header ── */
.page-header { padding-bottom: 28px; border-bottom: 1px solid var(--border); margin-bottom: 48px; }
.page-label { display: block; font-size: 9px; font-weight: 600; letter-spacing: 0.15em; text-transform: uppercase; color: var(--brand); margin-bottom: 14px; }
.page-title { font-family: var(--fhead); font-size: 36px; font-weight: 600; color: var(--ink); line-height: 1.12; letter-spacing: -0.015em; margin-bottom: 16px; }
.page-desc { font-size: 15px; color: var(--ink2); line-height: 1.8; font-weight: 300; max-width: 560px; }

/* ── Section heading ── */
.sh { font-family: var(--fhead); font-size: 21px; font-weight: 600; color: var(--ink); margin: 44px 0 5px; line-height: 1.25; }
.sh:first-of-type { margin-top: 0; }
.sh-sub { font-size: 13px; font-weight: 300; color: var(--ink3); margin-bottom: 18px; line-height: 1.65; }

/* ── Tile grid ── */
.tile-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1px; background: var(--border); border: 1px solid var(--border); margin-bottom: 40px; }
.tile { background: var(--surface); padding: 26px 28px; transition: background 0.12s; }
.tile:hover { background: var(--surface2); }
.tile-num { font-family: var(--fmono); font-size: 10px; font-weight: 500; color: var(--brand); letter-spacing: 0.08em; margin-bottom: 12px; }
.tile-title { font-family: var(--fhead); font-size: 16px; font-weight: 600; color: var(--ink); margin-bottom: 9px; line-height: 1.3; }
.tile-desc { font-size: 13px; color: var(--ink2); line-height: 1.7; font-weight: 300; }

/* ── Table ── */
.dt { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 40px; background: var(--surface); border: 1px solid var(--border); }
.dt th { text-align: left; padding: 10px 16px; font-size: 9px; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase; color: var(--ink3); background: var(--surface2); border-bottom: 1px solid var(--border); }
.dt td { padding: 13px 16px; border-bottom: 1px solid var(--border); color: var(--ink2); vertical-align: top; line-height: 1.65; }
.dt tr:last-child td { border-bottom: none; }
.dt tr:hover td { background: var(--surface2); }
code { font-family: var(--fmono); font-size: 11.5px; background: var(--surface2); color: var(--accent); padding: 1px 5px; border: 1px solid var(--border); }

/* ── Journey ── */
.journey { display: flex; flex-direction: column; }
.jstep { display: grid; grid-template-columns: 36px 1fr; }
.jspine { display: flex; flex-direction: column; align-items: center; padding-top: 2px; }
.jspine-line { width: 1px; flex: 1; background: var(--border); margin: 7px 0; min-height: 24px; }
.jnum { width: 26px; height: 26px; border: 1.5px solid var(--brand); display: flex; align-items: center; justify-content: center; font-family: var(--fmono); font-size: 10px; font-weight: 500; color: var(--brand); flex-shrink: 0; }
.jbody { padding: 0 0 36px 22px; }
.jbody-title { font-family: var(--fhead); font-size: 18px; font-weight: 600; color: var(--ink); margin-bottom: 8px; line-height: 1.3; }
.jbody-desc { font-size: 13px; color: var(--ink2); font-weight: 300; line-height: 1.75; margin-bottom: 18px; max-width: 540px; }
.jdetails { display: grid; grid-template-columns: 1fr 1fr; gap: 1px; background: var(--border); border: 1px solid var(--border); }
.jd { background: var(--surface); padding: 14px 16px; }
.jd-label { font-size: 9px; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase; color: var(--ink4); margin-bottom: 5px; }
.jd-val { font-size: 12px; color: var(--ink2); line-height: 1.65; font-weight: 300; }

/* ── Data flow ── */
.flow { display: flex; align-items: stretch; overflow-x: auto; margin: 24px 0 36px; }
.fn { background: var(--surface); border: 1px solid var(--border); margin-left: -1px; padding: 18px 16px; min-width: 108px; text-align: center; flex-shrink: 0; }
.fn:first-child { margin-left: 0; border-top: 2px solid var(--brand); }
.fn-name { font-size: 12px; font-weight: 600; color: var(--ink); margin-bottom: 3px; }
.fn-sub { font-family: var(--fmono); font-size: 10px; color: var(--ink3); }
.fa { display: flex; align-items: center; padding: 0 4px; flex-shrink: 0; color: var(--border2); }
.fa svg { width: 14px; height: 14px; }

/* ── Integration card ── */
.int-card { border: 1px solid var(--border); background: var(--surface); margin-bottom: 20px; }
.int-head { padding: 18px 22px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 14px; }
.int-head-name { font-family: var(--fhead); font-size: 18px; font-weight: 600; color: var(--ink); }
.int-head-sub { font-size: 12px; color: var(--ink3); margin-top: 2px; font-weight: 300; }
.int-badge { margin-left: auto; font-size: 9px; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; padding: 3px 10px; background: var(--surface2); color: var(--ink3); border: 1px solid var(--border); }
.int-body { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1px; background: var(--border); }
.int-col { background: var(--surface); padding: 18px 20px; }
.int-col h5 { font-size: 9px; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase; color: var(--ink4); margin-bottom: 8px; }
.int-col p { font-size: 13px; color: var(--ink2); line-height: 1.65; font-weight: 300; }

/* ── Callout ── */
.callout { border-left: 3px solid var(--border2); padding: 13px 18px; margin: 18px 0; background: var(--surface); border-top: 1px solid var(--border); border-right: 1px solid var(--border); border-bottom: 1px solid var(--border); }
.callout p { font-size: 13px; color: var(--ink2); line-height: 1.7; font-weight: 300; }
.callout strong { font-weight: 600; color: var(--ink); }
.callout.warn { border-left-color: var(--warn); }
.callout.tip { border-left-color: var(--success); }
.callout.info { border-left-color: var(--accent); }

/* ── Tag ── */
.tag { display: inline-block; font-size: 9px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; padding: 2px 7px; }
.tr { background: #FEF5F4; color: var(--brand); border: 1px solid #F0C0BC; }
.tb { background: #F0F5FA; color: var(--accent); border: 1px solid #B8D0E4; }
.tg { background: #F0F7F3; color: var(--success); border: 1px solid #B4D4C0; }
.ta { background: #FBF7EE; color: var(--gold); border: 1px solid #DDD0B4; }
.tn { background: var(--surface2); color: var(--ink3); border: 1px solid var(--border); }

/* ── Form ── */
.fb { background: var(--surface); border: 1px solid var(--border); margin-bottom: 28px; }
.fb-head { padding: 18px 22px; border-bottom: 1px solid var(--border); }
.fb-head h3 { font-family: var(--fhead); font-size: 18px; font-weight: 600; color: var(--ink); }
.fb-body { padding: 28px 22px; display: flex; flex-direction: column; gap: 20px; }
.frow { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
.field { display: flex; flex-direction: column; gap: 6px; }
.flabel { font-size: 9px; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase; color: var(--ink3); }
.finput, .fselect, .ftextarea { padding: 10px 12px; background: var(--surface2); border: 1px solid var(--border); color: var(--ink); font-family: var(--fbody); font-size: 13px; outline: none; transition: border-color 0.12s; -webkit-appearance: none; border-radius: 0; }
.finput:focus, .fselect:focus, .ftextarea:focus { border-color: var(--brand); background: var(--surface); box-shadow: 0 0 0 3px rgba(192,57,43,0.06); }
.finput::placeholder, .ftextarea::placeholder { color: var(--ink4); }
.ftextarea { resize: vertical; min-height: 90px; line-height: 1.6; }
.sev { display: flex; gap: 0; }
.sev-b { flex: 1; padding: 9px 10px; border: 1px solid var(--border); margin-left: -1px; background: var(--surface2); font-size: 12px; font-weight: 400; font-family: var(--fbody); color: var(--ink3); cursor: pointer; text-align: center; transition: all 0.1s; }
.sev-b:first-child { margin-left: 0; }
.sev-b:hover { background: var(--surface3); color: var(--ink); }
.sev-b.sl { background: #F0F7F3; border-color: var(--success); color: var(--success); z-index:1; font-weight:500; }
.sev-b.sm { background: #FBF7EE; border-color: var(--gold); color: var(--gold); z-index:1; font-weight:500; }
.sev-b.sh { background: #FEF5F4; border-color: var(--brand); color: var(--brand); z-index:1; font-weight:500; }
.btn-p { display: inline-flex; align-items: center; gap: 8px; padding: 11px 24px; border: none; cursor: pointer; background: var(--brand); color: #fff; font-family: var(--fbody); font-size: 13px; font-weight: 600; letter-spacing: 0.02em; transition: background 0.12s; }
.btn-p:hover { background: var(--brand-dk); }
.btn-p svg { width: 12px; height: 12px; stroke: #fff; fill: none; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }
.btn-p.blue { background: var(--accent); }
.btn-p.blue:hover { background: #245077; }
.btn-s { display: inline-flex; align-items: center; gap: 7px; padding: 8px 16px; border: 1px solid var(--border2); background: var(--surface); color: var(--ink2); font-family: var(--fbody); font-size: 12px; font-weight: 400; cursor: pointer; transition: all 0.12s; }
.btn-s:hover { background: var(--surface2); color: var(--ink); }

/* ── Tracker ── */
.tracker-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; gap: 12px; flex-wrap: wrap; }
.fbar { display: flex; gap: 0; }
.fb2 { padding: 7px 14px; border: 1px solid var(--border); margin-left: -1px; background: var(--surface); font-size: 12px; font-weight: 400; cursor: pointer; font-family: var(--fbody); color: var(--ink3); transition: all 0.1s; }
.fb2:first-child { margin-left: 0; }
.fb2:hover { background: var(--surface2); color: var(--ink); }
.fb2.active { background: var(--brand); border-color: var(--brand); color: #fff; z-index: 1; }
.tklist { display: flex; flex-direction: column; gap: 0; border: 1px solid var(--border); background: var(--border); }
.tkt { display: grid; grid-template-columns: 5px 1fr auto; background: var(--surface); border-bottom: 1px solid var(--border); transition: background 0.1s; }
.tkt:last-child { border-bottom: none; }
.tkt:hover { background: var(--surface2); }
.tkt-stripe { }
.tkt-body { padding: 15px 20px; }
.tkt-title { font-size: 13px; font-weight: 500; color: var(--ink); margin-bottom: 4px; }
.tkt-meta { font-size: 11px; color: var(--ink4); display: flex; gap: 14px; flex-wrap: wrap; font-family: var(--fmono); }
.tkt-actions { padding: 15px 18px; display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
.tkt-del {
  padding: 5px 6px; border: 1px solid var(--border);
  background: var(--surface2); cursor: pointer; border-radius: 0;
  color: var(--ink3); transition: all 0.12s; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
}
.tkt-del:hover { background: #FEF5F4; border-color: var(--brand); color: var(--brand); }
.st-sel { padding: 5px 8px; border: 1px solid var(--border); background: var(--surface2); color: var(--ink2); font-size: 11px; font-family: var(--fbody); cursor: pointer; -webkit-appearance: none; border-radius: 0; }
.empty-st { padding: 56px; text-align: center; background: var(--surface); border: 1px solid var(--border); }
.empty-st svg { width: 28px; height: 28px; stroke: var(--border2); fill: none; stroke-width: 1.25; margin: 0 auto 14px; display: block; stroke-linecap: round; stroke-linejoin: round; }
.empty-st p { font-size: 13px; color: var(--ink4); font-weight: 300; }

/* ── Toast ── */
.toast { position: fixed; bottom: 28px; right: 28px; background: var(--ink); color: #fff; padding: 12px 20px; font-size: 12px; font-weight: 500; font-family: var(--fbody); display: flex; align-items: center; gap: 10px; box-shadow: 0 8px 32px rgba(0,0,0,0.22); transform: translateY(60px); opacity: 0; transition: all 0.26s cubic-bezier(0.34,1.4,0.64,1); z-index: 9999; }
.toast.show { transform: none; opacity: 1; }
.toast svg { width: 13px; height: 13px; stroke: #7DD3A8; fill: none; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }

/* ── Responsive ── */
@media (max-width: 900px) {
  .content { padding: 36px 28px 64px; }
  .int-body { grid-template-columns: 1fr; }
  .tile-grid { grid-template-columns: 1fr; }
  .jdetails { grid-template-columns: 1fr; }
  .frow { grid-template-columns: 1fr; }
  .flow { gap: 0; }
}
@media (max-width: 768px) {
  /* Sidebar becomes a drawer */
  .sidebar {
    transform: translateX(-100%);
    transition: transform 0.28s cubic-bezier(0.4,0,0.2,1);
    box-shadow: none;
    z-index: 500;
  }
  .sidebar.open {
    transform: translateX(0);
    box-shadow: 4px 0 24px rgba(0,0,0,0.12);
  }
  /* Main fills full width */
  .main { margin-left: 0 !important; }
  :root { --sw: 252px; }
  /* Topbar brand is shorter on mobile */
  .tb-brand { width: auto; border-right: none; }
  .tb-section { display: none; }
  .tb-right { padding: 0 14px; gap: 10px; }
  .tb-date { display: none; }
  /* Content padding */
  .content { padding: 28px 18px 64px; }
  .page-title { font-size: 28px; }
  /* Hamburger visible */
  .hamburger { display: flex !important; }
  /* Overlay controlled by JS only — never force display here */
}

/* Hamburger button */
.hamburger {
  display: none;
  align-items: center; justify-content: center;
  width: 40px; height: 40px; cursor: pointer;
  background: none; border: none;
  flex-shrink: 0;
  padding: 8px;
  margin-right: 4px;
}
.hamburger span {
  display: block; width: 18px; height: 1.5px;
  background: rgba(255,255,255,0.7);
  transition: all 0.22s ease;
  position: relative;
}
.hamburger span::before,
.hamburger span::after {
  content: '';
  position: absolute; left: 0;
  width: 18px; height: 1.5px;
  background: rgba(255,255,255,0.7);
  transition: all 0.22s ease;
}
.hamburger span::before { top: -5px; }
.hamburger span::after  { top: 5px; }
.hamburger.open span { background: transparent; }
.hamburger.open span::before { transform: rotate(45deg); top: 0; }
.hamburger.open span::after  { transform: rotate(-45deg); top: 0; }

/* Sidebar overlay (dimmed background) */
.sidebar-overlay {
  display: none;
  position: fixed; inset: 0;
  background: rgba(26,24,22,0.5);
  z-index: 400;
  cursor: pointer;
}
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>
<header class="topbar">
  <button class="hamburger" id="hamburger" onclick="toggleSidebar()" aria-label="Menu">
    <span></span>
  </button>
  <div class="tb-brand">
    <div class="tb-mark"><svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
    <span class="tb-name">6ix Developers</span>
    <div class="tb-sep"></div>
    <span class="tb-section">Portal Intelligence Hub</span>
  </div>
  <div class="tb-right">
    <span class="tb-date" id="topbar-date"></span>
    <span class="tb-pill">Internal Use Only</span>
  </div>
</header>

<nav class="sidebar">
  <div class="nav-group">
    <span class="nav-group-label">Overview</span>
    <a class="nav-link active" onclick="show('overview',this)"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>System Overview</a>
    <a class="nav-link" onclick="show('journey',this)"><svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>Customer Journey</a>
    <a class="nav-link" onclick="show('data',this)"><svg viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>Data Architecture</a>
  </div>
  <div class="nav-group">
    <span class="nav-group-label">Product Features</span>
    <a class="nav-link" onclick="show('onboarding',this)"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="23" y1="11" x2="17" y2="11"/><line x1="20" y1="8" x2="20" y2="14"/></svg>Onboarding</a>
    <a class="nav-link" onclick="show('customer',this)"><svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>Customer Dashboard</a>
    <a class="nav-link" onclick="show('advisor',this)"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Advisor Dashboard</a>
    <a class="nav-link" onclick="show('integrations',this)"><svg viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>Integrations</a>
  </div>
  <div class="nav-group">
    <span class="nav-group-label">QA &amp; Feedback</span>
    <a class="nav-link" onclick="show('bug',this)"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>Report a Bug</a>
    <a class="nav-link" onclick="show('suggest',this)"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>Suggest Improvement</a>
    <a class="nav-link" onclick="show('tracker',this)"><svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>Issue Tracker<span class="nav-count" id="tracker-count">0</span></a>
  </div>
</nav>

<main class="main"><div class="content">

<!-- OVERVIEW -->
<div id="sec-overview" class="section active">
  <div class="page-header">
    <span class="page-label">System Overview</span>
    <h1 class="page-title">The 6ix Developers Portal</h1>
    <p class="page-desc">A fully integrated marketing operations platform managing the complete client lifecycle — from first contact through onboarding, strategy delivery, and ongoing performance management. This document is for internal use only.</p>
  </div>
  <div class="sh">Six core systems</div>
  <p class="sh-sub">Each system serves a distinct purpose and shares data with the others automatically.</p>
  <div class="tile-grid">
    <div class="tile"><div class="tile-num">01</div><div class="tile-title">Onboarding System</div><div class="tile-desc">A consultative four-step flow that collects business intelligence, selects services, generates a personalised strategy, and establishes payment via Stripe — without charging the client on day one.</div></div>
    <div class="tile"><div class="tile-num">02</div><div class="tile-title">Customer Dashboard</div><div class="tile-desc">Each client's personal interface: active services, KPI metrics, AI-generated recommendations, advisor messages, performance reports, and billing — all scoped exclusively to their account.</div></div>
    <div class="tile"><div class="tile-num">03</div><div class="tile-title">Advisor Dashboard</div><div class="tile-desc">A full customer intelligence centre. Advisors view all client data, manage services and budgets, generate AI strategies, upload reports, and control the engagement without leaving one screen.</div></div>
    <div class="tile"><div class="tile-num">04</div><div class="tile-title">AI Strategy Engine</div><div class="tile-desc">Nine strategy generators — SEO, Google Ads, Social Media, Brand, Quick Wins, Competitor Alerts, ROI, Service Gaps, Budget — powered by Claude AI using real client context including metrics and competitor data.</div></div>
    <div class="tile"><div class="tile-num">05</div><div class="tile-title">Odoo CRM Integration</div><div class="tile-desc">Every lead, service approval, and recommendation approval automatically creates or updates contacts and tasks in Odoo. The sales team has full pipeline visibility without accessing WordPress.</div></div>
    <div class="tile"><div class="tile-num">06</div><div class="tile-title">Google &amp; Meta APIs</div><div class="tile-desc">Per-client Google Ads Customer IDs, GA4 Property IDs, and Meta account credentials connect live campaign performance data directly into each dashboard — for both client and advisor views.</div></div>
  </div>
  <div class="sh">Portal access by role</div>
  <table class="dt">
    <thead><tr><th>Role</th><th>URL</th><th>Capability</th></tr></thead>
    <tbody>
      <tr><td><span class="tag tr">Customer</span></td><td><code>/portal/</code></td><td>Personal dashboard, metrics, services, messages, reports, billing</td></tr>
      <tr><td><span class="tag tb">Advisor</span></td><td><code>/advisor-portal/</code></td><td>Full client intelligence, metrics management, AI strategy, approvals, reporting</td></tr>
      <tr><td><span class="tag ta">Sales</span></td><td><code>/sales-portal/</code></td><td>Pipeline view, lead scores, onboarding activity and conversion tracking</td></tr>
      <tr><td><span class="tag tn">Admin</span></td><td><code>/wp-admin/</code></td><td>All settings, integrations, user management, API credentials</td></tr>
    </tbody>
  </table>
</div>

<!-- JOURNEY -->
<div id="sec-journey" class="section">
  <div class="page-header">
    <span class="page-label">Customer Journey</span>
    <h1 class="page-title">From first visit to active client</h1>
    <p class="page-desc">Six sequential steps every new client moves through. Each step documents what the user experiences, what data is collected, why we collect it, and where it flows in the system.</p>
  </div>
  <div class="journey">
    <div class="jstep">
      <div class="jspine"><div class="jnum">1</div><div class="jspine-line"></div></div>
      <div class="jbody">
        <div class="jbody-title">Landing &amp; Email Entry</div>
        <div class="jbody-desc">The client visits <code>/get-started/</code> and enters their email. The system checks for an existing account — returning users see the login flow; new users have an account created automatically and proceed directly to the profile step.</div>
        <div class="jdetails">
          <div class="jd"><div class="jd-label">Data Collected</div><div class="jd-val">Email address</div></div>
          <div class="jd"><div class="jd-label">Purpose</div><div class="jd-val">Creates the account identity. Ties all subsequent data to one record. Enables follow-up if they abandon.</div></div>
          <div class="jd"><div class="jd-label">Destination</div><div class="jd-val">WordPress users table · Odoo contact created immediately</div></div>
          <div class="jd"><div class="jd-label">Abandon Behaviour</div><div class="jd-val">Odoo lead tagged "Warm." Sales team receives a follow-up task.</div></div>
        </div>
      </div>
    </div>
    <div class="jstep">
      <div class="jspine"><div class="jnum">2</div><div class="jspine-line"></div></div>
      <div class="jbody">
        <div class="jbody-title">Business Profile &amp; Marketing Context</div>
        <div class="jbody-desc">The client completes their business profile and answers four guided questions: current marketing activity, goals (multi-select, up to six), challenges (multi-select, up to six), and monthly budget. A Marketing Readiness Score is calculated immediately from these answers and displayed as an animated ring.</div>
        <div class="jdetails">
          <div class="jd"><div class="jd-label">Data Collected</div><div class="jd-val">Name · Business · Website · Industry · Location · Employees · Revenue · Business stage · Competitors · Goals · Challenges · Budget</div></div>
          <div class="jd"><div class="jd-label">Purpose</div><div class="jd-val">Powers the AI strategy engine, advisor briefing, Odoo lead scoring, and the Marketing Readiness Score.</div></div>
          <div class="jd"><div class="jd-label">Destination</div><div class="jd-val">six_checkout_progress · Odoo lead fields updated · AI context established</div></div>
          <div class="jd"><div class="jd-label">Score</div><div class="jd-val">Calculated from six weighted signals. Links to public explainer at /marketing-readiness-score/</div></div>
        </div>
      </div>
    </div>
    <div class="jstep">
      <div class="jspine"><div class="jnum">3</div><div class="jspine-line"></div></div>
      <div class="jbody">
        <div class="jbody-title">Service Selection &amp; API Connection</div>
        <div class="jbody-desc">The client selects services and sets a monthly budget per service. They may optionally connect existing accounts — Google Ads Customer ID, GA4 Property ID, Meta Ad Account ID — to enable live performance data from day one.</div>
        <div class="jdetails">
          <div class="jd"><div class="jd-label">Data Collected</div><div class="jd-val">Selected services · Monthly budgets · Google Ads ID · GA4 Property ID · Meta Ad Account ID</div></div>
          <div class="jd"><div class="jd-label">Purpose</div><div class="jd-val">Creates service records. Enables API data pull. Establishes the financial scope of the engagement.</div></div>
          <div class="jd"><div class="jd-label">Destination</div><div class="jd-val">six_client_services · user meta for API IDs · Advisor Data Sources tab · Odoo opportunity value</div></div>
          <div class="jd"><div class="jd-label">Conditional Logic</div><div class="jd-val">API fields appear based on services selected. GA4 always shown; Ads ID only if Google Ads selected; Meta ID only if Social Media selected.</div></div>
        </div>
      </div>
    </div>
    <div class="jstep">
      <div class="jspine"><div class="jnum">4</div><div class="jspine-line"></div></div>
      <div class="jbody">
        <div class="jbody-title">Strategy Review</div>
        <div class="jbody-desc">A personalised strategy statement is generated from the client's service combination, industry, and goals. Growth insights specific to their challenge are shown alongside auto-populated goal chips. The client confirms before proceeding.</div>
        <div class="jdetails">
          <div class="jd"><div class="jd-label">What They See</div><div class="jd-val">Strategy statement · 2–4 growth insights · Confirmed goal list · Support channels overview</div></div>
          <div class="jd"><div class="jd-label">AI Involvement</div><div class="jd-val">Generated client-side from template logic using their inputs. No Claude API call at this stage.</div></div>
          <div class="jd"><div class="jd-label">Data Saved</div><div class="jd-val">Step 3 completion logged · Odoo lead status updated to "Strategy Confirmed"</div></div>
          <div class="jd"><div class="jd-label">Custom Goal</div><div class="jd-val">If the client adds a custom goal it is appended to their profile and visible to the advisor.</div></div>
        </div>
      </div>
    </div>
    <div class="jstep">
      <div class="jspine"><div class="jnum">5</div><div class="jspine-line"></div></div>
      <div class="jbody">
        <div class="jbody-title">Agreement &amp; Free Consultation Setup</div>
        <div class="jbody-desc">The client sees their assigned advisor, a summary of their selected plan, and the service agreement. They type their full name as a digital signature and add a payment method via Stripe. The card is verified and saved — no charge is made. A 10-day consultation period begins.</div>
        <div class="jdetails">
          <div class="jd"><div class="jd-label">Data Collected</div><div class="jd-val">Digital signature (full name typed) · Stripe payment method ID — card details are never stored on our servers</div></div>
          <div class="jd"><div class="jd-label">Payment Logic</div><div class="jd-val">Stripe SetupIntent. Card authorised only. No charge until the advisor activates services after the consultation.</div></div>
          <div class="jd"><div class="jd-label">Destination</div><div class="jd-val">Signature → user meta · Stripe ID → user meta · Odoo lead → "Consultation Active"</div></div>
          <div class="jd"><div class="jd-label">Advisor Notification</div><div class="jd-val">Assigned advisor receives an in-app notification. New client appears in their list immediately.</div></div>
        </div>
      </div>
    </div>
    <div class="jstep">
      <div class="jspine"><div class="jnum">6</div></div>
      <div class="jbody">
        <div class="jbody-title">Dashboard Access &amp; Ongoing Management</div>
        <div class="jbody-desc">The client accesses their personal dashboard. The advisor sees them immediately in the Advisor Dashboard. The engagement is live — metrics can be added, recommendations pushed, reports uploaded, and the AI strategy engine can be run against their account at any time.</div>
        <div class="jdetails">
          <div class="jd"><div class="jd-label">Client Sees</div><div class="jd-val">Marketing Maturity Score · Active services · KPIs · AI recommendations · Advisor card · Messages · Reports · Billing</div></div>
          <div class="jd"><div class="jd-label">Advisor Sees</div><div class="jd-val">Full client profile · All services and metrics · Data source connections · Goals · Activity timeline · 9 AI generators</div></div>
          <div class="jd"><div class="jd-label">Live Data</div><div class="jd-val">Google Ads and GA4 sync live performance into metrics. AI generates fresh strategies on demand.</div></div>
          <div class="jd"><div class="jd-label">Odoo (Ongoing)</div><div class="jd-val">Every service approval, recommendation approval, and budget change creates an Odoo task automatically.</div></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- DATA ARCHITECTURE -->
<div id="sec-data" class="section">
  <div class="page-header">
    <span class="page-label">Data Architecture</span>
    <h1 class="page-title">How data moves through the system</h1>
    <p class="page-desc">Every client input flows automatically through multiple systems. The same information simultaneously powers the client dashboard, the advisor view, Odoo CRM, and the AI strategy engine.</p>
  </div>
  <div class="sh">The data pipeline</div>
  <p class="sh-sub">From a client completing a field to an advisor receiving a strategy recommendation.</p>
  <div class="flow">
    <div class="fn"><div class="fn-name">Client Input</div><div class="fn-sub">Onboarding form</div></div>
    <div class="fa"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></div>
    <div class="fn"><div class="fn-name">WordPress DB</div><div class="fn-sub">six_* tables</div></div>
    <div class="fa"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></div>
    <div class="fn"><div class="fn-name">Odoo CRM</div><div class="fn-sub">Contacts &amp; tasks</div></div>
    <div class="fa"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></div>
    <div class="fn"><div class="fn-name">AI Engine</div><div class="fn-sub">Claude API</div></div>
    <div class="fa"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></div>
    <div class="fn"><div class="fn-name">Dashboards</div><div class="fn-sub">Client + Advisor</div></div>
  </div>
  <div class="sh">Database tables</div>
  <p class="sh-sub">All custom data uses the six_ prefix in WordPress. Standard WP tables handle accounts and API credential storage.</p>
  <table class="dt">
    <thead><tr><th>Table</th><th>Stores</th><th>Read by</th></tr></thead>
    <tbody>
      <tr><td><code>six_checkout_progress</code></td><td>Full onboarding profile — business data, goals, budget, completion status, readiness score</td><td>Both dashboards · AI engine · Odoo sync</td></tr>
      <tr><td><code>six_client_services</code></td><td>Services per client — slug, name, status (pending/active), monthly budget, approval timestamps</td><td>Both dashboards · Billing · Odoo</td></tr>
      <tr><td><code>six_metrics</code></td><td>KPI values per service — current, previous, and target with unit</td><td>Customer dashboard · Advisor · AI engine</td></tr>
      <tr><td><code>six_recommendations</code></td><td>AI and advisor recommendations — title, description, status (active/approved/dismissed)</td><td>Customer dashboard · Advisor</td></tr>
      <tr><td><code>six_assignments</code></td><td>Client-to-advisor pairing. One advisor per client.</td><td>All authenticated portal views</td></tr>
      <tr><td><code>six_messages</code></td><td>Threaded messages between client and advisor with read status</td><td>Messaging tabs on both portals</td></tr>
      <tr><td><code>six_notifications</code></td><td>In-app notifications — service approved, budget changed, recommendation pushed</td><td>Notification bell on both portals</td></tr>
      <tr><td><code>six_reports</code></td><td>Uploaded reports — title, file URL, type, period, upload date</td><td>Reports tabs on both portals</td></tr>
    </tbody>
  </table>
  <div class="sh">Automated trigger map</div>
  <p class="sh-sub">Every significant action triggers one or more automatic responses across connected systems.</p>
  <table class="dt">
    <thead><tr><th>Trigger</th><th>Automatic Response</th></tr></thead>
    <tbody>
      <tr><td>Client completes onboarding</td><td>Odoo contact created · Advisor notified · Services set to "pending" · Readiness score calculated</td></tr>
      <tr><td>Advisor approves a service</td><td>Status → active · Client notified · Odoo task created</td></tr>
      <tr><td>Client approves a recommendation</td><td>Status → approved · Advisor notified · Odoo task created with priority</td></tr>
      <tr><td>Client requests a budget change</td><td>Request queued for advisor approval · No change takes effect until approved</td></tr>
      <tr><td>Advisor sets budget directly</td><td>Budget updated immediately · Client notified via in-app notification</td></tr>
      <tr><td>Google Ads sync triggered</td><td>Live campaign data written to six_metrics · Visible in both dashboards immediately</td></tr>
      <tr><td>Onboarding abandoned mid-flow</td><td>Odoo lead tagged "Warm" with progress step · Sales team receives follow-up task</td></tr>
    </tbody>
  </table>
</div>

<!-- ONBOARDING -->
<div id="sec-onboarding" class="section">
  <div class="page-header">
    <span class="page-label">Feature Breakdown</span>
    <h1 class="page-title">Onboarding System</h1>
    <p class="page-desc">A consultative four-step flow designed to feel like a strategy session rather than a form. Light mode, professional typography, SVG icons. Advisors and sales reps are redirected away and never see this screen.</p>
  </div>
  <div class="sh">Step structure</div>
  <table class="dt">
    <thead><tr><th>Step</th><th>Purpose</th><th>Key Logic</th></tr></thead>
    <tbody>
      <tr><td><span class="tag tn">Step 1</span></td><td>Business profile and four marketing context questions</td><td>Goals and challenges are multi-select with up to six options each. Email is pre-populated from the login step. Business stage is a chip selector (Startup / Growing / Established / Scaling).</td></tr>
      <tr><td><span class="tag ta">Score</span></td><td>Marketing Readiness Score displayed (0–100)</td><td>Calculated from six weighted signals, displayed as an animated SVG ring. Links to a public explainer at /marketing-readiness-score/</td></tr>
      <tr><td><span class="tag tr">Step 2</span></td><td>Service selection and optional API connection</td><td>Service cards toggle independently. API connection fields appear conditionally. IDs saved to user meta on step completion.</td></tr>
      <tr><td><span class="tag tb">Step 3</span></td><td>Personalised strategy review and confirmation</td><td>Strategy statement generated from service combination and industry. Growth insights tailored to stated challenge. Goals auto-populate from selections.</td></tr>
      <tr><td><span class="tag tg">Step 4</span></td><td>Advisor card, plan summary, agreement, payment</td><td>Stripe SetupIntent — no charge today. Signature captured as typed full name. Trust messaging is minimal and text-based, not a colour box.</td></tr>
    </tbody>
  </table>
  <div class="sh">Conditional logic</div>
  <div class="callout tip"><p><strong>Resume:</strong> Returning logged-in users are dropped back at the step they left. Completed onboarding redirects immediately to /portal/. Advisors and sales reps are redirected to their own portals and never see the onboarding screen.</p></div>
  <table class="dt">
    <thead><tr><th>Condition</th><th>Behaviour</th></tr></thead>
    <tbody>
      <tr><td>Email already registered</td><td>Password field appears. Login flow. No duplicate account created.</td></tr>
      <tr><td>Advisor or sales role detected</td><td>Redirected to their respective portal immediately.</td></tr>
      <tr><td>"Yes, actively" selected in Q1</td><td>Platform question (Q1b) appears before goals.</td></tr>
      <tr><td>Google Ads selected in Step 2</td><td>Google Ads Customer ID input shown in the API connect panel.</td></tr>
      <tr><td>Social Media selected in Step 2</td><td>Meta Ad Account ID input shown in the API connect panel.</td></tr>
      <tr><td>Any service selected</td><td>GA4 Property ID field always shown — analytics applies across all services.</td></tr>
    </tbody>
  </table>
</div>

<!-- CUSTOMER DASHBOARD -->
<div id="sec-customer" class="section">
  <div class="page-header">
    <span class="page-label">Feature Breakdown</span>
    <h1 class="page-title">Customer Dashboard</h1>
    <p class="page-desc">The client's personal operating interface. Light mode by default with a dark mode toggle. All data is scoped exclusively to the logged-in client. They cannot access any other account.</p>
  </div>
  <div class="sh">Navigation sections</div>
  <table class="dt">
    <thead><tr><th>Section</th><th>What the client sees</th></tr></thead>
    <tbody>
      <tr><td><strong>Overview</strong></td><td>Marketing Maturity Score ring · Six KPI metric pills with progress fill bars · Six-month growth roadmap · 30-day action plan · Advisor-pushed recommendations</td></tr>
      <tr><td><strong>AI Insights</strong></td><td>AI-generated growth insights cached per client for 72 hours. Refreshes automatically on cache expiry.</td></tr>
      <tr><td><strong>Competitors</strong></td><td>Competitor websites from onboarding displayed with competitive context for positioning awareness.</td></tr>
      <tr><td><strong>Services</strong></td><td>Per-service metric cards with current, previous, and target values. Performance chart. AI upsell suggestions for inactive services.</td></tr>
      <tr><td><strong>Messages</strong></td><td>Direct messaging thread with the assigned advisor. Delivered via AJAX. Unread count shown in sidebar.</td></tr>
      <tr><td><strong>Reports</strong></td><td>PDF and image reports uploaded by the advisor. Viewable and downloadable with title and date.</td></tr>
      <tr><td><strong>Billing</strong></td><td>Active service budgets · Stripe payment method · Budget change request form (requires advisor approval).</td></tr>
      <tr><td><strong>Profile</strong></td><td>Editable personal information, business details, and competitor list.</td></tr>
    </tbody>
  </table>
  <div class="sh">Key interactions</div>
  <table class="dt">
    <thead><tr><th>Client Action</th><th>System Response</th></tr></thead>
    <tbody>
      <tr><td>Approves a recommendation</td><td>Status → approved · Advisor notified · Odoo task created</td></tr>
      <tr><td>Dismisses a recommendation</td><td>Removed from view · Logged for advisor visibility</td></tr>
      <tr><td>Requests a budget change</td><td>Request queued pending advisor approval · Advisor sees alert badge</td></tr>
      <tr><td>Requests a new service</td><td>Service record created as "pending" · Advisor notified</td></tr>
      <tr><td>Sends a message</td><td>Stored in six_messages · Advisor notified · Unread count updated</td></tr>
    </tbody>
  </table>
</div>

<!-- ADVISOR DASHBOARD -->
<div id="sec-advisor" class="section">
  <div class="page-header">
    <span class="page-label">Feature Breakdown</span>
    <h1 class="page-title">Advisor Dashboard</h1>
    <p class="page-desc">The full customer intelligence and control centre. Each client profile has seven internal tabs. Advisors manage the entire engagement without leaving this interface.</p>
  </div>
  <div class="sh">Client profile tabs</div>
  <table class="dt">
    <thead><tr><th>Tab</th><th>Advisor capability</th></tr></thead>
    <tbody>
      <tr><td><strong>Overview</strong></td><td>All metrics, active recommendations, pending budget change requests. Approve or decline budget changes inline.</td></tr>
      <tr><td><strong>Services &amp; Metrics</strong></td><td>Per-service metric cards. Add, edit, or delete metrics inline per service with current, previous, and target values.</td></tr>
      <tr><td><strong>AI Strategy</strong></td><td>Nine strategy generators using full client context. Output is editable before sending to the client.</td></tr>
      <tr><td><strong>Data Sources</strong></td><td>Set Google Ads Customer ID, GA4 Property ID, Meta Business/Account/Pixel IDs. Trigger Odoo sync on demand.</td></tr>
      <tr><td><strong>Activity</strong></td><td>Unified timeline of all service requests, recommendation events, and uploaded reports with budget change history.</td></tr>
      <tr><td><strong>Client Profile</strong></td><td>Edit personal information, business details, goals, competitor list, and per-service budgets. Changes notify the client.</td></tr>
      <tr><td><strong>Reports</strong></td><td>Upload PDF or image reports with a title. Past reports visible to both advisor and client.</td></tr>
    </tbody>
  </table>
  <div class="callout warn"><p><strong>Budget protocol:</strong> When an advisor sets a budget directly, the client is notified immediately and the change takes effect. When a client requests a budget change, the advisor must approve it — the request remains in a pending queue with no change until actioned.</p></div>
  <div class="sh">The nine AI strategy generators</div>
  <table class="dt">
    <thead><tr><th>Strategy Type</th><th>Output</th></tr></thead>
    <tbody>
      <tr><td>SEO Optimization</td><td>Three-point SEO strategy with specific traffic impact projections and timeframes</td></tr>
      <tr><td>Google Ads Optimization</td><td>Three-point Google Ads plan with expected ROAS or CPA improvement and numbers</td></tr>
      <tr><td>Social Media Growth</td><td>Platform-specific content tactics with engagement and follower growth expectations</td></tr>
      <tr><td>Brand Development</td><td>Brand actions with differentiator analysis and business impact assessment</td></tr>
      <tr><td>Quick Wins</td><td>Three actions achievable within 30 days with measurable expected outcomes</td></tr>
      <tr><td>Competitor Alerts</td><td>Three specific competitor threats with recommended counter-moves</td></tr>
      <tr><td>ROI Opportunities</td><td>High-ROI opportunities being missed with realistic projections</td></tr>
      <tr><td>Service Gaps</td><td>Critical missing services and the revenue impact of not having them</td></tr>
      <tr><td>Budget Optimization</td><td>Specific reallocation recommendations with performance improvement projections</td></tr>
    </tbody>
  </table>
</div>

<!-- INTEGRATIONS -->
<div id="sec-integrations" class="section">
  <div class="page-header">
    <span class="page-label">Feature Breakdown</span>
    <h1 class="page-title">Integrations</h1>
    <p class="page-desc">Four external systems connected to the portal. Each integration is configured per client by the advisor and synchronises data automatically into both dashboard views.</p>
  </div>
  <div class="int-card">
    <div class="int-head"><div><div class="int-head-name">Google Ads</div><div class="int-head-sub">MCC manager account · per-client Customer IDs</div></div><span class="int-badge">Per Client</span></div>
    <div class="int-body">
      <div class="int-col"><h5>ID Collected</h5><p>Google Ads Customer ID in format <code>123-456-7890</code>. Entered during onboarding (Step 2) or by the advisor in the Data Sources tab.</p></div>
      <div class="int-col"><h5>Purpose</h5><p>Grants our MCC manager account read-only access to campaign data. No ad changes are ever made without explicit client authorisation.</p></div>
      <div class="int-col"><h5>Output</h5><p>Pulls impressions, clicks, spend, and conversions into <code>six_metrics</code>. Visible in the Google Ads service tab and the advisor metrics panel.</p></div>
    </div>
  </div>
  <div class="int-card">
    <div class="int-head"><div><div class="int-head-name">Google Analytics 4</div><div class="int-head-sub">GA4 Property ID · read-only via service account JWT</div></div><span class="int-badge">Per Client</span></div>
    <div class="int-body">
      <div class="int-col"><h5>ID Collected</h5><p>GA4 Property ID — nine-digit number from GA4 Admin → Property Settings. Applies to any client with at least one active service.</p></div>
      <div class="int-col"><h5>Purpose</h5><p>Website traffic and behaviour data provides the complete performance picture alongside paid channel data — sessions, bounce rate, conversion events.</p></div>
      <div class="int-col"><h5>Output</h5><p>Populates Sessions and Traffic metric cards. Connection can be tested in WP Admin → 6ix Developers Settings.</p></div>
    </div>
  </div>
  <div class="int-card">
    <div class="int-head"><div><div class="int-head-name">Meta Ads</div><div class="int-head-sub">Business ID · Ad Account ID · Pixel ID per client</div></div><span class="int-badge">Per Client</span></div>
    <div class="int-body">
      <div class="int-col"><h5>IDs Collected</h5><p>Meta Business ID · Ad Account ID (<code>act_XXXXXXXXX</code>) · Pixel ID. All three set in the advisor Data Sources tab or during onboarding.</p></div>
      <div class="int-col"><h5>Purpose</h5><p>Full Meta Ads performance visibility — reach, frequency, spend, and leads — for clients running Facebook or Instagram campaigns.</p></div>
      <div class="int-col"><h5>Output</h5><p>Campaign performance in the Social Media service tab. Pixel ID provides conversion tracking context for AI recommendations.</p></div>
    </div>
  </div>
  <div class="int-card">
    <div class="int-head"><div><div class="int-head-name">Odoo CRM</div><div class="int-head-sub">XML-RPC sync · contacts, leads, and tasks</div></div><span class="int-badge">Automatic</span></div>
    <div class="int-body">
      <div class="int-col"><h5>Data Sent</h5><p>Contact: name, email, phone, business, industry, location. Lead: services, total budget, goals, readiness score, onboarding progress.</p></div>
      <div class="int-col"><h5>Trigger Points</h5><p>Contact on account creation · Lead on Step 1 complete · Task on service approval · Task on recommendation approval · Follow-up on abandoned onboarding.</p></div>
      <div class="int-col"><h5>Why It Matters</h5><p>The sales team has full pipeline visibility without accessing WordPress. Manual sync can be triggered from the advisor Data Sources tab per client.</p></div>
    </div>
  </div>
</div>

<!-- BUG REPORT -->
<div id="sec-bug" class="section">
  <div class="page-header">
    <span class="page-label">QA &amp; Feedback</span>
    <h1 class="page-title">Report a Bug</h1>
    <p class="page-desc">Complete the form below. All reports are logged to the Issue Tracker and visible to the full team immediately.</p>
  </div>
  <div class="callout warn"><p><strong>Critical issues</strong> affecting live customers should be escalated directly to the development team via Slack or WhatsApp immediately — do not rely solely on the tracker workflow.</p></div>
  <div class="fb">
    <div class="fb-head"><h3>Bug Report</h3></div>
    <div class="fb-body">
      <div class="frow">
        <div class="field"><label class="flabel">Bug Title *</label><input class="finput" id="bug-title" placeholder="Brief, descriptive title"></div>
        <div class="field"><label class="flabel">Affected Section *</label>
          <select class="fselect" id="bug-section">
            <option value="">Select section…</option>
            <optgroup label="Onboarding"><option>Onboarding — Email &amp; Login</option><option>Onboarding — Business Profile (Step 1)</option><option>Onboarding — Services (Step 2)</option><option>Onboarding — Strategy (Step 3)</option><option>Onboarding — Payment (Step 4)</option></optgroup>
            <optgroup label="Customer Dashboard"><option>Customer Dashboard — Overview</option><option>Customer Dashboard — Services</option><option>Customer Dashboard — Messages</option><option>Customer Dashboard — Billing</option><option>Customer Dashboard — Profile</option></optgroup>
            <optgroup label="Advisor Dashboard"><option>Advisor Dashboard — Client List</option><option>Advisor Dashboard — AI Strategy</option><option>Advisor Dashboard — Data Sources</option><option>Advisor Dashboard — Metrics</option></optgroup>
            <optgroup label="Integrations"><option>Integration — Google Ads</option><option>Integration — Google Analytics 4</option><option>Integration — Meta Ads</option><option>Integration — Odoo CRM</option><option>Integration — Stripe</option></optgroup>
            <option>Other</option>
          </select>
        </div>
      </div>
      <div class="field"><label class="flabel">Description *</label><textarea class="ftextarea" id="bug-desc" placeholder="What is happening? What should be happening instead?"></textarea></div>
      <div class="field"><label class="flabel">Steps to Reproduce</label><textarea class="ftextarea" id="bug-steps" placeholder="1. Navigate to…&#10;2. Click on…&#10;3. Notice that…"></textarea></div>
      <div class="field">
        <label class="flabel">Severity *</label>
        <div class="sev">
          <button class="sev-b" onclick="setSev('low',this,'bug-severity')">Low — cosmetic or minor</button>
          <button class="sev-b" onclick="setSev('medium',this,'bug-severity')">Medium — feature impaired</button>
          <button class="sev-b" onclick="setSev('high',this,'bug-severity')">High — blocks a user</button>
        </div>
        <input type="hidden" id="bug-severity">
      </div>
      <div class="field"><label class="flabel">Your Name</label><input class="finput" id="bug-reporter" placeholder="For follow-up if needed"></div>
      <button class="btn-p" onclick="submitBug()"><svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>Submit Report</button>
    </div>
  </div>
</div>

<!-- SUGGEST -->
<div id="sec-suggest" class="section">
  <div class="page-header">
    <span class="page-label">QA &amp; Feedback</span>
    <h1 class="page-title">Suggest an Improvement</h1>
    <p class="page-desc">Have an idea that would improve the product or the team's workflow? All suggestions are reviewed in sprint planning.</p>
  </div>
  <div class="fb">
    <div class="fb-head"><h3>Improvement Suggestion</h3></div>
    <div class="fb-body">
      <div class="frow">
        <div class="field"><label class="flabel">Idea Title *</label><input class="finput" id="sug-title" placeholder="Brief summary of the idea"></div>
        <div class="field"><label class="flabel">Affected Area</label>
          <select class="fselect" id="sug-section">
            <option value="">Select area…</option>
            <option>Onboarding</option><option>Customer Dashboard</option><option>Advisor Dashboard</option>
            <option>AI Strategy Engine</option><option>Integrations</option><option>Notifications</option>
            <option>Billing &amp; Payments</option><option>Overall UX / Design</option><option>Performance</option><option>Other</option>
          </select>
        </div>
      </div>
      <div class="field"><label class="flabel">Description *</label><textarea class="ftextarea" id="sug-desc" style="min-height:110px" placeholder="Describe the idea. What problem does it solve? Who benefits and how?"></textarea></div>
      <div class="field">
        <label class="flabel">Priority</label>
        <div class="sev">
          <button class="sev-b" onclick="setSev('low',this,'sug-priority')">Nice to have</button>
          <button class="sev-b" onclick="setSev('medium',this,'sug-priority')">Would improve UX meaningfully</button>
          <button class="sev-b" onclick="setSev('high',this,'sug-priority')">High impact</button>
        </div>
        <input type="hidden" id="sug-priority">
      </div>
      <div class="field"><label class="flabel">Your Name</label><input class="finput" id="sug-reporter" placeholder="Optional"></div>
      <button class="btn-p blue" onclick="submitSug()"><svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>Submit Suggestion</button>
    </div>
  </div>
</div>

<!-- TRACKER -->
<div id="sec-tracker" class="section">
  <div class="page-header">
    <span class="page-label">QA &amp; Feedback</span>
    <h1 class="page-title">Issue Tracker</h1>
    <p class="page-desc">All submitted bugs and suggestions. Filter by type or status. Update status inline. Data persists in your browser locally.</p>
  </div>
  <div class="tracker-bar">
    <div class="fbar">
      <button class="fb2 active" onclick="filterTkts('all',this)">All</button>
      <button class="fb2" onclick="filterTkts('bug',this)">Bugs</button>
      <button class="fb2" onclick="filterTkts('suggestion',this)">Suggestions</button>
      <button class="fb2" onclick="filterTkts('open',this)">Open</button>
      <button class="fb2" onclick="filterTkts('in-progress',this)">In Progress</button>
      <button class="fb2" onclick="filterTkts('fixed',this)">Resolved</button>
    </div>
    <div style="display:flex;gap:6px">
      <button class="btn-s" onclick="loadAndRender()" title="Refresh from database">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/></svg>
        Refresh
      </button>
      <button class="btn-s" onclick="clearAll()">Clear all</button>
    </div>
  </div>
  <div id="tracker-loading" style="padding:40px;text-align:center;color:var(--ink4);font-size:13px;font-style:italic">Loading issues…</div>
  <div id="ticket-list"></div>
  <div id="empty-state" class="empty-st" style="display:none">
    <svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
    <p>No issues on record. Submit a bug report or suggestion to begin tracking.</p>
  </div>
</div>

</div></main>

<div class="toast" id="toast"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg><span id="toast-msg"></span></div>

<script>
document.getElementById('topbar-date').textContent =
  new Date().toLocaleDateString('en-US',{weekday:'long',year:'numeric',month:'long',day:'numeric'});

function show(id,el){
  document.querySelectorAll('.section').forEach(s=>s.classList.remove('active'));
  document.querySelectorAll('.nav-link').forEach(n=>n.classList.remove('active'));
  var sec=document.getElementById('sec-'+id);
  if(sec)sec.classList.add('active');
  if(el)el.classList.add('active');
  window.scrollTo({top:0,behavior:'smooth'});
}
function toast(msg){
  var t=document.getElementById('toast');
  document.getElementById('toast-msg').textContent=msg;
  t.classList.add('show');
  setTimeout(()=>t.classList.remove('show'),3000);
}
function setSev(val,btn,field){
  btn.closest('.sev').querySelectorAll('.sev-b')
    .forEach(b=>b.classList.remove('sl','sm','sh'));
  btn.classList.add({low:'sl',medium:'sm',high:'sh'}[val]);
  document.getElementById(field).value=val;
}
// ── Shared storage via WordPress AJAX ────────────────────────────────────────
// Issues are stored server-side so ALL team members see the same data.
// Requires the WordPress hub AJAX handlers to be registered (see functions.php note).
// Falls back gracefully to localStorage if WordPress AJAX is unavailable.

// WordPress-injected credentials — always correct nonce for this user session
var HUB_AJAX  = <?php echo wp_json_encode( $hub_ajax_url ); ?>;
var HUB_NONCE = <?php echo wp_json_encode( $hub_nonce ); ?>;
var HUB_USER  = <?php echo wp_json_encode( $hub_name ); ?>;

async function getIssues() {
  try {
    var resp = await fetch(HUB_AJAX, {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: new URLSearchParams({action: 'six_hub_get_issues', nonce: HUB_NONCE})
    });
    if (!resp.ok) throw new Error('HTTP ' + resp.status);
    var data = await resp.json();
    if (data && data.success && Array.isArray(data.data)) {
      // Sync any queued localStorage items to DB
      _syncLocalQueue();
      return data.data;
    }
    console.warn('Hub: DB read failed', data);
    return JSON.parse(localStorage.getItem('six_hub_issues') || '[]');
  } catch(e) {
    console.warn('Hub: AJAX error', e);
    return JSON.parse(localStorage.getItem('six_hub_issues') || '[]');
  }
}

async function saveIssues(issues) {
  try {
    var resp = await fetch(HUB_AJAX, {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: new URLSearchParams({
        action: 'six_hub_save_issues',
        nonce: HUB_NONCE,
        issues: JSON.stringify(issues)
      })
    });
    if (!resp.ok) throw new Error('HTTP ' + resp.status);
    var data = await resp.json();
    if (data && data.success) {
      // Successfully saved to DB — clear any localStorage queue
      localStorage.removeItem('six_hub_issues_queue');
      localStorage.removeItem('six_hub_issues'); // clear old local copy
    } else {
      throw new Error(data?.data || 'Save failed');
    }
  } catch(e) {
    console.warn('Hub: save failed, queuing locally:', e);
    // Queue locally — will sync on next successful DB read
    localStorage.setItem('six_hub_issues_queue', JSON.stringify(issues));
    toast('⚠ Saved locally — will sync when connection restored.');
  }
  _updateBadgeUI(issues);
  renderTkts(issues);
}

// Sync any locally-queued issues to DB on reconnect
async function _syncLocalQueue() {
  var queued = localStorage.getItem('six_hub_issues_queue');
  if (!queued) return;
  try {
    var resp = await fetch(HUB_AJAX, {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: new URLSearchParams({
        action: 'six_hub_save_issues',
        nonce: HUB_NONCE,
        issues: queued
      })
    });
    var data = await resp.json();
    if (data && data.success) {
      localStorage.removeItem('six_hub_issues_queue');
      toast('✓ Local queue synced to database.');
    }
  } catch(e) { /* keep queue */ }
}

function _updateBadgeUI(issues) {
  var open = (issues || []).filter(i => i.status === 'open').length;
  var el = document.getElementById('tracker-count');
  el.textContent = open;
  el.className = 'nav-count' + (open > 0 ? ' show' : '');
}
async function updateBadge(){
  var issues = await getIssues();
  _updateBadgeUI(issues);
}
async function submitBug(){
  var title=document.getElementById('bug-title').value.trim();
  var desc=document.getElementById('bug-desc').value.trim();
  var sev=document.getElementById('bug-severity').value;
  if(!title||!desc||!sev){toast('Please complete title, description, and severity.');return;}
  var btn = document.querySelector('#sec-bug .btn-p');
  if(btn){ btn.textContent = 'Saving…'; btn.disabled = true; }
  var issues = await getIssues();
  issues.unshift({id:Date.now(),type:'bug',status:'open',title,
    section:document.getElementById('bug-section').value,desc,
    steps:document.getElementById('bug-steps').value.trim(),
    severity:sev,reporter:document.getElementById('bug-reporter').value.trim(),
    created:new Date().toISOString().split('T')[0]});
  await saveIssues(issues);
  ['bug-title','bug-desc','bug-steps','bug-reporter'].forEach(id=>document.getElementById(id).value='');
  document.getElementById('bug-section').value='';
  document.getElementById('bug-severity').value='';
  document.querySelectorAll('#sec-bug .sev-b').forEach(b=>b.classList.remove('sl','sm','sh'));
  if(btn){ btn.innerHTML='<svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>Submit Report'; btn.disabled=false; }
  toast('Bug report submitted — visible to all team members.');
}
async function submitSug(){
  var title=document.getElementById('sug-title').value.trim();
  var desc=document.getElementById('sug-desc').value.trim();
  if(!title||!desc){toast('Please complete title and description.');return;}
  var btn = document.querySelector('#sec-suggest .btn-p');
  if(btn){ btn.textContent='Saving…'; btn.disabled=true; }
  var issues = await getIssues();
  issues.unshift({id:Date.now(),type:'suggestion',status:'open',title,
    section:document.getElementById('sug-section').value,desc,
    severity:document.getElementById('sug-priority').value||'medium',
    reporter:document.getElementById('sug-reporter').value.trim(),
    created:new Date().toISOString().split('T')[0]});
  await saveIssues(issues);
  ['sug-title','sug-desc','sug-reporter'].forEach(id=>document.getElementById(id).value='');
  document.getElementById('sug-section').value='';
  document.getElementById('sug-priority').value='';
  document.querySelectorAll('#sec-suggest .sev-b').forEach(b=>b.classList.remove('sl','sm','sh'));
  if(btn){ btn.innerHTML='<svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>Submit Suggestion'; btn.disabled=false; }
  toast('Suggestion submitted — visible to all team members.');
}
var activeF='all';
function filterTkts(f,btn){
  activeF=f;
  document.querySelectorAll('.fb2').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  loadAndRender();
}
async function loadAndRender(){
  var issues = await getIssues();
  renderTkts(issues);
}
function renderTkts(issues){
  if(!issues){ loadAndRender(); return; }
  var loading = document.getElementById('tracker-loading');
  if(loading) loading.style.display = 'none';
  var shown=issues.filter(i=>{
    if(activeF==='all')return true;
    if(activeF==='bug')return i.type==='bug';
    if(activeF==='suggestion')return i.type==='suggestion';
    return i.status===activeF;
  });
  var list=document.getElementById('ticket-list');
  var empty=document.getElementById('empty-state');
  if(!shown.length){list.innerHTML='';empty.style.display='block';return;}
  empty.style.display='none';
  var sc={high:'var(--brand)',medium:'var(--gold)',low:'var(--success)'};
  var typeTag={bug:'<span class="tag tr">Bug</span>',suggestion:'<span class="tag tb">Suggestion</span>'};
  var sevTag={high:'<span class="tag tr">High</span>',medium:'<span class="tag ta">Medium</span>',low:'<span class="tag tg">Low</span>'};
  list.innerHTML='<div class="tklist">'+shown.map(i=>`
    <div class="tkt">
      <div class="tkt-stripe" style="background:${sc[i.severity]||'var(--border2)'}"></div>
      <div class="tkt-body">
        <div class="tkt-title">${esc(i.title)}</div>
        <div class="tkt-meta">
          <span>${i.created}</span>
          ${i.section?'<span>'+esc(i.section)+'</span>':''}
          ${i.reporter?'<span>'+esc(i.reporter)+'</span>':''}
        </div>
      </div>
      <div class="tkt-actions">
        ${typeTag[i.type]||''}
        ${sevTag[i.severity]||''}
        <select class="st-sel" onchange="changeStatus(${i.id},this.value)">
          <option ${i.status==='open'?'selected':''} value="open">Open</option>
          <option ${i.status==='in-progress'?'selected':''} value="in-progress">In Progress</option>
          <option ${i.status==='fixed'?'selected':''} value="fixed">Resolved</option>
        </select>
        <button onclick="deleteIssue(${i.id})" class="tkt-del" title="Delete issue">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
        </button>
      </div>
    </div>`).join('')+'</div>';
}
async function changeStatus(id, status){
  // Use dedicated endpoint — only sends one record, no race conditions
  try {
    var resp = await fetch(HUB_AJAX, {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: new URLSearchParams({
        action: 'six_hub_update_status',
        nonce: HUB_NONCE,
        issue_id: id,
        status: status
      })
    });
    var data = await resp.json();
    if (!data || !data.success) throw new Error(data?.data || 'Update failed');
    // Refresh the full list
    var issues = await getIssues();
    _updateBadgeUI(issues);
    renderTkts(issues);
  } catch(e) {
    // Fallback: full save
    var issues = await getIssues();
    var idx = issues.findIndex(i => i.id === id);
    if(idx > -1){ issues[idx].status = status; await saveIssues(issues); }
  }
}
async function clearAll(){
  if(!confirm('Remove all issues from the shared database? This cannot be undone for any team member.'))return;
  await saveIssues([]);
  renderTkts([]);
}

// ── Delete single issue ─────────────────────────────────────────────────
async function deleteIssue(id){
  if(!confirm('Delete this issue?'))return;
  try {
    var resp = await fetch(HUB_AJAX, {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: new URLSearchParams({
        action: 'six_hub_delete_issue',
        nonce: HUB_NONCE,
        issue_id: id
      })
    });
    var data = await resp.json();
    if (!data || !data.success) throw new Error(data?.data || 'Delete failed');
    toast('Issue deleted.');
    var issues = await getIssues();
    _updateBadgeUI(issues);
    renderTkts(issues);
  } catch(e) {
    toast('Delete failed — try again.');
  }
}
function esc(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
// ── Init — load shared issues on startup ─────────────────────────────────
async function init(){
  // Pre-fill reporter fields with logged-in user name
  ['bug-reporter','sug-reporter'].forEach(function(id){
    var el = document.getElementById(id);
    if(el && !el.value && typeof HUB_USER !== 'undefined') el.value = HUB_USER;
  });
  await updateBadge();
  var issues = await getIssues();
  renderTkts(issues);
}
init();

// ── Sidebar mobile toggle ─────────────────────────────────────────────────
function toggleSidebar(){
  var sb  = document.querySelector('.sidebar');
  var hb  = document.getElementById('hamburger');
  var ov  = document.getElementById('sidebar-overlay');
  var isOpen = sb.classList.contains('open');
  if(isOpen){ closeSidebar(); } else {
    sb.classList.add('open');
    hb.classList.add('open');
    ov.style.display = 'block';
    document.body.style.overflow = 'hidden';
  }
}
function closeSidebar(){
  var sb = document.querySelector('.sidebar');
  var hb = document.getElementById('hamburger');
  var ov = document.getElementById('sidebar-overlay');
  sb.classList.remove('open');
  hb.classList.remove('open');
  ov.style.display = 'none';
  document.body.style.overflow = '';
}
// Close sidebar when a nav item is clicked on mobile
document.querySelectorAll('.nav-link').forEach(function(link){
  link.addEventListener('click', function(){
    if(window.innerWidth <= 768) closeSidebar();
  });
});
</script>
</body>
</html>
