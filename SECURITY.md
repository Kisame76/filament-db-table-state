# Security policy

## Reporting a vulnerability

Please do not open a public issue for a security problem. Email
**k.doguc@kisame-labs.com** instead, with enough detail to reproduce it, and you will get
an acknowledgement within a few days.

## Scope worth knowing about

This package copies Filament's table state between the session and one database row per
user per table, so the places that deserve the closest look are the two boundaries that
copy crosses:

- **Whose row it is.** The row is looked up and written by the id of the authenticated
  user, resolved server-side from the configured guard (or from
  `TableStatePersister::resolveUserIdUsing()`). Nothing in the request chooses it, and a
  guest is never read or written. A way to read or overwrite another user's saved state
  is the report that matters most.
- **What goes back into the session.** Only the session keys Filament itself resolves for
  the current table are restored, and only when the session does not already hold a
  value for them. State that Filament no longer understands is its to reject, not this
  package's to repair.

Reports about either, or about anything else in the package, are appreciated.
