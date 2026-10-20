# PROJECT STATE
PROJECT: AgentMath / M00
REPO: arisbudianto/AgentMath
BRANCH: main
TARGET: https://math.speakverse.id

## CONFIRMED
- Matematika SD; ketikan, CSV, JPG/PNG satu foto per jawaban/soal.
- Foto wajib konfirmasi transkripsi pengajar.
- DB/username speakver_math. Password hanya di server.
- Per modul: pengujian, GitHub, CI, cPanel, smoke.

## IMPLEMENTED
Laravel 13/PHP 8.3; session auth, admin-created accounts, active-account guard, owner policy, minimal assessment title CRUD, server AI configuration readiness, feature tests and CI SQLite/MariaDB.
Admin does not bypass assessment ownership. No public registration or default passwords.

## VERIFICATION
Local PHP runtime/dependencies unavailable. Tests NOT VERIFIED locally.
CI and deployment status must be checked against actual commit.
No production changes made.

## OPEN
- Retrieve composer.lock CI artifact, commit it, rerun CI.
- cPanel authenticated access, PHP, document root, HTTPS, DB schema/grants.
- AI provider/model/budget and empirical evaluation targets not selected; not blockers for M00.

## NEXT
Push M00, inspect CI, correct failures, persist lockfile, then production preflight if access available.
