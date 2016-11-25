<?php

namespace ApplicationTest\Controller\Moder;

use Zend\Http\Header\Cookie;
use Zend\Http\Headers;
use Zend\Http\Request;
use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;

use Application\Controller\Moder\CarsController;
use Application\Controller\Moder\BrandVehicleController;
use Zend\Json\Json;

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

    public function testOrganizePicturesForm()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/cars/organize-pictures/car_id/1', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CarsController::class);
        $this->assertMatchedRouteName('moder/cars/params');
        $this->assertActionName('organize-pictures');
    }

    public function testOrganizePicturesAction()
    {
        // upload picture
        $request = $this->getRequest();
        $request->getHeaders()
            ->addHeader(Cookie::fromString('Cookie: remember=admin-token'))
            ->addHeaderLine('Content-Type', 'multipart/form-data');
        $request->getServer()->set('REMOTE_ADDR', '127.0.0.1');

        $file = tempnam(sys_get_temp_dir(), 'upl');
        $filename = 'test.jpg';
        copy(__DIR__ . '/../../_files/' . $filename, $file);

        $request->getFiles()->fromArray([
            'picture' => [
                [
                    'tmp_name' => $file,
                    'name'     => $filename,
                    'error'    => UPLOAD_ERR_OK,
                    'type'     => 'image/jpeg'
                ]
            ]
        ]);
        $this->dispatch('https://www.autowp.ru/upload/index/type/1/car_id/1', Request::METHOD_POST, [], true);

        $this->assertResponseStatusCode(200);
        $this->assertResponseHeaderContains('Content-Type', 'application/json; charset=utf-8');

        $json = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);

        $pictureId = $json[0]['id'];

        // do organize
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/cars/organize-pictures/car_id/1', Request::METHOD_POST, [
            'name'   => 'Pictures organize test item',
            'childs' => [$pictureId]
        ]);

        $this->assertResponseStatusCode(302);
        $this->assertHasResponseHeader('Location');
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
