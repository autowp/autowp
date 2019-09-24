<?php

namespace ApplicationTest\Api\Controller;

use Application\DuplicateFinder;
use Application\Model\CarOfDay;
use Exception;
use Zend\Http\Header\Cookie;
use Zend\Http\Request;
use Zend\Json\Json;

use Application\Controller\Api\ItemController;
use Application\Controller\Api\PictureController;
use Application\Test\AbstractHttpControllerTestCase;

class PictureControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    private function mockDuplicateFinder()
    {
        $serviceManager = $this->getApplicationServiceLocator();

        $tables = $serviceManager->get('TableManager');

        $mock = $this->getMockBuilder(DuplicateFinder::class)
            ->setMethods(['indexImage'])
            ->setConstructorArgs([
                $serviceManager->get('RabbitMQ'),
                $tables->get('df_distance')
            ])
            ->getMock();

        $mock->method('indexImage')->willReturn(true);

        $serviceManager->setService(DuplicateFinder::class, $mock);
    }

    /**
     * @suppress PhanUndeclaredMethod
     * @param int $vehicleId
     * @return int
     * @throws Exception
     */
    private function addPictureToItem(int $vehicleId): int
    {
        $this->reset();

        $this->mockDuplicateFinder();

        $request = $this->getRequest();
        $request->getHeaders()
            ->addHeader(Cookie::fromString('Cookie: remember=admin-token'))
            ->addHeaderLine('Content-Type', 'multipart/form-data');
        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $request->getServer()->set('REMOTE_ADDR', '127.0.0.1');

        $file = tempnam(sys_get_temp_dir(), 'upl');
        $filename = 'test.jpg';
        copy(__DIR__ . '/../../_files/' . $filename, $file);

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $request->getFiles()->fromArray([
            'file' => [
                'tmp_name' => $file,
                'name'     => $filename,
                'error'    => UPLOAD_ERR_OK,
                'type'     => 'image/jpeg'
            ]
        ]);

        $this->dispatch('https://www.autowp.ru/api/picture', Request::METHOD_POST, [
            'item_id' => $vehicleId
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

    /**
     * @suppress PhanUndeclaredMethod
     * @param $params
     * @return int
     * @throws Exception
     */
    private function createItem($params): int
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

    /**
     * @suppress PhanUndeclaredMethod
     * @param int $itemId
     * @return mixed
     * @throws Exception
     */
    private function getItemById(int $itemId)
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

    /**
     * @suppress PhanUndeclaredMethod
     * @param int $pictureId
     * @throws Exception
     */
    private function acceptPicture(int $pictureId)
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

    /**
     * @suppress PhanUndeclaredMethod
     */
    public function testGetList()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('http://www.autowp.ru/api/picture', Request::METHOD_GET, [
            'fields' => 'owner,thumb_medium,add_date,exif,image,items.item.name_html,' .
                        'items.item.brands.name_html,special_name,copyrights,' .
                        'change_status_user,rights,moder_votes,moder_voted,' .
                        'is_last,views,accepted_count,similar.picture.thumb_medium,' .
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
        $this->assertTrue($json['status']);
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
        $this->assertTrue($json['status']);
        $this->assertArrayHasKey('url', $json);
        $this->assertArrayHasKey('name', $json);
        $this->assertArrayHasKey('page', $json);
    }

    public function testCarOfDayPicture()
    {
        $itemOfDay = $this->getApplicationServiceLocator()->get(CarOfDay::class);
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
        $this->assertTrue($json['status']);
        $this->assertArrayHasKey('url', $json);
        $this->assertArrayHasKey('name', $json);
        $this->assertArrayHasKey('page', $json);
    }
}
