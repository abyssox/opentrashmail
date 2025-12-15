# Changelog
## 1.8.1
- Don't include xdebug in docker production build

## 1.8.0
- Refactored whole PHP code and Project structure
- Use composer
- Added captcha to password.html.php and admin.html.php
- Added Timezone support
- Added logrotate feature
- send.py now supports parameters -plain -html -multipart -attachment
- Optimized docker image build file (added production and development builds)
- Add xdebug feature for better php debugging

## 1.7.1
- Added auto expire feature to email addresses (default: 15 minutes)
- Added CSRF check to admin login
- Added -plain, -html, -multipart, -attachment parameters to send.py
- Added automatic cache busting for static assets
- various code/styling improvements

## 1.7.0
- Moved to UIkit 3.24.2 as CSS framework

## 1.6.3
- Masked ADMIN_PASSWORD on logs page
- Added dark/light theme toggle

## 1.6.2
- Changed mailserver run user
 
## 1.6.1
- Added Autocheck (checks for new mails every 15 seconds)

## V1.6.0
- PHP upgraded to 8.4
- New docker base image php:8.4-fpm-alpine
- Code refactored in nearly every area
- Path traversal security vulnerabilities fixed in mailserver3.py
- Dependencies updated
    - updated Pico to 2.1.1
    - updated htmx to 2.0.7
    - updated PrismJS to 1.30.0
    - updated Font Awesome to 7.1.0
    - updated Moment.js to 2.30.1

## V1.5.0
- Added per-email webhook configuration with customizable JSON payloads
- Implemented webhook retry mechanism with exponential backoff
- Added HMAC signature support for webhook security
- Created web UI for webhook configuration management
- Maintained backward compatibility with global webhook configuration

## V1.4.0
- Added support for webhooks
- Moved account list and logs to admin site with optional passwords

## V1.3.0
- Added TLS and STARTTLS support
- Various bug fixes and docs updates

## V1.2.6
- Fixed link to raw email in RSS template
- Added version string to branding part of the nav
- Fixed bug with double "v" in the version string

## V1.2.3
- Fixed attachment deletion bug
- Fixed random email generation

## V1.2.0
 - Implemented IP/Subnet filter using the config option `ALLOWED_IPS`
 - Implemented Password authentication of the site and API using config option `PASSWORD`
 - Implemented max attachment size as mentioned in [#63](https://github.com/HaschekSolutions/opentrashmail/issues/63)
 - Reworked the navbar header to look better on smaller screens

## V1.1.5
- Added support for plaintext file attachments
- Updated the way attachments are stored. Now it's md5 + filename

## V1.1.4
- Fixed crash when email contains attachment

## V1.1.3
- Switched SMTP server to Python3 and aiosmptd
- Switched PHP backend to PHP8.1
- Implemented content-id replacement with smart link to API so embedded images will now work
- Updated JSON to include details about attachments (filename,size in bytes,id,cid and a download URL)
- Removed quotes from ini settings
- Made docker start script more neat

## V1.0.0
- Launch of V1.0.0
- Complete rewrite of the GUI
- Breaking: New API (/rss, /json, /api) instead of old `api.php` calls
