<?php

declare(strict_types=1);

namespace WilyCode\Template\Tests;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use WilyCode\Router\HttpMethod;
use WilyCode\Router\Route;
use WilyCode\Router\Router;
use WilyCode\Template\Extension\TemplateTranslationExtension;
use WilyCode\Template\FileLocator;
use WilyCode\Template\TemplateEngine;
use WilyCode\Template\TemplateLoader;
use WilyCode\Translation\DateStyle;
use WilyCode\Translation\LocaleFormatterInterface;
use WilyCode\Translation\TranslatorInterface;

#[CoversClass(TemplateTranslationExtension::class)]
final class TemplateTranslationExtensionTest extends TestCase
{
    public function test_trans_uses_root_template_resource_during_render(): void
    {
        $translator = new SpyTranslator();
        $router = $this->routerWithCurrentRouteName('article.add');
        $engine = $this->newEngine([
            'article/form.tpl' => "{{ 'hello' | trans }}",
        ]);

        $engine->addExtension(new TemplateTranslationExtension($translator, $router));

        self::assertSame('article.form:hello', $engine->render('article.form'));
        self::assertSame(
            ['method' => 'translate', 'resource' => 'article.form', 'key' => 'hello', 'params' => []],
            $translator->calls[0]
        );
    }

    public function test_trans_uses_route_name_as_fallback_outside_render(): void
    {
        $translator = new SpyTranslator();
        $router = $this->routerWithCurrentRouteName('user.show');
        $engine = $this->newEngine();

        $engine->addExtension(new TemplateTranslationExtension($translator, $router));

        self::assertSame('user.show:hello', $engine->applyFilter('trans', 'hello'));
    }

    public function test_trans_works_without_router_during_template_render(): void
    {
        $translator = new SpyTranslator();
        $engine = $this->newEngine([
            'article/form.tpl' => "{{ 'hello' | trans }}",
        ]);
        $engine->addExtension(new TemplateTranslationExtension($translator));

        self::assertSame('article.form:hello', $engine->render('article.form'));
        self::assertSame('article.form', $translator->calls[0]['resource']);
    }

    public function test_trans_uses_default_resource_when_router_is_omitted(): void
    {
        $translator = new SpyTranslator();
        $engine = $this->newEngine();
        $engine->addExtension(new TemplateTranslationExtension($translator));

        self::assertSame('messages:hello', $engine->applyFilter('trans', 'hello'));
    }

    public function test_trans_uses_custom_default_resource_without_router(): void
    {
        $engine = $this->newEngine();
        $engine->addExtension(new TemplateTranslationExtension(
            translator: new SpyTranslator(),
            defaultResource: 'common/navigation',
        ));

        self::assertSame('common.navigation:hello', $engine->applyFilter('trans', 'hello'));
    }

    public function test_explicit_resource_and_global_filters_work_without_router(): void
    {
        $engine = $this->newEngine();
        $engine->addExtension(new TemplateTranslationExtension(new SpyTranslator()));

        self::assertSame('shared.nav:hello', $engine->applyFilter('trans', 'hello', 'shared/nav'));
        self::assertSame('global:save', $engine->applyFilter('gtrans', 'save'));
    }

    public function test_locale_formatter_can_be_provided_without_router(): void
    {
        $engine = $this->newEngine();
        $engine->addExtension(new TemplateTranslationExtension(
            translator: new SpyTranslator(),
            formatter: new SpyFormatter(),
        ));

        self::assertSame('en-US', $engine->callFunction('locale_code'));
        self::assertSame('number:2.50:2', $engine->applyFilter('lnumber', 2.5));
    }

    public function test_explicit_null_router_is_supported(): void
    {
        $engine = $this->newEngine();
        $engine->addExtension(new TemplateTranslationExtension(new SpyTranslator(), null));

        self::assertSame('messages:hello', $engine->applyFilter('trans', 'hello'));
    }

    public function test_trans_uses_default_resource_without_template_or_route(): void
    {
        $translator = new SpyTranslator();
        $router = $this->routerWithCurrentRouteName(null);
        $engine = $this->newEngine();

        $engine->addExtension(new TemplateTranslationExtension($translator, $router, 'messages'));

        self::assertSame('messages:hello', $engine->applyFilter('trans', 'hello'));
    }

    public function test_explicit_resource_overrides_template_and_is_normalized(): void
    {
        $translator = new SpyTranslator();
        $router = $this->routerWithCurrentRouteName('article.list');
        $engine = $this->newEngine([
            'article/list.tpl' => "{{ 'hello' | trans('shared/navigation') }}",
        ]);

        $engine->addExtension(new TemplateTranslationExtension($translator, $router));

        self::assertSame('shared.navigation:hello', $engine->render('article.list'));
    }

    public function test_ptrans_adds_count_parameter_automatically(): void
    {
        $translator = new SpyTranslator();
        $router = $this->routerWithCurrentRouteName('orders.list');
        $engine = $this->newEngine();

        $engine->addExtension(new TemplateTranslationExtension($translator, $router));

        self::assertSame('orders.list:items:3', $engine->applyFilter('ptrans', 'items', 3));
        self::assertSame(
            [
                'method' => 'translatePlural',
                'resource' => 'orders.list',
                'key' => 'items',
                'count' => 3,
                'params' => ['count' => 3],
            ],
            $translator->calls[0]
        );
    }

    public function test_gtrans_and_gptrans_use_global_resource(): void
    {
        $translator = new SpyTranslator();
        $router = $this->routerWithCurrentRouteName('article.list');
        $engine = $this->newEngine();

        $engine->addExtension(new TemplateTranslationExtension(
            translator: $translator,
            router: $router,
            globalResource: 'global',
        ));

        self::assertSame('global:save', $engine->applyFilter('gtrans', 'save'));
        self::assertSame('global:items:5', $engine->applyFilter('gptrans', 'items', 5));
        self::assertSame(['count' => 5], $translator->calls[1]['params']);
    }

    public function test_ttrans_and_strans_use_current_resource(): void
    {
        $translator = new SpyTranslator();
        $router = $this->routerWithCurrentRouteName('exam.list');
        $engine = $this->newEngine();

        $engine->addExtension(new TemplateTranslationExtension($translator, $router));

        self::assertSame('exam.list:type.exam.active', $engine->applyFilter('ttrans', 'active', 'exam'));
        self::assertSame('exam.list:status.exam.active', $engine->applyFilter('strans', 'active', 'exam'));
    }

    public function test_locale_filters_are_registered_only_when_formatter_is_provided(): void
    {
        $translator = new SpyTranslator();
        $router = $this->routerWithCurrentRouteName('dashboard');

        $formatter = new SpyFormatter();
        $engineB = $this->newEngine();
        $engineB->addExtension(new TemplateTranslationExtension($translator, $router, 'messages', $formatter));

        $dateTime = new DateTimeImmutable('2026-01-29 12:34:56');

        self::assertSame('date:2026-01-29|medium', $engineB->applyFilter('ldate', $dateTime));
        self::assertSame('datetime:2026-01-29 12:34:56|medium', $engineB->applyFilter('ldatetime', $dateTime));
        self::assertSame('money:PLN:1.00:2', $engineB->applyFilter('lmoney', 1, 'PLN', 2));
        self::assertSame('number:1.00:2', $engineB->applyFilter('lnumber', 1));
        self::assertSame('format:medium', $engineB->callFunction('locale_date_format'));
        self::assertSame('en-US', $engineB->callFunction('locale_code'));
    }

    public function test_locale_filters_are_unavailable_without_formatter(): void
    {
        $engine = $this->newEngine();
        $engine->addExtension(new TemplateTranslationExtension(
            new SpyTranslator(),
            $this->routerWithCurrentRouteName('dashboard'),
        ));

        $this->expectException(InvalidArgumentException::class);
        $engine->applyFilter('ldate', new DateTimeImmutable());
    }

    public function test_locale_functions_work_in_compiled_templates(): void
    {
        $engine = $this->newEngine([
            'index.tpl' => "@locale_code()|@locale_date_format('long')",
        ]);
        $engine->addExtension(new TemplateTranslationExtension(
            new SpyTranslator(),
            $this->routerWithCurrentRouteName('dashboard'),
            'messages',
            new SpyFormatter(),
        ));

        self::assertSame('en-US|format:long', $engine->render('index'));
    }

    public function test_ldate_and_ldatetime_support_string_style(): void
    {
        $translator = new SpyTranslator();
        $router = $this->routerWithCurrentRouteName('dashboard');
        $formatter = new SpyFormatter();
        $engine = $this->newEngine();

        $engine->addExtension(new TemplateTranslationExtension($translator, $router, 'messages', $formatter));

        $dateTime = new DateTimeImmutable('2026-01-29 12:34:56');

        self::assertSame('date:2026-01-29|long', $engine->applyFilter('ldate', $dateTime, 'long'));
        self::assertSame('datetime:2026-01-29 12:34:56|short', $engine->applyFilter('ldatetime', $dateTime, 'short'));

        self::assertSame(DateStyle::Long, $formatter->calls[0]['style']);
        self::assertSame(DateStyle::Short, $formatter->calls[1]['style']);
    }

    public function test_lmoney_and_lnumber_forward_to_formatter(): void
    {
        $translator = new SpyTranslator();
        $router = $this->routerWithCurrentRouteName('dashboard');
        $formatter = new SpyFormatter();
        $engine = $this->newEngine();

        $engine->addExtension(new TemplateTranslationExtension($translator, $router, 'messages', $formatter));

        self::assertSame('money:PLN:1234.57:2', $engine->applyFilter('lmoney', 1234.567, 'PLN', 2));
        self::assertSame('number:1234.6:1', $engine->applyFilter('lnumber', 1234.567, 1));
    }

    public function test_numeric_filters_accept_numeric_strings_without_losing_native_int_type(): void
    {
        $formatter = new SpyFormatter();
        $engine = $this->newEngine();
        $engine->addExtension(new TemplateTranslationExtension(
            new SpyTranslator(),
            $this->routerWithCurrentRouteName('dashboard'),
            'messages',
            $formatter,
        ));

        self::assertSame('money:PLN:12.50:2', $engine->applyFilter('lmoney', '12.50', 'PLN', 2));
        self::assertSame('number:7.00:2', $engine->applyFilter('lnumber', '7'));
        self::assertSame('number:11.00:2', $engine->applyFilter('lnumber', 11));
    }

    public function test_lmoney_rejects_non_numeric_strings(): void
    {
        $engine = $this->newEngine();
        $engine->addExtension(new TemplateTranslationExtension(
            new SpyTranslator(),
            $this->routerWithCurrentRouteName('dashboard'),
            'messages',
            new SpyFormatter(),
        ));

        $this->expectException(InvalidArgumentException::class);
        $engine->applyFilter('lmoney', 'not a number');
    }

    public function test_lnumber_rejects_non_numeric_strings(): void
    {
        $engine = $this->newEngine();
        $engine->addExtension(new TemplateTranslationExtension(
            new SpyTranslator(),
            $this->routerWithCurrentRouteName('dashboard'),
            'messages',
            new SpyFormatter(),
        ));

        $this->expectException(InvalidArgumentException::class);
        $engine->applyFilter('lnumber', '1,200');
    }

    public function test_lmoney_rejects_non_finite_numbers(): void
    {
        $engine = $this->newEngine();
        $engine->addExtension(new TemplateTranslationExtension(
            new SpyTranslator(),
            $this->routerWithCurrentRouteName('dashboard'),
            'messages',
            new SpyFormatter(),
        ));

        $this->expectException(InvalidArgumentException::class);
        $engine->applyFilter('lmoney', INF);
    }

    public function test_lnumber_rejects_non_finite_numeric_strings(): void
    {
        $engine = $this->newEngine();
        $engine->addExtension(new TemplateTranslationExtension(
            new SpyTranslator(),
            $this->routerWithCurrentRouteName('dashboard'),
            'messages',
            new SpyFormatter(),
        ));

        $this->expectException(InvalidArgumentException::class);
        $engine->applyFilter('lnumber', '1e309');
    }

    public function test_trans_and_ptrans_accept_backed_enum_values(): void
    {
        $translator = new SpyTranslator();
        $engine = $this->newEngine();
        $engine->addExtension(new TemplateTranslationExtension(
            $translator,
            $this->routerWithCurrentRouteName('catalog.list'),
        ));

        self::assertSame('catalog.list:active', $engine->applyFilter('trans', TranslationTestStatus::Active));
        self::assertSame('catalog.list:active:2', $engine->applyFilter('ptrans', TranslationTestStatus::Active, 2));
        self::assertSame(['count' => 2], $translator->calls[1]['params']);
    }

    public function test_shared_template_does_not_override_the_root_resource(): void
    {
        $translator = new SpyTranslator();
        $engine = $this->newEngine([
            'page/index.tpl' => "@include('partials.nav'){{ 'page' | trans }}",
            'partials/nav.tpl' => "{{ 'navigation' | trans }}",
        ]);
        $engine->addExtension(new TemplateTranslationExtension(
            $translator,
            $this->routerWithCurrentRouteName('route.other'),
        ));

        self::assertSame('page.index:navigationpage.index:page', $engine->render('page.index'));
        self::assertSame('page.index', $translator->calls[0]['resource']);
        self::assertSame('page.index', $translator->calls[1]['resource']);
    }

    public function test_global_plural_translation_overrides_explicit_count_parameter(): void
    {
        $translator = new SpyTranslator();
        $engine = $this->newEngine();
        $engine->addExtension(new TemplateTranslationExtension(
            $translator,
            $this->routerWithCurrentRouteName('stats'),
        ));

        self::assertSame('global:items:4', $engine->applyFilter('gptrans', 'items', 4, ['count' => 99]));
        self::assertSame(['count' => 4], $translator->calls[0]['params']);
    }

    /**
     * @param array<string, string> $templates
     */
    private function newEngine(array $templates = []): TemplateEngine
    {
        $base = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'wilycode-template-ext-'
            . bin2hex(random_bytes(6));
        $views = $base . DIRECTORY_SEPARATOR . 'views';
        $cache = $base . DIRECTORY_SEPARATOR . 'cache';

        if (!mkdir($views, 0777, true) && !is_dir($views)) {
            throw new \RuntimeException('Cannot create temp views directory: ' . $views);
        }

        foreach ($templates as $relativePath => $source) {
            $path = $views . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $directory = dirname($path);

            if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new \RuntimeException('Cannot create template directory: ' . $directory);
            }

            file_put_contents($path, $source);
        }

        return new TemplateEngine(
            new TemplateLoader(new FileLocator(['' => $views])),
            $cache,
        );
    }

    private function routerWithCurrentRouteName(?string $routeName): Router
    {
        $container = new class () implements ContainerInterface {
            public function get(string $id): mixed
            {
                throw new \RuntimeException('Service not found: ' . $id);
            }

            public function has(string $id): bool
            {
                return false;
            }
        };

        $router = new Router($container);

        if ($routeName === null) {
            return $router;
        }

        $route = new Route(HttpMethod::Get, '/', $routeName);
        $property = new \ReflectionProperty(Router::class, 'currentRoute');
        $property->setValue($router, $route);

        return $router;
    }
}

final class SpyTranslator implements TranslatorInterface
{
    /** @var list<array<string, mixed>> */
    public array $calls = [];


    public function translate(string $resource, string $key, array $parameters = []): string
    {
        $this->calls[] = [
            'method' => 'translate',
            'resource' => $resource,
            'key' => $key,
            'params' => $parameters,
        ];

        return $resource . ':' . $key;
    }

    public function translatePlural(string $resource, string $key, int $count, array $parameters = []): string
    {
        $this->calls[] = [
            'method' => 'translatePlural',
            'resource' => $resource,
            'key' => $key,
            'count' => $count,
            'params' => $parameters,
        ];

        return $resource . ':' . $key . ':' . $count;
    }
}

final class SpyFormatter implements LocaleFormatterInterface
{
    /** @var list<array<string, mixed>> */
    public array $calls = [];

    public function getLocale(): string
    {
        $this->calls[] = ['method' => 'getLocale'];

        return 'en-US';
    }

    public function getDateFormat(DateStyle $style = DateStyle::Medium): string
    {
        $this->calls[] = ['method' => 'getDateFormat', 'style' => $style];

        return 'format:' . $style->value;
    }

    public function formatDate(\DateTimeInterface $date, DateStyle $style = DateStyle::Medium): string
    {
        $this->calls[] = ['method' => 'formatDate', 'style' => $style];

        return 'date:' . $date->format('Y-m-d') . '|' . $style->value;
    }

    public function formatDateTime(\DateTimeInterface $dateTime, DateStyle $style = DateStyle::Medium): string
    {
        $this->calls[] = ['method' => 'formatDateTime', 'style' => $style];

        return 'datetime:' . $dateTime->format('Y-m-d H:i:s') . '|' . $style->value;
    }

    public function formatMoney(float|int $amount, ?string $currency = null, ?int $fractionDigits = null): string
    {
        $currency ??= 'XXX';
        $fractionDigits ??= 2;

        $this->calls[] = [
            'method' => 'formatMoney',
            'currency' => $currency,
            'fractionDigits' => $fractionDigits,
        ];

        return sprintf('money:%s:%0.' . $fractionDigits . 'f:%d', $currency, (float) $amount, $fractionDigits);
    }

    public function formatNumber(float|int $number, int $fractionDigits = 2): string
    {
        $this->calls[] = ['method' => 'formatNumber', 'fractionDigits' => $fractionDigits];

        return sprintf('number:%0.' . $fractionDigits . 'f:%d', (float) $number, $fractionDigits);
    }
}

enum TranslationTestStatus: string
{
    case Active = 'active';
}
