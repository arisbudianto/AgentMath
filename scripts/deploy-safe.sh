#!/usr/bin/env bash
# AgentMath shared-host deploy gate. No automatic migrations, Git pull or Composer changes.
# Usage: bash scripts/deploy-safe.sh --dry-run SOURCE TARGET EXPECTED_SHA
#        bash scripts/deploy-safe.sh --apply SOURCE TARGET EXPECTED_SHA
set -Eeuo pipefail
umask 077

mode="${1:-}"; source_dir="${2:-}"; target_dir="${3:-}"; expected_sha="${4:-}"
if [[ "$mode" != "--dry-run" && "$mode" != "--apply" ]] || [[ -z "$source_dir" || -z "$target_dir" || ! "$expected_sha" =~ ^[a-f0-9]{40}$ ]]; then
  echo "Usage: $0 --dry-run|--apply SOURCE TARGET EXPECTED_40_CHAR_SHA" >&2
  exit 2
fi
source_dir="$(cd "$source_dir" && pwd -P)"
target_dir="$(cd "$target_dir" && pwd -P)"
[[ "$source_dir" != "$target_dir" ]] || { echo "Source and target must differ" >&2; exit 1; }
[[ -f "$source_dir/artisan" && -f "$target_dir/artisan" && -f "$target_dir/.env" ]] || { echo "Laravel source/target or production .env missing" >&2; exit 1; }
[[ "$(git -C "$source_dir" rev-parse HEAD)" == "$expected_sha" ]] || { echo "Source commit does not match approved SHA" >&2; exit 1; }
[[ -z "$(git -C "$source_dir" status --porcelain)" ]] || { echo "Source checkout is dirty" >&2; exit 1; }
[[ -f "$source_dir/vendor/autoload.php" && -f "$target_dir/vendor/autoload.php" ]] || { echo "Dependencies missing: install/verify separately before deployment" >&2; exit 1; }
cmp -s "$source_dir/composer.lock" "$target_dir/composer.lock" || { echo "composer.lock differs; dependency changes require separate reviewed procedure" >&2; exit 1; }
[[ -n "${AGENTMATH_DEPLOY_APPROVED_SHA:-}" && "${AGENTMATH_DEPLOY_APPROVED_SHA}" == "$expected_sha" || "$mode" == "--dry-run" ]] || { echo "Apply requires AGENTMATH_DEPLOY_APPROVED_SHA matching expected commit" >&2; exit 1; }

excludes=(
  --exclude='/.git/' --exclude='/.github/' --exclude='/.gitignore'
  --exclude='/.env' --exclude='/.env.*' --exclude='/storage/'
  --exclude='/bootstrap/cache/' --exclude='/vendor/'
  --exclude='/public/.htaccess' --exclude='/public/php.ini'
  --exclude='/public/.user.ini' --exclude='/public/.well-known/'
)
rsync_flags=(-rlt --delete --itemize-changes "${excludes[@]}")
if [[ "$mode" == "--dry-run" ]]; then
  echo "DRY RUN only; review all removals, document root, backup and migrations before apply"
  rsync "${rsync_flags[@]}" --dry-run "$source_dir/" "$target_dir/"
  exit
fi

# Must use same OS lock as the AgentMath-M04 worker. Keep FD open until app is up.
lock_file="${AGENTMATH_DEPLOY_LOCK:-$HOME/.agentmath-grading.lock}"
exec 9>"$lock_file"
flock -w 60 9 || { echo "Grading worker/deployment lock unavailable" >&2; exit 1; }
cd "$target_dir"
maintenance=0
cleanup() {
  status=$?
  trap - EXIT
  if [[ "$maintenance" == 1 ]]; then
    echo "Deployment interrupted; application intentionally left in maintenance. Worker lock releases on exit." >&2
    echo "Restore approved code/dependencies, verify, then run artisan up manually after review." >&2
  fi
  exit "$status"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

# No migration: schema changes must be separately reviewed and approved.
php artisan down --retry=60
maintenance=1
rsync "${rsync_flags[@]}" "$source_dir/" "$target_dir/"
php artisan config:cache
php artisan view:cache
php artisan route:list --except-vendor >/dev/null
php artisan up
maintenance=0
echo "DEPLOY_COMMANDS_PASS commit=$expected_sha"
echo "HTTP identity, ownership smoke, worker and document root still require external verification."
