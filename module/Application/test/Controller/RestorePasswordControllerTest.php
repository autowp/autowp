<?php

namespace ApplicationTest\Controller;

use Application\Test\AbstractHttpControllerTestCase;

use Application\Controller\RestorePasswordController;

class RestorePasswordControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../_files/application.config.php';

    public function testIndex()
    {
        $this->dispatch('https://www.autowp.ru/restorepassword', 'GET');

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(RestorePasswordController::class);
        $this->assertMatchedRouteName('restorepassword');
        $this->assertActionName('index');

        $this->assertQuery("h1");
    }
}
