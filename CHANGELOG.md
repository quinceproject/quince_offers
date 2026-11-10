# Changelog

All notable changes to this module are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the versioning follows
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.4.2] - 2026-11-10

### Added

- Polish translation of every text the module shows, in the back office and in the shop.
- Polish version of the quote e-mail.

### Changed

- Texts no longer use long dashes.

## [0.4.1] - 2026-11-10

### Fixed

- The "Send a copy to" field reads "Nobody, the customer only" instead of using a
  long dash.

## [0.4.0] - 2026-11-10

First public release.

### Added

- "Ask for a PDF quote" on the product page: the customer collects products on a
  quote list, leaves an email address and gets a PDF quote by email, with a download
  link straight away.
- Quote numbering per shop, restarting every month, with a prefix set by the shop,
  and a validity period in days.
- PDF quote with net and gross prices frozen on the day of issue, the shop details,
  a QR code that opens the product (with UTM tags carrying the quote number) and,
  when Quince Contacts is installed, a contact person.
- Partner logos: a quote for an address at a partner's domain carries that partner's
  logo.
- Hidden copy of every quote to chosen shop contacts, and texts the customer reads
  set per language.
- Quote log in the back office with monthly totals, search, the items of each quote
  and building a missing PDF again.
- Admin API (scope `quince_offers_read`) to list quotes, read one and download its PDF.
- Optional integrations: a `quote.created` event through Quince Events and the
  newsletter consent recorded in Quince CMP.
- Declared dependency on Quince Engine.
