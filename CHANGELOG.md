# Changelog

All notable changes to WilyCode Template Translation are documented here.

## [0.1.0] - 2026-10-16

### Added

- Initial public Composer package `wilycode/template-translation`.
- Template-aware translation, pluralization, global translations, type and status filters.
- Optional locale date, time, money and number formatting filters.
- GitHub Actions PHPUnit workflow, README and integration tests.

### Fixed

- Use the published `wilycode/translation` dependency and `WilyCode\Translation` contracts.
- Normalize enum translation values consistently across filters.
- Reject invalid and non-finite numeric values instead of silently displaying zero.
- Make WilyCode Router optional, retaining route-name fallback when a Router is provided.
- Support compatible WilyCode Router 0.1 and 0.2 releases when used.
- Remove redundant pass-through translation helpers and outdated translation loading instructions.

### Compatibility

- Preserved `TemplateTranslationExtension` PHP namespace, constructor argument order and registered filter names. Existing Router integrations continue to work.
