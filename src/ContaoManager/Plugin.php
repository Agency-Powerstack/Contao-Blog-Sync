<?php

declare(strict_types=1);

namespace AgencyPowerstack\ContaoBlogSyncBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\ManagerPlugin\Routing\RoutingPluginInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use AgencyPowerstack\ContaoBlogSyncBundle\ContaoBlogSyncBundle;

class Plugin implements BundlePluginInterface, RoutingPluginInterface
{
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(ContaoBlogSyncBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class])
        ];
    }

    public function getRouteCollection(LoaderResolverInterface $resolver, KernelInterface $kernel): ?\Symfony\Component\Routing\RouteCollection
    {
        $loader = $resolver->resolve(__DIR__ . '/../../config/routes.yaml');

        if (!$loader) {
            return null;
        }

        return $loader->load(__DIR__ . '/../../config/routes.yaml');
    }
}
