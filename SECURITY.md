# Security

## Reporting a vulnerability

Email **security@oogle.com** with a description, affected version and steps to reproduce.
Do not open a public issue for security reports. You will get an acknowledgement within
three business days and a fix or mitigation plan within fourteen days for confirmed issues.

## Supported versions

The latest minor release of the current major version receives security fixes. Older
majors receive fixes only while a client site still runs them.

## What the theme does and does not do

The theme is presentation. It handles no form submissions, no AJAX or REST endpoints, no
database queries of its own, no file uploads and no user input. Its only network activity
is `inc/updates.php`, which reads the public GitHub Releases API over HTTPS (certificate
verified, 10 s timeout, every field validated) to answer WordPress's own update check.
No credential is stored in or read by the theme; an optional `OOGLE_GITHUB_TOKEN`
constant in `wp-config.php` only raises the API rate limit.

Security controls that belong to other layers are intentionally absent: authentication
hardening, login throttling, REST restrictions, HTTP security headers, WAF rules, file
permissions and version hiding are the job of the server, CDN or a dedicated plugin.
