<?php
/**
 * Onboarding Template v3 — Service-driven 5-step flow.
 * Steps: 1=Account, 2=Services, 3=Questionnaire, 4=AI Strategy, 5=Agreement
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( is_user_logged_in() ) {
    $uid       = get_current_user_id();
    $completed = get_user_meta( $uid, 'six_checkout_completed', true );
    if ( $completed ) { wp_redirect( home_url( '/portal/' ) ); exit; }
}
?>
<style>
:root{
  --pk:#E8547A;--pk-soft:#FDE8EF;--cy:#3C8FB5;--cy-soft:#E8F4FB;
  --blue:#3C6478;--d1:#F5F6F8;--d2:#FFFFFF;--d3:#F0F2F5;--d4:#E8EBF0;
  --bdr:#E2E6ED;--t1:#0F1923;--t2:#4A5568;--t3:#8A96A3;
  --ok:#1B9E52;--warn:#C17B1A;--err:#D93B3B;
  --r:14px;--tr:0.25s cubic-bezier(0.4,0,0.2,1);
  --fn:'Inter',sans-serif;--sh:0 1px 4px rgba(15,25,35,.08),0 4px 16px rgba(15,25,35,.04);
}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'DM Sans',sans-serif;background:var(--d1);color:var(--t1);min-height:100vh;overflow-x:hidden;}
body::before{content:'';position:fixed;inset:0;z-index:0;pointer-events:none;
  background:radial-gradient(ellipse 60% 50% at 0% 0%,rgba(232,84,122,.05) 0%,transparent 60%),
             radial-gradient(ellipse 50% 60% at 100% 100%,rgba(60,143,181,.05) 0%,transparent 60%);}

/* Layout */
.ob-wrap{position:relative;z-index:1;min-height:100vh;display:grid;grid-template-columns:360px 1fr;}
@media(max-width:900px){.ob-wrap{grid-template-columns:1fr;}.ob-side{display:none;}}

/* Sidebar */
.ob-side{background:linear-gradient(160deg,#F0F2F5 0%,#fff 100%);border-right:1px solid var(--bdr);
  padding:48px 40px;display:flex;flex-direction:column;justify-content:space-between;
  position:sticky;top:0;height:100vh;overflow:hidden;}
.ob-logo{font-family:'Syne',sans-serif;font-size:22px;font-weight:800;
  background:linear-gradient(135deg,var(--pk),var(--cy));-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
.ob-side-content{flex:1;display:flex;flex-direction:column;justify-content:center;}
.ob-side-hl{font-family:'Syne',sans-serif;font-size:26px;font-weight:800;line-height:1.2;margin-bottom:14px;}
.ob-side-hl span{color:var(--pk);}
.ob-side-desc{font-size:13px;color:var(--t2);line-height:1.7;margin-bottom:32px;max-width:260px;}
.ob-steps-nav{display:flex;flex-direction:column;}
.ob-step-item{display:flex;align-items:center;gap:14px;padding:12px 0;border-bottom:1px solid var(--bdr);transition:var(--tr);}
.ob-step-item:last-child{border-bottom:none;}
.ob-step-num{width:30px;height:30px;border-radius:50%;border:1.5px solid var(--bdr);display:flex;align-items:center;
  justify-content:center;font-size:12px;font-weight:700;color:var(--t3);flex-shrink:0;font-family:var(--fn);}
.ob-step-item.active .ob-step-num{background:var(--pk);border-color:var(--pk);color:#fff;box-shadow:0 0 14px rgba(232,84,122,.35);}
.ob-step-item.done .ob-step-num{background:var(--ok);border-color:var(--ok);color:#fff;}
.ob-step-lbl{font-size:13px;color:var(--t3);font-weight:500;}
.ob-step-item.active .ob-step-lbl{color:var(--t1);font-weight:600;}
.ob-step-item.done .ob-step-lbl{color:var(--ok);}
.ob-side-foot{font-size:11px;color:var(--t3);line-height:1.6;border-top:1px solid var(--bdr);padding-top:14px;}
.ob-side-foot a{color:var(--cy);text-decoration:none;}

/* Main */
.ob-main{padding:48px 40px;max-width:640px;margin:0 auto;width:100%;}
/* Login panel: vertically centered inside the right column */
#ob-login.ob-panel.active{
  min-height:calc(100vh - 96px);
  display:flex !important;flex-direction:column;justify-content:center;
}
#ob-login.ob-panel.active .ob-btnrow{position:static !important;padding:0;margin-top:20px;box-shadow:none;border-top:none;background:transparent;}
@media(max-width:600px){.ob-main{padding:28px 20px;}}

/* Progress */
.ob-prog{display:flex;align-items:center;gap:12px;margin-bottom:40px;}
.ob-prog-track{flex:1;height:3px;background:var(--d4);border-radius:2px;overflow:hidden;}
.ob-prog-fill{height:100%;background:linear-gradient(90deg,var(--pk),var(--cy));border-radius:2px;transition:width .6s cubic-bezier(.4,0,.2,1);}
.ob-prog-lbl{font-size:11px;font-weight:600;color:var(--t3);white-space:nowrap;font-family:'Syne',sans-serif;}

/* Panels */
.ob-panel{display:none;animation:panelIn .35s cubic-bezier(.4,0,.2,1) both;}
.ob-panel.active{display:block;}
@keyframes panelIn{from{opacity:0;transform:translateY(16px);}to{opacity:1;transform:none;}}

/* Typography */
.ob-eye{font-family:'Syne',sans-serif;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--pk);margin-bottom:8px;}
.ob-ttl{font-family:'Syne',sans-serif;font-size:26px;font-weight:700;line-height:1.2;margin-bottom:8px;letter-spacing:-.5px;}
.ob-dsc{font-size:14px;color:var(--t2);line-height:1.7;margin-bottom:28px;}

/* Form */
.ob-fg{margin-bottom:16px;}
.ob-lbl{display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.8px;color:var(--t3);margin-bottom:6px;}
.ob-inp{width:100%;background:var(--d3);border:1px solid var(--bdr);border-radius:10px;padding:12px 16px;
  color:var(--t1);font-family:'DM Sans',sans-serif;font-size:14px;outline:none;transition:var(--tr);-webkit-appearance:none;}
.ob-inp:focus{border-color:rgba(232,84,122,.5);background:#fff;box-shadow:0 0 0 3px rgba(232,84,122,.08);}
.ob-inp::placeholder{color:var(--t3);}
.ob-inp[readonly]{opacity:.55;cursor:not-allowed;}
.ob-inp option{background:var(--d3);}
.ob-g2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
@media(max-width:520px){.ob-g2{grid-template-columns:1fr;}}
.ob-hr{border:none;border-top:1px solid var(--bdr);margin:20px 0;}

/* Password wrap */
.ob-pw-wrap{position:relative;}
.ob-pw-tog{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;
  color:var(--t3);cursor:pointer;padding:0;display:flex;align-items:center;justify-content:center;min-width:44px;min-height:44px;}

/* Divider */
.ob-div{display:flex;align-items:center;gap:12px;margin:16px 0;}
.ob-div-line{flex:1;height:1px;background:var(--bdr);}
.ob-div-txt{font-size:11px;color:var(--t3);white-space:nowrap;font-weight:500;}

/* Found hint */
.ob-hint{font-size:12px;color:var(--t3);padding:9px 13px;background:var(--d3);border-radius:8px;border-left:3px solid var(--cy);margin-top:10px;}

/* Chips */
.ob-chips{display:flex;flex-wrap:wrap;gap:8px;margin-top:6px;}
.ob-chip{padding:8px 16px;border-radius:100px;border:1px solid var(--bdr);background:var(--d3);
  font-size:12px;color:var(--t2);cursor:pointer;transition:var(--tr);user-select:none;}
.ob-chip:hover{border-color:rgba(232,84,122,.4);}
.ob-chip.sel{background:rgba(232,84,122,.1);border-color:var(--pk);color:var(--pk);font-weight:600;}

/* Toggle yes/no */
.ob-tog-row{display:flex;gap:8px;margin-top:6px;}
.ob-tog-btn{flex:1;padding:9px;border:1px solid var(--bdr);border-radius:10px;background:var(--d3);
  font-size:13px;font-weight:600;color:var(--t2);cursor:pointer;text-align:center;transition:var(--tr);}
.ob-tog-btn.y{border-color:var(--ok);background:rgba(27,158,82,.08);color:var(--ok);}
.ob-tog-btn.n{border-color:var(--err);background:rgba(217,59,59,.08);color:var(--err);}

/* Buttons */
.ob-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:13px 28px;
  border-radius:10px;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:600;cursor:pointer;border:none;transition:var(--tr);text-decoration:none;}
.ob-primary{background:linear-gradient(135deg,var(--pk) 0%,#e6407a 100%);color:#fff;box-shadow:0 4px 20px rgba(232,84,122,.28);}
.ob-primary:hover{transform:translateY(-1px);box-shadow:0 6px 24px rgba(232,84,122,.42);}
.ob-primary:disabled{opacity:.5;transform:none;cursor:not-allowed;}
.ob-ghost{background:transparent;color:var(--t2);border:1px solid var(--bdr);}
.ob-ghost:hover{border-color:rgba(15,25,35,.25);color:var(--t1);}
.ob-btnrow{display:flex;gap:12px;align-items:center;margin-top:28px;}

/* Alert */
.ob-alert{padding:11px 15px;border-radius:8px;font-size:13px;margin-bottom:14px;display:none;}
.ob-alert.e{background:rgba(217,59,59,.08);border:1px solid rgba(217,59,59,.25);color:var(--err);}
.ob-alert.o{background:rgba(27,158,82,.08);border:1px solid rgba(27,158,82,.25);color:var(--ok);}
.ob-alert.on{display:block;}

/* Trust */
.ob-trust{display:flex;align-items:center;gap:10px;padding:11px 14px;background:var(--d3);border-radius:10px;border:1px solid var(--bdr);margin-top:8px;}

/* AI spinner */
.ob-ai-spinner{width:36px;height:36px;border:3px solid var(--bdr);border-top-color:var(--pk);border-radius:50%;animation:spin .8s linear infinite;}
/* Service progress dots */
.ob-svc-dot-step{width:8px;height:8px;border-radius:50%;background:var(--bdr);transition:var(--tr);}
.ob-svc-dot-step.done{background:var(--ok);}
.ob-svc-dot-step.active{background:var(--pk);transform:scale(1.3);}
/* Roadmap */
.ob-svc-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px}
.ob-svc{background:var(--d2);border:1.5px solid var(--bdr);border-radius:14px;padding:18px;
  cursor:pointer;transition:var(--tr);position:relative;overflow:hidden;display:flex;flex-direction:column;box-shadow:var(--sh);}
.ob-svc:hover{border-color:var(--c,var(--pk));transform:translateY(-1px);}
.ob-svc.sel{border-color:var(--c,var(--pk));}
.ob-svc.sel::before{content:'';position:absolute;inset:0;background:var(--c,var(--pk));opacity:.04;pointer-events:none;}
.ob-svc-bar{position:absolute;top:0;left:0;bottom:0;width:3px;background:var(--c,var(--pk));opacity:0;transition:opacity .2s;}
.ob-svc.sel .ob-svc-bar{opacity:1;}
.ob-svc-chk{position:absolute;top:14px;right:14px;width:22px;height:22px;border-radius:50%;
  border:1.5px solid var(--bdr);background:var(--d3);display:flex;align-items:center;justify-content:center;}
.ob-svc-chk svg{width:11px;height:11px;stroke:transparent;fill:none;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round;}
.ob-svc.sel .ob-svc-chk{background:var(--c,var(--pk));border-color:var(--c,var(--pk));}
.ob-svc.sel .ob-svc-chk svg{stroke:#fff;}
.ob-svc-ico{width:40px;height:40px;border-radius:10px;background:rgba(255,255,255,.9);outline:1.5px solid var(--c,var(--pk));
  color:var(--c,var(--pk));display:flex;align-items:center;justify-content:center;margin:0 0 12px 0;flex-shrink:0;}
.ob-svc-ico svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
.ob-svc-name{font-size:14px;font-weight:700;color:var(--t1);margin-bottom:4px;font-family:'Syne',sans-serif;padding-right:28px;}
.ob-svc-desc{font-size:12px;color:var(--t2);line-height:1.6;}
/* Not sure */
.ob-notsure{background:var(--d3);border:1.5px dashed var(--bdr);border-radius:14px;padding:16px;
  cursor:pointer;display:flex;align-items:center;gap:12px;transition:var(--tr);margin-top:4px;}
.ob-notsure:hover{border-color:rgba(232,84,122,.4);}
.ob-notsure.sel{border-color:var(--pk);background:rgba(232,84,122,.04);}
.ob-notsure-t{font-size:13px;font-weight:600;color:var(--t2);}
.ob-notsure-s{font-size:11px;color:var(--t3);margin-top:2px;}

/* \u2500\u2500\u2500 Step 3 questionnaire \u2500\u2500\u2500 */
.ob-q-sec{margin-bottom:28px;}
.ob-q-head{display:flex;align-items:center;gap:10px;margin-bottom:16px;padding-bottom:10px;border-bottom:2px solid var(--bdr);}
.ob-q-num{width:24px;height:24px;border-radius:50%;background:linear-gradient(135deg,var(--pk),var(--cy));
  display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;flex-shrink:0;}
.ob-q-title{font-family:'Syne',sans-serif;font-size:14px;font-weight:700;}
.ob-q-badge{font-size:10px;font-weight:600;color:var(--t3);background:var(--d3);border:1px solid var(--bdr);border-radius:20px;padding:2px 9px;}
/* Tabs */
.ob-tabs{display:flex;gap:0;border-bottom:2px solid var(--bdr);margin-bottom:24px;overflow-x:auto;}
.ob-tab{padding:10px 18px;font-size:13px;font-weight:600;color:var(--t3);cursor:pointer;
  border-bottom:2px solid transparent;margin-bottom:-2px;transition:var(--tr);white-space:nowrap;display:flex;align-items:center;gap:7px;}
.ob-tab:hover{color:var(--t1);}
.ob-tab.active{color:var(--pk);border-bottom-color:var(--pk);}
.ob-tab-dot{width:7px;height:7px;border-radius:50%;}
.ob-pane{display:none;}
.ob-pane.active{display:block;animation:panelIn .25s ease both;}
/* Tag input */
.ob-tag-wrap{border:1px solid var(--bdr);border-radius:10px;background:var(--d3);padding:8px 10px;
  display:flex;flex-wrap:wrap;gap:6px;cursor:text;transition:var(--tr);}
.ob-tag-wrap:focus-within{border-color:rgba(232,84,122,.5);background:#fff;box-shadow:0 0 0 3px rgba(232,84,122,.08);}
.ob-tag{display:flex;align-items:center;gap:5px;background:rgba(232,84,122,.1);border:1px solid rgba(232,84,122,.25);
  border-radius:20px;padding:3px 10px;font-size:12px;color:var(--pk);font-weight:500;}
.ob-tag-del{background:none;border:none;color:var(--pk);cursor:pointer;padding:0;font-size:13px;line-height:1;}
.ob-tag-inp{border:none;background:none;outline:none;font-size:13px;color:var(--t1);font-family:'DM Sans',sans-serif;min-width:80px;padding:3px 4px;}
/* Slider */
.ob-slider{width:100%;-webkit-appearance:none;appearance:none;height:4px;border-radius:2px;
  background:linear-gradient(90deg,var(--pk) var(--p,50%),var(--d4) var(--p,50%));outline:none;cursor:pointer;}
.ob-slider::-webkit-slider-thumb{-webkit-appearance:none;width:18px;height:18px;border-radius:50%;
  background:var(--pk);box-shadow:0 0 0 3px rgba(232,84,122,.2);cursor:pointer;}
.ob-slider::-moz-range-thumb{width:18px;height:18px;border-radius:50%;background:var(--pk);border:none;}
.ob-slval{font-family:var(--fn);font-size:15px;font-weight:700;color:var(--t1);margin-top:6px;}
.ob-sllbls{display:flex;justify-content:space-between;font-size:10px;color:var(--t3);margin-top:4px;}
/* Hours grid */
.ob-hrs{display:grid;grid-template-columns:repeat(7,1fr);gap:4px;}
.ob-hr-day{text-align:center;}
.ob-hr-lbl{font-size:10px;font-weight:600;color:var(--t3);margin-bottom:4px;text-transform:uppercase;}
.ob-hr-btn{width:100%;aspect-ratio:1;border:1px solid var(--bdr);border-radius:6px;background:var(--d3);
  font-size:10px;color:var(--t3);cursor:pointer;transition:var(--tr);}
.ob-hr-btn.on{background:rgba(232,84,122,.1);border-color:var(--pk);color:var(--pk);}
/* Loc type */
.ob-loc-type{display:flex;gap:8px;margin-bottom:8px;}
.ob-loc-btn{padding:5px 12px;border-radius:20px;border:1px solid var(--bdr);background:var(--d3);
  font-size:11px;font-weight:600;color:var(--t3);cursor:pointer;transition:var(--tr);}
.ob-loc-btn.on{background:rgba(60,143,181,.1);border-color:var(--cy);color:var(--cy);}

/* \u2500\u2500\u2500 Step 4 score \u2500\u2500\u2500 */
.ob-score-card{background:linear-gradient(135deg,var(--d3),var(--d4));border:1px solid var(--bdr);
  border-radius:var(--r);padding:28px;text-align:center;margin-bottom:20px;overflow:hidden;position:relative;}
.ob-score-card::before{content:'';position:absolute;top:-40px;right:-40px;width:200px;height:200px;
  border-radius:50%;background:radial-gradient(circle,rgba(232,84,122,.08) 0%,transparent 70%);}
.ob-ring{width:120px;height:120px;margin:0 auto 16px;position:relative;}
.ob-ring svg{transform:rotate(-90deg);}
.ob-ring-bg{fill:none;stroke:var(--d4);stroke-width:8;}
.ob-ring-fill{fill:none;stroke:url(#sg);stroke-width:8;stroke-linecap:round;stroke-dasharray:283;stroke-dashoffset:283;transition:stroke-dashoffset 1.2s cubic-bezier(.4,0,.2,1);}
.ob-score-n{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-family:'Syne',sans-serif;font-size:28px;font-weight:800;}
.ob-score-ttl{font-family:'Syne',sans-serif;font-size:16px;font-weight:700;margin-bottom:4px;}
.ob-score-sub{font-size:12px;color:var(--t2);margin-bottom:18px;}
.ob-score-cols{display:grid;grid-template-columns:1fr 1fr;gap:14px;text-align:left;}
.ob-score-ch{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;}
.ob-score-ch.g{color:var(--ok);}
.ob-score-ch.o{color:var(--warn);}
.ob-score-it{font-size:11px;color:var(--t2);line-height:1.5;margin-bottom:5px;padding-left:12px;position:relative;}
.ob-score-it::before{content:'\u2022';position:absolute;left:0;}
.ob-score-it.g::before{color:var(--ok);}
.ob-score-it.o::before{color:var(--warn);}
/* Perf grid */
.ob-perf{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:20px;}
.ob-perf-c{background:var(--d2);border:1px solid var(--bdr);border-radius:12px;padding:14px;text-align:center;box-shadow:var(--sh);}
.ob-perf-v{font-family:var(--fn);font-size:18px;font-weight:700;color:var(--cy);}
.ob-perf-l{font-size:10px;color:var(--t3);margin-top:3px;font-weight:600;letter-spacing:.5px;text-transform:uppercase;}
/* Strategy */
.ob-strat-q{font-size:14px;color:var(--t1);line-height:1.75;font-style:italic;padding:16px 18px;
  border-left:3px solid var(--pk);background:var(--pk-soft);border-radius:0 10px 10px 0;margin-bottom:0;}
.ob-ai-sug{padding:14px 16px;background:var(--cy-soft);border:1px solid rgba(60,143,181,.15);
  border-left:3px solid var(--cy);border-radius:var(--r);margin-bottom:10px;font-size:13px;color:var(--t2);line-height:1.6;}
/* Content card */
.ob-card{background:var(--d2);border:1px solid var(--bdr);border-radius:14px;overflow:hidden;margin-bottom:14px;box-shadow:var(--sh);}
.ob-card-hd{padding:12px 16px;border-bottom:1px solid var(--bdr);display:flex;align-items:center;gap:8px;}
.ob-card-dot{width:6px;height:6px;border-radius:50%;flex-shrink:0;}
.ob-card-hl{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--t3);}
.ob-card-body{padding:16px 18px;}
.ob-card-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--bdr);gap:12px;}
.ob-card-row:last-child{border-bottom:none;}
.ob-card-rl{font-size:13px;color:var(--t2);}
.ob-card-rv{font-size:13px;font-weight:700;color:var(--cy);font-family:'Inter',sans-serif;}
.ob-card-tot{display:flex;justify-content:space-between;align-items:center;padding:14px 18px;background:var(--d3);border-top:2px solid var(--bdr);}
.ob-card-tl{font-size:13px;font-weight:700;}
.ob-card-tv{font-size:17px;font-weight:800;color:var(--pk);font-family:'Inter',sans-serif;}

/* \u2500\u2500\u2500 Step 5 \u2500\u2500\u2500 */
.ob-adv-reveal{background:linear-gradient(135deg,var(--d3),var(--d4));border:1px solid rgba(60,143,181,.2);
  border-radius:var(--r);padding:20px;display:flex;align-items:center;gap:16px;margin-bottom:20px;position:relative;overflow:hidden;}
.ob-adv-av-wrap{position:relative;flex-shrink:0;}
.ob-adv-av{width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,var(--blue),var(--cy));
  display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:17px;font-weight:700;color:#fff;}
.ob-adv-online{position:absolute;bottom:2px;right:2px;width:12px;height:12px;border-radius:50%;background:var(--ok);border:2px solid var(--d3);}
.ob-adv-intro{font-size:10px;color:var(--cy);font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:2px;}
.ob-adv-name{font-family:'Syne',sans-serif;font-size:16px;font-weight:700;margin-bottom:2px;}
.ob-adv-role{font-size:12px;color:var(--t2);margin-bottom:6px;}
.ob-adv-tags{display:flex;flex-wrap:wrap;gap:5px;}
.ob-adv-tag{padding:3px 9px;border-radius:100px;background:rgba(60,143,181,.1);border:1px solid rgba(60,143,181,.2);font-size:10px;color:var(--cy);font-weight:600;}
/* Trust note */
.ob-trust-n{display:flex;align-items:flex-start;gap:12px;padding:14px 16px;background:var(--d3);border:1px solid var(--bdr);border-radius:10px;margin-bottom:20px;}
.ob-trust-ico{width:30px;height:30px;border-radius:8px;background:var(--d2);border:1px solid var(--bdr);display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.ob-trust-ico svg{width:14px;height:14px;stroke:var(--t3);fill:none;stroke-width:2;stroke-linecap:round;}
.ob-trust-txt{font-size:12px;color:var(--t2);line-height:1.6;}
.ob-trust-txt strong{color:var(--t1);font-weight:600;}
/* Contract */
.ob-contract{background:var(--d3);border:1px solid var(--bdr);border-radius:var(--r);overflow:hidden;margin-bottom:18px;}
.ob-contract-hdr{padding:13px 18px;border-bottom:1px solid var(--bdr);background:var(--d4);display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;}
.ob-contract-body{max-height:160px;overflow-y:auto;padding:16px 18px;font-size:12px;color:var(--t3);line-height:1.8;}
.ob-sign-row{padding:14px 18px;border-top:1px solid var(--bdr);display:flex;align-items:center;gap:10px;}
.ob-sign-inp{flex:1;background:var(--d4);border:1px solid var(--bdr);border-radius:8px;padding:10px 13px;
  color:var(--pk);font-family:'Syne',sans-serif;font-size:16px;font-style:italic;outline:none;}
.ob-sign-inp:focus{border-color:rgba(232,84,122,.4);}
.ob-sign-inp::placeholder{font-style:normal;color:var(--t3);font-family:'DM Sans',sans-serif;font-size:12px;}
.ob-sign-lbl{font-size:11px;color:var(--t3);white-space:nowrap;}
/* Stripe */
.ob-stripe-wrap{background:var(--d3);border:1px solid var(--bdr);border-radius:var(--r);padding:18px;margin-bottom:20px;}
.ob-stripe-lbl{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.8px;color:var(--t3);margin-bottom:10px;}
#ob-card-el{background:var(--d4);border:1px solid var(--bdr);border-radius:10px;padding:14px 16px;}
/* ── Step 4: 60-Day Plan — Apple-calibre design ─────────────────── */
/* Hero card */
.ob-plan-hero{background:linear-gradient(145deg,#E8547A 0%,#b5294e 100%);border-radius:20px;padding:28px 24px 24px;margin-bottom:16px;color:#fff;position:relative;overflow:hidden}
.ob-plan-hero::before{content:'';position:absolute;top:-60px;right:-60px;width:240px;height:240px;border-radius:50%;background:rgba(255,255,255,.07);pointer-events:none}
.ob-plan-hero::after{content:'';position:absolute;bottom:-50px;left:-30px;width:160px;height:160px;border-radius:50%;background:rgba(0,0,0,.06);pointer-events:none}
.ob-plan-hero-label{font-size:9px;font-weight:800;letter-spacing:2.5px;text-transform:uppercase;opacity:.65;margin-bottom:10px;display:flex;align-items:center;gap:8px}
.ob-plan-hero-headline{font-size:20px;font-weight:700;line-height:1.3;letter-spacing:-.3px;margin-bottom:6px}
.ob-plan-hero-sub{font-size:12px;opacity:.72;line-height:1.5;margin-bottom:18px;max-width:92%}
.ob-plan-hero-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;position:relative;z-index:1}
.ob-kpi-stat{background:rgba(255,255,255,.13);backdrop-filter:blur(4px);border-radius:12px;padding:10px 8px;text-align:center;border:1px solid rgba(255,255,255,.1)}
.ob-kpi-val{font-size:15px;font-weight:700;display:block;letter-spacing:-.3px;line-height:1.15}
.ob-kpi-lbl{font-size:9px;opacity:.65;margin-top:3px;letter-spacing:.3px;line-height:1.3}
/* Data badge */
.ob-data-badge{font-size:8.5px;font-weight:800;letter-spacing:.8px;text-transform:uppercase;padding:2px 7px;border-radius:20px;background:rgba(255,255,255,.18);color:#fff;border:1px solid rgba(255,255,255,.25)}
.ob-data-est{background:rgba(255,200,60,.2);color:#ffe082;border-color:rgba(255,200,60,.3)}
/* Insight */
.ob-insight-card{display:flex;gap:12px;align-items:flex-start;background:var(--d2);border:1px solid var(--bdr);border-radius:14px;padding:14px 16px;margin-bottom:14px}
.ob-insight-ico{width:32px;height:32px;border-radius:9px;background:rgba(66,133,244,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;color:#4285F4}
.ob-insight-txt{font-size:12.5px;color:var(--t2);line-height:1.55}
.ob-insight-txt strong{font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:var(--t3);display:block;margin-bottom:3px;font-weight:700}
/* Roadmap card */
.ob-card-wrap{background:var(--d2);border:1px solid var(--bdr);border-radius:16px;padding:20px;box-shadow:var(--sh)}
.ob-card-head{font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:2px;color:var(--t3);margin-bottom:20px}
.ob-roadmap-wrap{display:flex;flex-direction:column;gap:0}
.ob-roadmap-item{display:flex;gap:16px;padding-bottom:6px}
.ob-roadmap-left{display:flex;flex-direction:column;align-items:center;width:18px;flex-shrink:0;padding-top:2px}
.ob-roadmap-dot{width:12px;height:12px;border-radius:50%;flex-shrink:0}
.ob-roadmap-line{width:2px;flex:1;background:var(--bdr);min-height:24px;margin:5px 0}
.ob-roadmap-body{flex:1;padding-bottom:24px}
.ob-roadmap-week{font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;margin-bottom:3px}
.ob-roadmap-phase{font-size:14px;font-weight:700;color:var(--t1);margin-bottom:8px;letter-spacing:-.1px}
.ob-roadmap-pts{margin:0 0 0 2px;padding:0;list-style:none;display:flex;flex-direction:column;gap:5px}
.ob-roadmap-pts li{font-size:12.5px;color:var(--t2);line-height:1.45;padding-left:12px;position:relative}
.ob-roadmap-pts li::before{content:'';position:absolute;left:0;top:7px;width:4px;height:4px;border-radius:50%;background:var(--t3)}
.ob-roadmap-outcome{display:inline-flex;align-items:center;gap:5px;margin-top:10px;font-size:11.5px;font-weight:600;color:var(--t2);background:var(--d3);border-radius:20px;padding:3px 10px}
/* Disclaimer */
.ob-plan-disclaimer{font-size:11px;color:var(--t3);line-height:1.6;padding:12px 14px;background:var(--d3);border:1px solid var(--bdr);border-radius:10px;margin-bottom:6px}
/* Stripe card */
#ob-card-el{background:var(--d4);border:1px solid var(--bdr);border-radius:10px;padding:14px 16px;}
/* Dual path */
.ob-dual-path{display:flex;flex-direction:column;gap:10px;margin-bottom:16px}
.ob-path-card{display:flex;align-items:flex-start;gap:14px;padding:16px;border:1.5px solid var(--bdr);border-radius:14px;cursor:pointer;transition:var(--tr);background:var(--d2)}
.ob-path-card:hover{border-color:var(--pk);background:var(--pk-soft)}
.ob-path-card.selected{border-color:var(--pk);background:var(--pk-soft)}
.ob-path-icon{width:36px;height:36px;border-radius:10px;background:var(--d3);display:flex;align-items:center;justify-content:center;flex-shrink:0;color:var(--t2)}
.ob-path-primary .ob-path-icon{background:rgba(232,84,122,.1);color:var(--pk)}
.ob-path-title{font-size:14px;font-weight:600;color:var(--t1);margin-bottom:2px}
.ob-path-desc{font-size:12px;color:var(--t3);line-height:1.45}
/* Complete */
.ob-done{text-align:center;padding:40px 20px;}
.ob-done-ico{width:68px;height:68px;border-radius:50%;background:rgba(27,158,82,.08);border:1px solid rgba(27,158,82,.2);
  margin:0 auto 22px;display:flex;align-items:center;justify-content:center;color:var(--ok);}
.ob-done-ttl{font-family:'Syne',sans-serif;font-size:26px;font-weight:700;margin-bottom:12px;}
.ob-done-dsc{font-size:14px;color:var(--t2);line-height:1.7;max-width:380px;margin:0 auto 28px;}
/* Spinner */
.ob-spin{display:inline-block;width:15px;height:15px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;}
@keyframes spin{to{transform:rotate(360deg);}}

/* ─── Mobile ─── */
@media(max-width:768px){
  .ob-wrap{grid-template-columns:1fr !important;min-height:100dvh;}
  .ob-side{display:none !important;}
  .ob-main{padding:24px 20px 120px !important;max-width:100% !important;margin:0 !important;}
  .ob-prog{position:sticky;top:0;background:var(--d1);z-index:50;padding:10px 0 12px;margin-bottom:24px;border-bottom:1px solid var(--bdr);}
  .ob-ttl{font-size:22px !important;}
  .ob-g2{grid-template-columns:1fr !important;}
  .ob-svc-grid{grid-template-columns:1fr !important;gap:10px !important;}
  .ob-perf{grid-template-columns:1fr 1fr !important;}
  .ob-score-cols{grid-template-columns:1fr !important;}
  .ob-hrs{grid-template-columns:repeat(4,1fr);}
  .ob-btnrow{position:fixed !important;bottom:0;left:0;right:0;background:var(--d1);
    padding:14px 20px;padding-bottom:max(14px,env(safe-area-inset-bottom));
    border-top:1px solid var(--bdr);z-index:100;box-shadow:0 -4px 20px rgba(15,25,35,.08);}
  .ob-btn{min-height:50px !important;font-size:15px !important;flex:1;}
  .ob-ghost{flex:0 0 auto;padding:14px 20px !important;}
  .ob-contract-body{max-height:200px !important;}
}
@media(max-width:390px){.ob-main{padding:20px 16px 120px !important;}.ob-ttl{font-size:20px !important;}}

/* ── Google / NSL login button — full override ── */
#ob-social-wrap,
#ob-social-wrap .nsl-container,
#ob-social-wrap .nsl-container-block,
#ob-social-wrap .nsl-container-block-center{
  width:100% !important;display:block !important;margin:0 !important;padding:0 !important;
}
#ob-social-wrap .nsl-button{
  display:flex !important;flex-direction:row !important;
  align-items:center !important;justify-content:center !important;
  width:100% !important;height:48px !important;
  background:var(--d2) !important;
  border:1.5px solid var(--bdr) !important;
  border-radius:12px !important;
  box-shadow:none !important;
  padding:0 20px !important;
  gap:10px !important;
  cursor:pointer !important;
  text-decoration:none !important;
  transition:border-color .15s,background .15s !important;
}
#ob-social-wrap .nsl-button:hover{
  border-color:var(--t3) !important;background:var(--d3) !important;
}
#ob-social-wrap .nsl-button-svg-container{
  width:20px !important;height:20px !important;
  flex-shrink:0 !important;margin:0 !important;
  display:flex !important;align-items:center !important;
}
#ob-social-wrap .nsl-button-svg-container svg,
#ob-social-wrap .nsl-button-svg-container img{
  width:20px !important;height:20px !important;
}
#ob-social-wrap .nsl-button-label-text{
  font-size:14px !important;font-weight:500 !important;
  color:var(--t1) !important;
  text-decoration:none !important;
  letter-spacing:-.1px !important;
  font-family:var(--fn) !important;
}
/* Kill any blue link colour the browser adds */
#ob-social-wrap a,
#ob-social-wrap a:visited,
#ob-social-wrap a:hover{
  color:var(--t1) !important;
  text-decoration:none !important;
}
.ob-adv-card{display:flex;align-items:center;gap:16px;background:var(--d2);border:1px solid var(--bdr);border-radius:18px;padding:18px 20px;margin-bottom:18px;position:relative}
.ob-adv-info{flex:1;min-width:0}
.ob-adv-role-label{font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:1.8px;color:var(--pk);margin-bottom:4px}
.ob-adv-contact-btn{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:600;color:var(--t2);background:var(--d3);border:1px solid var(--bdr);border-radius:20px;padding:5px 12px;text-decoration:none;flex-shrink:0;transition:var(--tr)}
.ob-adv-contact-btn:hover{background:var(--d4);color:var(--t1)}
</style>

<svg width="0" height="0" style="position:absolute">
  <defs>
    <linearGradient id="sg" x1="0%" y1="0%" x2="100%" y2="0%">
      <stop offset="0%" style="stop-color:#E8547A"/>
      <stop offset="100%" style="stop-color:#3C8FB5"/>
    </linearGradient>
  </defs>
</svg>

<div class="ob-wrap">
<aside class="ob-side">
  <div class="ob-logo">6ix Developers</div>
  <div class="ob-side-content">
    <h2 class="ob-side-hl">Your <span>marketing strategy</span> starts here.</h2>
    <p class="ob-side-desc">Tell us about your business, pick your services, and we'll build a personalised growth plan in minutes.</p>
    <div class="ob-steps-nav" id="ob-sidebar-steps">
      <div class="ob-step-item" data-step="1"><div class="ob-step-num">1</div><div class="ob-step-lbl">Create Account</div></div>
      <div class="ob-step-item" data-step="2"><div class="ob-step-num">2</div><div class="ob-step-lbl">Select Services</div></div>
      <div class="ob-step-item" data-step="3"><div class="ob-step-num">3</div><div class="ob-step-lbl">Business Profile</div></div>
      <div class="ob-step-item" data-step="4"><div class="ob-step-num">4</div><div class="ob-step-lbl">AI Strategy &amp; Score</div></div>
      <div class="ob-step-item" data-step="5"><div class="ob-step-num">5</div><div class="ob-step-lbl">Plan &amp; Agreement</div></div>
    </div>
  </div>
  <div class="ob-side-foot">
    Secured by SSL · <a href="<?php echo esc_url(get_privacy_policy_url()); ?>">Privacy Policy</a><br>
    Questions? <a href="mailto:<?php echo esc_attr(get_option('admin_email')); ?>">Contact us</a>
  </div>
</aside>

<div class="ob-main" id="ob-main-el">
  <div class="ob-prog" id="ob-prog" style="display:none">
    <div class="ob-prog-track"><div class="ob-prog-fill" id="ob-prog-fill" style="width:0%"></div></div>
    <span class="ob-prog-lbl" id="ob-prog-lbl">Step 1 / 5</span>
  </div>

  <!-- LOGIN -->
  <div class="ob-panel active" id="ob-login">
    <?php if(is_user_logged_in()):$u=wp_get_current_user();?>
    <script>document.addEventListener('DOMContentLoaded',function(){
      OB.resumeLoggedIn(<?php echo get_current_user_id();?>,<?php echo wp_json_encode($u->user_email);?>,
        <?php echo intval(get_user_meta(get_current_user_id(),'six_checkout_step',true)?:1);?>);
    });</script>
    <?php else:?>
    <div style="font-family:'Syne',sans-serif;font-size:20px;font-weight:800;background:linear-gradient(135deg,var(--t1),var(--cy));-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin-bottom:32px">6ix Developers</div>
    <div class="ob-eye">Welcome</div>
    <h1 class="ob-ttl">Let's get started.</h1>
    <p class="ob-dsc">Enter your email — we'll log you in or set up your account automatically.</p>
    <div class="ob-alert e" id="ob-login-err"></div>
    <div id="ob-social-wrap" style="margin-bottom:18px"><?php echo do_shortcode('[nextend_social_login provider="google" style="fullwidth"]');?></div>
    <div class="ob-div"><div class="ob-div-line"></div><span class="ob-div-txt">or continue with email</span><div class="ob-div-line"></div></div>
    <div class="ob-fg"><label class="ob-lbl">Email Address</label>
      <input class="ob-inp" type="email" id="ob-email" placeholder="you@company.com" autocomplete="email"></div>
    <div id="ob-pw-sec" style="display:none">
      <div class="ob-hint" id="ob-hint"></div>
      <div class="ob-fg" style="margin-top:14px"><label class="ob-lbl">Password</label>
        <div class="ob-pw-wrap">
          <input class="ob-inp" type="password" id="ob-pw" placeholder="Your password" autocomplete="current-password" style="padding-right:44px">
          <button class="ob-pw-tog" onclick="togglePw()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
        </div>
      </div>
      <div style="text-align:right;margin-top:6px"><a href="#" style="font-size:12px;color:var(--cy);text-decoration:none" onclick="OB.showForgot();return false">Forgot password?</a></div>
      <div id="ob-forgot-wrap" style="display:none;margin-top:12px;background:var(--d3);border:1px solid var(--bdr);border-radius:10px;padding:14px">
        <div style="font-size:12px;color:var(--t2);margin-bottom:10px">We'll email you a reset link.</div>
        <div style="display:flex;gap:8px">
          <input class="ob-inp" id="ob-forgot-email" placeholder="your@email.com" type="email" style="flex:1">
          <button class="ob-btn ob-primary" style="padding:10px 16px;font-size:13px" onclick="OB.sendReset()">Send</button>
        </div>
        <div id="ob-forgot-msg" style="font-size:12px;margin-top:8px"></div>
      </div>
    </div>
    <div class="ob-btnrow" style="margin-top:20px">
      <button class="ob-btn ob-primary" style="flex:1" id="ob-login-btn" onclick="OB.handleEmail()">Continue &rarr;</button>
    </div>
    <div id="ob-s1-back-wrap" style="display:none;margin-top:10px"><button class="ob-btn ob-ghost" style="width:100%" onclick="OB.backToEmail()">&larr; Use a different email</button></div>
    <?php endif;?>
  </div>

  <!-- STEP 1: Account -->
  <div class="ob-panel" id="ob-s1">
    <div class="ob-eye">Step 1 of 5 &middot; Account</div>
    <h2 class="ob-ttl" id="s1-ttl">Tell us about yourself.</h2>
    <p class="ob-dsc" id="s1-dsc">Your advisor will use these details to personalise your consultation.</p>
    <div class="ob-alert e" id="s1-err"></div>
    <div class="ob-g2">
      <div class="ob-fg"><label class="ob-lbl">First Name *</label><input class="ob-inp" id="s1-first" placeholder="Alex" autocomplete="given-name"></div>
      <div class="ob-fg"><label class="ob-lbl">Last Name *</label><input class="ob-inp" id="s1-last" placeholder="Johnson" autocomplete="family-name"></div>
    </div>
    <div class="ob-fg"><label class="ob-lbl">Email</label><input class="ob-inp" id="s1-email" type="email" readonly autocomplete="email"></div>
    <div class="ob-fg"><label class="ob-lbl">Phone Number *</label>
      <input class="ob-inp" id="s1-phone" placeholder="+1 (416) 555-0100" type="tel" autocomplete="tel">
      <div style="font-size:11px;color:var(--t3);margin-top:4px">Your advisor will call to start your free consultation.</div>
    </div>
    <div id="s1-pw-wrap" class="ob-fg"><label class="ob-lbl">Create a Password *</label>
      <div class="ob-pw-wrap">
        <input class="ob-inp" type="password" id="s1-pw" placeholder="Minimum 8 characters" autocomplete="new-password" style="padding-right:44px">
        <button class="ob-pw-tog" onclick="togglePw1()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
      </div>
    </div>
    <div class="ob-trust">
      <svg viewBox="0 0 24 24" fill="none" stroke="var(--ok)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      <span>Your information is private and never shared with third parties.</span>
    </div>
    <div class="ob-btnrow">
      <button class="ob-btn ob-ghost" onclick="OB.backToEmail()">&larr; Back</button>
      <button class="ob-btn ob-primary" style="flex:1" id="s1-btn" onclick="OB.doStep1()">Select Services &rarr;</button>
    </div>
  </div>

  <!-- STEP 2: Service selection -->
  <div class="ob-panel" id="ob-s2">
    <div class="ob-eye">Step 2 of 5 &middot; Services</div>
    <h2 class="ob-ttl">Which services do you need?</h2>
    <p class="ob-dsc">Select everything that applies. We'll ask targeted questions for each service next.</p>
    <div class="ob-alert e" id="s2-err"></div>
    <div class="ob-svc-grid">
      <div class="ob-svc" data-svc="google-ads" style="--c:#4285F4">
        <div class="ob-svc-bar"></div><div class="ob-svc-chk"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
        <div class="ob-svc-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 20V10M12 20V4M6 20v-6"/></svg></div>
        <div class="ob-svc-name">Google Ads</div>
        <div class="ob-svc-desc">Reach customers actively searching for your services with precision-targeted paid campaigns.</div>
      </div>
      <div class="ob-svc" data-svc="seo" style="--c:#1B9E52">
        <div class="ob-svc-bar"></div><div class="ob-svc-chk"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
        <div class="ob-svc-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></div>
        <div class="ob-svc-name">SEO</div>
        <div class="ob-svc-desc">Build lasting organic rankings that bring qualified traffic month after month without ad spend.</div>
      </div>
      <div class="ob-svc" data-svc="google-business" style="--c:#FBBC04">
        <div class="ob-svc-bar"></div><div class="ob-svc-chk"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
        <div class="ob-svc-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></div>
        <div class="ob-svc-name">Google Business Profile</div>
        <div class="ob-svc-desc">Dominate local search and drive walk-ins with an optimised Google Business listing.</div>
      </div>
      <div class="ob-svc" data-svc="website" style="--c:#7C5CBF">
        <div class="ob-svc-bar"></div><div class="ob-svc-chk"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
        <div class="ob-svc-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></div>
        <div class="ob-svc-name">Website Development</div>
        <div class="ob-svc-desc">A high-converting, professionally designed website that works as your best sales asset.</div>
      </div>
    </div>
    <div class="ob-notsure" id="ob-notsure">
      <svg viewBox="0 0 24 24" fill="none" stroke="var(--t3)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      <div><div class="ob-notsure-t">Not sure &mdash; recommend for me</div><div class="ob-notsure-s">We'll match you with the best services based on your goals</div></div>
    </div>
    <div class="ob-btnrow">
      <button class="ob-btn ob-ghost" onclick="OB.goStep(1)">&larr; Back</button>
      <button class="ob-btn ob-primary" style="flex:1" onclick="OB.doStep2()">Answer a Few Questions &rarr;</button>
    </div>
  </div>

  <!-- STEP 3: Questionnaire -->
  <!-- STEP 3a: Business Basics -->
  <div class="ob-panel" id="ob-s3a">
    <div class="ob-eye">Step 3 of 5 &middot; Business Profile</div>
    <h2 class="ob-ttl">Tell us about your business.</h2>
    <p class="ob-dsc">This is the foundation of your personalised growth plan.</p>
    <div class="ob-alert e" id="s3a-err"></div>
    <div class="ob-g2">
      <div class="ob-fg"><label class="ob-lbl">Business Name *</label><input class="ob-inp" id="q-biz" placeholder="Acme Corp" autocomplete="organization"></div>
      <div class="ob-fg"><label class="ob-lbl">Website</label><input class="ob-inp" id="q-web-url" placeholder="https://yoursite.com" type="url"></div>
    </div>
    <div class="ob-fg"><label class="ob-lbl">Business Address</label><input class="ob-inp" id="q-addr" placeholder="123 Main St, Toronto, ON" autocomplete="street-address"></div>
    <div class="ob-g2">
      <div class="ob-fg"><label class="ob-lbl">Industry</label>
        <select class="ob-inp" id="q-ind">
          <option value="">Select your industry…</option>
          <optgroup label="Health &amp; Medical">
            <option>Dental / Orthodontics</option>
            <option>Medical Clinic / Family Doctor</option>
            <option>Physiotherapy / Chiropractic</option>
            <option>Mental Health / Therapy</option>
            <option>Optometry / Vision Care</option>
            <option>Pharmacy</option>
            <option>Veterinary / Pet Care</option>
            <option>Medical Spa / Aesthetics</option>
            <option>Naturopathic / Alternative Medicine</option>
          </optgroup>
          <optgroup label="Home Services">
            <option>Plumbing</option>
            <option>HVAC / Heating &amp; Cooling</option>
            <option>Electrical</option>
            <option>Roofing</option>
            <option>General Contracting</option>
            <option>Landscaping / Snow Removal</option>
            <option>Cleaning Services</option>
            <option>Pest Control</option>
            <option>Painting</option>
            <option>Flooring / Renovation</option>
            <option>Moving &amp; Storage</option>
            <option>Security Systems</option>
          </optgroup>
          <optgroup label="Legal &amp; Finance">
            <option>Law Firm / Legal Services</option>
            <option>Accounting &amp; Bookkeeping</option>
            <option>Financial Planning / Wealth Management</option>
            <option>Insurance Agency</option>
            <option>Mortgage Broker</option>
            <option>Tax Services</option>
          </optgroup>
          <optgroup label="Real Estate">
            <option>Real Estate Agent / Brokerage</option>
            <option>Property Management</option>
            <option>Commercial Real Estate</option>
            <option>Home Staging / Interior Design</option>
          </optgroup>
          <optgroup label="Automotive">
            <option>Auto Repair / Mechanic</option>
            <option>Car Dealership</option>
            <option>Auto Body &amp; Collision</option>
            <option>Towing &amp; Roadside</option>
            <option>Car Detailing / Wash</option>
          </optgroup>
          <optgroup label="Food &amp; Hospitality">
            <option>Restaurant / Café</option>
            <option>Food Truck / Catering</option>
            <option>Bakery / Dessert Shop</option>
            <option>Bar / Nightclub</option>
            <option>Hotel &amp; Hospitality</option>
            <option>Event Venue</option>
          </optgroup>
          <optgroup label="Beauty &amp; Wellness">
            <option>Hair Salon / Barbershop</option>
            <option>Nail Salon</option>
            <option>Spa &amp; Massage</option>
            <option>Tattoo &amp; Piercing</option>
            <option>Fitness / Personal Training</option>
            <option>Yoga / Pilates Studio</option>
            <option>Crossfit / Martial Arts</option>
          </optgroup>
          <optgroup label="Retail &amp; E-commerce">
            <option>Retail Store</option>
            <option>E-commerce / Online Store</option>
            <option>Grocery &amp; Supermarket</option>
            <option>Clothing &amp; Fashion</option>
            <option>Electronics &amp; Tech Retail</option>
            <option>Furniture &amp; Home Decor</option>
            <option>Jewellery &amp; Accessories</option>
          </optgroup>
          <optgroup label="Professional Services">
            <option>Marketing &amp; Advertising Agency</option>
            <option>IT Services / Tech Support</option>
            <option>SaaS / Software Company</option>
            <option>Consulting</option>
            <option>Staffing &amp; Recruitment</option>
            <option>Photography &amp; Videography</option>
            <option>Printing &amp; Signage</option>
            <option>Logistics &amp; Courier</option>
          </optgroup>
          <optgroup label="Education &amp; Childcare">
            <option>Tutoring / Test Prep</option>
            <option>Private School / Academy</option>
            <option>Daycare / Childcare</option>
            <option>Driving School</option>
            <option>Music / Arts School</option>
            <option>Language School</option>
          </optgroup>
          <optgroup label="Non-Profit &amp; Community">
            <option>Non-Profit Organization</option>
            <option>Religious Organization</option>
            <option>Community Services</option>
          </optgroup>
          <optgroup label="Other">
            <option>Other</option>
          </optgroup>
        </select>
      </div>
      <div class="ob-fg"><label class="ob-lbl">Years in Business</label>
        <select class="ob-inp" id="q-yrs">
          <option value="">Select…</option>
          <option>Less than 1 year</option><option>1–2 years</option><option>3–5 years</option><option>6–10 years</option><option>10+ years</option>
        </select>
      </div>
    </div>

    <div class="ob-fg"><label class="ob-lbl">What do you want to achieve? <span style="font-weight:400;font-size:11px">(select all that apply)</span></label>
      <div class="ob-chips" id="q-goals">
        <div class="ob-chip" data-v="leads">More Leads</div>
        <div class="ob-chip" data-v="sales">Online Sales</div>
        <div class="ob-chip" data-v="awareness">Brand Awareness</div>
        <div class="ob-chip" data-v="local">Local Growth</div>
        <div class="ob-chip" data-v="traffic">More Website Traffic</div>
        <div class="ob-chip" data-v="retention">Customer Retention</div>
      </div>
    </div>
    <div class="ob-btnrow">
      <button class="ob-btn ob-ghost" onclick="OB.goStep(2)">&larr; Back</button>
      <button class="ob-btn ob-primary" style="flex:1" onclick="OB.doStep3a()">Next &rarr;</button>
    </div>
  </div>

  <!-- STEP 3b: Service questions (rendered dynamically per service) -->
  <div class="ob-panel" id="ob-s3b">
    <div class="ob-eye" id="s3b-eye">Step 3 of 5 &middot; Google Ads Details</div>
    <h2 class="ob-ttl" id="s3b-ttl">Tell us about your Google Ads goals.</h2>
    <p class="ob-dsc" id="s3b-dsc">A few quick questions so we can build the right campaign structure for you.</p>
    <div id="ob-s3b-content"></div>
    <div class="ob-alert e" id="s3b-err"></div>
    <!-- Service progress dots -->
    <div id="ob-svc-dots" style="display:flex;gap:6px;justify-content:center;margin-bottom:20px"></div>
    <div class="ob-btnrow">
      <button class="ob-btn ob-ghost" id="s3b-back-btn" onclick="OB.s3bBack()">&larr; Back</button>
      <button class="ob-btn ob-primary" style="flex:1" id="s3b-next-btn" onclick="OB.s3bNext()">Next &rarr;</button>
    </div>
  </div>

  <!-- STEP 3c: Competitors (optional) -->
  <div class="ob-panel" id="ob-s3c">
    <div class="ob-eye">Step 3 of 5 &middot; Final Details</div>
    <h2 class="ob-ttl">Almost there.</h2>
    <p class="ob-dsc">Optional details that help us build a sharper competitive strategy for you.</p>
    <div class="ob-fg"><label class="ob-lbl">Main Competitors <span style="font-weight:400">(website URLs)</span></label>
      <input class="ob-inp" id="q-c1" placeholder="competitor1.com" style="margin-bottom:8px">
      <input class="ob-inp" id="q-c2" placeholder="competitor2.com" style="margin-bottom:8px">
      <input class="ob-inp" id="q-c3" placeholder="competitor3.com">
      <div style="font-size:11px;color:var(--t3);margin-top:6px">We use these to benchmark your strategy and find gaps in the market.</div>
    </div>
    <div class="ob-g2">
      <div class="ob-fg"><label class="ob-lbl">CRM / Tools Used</label><input class="ob-inp" id="q-crm" placeholder="e.g. HubSpot, Salesforce…"></div>
      <div class="ob-fg"><label class="ob-lbl">Reviews / Awards</label><input class="ob-inp" id="q-awards" placeholder="e.g. Google 4.8★, BBB A+"></div>
    </div>
    <div class="ob-fg"><label class="ob-lbl">Anything else we should know?</label>
      <textarea class="ob-inp" id="q-notes" rows="3" placeholder="Any context about your business, past marketing, or goals…" style="resize:vertical"></textarea>
    </div>
    <div class="ob-btnrow">
      <button class="ob-btn ob-ghost" onclick="OB.s3cBack()">&larr; Back</button>
      <button class="ob-btn ob-primary" style="flex:1" id="s3c-btn" onclick="OB.doStep3()">Generate My Growth Plan &rarr;</button>
    </div>
  </div>

  <!-- hidden: keep ob-s3 id so goStep(3) routing works -->
  <div id="ob-s3" style="display:none"></div>

  <!-- STEP 4: AI Growth Plan (replaces score) -->
  <div class="ob-panel" id="ob-s4">
    <div class="ob-eye" id="s4-eye">Step 4 of 5 &middot; Your Growth Plan</div>
    <h2 class="ob-ttl" id="s4-ttl">Building your plan…</h2>
    <p class="ob-dsc" id="s4-dsc">Analysing your business and selected services.</p>

    <!-- Loading state -->
    <div id="ob-s4-loading" style="text-align:center;padding:40px 20px">
      <div style="display:inline-flex;flex-direction:column;align-items:center;gap:16px">
        <div class="ob-ai-spinner"></div>
        <div style="font-size:13px;color:var(--t3)" id="ob-s4-loading-msg">Analysing your business profile…</div>
      </div>
    </div>

    <!-- AI results (hidden until ready) -->
    <div id="ob-s4-results" style="display:none">
      <div class="ob-plan-hero" id="ob-plan-hero"></div>
      <div id="ob-plan-assumptions" style="display:none"></div>
      <div id="ob-insight-cards"></div>
      <div id="ob-timeline-card" style="margin-bottom:16px"></div>
      <div id="ob-plan-summary-card"></div>
      <div id="ob-plan-disclaimer" style="display:none;font-size:11px;color:var(--t3);line-height:1.6;padding:12px 14px;background:var(--d3);border:1px solid var(--bdr);border-radius:10px;margin-bottom:6px"></div>
    </div>

    <div class="ob-btnrow" id="s4-btnrow" style="display:none">
      <button class="ob-btn ob-ghost" onclick="OB.s4Back()">&larr; Edit Details</button>
      <button class="ob-btn ob-primary" style="flex:1" onclick="OB.doStep4()">This looks right &rarr;</button>
    </div>
  </div>

  <!-- STEP 5: Agreement + Stripe -->
  <div class="ob-panel" id="ob-s5">
    <div class="ob-eye">Step 5 of 5 &middot; Agreement</div>
    <h2 class="ob-ttl" id="s5-ttl">You're almost there.</h2>
    <p class="ob-dsc" id="s5-dsc">Your advisor is ready. Choose how you'd like to proceed.</p>

    <!-- ── Advisor card ──────────────────────────────────────────────── -->
    <div class="ob-adv-card" id="ob-adv-reveal">
      <div class="ob-adv-av-wrap">
        <div class="ob-adv-av" id="ob-adv-init">AM</div>
        <div class="ob-adv-online"></div>
      </div>
      <div class="ob-adv-info">
        <div class="ob-adv-role-label">Your Dedicated Advisor</div>
        <div class="ob-adv-name" id="ob-adv-name">Loading&hellip;</div>
        <div class="ob-adv-role" id="ob-adv-role">Account Manager &middot; 6ix Developers</div>
      </div>
      
    </div>

    <!-- ── Trust note ────────────────────────────────────────────────── -->
    <div class="ob-trust-n">
      <div class="ob-trust-ico"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
      <div class="ob-trust-txt"><strong>10-day free consultation &mdash; no charge today.</strong> Your card is securely verified via Stripe but will not be charged until you and your advisor confirm the engagement.</div>
    </div>

    <!-- ── Dual path selection ────────────────────────────────────────── -->
    <div id="ob-dual-path-wrap">
      <div class="ob-dual-path" id="ob-path-select">
        <div class="ob-path-card ob-path-primary" id="ob-path-complete-btn" onclick="OB.selectPath('complete')">
          <div class="ob-path-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
          <div><div class="ob-path-title">Complete Onboarding Now</div><div class="ob-path-desc">Add card, sign agreement, get started today.</div></div>
        </div>
        <div class="ob-path-card" id="ob-path-call-btn" onclick="OB.selectPath('call')">
          <div class="ob-path-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
          <div><div class="ob-path-title">Schedule a Call First</div><div class="ob-path-desc">Talk to your advisor before committing &mdash; no card needed.</div></div>
        </div>
      </div>

      <!-- ── Complete onboarding form ──────────────────────────────────── -->
      <div id="ob-complete-form" style="display:none">
        <div class="ob-contract">
          <div class="ob-contract-hdr">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            <span>Service Agreement &amp; Authorisation</span>
          </div>
          <div class="ob-contract-body" id="ob-contract-text">
            <?php
            $agreement_page = get_page_by_path('service-agreement');
            if($agreement_page){ echo apply_filters('the_content', $agreement_page->post_content); }
            else { echo '<p>By signing below you agree to the 6ix Developers Service Agreement, including a 10-day free consultation period. No charge will be made until the engagement is confirmed by your advisor.</p>'; }
            ?>
          </div>
        </div>
        <div class="ob-stripe-wrap">
          <label class="ob-lbl">Payment Card <span style="font-weight:400;font-size:11px;color:var(--t3)">(saved, not charged today)</span></label>
          <div id="ob-card-el"></div>
          <div style="font-size:12px;color:var(--t3);margin-top:8px;display:flex;align-items:center;gap:6px">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Secured by Stripe. Card details are encrypted and never stored on our servers.
          </div>
          <div id="ob-stripe-err" style="color:#e53e3e;font-size:12px;margin-top:6px"></div>
        </div>
        <div class="ob-fg" style="margin-top:16px">
          <label class="ob-lbl">Digital Signature <span style="font-size:11px;font-weight:400">— type your full name to agree</span></label>
          <input class="ob-inp ob-sign-inp" id="ob-sig" placeholder="Your Full Name" autocomplete="off">
        </div>
        <div class="ob-alert e" id="s5-err"></div>
        <div class="ob-btnrow" style="margin-top:16px">
          <button class="ob-btn ob-ghost" onclick="OB.selectPath(null)">&larr; Back</button>
          <button class="ob-btn ob-primary" style="flex:1" id="ob-done-btn" onclick="OB.finish()">Start My Free Consultation &rarr;</button>
        </div>
      </div>

      <!-- ── Schedule call form ─────────────────────────────────────────── -->
      <div id="ob-schedule-form" style="display:none">
        <div class="ob-card-wrap" style="margin-bottom:16px">
          <div class="ob-card-head">Book a Consultation Call</div>
          <div class="ob-fg"><label class="ob-lbl">Preferred Date</label>
            <input type="date" class="ob-inp" id="ob-call-date" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
          </div>
          <div class="ob-fg" style="margin-top:14px"><label class="ob-lbl">Preferred Time Window</label>
            <div class="ob-chips" id="ob-call-time">
              <div class="ob-chip" data-v="9am-12pm">9am &ndash; 12pm</div>
              <div class="ob-chip" data-v="12pm-3pm">12pm &ndash; 3pm</div>
              <div class="ob-chip" data-v="3pm-6pm">3pm &ndash; 6pm</div>
              <div class="ob-chip" data-v="6pm-9pm">6pm &ndash; 9pm</div>
            </div>
          </div>
          <div class="ob-fg" style="margin-top:14px">
            <label class="ob-lbl">Anything specific to discuss? <span style="font-weight:400;font-size:11px">(optional)</span></label>
            <textarea class="ob-inp" id="ob-call-notes" rows="2" placeholder="Questions, goals, or concerns for the call&hellip;"></textarea>
          </div>
        </div>
        <div class="ob-alert e" id="s5-sched-err"></div>
        <button class="ob-btn ob-primary" style="width:100%" id="ob-sched-btn" onclick="OB.scheduleCall()">Confirm Call Request &rarr;</button>
        <button class="ob-btn ob-ghost" style="width:100%;margin-top:10px" onclick="OB.selectPath(null)">&larr; Back to options</button>
      </div>
    </div>
  </div>


  <!-- COMPLETE -->
  <div class="ob-panel" id="ob-complete">
    <div class="ob-done">
      <div class="ob-done-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" width="36" height="36"><polyline points="20 6 9 17 4 12"/></svg></div>
      <h2 class="ob-done-ttl">You're all set!</h2>
      <p class="ob-done-dsc">Welcome to 6ix Developers. Your advisor has been notified and will reach out within one business day to review your plan and get started.</p>
      <a href="<?php echo esc_url(home_url('/portal/')); ?>" class="ob-btn ob-primary" style="font-size:15px;padding:15px 36px">Go to My Dashboard &rarr;</a>
      <div style="margin-top:16px;font-size:12px;color:var(--t3)">Confirmation sent to <span id="ob-done-email" style="color:var(--cy)"></span></div>
    </div>
  </div>

</div><!-- /.ob-main -->
</div><!-- /.ob-wrap -->

<script>
(function(){
'use strict';
var S={
  step:0,
  userId:<?php echo intval($js_data['user_id']??0);?>,
  email:<?php echo wp_json_encode($js_data['email']??'');?>,
  advisor:null,isNew:true,isGoogle:false,
  svcs:[],budgets:{},q:{},score:0,
  nonce:(typeof sixPortal!=='undefined'?sixPortal.nonce:''),
  ajax:(typeof sixPortal!=='undefined'?sixPortal.ajax_url:'<?php echo esc_js(admin_url('admin-ajax.php'));?>'),
  sk:(typeof sixPortal!=='undefined'?sixPortal.stripe_key:'<?php echo esc_js(get_option('six_stripe_publishable_key',''));?>'),
  utm:(function(){var p=new URLSearchParams(window.location.search);return{source:p.get('utm_source')||'',medium:p.get('utm_medium')||'',campaign:p.get('utm_campaign')||''};
// Restore local state only for non-logged-in users
(function(){
  var phpUserId=<?php echo intval(get_current_user_id()); ?>;
  if(!phpUserId) OB._loadLocal();
})();})(),
};

function $i(id){return document.getElementById(id);}
function v(id){var e=$i(id);return e?e.value.trim():'';}
function chips(id){return Array.from(document.querySelectorAll('#'+id+' .ob-chip.sel')).map(function(c){return c.dataset.v;});}
function alert2(id,msg){var e=$i(id);if(!e)return;e.textContent=msg;e.classList.add('on');setTimeout(function(){e.classList.remove('on');},6000);}
function panel(id){document.querySelectorAll('.ob-panel').forEach(function(p){p.classList.remove('active');});var e=$i(id);if(e){e.classList.add('active');window.scrollTo({top:0,behavior:'smooth'});}}

function prog(n){
  var pr=$i('ob-prog'),fi=$i('ob-prog-fill'),lb=$i('ob-prog-lbl');
  if(!pr)return;
  if(n>=1){pr.style.display='flex';fi.style.width=(n/5*100)+'%';lb.textContent='Step '+n+' / 5';}
  else pr.style.display='none';
  document.querySelectorAll('.ob-step-item').forEach(function(el){
    var s=parseInt(el.dataset.step);
    el.classList.remove('active','done');
    if(s===n)el.classList.add('active');else if(s<n)el.classList.add('done');
  });
}

function post(data){
  return fetch(S.ajax,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:new URLSearchParams(Object.assign({nonce:S.nonce},data))
  }).then(function(r){return r.json();}).catch(function(){return{success:false,data:'Network error'};});
}

function animN(id,to,ms){
  var el=$i(id),t0=performance.now();
  var tick=function(now){var t=Math.min((now-t0)/ms,1);el.textContent=Math.round(to*t);if(t<1)requestAnimationFrame(tick);};
  requestAnimationFrame(tick);
}

/* ── Service meta ── */
var SM={'google-ads':{name:'Google Ads',color:'#4285F4'},'seo':{name:'SEO',color:'#1B9E52'},
  'google-business':{name:'Google Business',color:'#FBBC04'},'website':{name:'Website',color:'#7C5CBF'}};

/* ── Questionnaire builders ── */
function qSec(title,html){return'<div style="margin-bottom:22px"><div style="font-size:12px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:1px;margin-bottom:14px;padding-bottom:8px;border-bottom:1px solid var(--bdr)">'+title+'</div>'+html+'</div>';}
function qFG(lbl,inp){return'<div class="ob-fg"><label class="ob-lbl">'+lbl+'</label>'+inp+'</div>';}
function qSlider(id,lbl,mn,mx,def){
  return qFG(lbl,'<div><input type="range" class="ob-slider" id="'+id+'" min="'+mn+'" max="'+mx+'" value="'+def+'" step="100" oninput="OB.sl(this)">'
    +'<div class="ob-sllbls"><span>$'+mn.toLocaleString()+'</span><span>$'+mx.toLocaleString()+'</span></div>'
    +'<div class="ob-slval" id="'+id+'-v">$'+def.toLocaleString()+'/mo</div></div>');
}
function qTog(id){
  return'<div class="ob-tog-row"><div class="ob-tog-btn" data-tog="'+id+'" data-val="yes" onclick="OB.tog(this,\'yes\')">Yes</div>'
    +'<div class="ob-tog-btn" data-tog="'+id+'" data-val="no" onclick="OB.tog(this,\'no\')">No</div></div>';
}
function qTags(id){
  return'<div class="ob-tag-wrap" id="'+id+'-wrap" onclick="this.querySelector(\'input\').focus()">'
    +'<input class="ob-tag-inp" id="'+id+'-inp" placeholder="Type and press Enter…"></div>';
}
function qHours(pfx){
  var days=['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
  var h='<div class="ob-hrs" id="q-'+pfx+'-hrs">';
  days.forEach(function(d,i){
    h+='<div class="ob-hr-day"><div class="ob-hr-lbl">'+d+'</div>'
      +'<div class="ob-hr-btn'+(i<5?' on':'')+'" onclick="this.classList.toggle(\'on\')">AM</div>'
      +'<div class="ob-hr-btn'+(i<5?' on':'')+'" onclick="this.classList.toggle(\'on\')">PM</div>'
      +'</div>';
  });
  return h+'</div>';
}
// Read selected days/slots from qHours widget → comma-separated string
function getHoursVal(pfx){
  var wrap=document.getElementById('q-'+pfx+'-hrs');
  if(!wrap) return '';
  var days=['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
  var parts=[];
  wrap.querySelectorAll('.ob-hr-day').forEach(function(day,i){
    var btns=day.querySelectorAll('.ob-hr-btn');
    var am=btns[0]&&btns[0].classList.contains('on');
    var pm=btns[1]&&btns[1].classList.contains('on');
    if(am||pm) parts.push(days[i]+':'+(am&&pm?'All day':am?'AM':'PM'));
  });
  return parts.join(', ');
}
function qLoc(id){
  return '<input class="ob-inp" id="'+id+'" placeholder="e.g. Toronto, North York, Mississauga">';
}

function buildPane(slug){
  var h='';
  if(slug==='google-ads'){
    h+=qSec('Targeting &amp; Offer',
      qFG('Target Locations',qLoc('q-ads-loc'))
      +qFG('Products / Services to Promote','<textarea class="ob-inp" id="q-ads-prod" rows="2" placeholder="e.g. Dental cleanings, invisalign…"></textarea>')
      +qSlider('q-ads-bud','Monthly Ad Budget',500,20000,2000)
      +qFG('Ad Schedule',qHours('ads'))
    );
    h+=qSec('Market &amp; Messaging',
      qFG('Keywords <span style="font-weight:400;font-size:11px">(press Enter to add)</span>',qTags('q-ads-kw'))
      +qFG('Unique Selling Points','<input class="ob-inp" id="q-ads-usp" placeholder="What makes you stand out?">')
      +qFG('Current Promotions','<input class="ob-inp" id="q-ads-promo" placeholder="e.g. Free consultation, 10% off first service">')
    );
  } else if(slug==='seo'){
    h+=qSec('SEO Setup',
      qFG('Primary Pages to Rank','<textarea class="ob-inp" id="q-seo-pages" rows="2" placeholder="e.g. Dental implants Toronto, teeth whitening near me…"></textarea>')
      +qFG('Target Locations','<input class="ob-inp" id="q-seo-loc" placeholder="e.g. Toronto, North York, Scarborough">')
      +qSlider('q-seo-bud','Monthly SEO Budget',300,10000,1200)
      +qFG('Google Search Console Access?',qTog('q-seo-gsc'))
    );
    h+=qSec('Content &amp; Keywords',
      qFG('Target Keywords <span style="font-weight:400;font-size:11px">(press Enter)</span>',qTags('q-seo-kw'))
      +qFG('Unique Selling Points','<input class="ob-inp" id="q-seo-usp" placeholder="Why should customers choose you?">')
      +qFG('Existing Blog / Content?',qTog('q-seo-blog'))
      +qFG('Top Competitors (URLs)','<input class="ob-inp" id="q-seo-comp" placeholder="e.g. competitor1.com, competitor2.com">')
      +qFG('CRM / Review Tools <span style="font-weight:400;font-size:11px">e.g. Google, TrustIndex</span>','<input class="ob-inp" id="q-seo-crm" placeholder="HubSpot, TrustIndex, Google Reviews…">')
      +qFG('Reviews / Awards','<input class="ob-inp" id="q-seo-reviews" placeholder="e.g. 4.9 stars, 200+ reviews, award name">')
      +qFG('Anything Else?','<textarea class="ob-inp" id="q-seo-extra" rows="2" placeholder="Any other info that will help rank your site…"></textarea>')
    );
  } else if(slug==='google-business'){
    h+=qSec('Google Business Profile',
      qFG('Business Name on Google','<input class="ob-inp" id="q-gbp-name" placeholder="Acme Dental Care">')
      +qFG('Primary Category','<input class="ob-inp" id="q-gbp-cat" placeholder="e.g. Dentist, Plumber, Restaurant…">')
      +qFG('Services to Highlight','<textarea class="ob-inp" id="q-gbp-svcs" rows="2" placeholder="List your main services…"></textarea>')
      +qFG('Business Hours',qHours('gbp'))
      +qFG('Current Rating','<input class="ob-inp" id="q-gbp-rating" placeholder="e.g. 4.7 stars, 120 reviews" style="max-width:240px">')
      +qSlider('q-gbp-bud','Monthly Budget',200,5000,400)
    );
  } else if(slug==='website'){
    h+=qSec('Website Project',
      qFG('Website Goal','<div class="ob-chips" id="q-web-goal"><div class="ob-chip" data-v="lead-gen">Lead Generation</div><div class="ob-chip" data-v="ecom">E-commerce</div><div class="ob-chip" data-v="booking">Bookings</div><div class="ob-chip" data-v="portfolio">Portfolio</div></div>')
      +qFG('Pages Needed','<input class="ob-inp" id="q-web-pages" placeholder="e.g. Home, About, Services, Contact, Blog">')
      +qFG('Design Style','<div class="ob-chips" id="q-web-style"><div class="ob-chip" data-v="modern">Modern &amp; Clean</div><div class="ob-chip" data-v="bold">Bold &amp; Vibrant</div><div class="ob-chip" data-v="minimal">Minimal</div><div class="ob-chip" data-v="corp">Corporate</div></div>')
      +qFG('Reference Sites You Like','<input class="ob-inp" id="q-web-refs" placeholder="e.g. apple.com, airbnb.com">')
      +qFG('Existing Website to Redesign?',qTog('q-web-exist'))
      +qSlider('q-web-bud','Monthly Budget / Retainer',500,15000,2000)
    );
  }
  return h;
}


function getTags(wid){
  var w=$i(wid+'-wrap');if(!w)return[];
  return Array.from(w.querySelectorAll('.ob-tag')).map(function(t){return t.dataset.v;});
}
function togVal(id){return document.querySelector('[data-tog="'+id+'"].y')?'yes':'no';}

/* ─── PUBLIC API ─── */
window.OB={

  sl:function(el){
    var mn=parseFloat(el.min),mx=parseFloat(el.max),vl=parseFloat(el.value);
    el.style.setProperty('--p',((vl-mn)/(mx-mn)*100).toFixed(1)+'%');
    var ve=$i(el.id+'-v');if(ve)ve.textContent='$'+Math.round(vl).toLocaleString()+'/mo';
  },

  tog:function(el,val){
    var id=el.dataset.tog;
    document.querySelectorAll('[data-tog="'+id+'"]').forEach(function(b){b.classList.remove('y','n');});
    el.classList.add(val==='yes'?'y':'n');
  },

  locType:function(el){
    el.closest('.ob-loc-type').querySelectorAll('.ob-loc-btn').forEach(function(b){b.classList.remove('on');});
    el.classList.add('on');
  },

  resumeLoggedIn:function(uid,email,step){
    S.userId=uid;S.email=email;S.isNew=false;
    var m=$i('ob-main-el');if(m)m.classList.add('ob-main-form');

    // Fetch saved state from DB — restores all questionnaire fields on refresh
    Promise.all([
      post({action:'six_get_advisor_for_user',user_id:uid}),
      post({action:'six_get_onboarding_state',user_id:uid})
    ]).then(function(results){
      var ar=results[0], sr=results[1];
      if(ar&&ar.success&&ar.data) S.advisor=ar.data;

      if(sr&&sr.success&&sr.data){
        var d=sr.data;
        // Restore services
        if(d.platforms){
          S.svcs=d.platforms.split(',').filter(function(s){return s.trim()!=='';});
        }
        // Restore budgets
        if(d.budgets){
          S.budgets=d.budgets;
        }
        // Restore questionnaire data — S.q
        if(d.q){
          S.q=d.q;
          // Split competitors back into comp1/comp2/comp3
          if(d.q._competitors){
            var comps=d.q._competitors.split(',').map(function(s){return s.trim();});
            S.q.comp1=comps[0]||'';S.q.comp2=comps[1]||'';S.q.comp3=comps[2]||'';
          }
        }
        // Use DB step if higher than PHP-provided step
        var dbStep=Math.max(1,parseInt(d.step)||1);
        var finalStep=Math.max(step||1, dbStep);
        OB.goStep(finalStep);
      } else {
        OB.goStep(Math.max(1,step||1));
      }
    }).catch(function(){
      OB.goStep(Math.max(1,step||1));
    });
  },

  backToEmail:function(){
    S.email='';S.isNew=true;
    var btn=$i('ob-login-btn');
    if(btn){btn.textContent='Continue →';btn.disabled=false;btn.onclick=OB.handleEmail;}
    var pw=$i('ob-pw-sec');if(pw)pw.style.display='none';
    var hint=$i('ob-hint');if(hint)hint.textContent='';
    var back=$i('ob-s1-back-wrap');if(back)back.style.display='none';
    panel('ob-login');prog(0);
  },

  handleEmail:function(){
    var email=v('ob-email'),pws=$i('ob-pw-sec'),btn=$i('ob-login-btn');
    if(!email||!email.includes('@')){alert2('ob-login-err','Please enter a valid email address.');return;}
    if(pws&&pws.style.display!=='none'){OB.doLogin();return;}
    btn.innerHTML='<span class="ob-spin"></span>';btn.disabled=true;
    post({action:'six_check_email',email:email}).then(function(r){
      btn.disabled=false;S.email=email;
      OB.showLoginBack();
      if(r.data&&r.data.exists){
        $i('ob-hint').textContent='Account found for '+email;
        pws.style.display='block';btn.textContent='Log In \u2192';btn.onclick=OB.doLogin;
        $i('ob-pw').focus();S.isNew=false;
        var pw=$i('s1-pw-wrap');if(pw)pw.style.display='none';
      } else {
        S.isNew=true;
        var e=$i('s1-email');if(e)e.value=email;
        OB.goStep(1);
      }
    });
  },

  doLogin:function(){
    var email=v('ob-email'),pw=v('ob-pw'),btn=$i('ob-login-btn');
    btn.innerHTML='<span class="ob-spin"></span> Signing in\u2026';btn.disabled=true;
    post({action:'six_portal_login',email:email,password:pw}).then(function(r){
      btn.disabled=false;
      if(r.success){
        if(r.data.nonce)S.nonce=r.data.nonce;
        if(r.data.redirect_url){window.location.href=r.data.redirect_url;return;}
        S.userId=r.data.user_id;S.email=email;
        if(r.data.has_completed_checkout){window.location.replace('<?php echo esc_js(home_url('/portal/'));?>');}
        else{
          post({action:'six_get_advisor_for_user',user_id:S.userId}).then(function(ar){
            if(ar.success)S.advisor=ar.data;
            OB.goStep(r.data.resume_step||1);
          });
        }
      } else {btn.textContent='Log In \u2192';alert2('ob-login-err',r.data||'Incorrect email or password.');}
    });
  },

  doStep1:function(){
    var first=v('s1-first'),last=v('s1-last'),phone=v('s1-phone'),pw=v('s1-pw');
    if(!first||!last){alert2('s1-err','Please enter your first and last name.');return;}
    if(!phone){alert2('s1-err','Phone number is required — your advisor will use it to contact you.');return;}
    if(S.isNew&&!S.isGoogle&&pw.length<8){alert2('s1-err','Please create a password of at least 8 characters.');return;}
    var btn=$i('s1-btn');btn.innerHTML='<span class="ob-spin"></span> Saving\u2026';btn.disabled=true;
    var d={action:'six_create_personal_account',email:S.email,first:first,last:last,phone:phone,
      utm_source:S.utm.source,utm_medium:S.utm.medium,utm_campaign:S.utm.campaign};
    if(S.isNew&&!S.isGoogle)d.password=pw;
    post(d).then(function(res){
      btn.disabled=false;btn.textContent='Select Services \u2192';
      if(res.success){
        S.userId=res.data.user_id;S.advisor=res.data.advisor;
        if(res.data.nonce)S.nonce=res.data.nonce;
        S.q.first=first;S.q.last=last;S.q.phone=phone;
        OB.goStep(2);
      } else alert2('s1-err',res.data||'Could not save your details. Please try again.');
    });
  },

  doStep2:function(){
    var sel=Array.from(document.querySelectorAll('#ob-s2 .ob-svc.sel')).map(function(c){return c.dataset.svc;});
    var ns=$i('ob-notsure')&&$i('ob-notsure').classList.contains('sel');
    if(!sel.length&&!ns){alert2('s2-err','Please select at least one service.');return;}
    if(ns&&!sel.length)sel=['google-ads','seo'];
    S.svcs=sel;S.budgets={};
    post({action:'six_save_checkout_step',step:2,data:JSON.stringify({services:sel,not_sure:ns}),user_id:S.userId});
    OB.goStep('3a');
  },

  /* Step 3a: Business Basics */
  doStep3a:function(){
    var biz=v('q-biz');
    if(!biz){alert2('s3a-err','Please enter your business name.');return;}
    S.q=Object.assign(S.q,{
      bizname:biz,website:v('q-web-url'),address:v('q-addr'),
      industry:v('q-ind'),years:v('q-yrs'),goals:chips('q-goals').join(','),
    });
    // Save business basics to DB immediately
    if(S.userId){
      var sd={first:S.q.first||'',last:S.q.last||'',phone:S.q.phone||'',
        bizname:S.q.bizname||'',website:S.q.website||'',industry:S.q.industry||'',
        location:S.q.address||'',years:S.q.years||'',goal:S.q.goals||'',
        competitors:[S.q.comp1,S.q.comp2,S.q.comp3].filter(Boolean).join(','),
        platforms:S.svcs.join(',')};
      post({action:'six_save_checkout_step',step:1,data:JSON.stringify(sd),user_id:S.userId}).catch(function(){});
      OB._saveLocal();
    }
    if(S.svcs.length>0){
      S._svcIdx=0;OB._renderSvcScreen(0);OB.goStep('3b');
    } else OB.goStep('3c');
  },

  /* Step 3b: per-service screens */
  _renderSvcScreen:function(idx){
    var slug=S.svcs[idx];
    var meta={
      'google-ads':      {name:'Google Ads',           ttl:'Set up your Google Ads campaign.',   dsc:'These details help us build exactly the right campaign structure for you.'},
      'seo':             {name:'SEO',                  ttl:'Set up your SEO strategy.',          dsc:'A few details to tailor your organic growth plan to your market.'},
      'google-business': {name:'Google Business Profile',ttl:'Optimise your local presence.',    dsc:'These details power your local search strategy.'},
      'website':         {name:'Website',              ttl:'Plan your website project.',         dsc:'Tell us what you need so we can scope the right solution.'},
    };
    var m=meta[slug]||{name:slug,ttl:'Service details.',dsc:''};
    $i('s3b-eye').textContent='Step 3 of 5 · '+m.name+' Details';
    $i('s3b-ttl').textContent=m.ttl;
    $i('s3b-dsc').textContent=m.dsc;
    $i('ob-s3b-content').innerHTML=buildPane(slug);
    var dots=$i('ob-svc-dots');
    dots.innerHTML=S.svcs.map(function(s,i){
      return'<div class="ob-svc-dot-step'+(i<idx?' done':i===idx?' active':'')+'"></div>';
    }).join('');
    if(S.svcs.length<=1)dots.style.display='none';
    else dots.style.display='flex';
    document.querySelectorAll('#ob-s3b-content .ob-slider').forEach(function(sl){OB.sl(sl);});
    document.querySelectorAll('#ob-s3b-content .ob-tag-inp').forEach(function(inp){
      inp.addEventListener('keydown',function(e){
        if((e.key==='Enter'||e.key===',')&&this.value.trim()){
          e.preventDefault();var wrap=this.closest('.ob-tag-wrap');
          var tag=document.createElement('div');tag.className='ob-tag';tag.dataset.v=this.value.trim();
          tag.innerHTML=this.value.trim()+'<button class="ob-tag-del" onclick="this.parentNode.remove()">&times;</button>';
          wrap.insertBefore(tag,this);this.value='';
        }
      });
    });
    document.querySelectorAll('#ob-s3b-content .ob-chips .ob-chip').forEach(function(ch){
      ch.addEventListener('click',function(){this.classList.toggle('sel');});
    });
    $i('s3b-back-btn').textContent=idx===0?'← Back':('← '+(meta[S.svcs[idx-1]]||{name:S.svcs[idx-1]}).name);
    var isLast=idx===S.svcs.length-1;
    $i('s3b-next-btn').textContent=isLast?'Final Details →':('Next: '+(meta[S.svcs[idx+1]]||{name:S.svcs[idx+1]}).name+' →');
  },

  _collectSvcData:function(slug){
    if(slug==='google-ads'){
      var b=$i('q-ads-bud');S.budgets[slug]=b?parseInt(b.value):0;
      var locVal=v('q-ads-loc');
      var locBtn=document.querySelector('#ob-s3b-content .ob-loc-btn.on');
      S.q.ads_loc=locVal;
      S.q.ads_loc_type=locBtn?locBtn.textContent.trim():'Include';
      S.q.ads_prod=v('q-ads-prod');
      S.q.ads_kw=getTags('q-ads-kw').join(',');
      S.q.ads_usp=v('q-ads-usp');S.q.ads_promo=v('q-ads-promo');
      S.q.ads_sched=getHoursVal('ads')||'';
    } else if(slug==='seo'){
      var b=$i('q-seo-bud');S.budgets[slug]=b?parseInt(b.value):0;
      S.q.seo_pages=v('q-seo-pages');S.q.seo_loc=v('q-seo-loc');
      S.q.seo_kw=getTags('q-seo-kw').join(',');S.q.seo_usp=v('q-seo-usp');
      S.q.seo_gsc=togVal('q-seo-gsc');S.q.seo_blog=togVal('q-seo-blog');
      S.q.seo_comp=v('q-seo-comp')||'';
      S.q.seo_crm=v('q-seo-crm')||'';
      S.q.seo_reviews=v('q-seo-reviews')||'';
      S.q.seo_extra=v('q-seo-extra')||'';
    } else if(slug==='google-business'){
      var b=$i('q-gbp-bud');S.budgets[slug]=b?parseInt(b.value):0;
      S.q.gbp_name=v('q-gbp-name');S.q.gbp_cat=v('q-gbp-cat');
      S.q.gbp_svcs=v('q-gbp-svcs');S.q.gbp_rating=v('q-gbp-rating');
    } else if(slug==='website'){
      var b=$i('q-web-bud');S.budgets[slug]=b?parseInt(b.value):0;
      S.q.web_goal=chips('q-web-goal').join(',');
      S.q.web_pages=v('q-web-pages');S.q.web_style=chips('q-web-style').join(',');
      S.q.web_refs=v('q-web-refs');S.q.web_exist=togVal('q-web-exist');
    }
  },

  s3bBack:function(){
    OB._collectSvcData(S.svcs[S._svcIdx]);
    if(S._svcIdx===0)OB.goStep('3a');
    else{S._svcIdx--;OB._renderSvcScreen(S._svcIdx);}
  },

  s3bNext:function(){
    OB._collectSvcData(S.svcs[S._svcIdx]);
    OB._savePartialQ();   // persist to DB immediately
    if(S._svcIdx<S.svcs.length-1){S._svcIdx++;OB._renderSvcScreen(S._svcIdx);}
    else OB.goStep('3c');
  },

  // Save all collected questionnaire data to DB right now
  // so the server always has the latest data even if user abandons mid-flow
  _savePartialQ:function(){
    if(!S.userId) return;
    var tot=Object.values(S.budgets).reduce(function(a,b){return a+b;},0);
    var q=S.q;
    var sd={
      first:q.first||'',last:q.last||'',phone:q.phone||'',
      bizname:q.bizname||'',website:q.website||'',industry:q.industry||'',
      location:q.address||'',goal:q.goals||'',
      competitors:[q.comp1,q.comp2,q.comp3].filter(Boolean).join(','),
      mktg_budget:tot.toString(),platforms:S.svcs.join(','),
      // Google Ads
      ads_loc:q.ads_loc||'',ads_loc_type:q.ads_loc_type||'Include',ads_prod:q.ads_prod||'',ads_kw:q.ads_kw||'',
      ads_usp:q.ads_usp||'',ads_promo:q.ads_promo||'',ads_sched:q.ads_sched||'',
      ads_bud:S.budgets['google-ads']||0,
      // SEO
      seo_pages:q.seo_pages||'',seo_loc:q.seo_loc||'',seo_kw:q.seo_kw||'',
      seo_usp:q.seo_usp||'',seo_gsc:q.seo_gsc||'',seo_blog:q.seo_blog||'',
      seo_comp:q.seo_comp||'',seo_crm:q.seo_crm||'',
      seo_reviews:q.seo_reviews||'',seo_extra:q.seo_extra||'',
      seo_bud:S.budgets['seo']||0,
      // Google Business
      gbp_name:q.gbp_name||'',gbp_cat:q.gbp_cat||'',gbp_svcs:q.gbp_svcs||'',
      gbp_hrs:q.gbp_hrs||'',gbp_rating:q.gbp_rating||'',gbp_bud:S.budgets['google-business']||0,
      // Website
      web_goal:q.web_goal||'',web_pages:q.web_pages||'',web_style:q.web_style||'',
      web_refs:q.web_refs||'',web_exist:q.web_exist||'',web_bud:S.budgets['website']||0,
    };
    post({action:'six_save_checkout_step',step:1,data:JSON.stringify(sd),user_id:S.userId}).catch(function(){});
    OB._saveLocal();
  },

  s3cBack:function(){
    if(S.svcs.length>0){S._svcIdx=S.svcs.length-1;OB._renderSvcScreen(S._svcIdx);OB.goStep('3b');}
    else OB.goStep('3a');
  },

  /* Collect competitors + trigger AI plan */
  doStep3:function(){
    S.q.comp1=v('q-c1');S.q.comp2=v('q-c2');S.q.comp3=v('q-c3');
    S.q.crm=v('q-crm');S.q.awards=v('q-awards');S.q.notes=v('q-notes');
    var tot=Object.values(S.budgets).reduce(function(a,b){return a+b;},0);
    // CRITICAL: send ALL questionnaire data so estimate engine has everything
    var q=S.q;
    var sd={
      first:q.first||'',last:q.last||'',phone:q.phone||'',
      bizname:q.bizname||'',website:q.website||'',industry:q.industry||'',
      location:q.address||'',goal:q.goals||'',years:q.years||'',
      competitors:[q.comp1,q.comp2,q.comp3].filter(Boolean).join(','),
      mktg_budget:tot.toString(),platforms:S.svcs.join(','),
      // Google Ads
      ads_loc:q.ads_loc||'',ads_loc_type:q.ads_loc_type||'Include',
      ads_prod:q.ads_prod||'',ads_kw:q.ads_kw||'',
      ads_usp:q.ads_usp||'',ads_promo:q.ads_promo||'',ads_sched:q.ads_sched||'',
      ads_bud:S.budgets['google-ads']||0,
      // SEO
      seo_pages:q.seo_pages||'',seo_loc:q.seo_loc||'',seo_kw:q.seo_kw||'',
      seo_usp:q.seo_usp||'',seo_gsc:q.seo_gsc||'',seo_blog:q.seo_blog||'',
      seo_comp:q.seo_comp||'',seo_crm:q.seo_crm||'',
      seo_reviews:q.seo_reviews||'',seo_extra:q.seo_extra||'',
      seo_bud:S.budgets['seo']||0,
      // GBP
      gbp_name:q.gbp_name||'',gbp_cat:q.gbp_cat||'',gbp_svcs:q.gbp_svcs||'',
      gbp_hrs:q.gbp_hrs||'',gbp_rating:q.gbp_rating||'',gbp_bud:S.budgets['google-business']||0,
      // Website
      web_goal:q.web_goal||'',web_pages:q.web_pages||'',web_style:q.web_style||'',
      web_refs:q.web_refs||'',web_exist:q.web_exist||'',web_bud:S.budgets['website']||0,
    };
    var btn=$i('s3c-btn');btn.innerHTML='<span class="ob-spin"></span> Generating…';btn.disabled=true;
    post({action:'six_save_checkout_step',step:1,data:JSON.stringify(sd),user_id:S.userId}).then(function(){
      btn.disabled=false;btn.textContent='Generate My Growth Plan →';
      OB.goStep(4);
      OB.buildAIPlan();
    });
  },

  /* AI-powered growth plan — replaces score ring */
  buildAIPlan: async function(){
    var q=S.q, svcs=S.svcs;
    var tot=Object.values(S.budgets||{}).reduce(function(a,b){return a+(parseInt(b)||0);},0);
    S.score=Math.min(97,20+(svcs.length*10)+(tot>=5000?15:tot>=2000?10:tot>0?5:0)+(q.website?8:0)+(q.industry?5:0));

    var msgs=['Analysing your market and competition...','Pulling live keyword data from Google Ads...','Calculating your projected ROI and returns...','Building your tailored 60-day roadmap...'];
    var mi=0,msgEl=$i('ob-s4-loading-msg');
    if(msgEl) msgEl.textContent=msgs[0];
    var msgTimer=setInterval(function(){mi=(mi+1)%msgs.length;if(msgEl)msgEl.textContent=msgs[mi];},2500);

    var plan=null;
    try{
      // Pass all JS data directly so server has it even if DB write is slow
      var planPayload={
        action:'six_generate_growth_plan',
        user_id:S.userId,
        // Pass key fields directly as POST params
        bizname:   S.q.bizname||'',
        industry:  S.q.industry||'',
        location:  S.q.address||'',
        platforms: S.svcs.join(','),
        ads_loc:   S.q.ads_loc||'',
        ads_kw:    S.q.ads_kw||'',
        seo_kw:    S.q.seo_kw||'',
        gbp_cat:   S.q.gbp_cat||'',
        ads_bud:   String(S.budgets['google-ads']||0),
        seo_bud:   String(S.budgets['seo']||0),
        gbp_bud:   String(S.budgets['google-business']||0),
        web_bud:   String(S.budgets['website']||0),
        competitors:(S.q.comp1||'')+','+(S.q.comp2||'')+','+(S.q.comp3||''),
      };
      var res=await post(planPayload);
      if(res&&res.success&&res.data&&res.data.headline) plan=res.data;
    }catch(e){ console.warn('Growth plan error:',e); }

    // Client-side fallback if server call fails
    if(!plan){
      var svcN={'google-ads':'Google Ads','seo':'SEO','google-business':'Google Business Profile','website':'Website Development'};
      var ch=svcs.map(function(s){return svcN[s]||s;}).join(', ');
      plan={
        headline:(q.bizname||'Your business')+' is set up to generate consistent leads through '+ch+(q.address?' in '+q.address:'')+' — here is your 90-day roadmap.',
        kpis:[
          {label:'Est. Leads / Month 1',value:tot>0?Math.round(tot/150)+'-'+Math.round(tot/100):'8–15'},
          {label:'Est. Leads / Month 3',value:tot>0?Math.round(tot/80)+'-'+Math.round(tot/55):'20–35'},
          {label:'Est. Monthly ROI',value:tot>0?'+$'+Math.round(tot*2.5).toLocaleString():'TBD'}
        ],
        roadmap:[
          {week:'Week 1–2',phase:'Foundation',title:'Account setup & campaign architecture',
            points:['Audit your current digital presence and competitors in '+(q.address||'your market'),
                    'Build campaign structure tailored to '+(q.industry||'your industry')+' buying intent',
                    'Install tracking, conversion goals, and attribution'],
            outcome:'Everything live and tracking'},
          {week:'Week 3–4',phase:'Launch',title:'Campaigns go live — first data in',
            points:['Launch targeted campaigns across '+ch,'Set up A/B tests on ad copy and landing pages',
                    'Monitor daily — adjust bids, targeting, and messaging'],
            outcome:'First leads within 30 days'},
          {week:'Month 2',phase:'Optimise',title:'Data-driven refinement',
            points:['Identify top-performing keywords and audiences','Cut underperforming spend, scale what works',
                    'Deliver detailed performance report with recommendations'],
            outcome:'Lower cost per lead, higher quality'},
          {week:'Month 3',phase:'Scale',title:'Full momentum',
            points:['Expand winning campaigns to new audiences and keywords',
                    'Layer in '+(svcs.length>1?'cross-channel synergies between '+ch:'advanced targeting and retargeting'),
                    'Forecast and plan for months 4–6 based on real data'],
            outcome:tot>0?'Consistent '+Math.round(tot/80)+'-'+Math.round(tot/55)+' leads/month':'Consistent qualified lead flow'}
        ],
        disclaimer:true
      };
    }

    clearInterval(msgTimer);
    S._aiPlanJson=JSON.stringify(plan);
    post({action:'six_save_checkout_step',step:'1b',score:S.score,user_id:S.userId});

    var kpis=plan.kpis||[];
    var roadmap=plan.roadmap||[];
    var phaseColors=['#4285F4','#1B9E52','#E8547A','#9C27B0'];
    var backed=plan.data_backed;

    // ── Hero card ─────────────────────────────────────────────────────────
    var kpiHtml='';
    kpis.forEach(function(k){
      kpiHtml+='<div class="ob-kpi-stat"><span class="ob-kpi-val">'+k.value+'</span><div class="ob-kpi-lbl">'+k.label+'</div></div>';
    });
    // Trim sub to max 18 words
    var sub=plan.sub||'';
    var subWords=sub.split(' ');
    if(subWords.length>18) sub=subWords.slice(0,18).join(' ')+'…';
    $i('ob-plan-hero').innerHTML=
      '<div class="ob-plan-hero-label">Your Growth Estimate'+
        (backed?'<span class="ob-data-badge">Data-Backed ✓</span>':'<span class="ob-data-badge ob-data-est">Estimated</span>')+
      '</div>'+
      '<div class="ob-plan-hero-headline">'+(plan.headline||'Your tailored growth plan is ready.')+'</div>'+
      (sub?'<div class="ob-plan-hero-sub">'+sub+'</div>':'')+
      (kpiHtml?'<div class="ob-plan-hero-stats">'+kpiHtml+'</div>':'');

    // ── Insight cards (structured: what/why/action per service) ───────────
    var insHtml='';
    // Main single insight (backwards compat)
    if(plan.insight){
      var ins=plan.insight.split('.')[0];
      var insW=ins.split(' ');
      if(insW.length>20) ins=insW.slice(0,20).join(' ')+'…';
      insHtml+='<div class="ob-insight-card">'+
        '<div class="ob-insight-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>'+
        '<div class="ob-insight-txt"><strong>Key Insight — </strong>'+ins+'</div>'+
      '</div>';
    }
    // Structured insights array — what/why/action per service
    if(plan.insights&&plan.insights.length){
      plan.insights.forEach(function(ins){
        if(!ins.what) return;
        insHtml+=
          '<div class="ob-insight-card" style="flex-direction:column;align-items:flex-start;gap:10px;padding:16px 18px">'+
            '<div style="display:flex;align-items:flex-start;gap:10px">'+
              '<div class="ob-insight-ico" style="flex-shrink:0"><svg viewBox="0 0 24 24" fill="none" stroke="var(--cy)" stroke-width="2" width="15" height="15"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div>'+
              '<div><div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--cy);margin-bottom:4px">What&#39;s happening</div>'+
              '<div style="font-size:13px;color:var(--t1);line-height:1.5">'+ins.what+'</div></div>'+
            '</div>'+
            '<div style="display:flex;align-items:flex-start;gap:10px">'+
              '<div class="ob-insight-ico" style="flex-shrink:0"><svg viewBox="0 0 24 24" fill="none" stroke="var(--pk)" stroke-width="2" width="15" height="15"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg></div>'+
              '<div><div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--pk);margin-bottom:4px">Why it matters</div>'+
              '<div style="font-size:13px;color:var(--t2);line-height:1.5">'+ins.why+'</div></div>'+
            '</div>'+
            '<div style="display:flex;align-items:flex-start;gap:10px;background:rgba(255,255,255,0.04);border-radius:8px;padding:10px 12px">'+
              '<div class="ob-insight-ico" style="flex-shrink:0"><svg viewBox="0 0 24 24" fill="none" stroke="#56D364" stroke-width="2" width="15" height="15"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></div>'+
              '<div><div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#56D364;margin-bottom:4px">Action</div>'+
              '<div style="font-size:13px;font-weight:600;color:var(--t1);line-height:1.5">'+ins.action+'</div></div>'+
            '</div>'+
          '</div>';
      });
    }
    $i('ob-insight-cards').innerHTML=insHtml;

    // ── 60-day roadmap ────────────────────────────────────────────────────
    var rmHtml='<div class="ob-roadmap-wrap">';
    roadmap.forEach(function(ph,i){
      var col=phaseColors[i%phaseColors.length];
      var pts=(ph.points||[]).slice(0,3).map(function(p){
        // Trim to max 12 words
        var words=p.split(' ');
        if(words.length>12) p=words.slice(0,12).join(' ')+'…';
        return '<li>'+p+'</li>';
      }).join('');
      rmHtml+=
        '<div class="ob-roadmap-item">'+
          '<div class="ob-roadmap-left">'+
            '<div class="ob-roadmap-dot" style="background:'+col+'"></div>'+
            (i<roadmap.length-1?'<div class="ob-roadmap-line"></div>':'')+
          '</div>'+
          '<div class="ob-roadmap-body">'+
            '<div class="ob-roadmap-week" style="color:'+col+'">'+ph.week+'</div>'+
            '<div class="ob-roadmap-phase"><strong>'+ph.phase+'</strong> — '+ph.title+'</div>'+
            '<ul class="ob-roadmap-pts">'+pts+'</ul>'+
            (ph.outcome?'<div class="ob-roadmap-outcome"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="'+col+'" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> '+ph.outcome+'</div>':'')+
          '</div>'+
        '</div>';
    });
    rmHtml+='</div>';
    $i('ob-timeline-card').innerHTML=
      '<div class="ob-card-wrap">'+
        '<div class="ob-card-head">Your 60-Day Roadmap</div>'+
        rmHtml+
      '</div>';

    // ── Disclaimer ────────────────────────────────────────────────────────
    var disc=$i('ob-plan-disclaimer');
    if(disc){
      var roiKpi=(kpis.find(function(k){return k.label.indexOf('ROI')>-1;})||{}).value||'';
      var roiNote=roiKpi&&roiKpi!=='TBD'&&roiKpi.indexOf('$')>-1
        ? ' ROI is estimated as (projected leads × avg deal value × 20% close rate) minus your monthly investment.'
        : '';
      var src=backed?' based on real Google Ads keyword data for your market':' based on industry benchmarks for your market';
      disc.innerHTML='These projections are'+src+'.'+roiNote
        +' Once you complete onboarding, your advisor will reach out within one business day with a full benchmark report and custom strategy.';
      disc.style.display='block';
    }

    // ── Show ──────────────────────────────────────────────────────────────
    $i('ob-s4-loading').style.display='none';
    $i('ob-s4-results').style.display='block';
    $i('s4-btnrow').style.display='flex';
    $i('s4-ttl').textContent='Your 60-Day Growth Plan';
    $i('s4-dsc').textContent='Built on real data for your market. Review before proceeding.';
  },


  goStep:function(n){
    S.step=n;prog(n);
    var map={1:'ob-s1',2:'ob-s2','3a':'ob-s3a','3b':'ob-s3b','3c':'ob-s3c',3:'ob-s3a',4:'ob-s4',5:'ob-s5'};
    panel(map[n]||'ob-s'+n);
    if(n===3) prog(3);
    var e=$i('s1-email');if(e&&S.email)e.value=S.email;
    if(S.userId)post({action:'six_save_checkout_step',step:n,user_id:S.userId});
    if(typeof GE!=='undefined')GE.onStep(n);

    // ── Restore field values when navigating back ─────────────────────────
    var q=S.q;
    if(n==='3a'||n===3){
      // Business basics
      var flds={
        'q-biz':q.bizname,'q-web-url':q.website,'q-addr':q.address,
        'q-ind':q.industry,'q-yrs':q.years,
        'q-comp1':q.comp1||'','q-comp2':q.comp2||'','q-comp3':q.comp3||'',
        'q-ads-loc':q.ads_loc||'','q-ads-prod':q.ads_prod||'',
        'q-ads-usp':q.ads_usp||'','q-ads-promo':q.ads_promo||'','q-ads-sched':q.ads_sched||'',
        'q-seo-pages':q.seo_pages||'','q-seo-loc':q.seo_loc||'',
        'q-seo-usp':q.seo_usp||'','q-seo-comp':q.seo_comp||'',
        'q-seo-crm':q.seo_crm||'','q-seo-reviews':q.seo_reviews||'','q-seo-extra':q.seo_extra||'',
        'q-gbp-name':q.gbp_name||'','q-gbp-cat':q.gbp_cat||'',
        'q-gbp-svcs':q.gbp_svcs||'','q-gbp-rating':q.gbp_rating||'',
        'q-gbp-hrs':q.gbp_hrs||'',
        'q-web-pages':q.web_pages||'','q-web-refs':q.web_refs||'','q-web-exist':q.web_exist||'',
      };
      Object.keys(flds).forEach(function(id){
        var el=$i(id); if(el&&flds[id]) el.value=flds[id];
      });
      // Restore goal chips
      if(q.goals)(q.goals.split(',') ).forEach(function(v){
        var ch=document.querySelector('#q-goals .ob-chip[data-v="'+v.trim()+'"]');
        if(ch)ch.classList.add('sel');
      });
    }
    if(n==='3b'){
      // Restore location type button state
      setTimeout(function(){
        var lt=q.ads_loc_type||'Include';
        var btn=document.querySelector('#ob-s3b-content .ob-loc-type .ob-loc-btn.on');
        if(btn&&btn.textContent.trim()!==lt){
          document.querySelectorAll('#ob-s3b-content .ob-loc-type .ob-loc-btn').forEach(function(b){
            b.classList.remove('on');
            if(b.textContent.trim()===lt)b.classList.add('on');
          });
        }
        if(q.ads_loc){var el=$i('q-ads-loc');if(el)el.value=q.ads_loc;}
      },50);
    }
    if(n==='3c'){
      // Competitors
      var cs=['q-c1','q-c2','q-c3'];
      var vals=[q.comp1,q.comp2,q.comp3];
      cs.forEach(function(id,i){var el=$i(id);if(el&&vals[i])el.value=vals[i];});
      if($i('q-crm')&&q.crm)$i('q-crm').value=q.crm;
      if($i('q-awards')&&q.awards)$i('q-awards').value=q.awards;
      if($i('q-notes')&&q.notes)$i('q-notes').value=q.notes;
    }
    if(n===2){
      // Re-select services
      S.svcs.forEach(function(svc){
        var el=document.querySelector('#ob-s2 .ob-svc[data-svc="'+svc+'"]');
        if(el)el.classList.add('sel');
      });
    }

    // Switch main to form layout (not centered) once past login
    var m=$i('ob-main-el');if(m&&n>=1)m.classList.add('ob-main-form');
  },

  showForgot:function(){var w=$i('ob-forgot-wrap'),e=v('ob-email');if(w){w.style.display='block';var f=$i('ob-forgot-email');if(f&&e)f.value=e;}},
  sendReset:function(){
    var email=v('ob-forgot-email'),msg=$i('ob-forgot-msg');
    if(!email||!email.includes('@')){if(msg)msg.innerHTML='<span style="color:var(--err)">Enter a valid email.</span>';return;}
    post({action:'six_send_password_reset',email:email}).then(function(r){
      if(msg)msg.innerHTML=r.success?'<span style="color:var(--ok)">\u2713 Reset link sent!</span>':'<span style="color:var(--err)">'+(r.data||'Error')+'</span>';
    });
  },

  // Step 4 back button — return to last questionnaire screen
  s4Back:function(){
    OB.goStep('3c');
  },

  // Step 4 forward button — proceed to agreement
  doStep4:function(){
    OB.goStep(5);
    OB.loadAdvisor();
    // Init chip listeners for schedule call time picker
    setTimeout(function(){
      document.querySelectorAll('#ob-call-time .ob-chip').forEach(function(ch){
        ch.addEventListener('click',function(){
          document.querySelectorAll('#ob-call-time .ob-chip').forEach(function(x){x.classList.remove('sel');});
          ch.classList.add('sel');
        });
      });
    },150);
  },

  // ── Login: show back button after email entered ──────────────────────────
  showLoginBack:function(){
    var w=$i('ob-s1-back-wrap');
    if(w)w.style.display='block';
  },

  // ── Step 5: path selector ─────────────────────────────────────────────
  selectPath:function(path){
    var pathSel=$i('ob-path-select');
    var complete=$i('ob-complete-form');
    var schedule=$i('ob-schedule-form');
    document.querySelectorAll('.ob-path-card').forEach(function(b){b.classList.remove('selected');});
    if(path==='complete'){
      if(pathSel)pathSel.style.display='none';
      if(complete)complete.style.display='block';
      if(schedule)schedule.style.display='none';
      var btn=$i('ob-path-complete-btn');if(btn)btn.classList.add('selected');
      OB.initStripe();
    } else if(path==='call'){
      if(pathSel)pathSel.style.display='none';
      if(complete)complete.style.display='none';
      if(schedule)schedule.style.display='block';
      var btn=$i('ob-path-call-btn');if(btn)btn.classList.add('selected');
    } else {
      // Back — show path selector again
      if(pathSel)pathSel.style.display='flex';
      if(complete)complete.style.display='none';
      if(schedule)schedule.style.display='none';
    }
  },

  // ── Step 5: schedule call ─────────────────────────────────────────────
  scheduleCall:async function(){
    var date=v('ob-call-date');
    var selChip=document.querySelector('#ob-call-time .ob-chip.sel');
    var time=selChip?selChip.dataset.v:'';
    var notes=v('ob-call-notes')||'';
    if(!date){alert2('s5-sched-err','Please select a preferred date.');return;}
    if(!time){alert2('s5-sched-err','Please select a preferred time window.');return;}
    var btn=$i('ob-sched-btn');
    if(btn){btn.innerHTML='<span class="ob-spin"></span> Confirming…';btn.disabled=true;}

    var res=null;
    try{
      res=await post({
        action:'six_schedule_onboarding_call',
        user_id:S.userId||0,
        call_date:date,
        call_time:time,
        call_notes:notes,
        step1_data:JSON.stringify(S.q),
        services:S.svcs.join(','),
        score:S.score||0,
      });
    }catch(err){
      alert2('s5-sched-err','Network error. Please check your connection and try again.');
      if(btn){btn.innerHTML='Confirm Call Request →';btn.disabled=false;}
      return;
    }
    if(btn){btn.innerHTML='Confirm Call Request →';btn.disabled=false;}

    if(res&&res.success){
      // Show confirmation
      $i('ob-dual-path-wrap').innerHTML=
        '<div style="text-align:center;padding:24px 0">'+
          '<div style="width:48px;height:48px;border-radius:50%;background:rgba(27,158,82,.12);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;color:#1B9E52">'+
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'+
          '</div>'+
          '<div style="font-size:16px;font-weight:700;color:var(--t1);margin-bottom:6px">Call Requested</div>'+
          '<div style="font-size:13px;color:var(--t2)">Your advisor will reach out to confirm the time.</div>'+
          '<div style="font-size:12px;color:var(--t3);margin-top:8px">'+date+' · '+time+'</div>'+
        '</div>';
    } else {
      alert2('s5-sched-err', (res&&res.data)||'Something went wrong. Please try again.');
    }
  },

  // ── State persistence: save to localStorage ───────────────────────────
  _saveLocal:function(){
    try{
      var state={
        q:S.q, svcs:S.svcs, budgets:S.budgets,
        email:S.email, step:S.step, userId:S.userId,
        score:S.score, ts:Date.now()
      };
      localStorage.setItem('six_ob_state', JSON.stringify(state));
    }catch(e){}
  },
  _loadLocal:function(){
    try{
      var raw=localStorage.getItem('six_ob_state');
      if(!raw) return false;
      var state=JSON.parse(raw);
      // Expire after 7 days
      if(Date.now()-state.ts>604800000){localStorage.removeItem('six_ob_state');return false;}
      if(state.q)S.q=state.q;
      if(state.svcs)S.svcs=state.svcs;
      if(state.budgets)S.budgets=state.budgets;
      return true;
    }catch(e){return false;}
  },
  _clearLocal:function(){
    try{localStorage.removeItem('six_ob_state');}catch(e){}
  },

  // ── Step 5: path selector ─────────────────────────────────────────────
  // ── Step 5: Stripe card element ────────────────────────────────────────
  _stripe:null, _card:null,
  initStripe:function(){
    if(!S.sk||OB._stripe) return;
    OB._stripe = Stripe(S.sk);
    var els = OB._stripe.elements({
      appearance:{theme:'stripe',variables:{
        colorPrimary:'#E8547A',colorBackground:'#F0F2F5',
        colorText:'#0F1923',borderRadius:'10px'
      }}
    });
    OB._card = els.create('card',{hidePostalCode:true});
    OB._card.mount('#ob-card-el');
    OB._card.on('change',function(e){
      $i('ob-stripe-err').textContent = e.error ? e.error.message : '';
    });
  },

  // ── Step 5: load advisor name/avatar ──────────────────────────────────
  loadAdvisor:function(){
    if(!S.userId) return;
    // Try to get assigned advisor; if none, fall back to default
    var advisorAction = S.userId
        ? post({action:'six_get_advisor_for_user', user_id:S.userId})
        : post({action:'six_get_default_advisor'});
    advisorAction.then(function(r){
      if(!r||!r.success||!r.data) {
        // Try default advisor as fallback
        post({action:'six_get_default_advisor'}).then(function(r2){
          if(r2&&r2.success&&r2.data) {
            OB._applyAdvisorData(r2.data);
          }
        });
        return;
      }
      OB._applyAdvisorData(r.data);
    }).catch(function(){
      // Fallback on error
      post({action:'six_get_default_advisor'}).then(function(r3){
        if(r3&&r3.success&&r3.data) OB._applyAdvisorData(r3.data);
      }).catch(function(){});
    });
  },

  _applyAdvisorData:function(adv){
    var nm=$i('ob-adv-name'), rl=$i('ob-adv-role'), av=$i('ob-adv-init'), contact=$i('ob-adv-contact');
    var fullName=((adv.first_name||'')+' '+(adv.last_name||'')).trim();
    if(nm) nm.textContent=fullName||'Your Advisor';
    if(rl) rl.textContent=(adv.title||'Account Manager')+' · 6ix Developers';
    if(av){
      if(adv.avatar_url){
        av.style.background='none';
        av.innerHTML='<img src="'+adv.avatar_url+'" style="width:100%;height:100%;object-fit:cover;border-radius:50%">';
      } else {
        av.textContent=((adv.first_name||'A')[0]+(adv.last_name||'M')[0]).toUpperCase();
      }
    }
    if(contact&&adv.email){contact.href='mailto:'+adv.email;contact.style.display='inline-flex';}
  },

  // ── Step 5: submit ─────────────────────────────────────────────────────
  finish:async function(){
    var sig = v('ob-sig');
    if(!sig){ alert2('s5-err','Please type your full name to sign the agreement.'); return; }
    var btn = $i('ob-done-btn');
    btn.innerHTML = '<span class="ob-spin"></span> Processing…';
    btn.disabled = true;

    var pmId = null;
    if(OB._stripe && OB._card){
      var secret = await post({action:'six_stripe_setup', user_id:S.userId})
        .then(function(r){ return r.data && r.data.client_secret || ''; });
      if(secret){
        var res = await OB._stripe.confirmCardSetup(secret, {
          payment_method:{card:OB._card, billing_details:{name:sig, email:S.email}}
        });
        if(res.error){
          $i('ob-stripe-err').textContent = res.error.message;
          btn.innerHTML = 'Start My Free Consultation →';
          btn.disabled = false;
          return;
        }
        pmId = res.setupIntent.payment_method;
      }
    }

    post({
      action:'six_complete_onboarding',
      user_id:S.userId, signature:sig, payment_method_id:pmId||'',
      services:JSON.stringify(S.budgets), step1_data:JSON.stringify(S.q),
      score:S.score, ai_plan_json:S._aiPlanJson||''
    }).then(function(r){
      btn.innerHTML = 'Start My Free Consultation →';
      btn.disabled = false;
      if(r.success){
        S.step='done';
        OB._clearLocal();
        $i('ob-done-email').textContent = S.email;
        panel('ob-complete');
        var pr=$i('ob-prog'); if(pr) pr.style.display='none';
        setTimeout(function(){
          window.location.replace('<?php echo esc_js(home_url('/portal/')); ?>');
        }, 3000);
      } else {
        alert2('s5-err', r.data||'Something went wrong. Please try again.');
      }
    });
  },
};

/* ── Google Social Login — redirect-loop fix ── */
window.addEventListener('NSLAfterFormLogin',function(){
  post({action:'six_google_login_complete'}).then(function(res){
    if(!res.success)return;
    S.userId=res.data.user_id;S.email=res.data.email;S.advisor=res.data.advisor;
    S.isGoogle=true;S.isNew=false;if(res.data.nonce)S.nonce=res.data.nonce;
    S.q.first=res.data.first||'';S.q.last=res.data.last||'';S.q.phone=res.data.phone||'';
    // REDIRECT LOOP FIX: completed users go straight to portal
    if(res.data.completed){window.location.replace('<?php echo esc_js(home_url('/portal/'));?>');return;}
    var f=$i('s1-first'),l=$i('s1-last'),e=$i('s1-email'),p=$i('s1-phone'),pw=$i('s1-pw-wrap');
    if(f)f.value=S.q.first;if(l)l.value=S.q.last;if(e)e.value=S.email;if(p)p.value=S.q.phone;
    if(pw)pw.style.display='none';
    var ttl=$i('s1-ttl'),dsc=$i('s1-dsc');
    if(ttl)ttl.textContent='One last thing.';
    if(dsc)dsc.textContent='Please confirm your phone number so your advisor can reach you.';
    // Skip to step 2 if phone already on file, else stop at step 1 for phone
    OB.goStep(S.q.phone&&S.q.phone.length>=7?(res.data.resume_step||2):1);
  });
});

/* ── Service card clicks ── */
document.querySelectorAll('#ob-s2 .ob-svc').forEach(function(card){
  card.addEventListener('click',function(){
    this.classList.toggle('sel');
    var ns=$i('ob-notsure');if(ns)ns.classList.remove('sel');
  });
});
var ns=$i('ob-notsure');
if(ns)ns.addEventListener('click',function(){
  this.classList.toggle('sel');
  if(this.classList.contains('sel'))document.querySelectorAll('#ob-s2 .ob-svc').forEach(function(c){c.classList.remove('sel');});
});

/* ── Goal chip clicks ── */
document.querySelectorAll('#q-goals .ob-chip').forEach(function(c){
  c.addEventListener('click',function(){this.classList.toggle('sel');});
});

/* ── Password toggles ── */
window.togglePw=function(){var i=$i('ob-pw');if(i)i.type=i.type==='password'?'text':'password';};
window.togglePw1=function(){var i=$i('s1-pw');if(i)i.type=i.type==='password'?'text':'password';};

/* ── Growth Engine ── */
var GE = {
  _fired: false,
  _pageStart: Date.now(),
  _sessionId: (function(){
    var k='six_ob_sess';
    var id = sessionStorage.getItem(k);
    if(!id){ id='s'+Date.now()+'_'+Math.random().toString(36).slice(2,8); sessionStorage.setItem(k,id); }
    return id;
  })(),

  _stepNum: function(){
    var s=S.step;
    if(typeof s==='string'){
      if(s==='3a'||s==='3b'||s==='3c') return 3;
      return parseInt(s)||0;
    }
    return s||0;
  },

  // Heartbeat — keeps six_last_activity fresh for server-side stale detection
  heartbeat: function(){
    if(S.step==='done'||!S.userId) return;
    post({action:'six_save_checkout_step',step:GE._stepNum(),user_id:S.userId}).catch(function(){});
  },

  // Send abandon — fires once per page session
  send: function(){
    if(GE._fired) return;
    if(S.step==='done') return;
    if(!S.userId && !S.email) return;
    if((Date.now()-GE._pageStart)<8000) return; // ignore bounces under 8s
    GE._fired = true;

    var body = new URLSearchParams({
      action:     'six_track_abandoned_checkout',
      user_id:    String(S.userId||0),
      email:      S.email||'',
      session_id: GE._sessionId,
      step:       String(GE._stepNum()),
      score:      String(S.score||0),
      nonce:      S.nonce||''
    }).toString();
    var url = S.ajax;

    // sendBeacon is the most reliable method for page-unload requests
    var sent = false;
    try{ sent = navigator.sendBeacon(url, new Blob([body],{type:'application/x-www-form-urlencoded'})); }catch(e){}

    // keepalive fetch as backup (Chrome prefers this)
    try{ fetch(url,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body,keepalive:true}).catch(function(){}); }catch(e){}

    // sync XHR as last resort if beacon failed
    if(!sent){
      try{var x=new XMLHttpRequest();x.open('POST',url,false);x.setRequestHeader('Content-Type','application/x-www-form-urlencoded');x.send(body);}catch(e){}
    }
  },

  onStep: function(n){
    GE._fired = false; // allow re-fire if user navigates back then leaves again
    if(S.userId&&n>0) post({action:'six_growth_track',type:'step_view',user_id:S.userId,step:n}).catch(function(){});
  },
};

document.addEventListener('DOMContentLoaded',function(){
  GE._pageStart = Date.now();

  // Device tracking
  if(S.userId){
    post({action:'six_growth_device',user_id:S.userId,
      device:/Mobi|Android|iPhone/i.test(navigator.userAgent)?'mobile':'desktop'
    }).catch(function(){});
  }

  // Heartbeat every 30s — keeps server-side stale detection accurate
  setInterval(function(){ GE.heartbeat(); }, 30000);

  // Inactivity timer — 3 minutes
  var inactTimer, lastAct=Date.now();
  function resetInact(){
    lastAct=Date.now();
    clearTimeout(inactTimer);
    inactTimer=setTimeout(function(){
      if(S.step!=='done') GE.send();
    },180000);
  }
  ['mousedown','keydown','touchstart','scroll','click','input'].forEach(function(ev){
    document.addEventListener(ev,resetInact,{passive:true});
  });
  resetInact();

  // Page close — beforeunload fires on Chrome/Firefox/Edge desktop
  window.addEventListener('beforeunload',function(){
    if(S.step!=='done') GE.send();
  });

  // pagehide — fires on Safari and all mobile browsers
  // Only fire when page is NOT going into bfcache (persisted=false means truly leaving)
  window.addEventListener('pagehide',function(e){
    if(S.step!=='done' && !e.persisted) GE.send();
  });

  // NOTE: visibilitychange is intentionally NOT used.
  // It fires on every tab switch and causes false positives.
  // The stale-lead server cron handles users who switch away and never return.
});

})();
</script>
