# Changelog

## Unreleased

- Keep the OpenCart admin order heading intact by loading dynamic Wallee
  payment-method titles in an isolated language scope (upstream issue #1).
- Avoid wrapping already modified `include` and `require` paths in a second
  `modification()` call in the core OCMOD rules (upstream issue #7).
- Suppress only the expected MySQL 1062 duplicate cron constraint error while
  continuing to log unrelated cron failures (upstream issue #6).
- Serialize concurrent callbacks per Wallee transaction and compare normalized
  OpenCart status IDs to prevent duplicate order history entries and customer
  emails (upstream issue #4).
- Align fixed and percentage coupon discounts with OpenCart's proportional,
  tax-aware calculation so Wallee payment methods remain available after a
  discount code is applied (upstream issue #5).
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
