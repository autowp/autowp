<?php

namespace ApplicationTest\Api\Controller;

use Zend\Http\Header\Cookie;
use Zend\Http\Request;
use Zend\Json\Json;

use Application\Controller\Api\ItemController;
use Application\Controller\UploadController;
use Application\Test\AbstractHttpControllerTestCase;
use Application\Controller\Api\PictureModerVoteController;

class PictureModerControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    private function addPictureToItem($vehicleId)
    {
        $this->reset();

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
        $this->dispatch('https://www.autowp.ru/upload/send/type/1/item_id/' . $vehicleId, Request::METHOD_POST, [], true);

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

    public function testVote()
    {
        $itemId = $this->createItem([
            'item_type_id' => 1,
            'name'         => 'Peugeot 404 Coupe "Voted"',
            'begin_year'   => 1980,
            'end_year'     => 1990
        ]);
        $pictureId = $this->addPictureToItem($itemId);


        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('http://www.autowp.ru/api/picture-moder-vote/' . $pictureId, Request::METHOD_PUT, [
            'vote'   => 1,
            'reason' => 'Good pic',
            'save'   => 1
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(PictureModerVoteController::class);
        $this->assertMatchedRouteName('api/picture-moder-vote');


        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('http://www.autowp.ru/api/picture-moder-vote/' . $pictureId, Request::METHOD_DELETE);

        $this->assertResponseStatusCode(204);
        $this->assertModuleName('application');
        $this->assertControllerName(PictureModerVoteController::class);
        $this->assertMatchedRouteName('api/picture-moder-vote');


        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('http://www.autowp.ru/api/picture-moder-vote/' . $pictureId, Request::METHOD_PUT, [
            'vote'   => -1,
            'reason' => 'Poor pic',
            'save'   => 0
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(PictureModerVoteController::class);
        $this->assertMatchedRouteName('api/picture-moder-vote');
    }
}
