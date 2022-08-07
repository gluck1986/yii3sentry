<?php

declare(strict_types=1);

/** @var array $params */

use Sentry\Transport\DefaultTransportFactory;
use Sentry\Transport\TransportFactoryInterface;

return [
    \Webvork\Yii3\Sentry\YiiSentryConfig::class    => [
        '__construct()' => [
            'config' => $params['sentry'],
        ],
    ],
    TransportFactoryInterface::class => DefaultTransportFactory::class,
    \Sentry\HttpClient\HttpClientFactoryInterface::class => [
        'class' => \Sentry\HttpClient\HttpClientFactory::class,
        '__construct()' => [
            'sdkIdentifier' => \Sentry\Client::SDK_IDENTIFIER,
            'sdkVersion' => \Composer\InstalledVersions::getPrettyVersion(
                'sentry/sentry'
            ),
        ],
    ],
    \Sentry\Options::class => [
        'class' => \Sentry\Options::class,
        '__construct()' => [
            $params['sentry']['options'],
        ],
    ],
    \Sentry\State\HubInterface::class => \Sentry\State\Hub::class,
];
