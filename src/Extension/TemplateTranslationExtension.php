<?php

declare(strict_types=1);

namespace WilyCode\Template\Extension;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use WilyCode\Router\Router;
use WilyCode\Template\TemplateEngine;
use WilyCode\Template\TemplateExtensionInterface;
use WilyCode\Translation\DateStyle;
use WilyCode\Translation\LocaleFormatterInterface;
use WilyCode\Translation\TranslatorInterface;

/**
 * Translation and locale-formatting filters for WilyCode templates.
 *
 * The default translation resource is the logical name of the root template
 * currently rendered by TemplateEngine. The current route name is used only
 * when no template render context is active and a Router is supplied.
 */
final class TemplateTranslationExtension implements TemplateExtensionInterface
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ?Router $router = null,
        private readonly string $defaultResource = 'messages',
        private readonly ?LocaleFormatterInterface $formatter = null,
        private readonly string $globalResource = 'global',
    ) {
    }

    public function register(TemplateEngine $engine): void
    {
        $engine->addFilter(
            'trans',
            function (mixed $value, ?string $resource = null, array $params = []) use ($engine): string {
                return $this->translator->translate(
                    $this->resolveResource($engine, $resource),
                    $this->normalizeKeyValue($value),
                    $params,
                );
            }
        );

        $engine->addFilter(
            'ptrans',
            function (
                mixed $value,
                int $count,
                ?string $resource = null,
                array $params = [],
            ) use ($engine): string {
                $params['count'] = $count;

                return $this->translator->translatePlural(
                    $this->resolveResource($engine, $resource),
                    $this->normalizeKeyValue($value),
                    $count,
                    $params,
                );
            }
        );

        $engine->addFilter(
            'gtrans',
            function (mixed $value, array $params = []): string {
                return $this->translator->translate(
                    $this->normalizeResource($this->globalResource),
                    $this->normalizeKeyValue($value),
                    $params,
                );
            }
        );

        $engine->addFilter(
            'gptrans',
            function (mixed $value, int $count, array $params = []): string {
                $params['count'] = $count;

                return $this->translator->translatePlural(
                    $this->normalizeResource($this->globalResource),
                    $this->normalizeKeyValue($value),
                    $count,
                    $params,
                );
            }
        );

        $engine->addFilter(
            'ttrans',
            function (
                mixed $value,
                string $group,
                ?string $resource = null,
                array $params = [],
            ) use ($engine): string {
                $key = sprintf('type.%s.%s', $group, $this->normalizeKeyValue($value));

                return $this->translator->translate(
                    $this->resolveResource($engine, $resource),
                    $key,
                    $params,
                );
            }
        );

        $engine->addFilter(
            'strans',
            function (
                mixed $value,
                string $group,
                ?string $resource = null,
                array $params = [],
            ) use ($engine): string {
                $key = sprintf('status.%s.%s', $group, $this->normalizeKeyValue($value));

                return $this->translator->translate(
                    $this->resolveResource($engine, $resource),
                    $key,
                    $params,
                );
            }
        );

        if ($this->formatter === null) {
            return;
        }

        $engine->addFilter(
            'ldate',
            function (mixed $value, DateStyle|string|null $style = null): string {
                $date = $this->coerceDateTime($value);
                $style = $this->normalizeDateStyle($style);

                return $this->formatter->formatDate($date, $style);
            }
        );

        $engine->addFilter(
            'ldatetime',
            function (mixed $value, DateStyle|string|null $style = null): string {
                $dateTime = $this->coerceDateTime($value);
                $style = $this->normalizeDateStyle($style);

                return $this->formatter->formatDateTime($dateTime, $style);
            }
        );

        $engine->addFilter(
            'lmoney',
            function (mixed $value, ?string $currency = null, ?int $fractionDigits = null): string {
                return $this->formatter->formatMoney(
                    $this->normalizeNumericValue($value, 'lmoney'),
                    $currency,
                    $fractionDigits,
                );
            }
        );

        $engine->addFilter(
            'lnumber',
            function (mixed $value, int $fractionDigits = 2): string {
                return $this->formatter->formatNumber(
                    $this->normalizeNumericValue($value, 'lnumber'),
                    $fractionDigits,
                );
            }
        );

        $engine->addFunction(
            'locale_code',
            fn (): string => $this->formatter->getLocale()
        );

        $engine->addFunction(
            'locale_date_format',
            fn (DateStyle|string|null $style = null): string => $this->formatter->getDateFormat(
                $this->normalizeDateStyle($style)
            )
        );
    }

    private function resolveResource(TemplateEngine $engine, ?string $resource = null): string
    {
        if (is_string($resource) && trim($resource) !== '') {
            return $this->normalizeResource($resource);
        }

        $templateName = $engine->getCurrentTemplateName();

        if (is_string($templateName) && trim($templateName) !== '') {
            return $this->normalizeResource($templateName);
        }

        $routeName = $this->router?->getCurrentRoute()?->getName();

        if (is_string($routeName) && trim($routeName) !== '') {
            return $this->normalizeResource($routeName);
        }

        return $this->normalizeResource($this->defaultResource);
    }

    /**
     * Keep resource identifiers in dot notation. ArrayTranslate maps dots to
     * directories, for example article.list -> article/list.php.
     */
    private function normalizeResource(string $resource): string
    {
        $resource = str_replace(['::', '/', '\\'], '.', trim($resource));
        $resource = preg_replace('/\.+/', '.', $resource) ?? $resource;

        return trim($resource, '.');
    }

    private function normalizeDateStyle(DateStyle|string|null $style): DateStyle
    {
        if ($style instanceof DateStyle) {
            return $style;
        }

        if (is_string($style)) {
            return match (strtolower(trim($style))) {
                'short' => DateStyle::Short,
                'long' => DateStyle::Long,
                default => DateStyle::Medium,
            };
        }

        return DateStyle::Medium;
    }

    private function coerceDateTime(mixed $value): DateTimeInterface
    {
        if ($value instanceof DateTimeInterface) {
            return $value;
        }

        if (is_int($value)) {
            return new DateTimeImmutable('@' . $value);
        }

        if (is_string($value)) {
            try {
                return new DateTimeImmutable($value);
            } catch (\Exception $e) {
                throw new InvalidArgumentException('Invalid date/time string passed to ldate/ldatetime.', 0, $e);
            }
        }

        throw new InvalidArgumentException('ldate/ldatetime expects DateTimeInterface|string|int.');
    }

    private function normalizeNumericValue(mixed $value, string $filter): int|float
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value) && is_finite($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            $number = (float) $value;

            if (is_finite($number)) {
                return $number;
            }
        }

        throw new InvalidArgumentException(
            sprintf('%s expects a finite numeric value (int, float, or numeric string).', $filter),
        );
    }

    private function normalizeKeyValue(mixed $value): string
    {
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        return (string) $value;
    }
}
