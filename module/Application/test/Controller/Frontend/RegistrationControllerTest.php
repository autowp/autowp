<?php

namespace ApplicationTest\Frontend\Controller;

use Application\Test\AbstractHttpControllerTestCase;

use Application\Controller\RegistrationController;

class RegistrationControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../_files/application.config.php';

    public function testIndex()
    {
        $this->dispatch('https://www.autowp.ru/registration', 'GET');

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(RegistrationController::class);
        $this->assertMatchedRouteName('registration');
        $this->assertActionName('index');

        $this->assertQuery("h1");
    }
}
