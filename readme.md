This implementation sentry tracing using logs.

**Install**

**for gitlab**

you should add the repository to your project

```bash
    composer config repositories.<gitlab domain>/109 '{"type": "composer", "url": "https://<gitlab domain>/api/v4/group/109/-/packages/composer/packages.json"}'
```

__________________________________________________
if you have the error 404
you should add auth.json into composer.json directory by command and add this file to .gitignore

```bash
    composer config gitlab-token.<DOMAIN-NAME> <personal_access_token>
```

--------------------------------------------------
**Configure:**

add code block below to your params.php
and type your DSN
also you can define your environment and release, for example TAG from gitlab.ci
```php 
    'sentry' =>
        [
            'options' => [
                'dsn' => '',
                'environment' => 'local', //SENTRY_ENVIRONMENT, //YII_ENV,
                'release' => 'dev',  //SENTRY_RELEASE, //TAG
                // @see: https://docs.sentry.io/platforms/php/configuration/options/#send-default-pii
                'send_default_pii' => true,
                'traces_sample_rate' => 1.0,
            ],
            'log_level' => 'warning',
            'tracing'          => [
                // Indicates if the tracing integrations supplied by Sentry should be loaded
                'default_integrations'   => true,
            ],
        ]
```

add APP_START_TIME const into index.php and yii.php
```php
define('APP_START_TIME', microtime(true));
```

add log targets for breadcrumbs and tracing in app/config/common/logger.php
or another config file with logger settings

```php 
return [
    LoggerInterface::class => static function (
        /** your_another_log_target $your_log_target */
        SentryBreadcrumbLogTarget $sentryLogTarget,
        SentryTraceLogTarget $sentryTraceLogTarget
    ) {
        return new Logger([
        /** $your_log_target */
            $sentryLogTarget,
            $sentryTraceLogTarget
        ]);
    }
];
```
**if you want to see your logs in sentry timeline**, you need to use keys (float)'**time**' and (float)'**elapsed**' in log context array
_____

add DB log decorator for tracing db queries in app/config/params.php
```php
'yiisoft/yii-cycle' => [
        // DBAL config
        'dbal' => [
            // SQL query logger. Definition of Psr\Log\LoggerInterface
            // For example, \Yiisoft\Yii\Cycle\Logger\StdoutQueryLogger::class
            'query-logger' => \Webvork\Yii3\Sentry\DbLoggerDecorator::class,
            /**
            * ...
            * your another db settings 
            **/
    ]
]
```


if you want to trace guzzle requests and add sentry headers to external queries, add this into your config\httpClient.php
or into another factories config file

```php 
    GuzzleHttp\Client::class => static function (ContainerInterface $container) {
        $stack = new HandlerStack();
        $stack->setHandler(new CurlHandler());
        $factory = $container->get(GuzzleMiddlewareFactory::class);
        $middleware = static function (callable $handler) use ($factory): callable {
            return $factory->factory($handler);
        };

        $stack->push($middleware);

        return new GuzzleHttp\Client([
            'handler' => $stack,
        ]);
    },
```


if your transaction too heavy you can slice it to several transactions with clearing log buffer.

use SentryConsoleTransactionAdapter or SentryWebTransactionAdapter

for example:

```php
        /** some code with default transaction */
        /** commit default transaction and send data to sentry server */
        $sentryTraceString = $this->sentryTransactionAdapter->commit();
        while ($currentDate <= $endDate) {
            $this->sentryTransactionAdapter->begin($sentryTraceString)
                ->setName('my_heavy_operation/iteration')
                ->setData(['date' => $currentDate->format('Y-m-d')]);

            $this->process($currentDate, $sentryTraceString);
            $this->sentryTransactionAdapter->commit();
        }
        $this->sentryTransactionAdapter->begin($sentryTraceString)
            ->setName('my_heavy_operation done, terminating application');
    /** transaction will commit when application is terminated */
```
for this example all new transactions will linked to transaction with $sentryTraceString
