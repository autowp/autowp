<?php

namespace ApplicationTest\Controller\Frontend;

use Zend\Http\Header\Cookie;
use Zend\Http\Request;
use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;

use Application\Controller\BrandsController;

class BrandsControllerTest extends AbstractHttpControllerTestCase
{
    public function setUp()
    {
        $this->setApplicationConfig(include __DIR__ . '/../../_files/application.config.php');

        parent::setUp();
    }

    public function testNewcars()
    {
        $brandId = 1;

        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/cars/new', Request::METHOD_POST, [
            'name' => 'Car for testNewcars'
        ]);

        $headers = $this->getResponse()->getHeaders();
        $uri = $headers->get('Location')->uri();
        $parts = explode('/', $uri->getPath());
        $carId = $parts[count($parts)-1];

        // add to brand
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/brand-vehicle/add/brand_id/'.$brandId.'/vehicle_id/' . $carId, Request::METHOD_POST, [
            'name' => 'Test car'
        ]);

        // page
        $this->reset();
        $this->dispatch('https://www.autowp.ru/brands/newcars/' . $brandId, Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(BrandsController::class);
        $this->assertMatchedRouteName('brands/newcars');
        $this->assertActionName('newcars');

        $this->assertXpathQuery("//*[contains(text(), 'Car for testNewcars')]");
    }
}
