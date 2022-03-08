<?php

namespace ApplicationTest\Controller\Api;

use Application\Controller\Api\BrandsController;
use Application\Test\AbstractHttpControllerTestCase;
use ApplicationTest\Data;
use JsonException;
use Laminas\Http\Header\Location;
use Laminas\Http\Request;
use Laminas\Http\Response;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

use function count;
use function explode;

class BrandsControllerTest extends AbstractHttpControllerTestCase
{
    protected string $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    public function testBrandsIndex(): void
    {
        $this->dispatch('https://www.autowp.ru/api/brands', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(BrandsController::class);
        $this->assertMatchedRouteName('api/brands/get');
        $this->assertActionName('index');
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws JsonException
     */
    public function testNewItems(): void
    {
        $brandId = 204;

        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
        $this->dispatch('https://www.autowp.ru/api/item', Request::METHOD_POST, [
            'name'         => 'Car for testNewcars',
            'item_type_id' => 1,
        ]);

        $this->assertResponseStatusCode(201);

        /** @var Response $response */
        $response = $this->getResponse();
        /** @var Location $location */
        $location = $response->getHeaders()->get('Location');
        $uri      = $location->uri();
        $parts    = explode('/', $uri->getPath());
        $carId    = $parts[count($parts) - 1];

        // add to brand
        $this->reset();
        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
        $this->dispatch('https://www.autowp.ru/api/item-parent', Request::METHOD_POST, [
            'parent_id' => $brandId,
            'item_id'   => $carId,
        ]);
        $this->assertResponseStatusCode(201);

        // page
        $this->reset();
        $this->dispatch('https://www.autowp.ru/api/brands/' . $brandId . '/new-items', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(BrandsController::class);
        $this->assertMatchedRouteName('api/brands/item/new-items/get');
        $this->assertActionName('new-items');

        $this->assertXpathQuery("//*[contains(text(), 'Car for testNewcars')]");
    }
}
