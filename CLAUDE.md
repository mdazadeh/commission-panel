# Project notes for Claude

- Language: user-facing text and docs in Persian (RTL). Code identifiers in English.
- Target: DirectAdmin shared hosting, no SSH, PHP 8.3, LiteSpeed; `exec`/`proc_open`/`shell_exec`/`popen` are DISABLED.
  - Never use Symfony Process, `Schedule::command`, `->exec()`, mysqldump, sendmail. Use `Schedule::call`/`job`, SMTP, PHP-native code.
  - No Redis/APCu: cache, session, queue use the database driver.
- National internet: no external URLs at runtime (no CDN, Google Fonts, reCAPTCHA, foreign APIs). Bundle every asset.
- Money: integer rial strings + `brick/math`; never floats. Rounding only where docs/rules-catalog.md says (D-06).
- Sales are attributed by Farapayam marketer code (`users.marketer_code`), never by name.
- `sales_documents.issued_on` is a plain 'Y-m-d' string on purpose (see model comment).
- Every change to financial data or users must go through `AuditLogger` (hash-chained, append-only).
- Business rules live in `docs/rules-catalog.md` and `database/data/rule-version-*.json`; don't guess rules — ask.
- Real Excel files with personal data must never be committed.
- Tests: `php artisan test`, `npm run typecheck`, `npm run build`.
