# Country flags with GeoLite2

DeVichan reads a local GeoLite2 Country or GeoIP2 Country MMDB through
`maxmind-db/reader`. Lookups support IPv4 and IPv6 and make no HTTP requests.
Only the database updater needs MaxMind credentials and network access.

This replaces the bundled legacy `GeoIPv6.dat`. Existing installations that use
country flags must run `composer install` and install an MMDB before deploying
this change. Flags already stored on posts remain unchanged.

## Install and download the database

Create a [MaxMind account](https://www.maxmind.com/en/create-account) and a
[license key](https://www.maxmind.com/en/accounts/current/license-key).
Install the official [geoipupdate program](https://github.com/maxmind/geoipupdate#installation)
on the host. Keep the credentials and database outside the repository and web root.

Create the database directory and edit the updater configuration:

```sh
sudo install -d -m 0755 /var/lib/GeoIP
sudoedit /etc/GeoIP.conf
```

Set these values in `/etc/GeoIP.conf`. Replace the account and key placeholders
with your MaxMind credentials:

```text
AccountID YOUR_ACCOUNT_ID
LicenseKey YOUR_LICENSE_KEY
EditionIDs GeoLite2-Country
DatabaseDirectory /var/lib/GeoIP
```

Protect the credentials and download the database:

```sh
sudo chmod 0600 /etc/GeoIP.conf
sudo geoipupdate -f /etc/GeoIP.conf -v
```

The PHP user needs read access to `GeoLite2-Country.mmdb` and access to its parent
directories. It does not need access to `/etc/GeoIP.conf`.

For Docker, append this directory mount to the PHP service's existing volumes
in your private Compose file:

```yaml
- /var/lib/GeoIP:/var/lib/GeoIP:ro
```

Mount the directory, not the individual MMDB file. The updater replaces the file
with a new inode. A directory mount exposes that replacement to the container.
Apply the mount through your normal deployment procedure before enabling flags.

## Enable flags

Set these options in the site's private configuration:

```php
$config['geoip_database'] = '/var/lib/GeoIP/GeoLite2-Country.mmdb';
$config['mod']['country_flags'] = true;
```

The database path above is the default. An absolute path refers to the PHP
filesystem. A relative path starts at the DeVichan repository root.

Moderator flags and public post flags are independent. Both are disabled by
default. Enable public flags separately with `$config['country_flags'] = true`.
That setting records the country on new posts. It does not change older posts.
The existing flag images and styles remain in use.

Unknown, private and invalid addresses have no country flag. A missing or
unreadable database also leaves flags absent and records a PHP error log entry.
Posting and moderator pages remain available. Broader ban ranges have no flag
because one country does not describe every address in a range.

## Check the database

Run this from the Compose project directory. It uses the PHP worker user to
test database access, print the build date and query one IPv4 and one IPv6 address:

```sh
docker compose exec -T --user www-data cphp php <<'PHP'
<?php
require '/var/www/vendor/autoload.php';
$reader = new MaxMind\Db\Reader('/var/lib/GeoIP/GeoLite2-Country.mmdb');
$meta = $reader->metadata();
printf("%s, built %s UTC\n", $meta->databaseType, gmdate('Y-m-d H:i:s', $meta->buildEpoch));
foreach (['8.8.8.8', '2001:4860:4860::8888'] as $ip) {
    printf("%s: %s\n", $ip, $reader->get($ip)['country']['iso_code'] ?? 'unknown');
}
$reader->close();
PHP
```

Use the configured database path if it differs from the example. Then open a
moderator page with a visible public IP and make sure that its flag appears.
The files under `tests/fixtures/geoip/` contain selected test records only.
Do not use them as the site's database.

## Keep the database current

Run the same `geoipupdate -f /etc/GeoIP.conf` command regularly through your host's
scheduler. A daily run is sufficient to collect new Country releases. Use the
installed executable's absolute path in the scheduled command and monitor failures.
No schedule is installed by DeVichan.

The official updater locks concurrent runs, downloads to a temporary file,
checks its checksum and atomically replaces the database. A failed download or
checksum leaves the previous database in place. Subsequent PHP web requests read
the replacement without a cache purge or image rebuild.
[Updater options](https://github.com/maxmind/geoipupdate/blob/main/doc/geoipupdate.md)
and [file replacement implementation](https://github.com/maxmind/geoipupdate/blob/main/internal/geoipupdate/database/local_file_writer.go).

GeoLite's license requires prompt updates and deletion of old copies within
30 days after a newer release. This includes database copies retained in backups.
Do not commit the downloaded database to Git.
[MaxMind update instructions](https://dev.maxmind.com/geoip/updating-databases/)
and [GeoLite license](https://www.maxmind.com/en/geolite/eula).

## Attribution

GeoLite requires attribution to MaxMind. Retain the GeoLite attribution in the
footer when flags are enabled. MaxMind's example is:

> This product includes GeoLite Data created by MaxMind, available from
> https://www.maxmind.com.

The database license is separate from the PHP reader's license. Review the
[GeoLite terms](https://www.maxmind.com/en/geolite/eula) before use or redistribution.
