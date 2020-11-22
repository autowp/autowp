<?php

namespace ApplicationTest;

use Application\Test\AbstractHttpControllerTestCase;
use Casbin\Enforcer;
use Laminas\Mvc\Controller\PluginManager;

class AclTest extends AbstractHttpControllerTestCase
{
    protected string $applicationConfigPath = __DIR__ . '/../../../config/application.config.php';

    public function testAclServiceRegisters(): void
    {
        $services = $this->getApplicationServiceLocator();

        $acl = $services->get(Enforcer::class);

        $this->assertInstanceOf(Enforcer::class, $acl);
    }

    public function testAclControllerPluginRegisters(): void
    {
        $services = $this->getApplicationServiceLocator();

        $manager = $services->get(PluginManager::class);
        $plugin  = $manager->get('user');

        $result = $plugin()->enforce('global', 'moderate');
        $this->assertIsBool($result);

        $result = $plugin()->enforce('pictures', 'edit');
        $this->assertIsBool($result);
    }
}
