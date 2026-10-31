#!/usr/bin/env bash
# Isolated integration tests. Never touches the host's real crontab or application.
set -euo pipefail
ROOT="$(mktemp -d)"
trap 'rm -rf "$ROOT"' EXIT
mkdir -p "$ROOT"/{bin,source,target,home}
export HOME="$ROOT/home"
export PATH="$ROOT/bin:$PATH"
git -C "$ROOT/source" init -q
git -C "$ROOT/source" config user.email test@example.invalid
git -C "$ROOT/source" config user.name test
printf '#!/bin/sh\nexit 0\n' > "$ROOT/source/artisan"
printf 'same\n' > "$ROOT/source/composer.lock"
mkdir -p "$ROOT/target/vendor" "$ROOT/target/storage"
touch "$ROOT/target/vendor/autoload.php"
cp "$ROOT/source/artisan" "$ROOT/target/artisan"
cp "$ROOT/source/composer.lock" "$ROOT/target/composer.lock"
echo secret > "$ROOT/target/.env"
echo persistent > "$ROOT/target/storage/keep"
echo remove-me > "$ROOT/target/obsolete"
git -C "$ROOT/source" add . && git -C "$ROOT/source" commit -qm test
# Linked worktrees use a .git file, not a .git directory.
git -C "$ROOT/source" worktree add -q --detach "$ROOT/linked" HEAD
SOURCE="$ROOT/linked"
# An untracked vendor symlink must not make the checkout dirty or transfer to production.
mkdir -p "$ROOT/shared-vendor"
touch "$ROOT/shared-vendor/autoload.php"
ln -s "$ROOT/shared-vendor" "$SOURCE/vendor"
test "$(git -C "$SOURCE" status --porcelain)" = "?? vendor"
SHA="$(git -C "$SOURCE" rev-parse HEAD)"
export AGENTMATH_DEPLOY_APPROVED_SHA="$SHA"
cat > "$ROOT/bin/crontab" <<EOF
#!/bin/sh
echo '*/7 * * * * test ! -e $HOME/.agentmath-worker-paused && /usr/bin/flock -n $HOME/.agentmath-grading.lock /bin/sh -c worker # AgentMath-M04'
EOF
chmod +x "$ROOT/bin/crontab"
cat > "$ROOT/bin/php" <<'EOF'
#!/bin/sh
if [ "${FAIL_AT:-}" = "${2:-}" ]; then exit 17; fi
printf '%s\n' "${2:-}" >> "$HOME/artisan-commands"
EOF
chmod +x "$ROOT/bin/php"
SCRIPT="$(cd "$(dirname "$0")" && pwd)/deploy-safe.sh"
bash "$SCRIPT" --dry-run "$SOURCE" "$ROOT/target" "$SHA" > "$ROOT/dryrun"
test -f "$ROOT/target/obsolete"
test ! -e "$HOME/.agentmath-worker-paused"
! grep -qE "(^|/)vendor|(^|/)\.git([ /]|$)" "$ROOT/dryrun"
test ! -e "$ROOT/target/.git"
# Invalid approval must reject before any mutation.
if AGENTMATH_DEPLOY_APPROVED_SHA=bad bash "$SCRIPT" --apply "$ROOT/source" "$ROOT/target" "$SHA" >/dev/null 2>&1; then exit 1; fi
test ! -e "$HOME/.agentmath-worker-paused"
# Failure during maintenance must leave worker paused.
if FAIL_AT=config:cache bash "$SCRIPT" --apply "$ROOT/source" "$ROOT/target" "$SHA" >/dev/null 2>&1; then exit 1; fi
test -e "$HOME/.agentmath-worker-paused"
test -f "$ROOT/target/.env"
test -f "$ROOT/target/storage/keep"
test -f "$ROOT/target/vendor/autoload.php"
test ! -L "$ROOT/target/vendor"
test ! -f "$ROOT/target/obsolete"
# Failure state must reject retry.
if bash "$SCRIPT" --apply "$ROOT/source" "$ROOT/target" "$SHA" >/dev/null 2>&1; then exit 1; fi
rm "$HOME/.agentmath-worker-paused"
: > "$HOME/artisan-commands"
bash "$SCRIPT" --apply "$ROOT/source" "$ROOT/target" "$SHA" >/dev/null
test ! -e "$HOME/.agentmath-worker-paused"
grep -Fxq up "$HOME/artisan-commands"
test -f "$ROOT/target/.env"
test -f "$ROOT/target/storage/keep"
echo 'ISOLATED_DEPLOY_TESTS_PASS'
