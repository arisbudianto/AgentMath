# PROJECT STATE
PROJECT: AgentMath / M00–M01
REPO: arisbudianto/AgentMath
BRANCH: main
TARGET: https://math.speakverse.id

## CONFIRMED
- Matematika SD; ketikan, CSV, JPG/PNG satu foto per jawaban/soal.
- Foto wajib konfirmasi transkripsi pengajar.
- DB/username speakver_math. Password hanya di server.
- Per modul: pengujian, GitHub, CI, cPanel, smoke.

## IMPLEMENTED
Laravel 13/PHP 8.3+; session auth, admin-created accounts, active-account guard, ownership policies, classrooms, coded participants, assessment packages, questions/keys/guidelines/max scores, derived completeness, server AI configuration readiness.
Admin does not bypass assessment ownership. No public registration or default passwords.

## VERIFICATION
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
- M01 COMPLETE. Manual production login with admin credentials not repeated; local browser and automated CI login tests PASS.
- AI provider/model/budget and empirical evaluation targets not selected; not blockers for M00.

## NEXT
M02 only when requested. Preserve server .env/APP_KEY and existing users. Do not request credentials in chat. Deployment evidence is separate from the application implementation commit. Production currently has an admin and no teaching accounts/classes; admin creates teacher accounts through Akun before using M01.
