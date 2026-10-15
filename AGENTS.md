# WilyCode Template Translation — Agent Instructions

Before modifying this package, read [README.md](README.md).

- Keep the integration independent of WilyApp and translation storage implementations.
- Preserve the `TemplateTranslationExtension` PHP API and existing filter names.
- Use the published `wilycode/translation` interfaces and keep Composer constraints consistent.
- Keep resource resolution tied to the root template when rendering.
- Add tests for any changes to resource selection, pluralization or locale formatting.
- Update README.md and CHANGELOG.md when behavior or dependencies change.
- Run `composer test` before publishing.
