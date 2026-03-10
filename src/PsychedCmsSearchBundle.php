<?php

declare(strict_types=1);

namespace PsychedCms\Search;

use PsychedCms\Search\Message\IndexContentMessage;
use PsychedCms\Search\Message\ReindexAllMessage;
use PsychedCms\Search\Message\RemoveContentMessage;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class PsychedCmsSearchBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('framework')) {
            $builder->prependExtensionConfig('framework', [
                'messenger' => [
                    'routing' => [
                        IndexContentMessage::class => 'async',
                        RemoveContentMessage::class => 'async',
                        ReindexAllMessage::class => 'async',
                    ],
                ],
            ]);
        }
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.yaml');
    }
}
