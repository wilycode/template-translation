<?php

declare(strict_types=1);

use WilyCode\Template\Extension\TemplateTranslationExtension;
use WilyCode\Template\FileLocator;
use WilyCode\Template\TemplateEngine;
use WilyCode\Template\TemplateLoader;
use WilyCode\Translation\TranslatorInterface;

require dirname(__DIR__) . '/vendor/autoload.php';

if (class_exists('WilyCode\\Router\\Router')) {
    throw new RuntimeException('Router must not be installed for this smoke test.');
}

$translator = new class () implements TranslatorInterface {
    public function translate(string $resource, string $key, array $parameters = []): string
    {
        return $resource . ':' . $key;
    }

    public function translatePlural(string $resource, string $key, int $count, array $parameters = []): string
    {
        return $resource . ':' . $key . ':' . $count;
    }
};

$base = sys_get_temp_dir() . '/wilycode-routerless-' . bin2hex(random_bytes(6));
$views = $base . '/views';

if (!mkdir($views, 0777, true) && !is_dir($views)) {
    throw new RuntimeException('Could not create a temporary view directory.');
}

try {
    file_put_contents($views . '/index.tpl', "{{ 'hello' | trans }}");
    $engine = new TemplateEngine(new TemplateLoader(new FileLocator(['' => $views])), $base . '/cache');
    $engine->addExtension(new TemplateTranslationExtension($translator));

    if ($engine->render('index') !== 'index:hello') {
        throw new RuntimeException('Template rendering without Router failed.');
    }

    if ($engine->applyFilter('trans', 'hello') !== 'messages:hello') {
        throw new RuntimeException('Default resource fallback without Router failed.');
    }

    echo "Routerless installation and rendering passed.\n";
} finally {
    // Temporary smoke-test files live outside the repository and Composer dist.
    foreach (glob($base . '/cache/*') ?: [] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    @unlink($views . '/index.tpl');
    @rmdir($views);
    @rmdir($base . '/cache');
    @rmdir($base);
}
