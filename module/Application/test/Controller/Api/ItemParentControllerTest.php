<?php

namespace ApplicationTest\Controller\Api;

use Application\Controller\Api\ItemParentController;
use Application\Test\AbstractHttpControllerTestCase;
use ApplicationTest\Data;
use Laminas\Http\Request;
use Laminas\Json\Json;

class ItemParentControllerTest extends AbstractHttpControllerTestCase
{
    protected string $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    public function testCategoriesFirstOrder(): void
    {
        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
        $this->dispatch('https://www.autowp.ru/api/item-parent', Request::METHOD_GET, [
            'order' => 'categories_first',
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemParentController::class);
        $this->assertMatchedRouteName('api/item-parent/list');
        $this->assertActionName('index');

        $json = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);

        $this->assertNotEmpty($json);
    }
}
