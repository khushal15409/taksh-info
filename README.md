# Taksh info site

Laravel 12 website for the Taksh e-commerce app. It shares taksh-admin's MySQL database and mirrors the mobile app's logic.
**Never run `php artisan migrate` here** - the schema belongs to taksh-admin.

## Layout (one name per module)

Modules: `Account, Auth, Cart, Catalog, Checkout, Courier, Notifications, Orders, Services, Support, Wallet, Wishlist`.

| Layer | Where |
|---|---|
| Page controllers (HTML) | `app/Http/Controllers/Web/{Module}` (extend `Web\WebController`) |
| JSON endpoints used by small page scripts (same paths as the app API) | `app/Http/Controllers/Ajax/{Module}` |
| Page routes | `routes/web/*.php`, required from `routes/web.php` |
| JSON routes | `routes/{module}.php` |
| Views | `resources/views/{module}/...`, shared `layouts/`, `components/` (`<x-layout>`, `<x-modal>`, `<x-product-card>` ...) |
| Business logic | `app/Services` |
| Data access | `app/Repositories` (interface + Eloquent implementation, bound in a provider) |
| Validation | `app/Http/Requests` (JSON requests vs web requests that redirect with errors) |
| JSON shaping | `app/Http/Resources`; view-model shaping in `app/Support/Presenters` |
| Errors | `app/Exceptions` (`ApiException` subclasses render JSON or a flash toast) |
| Helpers / traits / enums | `app/Helpers`, `app/Traits`, `app/Enums` |

Route names have no `api.` prefix; tests enforce unique, well-formed names.

## Front end

Pages are server-rendered Blade; JS is small and only where needed, in `public/assets/js`
(`ui.js`, `header-state.js`, `pay.js`, `addresses.js`, ... and `modules/*.js` per page).
Header counters come from `HeaderStateService` as `data-*` attributes on `<body>`.
Hindi: `lang/hi.json`, `SetLocale` middleware (cookie `taksh_lang`), whole-text translation in `TranslateHtml`.

## Money safety

Checkout uses an idempotency token plus a per-user lock; Razorpay payments are verified by signature and by fetching the payment; settlement and refunds are idempotent.

## Configuration

Copy `.env.example` to `.env` and fill the DB, APITXT (OTP), RAZORPAY, SHIPROCKET and TAKSH_* keys. After changing it:
`php artisan config:clear route:clear view:clear`.

## Tests

`php artisan test` - repositories are replaced by in-memory fakes (`tests/Support`), so no database is needed.
