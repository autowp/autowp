<?php

namespace ApplicationTest\Frontend\Controller;

use Zend\Http\Header\Cookie;
use Zend\Http\Request;
use Zend\Json\Json;

use Application\Test\AbstractHttpControllerTestCase;

class CategoryControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../_files/application.config.php';

    public function testIndex()
    {
        $this->dispatch('https://www.autowp.ru/category', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(\Application\Controller\CategoryController::class);
        $this->assertMatchedRouteName('categories');
        $this->assertActionName('index');

        $this->assertQuery("h1");
        $this->assertQuery(".destinations");
    }

    public function testCreateCategoryAddItemAndGet()
    {
        $catname = 'catname-' . (100000000 * microtime());

        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/cars/new/item_type_id/3', Request::METHOD_POST, [
            'name'    => 'Test category',
            'catname' => $catname,
            'begin'   => [
                'year' => 2000
            ],
            'end'    => [
                'year' => 2000
            ]
        ]);

        $this->assertResponseStatusCode(302);
        $this->assertModuleName('application');
        $this->assertControllerName(\Application\Controller\Moder\CarsController::class);
        $this->assertMatchedRouteName('moder/cars/params');
        $this->assertActionName('new');

        $this->assertHasResponseHeader('Location');

        $header = $this->getResponse()->getHeaders()->get('Location');
        $path = $header->uri()->getPath();

        $this->assertStringStartsWith('/moder/cars/car/item_id/', $path);

        $path = explode('/', $path);
        $categoryId = (int)array_pop($path);

        $this->assertNotEmpty($categoryId);

        // add item to category
        $this->reset();

        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/cars/add-parent/item_id/1/parent_id/' . $categoryId, Request::METHOD_POST);

        $this->assertResponseStatusCode(302);
        $this->assertModuleName('application');
        $this->assertControllerName(\Application\Controller\Moder\CarsController::class);
        $this->assertMatchedRouteName('moder/cars/params');
        $this->assertActionName('add-parent');

        // request category page
        $this->reset();

        $this->dispatch('https://www.autowp.ru/category/' . $catname, Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(\Application\Controller\Moder\CarsController::class);
        $this->assertMatchedRouteName('categories');
        $this->assertActionName('category');
    }
}
