<?php

namespace ApplicationTest\Model;

use Application\Controller\Api\ItemController;
use Application\Controller\Api\PictureController;
use Application\Controller\Api\PictureItemController;
use Application\DuplicateFinder;
use Application\Model\Picture;
use Application\Test\AbstractHttpControllerTestCase;
use ApplicationTest\Data;
use Exception;
use Laminas\Http\Header\Location;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Http\Response;

use function Autowp\Commons\currentFromResultSetInterface;
use function copy;
use function count;
use function explode;
use function sys_get_temp_dir;
use function tempnam;

use const UPLOAD_ERR_OK;

class PictureTest extends AbstractHttpControllerTestCase
{
    protected string $applicationConfigPath = __DIR__ . '/../../../../config/application.config.php';

    public function testPattern(): void
    {
        $serviceManager = $this->getApplicationServiceLocator();

        $table = $serviceManager->get(Picture::class);

        $row = $table->getRow([]);

        $this->assertNotEmpty($row);

        $pattern = $table->getFileNamePattern($row['id']);

        $this->assertNotEmpty($pattern);
    }

    private function mockDuplicateFinder(): void
    {
        $serviceManager = $this->getApplicationServiceLocator();

        $tables = $serviceManager->get('TableManager');

        $mock = $this->getMockBuilder(DuplicateFinder::class)
            ->onlyMethods(['indexImage'])
            ->setConstructorArgs([
                $serviceManager->get('RabbitMQ'),
                $tables->get('df_distance'),
            ])
            ->getMock();

        $mock->method('indexImage');

        $serviceManager->setService(DuplicateFinder::class, $mock);
    }

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
        $itemId   = $parts[count($parts) - 1];

        return (int) $itemId;
    }

    /**
     * @throws Exception
     */
    private function addPictureToItem(int $itemId): int
    {
        $this->reset();

        $this->mockDuplicateFinder();

        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()
            ->addHeader(Data::getAdminAuthHeader(
                $this->getApplicationServiceLocator()->get('Config')['keycloak']
            ))
            ->addHeaderLine('Content-Type', 'multipart/form-data');
        $request->getServer()->set('REMOTE_ADDR', '127.0.0.1');

        $file     = tempnam(sys_get_temp_dir(), 'upl');
        $filename = 'test.jpg';
        copy(__DIR__ . '/../_files/' . $filename, $file);

        $request->getFiles()->fromArray([
            'file' => [
                'tmp_name' => $file,
                'name'     => $filename,
                'error'    => UPLOAD_ERR_OK,
                'type'     => 'image/jpeg',
            ],
        ]);

        $this->dispatch('https://www.autowp.ru/api/picture', Request::METHOD_POST, [
            'item_id' => $itemId,
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertModuleName('application');
        $this->assertControllerName(PictureController::class);
        $this->assertMatchedRouteName('api/picture/post');
        $this->assertActionName('post');

        /** @var Response $response */
        $response = $this->getResponse();
        /** @var Location $location */
        $location  = $response->getHeaders()->get('Location');
        $uri       = $location->uri();
        $parts     = explode('/', $uri->getPath());
        $pictureId = $parts[count($parts) - 1];

        return (int) $pictureId;
    }

    /**
     * @throws Exception
     */
    private function addPictureItem(int $pictureId, int $itemId, int $typeId): void
    {
        $this->reset();

        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
        $this->dispatch(
            'https://www.autowp.ru/api/picture-item/' . $pictureId . '/' . $itemId . '/' . $typeId,
            Request::METHOD_POST
        );

        $this->assertResponseStatusCode(201);
        $this->assertModuleName('application');
        $this->assertControllerName(PictureItemController::class);
        $this->assertMatchedRouteName('api/picture-item/item/create');
        $this->assertActionName('create');
    }

    private function getPictureFilename(int $pictureId): string
    {
        $serviceManager = $this->getApplicationServiceLocator();
        $tableManager   = $serviceManager->get('TableManager');

        $picture = currentFromResultSetInterface($tableManager->get('pictures')->select([
            'id' => $pictureId,
        ]));

        if (! $picture) {
            return '';
        }

        $image = currentFromResultSetInterface($tableManager->get('image')->select([
            'id' => $picture['image_id'],
        ]));

        if (! $image) {
            return '';
        }

        return $image['filepath'];
    }

    /**
     * @throws Exception
     */
    public function testPersonPictureFilenamePattern(): void
    {
        $personId  = $this->createItem([
            'item_type_id' => 8,
            'name'         => 'A.S. Pushkin',
        ]);
        $pictureId = $this->addPictureToItem($personId);

        $filename = $this->getPictureFilename($pictureId);

        $this->assertMatchesRegularExpression('/^a\/a\.s\._pushkin\/a\.s\._pushkin(_[0-9]+)?\.jpeg$/', $filename);
    }

    public function testPersonAndCopyrightPictureFilenamePattern(): void
    {
        $personId  = $this->createItem([
            'item_type_id' => 8,
            'name'         => 'A.S. Pushkin',
        ]);
        $pictureId = $this->addPictureToItem($personId);

        $copyrightId = $this->createItem([
            'item_type_id' => 9,
            'name'         => 'Copyrights',
        ]);
        $this->addPictureItem($pictureId, $copyrightId, 3);

        $filename = $this->getPictureFilename($pictureId);

        $this->assertMatchesRegularExpression('/^a\/a\.s\._pushkin\/a\.s\._pushkin(_[0-9]+)?\.jpeg$/', $filename);
    }

    public function testAuthorAndVehiclePictureFilenamePattern(): void
    {
        $vehicleId = $this->createItem([
            'item_type_id' => 1,
            'name'         => 'Toyota Corolla',
        ]);
        $pictureId = $this->addPictureToItem($vehicleId);

        $personId = $this->createItem([
            'item_type_id' => 8,
            'name'         => 'A.S. Pushkin',
        ]);
        $this->addPictureItem($pictureId, $personId, 2);

        $filename = $this->getPictureFilename($pictureId);

        $this->assertMatchesRegularExpression('/^t\/toyota_corolla\/toyota_corolla(_[0-9]+)?\.jpeg$/', $filename);
    }

    public function testPersonAndVehiclePictureFilenamePattern(): void
    {
        $vehicleId = $this->createItem([
            'item_type_id' => 1,
            'name'         => 'Toyota Corolla',
        ]);
        $pictureId = $this->addPictureToItem($vehicleId);

        $personId = $this->createItem([
            'item_type_id' => 8,
            'name'         => 'A.S. Pushkin',
        ]);

        $this->addPictureItem($pictureId, $personId, 1);

        $filename = $this->getPictureFilename($pictureId);

        $this->assertMatchesRegularExpression(
            '/^t\/toyota_corolla\/a\.s\._pushkin\/toyota_corolla_a\.s\._pushkin(_[0-9]+)?\.jpeg$/',
            $filename
        );
    }
}
