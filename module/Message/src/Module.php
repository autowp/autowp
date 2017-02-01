<?php

namespace Autowp\Message;

use Zend\EventManager\EventInterface;
use Zend\ModuleManager\Feature;

class Module implements
    Feature\AutoloaderProviderInterface,
    Feature\BootstrapListenerInterface,
    Feature\ConfigProviderInterface
{
    public function onBootstrap(EventInterface $e)
    {
        $application = $e->getApplication();
        $serviceManager = $application->getServiceManager();
        
        $maintenance = new Maintenance();
        $maintenance->attach($serviceManager->get('CronEventManager')); // TODO: move CronEventManager to zf-components
    }
    
    /**
     * @return array
     */
    public function getConfig()
    {
        $provider = new ConfigProvider();
        return [
            'service_manager' => $provider->getDependencyConfig()
        ];
    }

    public function getAutoloaderConfig()
    {
        return [
            'Zend\Loader\StandardAutoloader' => [
                'namespaces' => [
                    __NAMESPACE__ => __DIR__ . '/src',
                ],
            ],
        ];
    }
}
