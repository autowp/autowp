<?php

namespace ApplicationTest\Controller\Api;

use Application\Controller\Api\InboxController;
use Application\Controller\Api\ItemController;
use Application\Controller\Api\PictureController;
use Application\DuplicateFinder;
use Application\Test\AbstractHttpControllerTestCase;
use ApplicationTest\Data;
use Exception;
use Laminas\Http\Request;

use function array_replace;
use function copy;
use function count;
use function explode;
use function sys_get_temp_dir;
use function tempnam;

use const UPLOAD_ERR_OK;

class InboxControllerTest extends AbstractHttpControllerTestCase
{
    protected string $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

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
     * @suppress PhanUndeclaredMethod
     * @throws Exception
     */
    private function createItem(array $params): int
    {
        $this->reset();

        $this->getRequest()->getHeaders()->addHeader(Data::getAdminAuthHeader());
        $this->dispatch('https://www.autowp.ru/api/item', Request::METHOD_POST, $params);

        $this->assertResponseStatusCode(201);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemController::class);
        $this->assertMatchedRouteName('api/item/post');
        $this->assertActionName('post');

        $headers = $this->getResponse()->getHeaders();
        $uri     = $headers->get('Location')->uri();
        $parts   = explode('/', $uri->getPath());
        return (int) $parts[count($parts) - 1];
    }

    private function createVehicle(array $params = []): int
    {
        return $this->createItem(array_replace([
            'item_type_id' => 1,
            'name'         => 'Some vehicle',
        ], $params));
    }

    /**
     * @suppress PhanUndeclaredMethod
     * @throws Exception
     */
    private function addPictureToItem(int $vehicleId): int
    {
        $this->reset();

        $this->mockDuplicateFinder();

        $request = $this->getRequest();
        $request->getHeaders()
            ->addHeader(Data::getAdminAuthHeader())
            ->addHeaderLine('Content-Type', 'multipart/form-data');
        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $request->getServer()->set('REMOTE_ADDR', '127.0.0.1');

        $file     = tempnam(sys_get_temp_dir(), 'upl');
        $filename = 'test.jpg';
        copy(__DIR__ . '/../../_files/' . $filename, $file);

        /* @phan-suppress-next-line PhanUndeclaredMethod */
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

        $headers = $this->getResponse()->getHeaders();
        $uri     = $headers->get('Location')->uri();
        $parts   = explode('/', $uri->getPath());
        return (int) $parts[count($parts) - 1];
    }

    /**
     * @throws Exception
     */
    public function testIndex(): void
    {
        $vehicleId = $this->createVehicle();
        $this->addPictureToItem($vehicleId);

        $this->reset();
        $this->dispatch('https://www.autowp.ru/api/inbox', 'GET');

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(InboxController::class);
        $this->assertMatchedRouteName('api/inbox/get');
        $this->assertActionName('index');
    }
}
