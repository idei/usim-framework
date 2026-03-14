# Changelog

All notable changes to this package will be documented in this file.

The format is based on Keep a Changelog, and this project follows Semantic Versioning.

## [Unreleased]

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

