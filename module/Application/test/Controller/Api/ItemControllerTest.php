<?php

namespace ApplicationTest\Controller\Api;

use Zend\Http\Header\Cookie;
use Zend\Http\Headers;
use Zend\Http\Request;
use Zend\Json\Json;

use Application\Test\AbstractHttpControllerTestCase;
use Application\Controller\Api\ItemController;
use Application\Controller\Api\ItemParentController;
use Application\Controller\Api\PictureController;
use Application\Controller\Api\PictureItemController;
use Application\Controller\UploadController;
use Application\Controller\Moder\CarsController;
use Application\Controller\Api\ItemLanguageController;
use Application\Controller\Api\ItemLinkController;

class ItemControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    private function createItem($params)
    {
        $this->reset();

        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/api/item', Request::METHOD_POST, $params);

        $this->assertResponseStatusCode(201);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemController::class);
        $this->assertMatchedRouteName('api/item/post');
        $this->assertActionName('post');

        $headers = $this->getResponse()->getHeaders();
        $uri = $headers->get('Location')->uri();
        $parts = explode('/', $uri->getPath());
        $itemId = $parts[count($parts) - 1];

        return $itemId;
    }

    private function createVehicle(array $params = [])
    {
        return $this->createItem(array_replace([
            'item_type_id' => 1,
            'name'         => 'Some vehicle'
        ], $params));
    }

    private function createEngine()
    {
        return $this->createItem([
            'item_type_id' => 2,
            'name'         => 'Some engine'
        ]);
    }

    private function setEngineToVehicle($engineBrandCatname, $engineId, $vehicleId)
    {
        $this->reset();

        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch(
            'https://www.autowp.ru/cars/select-car-engine/item_id/' . $vehicleId,
            Request::METHOD_POST,
            [
                'brand'  => $engineBrandCatname,
                'engine' => $engineId
            ]
        );

        $this->assertResponseStatusCode(302);
        $this->assertModuleName('application');
        $this->assertControllerName(\Application\Controller\CarsController::class);
        $this->assertMatchedRouteName('cars/params');
        $this->assertActionName('select-car-engine');
    }

    private function getRandomBrand()
    {
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/api/item', Request::METHOD_GET, [
            'type_id' => 5,
            'order'   => 'id_desc',
            'fields'  => 'catname,subscription'
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemController::class);
        $this->assertMatchedRouteName('api/item/list');
        $this->assertActionName('index');

        $json = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);

        return $json['items'][0];
    }

    private function addItemParent($itemId, $parentId, array $params = [])
    {
        $this->reset();

        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch(
            'https://www.autowp.ru/api/item-parent',
            Request::METHOD_POST,
            array_replace([
                'item_id'   => $itemId,
                'parent_id' => $parentId
            ], $params)
        );

        $this->assertResponseStatusCode(201);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemParentController::class);
        $this->assertMatchedRouteName('api/item-parent/post');
        $this->assertActionName('post');
    }

    private function addPictureToItem($vehicleId)
    {
        $this->reset();

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
        $url = 'https://www.autowp.ru/upload/send/type/1/item_id/' . $vehicleId;
        $this->dispatch($url, Request::METHOD_POST, [], true);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(UploadController::class);
        $this->assertMatchedRouteName('upload/params');
        $this->assertActionName('send');

        $this->assertResponseHeaderContains('Content-Type', 'application/json; charset=utf-8');

        $json = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);

        $this->assertInternalType('array', $json);
        $this->assertNotEmpty($json);

        foreach ($json as $item) {
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('html', $item);
            $this->assertArrayHasKey('width', $item);
            $this->assertArrayHasKey('height', $item);
        }

        return $json[0]['id'];
    }

    private function setPerspective($pictureId, $itemId, $perspectiveId)
    {
        $this->reset();

        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch(
            'https://www.autowp.ru/api/picture-item/' . $pictureId. '/' . $itemId,
            Request::METHOD_PUT,
            [
                'perspective_id' => $perspectiveId
            ]
        );

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(PictureItemController::class);
        $this->assertMatchedRouteName('api/picture-item/update');
        $this->assertActionName('update');
    }

    private function acceptPicture($pictureId)
    {
        $this->reset();

        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch(
            'https://www.autowp.ru/api/picture/' . $pictureId,
            Request::METHOD_PUT,
            ['status' => 'accepted']
        );

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(PictureController::class);
        $this->assertMatchedRouteName('api/picture/picture/update');
        $this->assertActionName('update');
    }

    private function getItemParent($itemId, $parentId)
    {
        $this->reset();

        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch(
            'https://www.autowp.ru/api/item-parent/'.$itemId.'/'.$parentId,
            Request::METHOD_GET
        );

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemParentController::class);
        $this->assertMatchedRouteName('api/item-parent/item/get');
        $this->assertActionName('item');

        return Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);
    }

    public function testEngineUnderTheHoodPreviews()
    {
        $vehicleId = $this->createVehicle();
        $engineId = $this->createEngine();
        $brand = $this->getRandomBrand();
        $this->addItemParent($engineId, $brand['id']);
        $this->setEngineToVehicle($brand['catname'], $engineId, $vehicleId);
        $pictureId = $this->addPictureToItem($vehicleId);
        $this->acceptPicture($pictureId);
        $this->setPerspective($pictureId, $vehicleId, 17);

        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/api/item/' . $engineId, Request::METHOD_GET, [
            'fields' => 'preview_pictures'
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemController::class);
        $this->assertMatchedRouteName('api/item/item/get');
        $this->assertActionName('item');

        $json = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);

        $this->assertNotEmpty($json);
    }

    public function testCreateCarAndAddToBrand()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/api/item', Request::METHOD_POST, [
            'item_type_id' => 1,
            'name'         => 'Test car'
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemController::class);
        $this->assertMatchedRouteName('api/item/post');
        $this->assertActionName('post');

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
        $this->dispatch(
            'https://www.autowp.ru/api/item-parent',
            Request::METHOD_POST,
            [
                'item_id'   => $carId,
                'parent_id' => 205
            ]
        );

        $this->assertResponseStatusCode(201);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemParentController::class);
        $this->assertMatchedRouteName('api/item-parent/post');
        $this->assertActionName('post');
    }

    public function testTree()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/api/item/1/tree', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemController::class);
        $this->assertMatchedRouteName('api/item/item/tree/get');
        $this->assertActionName('tree');
    }

    public function testCreateBrand()
    {
        $catname = 'test-brand-' . (10000 * microtime(true));

        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/api/item', Request::METHOD_POST, [
            'item_type_id' => 5,
            'name'         => 'Test brand',
            'full_name'    => 'Test brand full name',
            'catname'      => $catname,
            'begin_year'   => 1950
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
        $brandId = (int)array_pop($path);

        $this->assertNotEmpty($brandId);

        // set language values
        $this->reset();

        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/api/item/' . $brandId . '/language/ru', Request::METHOD_PUT, [
            'name'      => 'Тест',
            'text'      => 'Краткое описание',
            'full_text' => 'Полное описание'
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemLanguageController::class);
        $this->assertMatchedRouteName('api/item/item/language/item/put');
        $this->assertActionName('put');


        $this->reset();

        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/api/item-link', Request::METHOD_POST, [
            'item_id' => $brandId,
            'name'    => 'Тест',
            'url'     => 'http://example.com',
            'type_id' => 'default'
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemLinkController::class);
        $this->assertMatchedRouteName('api/item-link/post');
        $this->assertActionName('post');
    }

    public function testBlacklistedCatnameNotAllowedManually()
    {
        $parentVehicleId = $this->createVehicle([
            'is_group' => true
        ]);
        $childVehicleId = $this->createVehicle();
        $this->addItemParent($childVehicleId, $parentVehicleId, [
            'type_id' => 1, // tuning
            'catname' => 'sport'
        ]);

        $json = $this->getItemParent($childVehicleId, $parentVehicleId);

        $this->assertNotEquals('sport', $json['catname']);


        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch(
            'https://www.autowp.ru/api/item-parent/'.$childVehicleId.'/'.$parentVehicleId,
            Request::METHOD_PUT,
            [
                'catname' => 'sport'
            ]
        );

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemParentController::class);
        $this->assertMatchedRouteName('api/item-parent/item/put');
        $this->assertActionName('put');


        $json = $this->getItemParent($childVehicleId, $parentVehicleId);

        $this->assertNotEquals('sport', $json['catname']);
    }

    public function testSubscription()
    {
        $brand = $this->getRandomBrand();

        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch(
            'https://www.autowp.ru/api/item/' . $brand['id'],
            Request::METHOD_PUT,
            [
                'subscription' => 1
            ]
        );

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemController::class);
        $this->assertMatchedRouteName('api/item/item/put');
        $this->assertActionName('put');

        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch(
            'https://www.autowp.ru/api/item/' . $brand['id'],
            Request::METHOD_PUT,
            [
                'subscription' => 0
            ]
        );

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemController::class);
        $this->assertMatchedRouteName('api/item/item/put');
        $this->assertActionName('put');
    }

    public function testItemParentAutoCatnameIsNotEmpty()
    {
        $parentId = $this->createVehicle([
            'name'     => 'Toyota Corolla',
            'is_group' => true
        ]);
        $childId = $this->createVehicle(['name' => 'Toyota Corolla Sedan']);

        $this->addItemParent($childId, $parentId);

        $itemParent = $this->getItemParent($childId, $parentId);

        $this->assertEquals('sedan', $itemParent['catname']);
    }

    public function testItemPoint()
    {
        $itemId = $this->createItem([
            'item_type_id' => 7,
            'name'         => 'Museum of something',
            'lat'          => 20.5,
            'lng'          => -15
        ]);

        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/api/item/' . $itemId, Request::METHOD_GET, [
            'fields' => 'lat,lng'
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemController::class);
        $this->assertMatchedRouteName('api/item/item/get');
        $this->assertActionName('item');

        $json = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);

        $this->assertSame(20.5, $json['lat']);
        $this->assertSame(-15, $json['lng']);
    }

    public function testEngineVehicles()
    {
        $engineId = $this->createItem([
            'item_type_id' => 2,
            'name'         => 'GM 5.0 V6',
        ]);

        $brand = $this->getRandomBrand();
        $this->addItemParent($engineId, $brand['id']);

        $itemId1 = $this->createItem([
            'item_type_id' => 1,
            'name'         => 'Chevrolet Corvette',
        ]);
        $this->addItemParent($itemId1, $brand['id']);

        $itemId2 = $this->createItem([
            'item_type_id' => 1,
            'name'         => 'Pontiac Firebird',
        ]);
        $this->addItemParent($itemId2, $brand['id']);

        $this->setEngineToVehicle($brand['catname'], $engineId, $itemId1);
        $this->setEngineToVehicle($brand['catname'], $engineId, $itemId2);

        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/api/item/' . $engineId, Request::METHOD_GET, [
            'fields'  => 'engine_vehicles'
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemController::class);
        $this->assertMatchedRouteName('api/item/item/get');
        $this->assertActionName('item');

        $json = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);

        $this->assertArrayHasKey('engine_vehicles', $json);
        foreach ($json['engine_vehicles'] as $item) {
            $this->assertNotEmpty($item['name_html']);
            $this->assertNotEmpty($item['url']);
        }
    }
}
