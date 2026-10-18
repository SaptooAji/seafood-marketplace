# Shared helper: which areas (backend, android) a set of changed files or a
# push touches. Used by the pre-push hook and by the `changes` job in
# .github/workflows/ci.yml, so both decide the same way (#252).
#
# A change to the CI workflow or to a hook runs every area. Anything else
# outside backend/ and android/ (docs, design, CLAUDE.md) runs nothing.

# Print each area the changed files touch, once, in a fixed order. Reads one
# path per line from stdin.
areas_for_files() {
  _backend=false
  _android=false
  while IFS= read -r _file; do
    case $_file in
      backend/*) _backend=true ;;
      android/*) _android=true ;;
      .github/workflows/ci.yml | .githooks/*)
        _backend=true
        _android=true
        ;;
    esac
  done
  if [ "$_backend" = true ]; then echo backend; fi
  if [ "$_android" = true ]; then echo android; fi
  return 0
}

# Print the areas a push touches. Reads the pre-push lines from stdin
# (`<local ref> <local sha> <remote ref> <remote sha>`, see githooks(5)) and
# takes the remote name as $1. A new branch is compared with <remote>/main, a
# deleted ref is skipped. When a comparison cannot be made, every area is
# printed, so a failure here never skips the checks.
push_areas() {
  _remote=${1:-origin}
  _changed=''
  while read -r _local_ref _local_sha _remote_ref _remote_sha; do
    [ -z "$_local_sha" ] && continue
    _is_zero "$_local_sha" && continue

    if _is_zero "$_remote_sha"; then
      _base=$(git merge-base "$_remote/main" "$_local_sha^{commit}" 2>/dev/null) || { _all_areas; return 0; }
    else
      _base=$_remote_sha
    fi

    git cat-file -e "$_base^{commit}" 2>/dev/null || { _all_areas; return 0; }
    _files=$(git -c core.quotePath=false diff --name-only "$_base" "$_local_sha^{commit}" 2>/dev/null) || { _all_areas; return 0; }
    _changed="$_changed
$_files"
  done
  printf '%s\n' "$_changed" | areas_for_files
}

_is_zero() {
  case $1 in
    *[!0]*) return 1 ;;
    *) return 0 ;;
  esac
}

_all_areas() {
  echo backend
  echo android
}
