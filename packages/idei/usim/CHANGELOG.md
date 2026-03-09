# Changelog

All notable changes to this package will be documented in this file.

The format is based on Keep a Changelog, and this project follows Semantic Versioning.

## [Unreleased]

### Added
- `usim:install` command with `minimal` and `full` presets.
- Scaffolding stubs for screens, auth screens, modals, routes, config, seeders, and migrations.
- `Idei\Usim\Traits\UsimUser` trait for user notification integration.
- Comprehensive package README with usage and architecture guides.

### Changed
- `UIEventController` now extends `Illuminate\Routing\Controller` to avoid app-level namespace coupling.
- Installer `.env` handling now appends only missing keys to avoid duplicated environment variables.
