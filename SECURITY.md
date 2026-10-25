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
verified, 10 s timeout, redirects never followed, every field validated) to answer
WordPress's own update check. The update package is accepted only when its URL is
GitHub's own release-asset URL for the repository named in `Update URI`
(`https://github.com/<owner>/<repo>/releases/download/<tag>/<asset>.zip`, exact host);
anything else means no update is offered. No credential is stored in or read by the
theme; an optional `OOGLE_GITHUB_TOKEN` constant in `wp-config.php` only raises the API
rate limit and is sent to `api.github.com` alone — never to another host, a filtered URL
or a redirect target.

Before the installed parent is replaced, three gates run on every upgrader path, including
the ones where core's own `check_package()` does not run, as long as the theme is in use
(the active theme or the parent of the active child). They are this theme's code: a copy that
is installed but not in use is not loaded, so updating it (Appearance → Themes, Oogle Manager)
gets only what that tool checks — Manager verifies the release's SHA-256, not the Ed25519
signature. Do not keep stale unused copies on production sites.

1. **Integrity** (`upgrader_pre_download`): the release must publish a `.sha256` sidecar and
   the downloaded package must match it.
2. **Authenticity** (`upgrader_pre_download`, since 1.2.0): the release must publish a `.sig`
   asset — an Ed25519 signature over the package bytes — and it must verify against a public
   key pinned in `inc/updates.php`. The private key lives only with the maintainer, never in
   the repository, in GitHub or in CI (`tools/sign-release.sh` signs an archive reproduced from
   the tag after the Release workflow has published it).
3. **Identity** (`upgrader_source_selection`): the extracted package must be this theme
   (name, no `Template` header, canonical `Update URI`) at exactly the expected version, with
   declared and compatible requirements, a valid `theme.json`, `templates/index.html` and no
   symbolic links. A rejected package is removed from `wp-content/upgrade/`.

Only releases of the installed major are offered; a package older than the installed version
is never installed.

## Trust model of the updater

| Who could tamper | What stops them |
|---|---|
| An attacker on the network between the site and GitHub | HTTPS with certificate verification on every request (a filter cannot switch it off), redirects never followed on API and metadata requests, bounded response sizes, and the `.sha256` check |
| An attacker who can edit this repository's Releases (a leaked token, a compromised GitHub account, a malicious Actions run or dependency) | The `.sig` check: a release not signed by the maintainer's offline key is not installed. The attacker can only make sites *not* update (a visible failure), never install something else |
| GitHub itself serving a wrong asset | The `.sig` check, as above |
| A compromised maintainer machine or key | **Not** covered. The key is the trust anchor: keep it in a password manager, never in a repository, and rotate it (below) if its exposure is suspected |
| A plugin or mu-plugin running PHP on the site | **Not** covered, and no theme can cover it: PHP on the site can remove the theme's hooks. Site code is trusted by definition |

The `.sha256` sidecar and the GitHub API live in the same place as the archive, so on their own
they would prove only that the transfer was intact. The pinned public key is what makes the
check independent of GitHub. The signature is over the archive bytes, so the manual
reproducibility check (`git archive` of the tag yields the identical zip) and the signature
check verify the same artefact.

**Key rotation.** `oogle_update_signing_keys()` holds a list, not a single key. To rotate:
generate a new key pair, add its public key to the list, publish a release signed with the
**old** key (every installed site accepts it), and from the next release on sign with the new
key; the old key can be dropped from the list a release later. A key that is *lost* cannot be
rotated this way: sites would have to install the next release once by manual upload
(Appearance → Themes → Add New → Upload, which the signature gate does not cover), after which
they follow the new key. Hence the password-manager backup.

**Verifying a release by hand.** `tools/verify-release.sh vX.Y.Z` downloads the three assets
and checks the `.sha256` and the `.sig` with OpenSSL against `tools/release-signing.pub`;
`gh attestation verify oogle-theme.zip --owner neumanc` checks the SLSA build provenance the
Release workflow attaches (which commit and workflow built the archive).

Security controls that belong to other layers are intentionally absent: authentication
hardening, login throttling, REST restrictions, HTTP security headers, WAF rules, file
permissions and version hiding are the job of the server, CDN or a dedicated plugin.
