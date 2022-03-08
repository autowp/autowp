<?php

namespace ApplicationTest\Controller\Api;

use Application\Controller\Api\AttrController;
use Application\Test\AbstractHttpControllerTestCase;
use ApplicationTest\Data;
use JsonException;
use Laminas\Http\Request;
use Laminas\Json\Json;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class AttrControllerTest extends AbstractHttpControllerTestCase
{
    protected string $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws JsonException
     */
    public function testUnitIndex(): void
    {
        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
        $this->dispatch('https://www.autowp.ru/api/attr/unit', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(AttrController::class);
        $this->assertMatchedRouteName('api/attr/unit/get');
        $this->assertActionName('unit-index');
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws JsonException
     */
    public function testListOptionsIndex(): void
    {
        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
        $this->dispatch('https://www.autowp.ru/api/attr/list-option', Request::METHOD_GET, [
            'attribute_id' => 20,
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(AttrController::class);
        $this->assertMatchedRouteName('api/attr/list-option/get');
        $this->assertActionName('list-option-index');

        $this->assertResponseHeaderContains('Content-Type', 'application/json; charset=utf-8');

        $data = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);
        $this->assertIsArray($data['items']);
    }
}
