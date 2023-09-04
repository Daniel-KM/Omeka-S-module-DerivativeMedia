<?php declare(strict_types=1);

namespace DerivativeMedia\Service\ViewHelper;

use DerivativeMedia\View\Helper\HasDerivative;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class HasDerivativeFactory implements FactoryInterface
{
    /**
     * Create and return the HasDerivative view helper
     *
     * @return HasDerivative
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $config = $services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $settings = $services->get('Omeka\Settings');
        return new HasDerivative(
            $basePath,
            $settings->get('derivativemedia_enable', []),
            (int) $settings->get('derivativemedia_max_size_live', 30),
            $services->get('ViewHelperManager')->get('Url')
        );
    }
}
