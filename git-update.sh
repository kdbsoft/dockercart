#!/bin/sh

BRANCH=$(git rev-parse --abbrev-ref HEAD)

echo "Current branch: $BRANCH"

git fetch origin

LOCAL=$(git rev-parse @)
REMOTE=$(git rev-parse origin/$BRANCH)

if [ "$LOCAL" = "$REMOTE" ]; then
    echo "✅ Already up to date"
else
    echo "⬇ Pulling updates..."
    git pull --rebase origin $BRANCH
    echo "🚀 Updated successfully"
fi