<?php

namespace ApplicationTest\Controller\Moder;

use Application\Test\AbstractHttpControllerTestCase;

use Application\Controller\Moder\CategoryController;

class CategoryControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../_files/application.config.php';

    public function testIndex()
    {
        $this->dispatch('https://www.autowp.ru/moder/category', 'GET');

        $this->assertResponseStatusCode(403);
        $this->assertModuleName('application');
        $this->assertControllerName(CategoryController::class);
        $this->assertMatchedRouteName('moder/category');
        $this->assertActionName('forbidden');
    }
}
