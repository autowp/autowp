<?php

namespace ApplicationTest\Controller\Moder;

use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;

use Application\Controller\Moder\HotlinkController;

class HotlinkControllerTest extends AbstractHttpControllerTestCase
{
    public function setUp()
    {
        $this->setApplicationConfig(include __DIR__ . '/../../_files/application.config.php');

        parent::setUp();
    }

    public function testIndex()
    {
        $this->dispatch('https://www.autowp.ru/moder/hotlink', 'GET');

        $this->assertResponseStatusCode(403);
        $this->assertModuleName('application');
        $this->assertControllerName(HotlinkController::class);
        $this->assertMatchedRouteName('moder/hotlink');
        $this->assertActionName('forbidden');
    }
}
