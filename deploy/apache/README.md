# Apache deployment

`tekg.conf` adds TE-KG at `/TE-/` without modifying the existing `zjubio`
virtual host or its aliases.

After PHP 8.4 and the required extensions are installed, an administrator can
enable the configuration with:

```bash
sudo cp /app/tekg/app/deploy/apache/tekg.conf /etc/apache2/conf-available/tekg.conf
sudo a2enconf tekg
sudo apache2ctl configtest
sudo systemctl reload apache2
```

The application requires PHP extensions for `curl`, `mbstring`, and
`pdo_mysql`. The configuration intentionally disables directory listings,
allows the runtime-data symbolic links, and blocks direct HTTP access to
machine-local configuration files and dotfiles.

Before enabling the site, grant the Apache `www-data` account read and
directory-traversal access to `/app/tekg/app` and `/data/tekg/runtime`. Do not
grant write access to the runtime datasets or server-local secrets.
