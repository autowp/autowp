<?php

namespace ApplicationTest\Controller;

use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;
use Zend\Permissions\Acl\Acl;
use Zend\View\Renderer\PhpRenderer;
use Zend\Mvc\Controller\PluginManager;

class AclTest extends AbstractHttpControllerTestCase
{
    public function setUp()
    {
        $this->setApplicationConfig(include __DIR__ . '/_files/application.config.php');

        parent::setUp();
    }

    public function testAclServiceRegisters()
    {
        $services = $this->getApplicationServiceLocator();

        $acl = $services->get(Acl::class);

        $this->assertInstanceOf(Acl::class, $acl);
    }

    public function testAclViewHelperRegisters()
    {
        $services = $this->getApplicationServiceLocator();

        $view = $services->get(PhpRenderer::class);

        $result = $view->user()->inheritsRole('moder');
        $this->assertInternalType('bool', $result);

        $result = $view->user()->isAllowed('pictures', 'edit');
        $this->assertInternalType('bool', $result);
    }

    public function testAclControllerPluginRegisters()
    {
        $services = $this->getApplicationServiceLocator();

        $manager = $services->get(PluginManager::class);
        $plugin = $manager->get('user');

        $result = $plugin()->inheritsRole('moder');
        $this->assertInternalType('bool', $result);

        $result = $plugin()->isAllowed('pictures', 'edit');
        $this->assertInternalType('bool', $result);
    }
}
