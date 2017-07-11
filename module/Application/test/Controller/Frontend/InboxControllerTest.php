<?php

namespace ApplicationTest\Frontend\Controller;

use Application\Test\AbstractHttpControllerTestCase;

use Application\Controller\InboxController;

class InboxControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    public function testIndex()
    {
        $this->dispatch('https://www.autowp.ru/inbox', 'GET');

        $this->assertResponseStatusCode(302);
        $this->assertModuleName('application');
        $this->assertControllerName(InboxController::class);
        $this->assertMatchedRouteName('inbox');
        $this->assertActionName('index');
    }
}
