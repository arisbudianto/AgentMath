# Safe deployment on cPanel shared hosting

This procedure is not automatic deployment. PR merge and production deployment require separate approvals.

## One-time worker cron prerequisite

The existing AgentMath-M04 cron invokes `flock -n /home/speakver/.agentmath-grading.lock` and `artisan agentmath:work`. Preserve its existing schedule (`*/7`) and output path. In cPanel Cron Jobs, add the following guard to the existing command immediately before `/usr/bin/flock`:

```sh
test ! -e /home/speakver/.agentmath-worker-paused &&
```

Example command body (not a replacement for the real cron log path):

```sh
test ! -e /home/speakver/.agentmath-worker-paused && /usr/bin/flock -n /home/speakver/.agentmath-grading.lock /bin/sh -c 'cd /home/speakver/math.speakverse.id && /usr/local/bin/php artisan agentmath:work'
```

Keep exactly one AgentMath-M04 entry and its original log redirect, schedule and other unrelated cron jobs. Back up crontab first. The deploy script verifies the pause guard before applying.

## Preflight

1. Confirm document root is the application `public/` directory.
2. Confirm exact CI-passed commit SHA, approved PR, clean checkout, correct production target, and compatible composer.lock/vendor.
3. Verify backup, independent restore drill, production .env retention, storage and rollback plan.
4. Confirm no schema change is needed. The script intentionally refuses differing composer.lock and never runs migrations.
5. Run `bash scripts/deploy-safe.sh --dry-run /home/speakver/agentmath-m00 /home/speakver/math.speakverse.id FULL_40_CHARACTER_APPROVED_SHA` and review all rsync deletions.
6. Only after explicit production approval, run with `AGENTMATH_DEPLOY_APPROVED_SHA=FULL_40_CHARACTER_APPROVED_SHA bash scripts/deploy-safe.sh --apply /home/speakver/agentmath-m00 /home/speakver/math.speakverse.id FULL_40_CHARACTER_APPROVED_SHA`.

## Failure handling

If the script fails after entering maintenance, it intentionally leaves the application down and keeps `~/.agentmath-worker-paused` present. Do not simply remove the marker or run `artisan up`. Recover code/dependencies from a verified release, confirm database compatibility, rebuild caches, smoke-test and only then clear marker and resume service under the same grading lock. The database backup is not automatically restored; new production data must be evaluated before any SQL restore.

## Limits

- The script is a guarded manual release mechanism, not CI/CD.
- It cannot guarantee that code/DB/storage are a consistent snapshot.
- It does not verify authenticated flows, HTTP identity or worker success.
- It does not handle dependency upgrades or migrations.
- The cPanel Git Deploy Manager remains unverified and should not be enabled automatically.
