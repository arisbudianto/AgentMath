# AgentMath production deployment gate (pending verification)

This is a review checklist, not an executable deployment or authorization to deploy.

## Verified on 2026-10-30

- Canonical checkout: `/home/speakver/agentmath-m00`, branch `main`, commit `c0751fb`, clean.
- Production application: `/home/speakver/math.speakverse.id`, not a Git checkout.
- Production health endpoint returned `{"app":"AgentMath","status":"ok"}`; serving commit and document root are not verified.
- Worker cron: exactly one `AgentMath-M04` entry, scheduled `*/7`, uses `/home/speakver/.agentmath-grading.lock`.
- Worker log exists, mode 600, mtime 2026-10-30 21:56 WIB. Successful processing is not verified.
- Backup directory `/home/speakver/agentmath-backups/predeploy-20261030-215243` mode 700:
  - application.tar.gz, storage.tar.gz, database.sql all SHA256 verified
  - archive listing verified; SQL dump has 41 CREATE TABLE and 19 INSERT statements
  - database restore NOT VERIFIED; snapshot consistency across database and storage NOT VERIFIED
  - .env and vendor are excluded from the backup; recovery of environment and dependencies NOT VERIFIED
- GitHub CI passed on PR #1 head `8b7f5b10c131ce66ca46a443f60ded8a0c2f811b` for PHP 8.3/8.4.
- Inbound SSH from GitHub Actions on ports 22/2222 failed TCP reachability tests. Other ports NOT VERIFIED.
- Git Deploy Manager shows Never deployed; its target/behavior NOT VERIFIED.

## Blocking risks

1. `scripts/deploy.sh` holds the worker flock only during rsync, releasing it before cache refresh, maintenance completion, and smoke tests. Do not use the script alone as a complete production procedure.
2. `scripts/deploy.sh` excludes vendor/ and does not install production dependencies, fetch Git, or run migrations.
3. The source checkout must match the exact approved, CI-passed commit and have a clean worktree; do not pull or deploy an unapproved main HEAD.
4. Before any rsync with --delete, inspect a dry-run for removals and protect cPanel-managed files, .env, storage, and uploads.
5. Verify web document root, production release identity, worker health, database migration compatibility, backup restore, and a code rollback route.
6. A code rollback does not reverse schema/data changes. Database restore may overwrite newer production data.

## Required deployment design

- Use an explicit, reviewed production release commit and separate approval for production deployment.
- Acquire the *same* OS grading lock before entering maintenance, and hold it across synchronization, dependency preparation/verification, migrations (if separately approved), cache refresh, smoke checks, and exit from maintenance.
- Ensure all failure paths leave the application in a known safe state and do not release the worker lock while partially updated code could be used.
- Avoid nested acquisition of the same flock in `scripts/deploy.sh`: implement and test a single-owner locking contract before executing.
- Prefer a separate deploy orchestrator with shell tests using stubbed artisan, rsync and flock. Do not enable automatic push-to-production deployment.
- Keep a recovery plan for prior code and dependency versions, persistent storage, .env, and database, with a restore drill on an isolated database.

## Next action

Implement and test the single-owner deployment orchestrator on a separate branch. No production action is authorized by this document.
