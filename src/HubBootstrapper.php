<?php

declare(strict_types=1);

namespace Webvork\Yii3\Sentry;

use Webvork\Yii3\Sentry\Http\YiiRequestFetcher;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Sentry\ClientBuilder;
use Sentry\Integration as SdkIntegration;
use Sentry\Integration\IntegrationInterface;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\State\HubInterface;
use Sentry\Transport\TransportFactoryInterface;

use function is_string;

final class HubBootstrapper
{
    public const DEFAULT_INTEGRATIONS = [];

    public function __construct(
        private Options $options,
        private YiiSentryConfig $configuration,
        private TransportFactoryInterface $transportFactory,
        private LoggerInterface $logger,
        private HubInterface $hub,
        private ContainerInterface $container,
    ) {
    }

    public function bootstrap(): void
    {
        $this->options->setIntegrations(fn(array $integrations) => $this->prepareIntegrations($integrations));

        $clientBuilder = new ClientBuilder($this->options);
        $clientBuilder
            ->setTransportFactory($this->transportFactory)
            ->setLogger($this->logger);

        $client = $clientBuilder->getClient();

        $hub = $this->hub;
        $hub->bindClient($client);
        SentrySdk::setCurrentHub($hub);
    }

    /**
     * @param IntegrationInterface[] $integrations
     *
     * @return IntegrationInterface[]
     */
    public function prepareIntegrations(array $integrations)
    {
        $userIntegrations = $this->resolveIntegrationsFromUserConfig();
        if ($this->options->hasDefaultIntegrations()) {
            $integrations = array_filter(
                $integrations,
                static function (
                    SdkIntegration\IntegrationInterface $integration
                ): bool {
                    if (
                        $integration instanceof
                        SdkIntegration\ErrorListenerIntegration
                    ) {
                        return false;
                    }

                    if (
                        $integration instanceof
                        SdkIntegration\ExceptionListenerIntegration
                    ) {
                        return false;
                    }

                    if (
                        $integration instanceof
                        SdkIntegration\FatalErrorListenerIntegration
                    ) {
                        return false;
                    }

                    // We also remove the default request integration so it can be readded
                    // after with a Laravel specific request fetcher. This way we can resolve
                    // the request from Laravel instead of constructing it from the global state
                    if (
                        $integration instanceof
                        SdkIntegration\RequestIntegration
                    ) {
                        return false;
                    }

                    return true;
                }
            );

            $integrations[] = new SdkIntegration\RequestIntegration(
                new YiiRequestFetcher($this->container)
            );
        }

        return array_merge($integrations, $userIntegrations);
    }

    /**
     * Resolve the integrations from the user configuration with the container.
     *
     * @return SdkIntegration\IntegrationInterface[]
     */
    private function resolveIntegrationsFromUserConfig(): array
    {
        // Default Sentry SDK integrations
        $integrations = [
            new Integration(),
            new Integration\ExceptionContextIntegration(),
        ];

        $integrationsToResolve = $this->configuration->getIntegrations();

        $enableDefaultTracingIntegrations = isset(
            $this->configuration
                ->getTracing()['default_integrations']
        )
            ? (bool)$this->configuration->getTracing()['default_integrations']
            : true;

        if (
            $enableDefaultTracingIntegrations
            && $this->configuration->couldHavePerformanceTracingEnabled()
        ) {
            $integrationsToResolve = array_merge(
                $integrationsToResolve,
                self::DEFAULT_INTEGRATIONS
            );
        }
        /** @psalm-suppress MixedAssignment */
        foreach ($integrationsToResolve as $userIntegration) {
            if (
                $userIntegration instanceof
                SdkIntegration\IntegrationInterface
            ) {
                $integrations[] = $userIntegration;
            } elseif (is_string($userIntegration)) {
                /** @psalm-suppress MixedAssignment */
                $resolvedIntegration = $this->container->get($userIntegration);

                if (
                    !$resolvedIntegration instanceof
                        SdkIntegration\IntegrationInterface
                ) {
                    if (is_array($resolvedIntegration)) {
                        $value = 'array';
                    } elseif (is_object($resolvedIntegration)) {
                        $value = get_class($resolvedIntegration);
                    } elseif (is_null($resolvedIntegration)) {
                        $value = 'null';
                    } else {
                        $value = (string)$resolvedIntegration;
                    }

                    throw new RuntimeException(
                        sprintf(
                            'Sentry integrations must be an instance of `%s` got `%s`.',
                            SdkIntegration\IntegrationInterface::class,
                            $value
                        )
                    );
                }

                $integrations[] = $resolvedIntegration;
            } else {
                throw new RuntimeException(
                    sprintf(
                        'Sentry integrations must either be a valid container reference or an instance of `%s`.',
                        SdkIntegration\IntegrationInterface::class
                    )
                );
            }
        }

        return $integrations;
    }
}
