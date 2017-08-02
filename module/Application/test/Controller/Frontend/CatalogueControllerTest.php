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
        $filename = '640x480.jpg';
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

    /**
     * @dataProvider userTokenProvider
     */
    public function testBrand(string $token)
    {
        $catname = 'brand-' . microtime(true);
        $name = 'Test brand';

        $this->createItem([
            'item_type_id' => 5,
            'catname'      => $catname,
            'name'         => $name
        ]);

        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=' . $token));
        $this->dispatch('https://www.autowp.ru/' . $catname, Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('brand');

        $this->assertXpathQuery("//h1[contains(text(), '$name')]");
    }

    /**
     * @dataProvider userTokenProvider
     */
    public function testCars(string $token)
    {
        $carId = $this->createItem([
            'name'         => 'Test car',
            'item_type_id' => 1,
            'is_concept'   => 0,
            'begin_year'   => 1999,
            'end_year'     => 2001,
            'is_group'     => 0
        ]);

        $brand = $this->getRandomBrand();
        $this->addItemParent($carId, $brand['id']);

        // request
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=' . $token));
        $this->dispatch('https://www.autowp.ru/'.$brand['catname'].'/cars', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('cars');

        $this->assertXpathQuery("//h1[contains(text(), '{$brand['name']}')]");
    }

    /**
     * @dataProvider userTokenProvider
     */
    public function testRecent(string $token)
    {
        $brand = $this->getRandomBrand();

        $pictureId = $this->addPictureToItem($brand['id']);
        $this->acceptPicture($pictureId);

        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=' . $token));
        $this->dispatch('https://www.autowp.ru/'.$brand['catname'].'/recent', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('recent');

        $this->assertXpathQuery("//h1[contains(text(), '{$brand['name']}')]");
    }

    /**
     * @dataProvider userTokenProvider
     */
    public function testConcepts(string $token)
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
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=' . $token));
        $this->dispatch('https://www.autowp.ru/'.$brand['catname'].'/concepts', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('concepts');

        $this->assertXpathQuery("//h1[contains(text(), '{$brand['name']}')]");
    }

    /**
     * @dataProvider userTokenProvider
     */
    public function testOther(string $token)
    {
        $pictureId = $this->addPictureToItem(204);
        $this->acceptPicture($pictureId);

        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=' . $token));
        $this->dispatch('https://www.autowp.ru/bmw/other', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('other');

        $this->assertQuery(".thumbnail");
    }

    /**
     * @dataProvider userTokenProvider
     */
    public function testMixed(string $token)
    {
        $pictureId = $this->addPictureToItem(204);
        $this->acceptPicture($pictureId);
        $this->setPerspective(204, $pictureId, 25);

        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=' . $token));
        $this->dispatch('https://www.autowp.ru/bmw/mixed', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('mixed');

        $this->assertQuery(".thumbnail");
    }

    /**
     * @dataProvider userTokenProvider
     */
    public function testLogotypes(string $token)
    {
        $pictureId = $this->addPictureToItem(204);
        $this->acceptPicture($pictureId);
        $this->setPerspective(204, $pictureId, 22);

        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=' . $token));
        $this->dispatch('https://www.autowp.ru/bmw/logotypes', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('logotypes');

        $this->assertQuery(".thumbnail");
    }

    /**
     * @dataProvider userTokenProvider
     */
    public function testOtherPicture(string $token)
    {
        $pictureId = $this->addPictureToItem(204);
        $this->acceptPicture($pictureId);
        $picture = $this->getPicture($pictureId);

        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=' . $token));
        $this->dispatch('https://www.autowp.ru/bmw/other/'.$picture['identity'].'/', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('other-picture');

        $this->assertQuery(".thumbnail");
    }

    /**
     * @dataProvider userTokenProvider
     */
    public function testMixedPicture(string $token)
    {
        $pictureId = $this->addPictureToItem(204);
        $this->acceptPicture($pictureId);
        $this->setPerspective(204, $pictureId, 25);
        $picture = $this->getPicture($pictureId);

        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=' . $token));
        $this->dispatch('https://www.autowp.ru/bmw/mixed/'.$picture['identity'].'/', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('mixed-picture');

        $this->assertQuery(".thumbnail");
    }

    /**
     * @dataProvider userTokenProvider
     */
    public function testLogotypesPicture(string $token)
    {
        $pictureId = $this->addPictureToItem(204);
        $this->acceptPicture($pictureId);
        $this->setPerspective(204, $pictureId, 22);
        $picture = $this->getPicture($pictureId);

        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=' . $token));
        $this->dispatch('https://www.autowp.ru/bmw/logotypes/'.$picture['identity'].'/', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('logotypes-picture');

        $this->assertQuery(".thumbnail");
    }

    /**
     * @dataProvider userTokenProvider
     */
    public function testOtherGallery(string $token)
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=' . $token));
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

    /**
     * @dataProvider userTokenProvider
     */
    public function testMixedGallery(string $token)
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=' . $token));
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

    /**
     * @dataProvider userTokenProvider
     */
    public function testLogotypesGallery(string $token)
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=' . $token));
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

    /**
     * @dataProvider userTokenProvider
     */
    public function testBrandMosts(string $token)
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
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=' . $token));
        $this->dispatch('https://www.autowp.ru/bmw/mosts', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('brand-mosts');
    }

    /**
     * @dataProvider userTokenProvider
     */
    public function testBrandFactories(string $token)
    {
        $factoryId = $this->createItem([
            'item_type_id' => 6,
            'name'         => 'Factory'
        ]);
        $pictureId = $this->addPictureToItem($factoryId);

        $vehicleId = $this->createItem([
            'item_type_id' => 1,
            'name'         => 'Vehicle builded on factory'
        ]);

        $this->addItemParent($vehicleId, $factoryId);

        $brand = $this->getRandomBrand();
        $this->addItemParent($vehicleId, $brand['id']);

        $this->acceptPicture($pictureId);

        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=' . $token));
        $this->dispatch('https://www.autowp.ru/'.$brand['catname'], Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('brand');

        $this->assertXpathQuery("//h2[contains(text(), 'Factories')]");
    }

    /**
     * @dataProvider userTokenProvider
     */
    public function testBrandItem(string $token)
    {
        $catname = 'brand-item-' . microtime(true);
        $name = 'Vehicle';

        $brand = $this->getRandomBrand();

        $vehicleId = $this->createItem([
            'item_type_id' => 1,
            'name'         => $name
        ]);

        $this->addItemParent($vehicleId, $brand['id'], [
            'catname' => $catname
        ]);

        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=' . $token));
        $this->dispatch('https://www.autowp.ru/' . $brand['catname'] . '/' . $catname, Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('brand-item');

        $this->assertXpathQuery("//h3[contains(text(), '$name')]");
    }

    /**
     * @dataProvider userTokenProvider
     */
    public function testBrandItemSubitem(string $token)
    {
        $catname = 'brand-item-' . microtime(true);
        $name = 'Vehicle';
        $subName = 'Sub vehicle';
        $subCatname = 'sub';

        $brand = $this->getRandomBrand();

        $vehicleId = $this->createItem([
            'item_type_id' => 1,
            'name'         => $name,
            'is_group'     => true
        ]);

        $this->addItemParent($vehicleId, $brand['id'], [
            'catname' => $catname
        ]);

        $subVehicleId = $this->createItem([
            'item_type_id' => 1,
            'name'         => $subName
        ]);

        $this->addItemParent($subVehicleId, $vehicleId, [
            'catname' => $subCatname
        ]);

        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=' . $token));
        $this->dispatch('https://www.autowp.ru/' . $brand['catname'] . '/' . $catname . '/' . $subCatname, Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('brand-item');

        $this->assertXpathQuery("//h3[contains(text(), '$subName')]");
    }

    /**
     * @dataProvider userTokenProvider
     */
    public function testBrandItemGroup(string $token)
    {
        $catname = 'brand-item-' . microtime(true);
        $name = 'Vehicle';
        $subName = 'Sub vehicle';
        $subCatname = 'sub';

        $brand = $this->getRandomBrand();

        $vehicleId = $this->createItem([
            'item_type_id' => 1,
            'name'         => $name,
            'is_group'     => true
        ]);

        $this->addItemParent($vehicleId, $brand['id'], [
            'catname' => $catname
        ]);

        $subVehicleId = $this->createItem([
            'item_type_id' => 1,
            'name'         => $subName
        ]);

        $this->addItemParent($subVehicleId, $vehicleId, [
            'catname' => $subCatname
        ]);

        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=' . $token));
        $this->dispatch('https://www.autowp.ru/' . $brand['catname'] . '/' . $catname, Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('brand-item');

        $this->assertXpathQuery("//h1[contains(text(), '$name')]");
        $this->assertXpathQuery("//h3[contains(text(), '$subName')]");
    }

    /**
     * @dataProvider userTokenProvider
     */
    public function testBrandItemPictures(string $token)
    {
        $catname = 'brand-item-' . microtime(true);
        $name = 'Vehicle';

        $brand = $this->getRandomBrand();

        $vehicleId = $this->createItem([
            'item_type_id' => 1,
            'name'         => $name
        ]);

        $this->addItemParent($vehicleId, $brand['id'], [
            'catname' => $catname
        ]);

        $pictureId = $this->addPictureToItem($vehicleId);
        $this->acceptPicture($pictureId);

        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=' . $token));
        $this->dispatch('https://www.autowp.ru/' . $brand['catname'] . '/' . $catname . '/pictures', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('brand-item-pictures');

        $this->assertXpathQuery("//h1[contains(text(), '$name')]");
    }

    /**
     * @dataProvider userTokenProvider
     */
    public function testBrandItemPicturesPicture(string $token)
    {
        $catname = 'brand-item-' . microtime(true);
        $name = 'Vehicle';

        $brand = $this->getRandomBrand();

        $vehicleId = $this->createItem([
            'item_type_id' => 1,
            'name'         => $name
        ]);

        $this->addItemParent($vehicleId, $brand['id'], [
            'catname' => $catname
        ]);

        $pictureId = $this->addPictureToItem($vehicleId);
        $this->acceptPicture($pictureId);

        $picture = $this->getPicture($pictureId);

        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=' . $token));
        $this->dispatch('https://www.autowp.ru/' . $brand['catname'] . '/' . $catname . '/pictures/' . $picture['identity'], Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('brand-item-picture');

        $this->assertXpathQuery("//h1[contains(text(), '$name')]");
    }

    /**
     * @dataProvider userTokenProvider
     */
    public function testBrandItemGallery(string $token)
    {
        $catname = 'brand-item-' . microtime(true);
        $name = 'Vehicle';

        $brand = $this->getRandomBrand();

        $vehicleId = $this->createItem([
            'item_type_id' => 1,
            'name'         => $name
        ]);

        $this->addItemParent($vehicleId, $brand['id'], [
            'catname' => $catname
        ]);

        $pictureId = $this->addPictureToItem($vehicleId);
        $this->acceptPicture($pictureId);

        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=' . $token));
        $this->dispatch('https://www.autowp.ru/' . $brand['catname'] . '/' . $catname . '/pictures/gallery', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('brand-item-gallery');

        $this->assertResponseHeaderContains('Content-Type', 'application/json; charset=utf-8');

        $json = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);
        $this->assertNotEmpty($json['items']);
    }

    public function userTokenProvider()
    {
        return [
            [''],
            ['token'],
            ['admin-token'],
        ];
    }
}
