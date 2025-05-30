# HTTPS Requests from PHP

Some HTTPS and HTTP/2 request tests with curl and PHP.

## Setup & Run

1. Install Composer (for example in `./bin`).
2. `php bin/composer.phar install`
3. `php src/curl.php`

## curl error "SSL certificate problem: unable to get local issuer certificate"

Download `cacert.pem` from https://curl.se/docs/caextract.html and direct curl to where it can find it, for example via `php.ini`:

```ini
# php.ini

[curl]
curl.cainfo=C:\Users\YOUR_HOME\scoop\apps\cacert\current\cacert.pem
```
