#!/usr/bin/env bash
# Rechat plugin release flow.
#
# Usage:
#   1. Bump the "Version:" header AND RCH_VERSION in index.php (same value).
#   2. Run: ./release.sh
#
# Reads the version from index.php, then:
#   - commits any staged/unstaged changes (if a commit message is given or version changed)
#   - pushes master to origin
#   - creates a GitHub release  tag=v<version>  name="Version <version>"
#
set -euo pipefail

REPO="rechat/rechat-wp"
BRANCH="master"

# --- read version from index.php header ---
VERSION="$(grep -m1 -E '^\s*Version:' index.php | sed -E 's/.*Version:[[:space:]]*//' | tr -d '[:space:]')"
if [[ -z "$VERSION" ]]; then
  echo "ERROR: could not read Version from index.php" >&2
  exit 1
fi
TAG="v${VERSION}"
NAME="Version ${VERSION}"

echo "==> Version:  $VERSION"
echo "==> Tag:      $TAG"
echo "==> Name:     $NAME"

# --- abort if tag already exists remotely ---
if git ls-remote --tags origin "refs/tags/${TAG}" | grep -q "$TAG"; then
  echo "ERROR: tag $TAG already exists on origin. Bump the version first." >&2
  exit 1
fi

# --- commit if there are changes ---
if ! git diff --quiet || ! git diff --cached --quiet; then
  git add -A
  git commit -m "Release ${TAG}"
fi

# --- push and release ---
git push origin "$BRANCH"
gh release create "$TAG" --repo "$REPO" --target "$BRANCH" --title "$NAME" --generate-notes

echo "==> Released: https://github.com/${REPO}/releases/tag/${TAG}"
