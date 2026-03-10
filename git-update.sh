#!/bin/sh

echo "📥 Fetching updates from GitHub..."
git fetch origin

CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)

echo "🔀 Current branch: $CURRENT_BRANCH"

echo "⬇ Updating branch..."
git pull --rebase origin $CURRENT_BRANCH

echo "✅ Done."