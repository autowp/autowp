<?php

namespace ApplicationTest\Model;

use Application\Controller\Api\ItemController;
use Application\Controller\Api\PictureController;
use Application\DuplicateFinder;
use Application\Model\CarOfDay;
use Application\Test\AbstractHttpControllerTestCase;
use ApplicationTest\Data;
use Exception;
use Laminas\Http\Header\Location;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Http\Response;

use function copy;
use function count;
use function explode;
use function sys_get_temp_dir;
use function tempnam;

use const UPLOAD_ERR_OK;

class CarOfDayTest extends AbstractHttpControllerTestCase
{
    protected string $applicationConfigPath = __DIR__ . '/../../../../config/application.config.php';

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
        return (int) $parts[count($parts) - 1];
    }

    /**
     * @throws Exception
     */
    private function addPictureToItem(int $vehicleId): int
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
            'item_id' => $vehicleId,
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertModuleName('application');
        $this->assertControllerName(PictureController::class);
        $this->assertMatchedRouteName('api/picture/post');
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
    private function acceptPicture(int $pictureId): void
    {
        $this->reset();

        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
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
     * @throws Exception
     */
    private function createCandidate(): void
    {
        $vehicleId = $this->createItem([
            'item_type_id' => 1,
            'name'         => 'Some vehicle',
            'begin_year'   => 2001,
            'end_year'     => 2005,
        ]);

        for ($i = 0; $i < 5; $i++) {
            $pictureId = $this->addPictureToItem($vehicleId);
            $this->acceptPicture($pictureId);
        }
    }

    /**
     * @throws Exception
     */
    public function testCarOfDay(): void
    {
        $this->createCandidate();

        $serviceManager = $this->getApplicationServiceLocator();

        $model  = $serviceManager->get(CarOfDay::class);
        $result = $model->getCarOfDayCandidate();

        $this->assertNotEmpty($result);
    }
}
