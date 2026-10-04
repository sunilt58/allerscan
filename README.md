# AllerScan — Your everyday allergen companion

A shopper-facing website and installable web app for checking the allergens recorded for packaged products in Japan. Built with Laravel 13, Blade, Livewire 4, plain CSS/JavaScript, and MySQL. No Vue or React is required.

## Open on this Mac

1. Start Herd and the MySQL service in DBngin.
2. Visit **https://allerscan.test**. Browsing does not require an account.
3. In **Settings → My allergens**, choose the allergens to highlight.
4. Explore **Discover**, or open **Scan** and enter `DEMO001` (a local sample record).
5. Open a product to see its recorded allergens and source, listen in Japanese or English, or save it.

The local `.env` uses a dedicated MySQL user with access only to `allerscan` and is ignored by Git. Historical POS tables remain unused; checkout and customer-display routes have been retired.

## Features

- Public catalog search by Japanese/English name or exact barcode, with category filters.
- Camera barcode scanning via the bundled ZXing library, loaded only when requested. Manual and keyboard-style USB barcode entry also work.
- Product detail pages with recorded allergen identities, source, date, and a SAMPLE label on sample records.
- Personal allergen selections, highlighted matches, and a saved list of up to 50 products.
- Optional shopper accounts that keep allergens and saved products on every device, with self-service account deletion. Browsing and scanning never need an account.
- Japan's 8 mandatory and 20 recommended labeling items (特定原材料等), plus pistachio, as the allergen list.
- Japanese (default) and English interface, switched from the header or **Settings → Language** and remembered in a `locale` cookie. Interface text lives in `lang/ja.json` (English source strings are the keys); product and allergen names use their `_ja`/`_en` columns.
- Japanese/English speech with selectable device voices, preview, and stop controls.
- Forest, Ocean, and Midnight themes, high contrast, larger text, keyboard access, and mobile navigation.
- Installable web app manifest, home-screen icons, and an offline reconnect page. Product/allergen responses are not cached by the service worker.
- Suggestion page where signed-in shoppers propose a missing product or allergen and follow what happened to it.
- A separate team area (`/admin`) with its own layout: catalog create/edit/archive/restore, and a review list for shopper suggestions with a waiting-count badge and notice.

Without an account, allergen selections and saved product IDs live in this browser's local storage and are never sent to the database. With an account they are stored on the server (`allergen_user` and `product_user`) and mirrored into the browser: right after signing in, the browser's lists are merged into the account; after that the account is the source of truth, and signing out clears them from the browser. Theme, text size, and voices always stay in the browser.

Allergen selections can reveal health information. Registration asks for explicit consent, and the Privacy & terms page describes what is stored. Email sending is not configured yet, so there is no email verification or password reset.

## Smartphone installation

This is a PWA, not an App Store/Google Play native app. On a reachable HTTPS deployment, use the browser's Install option; on iPhone/iPad use Safari → Share → Add to Home Screen. Settings includes an Install button when the browser offers installation.

`allerscan.test` is this Mac's local Herd address. A phone needs a reachable HTTPS deployment or appropriately configured local access before it can use or install the app. Native app packaging is not included; see **Public deployment** for hosting. Product lookups require an internet/server connection; offline navigation displays a reconnect page rather than old allergen records.

## Catalog management

Team accounts land in the team area (`/admin/products`) after signing in; from the shopper site, use **Manage catalog** in the footer. Locally the first team account is:

- Email: `admin@allerscan.test`
- Password: the `ADMIN_PASSWORD` value in your local `.env` (see `.env.example`)

The seeder creates this account only if absent; changing `ADMIN_PASSWORD` later does not reset it. To give a teammate catalog access, have them create an account on the site, then run:

```sh
php artisan allerscan:team teammate@example.com          # grant
php artisan allerscan:team teammate@example.com --remove # revoke
```

## Suggestions

Signed-in shoppers can suggest a product (barcode, names, size, category, allergens seen on the package) or an allergen missing from the list at `/suggest`; a "product not found" scan links there with the barcode filled in. Nothing suggested is public. In **Suggestions**, the team either dismisses a suggestion or:

- **Check and add product** opens the normal product form filled in from the suggestion, with status *Unknown*. Check every field against the package label, then save; the suggestion is marked added only when the product is saved.
- **Add to allergen list** creates the allergen after the team confirms its code and both names.

Shoppers see *Waiting for review*, *Added*, or *Not added* on their suggestion page. Each account may have 20 suggestions waiting at once, and suggestions are deleted with the account.

## Smartphone app API

The Android and iPhone app uses the same accounts, catalog, and suggestions as the website through a JSON API at `/api/v1`. Send `Accept: application/json`; add `Accept-Language: en` for English messages (Japanese is the default). Sign-in uses Laravel Sanctum tokens: `register` and `login` return a `token`, which signed-in requests send as `Authorization: Bearer <token>`. Each device has its own token; `logout` ends only that device's.

| Method | Path | Sign-in | Purpose |
|---|---|---|---|
| POST | `/api/v1/register` | — | Create a shopper account: `name`, `email`, `password`, `password_confirmation`, `consent` (true), `device_name` |
| POST | `/api/v1/login` | — | `email`, `password`, `device_name` → `token` and `user` |
| GET | `/api/v1/allergens` | — | The allergen list (`code`, `name_ja`, `name_en`) |
| GET | `/api/v1/products/barcode/{barcode}` | — | Look up a scanned barcode; 404 with a `message` if it isn't in the catalog |
| GET | `/api/v1/products?q=&category=` | — | Search by name or exact barcode, 20 per page |
| GET | `/api/v1/products/{id}` | — | One product |
| GET | `/api/v1/me` | ✓ | The account, its `allergens` (codes) and `saved_products` (ids) |
| PUT | `/api/v1/me/preferences` | ✓ | Replace `allergens` and `saved_products` (up to 50) |
| GET | `/api/v1/me/saved-products` | ✓ | Saved products with details |
| GET | `/api/v1/me/suggestions` | ✓ | The account's suggestions and their `status` |
| POST | `/api/v1/suggestions` | ✓ | Suggest a product or allergen (same fields as the website form) |
| POST | `/api/v1/logout` | ✓ | Sign out this device |
| DELETE | `/api/v1/me` | ✓ | Delete the account (`password` required) |

Products include both language names, `information_status` (`recorded` or `unknown`), `is_sample`, and their `allergens`. **An `unknown` product must be shown as "information unconfirmed", never as allergen-free**, and an empty allergen list never means a product is safe. Validation errors return 422 with an `errors` object; a missing or expired token returns 401.

Phones can only reach the API at an HTTPS address that is online (iPhone blocks plain HTTP by default), so test against a deployed site or a `herd share` link rather than `allerscan.test`.

## Product data

The team enters real products from their packaging: barcode, Japanese and English names, size, the allergens the label declares, the source (for example "Package label, photographed 2026-10-01"), and the date checked. New entries are real catalog records. Until allergens have been checked against the label, leave **Information status** as *Unknown*; shoppers then see "Information unconfirmed" rather than "None recorded".

When a team member scans or types a barcode that isn't in the catalog on the shopper **Scan** page, **Register this product** opens the add-product form with the barcode filled in; shoppers see the suggestion link instead. The team's product page also has its own barcode box with a camera button: a registered (or archived) barcode opens that product for editing, and a new one opens the add-product form.

In the product form, **Fill in from Open Food Facts** looks the barcode up in the free, crowd-sourced [Open Food Facts](https://world.openfoodfacts.org) database. It fills only empty fields, ticks allergens that map exactly to our list, lists the rest (for example "gluten" or "crustaceans") for the team to decide, and sets the status to *Unknown*. Coverage of Japanese products is limited, and the data is a draft: check it against the package before marking it *Recorded*. Open Food Facts data is under the ODbL, credited on the Privacy & terms page.

`php artisan db:seed` also adds ten **sample records** (`DEMO001`–`DEMO010`) in local and testing environments only. They are fictional, labeled SAMPLE wherever they appear, and used by the automated tests. They are never seeded on a deployed site. Archive them locally once real products are entered if you don't want to see them.

An empty recorded list or no preference match never establishes that a product is allergen-free. Always check the real packaging and manufacturer information.

## Another developer's machine

Requirements: PHP 8.4.1+ (the locked Symfony 8 packages need it), Composer, Node 20.19+ or 22.12+ (Node 24 recommended), MySQL, and a local server — Herd (macOS/Windows) or Laragon (Windows). PHP needs the `pdo_mysql`, `pdo_sqlite`, `sqlite3`, `mbstring`, `intl`, `fileinfo`, `zip`, and `openssl` extensions.

The project folder must be named `allerscan` so the site is served at `https://allerscan.test`, matching `APP_URL`.

```sh
composer install
cp .env.example .env
php artisan key:generate
npm ci
npm run build
```

Create an empty dedicated MySQL database named `allerscan`, configure credentials in `.env`, and then run:

```sh
php artisan migrate --seed
herd secure allerscan
```

Use the Herd URL. `npm run dev` watches frontend edits; `npm run build` produces assets without a continuously running Node process. Do not point tests at an unrelated database.

### Windows with Laragon

1. Laragon's bundled PHP is often older than 8.4. Download the PHP 8.4 **Thread Safe x64** zip from windows.php.net, unzip it into `C:\laragon\bin\php\`, then select it in **Menu → PHP → Version** and enable the extensions listed above in **Menu → PHP → Extensions**. Confirm with `php -v`.
2. If `node -v` is older than 20.19, install Node 24 LTS from nodejs.org.
3. In Laragon's Terminal:

    ```powershell
    cd C:\laragon\www
    git clone https://github.com/sunilt58/allerscan.git allerscan
    cd allerscan
    composer install
    copy .env.example .env
    php artisan key:generate
    npm ci
    npm run build
    ```

4. **Start All**, open **Database**, and create `allerscan`. Laragon's MySQL uses `root` with an empty password, which matches `.env.example`. Then run `php artisan migrate --seed`.
5. The camera requires HTTPS: enable **Menu → Apache → SSL**, add Laragon's certificate to the Windows trust store from the same menu, restart Laragon, and open `https://allerscan.test`.
6. Run `php artisan test --compact` to confirm the setup.

Each machine has its own database, so catalog entries made on one computer do not appear on another.

## Code map

- `app/Http/Controllers/CatalogController.php`: search, exact barcode lookup, active product details, and bounded saved-list results.
- `app/Livewire/ProductManager.php`: admin catalog editing and validation.
- `app/Http/Controllers/AccountController.php`: registration, synced allergens and saved products, account deletion.
- `app/Http/Controllers/SuggestionController.php` and `app/Livewire/SuggestionReview.php`: shopper suggestions and the team's review list.
- `routes/api.php`, `app/Http/Controllers/Api/V1/`, and `app/Http/Resources/`: the smartphone app API. Registration and suggestion rules are shared with the website through `app/Http/Requests/`.
- `resources/views/components/admin-layout.blade.php` and `resources/css/admin.css`: the team area's layout.
- `resources/views/`: shopper pages, shared components, settings, and admin management.
- `resources/js/app.js`: local preferences, highlighting, saved lists, camera, speech, and installation prompt.
- `resources/css/shopper.css`: consumer layout and responsive design; `themes.css` supplies palettes.
- `public/manifest.webmanifest`, `public/sw.js`, `public/offline.html`: installation and offline behavior.
- `database/seeders/DatabaseSeeder.php`: first team account, plus local-only sample records.

## Public deployment

1. Copy `.env.production.example` to `.env` on the server and fill in every blank value, including `APP_KEY` (`php artisan key:generate`), database credentials, `APP_URL` (HTTPS), `CONTACT_EMAIL`, `ADMIN_EMAIL`, and a strong, unique `ADMIN_PASSWORD`.
2. `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`, `php artisan migrate --force`.
3. `php artisan db:seed --force` creates the first team account from `ADMIN_EMAIL` and `ADMIN_PASSWORD`. It refuses to run with the default password and adds no sample products.
4. `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
5. Configure database backups and error monitoring on the host.
6. When the catalog holds real, checked products, set `APP_NOINDEX=false` so search engines may list the site.

All web responses send security headers (`X-Frame-Options`, `nosniff`, a restrictive CSP, `Permissions-Policy`, and HSTS over HTTPS in production). Requests from any proxy are trusted so client IPs and HTTPS are detected behind a load balancer; restrict this in `bootstrap/app.php` if the app is exposed directly. While `APP_NOINDEX=true` (the default), responses include `X-Robots-Tag: noindex`. The **Privacy & terms** page is linked in the footer.

## Verification

```sh
php artisan test --compact
npm run build
npm run test:browser
```

PHP tests use isolated in-memory SQLite and cover public search/detail routes, missing and archived products, request limits, output escaping, admin authorization, catalog validation, registration, preference sync, account deletion, suggestions, team-only access, and the app API.

Browser tests use installed Google Chrome and the running Herd site, and need the local sample records. They do not edit the development catalog; the account and suggestion tests each create one account and delete it again. Set `PLAYWRIGHT_BASE_URL` to override the URL. They cover allergen highlights, saved-list recovery and synchronization, voice selection using simulated browser voices, themes, phone layouts, camera denial, and service-worker offline behavior. Screenshots are saved under the ignored `storage/app/testing/` directory.

Physical camera barcode detection, actual device voices, and installation on real iPhone/Android devices still need device testing. Automated checks simulate denied camera permission and browser speech output.
