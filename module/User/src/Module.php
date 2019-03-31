<?php

namespace Autowp\User;

use Zend\EventManager\EventInterface as Event;
use Zend\ModuleManager\Feature;

class Module implements
    Feature\AutoloaderProviderInterface,
    Feature\BootstrapListenerInterface,
    Feature\ConfigProviderInterface
{
    /**
     * @return array
     */
    public function getConfig()
    {
        $provider = new ConfigProvider();
        return [
            'controller_plugins' => $provider->getControllerPluginConfig(),
            'service_manager'    => $provider->getDependencyConfig(),
            'tables'             => $provider->getTablesConfig(),
            'view_helpers'       => $provider->getViewHelperConfig(),
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

    /**
     * @suppress PhanUndeclaredMethod
     * @param Event $e
     * @return array
     */
    public function onBootstrap(Event $e)
    {
        $application = $e->getApplication();
        $eventManager = $application->getEventManager();
        $serviceManager = $application->getServiceManager();

        $authRememberListener = new Auth\RememberDispatchListener();
        $authRememberListener->attach($eventManager);

        $maintenance = new Maintenance();
        $maintenance->attach($serviceManager->get('CronEventManager')); // TODO: move CronEventManager to zf-components

        return [];
    }
}
