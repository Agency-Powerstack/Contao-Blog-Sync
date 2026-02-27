<?php

declare(strict_types=1);

namespace AgencyPowerstack\ContaoBlogSyncBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Loads the bundle's services.yaml and registers the blog_sync.frontend_url container parameter.
 *
 * The frontend URL is read from the BLOG_SYNC_FRONTEND_URL environment variable at runtime.
 * If the variable is not set, the parameter defaults to the production URL. No manual
 * $_SERVER/$_ENV/getenv cascade is required – Symfony's env() processor handles resolution.
 */
final class ContaoBlogSyncExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.yaml');

        // Default value used when BLOG_SYNC_FRONTEND_URL is not set in the environment.
        // The 'default' env processor resolves to this parameter when the env var is absent.
        $container->setParameter('blog_sync.frontend_url_default', 'https://app.agency-powerstack.com');

        // blog_sync.frontend_url resolves to BLOG_SYNC_FRONTEND_URL at runtime,
        // falling back to blog_sync.frontend_url_default if the variable is not set.
        $container->setParameter(
            'blog_sync.frontend_url',
            '%env(default:blog_sync.frontend_url_default:BLOG_SYNC_FRONTEND_URL)%'
        );
    }
}
