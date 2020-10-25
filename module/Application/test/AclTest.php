<?php

namespace ApplicationTest;

use Application\Test\AbstractHttpControllerTestCase;
use Laminas\Mvc\Controller\PluginManager;
use Laminas\Permissions\Acl\Acl;

class AclTest extends AbstractHttpControllerTestCase
{
    protected string $applicationConfigPath = __DIR__ . '/../../../config/application.config.php';

    public function testAclServiceRegisters(): void
    {
        $services = $this->getApplicationServiceLocator();

        $acl = $services->get(Acl::class);

        $this->assertInstanceOf(Acl::class, $acl);
    }

    public function testAclControllerPluginRegisters(): void
    {
        $services = $this->getApplicationServiceLocator();

        $manager = $services->get(PluginManager::class);
        $plugin  = $manager->get('user');

        $result = $plugin()->inheritsRole('moder');
        $this->assertIsBool($result);

        $result = $plugin()->isAllowed('pictures', 'edit');
        $this->assertIsBool($result);
    }
}
