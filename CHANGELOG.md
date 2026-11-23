# Changelog

All notable changes to EASM are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Version numbers come from the `VERSION` file (set with `composer milestone:bump -- vX.Y.Z`). The git tag must be `v` plus that value.

## [1.4.1] - 2026-11-23

### Fixed

- Dark-mode contrast on the installer, public pages (including partner embed) and the login form.
- Bulk observations: saving the same animal, type and day twice updates the existing row instead of creating a duplicate.
- Starter observation types use real species names (for example Royal python), so they match production animals; untouched demo-labelled filters are upgraded on re-seed, and admin-edited filters are left alone.
- `release-package.yml` fails if the shared-hosting zip or its `.minisig` is missing from the published Release.

### Changed

- Added this changelog, a release checklist, and clearer one-milestone-branch shipping notes for agents.

## [1.4.0] - 2026-11-23

Shelter dashboard and change timeline. See the [GitHub Release](https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/releases/tag/v1.4.0).

[1.4.1]: https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/releases/tag/v1.4.1
[1.4.0]: https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/releases/tag/v1.4.0
