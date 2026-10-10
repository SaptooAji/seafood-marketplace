# Shared helper: find Composer in Git Bash on Windows (Herd ships composer.bat),
# macOS/Linux, and CI.
composer_run() {
  if command -v composer >/dev/null 2>&1; then
    composer "$@"
  elif command -v composer.bat >/dev/null 2>&1; then
    composer.bat "$@"
  elif command -v cmd.exe >/dev/null 2>&1; then
    cmd.exe //c composer "$@"
  else
    echo "hook: Composer not found in PATH" >&2
    return 127
  fi
}
