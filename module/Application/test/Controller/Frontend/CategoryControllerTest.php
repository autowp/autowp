<?php

namespace ApplicationTest\Frontend\Controller;

use Zend\Http\Header\Cookie;
use Zend\Http\Request;

use Application\Test\AbstractHttpControllerTestCase;

class CategoryControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

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
        $catname = 'catname-' . (10000 * microtime(true));

        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/api/item', Request::METHOD_POST, [
            'item_type_id' => 3,
            'name'         => 'Test category',
            'catname'      => $catname,
            'begin_year'   => 2000,
            'end_year'     => 2000
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertModuleName('application');
        $this->assertControllerName(\Application\Controller\Api\ItemController::class);
        $this->assertMatchedRouteName('api/item/post');
        $this->assertActionName('post');

        $this->assertHasResponseHeader('Location');

        $header = $this->getResponse()->getHeaders()->get('Location');
        $path = $header->uri()->getPath();

        $this->assertStringStartsWith('/api/item/', $path);

        $path = explode('/', $path);
        $categoryId = (int)array_pop($path);

        $this->assertNotEmpty($categoryId);

        // add item to category
        $this->reset();

        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch(
            'https://www.autowp.ru/api/item-parent',
            Request::METHOD_POST,
            [
                'item_id'   => 1,
                'parent_id' => $categoryId
            ]
        );

        $this->assertResponseStatusCode(201);
        $this->assertModuleName('application');
        $this->assertControllerName(\Application\Controller\Api\ItemParentController::class);
        $this->assertMatchedRouteName('api/item-parent/post');
        $this->assertActionName('post');

        // request category page
        $this->reset();

        $this->dispatch('https://www.autowp.ru/category/' . $catname, Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(\Application\Controller\CategoryController::class);
        $this->assertMatchedRouteName('categories');
        $this->assertActionName('category');
    }
}
