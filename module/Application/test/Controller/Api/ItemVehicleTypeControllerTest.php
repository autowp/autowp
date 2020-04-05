<?php

namespace ApplicationTest\Controller\Api;

use Application\Controller\Api\ItemController;
use Application\Controller\Api\ItemParentController;
use Application\Controller\Api\ItemVehicleTypeController;
use Application\Model\VehicleType;
use Application\Test\AbstractHttpControllerTestCase;
use ApplicationTest\Data;
use Exception;
use Laminas\Http\Request;
use Laminas\Json\Json;

use function array_replace;
use function count;
use function explode;
use function in_array;
use function sprintf;

class ItemVehicleTypeControllerTest extends AbstractHttpControllerTestCase
{
    protected string $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    /**
     * @suppress PhanUndeclaredMethod
     * @throws Exception
     */
    private function createItem(array $params): int
    {
        $this->reset();

        $this->getRequest()->getHeaders()->addHeader(Data::getAdminAuthHeader());
        $this->dispatch('https://www.autowp.ru/api/item', Request::METHOD_POST, $params);

        $this->assertResponseStatusCode(201);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemController::class);
        $this->assertMatchedRouteName('api/item/post');
        $this->assertActionName('post');

        $headers = $this->getResponse()->getHeaders();
        $uri     = $headers->get('Location')->uri();
        $parts   = explode('/', $uri->getPath());

        return (int) $parts[count($parts) - 1];
    }

    /**
     * @suppress PhanUndeclaredMethod
     * @throws Exception
     */
    private function addItemParent(int $itemId, int $parentId, array $params = []): void
    {
        $this->reset();

        $this->getRequest()->getHeaders()->addHeader(Data::getAdminAuthHeader());
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

    /**
     * @suppress PhanUndeclaredMethod
     * @throws Exception
     */
    private function setItemVehicleTypes(int $itemId, array $vehicleTypeIds): void
    {
        $this->reset();

        $ids = $this->getItemVehicleTypeIds($itemId);

        foreach ($ids as $id) {
            if (! in_array($id, $vehicleTypeIds)) {
                $this->reset();
                $this->getRequest()->getHeaders()->addHeader(Data::getAdminAuthHeader());
                $url = 'https://www.autowp.ru/api/item-vehicle-type/' . $itemId . '/' . $id;
                $this->dispatch($url, Request::METHOD_DELETE);

                $this->assertResponseStatusCode(204);
                $this->assertModuleName('application');
                $this->assertControllerName(ItemVehicleTypeController::class);
                $this->assertMatchedRouteName('api/item-vehicle-type/item/delete');
                $this->assertActionName('delete');
            }
        }

        foreach ($vehicleTypeIds as $id) {
            if (! in_array($id, $ids)) {
                $this->reset();
                $this->getRequest()->getHeaders()->addHeader(Data::getAdminAuthHeader());
                $url = sprintf(
                    'https://www.autowp.ru/api/item-vehicle-type/%s/%s',
                    $itemId,
                    $id
                );
                $this->dispatch($url, Request::METHOD_POST);

                $this->assertResponseStatusCode(201);
                $this->assertModuleName('application');
                $this->assertControllerName(ItemVehicleTypeController::class);
                $this->assertMatchedRouteName('api/item-vehicle-type/item/post');
                $this->assertActionName('create');
            }
        }
    }

    /**
     * @suppress PhanUndeclaredMethod
     * @throws Exception
     */
    private function getItemVehicleTypeIds(int $itemId): array
    {
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Data::getAdminAuthHeader());
        $this->dispatch('https://www.autowp.ru/api/item-vehicle-type', Request::METHOD_GET, [
            'item_id' => $itemId,
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemVehicleTypeController::class);
        $this->assertMatchedRouteName('api/item-vehicle-type/index');
        $this->assertActionName('index');

        $json = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);

        $this->assertNotEmpty($json);
        $result = [];
        foreach ($json['items'] as $item) {
            $result[] = $item['vehicle_type_id'];
        }

        return $result;
    }

    private function getItemInheritedVehicleTypeIds(int $itemId): array
    {
        $serviceManager = $this->getApplicationServiceLocator();
        /** @var VehicleType $vehicleType */
        $vehicleType = $serviceManager->get(VehicleType::class);

        return $vehicleType->getVehicleTypes($itemId, true);
    }

    private function assertVehicleTypes(int $itemId, array $expected, array $inheritedExpected): void
    {
        $this->assertEquals($expected, $this->getItemVehicleTypeIds($itemId));
        $this->assertEquals($inheritedExpected, $this->getItemInheritedVehicleTypeIds($itemId));
    }

    /**
     * @suppress PhanUndeclaredMethod
     */
    public function testList(): void
    {
        $this->getRequest()->getHeaders()->addHeader(Data::getAdminAuthHeader());
        $this->dispatch('https://www.autowp.ru/api/item-vehicle-type', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemVehicleTypeController::class);
        $this->assertMatchedRouteName('api/item-vehicle-type/index');
        $this->assertActionName('index');

        $json = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);

        $this->assertNotEmpty($json);
    }

    public function testCreateVehicleAndSetVehicleTypeAndChange(): void
    {
        $itemId = $this->createItem([
            'item_type_id' => 1,
            'name'         => 'Toyota CoroVa',
        ]);
        $this->setItemVehicleTypes($itemId, [1]);

        $this->assertVehicleTypes($itemId, [1], []);

        $this->setItemVehicleTypes($itemId, [2, 3]);

        $this->assertVehicleTypes($itemId, [2, 3], []);
    }

    public function testVehicleTypePresistsAfterAddParentWithoutVehicleType(): void
    {
        $itemId = $this->createItem([
            'item_type_id' => 1,
            'name'         => 'Toyota Camry',
            'body'         => 'gen.3',
        ]);
        $this->setItemVehicleTypes($itemId, [2, 3]);

        $parentId = $this->createItem([
            'item_type_id' => 1,
            'name'         => 'Toyota Camry',
            'is_group'     => true,
        ]);

        $this->addItemParent($itemId, $parentId);

        $this->setItemVehicleTypes($itemId, [2, 3]);
    }

    public function testInheritedWhenChildCreated(): void
    {
        $parentId = $this->createItem([
            'item_type_id' => 1,
            'name'         => 'Parent vehicle',
            'is_group'     => true,
        ]);

        $this->setItemVehicleTypes($parentId, [1]);
        $this->assertVehicleTypes($parentId, [1], []);

        $childId = $this->createItem([
            'item_type_id' => 1,
            'name'         => 'Child vehicle',
        ]);
        $this->assertVehicleTypes($childId, [], []);

        $this->addItemParent($childId, $parentId);
        $this->assertVehicleTypes($childId, [], [1]);
    }

    public function testInheritedWhenParentVehicleTypeChanged(): void
    {
        $parentId = $this->createItem([
            'item_type_id' => 1,
            'name'         => 'Parent vehicle',
            'is_group'     => true,
        ]);
        $this->assertVehicleTypes($parentId, [], []);

        $childId = $this->createItem([
            'item_type_id' => 1,
            'name'         => 'Child vehicle',
        ]);
        $this->assertVehicleTypes($childId, [], []);

        $this->addItemParent($childId, $parentId);
        $this->assertVehicleTypes($childId, [], []);

        $this->setItemVehicleTypes($parentId, [1]);

        $this->assertVehicleTypes($parentId, [1], []);
        $this->assertVehicleTypes($childId, [], [1]);
    }

    public function testNotInheritedWhenHaveOwnValue(): void
    {
        $parentId = $this->createItem([
            'item_type_id' => 1,
            'name'         => 'Parent vehicle',
            'is_group'     => true,
        ]);

        $childId = $this->createItem([
            'item_type_id' => 1,
            'name'         => 'Child vehicle',
        ]);

        $this->addItemParent($childId, $parentId);

        $this->setItemVehicleTypes($parentId, [1]);
        $this->setItemVehicleTypes($childId, [2]);

        $this->assertVehicleTypes($childId, [2], []);
    }

    public function testInheritWhenClearOwnValue(): void
    {
        $childId = $this->createItem([
            'item_type_id' => 1,
            'name'         => 'Child vehicle',
        ]);
        $this->setItemVehicleTypes($childId, [2]);

        $parentId = $this->createItem([
            'item_type_id' => 1,
            'name'         => 'Parent vehicle',
            'is_group'     => true,
        ]);
        $this->setItemVehicleTypes($parentId, [2, 3]);

        $this->addItemParent($childId, $parentId);

        $this->setItemVehicleTypes($childId, []);

        $this->assertVehicleTypes($childId, [], [2, 3]);
    }
}
