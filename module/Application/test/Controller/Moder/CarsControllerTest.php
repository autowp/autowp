<?php

namespace ApplicationTest\Controller\Moder;

use Zend\Http\Header\Cookie;
use Zend\Http\Headers;
use Zend\Http\Request;
use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;

use Application\Controller\Moder\CarsController;
use Application\Controller\Moder\BrandVehicleController;

class CarsControllerTest extends AbstractHttpControllerTestCase
{
    public function setUp()
    {
        $this->setApplicationConfig(include __DIR__ . '/../../_files/application.config.php');

        parent::setUp();
    }

    public function testIndex()
    {
        $this->dispatch('https://www.autowp.ru/moder/cars', Request::METHOD_GET);

        $this->assertResponseStatusCode(403);
        $this->assertModuleName('application');
        $this->assertControllerName(CarsController::class);
        $this->assertMatchedRouteName('moder/cars');
        $this->assertActionName('forbidden');
    }

    public function testCreateCarAndAddToBrand()
    {
        //ini_set('xdebug.show_exception_trace', 1);
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/cars/new', Request::METHOD_POST, [
            'name' => 'Test car'
        ]);

        $this->assertResponseStatusCode(302);
        $this->assertModuleName('application');
        $this->assertControllerName(CarsController::class);
        $this->assertMatchedRouteName('moder/cars');
        $this->assertActionName('new');

        /**
         * @var Headers $headers
         */
        $headers = $this->getResponse()->getHeaders();

        $uri = $headers->get('Location')->uri();
        $parts = explode('/', $uri->getPath());
        $carId = $parts[count($parts) - 1];

        $this->assertNotEmpty($carId);

        // add to brand
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/brand-vehicle/add/brand_id/1/vehicle_id/' . $carId, Request::METHOD_POST, [
            'name' => 'Test car'
        ]);

        $this->assertResponseStatusCode(302);
        $this->assertModuleName('application');
        $this->assertControllerName(BrandVehicleController::class);
        $this->assertMatchedRouteName('moder/brand-vehicle/params');
        $this->assertActionName('add');
    }

    public function testAlpha()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/cars/alpha/char/T', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CarsController::class);
        $this->assertMatchedRouteName('moder/cars/params');
        $this->assertActionName('alpha');
    }

    public function testSelectTwinsGroup()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/cars/car-select-twins-group/car_id/1', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CarsController::class);
        $this->assertMatchedRouteName('moder/cars/params');
        $this->assertActionName('car-select-twins-group');
    }

    public function testSelectBrand()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/cars/car-select-brand/car_id/1', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CarsController::class);
        $this->assertMatchedRouteName('moder/cars/params');
        $this->assertActionName('car-select-brand');
    }

    public function testSelectFactory()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/cars/car-select-factory/car_id/1', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CarsController::class);
        $this->assertMatchedRouteName('moder/cars/params');
        $this->assertActionName('car-select-factory');
    }

    public function testSelectParent()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/cars/car-select-parent/car_id/1', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CarsController::class);
        $this->assertMatchedRouteName('moder/cars/params');
        $this->assertActionName('car-select-parent');
    }

    public function testOrganize()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/cars/organize/car_id/1', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CarsController::class);
        $this->assertMatchedRouteName('moder/cars/params');
        $this->assertActionName('organize');
    }

    public function testOrganizePictures()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/cars/organize-pictures/car_id/1', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CarsController::class);
        $this->assertMatchedRouteName('moder/cars/params');
        $this->assertActionName('organize-pictures');
    }

    public function testTree()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/cars/car-tree/car_id/1', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CarsController::class);
        $this->assertMatchedRouteName('moder/cars/params');
        $this->assertActionName('car-tree');
    }
}
