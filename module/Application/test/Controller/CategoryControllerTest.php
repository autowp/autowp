<?php

namespace ApplicationTest\Controller;

use Application\Test\AbstractHttpControllerTestCase;

use Application\Controller\CategoryController;

class CategoryControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../_files/application.config.php';

    public function testIndex()
    {
        $this->dispatch('https://www.autowp.ru/category', 'GET');

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CategoryController::class);
        $this->assertMatchedRouteName('categories');
        $this->assertActionName('index');

        $this->assertQuery("h1");
        $this->assertQuery(".destinations");
    }
}
