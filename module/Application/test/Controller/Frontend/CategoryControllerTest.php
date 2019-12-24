<?php

namespace ApplicationTest\Frontend\Controller;

use Zend\Http\Header\Cookie;
use Zend\Http\Request;
use Application\Controller\Api\ItemController;
use Application\Controller\Api\ItemParentController;
use Application\Test\AbstractHttpControllerTestCase;

class CategoryControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    /**
     * @suppress PhanUndeclaredMethod
     */
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
        $this->assertControllerName(ItemController::class);
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
        $this->assertControllerName(ItemParentController::class);
        $this->assertMatchedRouteName('api/item-parent/post');
        $this->assertActionName('post');
    }
}
