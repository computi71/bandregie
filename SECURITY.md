# Security Policy

## Reporting a vulnerability

Please report security problems privately first, so a fix can go out before
the details do.

- **Preferred:** [open a private security advisory](https://github.com/computi71/bandregie/security/advisories/new)
  on GitHub. It stays between you and the maintainer until it is resolved.
- **Alternative:** Open Bug Bounty, which has been used for this project
  before and works fine.

Please include what you did, what you got back, and which version you tested
(the footer of any page shows it). A single request that demonstrates the
problem is worth more than a scanner report.

What you can expect: an acknowledgement within a few days, and an honest
answer about whether it is a problem and when it will be fixed. This is a
small project maintained in spare time — the answer will be quick, the fix
may take a little longer.

## Scope

The code in this repository. If you are testing a running installation, test
your own. Please do not test other bands' instances without their permission,
and do not use findings to read data that is not yours.

Out of scope: missing rate limits on the login form (deliberate — a band of
six does not need to be locked out by a stranger), missing security headers
that only matter with third-party scripts (there are none), and reports that
amount to "this software has features".

## Supported versions

The latest release. This is a single-branch project; fixes go into the next
version rather than into backports.

## What the project does about security

- Uploads live outside the document root and are served through a route that
  checks the session and the module permission. Unknown and forbidden both
  answer 404, so a response never confirms that a file exists.
- Private file names carry no meaning and cannot be counted through.
- Every write goes through a CSRF check, and permissions are enforced in the
  route, never only in the interface. A hidden form is not a rule.
- The content security policy is `script-src 'self'` — there is no inline
  JavaScript and no third-party script.
- Passwords are stored with `password_hash()` — bcrypt with a per-password
  random salt. The first administrator gets a random password written to a
  file outside the web root, which must be changed at first login. No
  installation ships with known credentials.
- Data at rest can be encrypted: with a `data_key` in `app/config.php`,
  backups and attachments are sealed with XChaCha20-Poly1305 (libsodium,
  authenticated). The key never touches the database, because it would then
  ride along inside the backups it protects. The system check tests that the
  encryption actually works — seal, open, flip a byte, confirm the refusal —
  as GDPR Art. 32(1)(d) asks for effectiveness to be verified rather than
  assumed. Not encrypted, deliberately: the live database, which the server
  has to sort and sum in, and `data/uploads`, which the web server serves
  directly. Both are stated in the settings rather than glossed over.

## Acknowledgements

- **SecTech** — improper access control on uploaded files (OBB-4627784),
  July 2026. Reported responsibly, fixed the same day.
- **SecTech** — an exposed `/plesk-stat/` directory on an installation, July
  2026. The directory belongs to the hosting panel rather than to this code,
  but the reports carry visitor IP addresses and the exposure is the Plesk
  default — so the system check now tests for it and says how to close it
  (#103). A finding outside the code that made the code better.
