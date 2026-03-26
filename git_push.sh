#!/usr/bin/env bash
set -euo pipefail

# Usage:
#   ./git_push.sh
#   ./git_push.sh "my commit message"
#   ./git_push.sh "my commit message" origin main
#
# Defaults:
#   message = "sync local -> remote"
#   remote  = origin
#   branch  = current branch (or main if detached)

COMMIT_MESSAGE="${1:-sync local -> remote}"
REMOTE="${2:-origin}"
CURRENT_BRANCH="$(git rev-parse --abbrev-ref HEAD)"
if [[ "$CURRENT_BRANCH" == "HEAD" ]]; then
  CURRENT_BRANCH="main"
fi
BRANCH="${3:-$CURRENT_BRANCH}"

echo "==> Remote: ${REMOTE}"
echo "==> Branch: ${BRANCH}"

if ! git remote get-url "${REMOTE}" >/dev/null 2>&1; then
  echo "ERROR: remote '${REMOTE}' is not configured."
  echo "Hint: git remote add ${REMOTE} <repo-url>"
  exit 1
fi

echo "==> Fetch latest ${REMOTE}/${BRANCH}"
git fetch "${REMOTE}" "${BRANCH}" --prune

echo "==> Stage all changes"
git add -A

if git diff --cached --quiet; then
  echo "==> No staged changes to commit."
else
  echo "==> Commit: ${COMMIT_MESSAGE}"
  git commit -m "${COMMIT_MESSAGE}"
fi

LOCAL_HEAD="$(git rev-parse HEAD)"
REMOTE_HEAD="$(git rev-parse "${REMOTE}/${BRANCH}")"

echo "==> Push with lease"
echo "    local : ${LOCAL_HEAD}"
echo "    remote: ${REMOTE_HEAD}"
git push -u "${REMOTE}" "HEAD:${BRANCH}" --force-with-lease="${BRANCH}:${REMOTE_HEAD}"

echo "==> Done."
