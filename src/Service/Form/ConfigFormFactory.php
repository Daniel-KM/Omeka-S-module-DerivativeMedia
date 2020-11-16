<?php declare(strict_types=1);

namespace DerivativeMedia\Service\Form;

use DerivativeMedia\Form\ConfigForm;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ConfigFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $form = new ConfigForm(null, $options);
        return $form
            ->setConnection($services->get('Omeka\Connection'));
    }
}
