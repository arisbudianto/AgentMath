# M14 deployment and public asset repair

Application b27820b592accd0c238053b40d209847dad08408 deployed after CI39681940872 passed. Local suite:165 tests/1578 assertions. Backup: `/home/speakver/agentmath-backups/m14-b27820b/`. Deployment held the worker OS lock and preserved `.env`; no new migrations.

Production transactional smoke passed M05–M14, including private bank gates, home/login separation and queue isolation. Fixtures rolled back and temporary photos removed.

User PDF exposed a missed deployment check: new public assets inherited private permissions from umask077 (files600, images directory700). PHP view checks and file hash checks passed, but static HTTP delivery failed. Corrected only home.css/home.js/math-garden.svg to644 and public/images to755. Private book storage, secrets and cached configuration retain private permissions.

Verified actual page URLs: home.css?v=m14 HTTP200 text/css20653bytes; home.js HTTP200 text/javascript8377bytes; images/math-garden.svg HTTP200 image/svg+xml5551bytes. Unversioned CSS initially retained a cached404, while the versioned URL used by the page returned200. Browser production hero now shows the pastel layout and original illustration.

Future public synchronization must run `bash scripts/verify-public-home.sh /home/speakver/math.speakverse.id https://math.speakverse.id` before declaring success. This narrowly scoped helper fixes these public asset permissions and validates HTTPS status, content type and nonempty responses. It does not change private storage permissions. Browser visual checks remain required.
