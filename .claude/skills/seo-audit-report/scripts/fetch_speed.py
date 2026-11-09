#!/usr/bin/env python3
"""
fetch_speed.py — measure a page's speed, as the `speed` block of an audit file.

    python fetch_speed.py https://example.com --key $PAGESPEED_API_KEY
    python fetch_speed.py https://example.com -o speed.json

Uses Google PageSpeed Insights on purpose rather than a local timing run. The
report's whole claim is that a client can check any figure in it themselves,
and PageSpeed is the thing they will check it against — pagespeed.web.dev is
the first result for "website speed test" and it is Google's own. A number
measured here that disagrees with the number they get there is worse than no
number at all, however technically defensible our own measurement was.

An API key is required in practice. The anonymous quota is shared across
everyone on the internet and is normally exhausted, so a keyless run mostly
returns HTTP 429. Keys are free: Google Cloud Console → APIs & Services →
Credentials → Create credentials → API key, then enable the PageSpeed Insights
API on that project. The portal stores one under
6ix Portal → Integrations → Google PageSpeed Insights.

Writes nothing and exits non-zero if the measurement fails. There is no
fallback estimate on purpose: a speed section is either measured or absent.
"""

import argparse
import json
import os
import sys
import urllib.error
import urllib.parse
import urllib.request

API = "https://www.googleapis.com/pagespeedonline/v5/runPagespeed"

# label, the PSI audit id, and how to render the value. The thresholds in the
# notes are Google's own published "good" bands, quoted so the client can see
# what the verdict is measured against rather than taking ours on trust.
METRICS = [
    ("First Contentful Paint", "first-contentful-paint",
     "When the first text or image appears. Good is under 1.8 s."),
    ("Largest Contentful Paint", "largest-contentful-paint",
     "When the main content has loaded. Good is under 2.5 s."),
    ("Total Blocking Time", "total-blocking-time",
     "How long the page ignores taps while it finishes loading. Good is under 200 ms."),
    ("Cumulative Layout Shift", "cumulative-layout-shift",
     "How much the page jumps about while loading. Good is under 0.1."),
    ("Speed Index", "speed-index",
     "How quickly the page visibly fills in. Good is under 3.4 s."),
]


def status_from(score):
    """PSI scores each metric 0-1. Its own banding, not ours."""
    if score is None:
        return "warning"
    if score >= 0.9:
        return "good"
    if score >= 0.5:
        return "warning"
    if score >= 0.25:
        return "serious"
    return "critical"


def fetch(url, key, strategy):
    params = {"url": url, "strategy": strategy, "category": "performance"}
    if key:
        params["key"] = key
    req = urllib.request.Request(
        API + "?" + urllib.parse.urlencode(params),
        headers={"Accept": "application/json", "User-Agent": "6ix-audit/1.0"},
    )
    with urllib.request.urlopen(req, timeout=120) as res:
        return json.loads(res.read().decode("utf-8"))


def build(payload, url, strategy):
    lh = payload.get("lighthouseResult") or {}
    audits = lh.get("audits") or {}
    cats = lh.get("categories") or {}

    perf = (cats.get("performance") or {}).get("score")
    score = round(perf * 100) if isinstance(perf, (int, float)) else None

    metrics = []
    for label, audit_id, note in METRICS:
        a = audits.get(audit_id) or {}
        display = a.get("displayValue")
        s = a.get("score")
        if display is None and s is None:
            continue                       # not measured; say nothing about it
        metrics.append({
            "label": label,
            "value": display if display is not None else "—",
            "ratio": s if isinstance(s, (int, float)) else None,
            "status": status_from(s if isinstance(s, (int, float)) else None),
            "note": note,
        })

    out = {
        "source": "Google PageSpeed Insights",
        "url": payload.get("id") or url,
        "strategy": strategy,
        "fetched": (lh.get("fetchTime") or "")[:10],
        "score": score,
        "score_note": f"Google's own score for this page on a {strategy} connection.",
        "metrics": metrics,
        "bounce_rate_unavailable": (
            "Bounce rate — how many visitors leave without going any further — is measured "
            "in your own Google Analytics, which we do not have access to. It is one of the "
            "answers that would most sharpen this audit."
        ),
    }

    # Field data: what real Chrome users actually experienced on this site over
    # the last 28 days. Only present for sites with enough traffic, and worth
    # far more than the lab run when it is there, because it is not a
    # simulation of anything.
    crux = payload.get("loadingExperience") or {}
    if crux.get("metrics"):
        out["field_data"] = True
        out["field_note"] = (
            "Real Chrome users visiting this site over the last 28 days, not a simulation."
        )
    return out


def main():
    ap = argparse.ArgumentParser(description="Measure a page with PageSpeed Insights.")
    ap.add_argument("url", help="The page to measure, e.g. https://example.com/")
    ap.add_argument("--key", default=os.environ.get("PAGESPEED_API_KEY", ""),
                    help="PageSpeed Insights API key (or set PAGESPEED_API_KEY).")
    ap.add_argument("--strategy", default="mobile", choices=["mobile", "desktop"],
                    help="Which device to measure as. Default mobile, which is how most "
                         "local-service traffic arrives.")
    ap.add_argument("-o", "--out", help="Write the JSON here instead of stdout.")
    args = ap.parse_args()

    if not args.key:
        print(
            "No PageSpeed API key. The anonymous quota is shared and normally exhausted, so a\n"
            "keyless run will almost certainly fail. Pass --key, or set PAGESPEED_API_KEY.\n"
            "Keys are free: Google Cloud Console → APIs & Services → Credentials → API key,\n"
            "then enable the PageSpeed Insights API. The portal keeps one under\n"
            "6ix Portal → Integrations → Google PageSpeed Insights.\n"
            "Trying anyway…",
            file=sys.stderr,
        )

    try:
        payload = fetch(args.url, args.key, args.strategy)
    except urllib.error.HTTPError as exc:
        body = exc.read().decode("utf-8", "replace")[:400]
        if exc.code == 429:
            print("PageSpeed refused the request: quota exhausted (HTTP 429). This is what a\n"
                  "keyless run normally returns. Use an API key.", file=sys.stderr)
        else:
            print(f"PageSpeed returned HTTP {exc.code}: {body}", file=sys.stderr)
        return 1
    except urllib.error.URLError as exc:
        print(f"Could not reach PageSpeed: {exc.reason}", file=sys.stderr)
        return 1
    except (TimeoutError, json.JSONDecodeError) as exc:
        print(f"PageSpeed did not return a usable result: {exc}", file=sys.stderr)
        return 1

    speed = build(payload, args.url, args.strategy)
    if speed.get("score") is None and not speed.get("metrics"):
        print("PageSpeed returned no performance data for that URL — nothing to report. "
              "Leave the speed section out rather than filling it.", file=sys.stderr)
        return 1

    text = json.dumps(speed, indent=2)
    if args.out:
        with open(args.out, "w", encoding="utf-8") as fh:
            fh.write(text + "\n")
        print(f"Wrote {args.out}. Put this object under `speed` in the audit file.")
    else:
        print(text)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
