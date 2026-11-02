# Host profile — Netafraz shared (checked 2026-11-02)

- Panel: DirectAdmin | Server: LiteSpeed (SAPI litespeed), mod_rewrite OK, HTTPS OK, TZ Asia/Tehran
- PHP 8.3.x web; CLI path: UNKNOWN (PHP_BINARY empty) -> verify via test cron
- Extensions: all Laravel-required OK incl. bcmath, intl, gd, zip, pdo_mysql, OPcache
- Missing: sodium, redis, apcu, imagick
- memory_limit 512M | max_execution_time 30 (set_time_limit enabled) | upload/post 64M | max_input_vars 10000
- Disabled: proc_open, exec, shell_exec, popen  (symlink, putenv, fsockopen enabled)
- open_basedir: /home/faranepa/domains/faranepanel.ir/ : /tmp/ : /usr/local/php-8.3/lib/php/
- Writable outside public_html: yes
- MySQL/MariaDB version: UNKNOWN | disk quota: UNKNOWN

## Design consequences
- Layout: app at /home/faranepa/domains/faranepanel.ir/commission_app ; public entry at public_html/commission
- Cache/session/queue: database driver (no redis/apcu)
- No Symfony Process anywhere at runtime: no mysqldump/composer/sendmail; scheduler uses in-process ->call()/->job()
- Queue: separate cron `artisan queue:work database --stop-when-empty --max-time=55`
- Mail: SMTP transport only
- Backup: PHP-native SQL dump (no mysqldump binary) + DirectAdmin backups
- Passwords: bcrypt (argon2 availability unknown)
- Excel import: OpenSpout streaming, chunked via queue
- Session cookie: name faran_commission_session, path /commission, Secure, HttpOnly, SameSite=Strict
