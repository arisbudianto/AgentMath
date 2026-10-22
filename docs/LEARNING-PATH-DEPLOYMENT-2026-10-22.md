# Learning path kelas 1 — deployment 22 Oktober 2026

Application commit `b5cda27a2099b2ad5aec533006a6db940cd41c35` passed local tests (167 tests, 1744 assertions) and GitHub Actions run 39714693462. It was deployed to `/home/speakver/math.speakverse.id` from the Git checkout under the grading worker lock. A backup of replaced files and an `.env` checksum are in `/home/speakver/agentmath-backups/learning-path-b5cda27/`.

Migration `2026_10_22_140000_create_learning_paths` ran successfully (batch 15). Route and view caches were refreshed. The application returned to live mode; `.env` checksum matched and config cache remained mode 600. HTTPS health and home returned 200, unauthenticated `/student/path` redirected to login (302), and versioned CSS returned 200 with the learning-path styles. All six student path routes and two teacher configuration routes are registered.

Production bank remained one book, eight chapters, 242 pages and 86 sets. No existing production data was changed beyond schema installation. There are zero grade-1 classrooms and zero configured learning paths at deployment time. A teacher must create a grade-1 classroom, prepare separate pretest and post-test assessments with questions, and select them on the classroom's learning-path page before students can begin. The application then enforces pretest, Materials 1–8, and post-test per student.
