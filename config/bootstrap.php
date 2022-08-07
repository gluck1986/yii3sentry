<?php

declare(strict_types=1);

use Webvork\Yii3\Sentry\HubBootstrapper;
use Psr\Container\ContainerInterface;

return [
    static function (ContainerInterface $container) {
        $bootstrapper = $container->get(HubBootstrapper::class);
        $bootstrapper->bootstrap();
    },
];
