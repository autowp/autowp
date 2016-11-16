<?php

namespace ApplicationTest\Controller\Moder;

use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;

use Application\Controller\Moder\RightsController;

class RightsControllerTest extends AbstractHttpControllerTestCase
{
    public function setUp()
    {
        $this->setApplicationConfig(include __DIR__ . '/../../_files/application.config.php');

        parent::setUp();
    }

    public function testIndex()
    {
        $this->dispatch('https://www.autowp.ru/moder/rights', 'GET');

        $this->assertResponseStatusCode(403);
        $this->assertModuleName('application');
        $this->assertControllerName(RightsController::class);
        $this->assertMatchedRouteName('moder/rights');
        $this->assertActionName('forbidden');
    }
}
