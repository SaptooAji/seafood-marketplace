#!/bin/sh
# Tests for .githooks/_areas.sh: which areas (backend, android) a set of
# changed files or a push touches. Plain POSIX sh, no dependencies.
# Run from anywhere: sh .githooks/tests/areas.sh
set -u

HOOKS="$(cd "$(dirname "$0")/.." && pwd)"
. "$HOOKS/_areas.sh"

FAILED=0
PASSED=0

# expect <description> <expected areas, space separated> <actual output>
expect() {
  actual=$(printf '%s\n' "$3" | grep -v '^$' | tr '\n' ' ' | sed 's/ $//')
  if [ "$actual" = "$2" ]; then
    PASSED=$((PASSED + 1))
  else
    FAILED=$((FAILED + 1))
    echo "FAIL: $1"
    echo "  expected: '$2'"
    echo "  actual:   '$actual'"
  fi
}

# --- areas_for_files: list of changed files -> areas -----------------------

files() { printf '%s\n' "$@" | areas_for_files; }

expect 'docs only' '' "$(files docs/prd.md)"
expect 'backend file' 'backend' "$(files backend/app/Actions/SaveProduct.php)"
expect 'android file' 'android' "$(files android/app/build.gradle.kts)"
expect 'backend and android' 'backend android' "$(files backend/routes/api.php android/app/src/main/AndroidManifest.xml)"
expect 'the CI workflow runs both' 'backend android' "$(files .github/workflows/ci.yml)"
expect 'a hook runs both' 'backend android' "$(files .githooks/pre-push)"
expect 'design/android is not android/' '' "$(files design/android/theme/Color.kt)"
expect 'CLAUDE.md and README' '' "$(files CLAUDE.md README.md)"
expect 'other workflow files' '' "$(files .github/dependabot.yml)"
expect 'a file name with spaces' 'backend' "$(files 'backend/resources/views/a file.blade.php')"
expect 'no files' '' "$(printf '' | areas_for_files)"
expect 'each area once' 'backend' "$(files backend/a.php backend/b.php backend/c.php)"

# --- push_areas: pre-push stdin -> areas ------------------------------------

REPO=$(mktemp -d)
trap 'rm -rf "$REPO"' EXIT
cd "$REPO" || exit 1
git init --quiet --initial-branch=main
git config user.email test@example.com
git config user.name Test
git config commit.gpgsign false
git config core.autocrlf false
git config tag.gpgsign false

commit() {
  mkdir -p "$(dirname "$1")"
  echo "$2" > "$1"
  git add -- "$1"
  git commit --quiet -m "$2"
  git rev-parse HEAD
}

ZERO=0000000000000000000000000000000000000000
BASE=$(commit README.md base)
git update-ref refs/remotes/origin/main "$BASE"

git switch --quiet -c docs-branch
DOCS=$(commit docs/notes.md docs)

git switch --quiet main
git switch --quiet -c backend-branch
BACKEND1=$(commit backend/app/One.php one)
BACKEND2=$(commit docs/two.md two)

git switch --quiet main
git switch --quiet -c android-branch
ANDROID=$(commit android/app/Main.kt android)

git switch --quiet main
git switch --quiet -c spaced-branch
SPACED=$(commit 'backend/a file.php' spaced)

git switch --quiet main
git tag -a v1.0.0 -m 'v1.0.0' "$BASE"
TAG=$(git rev-parse v1.0.0)

push() { printf '%s\n' "$@" | push_areas origin; }

expect 'new branch with docs only' '' \
  "$(push "refs/heads/docs-branch $DOCS refs/heads/docs-branch $ZERO")"
expect 'new branch with a backend commit' 'backend' \
  "$(push "refs/heads/backend-branch $BACKEND2 refs/heads/backend-branch $ZERO")"
expect 'existing branch: only the new commits count' '' \
  "$(push "refs/heads/backend-branch $BACKEND2 refs/heads/backend-branch $BACKEND1")"
expect 'existing branch with a backend commit' 'backend' \
  "$(push "refs/heads/backend-branch $BACKEND1 refs/heads/backend-branch $BASE")"
expect 'several refs at once: union of areas' 'backend android' \
  "$(push "refs/heads/backend-branch $BACKEND2 refs/heads/backend-branch $ZERO" \
          "refs/heads/android-branch $ANDROID refs/heads/android-branch $ZERO" \
          "refs/heads/docs-branch $DOCS refs/heads/docs-branch $ZERO")"
expect 'deleting a branch checks nothing' '' \
  "$(push "(delete) $ZERO refs/heads/old-branch $BACKEND1")"
expect 'a tag on a commit already in main' '' \
  "$(push "refs/tags/v1.0.0 $TAG refs/tags/v1.0.0 $ZERO")"
expect 'a file name with spaces in a push' 'backend' \
  "$(push "refs/heads/spaced-branch $SPACED refs/heads/spaced-branch $ZERO")"
expect 'nothing to push' '' "$(printf '' | push_areas origin)"

# Fail safe: when the comparison cannot be made, every check runs.
UNKNOWN=1234567890123456789012345678901234567890
expect 'unknown remote commit runs everything' 'backend android' \
  "$(push "refs/heads/docs-branch $DOCS refs/heads/docs-branch $UNKNOWN")"
git update-ref -d refs/remotes/origin/main
expect 'no origin/main for a new branch runs everything' 'backend android' \
  "$(push "refs/heads/docs-branch $DOCS refs/heads/docs-branch $ZERO")"

echo "areas: $PASSED passed, $FAILED failed"
[ "$FAILED" -eq 0 ]
