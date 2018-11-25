<?php

namespace ApplicationTest\Controller\Api;

use Zend\Http\Header\Cookie;
use Zend\Http\Request;
use Zend\Json\Json;

use Application\Test\AbstractHttpControllerTestCase;
use Application\Controller\Api\ItemController;
use Application\Controller\Api\ItemLanguageController;
use Application\Controller\Api\ItemLinkController;
use Application\Controller\Api\ItemParentController;
use Application\Controller\Api\PictureController;
use Application\Controller\Api\PictureItemController;

class ItemControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    /**
     * @suppress PhanUndeclaredMethod
     */
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

    /**
     * @suppress PhanUndeclaredMethod
     */
    private function setEngineToVehicle($engineId, $vehicleId)
    {
        $this->reset();

        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch(
            'https://www.autowp.ru/api/item/' . $vehicleId,
            Request::METHOD_PUT,
            [
                'engine_id' => $engineId
            ]
        );

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(\Application\Controller\Api\ItemController::class);
        $this->assertMatchedRouteName('api/item/item/put');
        $this->assertActionName('put');
    }

    /**
     * @suppress PhanUndeclaredMethod
     */
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

        $this->assertNotEmpty($json['items'], 'Failed to found random brand');

        return $json['items'][0];
    }

    /**
     * @suppress PhanUndeclaredMethod
     */
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

    private function mockDuplicateFinder()
    {
        $serviceManager = $this->getApplicationServiceLocator();

        $tables = $serviceManager->get('TableManager');

        $mock = $this->getMockBuilder(\Application\DuplicateFinder::class)
            ->setMethods(['indexImage'])
            ->setConstructorArgs([
                $serviceManager->get('RabbitMQ'),
                $tables->get('df_distance')
            ])
            ->getMock();

        $mock->method('indexImage')->willReturn(true);

        $serviceManager->setService(\Application\DuplicateFinder::class, $mock);
    }

    /**
     * @suppress PhanUndeclaredMethod
     */
    private function addPictureToItem($vehicleId)
    {
        $this->reset();

        $this->mockDuplicateFinder();

        $request = $this->getRequest();
        $request->getHeaders()
            ->addHeader(Cookie::fromString('Cookie: remember=admin-token'))
            ->addHeaderLine('Content-Type', 'multipart/form-data');
        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $request->getServer()->set('REMOTE_ADDR', '127.0.0.1');

        $file = tempnam(sys_get_temp_dir(), 'upl');
        $filename = 'test.jpg';
        copy(__DIR__ . '/../../_files/' . $filename, $file);

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $request->getFiles()->fromArray([
            'file' => [
                'tmp_name' => $file,
                'name'     => $filename,
                'error'    => UPLOAD_ERR_OK,
                'type'     => 'image/jpeg'
            ]
        ]);

        $this->dispatch('https://www.autowp.ru/api/picture', Request::METHOD_POST, [
            'item_id' => $vehicleId
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertModuleName('application');
        $this->assertControllerName(PictureController::class);
        $this->assertMatchedRouteName('api/picture/post');
        $this->assertActionName('post');

        $headers = $this->getResponse()->getHeaders();
        $uri = $headers->get('Location')->uri();
        $parts = explode('/', $uri->getPath());
        $pictureId = $parts[count($parts) - 1];

        return $pictureId;
    }

    /**
     * @suppress PhanUndeclaredMethod
     */
    private function setPerspective($pictureId, $itemId, $perspectiveId)
    {
        $this->reset();

        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch(
            'https://www.autowp.ru/api/picture-item/' . $pictureId. '/' . $itemId . '/1',
            Request::METHOD_PUT,
            [
                'perspective_id' => $perspectiveId
            ]
        );

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(PictureItemController::class);
        $this->assertMatchedRouteName('api/picture-item/item/update');
        $this->assertActionName('update');
    }

    /**
     * @suppress PhanUndeclaredMethod
     */
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

    /**
     * @suppress PhanUndeclaredMethod
     */
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

    /**
     * @suppress PhanUndeclaredMethod
     */
    public function testEngineUnderTheHoodPreviews()
    {
        $vehicleId = $this->createVehicle();
        $engineId = $this->createEngine();
        $brand = $this->getRandomBrand();
        $this->addItemParent($engineId, $brand['id']);
        $this->setEngineToVehicle($engineId, $vehicleId);
        $pictureId = $this->addPictureToItem($vehicleId);

        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/api/picture/' . $pictureId, Request::METHOD_GET);
        $this->assertResponseStatusCode(200);
        $json = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);
        $this->assertEquals('inbox', $json['status']);

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

    /**
     * @suppress PhanUndeclaredMethod
     */
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

    /**
     * @suppress PhanUndeclaredMethod
     */
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

    /**
     * @suppress PhanUndeclaredMethod
     */
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

    /**
     * @suppress PhanUndeclaredMethod
     */
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

    /**
     * @suppress PhanUndeclaredMethod
     */
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

    /**
     * @suppress PhanUndeclaredMethod
     */
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

    /**
     * @suppress PhanUndeclaredMethod
     */
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

        $this->setEngineToVehicle($engineId, $itemId1);
        $this->setEngineToVehicle($engineId, $itemId2);

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

    /**
     * @suppress PhanUndeclaredMethod
     */
    public function testFields()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/api/item', Request::METHOD_GET, [
            'fields' => 'childs_count,name_html,name_text,name_default,description,' .
                'has_text,brands,spec_editor_url,specs_url,categories,' .
                'twins_groups,url,more_pictures_url,preview_pictures,design,'.
                'engine_vehicles,catname,is_concept,spec_id,begin_year,end_year,body',
            'limit'  => 100
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemController::class);
        $this->assertMatchedRouteName('api/item/list');
        $this->assertActionName('index');

        $json = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);

        $this->assertNotEmpty($json['items']);
    }
}
