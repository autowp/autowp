<?php

namespace ApplicationTest\Controller;

use Application\Test\AbstractHttpControllerTestCase;
use Zend\Permissions\Acl\Acl;
use Zend\View\Renderer\PhpRenderer;
use Zend\Mvc\Controller\PluginManager;

class AclTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../config/application.config.php';

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

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $result = $view->user()->inheritsRole('moder');
        $this->assertIsBool($result);

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $result = $view->user()->isAllowed('pictures', 'edit');
        $this->assertIsBool($result);
    }

    public function testAclControllerPluginRegisters()
    {
        $services = $this->getApplicationServiceLocator();

        $manager = $services->get(PluginManager::class);
        $plugin = $manager->get('user');

        $result = $plugin()->inheritsRole('moder');
        $this->assertIsBool($result);

        $result = $plugin()->isAllowed('pictures', 'edit');
        $this->assertIsBool($result);
    }
}
