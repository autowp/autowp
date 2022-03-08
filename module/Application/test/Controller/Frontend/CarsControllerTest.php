<?php

namespace ApplicationTest\Controller\Frontend;

use Application\Controller\Api\AttrController;
use Application\Controller\Api\ItemController;
use Application\Test\AbstractHttpControllerTestCase;
use ApplicationTest\Data;
use Exception;
use Laminas\Http\Header\Location;
use Laminas\Http\Request;
use Laminas\Http\Response;
use Laminas\Json\Json;

use function count;
use function explode;

class CarsControllerTest extends AbstractHttpControllerTestCase
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

    public function testSelectCarEngine(): void
    {
        // create engine
        $engineId = $this->createItem([
            'item_type_id' => 2,
            'name'         => 'Engine',
        ]);

        // select engine
        $this->reset();
        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
        $this->dispatch('https://www.autowp.ru/api/item/1', Request::METHOD_PUT, [
            'engine_id' => $engineId,
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemController::class);
        $this->assertMatchedRouteName('api/item/item/put');
        $this->assertActionName('put');

        // cancel engine
        $this->reset();
        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
        $this->dispatch('https://www.autowp.ru/api/item/1', Request::METHOD_PUT, [
            'engine_id' => '',
            'foo'       => 'bar', // workaround for zf bug
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemController::class);
        $this->assertMatchedRouteName('api/item/item/put');
        $this->assertActionName('put');

        // inherit engine
        $this->reset();
        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
        $this->dispatch('https://www.autowp.ru/api/item/1', Request::METHOD_PUT, [
            'engine_id' => 'inherited',
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemController::class);
        $this->assertMatchedRouteName('api/item/item/put');
        $this->assertActionName('put');
    }

    public function testCarsSpecificationsEditor(): void
    {
        $this->reset();
        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));

        $this->dispatch('https://www.autowp.ru/api/user/me', Request::METHOD_GET);
        $this->assertResponseStatusCode(200);

        $me = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);

        $this->reset();
        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));

        $url = 'https://www.autowp.ru/api/attr/user-value';
        $this->dispatch($url, Request::METHOD_PATCH, [
            'items' => [
                ['item_id' => 1, 'attribute_id' => 66, 'user_id' => $me['id'], 'value' => '11'],
                ['item_id' => 1, 'attribute_id' => 13, 'user_id' => $me['id'], 'value' => '3'],
                ['item_id' => 1, 'attribute_id' => 67, 'user_id' => $me['id'], 'value' => '5'],
                ['item_id' => 1, 'attribute_id' => 68, 'user_id' => $me['id'], 'value' => '2'],
                ['item_id' => 1, 'attribute_id' => 204, 'user_id' => $me['id'], 'value' => '85'],
                ['item_id' => 1, 'attribute_id' => 1, 'user_id' => $me['id'], 'value' => '1234'],
                ['item_id' => 1, 'attribute_id' => 2, 'user_id' => $me['id'], 'value' => '2345'],
                ['item_id' => 1, 'attribute_id' => 140, 'user_id' => $me['id'], 'value' => '3456'],
                ['item_id' => 1, 'attribute_id' => 3, 'user_id' => $me['id'], 'value' => '4567'],
                ['item_id' => 1, 'attribute_id' => 141, 'user_id' => $me['id'], 'value' => '5678'],
                ['item_id' => 1, 'attribute_id' => 203, 'user_id' => $me['id'], 'value' => '6789'],
                ['item_id' => 1, 'attribute_id' => 4, 'user_id' => $me['id'], 'value' => '2600'],
                ['item_id' => 1, 'attribute_id' => 5, 'user_id' => $me['id'], 'value' => '2000'],
                ['item_id' => 1, 'attribute_id' => 6, 'user_id' => $me['id'], 'value' => '2100'],
                ['item_id' => 1, 'attribute_id' => 176, 'user_id' => $me['id'], 'value' => '20'],
                ['item_id' => 1, 'attribute_id' => 7, 'user_id' => $me['id'], 'value' => '30'],
                ['item_id' => 1, 'attribute_id' => 168, 'user_id' => $me['id'], 'value' => '40'],
                ['item_id' => 1, 'attribute_id' => 64, 'user_id' => $me['id'], 'value' => '0.20'],
                ['item_id' => 1, 'attribute_id' => 65, 'user_id' => $me['id'], 'value' => '0.40'],
                ['item_id' => 1, 'attribute_id' => 71, 'user_id' => $me['id'], 'value' => '1300'],
                ['item_id' => 1, 'attribute_id' => 72, 'user_id' => $me['id'], 'value' => '1400'],
                ['item_id' => 1, 'attribute_id' => 73, 'user_id' => $me['id'], 'value' => '1500'],
                ['item_id' => 1, 'attribute_id' => 100, 'user_id' => $me['id'], 'value' => 'HDi'],
                ['item_id' => 1, 'attribute_id' => 207, 'user_id' => $me['id'], 'value' => '106'],
                ['item_id' => 1, 'attribute_id' => 20, 'user_id' => $me['id'], 'value' => '2'],
                ['item_id' => 1, 'attribute_id' => 21, 'user_id' => $me['id'], 'value' => '4'],
                ['item_id' => 1, 'attribute_id' => 23, 'user_id' => $me['id'], 'value' => '25'],
                ['item_id' => 1, 'attribute_id' => 156, 'user_id' => $me['id'], 'value' => '69'],
                ['item_id' => 1, 'attribute_id' => 25, 'user_id' => $me['id'], 'value' => '10'],
                ['item_id' => 1, 'attribute_id' => 26, 'user_id' => $me['id'], 'value' => '8'],
                ['item_id' => 1, 'attribute_id' => 27, 'user_id' => $me['id'], 'value' => '4'],
                ['item_id' => 1, 'attribute_id' => 28, 'user_id' => $me['id'], 'value' => '84.5'],
                ['item_id' => 1, 'attribute_id' => 29, 'user_id' => $me['id'], 'value' => '80.3'],
                ['item_id' => 1, 'attribute_id' => 159, 'user_id' => $me['id'], 'value' => '50'],
                ['item_id' => 1, 'attribute_id' => 31, 'user_id' => $me['id'], 'value' => '2963'],
                ['item_id' => 1, 'attribute_id' => 33, 'user_id' => $me['id'], 'value' => '200'],
                ['item_id' => 1, 'attribute_id' => 34, 'user_id' => $me['id'], 'value' => '4000'],
                ['item_id' => 1, 'attribute_id' => 35, 'user_id' => $me['id'], 'value' => '5000'],
                ['item_id' => 1, 'attribute_id' => 171, 'user_id' => $me['id'], 'value' => '200'],
                ['item_id' => 1, 'attribute_id' => 172, 'user_id' => $me['id'], 'value' => '200'],
                ['item_id' => 1, 'attribute_id' => 173, 'user_id' => $me['id'], 'value' => '200'],
                ['item_id' => 1, 'attribute_id' => 174, 'user_id' => $me['id'], 'value' => '200'],
                ['item_id' => 1, 'attribute_id' => 177, 'user_id' => $me['id'], 'value' => '200'],
                ['item_id' => 1, 'attribute_id' => 178, 'user_id' => $me['id'], 'value' => '200'],
                ['item_id' => 1, 'attribute_id' => 37, 'user_id' => $me['id'], 'value' => '480'],
                ['item_id' => 1, 'attribute_id' => 38, 'user_id' => $me['id'], 'value' => '4500'],
                ['item_id' => 1, 'attribute_id' => 39, 'user_id' => $me['id'], 'value' => '6000'],
                ['item_id' => 1, 'attribute_id' => 30, 'user_id' => $me['id'], 'value' => '14'],
                ['item_id' => 1, 'attribute_id' => 98, 'user_id' => $me['id'], 'value' => ['39']],
                ['item_id' => 1, 'attribute_id' => 99, 'user_id' => $me['id'], 'value' => '47'],
                ['item_id' => 1, 'attribute_id' => 179, 'user_id' => $me['id'], 'value' => '81'],
                ['item_id' => 1, 'attribute_id' => 206, 'user_id' => $me['id'], 'value' => '100'],
                ['item_id' => 1, 'attribute_id' => 41, 'user_id' => $me['id'], 'value' => '17'],
                ['item_id' => 1, 'attribute_id' => 139, 'user_id' => $me['id'], 'value' => 'Aisin'],
                ['item_id' => 1, 'attribute_id' => 43, 'user_id' => $me['id'], 'value' => '50'],
                ['item_id' => 1, 'attribute_id' => 44, 'user_id' => $me['id'], 'value' => '6'],
                ['item_id' => 1, 'attribute_id' => 83, 'user_id' => $me['id'], 'value' => 'Clutch'],
                ['item_id' => 1, 'attribute_id' => 209, 'user_id' => $me['id'], 'value' => '121'],
                ['item_id' => 1, 'attribute_id' => 210, 'user_id' => $me['id'], 'value' => '131'],
                ['item_id' => 1, 'attribute_id' => 213, 'user_id' => $me['id'], 'value' => '1'],
                ['item_id' => 1, 'attribute_id' => 214, 'user_id' => $me['id'], 'value' => '152'],
                ['item_id' => 1, 'attribute_id' => 215, 'user_id' => $me['id'], 'value' => '155'],
                ['item_id' => 1, 'attribute_id' => 216, 'user_id' => $me['id'], 'value' => '1'],
                ['item_id' => 1, 'attribute_id' => 212, 'user_id' => $me['id'], 'value' => '1'],
                ['item_id' => 1, 'attribute_id' => 218, 'user_id' => $me['id'], 'value' => 161],
                ['item_id' => 1, 'attribute_id' => 219, 'user_id' => $me['id'], 'value' => 181],
                ['item_id' => 1, 'attribute_id' => 222, 'user_id' => $me['id'], 'value' => '1'],
                ['item_id' => 1, 'attribute_id' => 223, 'user_id' => $me['id'], 'value' => null, 'empty' => true],
                ['item_id' => 1, 'attribute_id' => 224, 'user_id' => $me['id'], 'value' => null, 'empty' => true],
                ['item_id' => 1, 'attribute_id' => 225, 'user_id' => $me['id'], 'value' => '1'],
                ['item_id' => 1, 'attribute_id' => 221, 'user_id' => $me['id'], 'value' => '1'],
                ['item_id' => 1, 'attribute_id' => 10, 'user_id' => $me['id'], 'value' => 'Steering type'],
                ['item_id' => 1, 'attribute_id' => 8, 'user_id' => $me['id'], 'value' => 'Front suspension'],
                ['item_id' => 1, 'attribute_id' => 9, 'user_id' => $me['id'], 'value' => 'Rear suspension'],
                ['item_id' => 1, 'attribute_id' => 182, 'user_id' => $me['id'], 'value' => '12'],
                ['item_id' => 1, 'attribute_id' => 47, 'user_id' => $me['id'], 'value' => '300'],
                ['item_id' => 1, 'attribute_id' => 48, 'user_id' => $me['id'], 'value' => '10'],
                ['item_id' => 1, 'attribute_id' => 175, 'user_id' => $me['id'], 'value' => '11'],
                ['item_id' => 1, 'attribute_id' => 49, 'user_id' => $me['id'], 'value' => '20'],
                ['item_id' => 1, 'attribute_id' => 50, 'user_id' => $me['id'], 'value' => '30'],
                ['item_id' => 1, 'attribute_id' => 51, 'user_id' => $me['id'], 'value' => '40'],
                ['item_id' => 1, 'attribute_id' => 52, 'user_id' => $me['id'], 'value' => '80'],
                ['item_id' => 1, 'attribute_id' => 53, 'user_id' => $me['id'], 'value' => '1'],
                ['item_id' => 1, 'attribute_id' => 160, 'user_id' => $me['id'], 'value' => '8'],
                ['item_id' => 1, 'attribute_id' => 161, 'user_id' => $me['id'], 'value' => '25'],
                ['item_id' => 1, 'attribute_id' => 55, 'user_id' => $me['id'], 'value' => '30'],
                ['item_id' => 1, 'attribute_id' => 56, 'user_id' => $me['id'], 'value' => '20'],
                ['item_id' => 1, 'attribute_id' => 58, 'user_id' => $me['id'], 'value' => '60'],
                ['item_id' => 1, 'attribute_id' => 59, 'user_id' => $me['id'], 'value' => '90'],
                ['item_id' => 1, 'attribute_id' => 61, 'user_id' => $me['id'], 'value' => '200'],
                ['item_id' => 1, 'attribute_id' => 62, 'user_id' => $me['id'], 'value' => '300'],
                ['item_id' => 1, 'attribute_id' => 79, 'user_id' => $me['id'], 'value' => '8.8'],
                ['item_id' => 1, 'attribute_id' => 80, 'user_id' => $me['id'], 'value' => '10.5'],
                ['item_id' => 1, 'attribute_id' => 81, 'user_id' => $me['id'], 'value' => '8.8'],
                ['item_id' => 1, 'attribute_id' => 185, 'user_id' => $me['id'], 'value' => '8.8'],
                ['item_id' => 1, 'attribute_id' => 186, 'user_id' => $me['id'], 'value' => '10.5'],
                ['item_id' => 1, 'attribute_id' => 187, 'user_id' => $me['id'], 'value' => '8.8'],
                ['item_id' => 1, 'attribute_id' => 188, 'user_id' => $me['id'], 'value' => '10.5'],
                ['item_id' => 1, 'attribute_id' => 190, 'user_id' => $me['id'], 'value' => '8.8'],
                ['item_id' => 1, 'attribute_id' => 191, 'user_id' => $me['id'], 'value' => '10.5'],
                ['item_id' => 1, 'attribute_id' => 193, 'user_id' => $me['id'], 'value' => '8.8'],
                ['item_id' => 1, 'attribute_id' => 194, 'user_id' => $me['id'], 'value' => '10.5'],
                ['item_id' => 1, 'attribute_id' => 200, 'user_id' => $me['id'], 'value' => '8.8'],
                ['item_id' => 1, 'attribute_id' => 201, 'user_id' => $me['id'], 'value' => '10.5'],
                ['item_id' => 1, 'attribute_id' => 202, 'user_id' => $me['id'], 'value' => '11.6'],
                ['item_id' => 1, 'attribute_id' => 138, 'user_id' => $me['id'], 'value' => '1'],
                ['item_id' => 1, 'attribute_id' => 158, 'user_id' => $me['id'], 'value' => '300'],
                ['item_id' => 1, 'attribute_id' => 205, 'user_id' => $me['id'], 'value' => '800'],
                ['item_id' => 1, 'attribute_id' => 11, 'user_id' => $me['id'], 'value' => '12'],
                ['item_id' => 1, 'attribute_id' => 196, 'user_id' => $me['id'], 'value' => '11.5'],
                ['item_id' => 1, 'attribute_id' => 197, 'user_id' => $me['id'], 'value' => '12.5'],
                ['item_id' => 1, 'attribute_id' => 198, 'user_id' => $me['id'], 'value' => '3'],
                ['item_id' => 1, 'attribute_id' => 77, 'user_id' => $me['id'], 'value' => '0'],
                ['item_id' => 1, 'attribute_id' => 75, 'user_id' => $me['id'], 'value' => 'Front breakes'],
                ['item_id' => 1, 'attribute_id' => 144, 'user_id' => $me['id'], 'value' => '58'],
                ['item_id' => 1, 'attribute_id' => 146, 'user_id' => $me['id'], 'value' => '130'],
                ['item_id' => 1, 'attribute_id' => 148, 'user_id' => $me['id'], 'value' => '30'],
                ['item_id' => 1, 'attribute_id' => 150, 'user_id' => $me['id'], 'value' => '62'],
                ['item_id' => 1, 'attribute_id' => 152, 'user_id' => $me['id'], 'value' => '1'],
                ['item_id' => 1, 'attribute_id' => 153, 'user_id' => $me['id'], 'value' => '1'],
                ['item_id' => 1, 'attribute_id' => 76, 'user_id' => $me['id'], 'value' => 'Rear breakes'],
                ['item_id' => 1, 'attribute_id' => 145, 'user_id' => $me['id'], 'value' => 60],
                ['item_id' => 1, 'attribute_id' => 147, 'user_id' => $me['id'], 'value' => '130'],
                ['item_id' => 1, 'attribute_id' => 149, 'user_id' => $me['id'], 'value' => '30'],
                ['item_id' => 1, 'attribute_id' => 151, 'user_id' => $me['id'], 'value' => 66],
                ['item_id' => 1, 'attribute_id' => 154, 'user_id' => $me['id'], 'value' => '1'],
                ['item_id' => 1, 'attribute_id' => 155, 'user_id' => $me['id'], 'value' => '1'],
                ['item_id' => 1, 'attribute_id' => 82, 'user_id' => $me['id'], 'value' => '330'],
                ['item_id' => 1, 'attribute_id' => 157, 'user_id' => $me['id'], 'value' => '74'],
                ['item_id' => 1, 'attribute_id' => 164, 'user_id' => $me['id'], 'value' => 'GALAXY'],
                ['item_id' => 1, 'attribute_id' => 165, 'user_id' => $me['id'], 'value' => '79'],
                ['item_id' => 1, 'attribute_id' => 87, 'user_id' => $me['id'], 'value' => '235'],
                ['item_id' => 1, 'attribute_id' => 90, 'user_id' => $me['id'], 'value' => '45'],
                ['item_id' => 1, 'attribute_id' => 88, 'user_id' => $me['id'], 'value' => '18'],
                ['item_id' => 1, 'attribute_id' => 89, 'user_id' => $me['id'], 'value' => '7'],
                ['item_id' => 1, 'attribute_id' => 162, 'user_id' => $me['id'], 'value' => '20'],
                ['item_id' => 1, 'attribute_id' => 91, 'user_id' => $me['id'], 'value' => '235'],
                ['item_id' => 1, 'attribute_id' => 94, 'user_id' => $me['id'], 'value' => '45'],
                ['item_id' => 1, 'attribute_id' => 92, 'user_id' => $me['id'], 'value' => '18'],
                ['item_id' => 1, 'attribute_id' => 93, 'user_id' => $me['id'], 'value' => '7'],
                ['item_id' => 1, 'attribute_id' => 163, 'user_id' => $me['id'], 'value' => '20'],
                ['item_id' => 1, 'attribute_id' => 170, 'user_id' => $me['id'], 'value' => 'Toyota Motors Mitischi'],
            ],
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(AttrController::class);
        $this->assertMatchedRouteName('api/attr/user-value/patch');
        $this->assertActionName('user-value-patch');
    }
}
