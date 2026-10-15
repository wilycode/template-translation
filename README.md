# WilyCode Template Translation

[![PHP 8.4+](https://img.shields.io/badge/PHP-8.4%2B-777BB4)](https://www.php.net/)
[![MIT License](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Tests](https://github.com/wilycode/template-translation/actions/workflows/tests.yml/badge.svg)](https://github.com/wilycode/template-translation/actions/workflows/tests.yml)

**Translation, pluralization and optional locale formatting filters for the [WilyCode Template](https://github.com/wilycode/template) engine, with resource selection based on the rendered view.**

> ### WilyCode PHP Ecosystem
>
> This library is part of **[WilyCode](https://github.com/wilycode/wilycode)**, a collection of independent PHP components. Each package has its own repository, Composer installation and releases.
>
> **[Explore the WilyCode ecosystem →](https://github.com/wilycode/wilycode)**

This is an integration package. It does not replace a translator, store translations, or change the `.tpl` language.

## Requirements

- PHP 8.4+
- `wilycode/template:^0.1`
- `wilycode/translation:^0.1.1` (translation contracts and locale formatting)
- **Optional:** `wilycode/router:^0.1 || ^0.2` (only needed for route-name fallback outside a template render)

## Installation

```bash
composer require wilycode/template-translation
```

## Registration

Supply an implementation of `WilyCode\Translation\TranslatorInterface`. **A Router is not required**:

```php
use WilyCode\Template\Extension\TemplateTranslationExtension;

$engine->addExtension(new TemplateTranslationExtension(
    translator: $translator,
    defaultResource: 'messages',
    formatter: $localeFormatter, // Optional: omit when locale formatting is not needed
));
```

If your application already uses WilyCode Router and you want to fall back to the current route name **when translation filters run outside `render()`**, pass it as an optional argument:

```php
$engine->addExtension(new TemplateTranslationExtension(
    translator: $translator,
    router: $router, // Optional WilyCode\Router\Router
));
```

To enable the route-name fallback, install the integration separately:

```bash
composer require wilycode/router:^0.2
```

Existing positional calls such as `new TemplateTranslationExtension($translator, $router, 'messages', $formatter)` remain supported.

The PHP class and namespace of the extension remain `WilyCode\Template\Extension\TemplateTranslationExtension`.

## Translation resource selection

The `trans`, `ptrans`, `ttrans` and `strans` filters select resources in this order:

1. Explicit filter resource, if provided.
2. Name of the **root template** currently rendered (`TemplateEngine::getCurrentTemplateName()`).
3. Current route name, **only if a Router was supplied** (`Router::getCurrentRoute()?->getName()`).
4. The configured `defaultResource` (`messages` by default).

For example, rendering `article.form` selects the logical resource `article.form`, even when the route is called `article.add` or `article.edit`.

```html
<h1>{{ 'page_title' | trans }}</h1>
```

A translator implementation decides how logical resource names map to files or database entries; a filesystem translator might map `article.form` to `languages/pl/article/form.php`.

Includes and layout rendering preserve the root template resource. Outside `render()`, the current route is used if a Router was supplied; otherwise the configured default resource is used. Normal translations during `render()` work identically with or without a Router.

## Filters

| Filter | Purpose | Example |
| --- | --- | --- |
| `trans` | Translate using selected resource | `{{ 'title' \| trans }}` |
| `ptrans` | Translate plural form, injecting `{count}` | `{{ 'items' \| ptrans($count) }}` |
| `gtrans` | Translate with `globalResource` | `{{ 'save' \| gtrans }}` |
| `gptrans` | Global plural translation | `{{ 'items' \| gptrans($count) }}` |
| `ttrans` | Translate `type.<group>.<value>` | `{{ $type \| ttrans('article') }}` |
| `strans` | Translate `status.<group>.<value>` | `{{ $status \| strans('article') }}` |

These examples use WilyCode `.tpl` syntax:

```html
<h1>{{ 'page_title' | trans }}</h1>
<p>{{ 'welcome' | trans(null, ['name' => $userName]) }}</p>
<p>{{ 'items' | ptrans($count) }}</p>
<p>{{ 'title' | trans('shared.navigation') }}</p>
<p>{{ 'save' | gtrans }}</p>
<p>{{ 'items' | gptrans($count) }}</p>
<p>{{ $type | ttrans('article') }}</p>
<p>{{ $status | strans('article') }}</p>
```

`ptrans` and `gptrans` automatically set the `count` parameter to the actual count, even if the supplied parameters array also contains a `count` key.

Global filters use the configured `globalResource` (default: `global`) without prefixing the translation key. So `{{ 'common.yes' | gtrans }}` requests resource `global`, key `common.yes`.

Both explicit resource slashes and namespace-style separators are normalized to dot notation, e.g. `shared/navigation` → `shared.navigation`.

Backed enums are translated using their `value`, unit enums using their `name`.

## Optional locale formatting

If `formatter` implements `WilyCode\Translation\LocaleFormatterInterface`, the following filters and functions are also registered:

| Filter or function | Example |
| --- | --- |
| `ldate` | `{{ $date \| ldate }}` |
| `ldate` (long style) | `{{ $date \| ldate('long') }}` |
| `ldatetime` | `{{ $dateTime \| ldatetime('short') }}` |
| `lmoney` | `{{ $amount \| lmoney('PLN', 2) }}` |
| `lnumber` | `{{ $number \| lnumber(2) }}` |
| `locale_code` | `@locale_code()` |
| `locale_date_format` | `@locale_date_format('medium')` |

Date filters accept `DateTimeInterface`, integer Unix timestamps, or strings accepted by PHP's `DateTimeImmutable`. Styles are `short`, `medium`, or `long` (default `medium`). Numeric filters accept finite integers, floats and numeric strings. Invalid strings (such as `"abc"`) and non-finite values raise `InvalidArgumentException` instead of silently displaying `0`.

If no formatter is configured, these locale filters and functions are **not** registered. The translation filters still work.

## Translator responsibilities

The extension delegates to `TranslatorInterface::translate()` and `translatePlural()`; it does **not** load locale files, query a database, or cache messages itself. Your translator implementation is responsible for selecting a language, loading any resources it needs, fallback behavior and formatting placeholders.

To provide translation storage, consider [WilyCode Translation Database](https://github.com/wilycode/translation-database), or implement your own translator.

## Development

```bash
composer install
composer test
```

Tests run against the actual Composer dependencies (including Router as a development-only dependency). GitHub Actions also checks a production installation **without** Router. Source-only files such as `tests/`, `.github/` and `AGENTS.md` are excluded from regular Composer `dist` exports.

## Related libraries

- [WilyCode Template](https://github.com/wilycode/template) — template engine.
- [WilyCode Template Assets](https://github.com/wilycode/template-assets) — asset helpers.
- [WilyCode Template String](https://github.com/wilycode/template-string) — string and formatting filters.
- [WilyCode Translation](https://github.com/wilycode/translation) — translator contracts and formatting.

## License

MIT. See [LICENSE](LICENSE).
