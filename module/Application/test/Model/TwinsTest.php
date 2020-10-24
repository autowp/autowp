<?php

namespace ApplicationTest\Model;

use Application\Controller\Api\ItemController;
use Application\Controller\Api\ItemParentController;
use Application\Model\Twins;
use Application\Test\AbstractHttpControllerTestCase;
use ApplicationTest\Data;
use Exception;
use Laminas\Http\Request;

use function array_replace;
use function count;
use function explode;

class TwinsTest extends AbstractHttpControllerTestCase
{
    protected string $applicationConfigPath = __DIR__ . '/../../../../config/application.config.php';

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

    public function testGetCarsGroups()
    {
        $vehicle1Id = $this->createItem([
            'item_type_id' => 1,
            'name'         => 'First vehicle',
        ]);

        $vehicle2Id = $this->createItem([
            'item_type_id' => 1,
            'name'         => 'Second vehicle',
        ]);

        $groupId = $this->createItem([
            'item_type_id' => 4,
            'name'         => 'Twins group',
        ]);

        $this->addItemParent($vehicle1Id, $groupId);
        $this->addItemParent($vehicle2Id, $groupId);

        $serviceManager = $this->getApplicationServiceLocator();

        $model = $serviceManager->get(Twins::class);

        $groups = $model->getCarsGroups([$vehicle1Id, $vehicle2Id], 'en');

        $this->assertArrayHasKey($vehicle1Id, $groups);
        $this->assertArrayHasKey($vehicle2Id, $groups);

        $this->assertNotEmpty($groups[$vehicle1Id]);
        $this->assertNotEmpty($groups[$vehicle2Id]);
    }
}
