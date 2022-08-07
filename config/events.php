<?php

declare(strict_types=1);

use Webvork\Yii3\Sentry\Tracing\EventTraceHandler;
use Yiisoft\Middleware\Dispatcher\Event\AfterMiddleware;
use Yiisoft\Middleware\Dispatcher\Event\BeforeMiddleware;
use Yiisoft\Yii\Http\Event\ApplicationShutdown;

if (empty($params['sentry']['options']['dsn'])) {
    return [];
}

return [
    ApplicationShutdown::class => [
        [EventTraceHandler::class, 'listen'],
    ],
    BeforeMiddleware::class => [
        [EventTraceHandler::class, 'listen'],
    ],
    AfterMiddleware::class => [
        [EventTraceHandler::class, 'listen'],
    ],
];
