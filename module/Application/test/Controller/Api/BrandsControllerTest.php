<?php

namespace ApplicationTest\Controller\Api;

use Application\Controller\Api\BrandsController;
use Application\Test\AbstractHttpControllerTestCase;
use Laminas\Http\Header\Cookie;
use Laminas\Http\Request;

use function count;
use function explode;

class BrandsControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    public function testBrandsIndex()
    {
        $this->dispatch('https://www.autowp.ru/api/brands', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(BrandsController::class);
        $this->assertMatchedRouteName('api/brands/get');
        $this->assertActionName('index');
    }

    /**
     * @suppress PhanUndeclaredMethod
     */
    public function testNewItems()
    {
        $brandId = 204;

        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/api/item', Request::METHOD_POST, [
            'name'         => 'Car for testNewcars',
            'item_type_id' => 1,
        ]);

        $this->assertResponseStatusCode(201);

        $headers = $this->getResponse()->getHeaders();
        $uri     = $headers->get('Location')->uri();
        $parts   = explode('/', $uri->getPath());
        $carId   = $parts[count($parts) - 1];

        // add to brand
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/api/item-parent', Request::METHOD_POST, [
            'parent_id' => $brandId,
            'item_id'   => $carId,
        ]);
        $this->assertResponseStatusCode(201);

        // page
        $this->reset();
        $this->dispatch('https://www.autowp.ru/api/brands/' . $brandId . '/new-items', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(BrandsController::class);
        $this->assertMatchedRouteName('api/brands/item/new-items/get');
        $this->assertActionName('new-items');

        $this->assertXpathQuery("//*[contains(text(), 'Car for testNewcars')]");
    }
}
