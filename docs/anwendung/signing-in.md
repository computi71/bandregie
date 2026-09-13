# Signing in

Three ways in, and what they are worth.

## Signing in

Three ways in, and they stack rather than compete.

**Password** is always there. **Passkeys** sit next to it: the private key stays
in the member's keychain and is released by face, fingerprint or the device
lock, so nothing reusable is ever sent. **A second factor** (TOTP, RFC 6238)
can be switched on for password sign-in — off, optional, or required for
everyone, set by an administrator under *Settings → Second factor*.

Signing in with a passkey never asks for the second factor. That is deliberate,
not an oversight: releasing a passkey already requires the device to be
unlocked, which is a second factor that cannot be typed in, phished, or read
off a screen.

Setting it up shows a QR code and the secret to type by hand, and takes one
code from the app before it counts — proof that the app really computes,
before it becomes the condition for getting in. Ten single-use recovery codes
are shown exactly once and stored only as SHA-256 fingerprints. When phone and
recovery codes are gone at once, an administrator resets the factor from the
member list; when an administrator locks *themselves* out by requiring it
without having one, the setup page offers them the way back.

The secret is sealed with the encryption key when one is configured, code entry
is rate-limited like the password, and a correct password alone puts no user id
in the session — until the code is right, everything behind the login stays
shut. No library and no external service is involved: the QR encoder
(`app/qr.php`) is part of this repository, because the code carries the secret
in the clear and rendering it elsewhere would ship the second factor to a
stranger.

**Stay signed in** is offered at sign-in and lasts 90 days on that device. The
cookie carries a selector and a validator; the server keeps the selector and
only a SHA-256 of the validator, so a stolen database yields no session. Every
use swaps the validator and slides the expiry, which makes an intercepted
cookie worthless after the next request. It is issued only after a complete
sign-in (a passkey always brings it, since the key belongs to that one device),
and it is deleted on sign-out and on every password change — a new password
turns every device out.

Without it the session died after 24 minutes of inactivity, and a signed-out app
fetches no count, refreshes no push subscription and never learns that the
browser rotated one. That, not the push machinery, was why the badge on the app
icon kept going quiet.

**A start password** — the one mailed with the access details — is good for
seven days. It travels in plain text through every mailbox that mail passes,
and one that still opens the door months later is the trap this closes. An
expired one is refused after the password check, never before: refusing earlier
would tell a stranger which address has an account.

Found a hole? Please report it privately first — see [SECURITY.md](SECURITY.md).
