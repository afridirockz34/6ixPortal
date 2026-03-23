#!/bin/bash
# Run this script on your LOCAL machine to push the 6ix Portal to GitHub.
# Prerequisites: git installed locally.
#
# Steps:
#   1. Download the 6ixPortal folder from Claude's outputs
#   2. Place this script inside it
#   3. Run: bash push-to-github.sh

TOKEN="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"
REPO="https://${TOKEN}@github.com/afridirockz34/6ixPortal.git"

echo "Setting remote origin..."
git remote add origin "$REPO" 2>/dev/null || git remote set-url origin "$REPO"

echo "Pushing to GitHub..."
git push -u origin main

echo ""
echo "✅ Done! View your repo at: https://github.com/afridirockz34/6ixPortal"
