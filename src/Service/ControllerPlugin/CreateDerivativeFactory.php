<?php declare(strict_types=1);

namespace DerivativeMedia\Service\ControllerPlugin;

use DerivativeMedia\Mvc\Controller\Plugin\CreateDerivative;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class CreateDerivativeFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $config = $services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        return new CreateDerivative(
            $services->get('Omeka\Cli'),
            $basePath
        );
    }
}
