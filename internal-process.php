<?php
/**
 * Template Name: Internal Process Page
 * Template Post Type: page
 *
 * HOW IT WORKS — shared bug/suggestion storage:
 * WordPress injects a valid nonce + admin-ajax.php URL at the top of every
 * page load. The JS uses those to POST to our AJAX handlers, which read/write
 * the 'six_hub_issues' site option in the database. Every team member who
 * opens this page hits the same option — so all bugs and suggestions are
 * shared in real time across all browsers and devices.
 *
 * REQUIRED: portal/ajax-handlers.php must be included by your theme and must
 * register wp_ajax_six_hub_get_issues and wp_ajax_six_hub_save_issues.
 */

// Inject credentials so JS can talk to WordPress AJAX
$_hub_nonce    = wp_create_nonce( 'six_hub_nonce' );
$_hub_ajax_url = admin_url( 'admin-ajax.php' );
$_hub_user     = wp_get_current_user()->display_name ?: wp_get_current_user()->user_login;
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

/* Ticket wrapper — collapses/expands */
.tkt-wrap { background: var(--surface); border-bottom: 1px solid var(--border); }
.tkt-wrap:last-child { border-bottom: none; }

/* Collapsed row — always visible */
.tkt { display: grid; grid-template-columns: 5px 1fr auto; cursor: pointer; transition: background 0.1s; }
.tkt:hover { background: var(--surface2); }
.tkt-stripe { }
.tkt-body { padding: 15px 20px; min-width: 0; }
.tkt-title { font-size: 13px; font-weight: 600; color: var(--ink); margin-bottom: 5px; display: flex; align-items: center; gap: 8px; }
.tkt-chevron { margin-left: auto; flex-shrink: 0; color: var(--ink4); transition: transform 0.2s; }
.tkt-wrap.open .tkt-chevron { transform: rotate(180deg); }
.tkt-meta { font-size: 11px; color: var(--ink4); display: flex; gap: 12px; flex-wrap: wrap; font-family: var(--fmono); }
.tkt-tags { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 6px; }
.tkt-actions { padding: 12px 16px; display: flex; align-items: center; gap: 8px; flex-shrink: 0; border-left: 1px solid var(--border); }

/* Expanded detail panel */
.tkt-detail {
  display: none;
  border-top: 1px solid var(--border);
  background: var(--surface2);
  padding: 20px 24px;
  animation: slideDown 0.18s ease;
}
.tkt-wrap.open .tkt-detail { display: block; }
@keyframes slideDown { from { opacity:0; transform:translateY(-4px); } to { opacity:1; transform:none; } }

.tkt-detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
@media (max-width: 640px) { .tkt-detail-grid { grid-template-columns: 1fr; } }

.tkt-detail-field { }
.tkt-detail-label {
  font-size: 9px; font-weight: 600; letter-spacing: 0.12em;
  text-transform: uppercase; color: var(--ink4);
  margin-bottom: 5px;
}
.tkt-detail-val {
  font-size: 13px; color: var(--ink2); line-height: 1.65;
  background: var(--surface); border: 1px solid var(--border);
  padding: 10px 12px;
}
.tkt-detail-val.pre {
  font-family: var(--fmono); font-size: 12px;
  white-space: pre-wrap; word-break: break-word;
}
.tkt-detail-actions {
  display: flex; align-items: center; gap: 8px;
  padding-top: 14px; border-top: 1px solid var(--border);
  flex-wrap: wrap;
}

.tkt-del { padding: 6px 10px; border: 1px solid var(--border); background: var(--surface); cursor: pointer; color: var(--ink3); transition: all 0.12s; display: inline-flex; align-items: center; gap: 5px; font-size: 12px; font-family: var(--fbody); }
.tkt-del:hover { background: #FEF5F4; border-color: var(--brand); color: var(--brand); }
.st-sel { padding: 7px 10px; border: 1px solid var(--border); background: var(--surface); color: var(--ink2); font-size: 12px; font-family: var(--fbody); cursor: pointer; -webkit-appearance: none; }
.st-sel:focus { outline: none; border-color: var(--brand); }
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
  .sidebar { transform: translateX(-100%); transition: transform 0.28s cubic-bezier(0.4,0,0.2,1); box-shadow: none; z-index: 500; }
  .sidebar.open { transform: translateX(0); box-shadow: 4px 0 24px rgba(0,0,0,0.12); }
  .main { margin-left: 0 !important; }
  :root { --sw: 252px; }
  .tb-brand { width: auto; border-right: none; }
  .tb-section { display: none; }
  .tb-right { padding: 0 14px; gap: 10px; }
  .tb-date { display: none; }
  .content { padding: 28px 18px 64px; }
  .page-title { font-size: 28px; }
  .hamburger { display: flex !important; }
  .tkt-actions { flex-wrap: wrap; padding: 10px 12px; gap: 6px; }
  .sev { flex-direction: column; }
  .sev-b { margin-left: 0 !important; }
}
.hamburger { display: none; align-items: center; justify-content: center; width: 40px; height: 40px; cursor: pointer; background: none; border: none; flex-shrink: 0; padding: 8px; margin-right: 4px; }
.hamburger span { display: block; width: 18px; height: 1.5px; background: rgba(255,255,255,0.7); transition: all 0.22s ease; position: relative; }
.hamburger span::before, .hamburger span::after { content: ''; position: absolute; left: 0; width: 18px; height: 1.5px; background: rgba(255,255,255,0.7); transition: all 0.22s ease; }
.hamburger span::before { top: -5px; }
.hamburger span::after  { top: 5px; }
.hamburger.open span { background: transparent; }
.hamburger.open span::before { transform: rotate(45deg); top: 0; }
.hamburger.open span::after  { transform: rotate(-45deg); top: 0; }
.sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(26,24,22,0.5); z-index: 400; cursor: pointer; }
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>
<header class="topbar">
  <button class="hamburger" id="hamburger" onclick="toggleSidebar()" aria-label="Menu"><span></span></button>
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
    <div class="tile"><div class="tile-num">04</div><div class="tile-title">AI Strategy Engine</div><div class="tile-desc">Nine strategy generators powered by Claude AI using real client context including metrics and competitor data.</div></div>
    <div class="tile"><div class="tile-num">05</div><div class="tile-title">Odoo CRM Integration</div><div class="tile-desc">Every lead, service approval, and recommendation approval automatically creates or updates contacts and tasks in Odoo.</div></div>
    <div class="tile"><div class="tile-num">06</div><div class="tile-title">Google &amp; Meta APIs</div><div class="tile-desc">Per-client Google Ads, GA4, and Meta account credentials connect live campaign data directly into each dashboard.</div></div>
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
    <p class="page-desc">Six sequential steps every new client moves through.</p>
  </div>
  <div class="journey">
    <div class="jstep"><div class="jspine"><div class="jnum">1</div><div class="jspine-line"></div></div><div class="jbody"><div class="jbody-title">Landing &amp; Email Entry</div><div class="jbody-desc">The client visits <code>/get-started/</code> and enters their email. Returning users see the login flow; new users have an account created automatically.</div><div class="jdetails"><div class="jd"><div class="jd-label">Data Collected</div><div class="jd-val">Email address</div></div><div class="jd"><div class="jd-label">Destination</div><div class="jd-val">WordPress users table · Odoo contact created immediately</div></div></div></div></div>
    <div class="jstep"><div class="jspine"><div class="jnum">2</div><div class="jspine-line"></div></div><div class="jbody"><div class="jbody-title">Business Profile &amp; Marketing Context</div><div class="jbody-desc">The client completes their business profile and answers four guided questions. A Marketing Readiness Score is calculated immediately.</div><div class="jdetails"><div class="jd"><div class="jd-label">Data Collected</div><div class="jd-val">Name · Business · Website · Industry · Location · Goals · Challenges · Budget</div></div><div class="jd"><div class="jd-label">Destination</div><div class="jd-val">six_checkout_progress · Odoo lead fields · AI context</div></div></div></div></div>
    <div class="jstep"><div class="jspine"><div class="jnum">3</div><div class="jspine-line"></div></div><div class="jbody"><div class="jbody-title">Service Selection &amp; API Connection</div><div class="jbody-desc">The client selects services, sets monthly budgets, and optionally connects existing ad accounts.</div><div class="jdetails"><div class="jd"><div class="jd-label">Data Collected</div><div class="jd-val">Selected services · Budgets · Google Ads ID · GA4 ID · Meta ID</div></div><div class="jd"><div class="jd-label">Destination</div><div class="jd-val">six_client_services · user meta · Odoo opportunity value</div></div></div></div></div>
    <div class="jstep"><div class="jspine"><div class="jnum">4</div><div class="jspine-line"></div></div><div class="jbody"><div class="jbody-title">Strategy Review</div><div class="jbody-desc">A personalised strategy statement is generated. Growth insights and goal chips are shown. The client confirms before proceeding.</div></div></div>
    <div class="jstep"><div class="jspine"><div class="jnum">5</div><div class="jspine-line"></div></div><div class="jbody"><div class="jbody-title">Agreement &amp; Free Consultation Setup</div><div class="jbody-desc">Client types their name as a digital signature and adds a payment method via Stripe SetupIntent. No charge made. 10-day consultation begins.</div></div></div>
    <div class="jstep"><div class="jspine"><div class="jnum">6</div></div><div class="jbody"><div class="jbody-title">Dashboard Access &amp; Ongoing Management</div><div class="jbody-desc">Client accesses their personal dashboard. Advisor sees them immediately and can manage services, push recommendations, and run AI strategies.</div></div></div>
  </div>
</div>

<!-- DATA ARCHITECTURE -->
<div id="sec-data" class="section">
  <div class="page-header">
    <span class="page-label">Data Architecture</span>
    <h1 class="page-title">How data moves through the system</h1>
    <p class="page-desc">Every client input flows automatically through multiple systems simultaneously.</p>
  </div>
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
  <table class="dt">
    <thead><tr><th>Table</th><th>Stores</th><th>Read by</th></tr></thead>
    <tbody>
      <tr><td><code>six_checkout_progress</code></td><td>Full onboarding profile — business data, goals, budget, readiness score</td><td>Both dashboards · AI engine · Odoo</td></tr>
      <tr><td><code>six_client_services</code></td><td>Services per client — status, budget, approval timestamps</td><td>Both dashboards · Billing · Odoo</td></tr>
      <tr><td><code>six_metrics</code></td><td>KPI values per service — current, previous, target</td><td>Customer dashboard · Advisor · AI</td></tr>
      <tr><td><code>six_recommendations</code></td><td>AI and advisor recommendations with status</td><td>Customer dashboard · Advisor</td></tr>
      <tr><td><code>six_assignments</code></td><td>Client-to-advisor pairing</td><td>All portal views</td></tr>
      <tr><td><code>six_messages</code></td><td>Threaded messages with read status</td><td>Messaging tabs on both portals</td></tr>
      <tr><td><code>six_notifications</code></td><td>In-app notifications</td><td>Notification bell on both portals</td></tr>
      <tr><td><code>six_reports</code></td><td>Uploaded reports — title, file URL, date</td><td>Reports tabs on both portals</td></tr>
    </tbody>
  </table>
</div>

<!-- ONBOARDING -->
<div id="sec-onboarding" class="section">
  <div class="page-header">
    <span class="page-label">Feature Breakdown</span>
    <h1 class="page-title">Onboarding System</h1>
    <p class="page-desc">A consultative four-step flow designed to feel like a strategy session rather than a form.</p>
  </div>
  <div class="sh">Step structure</div>
  <table class="dt">
    <thead><tr><th>Step</th><th>Purpose</th><th>Key Logic</th></tr></thead>
    <tbody>
      <tr><td><span class="tag tn">Step 1</span></td><td>Business profile and four marketing context questions</td><td>Goals and challenges are multi-select. Business stage is a chip selector.</td></tr>
      <tr><td><span class="tag ta">Score</span></td><td>Marketing Readiness Score (0–100)</td><td>Calculated from six weighted signals, displayed as an animated SVG ring.</td></tr>
      <tr><td><span class="tag tr">Step 2</span></td><td>Service selection and optional API connection</td><td>Service cards toggle independently. API fields appear conditionally.</td></tr>
      <tr><td><span class="tag tb">Step 3</span></td><td>Personalised strategy review and confirmation</td><td>Strategy generated from service combination and industry.</td></tr>
      <tr><td><span class="tag tg">Step 4</span></td><td>Advisor card, plan summary, agreement, payment</td><td>Stripe SetupIntent — no charge today. Signature captured as typed full name.</td></tr>
    </tbody>
  </table>
</div>

<!-- CUSTOMER DASHBOARD -->
<div id="sec-customer" class="section">
  <div class="page-header">
    <span class="page-label">Feature Breakdown</span>
    <h1 class="page-title">Customer Dashboard</h1>
    <p class="page-desc">The client's personal operating interface. Light mode by default with a dark mode toggle.</p>
  </div>
  <div class="sh">Navigation sections</div>
  <table class="dt">
    <thead><tr><th>Section</th><th>What the client sees</th></tr></thead>
    <tbody>
      <tr><td><strong>Overview</strong></td><td>Marketing Maturity Score · KPI metrics · Six-month growth roadmap · 30-day action plan · Recommendations</td></tr>
      <tr><td><strong>Services</strong></td><td>Per-service metric cards with current, previous, and target values. Performance chart.</td></tr>
      <tr><td><strong>Messages</strong></td><td>Direct messaging thread with the assigned advisor. Unread count shown in sidebar.</td></tr>
      <tr><td><strong>Reports</strong></td><td>PDF and image reports uploaded by the advisor. Viewable and downloadable.</td></tr>
      <tr><td><strong>Billing</strong></td><td>Active service budgets · Stripe payment method · Budget change request form.</td></tr>
      <tr><td><strong>Profile</strong></td><td>Editable personal information, business details, and competitor list.</td></tr>
    </tbody>
  </table>
</div>

<!-- ADVISOR DASHBOARD -->
<div id="sec-advisor" class="section">
  <div class="page-header">
    <span class="page-label">Feature Breakdown</span>
    <h1 class="page-title">Advisor Dashboard</h1>
    <p class="page-desc">The full customer intelligence and control centre. Each client profile has seven internal tabs.</p>
  </div>
  <div class="sh">Client profile tabs</div>
  <table class="dt">
    <thead><tr><th>Tab</th><th>Advisor capability</th></tr></thead>
    <tbody>
      <tr><td><strong>Overview</strong></td><td>All metrics, active recommendations, pending budget requests. Approve or decline budget changes inline.</td></tr>
      <tr><td><strong>Services &amp; Metrics</strong></td><td>Add, edit, or delete metrics inline per service with current, previous, and target values.</td></tr>
      <tr><td><strong>AI Strategy</strong></td><td>Nine strategy generators using full client context. Output is editable before sending to the client.</td></tr>
      <tr><td><strong>Data Sources</strong></td><td>Set Google Ads, GA4, Meta IDs. Trigger Odoo sync on demand.</td></tr>
      <tr><td><strong>Activity</strong></td><td>Unified timeline of all service requests, recommendation events, and uploaded reports.</td></tr>
      <tr><td><strong>Client Profile</strong></td><td>Edit personal information, business details, goals, competitors, and budgets.</td></tr>
      <tr><td><strong>Reports</strong></td><td>Upload PDF or image reports. Past reports visible to both advisor and client.</td></tr>
    </tbody>
  </table>
</div>

<!-- INTEGRATIONS -->
<div id="sec-integrations" class="section">
  <div class="page-header">
    <span class="page-label">Feature Breakdown</span>
    <h1 class="page-title">Integrations</h1>
    <p class="page-desc">Four external systems connected to the portal, configured per client by the advisor.</p>
  </div>
  <div class="int-card">
    <div class="int-head"><div><div class="int-head-name">Google Ads</div><div class="int-head-sub">MCC manager account · per-client Customer IDs</div></div><span class="int-badge">Per Client</span></div>
    <div class="int-body">
      <div class="int-col"><h5>ID Collected</h5><p>Google Ads Customer ID (<code>123-456-7890</code>). Entered during onboarding or by the advisor in Data Sources.</p></div>
      <div class="int-col"><h5>Purpose</h5><p>Read-only access to campaign data. No ad changes without explicit client authorisation.</p></div>
      <div class="int-col"><h5>Output</h5><p>Pulls impressions, clicks, spend, and conversions into <code>six_metrics</code>.</p></div>
    </div>
  </div>
  <div class="int-card">
    <div class="int-head"><div><div class="int-head-name">Google Analytics 4</div><div class="int-head-sub">GA4 Property ID · read-only via service account</div></div><span class="int-badge">Per Client</span></div>
    <div class="int-body">
      <div class="int-col"><h5>ID Collected</h5><p>Nine-digit Property ID from GA4 Admin → Property Settings.</p></div>
      <div class="int-col"><h5>Purpose</h5><p>Website traffic and behaviour data alongside paid channel data.</p></div>
      <div class="int-col"><h5>Output</h5><p>Populates Sessions and Traffic metric cards in the customer dashboard.</p></div>
    </div>
  </div>
  <div class="int-card">
    <div class="int-head"><div><div class="int-head-name">Meta Ads</div><div class="int-head-sub">Business ID · Ad Account ID · Pixel ID</div></div><span class="int-badge">Per Client</span></div>
    <div class="int-body">
      <div class="int-col"><h5>IDs Collected</h5><p>Meta Business ID · Ad Account ID (<code>act_XXXXXXXXX</code>) · Pixel ID.</p></div>
      <div class="int-col"><h5>Purpose</h5><p>Full Meta Ads visibility — reach, frequency, spend, and leads.</p></div>
      <div class="int-col"><h5>Output</h5><p>Campaign performance in the Social Media service tab.</p></div>
    </div>
  </div>
  <div class="int-card">
    <div class="int-head"><div><div class="int-head-name">Odoo CRM</div><div class="int-head-sub">XML-RPC sync · contacts, leads, and tasks</div></div><span class="int-badge">Automatic</span></div>
    <div class="int-body">
      <div class="int-col"><h5>Data Sent</h5><p>Contact details, services, total budget, goals, readiness score, onboarding progress.</p></div>
      <div class="int-col"><h5>Trigger Points</h5><p>On account creation · Step 1 complete · Service approval · Recommendation approval · Abandoned onboarding.</p></div>
      <div class="int-col"><h5>Why It Matters</h5><p>Sales team has full pipeline visibility without WordPress access. Manual sync available per client.</p></div>
    </div>
  </div>
</div>

<!-- BUG REPORT -->
<div id="sec-bug" class="section">
  <div class="page-header">
    <span class="page-label">QA &amp; Feedback</span>
    <h1 class="page-title">Report a Bug</h1>
    <p class="page-desc">Complete the form below. Reports are saved to the shared database and visible to the full team immediately.</p>
  </div>
  <div class="callout warn"><p><strong>Critical issues</strong> affecting live customers should also be escalated directly via Slack or WhatsApp — do not wait for the tracker.</p></div>
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
    <p class="page-desc">Have an idea that would improve the product? All suggestions are saved to the shared database and reviewed in sprint planning.</p>
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
    <p class="page-desc">All bugs and suggestions from the whole team, stored in the shared database. Everyone sees the same list.</p>
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
      <button class="btn-s" onclick="loadAndRender()">↻ Refresh</button>
      <button class="btn-s" onclick="clearAll()">Clear all</button>
    </div>
  </div>
  <div id="tracker-loading" style="padding:40px;text-align:center;color:var(--ink4);font-size:13px;font-style:italic">Loading shared issues…</div>
  <div id="ticket-list"></div>
  <div id="empty-state" class="empty-st" style="display:none">
    <svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
    <p>No issues on record. Submit a bug report or suggestion to begin tracking.</p>
  </div>
</div>

</div></main>
<div class="toast" id="toast"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg><span id="toast-msg"></span></div>

<script>
// ── WordPress credentials injected by PHP ────────────────────────────────
// These are the ONLY two lines that changed from the original file.
// PHP runs on the server and writes the real nonce + ajax URL directly into
// the page before the browser receives it. This is why sharing now works.
var HUB_AJAX  = <?php echo wp_json_encode( $_hub_ajax_url ); ?>;
var HUB_NONCE = <?php echo wp_json_encode( $_hub_nonce ); ?>;
var HUB_USER  = <?php echo wp_json_encode( $_hub_user ); ?>;

// ── Everything below is identical to the original file ───────────────────

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
  setTimeout(()=>t.classList.remove('show'),3500);
}
function setSev(val,btn,field){
  btn.closest('.sev').querySelectorAll('.sev-b').forEach(b=>b.classList.remove('sl','sm','sh'));
  btn.classList.add({low:'sl',medium:'sm',high:'sh'}[val]);
  document.getElementById(field).value=val;
}

// ── Shared database storage via WordPress AJAX ───────────────────────────
// Issues are stored in the WordPress database (wp_options table, key: six_hub_issues).
// Every team member who opens this page reads the same data from the database.
// The nonce is generated fresh by PHP on each page load so it is always valid.

async function getIssues() {
  try {
    var resp = await fetch(HUB_AJAX, {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: new URLSearchParams({action: 'six_hub_get_issues', nonce: HUB_NONCE})
    });
    var data = await resp.json();
    if (data && data.success && Array.isArray(data.data)) return data.data;
    // Surface the exact server error
    var msg = (data && data.data && data.data.message) ? data.data.message : (data && data.data) ? data.data : 'Could not load issues';
    console.warn('Hub get_issues failed:', msg, data);
    if (msg && msg.toLowerCase().includes('log in')) {
      document.getElementById('tracker-loading').textContent = '⚠ ' + msg;
    }
    return [];
  } catch(e) {
    console.error('Hub AJAX error:', e);
    return [];
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
    var data = await resp.json();
    if (data && data.success) return true;
    // Show the exact server error so it's debuggable
    var msg = (data && data.data && data.data.message)
      ? data.data.message
      : (data && data.data && typeof data.data === 'string')
        ? data.data
        : 'Save failed — please refresh and try again.';
    toast('⚠ ' + msg);
    console.warn('Hub save failed:', data);
    return false;
  } catch(e) {
    toast('⚠ Network error — check your connection and try again.');
    console.error('Hub save error:', e);
    return false;
  }
}

function _updateBadge(issues) {
  var open = (issues||[]).filter(i=>i.status==='open').length;
  var el = document.getElementById('tracker-count');
  el.textContent = open;
  el.className = 'nav-count' + (open > 0 ? ' show' : '');
}

async function submitBug(){
  var title=document.getElementById('bug-title').value.trim();
  var desc=document.getElementById('bug-desc').value.trim();
  var sev=document.getElementById('bug-severity').value;
  if(!title||!desc||!sev){toast('Please complete title, description, and severity.');return;}
  var btn=document.querySelector('#sec-bug .btn-p');
  if(btn){btn.textContent='Saving…';btn.disabled=true;}
  var issues=await getIssues();
  issues.unshift({
    id: Date.now(), type:'bug', status:'open', title,
    section: document.getElementById('bug-section').value, desc,
    steps: document.getElementById('bug-steps').value.trim(),
    severity: sev,
    reporter: document.getElementById('bug-reporter').value.trim() || HUB_USER,
    created: new Date().toISOString().split('T')[0]
  });
  var ok=await saveIssues(issues);
  if(btn){btn.innerHTML='<svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>Submit Report';btn.disabled=false;}
  if(ok){
    ['bug-title','bug-desc','bug-steps','bug-reporter'].forEach(id=>document.getElementById(id).value='');
    document.getElementById('bug-section').value='';
    document.getElementById('bug-severity').value='';
    document.querySelectorAll('#sec-bug .sev-b').forEach(b=>b.classList.remove('sl','sm','sh'));
    _updateBadge(issues);
    toast('✓ Bug report saved — visible to all team members.');
  }
}

async function submitSug(){
  var title=document.getElementById('sug-title').value.trim();
  var desc=document.getElementById('sug-desc').value.trim();
  if(!title||!desc){toast('Please complete title and description.');return;}
  var btn=document.querySelector('#sec-suggest .btn-p');
  if(btn){btn.textContent='Saving…';btn.disabled=true;}
  var issues=await getIssues();
  issues.unshift({
    id: Date.now(), type:'suggestion', status:'open', title,
    section: document.getElementById('sug-section').value, desc,
    severity: document.getElementById('sug-priority').value||'medium',
    reporter: document.getElementById('sug-reporter').value.trim() || HUB_USER,
    created: new Date().toISOString().split('T')[0]
  });
  var ok=await saveIssues(issues);
  if(btn){btn.innerHTML='<svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>Submit Suggestion';btn.disabled=false;}
  if(ok){
    ['sug-title','sug-desc','sug-reporter'].forEach(id=>document.getElementById(id).value='');
    document.getElementById('sug-section').value='';
    document.getElementById('sug-priority').value='';
    document.querySelectorAll('#sec-suggest .sev-b').forEach(b=>b.classList.remove('sl','sm','sh'));
    _updateBadge(issues);
    toast('✓ Suggestion saved — visible to all team members.');
  }
}

var activeF='all';
function filterTkts(f,btn){
  activeF=f;
  document.querySelectorAll('.fb2').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  loadAndRender();
}

async function loadAndRender(){
  document.getElementById('tracker-loading').style.display='block';
  document.getElementById('ticket-list').innerHTML='';
  document.getElementById('empty-state').style.display='none';
  var issues=await getIssues();
  _updateBadge(issues);
  renderTkts(issues);
}

function toggleTkt(id){
  var wrap = document.getElementById('tkt-'+id);
  if(wrap) wrap.classList.toggle('open');
}

function renderTkts(issues){
  document.getElementById('tracker-loading').style.display='none';
  var shown=(issues||[]).filter(i=>{
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
  var statusLabel={'open':'Open','in-progress':'In Progress','fixed':'Resolved'};

  // Chevron SVG
  var chev='<svg class="tkt-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><polyline points="6 9 12 15 18 9"/></svg>';

  list.innerHTML='<div class="tklist">'+shown.map(i=>{
    var hasSeps = i.section || i.reporter;
    return `
    <div class="tkt-wrap" id="tkt-${i.id}">
      <!-- ── Collapsed header row — click to expand ── -->
      <div class="tkt" onclick="toggleTkt(${i.id})">
        <div class="tkt-stripe" style="background:${sc[i.severity]||'var(--border2)'}"></div>
        <div class="tkt-body">
          <div class="tkt-title">
            ${esc(i.title)}
            ${chev}
          </div>
          <div class="tkt-meta">
            <span>${i.created}</span>
            ${i.reporter?'<span>by '+esc(i.reporter)+'</span>':''}
            ${i.section?'<span>'+esc(i.section)+'</span>':''}
          </div>
          <div class="tkt-tags">
            ${typeTag[i.type]||''}
            ${sevTag[i.severity]||''}
            <span class="tag tn">${statusLabel[i.status]||i.status}</span>
          </div>
        </div>
        <div class="tkt-actions" onclick="event.stopPropagation()">
          <select class="st-sel" onchange="changeStatus(${i.id},this.value)">
            <option ${i.status==='open'?'selected':''} value="open">Open</option>
            <option ${i.status==='in-progress'?'selected':''} value="in-progress">In Progress</option>
            <option ${i.status==='fixed'?'selected':''} value="fixed">Resolved</option>
          </select>
        </div>
      </div>

      <!-- ── Expanded detail panel ── -->
      <div class="tkt-detail">
        <div class="tkt-detail-grid">
          <div class="tkt-detail-field">
            <div class="tkt-detail-label">Description</div>
            <div class="tkt-detail-val pre">${esc(i.desc||'—')}</div>
          </div>
          ${i.steps?`<div class="tkt-detail-field">
            <div class="tkt-detail-label">Steps to Reproduce</div>
            <div class="tkt-detail-val pre">${esc(i.steps)}</div>
          </div>`:''}
          <div class="tkt-detail-field">
            <div class="tkt-detail-label">Section</div>
            <div class="tkt-detail-val">${esc(i.section||'—')}</div>
          </div>
          <div class="tkt-detail-field">
            <div class="tkt-detail-label">Reported by</div>
            <div class="tkt-detail-val">${esc(i.reporter||'—')}</div>
          </div>
          <div class="tkt-detail-field">
            <div class="tkt-detail-label">Date Submitted</div>
            <div class="tkt-detail-val">${esc(i.created||'—')}</div>
          </div>
          <div class="tkt-detail-field">
            <div class="tkt-detail-label">Severity / Priority</div>
            <div class="tkt-detail-val">${esc(i.severity||'—')}</div>
          </div>
        </div>
        <div class="tkt-detail-actions">
          <select class="st-sel" onchange="changeStatus(${i.id},this.value)">
            <option ${i.status==='open'?'selected':''} value="open">Open</option>
            <option ${i.status==='in-progress'?'selected':''} value="in-progress">In Progress</option>
            <option ${i.status==='fixed'?'selected':''} value="fixed">Resolved</option>
          </select>
          <button class="tkt-del" onclick="deleteIssue(${i.id})">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6M9 6V4h6v2"/></svg>
            Delete
          </button>
        </div>
      </div>
    </div>`;
  }).join('')+'</div>';
}

async function changeStatus(id,status){
  var issues=await getIssues();
  var idx=issues.findIndex(i=>i.id===id);
  if(idx>-1){issues[idx].status=status;await saveIssues(issues);_updateBadge(issues);}
}

async function deleteIssue(id){
  if(!confirm('Delete this issue from the shared database?'))return;
  var issues=await getIssues();
  var filtered=issues.filter(i=>i.id!==id);
  var ok=await saveIssues(filtered);
  if(ok){_updateBadge(filtered);renderTkts(filtered);toast('Issue deleted.');}
}

async function clearAll(){
  if(!confirm('Remove ALL issues for the entire team? This cannot be undone.'))return;
  var ok=await saveIssues([]);
  if(ok){_updateBadge([]);renderTkts([]);toast('All issues cleared.');}
}

function esc(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}

// ── Init ─────────────────────────────────────────────────────────────────
async function init(){
  // Pre-fill reporter name from logged-in WordPress user
  ['bug-reporter','sug-reporter'].forEach(function(id){
    var el=document.getElementById(id);
    if(el&&HUB_USER)el.placeholder=HUB_USER;
  });
  await loadAndRender();
}
init();

// ── Mobile sidebar ────────────────────────────────────────────────────────
function toggleSidebar(){
  var sb=document.querySelector('.sidebar');
  var hb=document.getElementById('hamburger');
  var ov=document.getElementById('sidebar-overlay');
  if(sb.classList.contains('open')){closeSidebar();}
  else{sb.classList.add('open');hb.classList.add('open');ov.style.display='block';document.body.style.overflow='hidden';}
}
function closeSidebar(){
  var sb=document.querySelector('.sidebar');
  var hb=document.getElementById('hamburger');
  var ov=document.getElementById('sidebar-overlay');
  sb.classList.remove('open');hb.classList.remove('open');ov.style.display='none';document.body.style.overflow='';
}
document.querySelectorAll('.nav-link').forEach(function(link){
  link.addEventListener('click',function(){if(window.innerWidth<=768)closeSidebar();});
});
</script>
</body>
</html>
