# Changelog

## Unreleased

- Build confirmation line items from the persisted OpenCart order when an
  order ID is available, preventing authorization amount mismatches caused by
  stale session carts (upstream issue #9).
- Add compatibility checks for PHP 8.2, 8.3, and 8.4.
- Make the bundled SDK models compatible with PHP 8.4 nullable parameters and
  `ArrayAccess` return types.
- Preserve webhook and manual-task settings correctly when saving the module.
- Update an existing webhook URL in place where possible so listeners remain
  active during a canonical-host change.
- Install and verify a replacement webhook before removing an old one.
- Add Composer-based lint and webhook synchronization tests.
