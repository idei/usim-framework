# Changelog

All notable changes to this package will be documented in this file.

The format is based on Keep a Changelog, and this project follows Semantic Versioning.

## [Unreleased]

### Added
- Database translation architecture based on identifier keys with language-specific values and optional media payload (`usim_languages`, `usim_text_keys`, `usim_text_values`).
- New package models: `UsimLanguage`, `UsimTextKey`, and `UsimTextValue`.
- New `TranslationService` with CRUD operations for languages, keys, and text/media values.
- New global helper `t(string $key, array $params = [], ?string $language = null): string`.
- New i18n autokey suggestion logging when human-readable text is passed to `t(...)`, including generated key plus source file/line/character context.
- New installer stubs: translation migrations and seeders (`UsimLanguageSeeder`, `UsimTranslationSeeder`).

### Changed
- `usim:install` database scaffolding logic was extracted into a dedicated concern (`InstallsDatabaseScaffolding`) to keep `InstallCommand` smaller and easier to maintain.
- `UsimSeeder` now orchestrates translation seeders in addition to role/user seeders.
- Package defaults and installer stubs now resolve user-facing text via `t('usim...')` keys (dialogs, time units, auth/admin/menu screens, modal scaffolding, and service messages) instead of hardcoded literals.
- `UsimTranslationSeeder` stub now includes a comprehensive baseline keyset for scaffolded UI and service responses under `usim.component.*`, `usim.dialog.*`, `usim.time_unit.*`, and `usim.*` namespaces.

### Fixed
- Translation resolution now falls back to English by default and then to Laravel's standard translator before returning the key.
- Auto-generated key length is now configurable (`ui-services.i18n.auto_key_max_length`) with smart truncation that prefers completing the current token up to the next separator.
- Human-text auto-key generation now normalizes escaped and platform line breaks before deriving keys and storing fallback values.
- Table refresh cycles now reset render-affecting cell state (styles, colors, media/button payloads) before hydrating new row data, ensuring incremental diffs are emitted when visual-only cell changes occur.
- `usim:install` now registers `vendor/idei/usim/src/Support/helpers.php` in the consumer `composer.json` under `autoload.files` when missing.
- Confirm dialog messages now render escaped `\\n` as real line breaks in the frontend and support markdown-to-HTML formatting through `LabelBuilder::markdown()`.

## [0.7.0] - 2026-03-28

### Added
- `UIContainer` appearance API: `appearance(string $appearance)`, `card()`, and `plain()` fluent methods to control container visual style; default appearance is `card`.
- Carousel and calendar components now consume CSS theme tokens for consistent light/dark styling.

### Changed
- `ConfirmDialogService` and `RegisterDialog` scaffolding updated to use `plain()` container appearance.
- Admin Dashboard stub significantly refined: improved user feedback handling, clearer table layout, and role management flow.
- `UserService` stub enhanced with stronger field validation and role management logic.
- All auth screen stubs (`Login`, `EmailVerified`, `ForgotPassword`, `ResetPassword`) updated to use `plain()` container appearance for a consistent look.
- `EditUserDialog` and `Admin\Dashboard` stubs updated to use `plain()` appearance.

### Fixed
- `AbstractUIService` now always persists the container state after `postLoadUI()`, including on `?reset=true` reloads, preventing stale cache snapshots.
- Checkbox `checked` state now syncs correctly in the UI renderer when the server sends incremental changes.

## [0.6.0] - 2026-03-26

### Added
- Theme-switching support in the backend/frontend contract via `AbstractUIService::changeTheme()` and UI renderer handling for light/dark mode updates.
- Persistent theme tokens and assets for light/dark mode (`resources/assets/css/ui-theme-tokens.css`, `resources/assets/images/theme-icon-*.svg`).
- New package landing view stub with dynamic theme-aware styling (`stubs/views/landing.blade.php`).
- Rich label HTML rendering through `LabelBuilder::html()`, including safe rendering of existing backend Blade views into label content.
- New positioning helpers for components and containers, including anchor-based positioning and offset APIs such as `position()`, `positionMode()`, `offsetX()`, `offsetY()`, and directional helpers like `top()` / `right()` / `bottom()` / `left()`.
- Card theme variants and theme assets for light/dark UI toggles in the default package UI.
- New `app_id` package configuration (from `APP_ID`) to scope client storage keys per application.

### Changed
- Default USIM styles now rely more heavily on CSS variables for more consistent theming across renderer components and package-provided screens.
- Package screen scaffolding was refreshed with simplified layouts, updated button styling, theme toggle support, and richer label rendering for auth and home flows.
- Storage variables declared with the `store_` prefix are now serialized in plain JSON by default instead of being encrypted as a full opaque payload.
- Sensitive persisted values must now opt in to protection by using the `_crypt` suffix, for example `store_token_crypt` or `store_password_crypt`.
- The storage contract now supports finer-grained client synchronization, allowing clients to inspect plain values such as `store_theme` and update local storage using incremental changes instead of replacing the entire encrypted blob on every response.
- `PrepareUIContext` and `UIChangesCollector` were aligned with the new storage contract to simplify state handling and incremental updates.

### Fixed
- UI styling now accepts numeric shadow values in refreshed screen scaffolding without requiring string-only shadow configuration.

## [0.5.0] - 2026-03-20

### Added
- Email verification and password reset Blade view stubs (`stubs/views/emails/verify-email.blade.php`, `stubs/views/emails/reset-password.blade.php`).
- Terms and conditions Blade view stub (`stubs/views/terms.blade.php`) with a migration stub `add_terms_accepted_at_to_users_table.php.stub` that adds a nullable `terms_accepted_at` datetime column to the users table.
- `EventServiceProvider` stub (`stubs/providers/EventServiceProvider.php.stub`) for app-level event/listener registration.
- `ClassModifier` utility now exposes four new static methods: `addTraitToClass()`, `addInterface()`, `addPropertyArrayValue()`, and `addCast()` — enabling programmatic, AST-based modification of any PHP class during installation.
- `AbstractUIService` component lookup helpers: `findComponentAs()` and `findRootComponentAs()` for typed retrieval of child components.
- Test scaffolding stubs: `stubs/tests/Support/usim_bootstrap.php.stub` and `stubs/tests/Traits/UsimTestHelpers.php.stub`.
- `UsimSeeder` stub (`stubs/seeders/UsimSeeder.php.stub`) to orchestrate `UsimRoleSeeder` and `UsimUserSeeder` in a single seeder call.
- New required composer dependencies: `nikic/php-parser: ^5.7`, `symfony/var-dumper: ^6.0|^7.0`, `illuminate/contracts: ^10.0|^11.0|^12.0`.
- `UserService` stub emits email verification and profile update events for UI synchronization.
- UI renderer (`ui-renderer.js`) now supports comprehensive CSS grid properties (`grid-template-columns/rows/areas`, `grid-auto-columns/rows/flow`) and flex layout properties (`flex-direction`, `flex-wrap`), as well as background image/size/position styling.

### Changed
- **Breaking:** Seeder stubs renamed from `RoleSeeder` → `UsimRoleSeeder` and `UserSeeder` → `UsimUserSeeder` to avoid class-name collisions in consumer projects.
- User model stub now uses the `UsimUser` trait for password reset and email verification notifications instead of inline method overrides.
- User model stub adds `terms_accepted_at` to `$fillable` and `$casts` (as `datetime`).
- `usim:install` now programmatically modifies the consumer's `User` model (adds traits, interfaces, properties, casts) via `ClassModifier` instead of overwriting the file.
- `usim:install` post-install guidance improved with clearer migration and seeding instructions.
- API auth routes installation commented out in `InstallCommand` pending a dedicated auth-routes refactor.
- `UsimUserSeeder` now only skips seeding when `User::count() > 0` (was `> 1`), so a single existing user prevents re-seeding.

## [0.4.0] - 2026-03-15

### Added
- Admin Dashboard screen stub (`App\UI\Screens\Admin\Dashboard`) with full user management: search, paginated user table, create, edit, and delete users with role assignment.
- `EditUserDialog` modal component stub for inline user editing from the Admin Dashboard.
- `UserApiTableModel` data table component stub (`App\UI\Components\DataTable`) for paginated, searchable user lists backed by `UserService`.
- `upload_disk` configuration key (`config/ui-services.php`) to set the filesystem disk used for uploads. Defaults to `local`; override via `UPLOAD_DISK` env variable.
- `UPLOAD_DISK=local` entry added to the published `.env` template.

### Changed
- `usim:install` now exposes a single unified installation flow; the `minimal` preset and `--preset` option were removed.
- `usim:install` now scaffolds the Admin Dashboard screen in `App\UI\Screens\Admin` alongside the core Home, Menu, and auth screens.
- Menu scaffold updated to show a link to the Admin Dashboard for authenticated users.
- `UserService` stub significantly expanded with `findUser`, `getUser`, `updateUser`, and `createUser` methods including field validation, role management, and email notification handling.
- `UploadController` and `UploadService` now use the configurable `upload_disk` key instead of the hardcoded `uploads` disk name.

### Removed
- `MenuMinimal.php.stub` — removed alongside the minimal preset.

## [0.3.2] - 2026-03-13

### Fixed
- USIM routes now always include `PrepareUIContext` middleware from the package to avoid host-app bootstrap coupling.
- Initial UI loads now normalize missing or invalid storage payloads to an empty array, preventing `UIChangesCollector::setStorage()` type errors.
- `usim:install` now disables the default Laravel `/` welcome route when adding the USIM catch-all route to avoid route conflicts.

## [0.3.1] - 2026-03-13

### Fixed
- Package CI stub lint now validates only `*.php.stub` files and replaces template placeholders before running `php -l`.

## [0.3.0] - 2026-03-13

### Added
- Service-layer scaffolding stubs for auth and user flows: `App\Services\Auth\AuthSessionService`, `LoginService`, `RegisterService`, `PasswordService`, and `App\Services\User\UserService`.
- Test scaffolding stubs under `stubs/tests` including `Pest.php`, `TestCase.php`, UI test support helpers, and feature test templates for home/menu, login, password recovery, and auth event contracts.
- Full preset installer publishing for test scaffolding files into the consumer `tests/` directory.

### Changed
- Refreshed core scaffolding stubs to match the current working app architecture: `Home`, `Menu`, auth screens, `AuthController`, `User` model, and web catch-all route stubs.
- Installer full preset now publishes service stubs before auth screens to keep generated code dependencies coherent.
- Seeder scaffolding updated from `UsimUserSeeder` to `UserSeeder`, including installer references and post-install guidance.
- User model scaffolding no longer injects the `UsimUser` trait automatically; default model stub now uses explicit notification methods for reset/verification flows.

## [0.2.0] - 2026-03-12

### Added
- `usim:install` command with `minimal` and `full` presets.
- Scaffolding stubs for screens, auth screens, modals, routes, config, seeders, and migrations.
- `Idei\Usim\Traits\UsimUser` trait for user notification integration.
- Carousel, uploader, calendar, image crop editor, and expanded table/form UI components.
- Upload handling, temporary upload cleanup job, and package routes/controllers for UI and upload flows.
- Package CI workflow, release checklist, release automation script, and consumer upgrade guide.
- Comprehensive package README, testing guide, contract docs, and mobile client templates.

### Changed
- `UIEventController` now extends `Illuminate\Routing\Controller` to avoid app-level namespace coupling.
- Installer `.env` handling now appends only missing keys to avoid duplicated environment variables.
- Demo mode handling now relies on environment configuration for local testing.
- Table pagination and loading-state behavior were improved in `TableBuilder` and the UI renderer.
- Package metadata, dependencies, and public release structure were updated for Packagist distribution.

### Fixed
- Request-scoped `UIChangesCollector` reset to stabilize UI scenario event flow.
- Dynamic internal URL/port handling for notifications and verification flows.
- Installer test coverage and generated screen validation for `Menu.php` and related scaffolding outputs.

