<?php

declare(strict_types=1);

use App\Infrastructure\Sentry\Tracing\SentryTraceConsoleListener;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Yiisoft\Yii\Console\Event\ApplicationShutdown;
use Yiisoft\Yii\Console\Event\ApplicationStartup;

return [
    ApplicationStartup::class => [
        [SentryTraceConsoleListener::class, 'listenAppStart'],
    ],
    ConsoleCommandEvent::class => [
        [SentryTraceConsoleListener::class, 'listenBeginCommand'],
    ],
    ConsoleTerminateEvent::class => [
        [SentryTraceConsoleListener::class, 'listenCommandTerminate'],
    ],
    ApplicationShutdown::class => [
        [SentryTraceConsoleListener::class, 'listenShutdown'],
    ],
    ConsoleErrorEvent::class => [
        [SentryTraceConsoleListener::class, 'listenException'],
    ],
];
