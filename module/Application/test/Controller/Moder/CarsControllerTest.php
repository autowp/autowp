<?php

namespace ApplicationTest\Controller\Moder;

use Zend\Http\Header\Cookie;
use Zend\Http\Headers;
use Zend\Http\Request;
use Zend\Json\Json;

use Application\Controller\Moder\CarsController;
use Application\Test\AbstractHttpControllerTestCase;

class CarsControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../_files/application.config.php';

    public function testVehicleIsNotForbidden()
    {
        /**
         * @var Request $request
         */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('http://www.autowp.ru/moder/cars/car/item_id/1', 'GET');

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CarsController::class);
        $this->assertMatchedRouteName('moder/cars/params');
        $this->assertActionName('car');

        $this->assertXpathQuery("//h1[contains(text(), 'test car')]|//*[@value='test car']");
    }

    public function testCreateCarAndAddToBrand()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/cars/new/item_type_id/1', Request::METHOD_POST, [
            'name' => 'Test car'
        ]);

        $this->assertResponseStatusCode(302);
        $this->assertModuleName('application');
        $this->assertControllerName(CarsController::class);
        $this->assertMatchedRouteName('moder/cars/params');
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
        $this->dispatch('https://www.autowp.ru/moder/cars/add-parent/parent_id/205/item_id/' . $carId, Request::METHOD_POST, [
            'name' => 'Test car'
        ]);

        $this->assertResponseStatusCode(302);
        $this->assertModuleName('application');
        $this->assertControllerName(CarsController::class);
        $this->assertMatchedRouteName('moder/cars/params');
        $this->assertActionName('add-parent');
    }

    public function testSelectBrand()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/cars/car-select-brand/item_id/1', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CarsController::class);
        $this->assertMatchedRouteName('moder/cars/params');
        $this->assertActionName('car-select-brand');
    }

    public function testSelectParent()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/cars/car-select-parent/item_id/1', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CarsController::class);
        $this->assertMatchedRouteName('moder/cars/params');
        $this->assertActionName('car-select-parent');
    }

    public function testOrganize()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/cars/organize/item_id/1', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CarsController::class);
        $this->assertMatchedRouteName('moder/cars/params');
        $this->assertActionName('organize');
    }

    public function testOrganizePicturesForm()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/cars/organize-pictures/item_id/1', Request::METHOD_GET);

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
        $this->dispatch('https://www.autowp.ru/upload/send/type/1/item_id/1', Request::METHOD_POST, [], true);

        $this->assertResponseStatusCode(200);
        $this->assertResponseHeaderContains('Content-Type', 'application/json; charset=utf-8');

        $json = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);

        $pictureId = $json[0]['id'];

        // do organize
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/cars/organize-pictures/item_id/1', Request::METHOD_POST, [
            'name'   => 'Pictures organize test item',
            'childs' => [$pictureId]
        ]);

        $this->assertResponseStatusCode(302);
        $this->assertHasResponseHeader('Location');
    }

    public function testTree()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/cars/car-tree/item_id/1', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CarsController::class);
        $this->assertMatchedRouteName('moder/cars/params');
        $this->assertActionName('car-tree');
    }

    public function testCreateBrand()
    {
        $catname = 'test-brand-' . (10000 * microtime(true));

        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/cars/new/item_type_id/5', Request::METHOD_POST, [
            'name'      => 'Test brand',
            'full_name' => 'Test brand full name',
            'catname'   => $catname,
            'begin'     => [
                'year' => 1950
            ]
        ]);

        $this->assertResponseStatusCode(302);
        $this->assertModuleName('application');
        $this->assertControllerName(CarsController::class);
        $this->assertMatchedRouteName('moder/cars/params');
        $this->assertActionName('new');

        $this->assertHasResponseHeader('Location');

        $header = $this->getResponse()->getHeaders()->get('Location');
        $path = $header->uri()->getPath();

        $this->assertStringStartsWith('/moder/cars/car/item_id/', $path);

        $path = explode('/', $path);
        $brandId = (int)array_pop($path);

        $this->assertNotEmpty($brandId);

        // set language values
        $this->reset();

        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/cars/car-name/item_id/' . $brandId, Request::METHOD_POST, [
            'ru'      => [
                'name'      => 'Тест',
                'text'      => 'Краткое описание',
                'full_text' => 'Полное описание'
            ],
            'en'      => [
                'name'      => 'Test',
                'text'      => 'Short description',
                'full_text' => 'Full description'
            ]
        ]);

        $this->assertResponseStatusCode(302);
        $this->assertModuleName('application');
        $this->assertControllerName(CarsController::class);
        $this->assertMatchedRouteName('moder/cars/params');
        $this->assertActionName('car-name');

        // set links
        $this->reset();

        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/cars/save-links/item_id/' . $brandId, Request::METHOD_POST, [
            'new'      => [
                'name' => 'Тест',
                'url'  => 'http://example.com',
                'type' => 'default'
            ]
        ]);

        $this->assertResponseStatusCode(302);
        $this->assertModuleName('application');
        $this->assertControllerName(CarsController::class);
        $this->assertMatchedRouteName('moder/cars/params');
        $this->assertActionName('save-links');
    }
}
