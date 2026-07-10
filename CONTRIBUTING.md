# Contributing

Thanks for considering contributing to Plugin Marketplace for Pelican.

## Getting set up

1. Fork and clone this repository.
2. For UI/integration work, copy it into a real Pelican panel checkout as `plugins/plugin-marketplace` and follow [docs/INSTALLATION.md](docs/INSTALLATION.md).
3. For logic-only work, you can iterate against just this repo - see [docs/DEVELOPER.md](docs/DEVELOPER.md) for running the standalone test suite (`cd plugin-marketplace && composer install && vendor/bin/pest`).

## Before opening a PR

- Run the test suite (`vendor/bin/pest`) and add tests for any new/changed behavior in `src/Services/`, `src/Data/`, or `src/Enums/` - those are the parts of this plugin that can be tested in isolation (see docs/DEVELOPER.md for why the Filament/Job layer can't be).
- Run Pint against the host panel's config if you have access to one (`composer pint`), or format consistently with the rest of the codebase by hand otherwise: no unused imports, one class per file, PSR-4 matching directory structure.
- If you're touching a repository client (`src/Services/Repositories/*.php`), verify field names against the **live** API rather than trusting documentation alone - all three upstream APIs have subtleties documentation doesn't fully capture (see the comments at the top of each client class). Update the fixtures in the corresponding `tests/Feature/*ClientTest.php` to match.
- Update the relevant doc in `docs/` if you change behavior a user or developer would need to know about - stale docs are worse than no docs.
- Update `CHANGELOG.md`.

## Code style

- PHP 8.3+, typed everywhere (parameters, returns, properties).
- Constructor property promotion + `readonly` for DTOs (`src/Data/`).
- Dependency injection over `app()`/facades inside services where practical - see `src/Contracts/DaemonFileRepositoryFactory.php` for the pattern used to keep Wings-dependent services testable.
- No magic strings for anything that has an enum already (`MarketplaceRepository`, `InstallJobStatus`, etc.) - add a case instead of comparing raw strings.
- Match the host Pelican codebase's own conventions wherever this plugin touches something the host also does (Filament resource/page structure, permission-string format, notification patterns) - consistency with the host matters more than personal preference here, since this plugin is meant to feel native to Pelican.

## Reporting bugs / requesting features

Open an issue with:
- Pelican panel version, PHP version.
- Which repository (Hangar/Modrinth/SpigotMC) and plugin, if relevant - upstream API responses vary a lot in completeness.
- Panel logs (`storage/logs/laravel.log`) around the time of the issue - most failures in this plugin `report()` the underlying exception before showing a generic notification.
