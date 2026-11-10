# Quince Offers

**PDF quotes for the products a customer picks, for PrestaShop 9.2.**

Quince Offers adds "Ask for a PDF quote" to the product page. The customer collects
products on a quote list, leaves an email address and gets a numbered PDF quote by
email, with net prices and a validity date. The shop keeps a copy of every quote in
the back office, and an outside system can read them through the Admin API.

> Quince Offers is a free, open-source module (GPL-2.0-only). There is nothing to buy.
> If it saves you time, you can support development, see
> [Supporting the project](#supporting-the-project).

---

## Features

- **Quote list on the product page.** A button opens a drawer where the customer adds
  products to the quote list, enters an email address, a name or company, an optional
  phone number and, if they want, signs up for the newsletter. After sending, the
  drawer shows the quote number and a **Download the PDF** link.
- **Numbered quotes.** Every shop counts its quotes on its own and starts from one each
  month. The shop sets the number prefix (for example `OF/2026/09/0001`) and how many
  days a quote stays valid. The number and the date go on the PDF and into the email.
- **Prices frozen on the day of issue.** The PDF shows net and gross prices taken when
  the quote is issued. Building the PDF again later uses the saved prices, not the
  current catalogue.
- **The PDF.** Shop details from the PrestaShop settings, the items with net and gross
  prices, a QR code that opens the product (with UTM tags carrying the quote number)
  and a contact block. With Quince Contacts installed, the contact block shows the
  first active contact person instead of the shop details.
- **Partner logos.** The shop adds a partner's domain and logo (JPG, PNG or WEBP, up
  to 1 MB). Every quote sent to an address at that domain carries the partner's logo.
- **Copies and wording.** A hidden copy of every quote can go to chosen shop contacts
  (from Shop → Contacts), and replies can go to one of them. The texts the customer
  reads are set per language.
- **Quote log.** Quotes issued this month and their net value, search by number, email
  or company, the items of each quote, and building the PDF again for a quote that has
  none.
- **Optional integrations.** With [Quince Events](https://github.com/quinceproject/quince_events)
  installed, every quote sends a `quote.created` event. With Quince CMP installed, the
  newsletter sign-up from the quote form is recorded in its consent register. Without
  them the module works on its own.

## Requirements

| Requirement | Version |
|-------------|---------|
| PrestaShop  | 9.2 (tested on 9.2.0) |
| PHP         | 8.4 (tested), 8.1 or newer required |
| Quince Engine | required |
| Quince theme | for the button on the product page |

Quince Engine hosts the module's settings screens. The module declares it as a
dependency, so PrestaShop refuses to install Quince Offers without it.

The button and the drawer are shown through the `displayQuinceOffer` hook, which the
Quince theme places on the product page. Another theme has to call
`{hook h='displayQuinceOffer' product=$product}` in its product template.

## Installation

The release ZIP already contains everything the module needs. Composer and the
command line are not required.

**From the back office**

1. Download the latest **`quince_offers.zip`** from the
   [**Releases**](https://github.com/quinceproject/quince_offers/releases) page.
2. In the back office go to **Modules → Module Manager → Upload a module** and
   choose the ZIP. PrestaShop installs it.

**By copying the files to the server**

1. Unpack the release ZIP. It contains a `quince_offers` folder.
2. Upload that folder to the `modules/` directory of the shop, for example by FTP.
3. In **Modules → Module Manager** find Quince Offers and click **Install**.

**From Quince Engine** *(coming soon)*

The module list in Quince Engine will install the module straight from its release,
without downloading anything by hand.

**With Composer** *(coming soon)*

Installing with `composer require quinceproject/offers` will work once the package is
published on Packagist.

> ⚠️ **Do not** use the green **Code → Download ZIP** button on this page. That
> archive contains the source only and is **missing the `vendor/` folder**, so the
> module will not run. Always install from a
> [**Release**](https://github.com/quinceproject/quince_offers/releases).

Uninstalling the module removes the quote log, the numbering and the generated PDF
files.

## Configuration

Open **Quince project → Offers** in the back office menu, or click **Configure** next
to the module in the Module Manager.

1. **Numbering and validity.** Set the number prefix and the number of days a quote
   stays valid.
2. **Copies.** Choose who gets a hidden copy of every quote and where replies go.
3. **Texts the customer reads.** Write them for each language of the shop.
4. **Partner logos.** Add a partner's domain and upload its logo.

## Admin API

Three read-only endpoints on the PrestaShop Admin API, under the OAuth2 scope
`quince_offers_read`:

| Method | Path | Returns |
|--------|------|---------|
| `GET` | `/quince-offers/offers` | the quotes of a shop |
| `GET` | `/quince-offers/offers/{id}` | one quote with its buyer and items |
| `GET` | `/quince-offers/offers/{id}/pdf` | the PDF file of that quote |

Quotes are read only; the API does not create or change them.

## Known limitations

- The module has no Polish or other translation yet: the back office, the quote form
  and the email are in English. The texts the customer reads can be written per
  language in the settings.
- Building a PDF again for a quote that already has one writes the file under the
  same name, replacing the old file.

## Architecture

- **`OfferMaker`** takes the chosen products and the customer's details, freezes the
  prices and saves the quote; **`OfferNumber`** gives it its number.
- **`PdfGenerator`** builds the PDF with mPDF and stores it under `var/` of the shop,
  outside the web root of the module.
- **`OfferMailer`** sends the quote with the PDF attached.
- **Front controllers** `submit` (the quote form) and `pdf` (the download).
- **Symfony controllers and Twig** for the back office screens, inside the Quince
  Engine shell; **API Platform** resources for the Admin API.
- **mPDF and its packages** live in `libs/`, loaded only when a PDF is built.

## Development / building from source

This repository tracks **source only**. The `vendor/` directory is git-ignored, so a
`git clone` is **not** directly installable. **Start with `composer install`**: the
development environment mounts module directories straight from disk, and without
that step a fresh clone gives a broken back office.

```bash
git clone https://github.com/quinceproject/quince_offers.git
cd quince_offers
composer install
```

The folder must be called `quince_offers`, the same as the module. A clone of this
repository already has that name.

To build an installable archive, zip the `quince_offers/` folder **with its `vendor/`
and `libs/` directories included**, leaving out `.git/` and `.gitignore`.

## License

Code: [GNU General Public License v2.0 only (GPL-2.0-only)](LICENSE).

The module ships mPDF, which is licensed under GPL-2.0-only, so the whole module is
released under that licence. Other Quince modules use the Open Software License 3.0.
The other packages in `libs/` keep their own licences, listed in
[THIRD-PARTY-NOTICES.md](THIRD-PARTY-NOTICES.md).

**The name "Quince" is not covered by the code licence.** A fork may use the code; it
may not use the name.

© 2026 Marcin Gajewski

## Supporting the project

Quince modules are free downloads and there is nothing to buy. If the module is useful
to you, you can support its development by buying me a coffee:

**[buymeacoffee.com/marcingajewski](https://buymeacoffee.com/marcingajewski)**
