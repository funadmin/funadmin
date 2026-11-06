<?php

declare(strict_types=1);

namespace app\common\service\translation;

use Symfony\Component\Translation\Translator;

/** Expose CRUD-generated nested arrays as default-domain dotted keys. */
final class GeneratedLanguagePacks
{
    private static ?\WeakMap $registered = null;

    public static function register(Translator $translator, array $paths): void
    {
        self::$registered ??= new \WeakMap();
        if (isset(self::$registered[$translator])) {
            return;
        }
        foreach ($paths as $path) {
            foreach ((array) glob(rtrim($path, '/\\') . '/*/*.php') as $file) {
                if (basename($file) === 'messages.php') {
                    continue; // Already loaded by native Webman configuration.
                }
                $messages = require $file;
                if (!is_array($messages) || !array_filter($messages, 'is_array')) {
                    continue;
                }
                // PhpFileLoader flattens generated nested arrays without altering their keys.
                $translator->addResource('phpfile', $file, basename(dirname($file)), 'messages');
            }
        }
        self::$registered[$translator] = true;
    }
}
