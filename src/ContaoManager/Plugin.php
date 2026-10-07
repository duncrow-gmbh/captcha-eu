<?php

declare(strict_types=1);

namespace DuncrowGmbh\CaptchaEu\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\ManagerPlugin\Routing\RoutingPluginInterface;
use DuncrowGmbh\CaptchaEu\DuncrowGmbhCaptchaEuBundle;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouteCollection;

class Plugin implements BundlePluginInterface, RoutingPluginInterface
{
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(DuncrowGmbhCaptchaEuBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class]),
        ];
    }

    /**
     * Loads the #[Route] attributes of the controllers.
     */
    public function getRouteCollection(LoaderResolverInterface $resolver, KernelInterface $kernel): RouteCollection|null
    {
        $path = '@DuncrowGmbhCaptchaEuBundle/src/Controller';

        $loader = $resolver->resolve($path, 'attribute');

        return $loader ? $loader->load($path, 'attribute') : null;
    }
}
