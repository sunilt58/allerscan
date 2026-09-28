# AllerScan — Your everyday allergen companion

A graduation project for January 2027: a shopper-facing website and installable web app, built with Laravel 13, Blade, Livewire 4, plain CSS/JavaScript, and MySQL. No Vue or React is required.

## Open on this Mac

1. Start Herd and the MySQL service in DBngin.
2. Visit **https://allerscan.test**. Browsing does not require an account.
3. In **Settings → My allergens**, choose the allergens to highlight.
4. Explore **Discover**, or open **Scan** and enter `DEMO001`.
5. Open a product to see its recorded allergens and source, listen in Japanese or English, or save it.

The local `.env` uses a dedicated MySQL user with access only to `allerscan_demo` and is ignored by Git. No existing database records were deleted during the redesign. Historical POS tables remain unused; checkout and customer-display routes have been retired.

## Features

- Public catalog search by Japanese/English name or exact barcode, with category filters.
- Camera barcode scanning via the bundled ZXing library, loaded only when requested. Manual and keyboard-style USB barcode entry also work.
- Product detail pages with recorded allergen identities, source, date, and fictional-demo flag.
- Personal allergen selections, highlighted matches, and a saved list of up to 50 products.
- Japanese (default) and English interface, switched from the header or **Settings → Language** and remembered in a `locale` cookie. Interface text lives in `lang/ja.json` (English source strings are the keys); product and allergen names use their `_ja`/`_en` columns.
- Japanese/English speech with selectable device voices, preview, and stop controls.
- Forest, Ocean, and Midnight themes, high contrast, larger text, keyboard access, and mobile navigation.
- Installable web app manifest, home-screen icons, and an offline reconnect page. Product/allergen responses are not cached by the service worker.
- Protected team login and administrator-only catalog create/edit/archive/restore. Shoppers do not need accounts.

Preferences and saved product IDs live in this browser's local storage, shared across tabs. They do not sync across devices or browsers. Clearing browser data removes them. No personal allergy profile is sent to the database. Saved pages request fresh product information; archived products are hidden.

## Smartphone installation

This is a PWA, not an App Store/Google Play native app. On a reachable HTTPS deployment, use the browser's Install option; on iPhone/iPad use Safari → Share → Add to Home Screen. Settings includes an Install button when the browser offers installation.

`allerscan.test` is this Mac's local Herd address. A phone needs a reachable HTTPS deployment or appropriately configured local access before it can use or install the app. Native app packaging is not included; see **Public deployment** for hosting. Product lookups require an internet/server connection; offline navigation displays a reconnect page rather than old allergen records.

## Catalog management

Use **Team sign in** in the footer, then **Manage catalog** after signing in:

- Email: `demo@allerscan.test`
- Password: the `DEMO_PASSWORD` value in your local `.env` (see `.env.example`)

The seeder creates this administrator only if absent. `DEMO_PASSWORD` chooses the password before initial seeding; changing the setting does not reset an existing account. There is no public registration.

## Demo data

The ten seeded products and allergen declarations are **fictional**. `DEMO001`–`DEMO010` are fictional identifiers; real product barcodes require catalog entries. The allergen vocabulary is deliberately limited. An unknown record remains “unconfirmed”; an empty recorded list or no preference match never establishes that a product is allergen-free. Always check the real packaging and manufacturer information.

## Another developer's machine

Requirements: PHP 8.3+, Composer, Node 24, Herd, and MySQL.

```sh
composer install
cp .env.example .env
php artisan key:generate
npm ci
npm run build
```

Create an empty dedicated MySQL database named `allerscan_demo`, configure credentials in `.env`, and then run:

```sh
php artisan migrate --seed
herd secure allerscan
```

Use the Herd URL. `npm run dev` watches frontend edits; `npm run build` produces assets without a continuously running Node process. Do not point tests at an unrelated database.

## Code map

- `app/Http/Controllers/CatalogController.php`: search, exact barcode lookup, active product details, and bounded saved-list results.
- `app/Livewire/ProductManager.php`: admin catalog editing and validation.
- `resources/views/`: shopper pages, shared components, settings, and admin management.
- `resources/js/app.js`: local preferences, highlighting, saved lists, camera, speech, and installation prompt.
- `resources/css/shopper.css`: consumer layout and responsive design; `themes.css` supplies palettes.
- `public/manifest.webmanifest`, `public/sw.js`, `public/offline.html`: installation and offline behavior.
- `database/seeders/DatabaseSeeder.php`: fictional catalog and local team account.

## Public deployment

1. Copy `.env.production.example` to `.env` on the server and fill in every blank value, including `APP_KEY` (`php artisan key:generate`), database credentials, `APP_URL` (HTTPS), and `CONTACT_EMAIL`.
2. `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`, `php artisan migrate --force`.
3. For the first seed only, set `DEMO_ALLOW_SEED=true` and a strong, unique `DEMO_PASSWORD`, run `php artisan db:seed --force`, then set `DEMO_ALLOW_SEED=false`.
4. `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
5. Configure database backups and error monitoring on the host.

All web responses send security headers (`X-Frame-Options`, `nosniff`, a restrictive CSP, `Permissions-Policy`, and HSTS over HTTPS in production). Requests from any proxy are trusted so client IPs and HTTPS are detected behind a load balancer; restrict this in `bootstrap/app.php` if the app is exposed directly. While `DEMO_NOINDEX=true` (the default), responses include `X-Robots-Tag: noindex` so search engines don't list fictional allergen data. The **Privacy & terms** page is linked in the footer.

## Verification

```sh
php artisan test --compact
npm run build
npm run test:browser
```

PHP tests use isolated in-memory SQLite and cover public search/detail routes, missing and archived products, request limits, output escaping, admin authorization, and catalog validation.

Browser tests use installed Google Chrome and the running Herd site. They change browser-local data only, without editing the development catalog. Set `PLAYWRIGHT_BASE_URL` to override the URL. They cover allergen highlights, saved-list recovery and synchronization, voice selection using simulated browser voices, themes, phone layouts, camera denial, and service-worker offline behavior. Screenshots are saved under the ignored `storage/app/testing/` directory.

Physical camera barcode detection, actual device voices, and installation on real iPhone/Android devices still need device testing. Automated checks simulate denied camera permission and browser speech output.
