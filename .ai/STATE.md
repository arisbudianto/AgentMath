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

## OPEN
- M01 GitHub push, CI PHP 8.3/8.4 with SQLite/MariaDB, then cPanel deployment and smoke pending at this commit.
- AI provider/model/budget and empirical evaluation targets not selected; not blockers for M00.

## NEXT
Finish M01 push → CI → backup/deploy additive migration → smoke. Preserve server .env/APP_KEY and existing users. Do not request credentials in chat. Follow-up deployment evidence may be recorded separately from the application commit.
