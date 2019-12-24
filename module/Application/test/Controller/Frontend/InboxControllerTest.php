<?php

namespace ApplicationTest\Frontend\Controller;

use Application\Test\AbstractHttpControllerTestCase;
use Application\Controller\Api\InboxController;

class InboxControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    public function testIndex()
    {
        $this->dispatch('https://www.autowp.ru/api/inbox', 'GET');

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(InboxController::class);
        $this->assertMatchedRouteName('api/inbox/get');
        $this->assertActionName('index');
    }
}
