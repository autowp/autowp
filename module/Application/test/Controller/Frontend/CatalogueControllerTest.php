<?php

namespace ApplicationTest\Controller\Frontend;

use Zend\Http\Header\Cookie;
use Zend\Http\Request;
use Zend\Json\Json;

use Application\Controller\Api\PictureItemController;
use Application\Controller\CatalogueController;
use Application\Controller\UploadController;
use Application\Test\AbstractHttpControllerTestCase;
use Application\Controller\Api\PictureController;
use Application\Controller\Api\ItemController;
use Application\Model\Item;
use Application\Controller\Api\ItemParentController;

class CatalogueControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    private function setPerspective($itemId, $pictureId, $perspectiveId)
    {
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/api/picture-item/' . $pictureId . '/' . $itemId, Request::METHOD_PUT, [
            'perspective_id' => $perspectiveId
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(PictureItemController::class);
        $this->assertMatchedRouteName('api/picture-item/update');
        $this->assertActionName('update');
    }

    private function addPictureToItem($itemId)
    {
        $this->reset();

        $request = $this->getRequest();
        $request->getHeaders()
            ->addHeader(Cookie::fromString('Cookie: remember=admin-token'))
            ->addHeaderLine('Content-Type', 'multipart/form-data');
        $request->getServer()->set('REMOTE_ADDR', '127.0.0.1');

        $file = tempnam(sys_get_temp_dir(), 'upl');
        $filename = 'test1.jpg';
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
        $this->dispatch('https://www.autowp.ru/upload/send/type/1/item_id/' . $itemId, Request::METHOD_POST, [], true);

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

    private function getPicture($itemId)
    {
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/api/picture/' . $itemId, Request::METHOD_GET, [
            'fields' => 'identity'
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(PictureController::class);
        $this->assertMatchedRouteName('api/picture/picture/item');
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

        return $json['items'][0];
    }

    public function testBrand()
    {
        $catname = 'brand-' . microtime(true);
        $name = 'Test brand';

        $this->createItem([
            'item_type_id' => 5,
            'catname'      => $catname,
            'name'         => $name
        ]);

        $this->reset();
        $this->dispatch('https://www.autowp.ru/' . $catname, Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('brand');

        $this->assertXpathQuery("//h1[contains(text(), '$name')]");
    }

    public function testCars()
    {
        $carId = $this->createItem([
            'name'         => 'Test car',
            'item_type_id' => 1,
            'is_concept'   => 0,
            'begin_year'   => 1999,
            'end_year'     => 2001,
            'is_group'     => 0
        ]);

        $this->addItemParent($carId, 204);

        // request
        $this->reset();
        $this->dispatch('https://www.autowp.ru/bmw/cars', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('cars');

        $this->assertXpathQuery("//h1[contains(text(), 'BMW')]");
    }

    public function testRecent()
    {
        $brand = $this->getRandomBrand();

        $pictureId = $this->addPictureToItem($brand['id']);
        $this->acceptPicture($pictureId);

        $this->reset();
        $this->dispatch('https://www.autowp.ru/'.$brand['catname'].'/recent', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('recent');

        $this->assertXpathQuery("//h1[contains(text(), '{$brand['name']}')]");
    }

    public function testConcepts()
    {
        $carId = $this->createItem([
            'item_type_id' => 1,
            'name'         => 'Test concept car',
            'is_concept'   => 1
        ]);

        // check is_concept
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/api/item/' . $carId, Request::METHOD_GET, [
            'fields' => 'is_concept'
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertMatchedRouteName('api/item/item/get');
        $this->assertResponseHeaderContains('Content-Type', 'application/json; charset=utf-8');

        $data = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);
        $this->assertTrue($data['is_concept']);

        $brand = $this->getRandomBrand();

        // add to brand
        $this->addItemParent($carId, $brand['id']);

        // request concept
        $this->reset();

        $this->dispatch('https://www.autowp.ru/'.$brand['catname'].'/concepts', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('concepts');

        $this->assertXpathQuery("//h1[contains(text(), '{$brand['name']}')]");
    }

    public function testOther()
    {
        $pictureId = $this->addPictureToItem(204);
        $this->acceptPicture($pictureId);

        $this->reset();
        $this->dispatch('https://www.autowp.ru/bmw/other', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('other');

        $this->assertQuery(".thumbnail");
    }

    public function testMixed()
    {
        $pictureId = $this->addPictureToItem(204);
        $this->acceptPicture($pictureId);
        $this->setPerspective(204, $pictureId, 25);

        $this->reset();
        $this->dispatch('https://www.autowp.ru/bmw/mixed', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('mixed');

        $this->assertQuery(".thumbnail");
    }

    public function testLogotypes()
    {
        $pictureId = $this->addPictureToItem(204);
        $this->acceptPicture($pictureId);
        $this->setPerspective(204, $pictureId, 22);

        $this->reset();
        $this->dispatch('https://www.autowp.ru/bmw/logotypes', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('logotypes');

        $this->assertQuery(".thumbnail");
    }

    public function testOtherPicture()
    {
        $pictureId = $this->addPictureToItem(204);
        $this->acceptPicture($pictureId);
        $picture = $this->getPicture($pictureId);

        $this->reset();
        $this->dispatch('https://www.autowp.ru/bmw/other/'.$picture['identity'].'/', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('other-picture');

        $this->assertQuery(".thumbnail");
    }

    public function testMixedPicture()
    {
        $pictureId = $this->addPictureToItem(204);
        $this->acceptPicture($pictureId);
        $this->setPerspective(204, $pictureId, 25);
        $picture = $this->getPicture($pictureId);

        $this->reset();
        $this->dispatch('https://www.autowp.ru/bmw/mixed/'.$picture['identity'].'/', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('mixed-picture');

        $this->assertQuery(".thumbnail");
    }

    public function testLogotypesPicture()
    {
        $pictureId = $this->addPictureToItem(204);
        $this->acceptPicture($pictureId);
        $this->setPerspective(204, $pictureId, 22);
        $picture = $this->getPicture($pictureId);

        $this->reset();
        $this->dispatch('https://www.autowp.ru/bmw/logotypes/'.$picture['identity'].'/', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('logotypes-picture');

        $this->assertQuery(".thumbnail");
    }

    public function testOtherGallery()
    {
        $this->dispatch('https://www.autowp.ru/bmw/other/gallery/', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('other-gallery');
        $this->assertResponseHeaderContains('Content-Type', 'application/json; charset=utf-8');

        $data = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);

        $this->assertArrayHasKey('page', $data);
        $this->assertArrayHasKey('pages', $data);
        $this->assertArrayHasKey('count', $data);
        $this->assertArrayHasKey('items', $data);
    }

    public function testMixedGallery()
    {
        $this->dispatch('https://www.autowp.ru/bmw/mixed/gallery/', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('mixed-gallery');
        $this->assertResponseHeaderContains('Content-Type', 'application/json; charset=utf-8');

        $data = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);

        $this->assertArrayHasKey('page', $data);
        $this->assertArrayHasKey('pages', $data);
        $this->assertArrayHasKey('count', $data);
        $this->assertArrayHasKey('items', $data);
    }

    public function testLogotypesGallery()
    {
        $this->dispatch('https://www.autowp.ru/bmw/logotypes/gallery/', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('logotypes-gallery');
        $this->assertResponseHeaderContains('Content-Type', 'application/json; charset=utf-8');

        $data = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);

        $this->assertArrayHasKey('page', $data);
        $this->assertArrayHasKey('pages', $data);
        $this->assertArrayHasKey('count', $data);
        $this->assertArrayHasKey('items', $data);
    }

    /*public function testVehiclePicture()
    {
        $pictureId = $this->addPictureToItem(1);
        $this->acceptPicture($pictureId);
        $picture = $this->getPicture($pictureId);

        $this->reset();
        $this->dispatch('https://www.autowp.ru/bmw/first-car/pictures/'.$picture['identity'], Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('brand-item-picture');
    }*/

    public function testBrandMosts()
    {
        // add to brand
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch(
            'https://www.autowp.ru/api/item-parent',
            Request::METHOD_POST,
            [
                'item_id'   => 3,
                'parent_id' => 204
            ]
        );

        $this->assertResponseStatusCode(201);

        $this->reset();
        $this->dispatch('https://www.autowp.ru/bmw/mosts', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('brand-mosts');
    }
}
