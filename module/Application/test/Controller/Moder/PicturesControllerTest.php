<?php

namespace ApplicationTest\Controller\Moder;

use Zend\Http\Header\Cookie;
use Zend\Http\Request;
use Application\Test\AbstractHttpControllerTestCase;

use Application\Controller\Moder\PicturesController;
use Application\Model\DbTable\Picture;
use Zend\Json\Json;

class PicturesControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../_files/application.config.php';

    public function testIndexForbidden()
    {
        $this->dispatch('https://www.autowp.ru/moder/pictures', Request::METHOD_GET);

        $this->assertResponseStatusCode(403);
        $this->assertModuleName('application');
        $this->assertControllerName(PicturesController::class);
        $this->assertMatchedRouteName('moder/pictures');
        $this->assertActionName('forbidden');
    }

    public function testPicture()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/pictures/picture/picture_id/1', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(PicturesController::class);
        $this->assertMatchedRouteName('moder/pictures/params');
        $this->assertActionName('picture');
    }

    public function testIndex()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/pictures', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(PicturesController::class);
        $this->assertMatchedRouteName('moder/pictures');
        $this->assertActionName('index');
    }

    public function testMove()
    {
        // upload picture
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
        $this->dispatch('https://www.autowp.ru/upload/index/type/1/item_id/1', Request::METHOD_POST, [], true);

        $this->assertResponseStatusCode(200);
        $this->assertResponseHeaderContains('Content-Type', 'application/json; charset=utf-8');

        $json = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);

        $pictureId = $json[0]['id'];

        // move to car
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $url = 'https://www.autowp.ru/moder/pictures/move/' . implode('/', [
            'picture_id/' . $pictureId,
            'type/' . Picture::VEHICLE_TYPE_ID,
            'item_id/1'
        ]);
        $this->dispatch($url, Request::METHOD_POST);

        $this->assertResponseStatusCode(302);
        $this->assertHasResponseHeader('Location');

        // move to brand
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $url = 'https://www.autowp.ru/moder/pictures/move/' . implode('/', [
            'picture_id/' . $pictureId,
            'type/' . Picture::LOGO_TYPE_ID,
            'brand_id/1'
        ]);
        $this->dispatch($url, Request::METHOD_POST);

        $this->assertResponseStatusCode(302);
        $this->assertHasResponseHeader('Location');

        // move to factory
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $url = 'https://www.autowp.ru/moder/pictures/move/' . implode('/', [
            'picture_id/' . $pictureId,
            'type/' . Picture::FACTORY_TYPE_ID,
            'factory_id/1'
        ]);
        $this->dispatch($url, Request::METHOD_POST);

        $this->assertResponseStatusCode(302);
        $this->assertHasResponseHeader('Location');
    }
}
