<?php

namespace ApplicationTest\Controller\Api;

use Application\Controller\Api\ItemController;
use Application\Controller\Api\ItemParentController;
use Application\Controller\Api\PictureController;
use Application\Controller\Api\PictureItemController;
use Application\DuplicateFinder;
use Application\Model\Item;
use Application\Test\AbstractHttpControllerTestCase;
use ApplicationTest\Data;
use Exception;
use Laminas\Http\Header\Location;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Http\Response;
use Laminas\Json\Json;

use function array_pop;
use function array_replace;
use function copy;
use function count;
use function explode;
use function microtime;
use function sys_get_temp_dir;
use function tempnam;

use const UPLOAD_ERR_OK;

class ItemControllerTest extends AbstractHttpControllerTestCase
{
    protected string $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    /**
     * @throws Exception
     */
    private function createItem(array $params): int
    {
        $this->reset();

        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
        $this->dispatch('https://www.autowp.ru/api/item', Request::METHOD_POST, $params);

        $this->assertResponseStatusCode(201);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemController::class);
        $this->assertMatchedRouteName('api/item/post');
        $this->assertActionName('post');

        /** @var Response $response */
        $response = $this->getResponse();
        /** @var Location $location */
        $location = $response->getHeaders()->get('Location');
        $uri      = $location->uri();
        $parts    = explode('/', $uri->getPath());
        return (int) $parts[count($parts) - 1];
    }

    private function createVehicle(array $params = []): int
    {
        return $this->createItem(array_replace([
            'item_type_id' => 1,
            'name'         => 'Some vehicle',
        ], $params));
    }

    private function createEngine(): int
    {
        return $this->createItem([
            'item_type_id' => 2,
            'name'         => 'Some engine',
        ]);
    }

    /**
     * @throws Exception
     */
    private function setEngineToVehicle(int $engineId, int $vehicleId): void
    {
        $this->reset();

        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
        $this->dispatch(
            'https://www.autowp.ru/api/item/' . $vehicleId,
            Request::METHOD_PUT,
            [
                'engine_id' => $engineId,
            ]
        );

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemController::class);
        $this->assertMatchedRouteName('api/item/item/put');
        $this->assertActionName('put');
    }

    /**
     * @throws Exception
     */
    private function getRandomBrand(): array
    {
        $this->reset();
        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
        $this->dispatch('https://www.autowp.ru/api/item', Request::METHOD_GET, [
            'type_id' => 5,
            'order'   => 'id_desc',
            'fields'  => 'catname,subscription',
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
     * @throws Exception
     */
    private function addItemParent(int $itemId, int $parentId, array $params = []): void
    {
        $this->reset();

        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
        $this->dispatch(
            'https://www.autowp.ru/api/item-parent',
            Request::METHOD_POST,
            array_replace([
                'item_id'   => $itemId,
                'parent_id' => $parentId,
            ], $params)
        );

        $this->assertResponseStatusCode(201);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemParentController::class);
        $this->assertMatchedRouteName('api/item-parent/post');
        $this->assertActionName('post');
    }

    private function mockDuplicateFinder(): void
    {
        $serviceManager = $this->getApplicationServiceLocator();

        $tables = $serviceManager->get('TableManager');

        $mock = $this->getMockBuilder(DuplicateFinder::class)
            ->onlyMethods(['indexImage'])
            ->setConstructorArgs([
                $serviceManager->get('RabbitMQ'),
                $tables->get('df_distance'),
            ])
            ->getMock();

        $mock->method('indexImage');

        $serviceManager->setService(DuplicateFinder::class, $mock);
    }

    /**
     * @throws Exception
     */
    private function addPictureToItem(int $vehicleId): int
    {
        $this->reset();

        $this->mockDuplicateFinder();

        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()
            ->addHeader(Data::getAdminAuthHeader(
                $this->getApplicationServiceLocator()->get('Config')['keycloak']
            ))
            ->addHeaderLine('Content-Type', 'multipart/form-data');
        $request->getServer()->set('REMOTE_ADDR', '127.0.0.1');

        $file     = tempnam(sys_get_temp_dir(), 'upl');
        $filename = 'test.jpg';
        copy(__DIR__ . '/../../_files/' . $filename, $file);

        $request->getFiles()->fromArray([
            'file' => [
                'tmp_name' => $file,
                'name'     => $filename,
                'error'    => UPLOAD_ERR_OK,
                'type'     => 'image/jpeg',
            ],
        ]);

        $this->dispatch('https://www.autowp.ru/api/picture', Request::METHOD_POST, [
            'item_id' => $vehicleId,
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertModuleName('application');
        $this->assertControllerName(PictureController::class);
        $this->assertMatchedRouteName('api/picture/post');
        $this->assertActionName('post');

        /** @var Response $response */
        $response = $this->getResponse();
        /** @var Location $location */
        $location = $response->getHeaders()->get('Location');
        $uri      = $location->uri();
        $parts    = explode('/', $uri->getPath());
        return (int) $parts[count($parts) - 1];
    }

    /**
     * @throws Exception
     */
    private function setPerspective(int $pictureId, int $itemId, int $perspectiveId): void
    {
        $this->reset();

        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
        $this->dispatch(
            'https://www.autowp.ru/api/picture-item/' . $pictureId . '/' . $itemId . '/1',
            Request::METHOD_PUT,
            [
                'perspective_id' => $perspectiveId,
            ]
        );

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(PictureItemController::class);
        $this->assertMatchedRouteName('api/picture-item/item/update');
        $this->assertActionName('update');
    }

    /**
     * @throws Exception
     */
    private function acceptPicture(int $pictureId): void
    {
        $this->reset();

        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
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
     * @throws Exception
     */
    private function getItemParent(int $itemId, int $parentId): array
    {
        $this->reset();

        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
        $this->dispatch(
            'https://www.autowp.ru/api/item-parent/' . $itemId . '/' . $parentId,
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
     * @throws Exception
     */
    public function testEngineUnderTheHoodPreviews(): void
    {
        $vehicleId = $this->createVehicle();
        $engineId  = $this->createEngine();
        $brand     = $this->getRandomBrand();
        $this->addItemParent($engineId, $brand['id']);
        $this->setEngineToVehicle($engineId, $vehicleId);
        $pictureId = $this->addPictureToItem($vehicleId);

        $this->reset();
        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
        $this->dispatch('https://www.autowp.ru/api/picture/' . $pictureId, Request::METHOD_GET);
        $this->assertResponseStatusCode(200);
        $json = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);
        $this->assertEquals('inbox', $json['status']);

        $this->acceptPicture($pictureId);
        $this->setPerspective($pictureId, $vehicleId, 17);

        $this->reset();
        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
        $this->dispatch('https://www.autowp.ru/api/item/' . $engineId, Request::METHOD_GET, [
            'fields' => 'preview_pictures',
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
     * @throws Exception
     */
    public function testCreateCarAndAddToBrand(): void
    {
        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
        $this->dispatch('https://www.autowp.ru/api/item', Request::METHOD_POST, [
            'item_type_id' => 1,
            'name'         => 'Test car',
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemController::class);
        $this->assertMatchedRouteName('api/item/post');
        $this->assertActionName('post');

        /** @var Response $response */
        $response = $this->getResponse();
        /** @var Location $location */
        $location = $response->getHeaders()->get('Location');
        $uri      = $location->uri();
        $parts    = explode('/', $uri->getPath());
        $carId    = $parts[count($parts) - 1];

        $this->assertNotEmpty($carId);

        // add to brand
        $this->reset();
        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
        $this->dispatch(
            'https://www.autowp.ru/api/item-parent',
            Request::METHOD_POST,
            [
                'item_id'   => $carId,
                'parent_id' => 205,
            ]
        );

        $this->assertResponseStatusCode(201);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemParentController::class);
        $this->assertMatchedRouteName('api/item-parent/post');
        $this->assertActionName('post');
    }

    public function testTree(): void
    {
        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
        $this->dispatch('https://www.autowp.ru/api/item/1/tree', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemController::class);
        $this->assertMatchedRouteName('api/item/item/tree/get');
        $this->assertActionName('tree');
    }

    /**
     * @throws Exception
     */
    public function testCreateBrand(): void
    {
        $catname = 'test-brand-' . (10000 * microtime(true));

        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
        $this->dispatch('https://www.autowp.ru/api/item', Request::METHOD_POST, [
            'item_type_id' => 5,
            'name'         => 'Test brand',
            'full_name'    => 'Test brand full name',
            'catname'      => $catname,
            'begin_year'   => 1950,
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemController::class);
        $this->assertMatchedRouteName('api/item/post');
        $this->assertActionName('post');

        $this->assertHasResponseHeader('Location');

        /** @var Response $response */
        $response = $this->getResponse();
        /** @var Location $header */
        $header = $response->getHeaders()->get('Location');
        $path   = $header->uri()->getPath();

        $this->assertStringStartsWith('/api/item/', $path);

        $path    = explode('/', $path);
        $brandId = (int) array_pop($path);

        $this->assertNotEmpty($brandId);
    }

    /**
     * @throws Exception
     */
    public function testBlacklistedCatnameNotAllowedManually(): void
    {
        $parentVehicleId = $this->createVehicle([
            'is_group' => true,
        ]);
        $childVehicleId  = $this->createVehicle();
        $this->addItemParent($childVehicleId, $parentVehicleId, [
            'type_id' => 1, // tuning
            'catname' => 'sport',
        ]);

        $json = $this->getItemParent($childVehicleId, $parentVehicleId);

        $this->assertNotEquals('sport', $json['catname']);

        $this->reset();
        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
        $this->dispatch(
            'https://www.autowp.ru/api/item-parent/' . $childVehicleId . '/' . $parentVehicleId,
            Request::METHOD_PUT,
            [
                'catname' => 'sport',
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
     * @throws Exception
     */
    public function testSubscription(): void
    {
        $brand = $this->getRandomBrand();

        $this->reset();
        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
        $this->dispatch(
            'https://www.autowp.ru/api/item/' . $brand['id'],
            Request::METHOD_PUT,
            [
                'subscription' => 1,
            ]
        );

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemController::class);
        $this->assertMatchedRouteName('api/item/item/put');
        $this->assertActionName('put');

        $this->reset();
        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
        $this->dispatch(
            'https://www.autowp.ru/api/item/' . $brand['id'],
            Request::METHOD_PUT,
            [
                'subscription' => 0,
            ]
        );

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemController::class);
        $this->assertMatchedRouteName('api/item/item/put');
        $this->assertActionName('put');
    }

    /**
     * @throws Exception
     */
    public function testItemParentAutoCatnameIsNotEmpty(): void
    {
        $parentId = $this->createVehicle([
            'name'     => 'Toyota Corolla',
            'is_group' => true,
        ]);
        $childId  = $this->createVehicle(['name' => 'Toyota Corolla Sedan']);

        $this->addItemParent($childId, $parentId);

        $itemParent = $this->getItemParent($childId, $parentId);

        $this->assertEquals('sedan', $itemParent['catname']);
    }

    /**
     * @throws Exception
     */
    public function testItemPoint(): void
    {
        $itemId = $this->createItem([
            'item_type_id' => 7,
            'name'         => 'Museum of something',
            'lat'          => 20.5,
            'lng'          => -15,
        ]);

        $this->reset();
        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
        $this->dispatch('https://www.autowp.ru/api/item/' . $itemId, Request::METHOD_GET, [
            'fields' => 'lat,lng',
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
     * @throws Exception
     */
    public function testEngineVehicles(): void
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
        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
        $this->dispatch('https://www.autowp.ru/api/item/' . $engineId, Request::METHOD_GET, [
            'fields' => 'engine_vehicles',
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
        }
    }

    /**
     * @throws Exception
     */
    public function testFields(): void
    {
        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
        $this->dispatch('https://www.autowp.ru/api/item', Request::METHOD_GET, [
            'fields' => 'childs_count,name_html,name_text,name_default,description,attr_zone_id,'
                . 'has_text,brands,spec_editor_url,specs_route,categories,'
                . 'twins_groups,url,preview_pictures,design,'
                . 'engine_vehicles,catname,is_concept,spec_id,begin_year,end_year,body',
            'limit'  => 100,
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemController::class);
        $this->assertMatchedRouteName('api/item/list');
        $this->assertActionName('index');

        $json = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);

        $this->assertNotEmpty($json['items']);
    }

    public function testCreateCategoryAddItemAndGet(): void
    {
        $catname = 'catname-' . (10000 * microtime(true));

        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
        $this->dispatch('https://www.autowp.ru/api/item', Request::METHOD_POST, [
            'item_type_id' => 3,
            'name'         => 'Test category',
            'catname'      => $catname,
            'begin_year'   => 2000,
            'end_year'     => 2000,
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemController::class);
        $this->assertMatchedRouteName('api/item/post');
        $this->assertActionName('post');

        $this->assertHasResponseHeader('Location');

        /** @var Response $response */
        $response = $this->getResponse();
        /** @var Location $header */
        $header = $response->getHeaders()->get('Location');
        $path   = $header->uri()->getPath();

        $this->assertStringStartsWith('/api/item/', $path);

        $path       = explode('/', $path);
        $categoryId = (int) array_pop($path);

        $this->assertNotEmpty($categoryId);

        // add item to category
        $this->reset();

        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
        $this->dispatch(
            'https://www.autowp.ru/api/item-parent',
            Request::METHOD_POST,
            [
                'item_id'   => 1,
                'parent_id' => $categoryId,
            ]
        );

        $this->assertResponseStatusCode(201);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemParentController::class);
        $this->assertMatchedRouteName('api/item-parent/post');
        $this->assertActionName('post');
    }

    /**
     * @throws Exception
     */
    public function testNatSort(): void
    {
        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
        $this->dispatch('https://www.autowp.ru/api/item', Request::METHOD_GET, [
            'limit' => 100,
            'order' => 'name_nat',
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemController::class);
        $this->assertMatchedRouteName('api/item/list');
        $this->assertActionName('index');

        $json = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);

        $this->assertNotEmpty($json['items']);
    }

    /**
     * @throws Exception
     */
    public function testPath(): void
    {
        $topCatname = 'top' . microtime(true);
        $categoryId = $this->createItem([
            'item_type_id' => Item::CATEGORY,
            'name'         => 'top level',
            'catname'      => $topCatname,
        ]);

        $lvl2Catname    = 'lvl2' . microtime(true);
        $lvl2CategoryId = $this->createItem([
            'item_type_id' => Item::CATEGORY,
            'name'         => 'sub level',
            'catname'      => $lvl2Catname,
        ]);

        $lvl3Catname    = 'lvl3' . microtime(true);
        $lvl3CategoryId = $this->createItem([
            'item_type_id' => Item::CATEGORY,
            'name'         => 'sub level',
            'catname'      => $lvl3Catname,
        ]);

        $this->addItemParent($lvl2CategoryId, $categoryId);
        $this->addItemParent($lvl3CategoryId, $lvl2CategoryId);

        $this->reset();
        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
        $this->dispatch('https://www.autowp.ru/api/item/path', Request::METHOD_GET, [
            'catname' => $lvl3Catname,
            'path'    => '',
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemController::class);
        $this->assertMatchedRouteName('api/item/path/get');
        $this->assertActionName('path');

        $json = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);

        $this->assertNotEmpty($json['path']);
    }
}
