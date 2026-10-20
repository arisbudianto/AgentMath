# PROJECT STATE
PROJECT: AgentMath / M00–M03A
REPO: arisbudianto/AgentMath
BRANCH: main
TARGET: https://math.speakverse.id

## CONFIRMED
- Matematika SD; ketikan, CSV, JPG/PNG satu foto per jawaban/soal.
- Foto wajib konfirmasi transkripsi pengajar.
- DB/username speakver_math. Password hanya di server.
- Per modul: pengujian, GitHub, CI, cPanel, smoke.

## IMPLEMENTED
M03A: photo transcription manual/optional vision draft, preserved uncertainties, explicit teacher confirmation, append-only version history, stale-photo/concurrency checks, readyText gate for future grading. AI_TRANSCRIPTION_ENABLED defaults false. Local 62 tests / 538 assertions PASS, browser manual draft/confirmation with synthetic image PASS, migration upgrade preserves earlier data. GitHub/CI/deployment pending until recorded below. See docs/M03A.md.
M03: typed answers, CSV preview/confirmation with revalidation and atomic commit, private JPG/PNG, immutable answer revisions, source/participant snapshots, duplicate checks and import expiry cleanup. No AI grading/transcription. See docs/M03.md.
M02: source-linked rules, manual drafts, optional OpenAI A1/A2 structured drafts, citation/reference/score validation, optimistic draft revision, source snapshots/hash, immutable approved versions, copy lineage and current matching approved rubric. See docs/M02.md.
Laravel 13/PHP 8.3+; session auth, admin-created accounts, active-account guard, ownership policies, classrooms, coded participants, assessment packages, questions/keys/guidelines/max scores, derived completeness, server AI configuration readiness.
Admin does not bypass assessment ownership. No public registration or default passwords.

## VERIFICATION
M03 local PHP 8.5.11: 55 tests / 437 assertions PASS; browser typed/CSV upload-preview-confirm/photo display/revision PASS after configuring writable upload_tmp_dir. Upgrade M00→M01→M02→M03 preserves prior data.
M03 application 2d3ad3b4411df21441cc646b9f04ef090808824a: CI 39508247022 PHP 8.3/8.4 SQLite/MariaDB SUCCESS. cPanel migration batch 4 Ran, cache rebuilt, live. Production transactional text/revisions, CSV commit/replay and real private PNG/authorized streaming PASS, foreign owner 403. Fixtures rolled back and test files deleted. .env/APP_KEY unchanged. File 600/directory 700; PHP CLI upload 16M/post 32M/temp writable. Evidence docs/M03-DEPLOYMENT-2026-10-21.md.
M02 local: 35 tests / 274 assertions PASS on PHP 8.5.11, syntax/manifest/views/routes PASS. Browser manual draft/edit/add-remove row/save/approve PASS. AI HTTP mocked; live AI API NOT tested/configured. Isolated M00→M01→M02 upgrade preserves existing account/assessment/owner_id.
M02 application 26d2de3bfde131842e73b3dc96bffeff32460191: CI 39502874052 PHP 8.3/8.4 SQLite/MariaDB SUCCESS. cPanel migrated batch 3, caches rebuilt, live. Production transactional workflow/locking/lineage/source-staleness and HTTP ownership smoke PASS; fixtures rolled back. .env/APP_KEY unchanged. AI_ENABLED=false, key/model incomplete and unchanged. Evidence: docs/M02-DEPLOYMENT-2026-10-21.md.
M01 local: PHP 8.5.11; 22 tests / 173 assertions PASS, manifest/syntax/views/routes PASS.
Browser local: teacher login, class, normalized participant code, assessment and complete question package PASS.
Isolated SQLite upgrade M00→M01 preserves existing account, assessment and owner_id.
CI b91a941: PASS SQLite and MariaDB, each 11 tests / 56 assertions; syntax, views, routes PASS.
composer.lock retrieved from successful CI artifact 12268863131. CI c2ece03: PASS all steps including SQLite/MariaDB, views and routes.
Run: https://github.com/arisbudianto/AgentMath/actions/runs/39433318337
Later documentation-only commits do not invalidate this code verification.
M00 cPanel deployed at 5604c2d; PHP 8.4.26, document root public, migrations/cache successful, one active admin. HTTPS login/health and guest access checks PASS. See docs/M00-DEPLOYMENT-2026-10-20.md.
M01 application commit 1a569704dbd4873aa61294787c75f6eef3d6cebd: GitHub CI PASS on PHP 8.3/8.4, SQLite/MariaDB. Run 39496133473. Deployed to cPanel; migration batch 2 Ran, caches rebuilt, live. Transactional production runtime smoke and cross-owner denial PASS; all fixtures rolled back, original admin retained. .env/APP_KEY unchanged. See docs/M01-DEPLOYMENT-2026-10-21.md for backup and recovery.

## OPEN
- M03 COMPLETE. Production browser multipart not repeated with credentials; browser local and production runtime storage/streaming PASS. CSV preview cleanup has on-demand and CLI paths; host cron not installed.
- M02 COMPLETE for manual workflow and optional AI adapter. Real AI requests remain unverified until server key/model are configured; do not enable automatically.
- M01 COMPLETE. Manual production login with admin credentials not repeated; local browser and automated CI login tests PASS.
- AI provider/model/budget and empirical evaluation targets not selected; not blockers for M00.

## NEXT
M03A transcription only when requested; photos require teacher-confirmed transcription before future grading. Preserve .env/APP_KEY/storage and answer/rubric history. Never request credentials in chat. Admin creates teaching accounts via Akun. M02 real AI activation still requires server configuration and a separate live-service check. Continue test → GitHub → CI → cPanel → smoke per module.
