<?php

namespace ApplicationTest\Controller\Frontend;

use Zend\Http\Request;
use Zend\Json\Json;
use Application\Test\AbstractHttpControllerTestCase;

use Application\Controller\CatalogueController;
use Zend\Http\Header\Cookie;

class CatalogueControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../_files/application.config.php';

    public function testBrand()
    {
        $this->dispatch('https://www.autowp.ru/bmw', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('brand');

        $this->assertXpathQuery("//h1[contains(text(), 'BMW')]");
    }

    public function testCars()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/cars/new/item_type_id/1', Request::METHOD_POST, [
            'name'       => 'Test car',
            'is_concept' => 0,
            'begin' => [
                'year' => 1999,
            ],
            'end'   => [
                'year' => 2001,
            ],
            'is_group'   => 0
        ]);

        $this->assertResponseStatusCode(302);
        $this->assertMatchedRouteName('moder/cars/params');

        $uri = $this->getResponse()->getHeaders()->get('Location')->uri();
        $parts = explode('/', $uri->getPath());
        $carId = $parts[count($parts) - 1];

        $this->assertNotEmpty($carId);

        // add to brand
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/cars/add-parent/parent_id/204/item_id/' . $carId, Request::METHOD_POST);

        $this->assertResponseStatusCode(302);
        $this->assertModuleName('application');
        $this->assertMatchedRouteName('moder/cars/params');
        $this->assertActionName('add-parent');

        // request
        $this->reset();
        $this->dispatch('https://www.autowp.ru/bmw/cars', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('cars');

        $this->assertXpathQuery("//h1[contains(text(), 'BMW')]");
    }

    public function testRecent()
    {
        $this->dispatch('https://www.autowp.ru/bmw/recent', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('recent');

        $this->assertXpathQuery("//h1[contains(text(), 'BMW')]");
    }

    public function testConcepts()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/cars/new/item_type_id/1', Request::METHOD_POST, [
            'name'       => 'Test concept car',
            'is_concept' => 1
        ]);

        $this->assertResponseStatusCode(302);
        $this->assertMatchedRouteName('moder/cars/params');

        $uri = $this->getResponse()->getHeaders()->get('Location')->uri();
        $parts = explode('/', $uri->getPath());
        $carId = $parts[count($parts) - 1];

        $this->assertNotEmpty($carId);

        // add to brand
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/cars/add-parent/parent_id/204/item_id/' . $carId, Request::METHOD_POST);

        $this->assertResponseStatusCode(302);
        $this->assertModuleName('application');
        $this->assertMatchedRouteName('moder/cars/params');
        $this->assertActionName('add-parent');

        // request concept
        $this->reset();

        $this->dispatch('https://www.autowp.ru/bmw/concepts', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('concepts');

        $this->assertXpathQuery("//h1[contains(text(), 'BMW')]");
    }

    public function testOther()
    {
        $this->dispatch('https://www.autowp.ru/bmw/other', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('other');

        $this->assertQuery(".thumbnail");
    }

    public function testMixed()
    {
        $this->dispatch('https://www.autowp.ru/bmw/mixed', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('mixed');

        $this->assertQuery(".thumbnail");
    }

    public function testLogotypes()
    {
        $this->dispatch('https://www.autowp.ru/bmw/logotypes', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('logotypes');

        $this->assertQuery(".thumbnail");
    }

    public function testOtherPicture()
    {
        $this->dispatch('https://www.autowp.ru/bmw/other/identity2/', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('other-picture');

        $this->assertQuery(".thumbnail");
    }

    public function testMixedPicture()
    {
        $this->dispatch('https://www.autowp.ru/bmw/mixed/identity3/', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('mixed-picture');

        $this->assertQuery(".thumbnail");
    }

    public function testLogotypesPicture()
    {
        $this->dispatch('https://www.autowp.ru/bmw/logotypes/identity4/', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('logotypes-picture');

        $this->assertQuery(".thumbnail");
    }

    public function testOtherGallery()
    {
        $this->dispatch('https://www.autowp.ru/bmw/other/gallery/', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('other-gallery');
        $this->assertResponseHeaderContains('Content-Type', 'application/json; charset=utf-8');

        $data = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);

        $this->assertArrayHasKey('page', $data);
        $this->assertArrayHasKey('pages', $data);
        $this->assertArrayHasKey('count', $data);
        $this->assertArrayHasKey('items', $data);
    }

    public function testMixedGallery()
    {
        $this->dispatch('https://www.autowp.ru/bmw/mixed/gallery/', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('mixed-gallery');
        $this->assertResponseHeaderContains('Content-Type', 'application/json; charset=utf-8');

        $data = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);

        $this->assertArrayHasKey('page', $data);
        $this->assertArrayHasKey('pages', $data);
        $this->assertArrayHasKey('count', $data);
        $this->assertArrayHasKey('items', $data);
    }

    public function testLogotypesGallery()
    {
        $this->dispatch('https://www.autowp.ru/bmw/logotypes/gallery/', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('logotypes-gallery');
        $this->assertResponseHeaderContains('Content-Type', 'application/json; charset=utf-8');

        $data = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);

        $this->assertArrayHasKey('page', $data);
        $this->assertArrayHasKey('pages', $data);
        $this->assertArrayHasKey('count', $data);
        $this->assertArrayHasKey('items', $data);
    }

    public function testVehiclePicture()
    {
        $this->dispatch('https://www.autowp.ru/bmw/first-car/pictures/identity1', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('brand-item-picture');
    }

    public function testBrandMosts()
    {
        // add to brand
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/cars/add-parent/parent_id/204/item_id/3', Request::METHOD_POST);

        $this->assertResponseStatusCode(302);

        $this->reset();
        $this->dispatch('https://www.autowp.ru/bmw/mosts', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('brand-mosts');
    }
}
