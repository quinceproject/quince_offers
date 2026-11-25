# Changelog

All notable changes to this module are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the versioning follows
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.5.0] - 2026-11-25

### Changed

- The offer email has a new layout, the same as the Quince Contacts emails: the
  shop's email logo, a bar in the shop colour, the content on a white card and a
  dark footer with the seller details. Colours and fonts come from the engine
  appearance settings (Quince → Appearance), the seller details from Shop
  Parameters → Contact → Stores.
- The email shows the offer number and validity date, the items with SKU,
  quantity and amounts (gross, net below), the total, a button to download the PDF
  and the contact person, the same one as in the PDF. The PDF is still attached.
- The validity date is written the way the offer language writes dates
  (25.12.2026 in Polish).

## [0.4.10] - 2026-11-16

### Fixed

- The quote PDF is written in the language of the quote. A quote for a Polish
  customer had every heading in English: the module had no Polish translation for
  the PDF, and the translator of the current request only knows the current
  language. Polish translations added for all PDF texts.
- The contact person in the PDF shows the job title and the phone number from
  Quince Contacts. Before, they were read under wrong names and never showed.

### Changed

- In a quote with several products the contact block ("Questions? Talk to us")
  stands once, at the end, instead of under every product. It shows the person's
  photo on the left, then the name, job title, phone and email.
- Under each product stays only the QR code row: "Scan to open the product" on the
  left, in the text colour and a larger size, the code on the right.

## [0.4.9] - 2026-11-16

### Changed

- New module logo: the letter Q in the project gradient.
- Files in `src/` stop when opened directly instead of through PrestaShop.

## [0.4.8] - 2026-11-15

### Security

- **In a multistore, the offer log shows only offers from shops the employee may
  manage**, within the shop context chosen in the admin. This applies to the list, the
  counters at the top and opening or rebuilding a single offer's PDF. Before, an
  employee limited to one shop saw the customer data from offers of every shop.

### Fixed

- Two offers sent at the same moment can no longer get the same number. The counter
  is now raised and read in a single database query.

## [0.4.7] - 2026-11-15

### Security

- **The PDF generator no longer fetches remote files.** A product description with an
  image or stylesheet from another address made the shop's server request it every
  time an offer was generated. Images in descriptions that point to the shop itself
  are read from disk and still appear in the PDF; images from other domains do not.
- The `libs/` folder is closed to the web, and an unused mPDF script that read a file
  name from the request is removed.

## [0.4.6] - 2026-11-15

### Security

- Every back-office screen of the module checks the employee's permissions. Before,
  any logged-in employee, whatever their profile, could open the offer log with
  customers' details and change the settings. The screens now belong to the module's
  own menu tab (`AdminQuinceOffers`): grant it to a profile under **Advanced
  Parameters → Team → Permissions**. SuperAdmin needs nothing. A profile that had
  access through the hidden `AdminQuince` tab needs access to this tab now.
- The newsletter box in the offer form no longer records consent as confirmed for an
  address anyone can type in. For a visitor who is not signed in with that address the
  consent goes to Quince CMP marked as unverified, and an unticked box records nothing,
  so it cannot withdraw another person's consent.

## [0.4.5] - 2026-11-15

### Security

- The same e-mail address gets at most three offers in ten minutes, whichever
  address the requests come from. Before, the offer form could be used to send PDFs
  from the shop's address to any mailbox. Together with Quince Engine 0.8.1, which
  makes the form guard mandatory for sending an offer.
- The customer's name is escaped in the offer e-mail.

## [0.4.4] - 2026-11-13

### Fixed

- The back office no longer sends you to the shop's home page after this module is
  installed from its release ZIP. The index.php file in every folder of the package
  redirected and stopped the process also when PrestaShop loaded it while scanning
  `src/ApiPlatform/Resources`, so the back office could not be built.

## [0.4.3] - 2026-11-10

### Changed

- Admin API descriptions no longer use long dashes.
- An empty value in the quote PDF shows a hyphen instead of a long dash.

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
