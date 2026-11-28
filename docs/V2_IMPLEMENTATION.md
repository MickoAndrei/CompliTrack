# CompliTrack V2 — integration and local demonstration

## Status

This is an implementation increment on the uploaded CompliTrack source (not a separate mockup application). The original Laravel/MongoDB services, Flutter repositories, realtime service, README, documentation and YAML files remain in place. The ZIP is not a built APK or a deployed database. Do not replace a running installation with it until the local integration tests pass.

### Implemented
- Shared web portal shell, per-user collapsible sidebar, centered content, navy/gold design tokens, status badges, modal backdrop, scrollbar and notification styling.
- Shared Flutter four-tab navigation and route-aware selection, navy header, account avatar, status colors, modal backdrop, real notification preview with pagination/read/clear actions.
- Mobile audit register, assignment detail, checklist preparation and evidence-review actions, submission and ATL accept/return, and plan list/decision screens, connected to scoped Laravel routes. Existing workflows and authorization are retained.
- An opt-in local synthetic dataset using the existing MongoDB collections: 48 accounts (1 super admin, 2 ATL, 10 auditors, 35 auditees), five academic departments and one office, four approved plans, 40 assignments, 120 checklist items including unevaluated items, 24 CARs and encrypted generated PDF/XLSX records. The generator creates a consolidated 14-column CAR register and PDF counterpart. No legacy mock kit import, no genuine QMS document reproduction, and no real email recipients. All generated documents identify themselves discreetly as simulations. Counts are intended seed outcomes, not observed MongoDB results.

### Not yet complete or verified
- Full screen-by-screen Figma visual parity, complete new plan-creation wizard and revised automated assignment generation, report-upload/publishing workflow, new participant-selection UI, university-wide organization scope redesign, full file-version and notification parity, and a full end-to-end CAR approval implementation remain follow-up work. Existing capabilities are preserved; workspace scope is shown as a label, not an unrestricted switch.
- PHP and JS syntax and the standalone PDF/XLSX writer were checked locally. Composer, MongoDB extension/server, Flutter/Dart SDK and cached frontend dependencies were unavailable; no Laravel integration test, actual seed run, frontend build, APK, or live-device verification was performed. Existing tests may need updates for intentionally changed navigation.
- Existing dataset kit remains archived in the source for backward compatibility; V2 does not import it. New records are created only by running the guarded seeder. PDF and XLSX formats are generated; no DOCX generator is included in this increment.

## Setup (Windows PowerShell; isolated checkout)

Extract the ZIP into a NEW directory, not over the original project. Start a local MongoDB deployment. Use the V2 example configuration; never copy an existing production `.env` or run a wipe against the existing database.

```powershell
Copy-Item .env.v2.example .env
composer install
npm ci
php artisan key:generate
php artisan jwt:secret
```

In `.env`, ensure `COMPLITRACK_V2=true`, `MONGODB_DATABASE=complitrack_v2`, `MONGODB_LOCAL_DATABASE=complitrack_v2`, and the URI points to localhost or 127.0.0.1. Set `COMPLITRACK_V2_SEED_PASSWORD` to a private, unique 16+ character password for the fictional accounts. Generate a NEW 32-byte encryption key, for example `php -r "echo base64_encode(random_bytes(32));"` and set `ENCRYPTION_KEY=base64:<generated value>`. Set `APP_URL=http://127.0.0.1:8000`. Keep remote Mongo settings empty and mail on log. Do not share passwords or secrets.

Run `database/mongodb/init-v2.js` in MongoDB Compass Playground or mongosh on the local deployment (NOT legacy init.js). The script creates collections/indexes in `complitrack_v2`. The guarded seeder creates organizational records directly, so the legacy QMS migration/import is not needed to create the synthetic dataset.

```powershell
php artisan config:clear
php artisan db:seed --class='Database\Seeders\V2\V2DatabaseSeeder'
npm run build
php artisan serve --host=0.0.0.0 --port=8000
```

Login using `rafael.m.villareal@example.test` (Super Admin), `angela.r.mendoza@example.test` (ATL), `adrian.a.navarro@example.test` (Auditor), or `camille.a.rosales@example.test` (Auditee), with the configured seed password. The dataset uses fictional names and reserved domains. The seed guard rejects nonlocal MongoDB, a wrong database, missing explicit opt-in, weak passwords, and existing operational data. A completed run is a no-op. If a partial run fails, inspect the isolated environment; do not run any automatic destructive reset. Database data and encrypted files are not included in the ZIP.

Use `realtime/.env.v2.example` as realtime `.env`; ensure the local database name and JWT secret match Laravel, then `npm ci` and run the existing realtime start command from its package.json. Use `mobile/.env.example` as `mobile/.env` and configure API/realtime URLs for the emulator/physical phone. From mobile directory run `flutter pub get`, `flutter analyze`, `flutter test`, `flutter run`.

## Verification before deployment

- Run `php artisan test` against an isolated testing database, `php artisan view:cache`, `npm run build`, realtime tests, `flutter analyze` and `flutter test`.
- Verify each role login, sidebar expand/collapse, mobile route selection, notifications, QMS open/download authorization, plan approval, assignment scope, checklist submit/return, accepted finding and CAR lifecycle. Confirm users cannot access records from other scopes by direct URL or API calls.
- Confirm archive decryption and checksums, the 14 CAR fields, seed counts, no outward messages, and no writes to the original database/storage.
- Keep the original ZIP unchanged and use a distinct database, storage directory and keys. Do not mix old and new archives.

## Naming

PHP classes PascalCase; Laravel view/JS/CSS files kebab-case; Dart source snake_case; BSON fields retain existing camelCase and ObjectId references. Shared styles live in `public/css/v2/portal.css`; shell behavior in `resources/js/components/portal-shell.js`; mobile shared widgets in `mobile/lib/presentation/widgets/v2`; mobile audit screens in `mobile/lib/presentation/screens/audits`; isolated seeder in `database/seeders/V2`. Existing paths remain intact to avoid breaking routes and imports.
