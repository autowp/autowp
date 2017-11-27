<?php

namespace ApplicationTest\Frontend\Controller;

use Zend\Http\Header\Cookie;
use Zend\Http\Request;
use Zend\Json\Json;

use Application\Controller\Api\ItemController;
use Application\Controller\Api\ItemParentController;
use Application\Controller\Api\PictureController;
use Application\Controller\TwinsController;
use Application\Test\AbstractHttpControllerTestCase;

class TwinsControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    private function mockDuplicateFinder()
    {
        $serviceManager = $this->getApplicationServiceLocator();

        $tables = $serviceManager->get('TableManager');

        $mock = $this->getMockBuilder(\Application\DuplicateFinder::class)
            ->setMethods(['indexImage'])
            ->setConstructorArgs([
                $tables->get('df_hash'),
                $tables->get('df_distance')
            ])
            ->getMock();

        $mock->method('indexImage')->willReturn(true);

        $serviceManager->setService(\Application\DuplicateFinder::class, $mock);
    }

    private function addPictureToItem($itemId)
    {
        $this->reset();

        $this->mockDuplicateFinder();

        $request = $this->getRequest();
        $request->getHeaders()
            ->addHeader(Cookie::fromString('Cookie: remember=admin-token'))
            ->addHeaderLine('Content-Type', 'multipart/form-data');
        $request->getServer()->set('REMOTE_ADDR', '127.0.0.1');

        $file = tempnam(sys_get_temp_dir(), 'upl');
        $filename = '640x480.jpg';
        copy(__DIR__ . '/../../_files/' . $filename, $file);

        $request->getFiles()->fromArray([
            'file' => [
                'tmp_name' => $file,
                'name'     => $filename,
                'error'    => UPLOAD_ERR_OK,
                'type'     => 'image/jpeg'
            ]
        ]);
        $this->dispatch('https://www.autowp.ru/api/picture', Request::METHOD_POST, [
            'item_id' => $itemId
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

    private function getRandomBrand()
    {
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/api/item', Request::METHOD_GET, [
            'type_id' => 5,
            'order'   => 'id_desc',
            'fields'  => 'name,catname'
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

    public function testIndexActionCanBeAccessed()
    {
        $this->dispatch('http://www.autowp.ru/twins', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(TwinsController::class);
        $this->assertMatchedRouteName('twins');
    }

    public function testGroup()
    {
        $groupName = 'Daihatsu / Toyota Cordoba';

        $groupId = $this->createItem([
            'item_type_id' => 4,
            'name'         => $groupName
        ]);

        $itemId1 = $this->createItem([
            'item_type_id' => 1,
            'name'         => 'Toyota Cordoba'
        ]);

        $itemId2 = $this->createItem([
            'item_type_id' => 1,
            'name'         => 'Daihatsu Cordoba'
        ]);

        $this->addItemParent($itemId1, $groupId);
        $this->addItemParent($itemId2, $groupId);

        $brand = $this->getRandomBrand();

        $this->addItemParent($itemId1, $brand['id']);

        $this->addPictureToItem($itemId1);
        $this->addPictureToItem($itemId2);

        $tokens = [
            '',
            'token',
            'admin-token'
        ];
        foreach ($tokens as $token) {
            $this->reset();
            $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=' . $token));
            $this->dispatch('http://www.autowp.ru/twins/group' . $groupId, Request::METHOD_GET);

            $this->assertResponseStatusCode(200);
            $this->assertModuleName('application');
            $this->assertControllerName(TwinsController::class);
            $this->assertMatchedRouteName('twins/group');

            $this->assertXpathQuery("//h1[contains(text(), '$groupName')]");

            $this->reset();
            $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=' . $token));
            $this->dispatch('http://www.autowp.ru/twins/group' . $groupId.'/pictures', Request::METHOD_GET);

            $this->assertResponseStatusCode(200);
            $this->assertModuleName('application');
            $this->assertControllerName(TwinsController::class);
            $this->assertMatchedRouteName('twins/group/pictures');

            $this->assertXpathQuery("//h1[contains(text(), '$groupName')]");

            $this->reset();
            $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=' . $token));
            $this->dispatch('http://www.autowp.ru/twins/group' . $groupId.'/specifications', Request::METHOD_GET);

            $this->assertResponseStatusCode(200);
            $this->assertModuleName('application');
            $this->assertControllerName(TwinsController::class);
            $this->assertMatchedRouteName('twins/group/specifications');

            $this->assertXpathQuery("//h1[contains(text(), '$groupName')]");
        }
    }
}
