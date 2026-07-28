<?php

namespace App\Logging;

use Monolog\Logger;

final class CreatePostHogLogger
{
    /**
     * Create the Laravel custom logging channel used by managed instances.
     */
    public function __invoke(array $config): Logger
    {
        return new Logger('posthog', [new PostHogLogHandler(
            token: (string) ($config['token'] ?? ''),
            host: (string) ($config['host'] ?? 'https://eu.i.posthog.com'),
            level: Logger::toMonologLevel($config['level'] ?? 'debug'),
        )]);
    }
}
