<?php

namespace ApplicationTest\Controller\Moder;

use Application\Test\AbstractHttpControllerTestCase;

use Application\Controller\Moder\CommentsController;

class CommentsControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../_files/application.config.php';

    public function testIndex()
    {
        $this->dispatch('https://www.autowp.ru/moder/comments', 'GET');

        $this->assertResponseStatusCode(403);
        $this->assertModuleName('application');
        $this->assertControllerName(CommentsController::class);
        $this->assertMatchedRouteName('moder/comments');
        $this->assertActionName('forbidden');
    }
}
