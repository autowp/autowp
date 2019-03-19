<?php

namespace ApplicationTest\Model;

use Zend\Http\Header\Cookie;
use Zend\Http\Request;

use Application\Controller\Api\ItemController;
use Application\Controller\Api\PictureController;
use Application\Controller\Api\PictureItemController;
use Application\Model\Picture;
use Application\Test\AbstractHttpControllerTestCase;

class PictureTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../config/application.config.php';

    public function testPattern()
    {
        $serviceManager = $this->getApplicationServiceLocator();

        $table = $serviceManager->get(Picture::class);

        $row = $table->getRow([]);

        $pattern = $table->getFileNamePattern($row['id']);

        $this->assertNotEmpty($pattern);
    }

    private function mockDuplicateFinder()
    {
        $serviceManager = $this->getApplicationServiceLocator();

        $tables = $serviceManager->get('TableManager');

        $mock = $this->getMockBuilder(\Application\DuplicateFinder::class)
            ->setMethods(['indexImage'])
            ->setConstructorArgs([
                $serviceManager->get('RabbitMQ'),
                $tables->get('df_distance')
            ])
            ->getMock();

        $mock->method('indexImage')->willReturn(true);

        $serviceManager->setService(\Application\DuplicateFinder::class, $mock);
    }

    /**
     * @suppress PhanUndeclaredMethod
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

        return (int) $itemId;
    }

    /**
     * @suppress PhanUndeclaredMethod
     */
    private function addPictureToItem(int $itemID): int
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
        copy(__DIR__ . '/../_files/' . $filename, $file);

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
            'item_id' => $itemID
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertModuleName('application');
        $this->assertControllerName(PictureController::class);
        $this->assertMatchedRouteName('api/picture/post');
        $this->assertActionName('post');

        $headers = $this->getResponse()->getHeaders();
        $uri = $headers->get('Location')->uri();
        $parts = explode('/', $uri->getPath());
        $pictureID = $parts[count($parts) - 1];

        return (int) $pictureID;
    }

    /**
     * @suppress PhanUndeclaredMethod
     */
    private function addPictureItem(int $pictureID, int $itemID, int $typeID)
    {
        $this->reset();

        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch(
            'https://www.autowp.ru/api/picture-item/' . $pictureID . '/' . $itemID . '/' . $typeID,
            Request::METHOD_POST
        );

        $this->assertResponseStatusCode(201);
        $this->assertModuleName('application');
        $this->assertControllerName(PictureItemController::class);
        $this->assertMatchedRouteName('api/picture-item/item/create');
        $this->assertActionName('create');
    }

    private function getPictureFilename(int $pictureID): string
    {
        $serviceManager = $this->getApplicationServiceLocator();
        $tableManager = $serviceManager->get('TableManager');

        $picture = $tableManager->get('pictures')->select([
            'id' => $pictureID
        ])->current();

        if (! $picture) {
            return '';
        }

        $image = $tableManager->get('image')->select([
            'id' => $picture['image_id']
        ])->current();

        if (! $image) {
            return '';
        }

        return $image['filepath'];
    }

    public function testPersonPictureFilenamePattern()
    {
        $personID = $this->createItem([
            'item_type_id' => 8,
            'name'         => 'A.S. Pushkin'
        ]);
        $pictureID = $this->addPictureToItem($personID);

        $filename = $this->getPictureFilename($pictureID);

        $this->assertRegExp('/^a\/a\.s\._pushkin\/a\.s\._pushkin(_[0-9]+)?\.jpeg$/', $filename);
    }

    public function testPersonAndCopyrightPictureFilenamePattern()
    {
        $personID = $this->createItem([
            'item_type_id' => 8,
            'name'         => 'A.S. Pushkin'
        ]);
        $pictureID = $this->addPictureToItem($personID);

        $copyrightID = $this->createItem([
            'item_type_id' => 9,
            'name'         => 'Copyrights'
        ]);
        $this->addPictureItem($pictureID, $copyrightID, 3);

        $filename = $this->getPictureFilename($pictureID);

        $this->assertRegExp('/^a\/a\.s\._pushkin\/a\.s\._pushkin(_[0-9]+)?\.jpeg$/', $filename);
    }

    public function testAuthorAndVehiclePictureFilenamePattern()
    {
        $vehicleID = $this->createItem([
            'item_type_id' => 1,
            'name'         => 'Toyota Corolla'
        ]);
        $pictureID = $this->addPictureToItem($vehicleID);

        $personID = $this->createItem([
            'item_type_id' => 8,
            'name'         => 'A.S. Pushkin'
        ]);
        $this->addPictureItem($pictureID, $personID, 2);

        $filename = $this->getPictureFilename($pictureID);

        $this->assertRegExp('/^t\/toyota_corolla\/toyota_corolla(_[0-9]+)?\.jpeg$/', $filename);
    }

    public function testPersonAndVehiclePictureFilenamePattern()
    {
        $vehicleID = $this->createItem([
            'item_type_id' => 1,
            'name'         => 'Toyota Corolla'
        ]);
        $pictureID = $this->addPictureToItem($vehicleID);

        $personID = $this->createItem([
            'item_type_id' => 8,
            'name'         => 'A.S. Pushkin'
        ]);

        $this->addPictureItem($pictureID, $personID, 1);

        $filename = $this->getPictureFilename($pictureID);

        $this->assertRegExp('/^t\/toyota_corolla\/a\.s\._pushkin\/toyota_corolla_a\.s\._pushkin(_[0-9]+)?\.jpeg$/', $filename);
    }
}
