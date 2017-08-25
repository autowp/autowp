<?php

namespace ApplicationTest\Api\Controller;

use Zend\Http\Header\Cookie;
use Zend\Http\Request;
use Zend\Json\Json;

use Application\Controller\Api\ItemController;
use Application\Controller\Api\PictureController;
use Application\Controller\UploadController;
use Application\Test\AbstractHttpControllerTestCase;

class PictureControllerTest extends AbstractHttpControllerTestCase
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

    private function addPictureToItem($vehicleId)
    {
        $this->reset();

        $this->mockDuplicateFinder();

        $request = $this->getRequest();
        $request->getHeaders()
            ->addHeader(Cookie::fromString('Cookie: remember=admin-token'))
            ->addHeaderLine('Content-Type', 'multipart/form-data');
        $request->getServer()->set('REMOTE_ADDR', '127.0.0.1');

        $file = tempnam(sys_get_temp_dir(), 'upl');
        $filename = 'test.jpg';
        copy(__DIR__ . '/../../_files/' . $filename, $file);

        $request->getFiles()->fromArray([
            'picture' => [
                [
                    'tmp_name' => $file,
                    'name'     => $filename,
                    'error'    => UPLOAD_ERR_OK,
                    'type'     => 'image/jpeg'
                ]
            ]
        ]);
        $url = 'https://www.autowp.ru/upload/send/type/1/item_id/' . $vehicleId;
        $this->dispatch($url, Request::METHOD_POST, [], true);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(UploadController::class);
        $this->assertMatchedRouteName('upload/params');
        $this->assertActionName('send');

        $this->assertResponseHeaderContains('Content-Type', 'application/json; charset=utf-8');

        $json = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);

        $this->assertInternalType('array', $json);
        $this->assertNotEmpty($json);

        foreach ($json as $item) {
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('html', $item);
            $this->assertArrayHasKey('width', $item);
            $this->assertArrayHasKey('height', $item);
        }

        return $json[0]['id'];
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

    private function getItemById($itemId)
    {
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/api/item/' . $itemId, Request::METHOD_GET, [
            'fields' => 'total_pictures,begin_year,end_year'
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemController::class);
        $this->assertMatchedRouteName('api/item/item/get');
        $this->assertActionName('item');

        $json = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);

        return $json;
    }

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

    public function testGetList()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('http://www.autowp.ru/api/picture', Request::METHOD_GET, [
            'fields' => 'owner,thumbnail,add_date,iptc,exif,image,items.item.name_html,' .
                        'items.item.brands.name_html,special_name,copyrights,' .
                        'change_status_user,rights,moder_votes,moder_voted,' .
                        'is_last,views,accepted_count,similar.picture.thumbnail,' .
                        'replaceable,siblings.name_text,ip.rights,ip.blacklist',
            'limit'  => 100
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(PictureController::class);
        $this->assertMatchedRouteName('api/picture/index');

        $this->assertResponseHeaderContains('Content-Type', 'application/json; charset=utf-8');

        $json = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);

        $this->assertNotEmpty($json);
    }

    public function testRandomPicture()
    {
        $this->dispatch('http://www.autowp.ru/api/picture/random-picture', 'GET');

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(PictureController::class);
        $this->assertMatchedRouteName('api/picture/random_picture');

        $this->assertResponseHeaderContains('Content-Type', 'application/json; charset=utf-8');

        $json = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);
        $this->assertArrayHasKey('status', $json);
        $this->assertArrayHasKey('url', $json);
        $this->assertArrayHasKey('name', $json);
        $this->assertArrayHasKey('page', $json);
    }

    public function testNewPicture()
    {
        $this->dispatch('http://www.autowp.ru/api/picture/new-picture', 'GET');

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(PictureController::class);
        $this->assertMatchedRouteName('api/picture/new-picture');

        $this->assertResponseHeaderContains('Content-Type', 'application/json; charset=utf-8');

        $json = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);
        $this->assertArrayHasKey('status', $json);
        $this->assertArrayHasKey('url', $json);
        $this->assertArrayHasKey('name', $json);
        $this->assertArrayHasKey('page', $json);
    }

    public function testCarOfDayPicture()
    {
        $itemOfDay = $this->getApplicationServiceLocator()->get(\Application\Model\CarOfDay::class);
        $row = $itemOfDay->getCurrent();
        $itemId = $row ? $row['item_id'] : null;

        if (! $itemId) {
            $itemId = $this->createItem([
                'item_type_id' => 1,
                'name'         => 'Peugeot 407 Coupe "Car of the day"',
                'begin_year'   => 2006,
                'end_year'     => 2012
            ]);
        }
        $item = $this->getItemById($itemId);

        for ($i = $item['total_pictures']; $i < 5; $i++) {
            $pictureId = $this->addPictureToItem($item['id']);
            $this->acceptPicture($pictureId);
        }


        $this->reset();
        $this->dispatch('http://www.autowp.ru/api/picture/car-of-day-picture', 'GET');

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(PictureController::class);
        $this->assertMatchedRouteName('api/picture/car-of-day-picture');

        $this->assertResponseHeaderContains('Content-Type', 'application/json; charset=utf-8');

        $json = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);
        $this->assertArrayHasKey('status', $json);
        $this->assertArrayHasKey('url', $json);
        $this->assertArrayHasKey('name', $json);
        $this->assertArrayHasKey('page', $json);
    }
}
