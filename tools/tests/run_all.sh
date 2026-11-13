#!/usr/bin/env bash
# Every mechanical check over the SEO skills, in one command.
#
#     bash tools/tests/run_all.sh
#
# Run it after changing render_report.py, the metric catalogue, the profile
# API's stage values, or anything a skill cites by path.
set -u
cd "$(dirname "$0")/../.."
fail=0

run() {
  printf '\n\033[1m── %s\033[0m\n' "$1"; shift
  "$@" || fail=$((fail + 1))
}

run "Skills are usable cold"        python3 tools/check_seo_skills.py
run "Skills match the portal code"  php     tools/check_skill_drift.php
run "Audit renderer + checker"      python3 tools/tests/test_audit_render.py
run "Rank grid"                     python3 tools/tests/test_rank_grid.py
run "Competitors on the grid"       python3 tools/tests/test_rivals.py
run "GA4 / Search Console"          php     tools/tests/test_ga4.php
run "Pull metrics precedence"       php     tools/tests/test_pull_metrics.php
run "MCP live integration reads"    php     tools/tests/test_mcp_live_metrics.php
run "Content publish gate"          python3 tools/tests/test_content_gate.py
run "Keyword / topic set shape"      python3 tools/tests/test_keyword_set.py
run "Strategy readability gate"      python3 tools/tests/test_strategy_language.py

if [ "$fail" -ne 0 ]; then
  printf '\n\033[31m%s suite(s) failed.\033[0m\n' "$fail"; exit 1
fi
printf '\n\033[32mAll suites passed.\033[0m\n'
