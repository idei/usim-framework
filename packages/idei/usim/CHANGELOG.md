# Changelog

All notable changes to this package will be documented in this file.

The format is based on Keep a Changelog, and this project follows Semantic Versioning.

## [Unreleased]

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

