<?php

namespace ApplicationTest\Controller\Api;

use Application\Controller\Api\ItemController;
use Application\Test\AbstractHttpControllerTestCase;
use ApplicationTest\Data;
use Exception;
use Laminas\Http\Header\Location;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Http\Response;
use Laminas\Json\Json;

use function array_pop;
use function count;
use function explode;
use function microtime;

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
}
