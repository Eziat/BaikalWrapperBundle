<?php

declare(strict_types = 1);

namespace Eziat\BaikalWrapperBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * @author Tomas Jakl <tomasjakll@gmail.com>
 */
class EziatBaikalWrapperExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $processor     = new Processor();
        $configuration = new Configuration();
        $loader        = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));

        $config = $processor->processConfiguration($configuration, $configs);

        // Load services only when baikal parameters are set.
        $parameters = ['baikal.db_host', 'baikal.db_name', 'baikal.db_user', 'baikal.db_password'];
        if ($this->doesParametersExist($parameters, $container) === true) {
            $loader->load('services.xml');
            $loader->load('commands.xml');
        }
    }

    private function doesParametersExist(array $parameters, ContainerBuilder $container)
    {
        foreach ($parameters as $parameter) {
            if ( $container->hasParameter($parameter) === false ){
                return false;
            }
        }

        return true;
    }
}