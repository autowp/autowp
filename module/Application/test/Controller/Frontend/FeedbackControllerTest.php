<?php

namespace ApplicationTest\Frontend\Controller;

use Application\Test\AbstractHttpControllerTestCase;

use Application\Controller\FeedbackController;

class FeedbackControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../_files/application.config.php';

    public function testIndex()
    {
        $this->dispatch('https://www.autowp.ru/feedback', 'GET');

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(FeedbackController::class);
        $this->assertMatchedRouteName('feedback');
        $this->assertActionName('index');

        $this->assertQuery("h1");
    }
}
