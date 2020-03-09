<?php

namespace ApplicationTest\Controller;

use Application\Model\Item;
use Application\Model\Picture;
use Application\Service\PictureService;
use Application\Test\AbstractHttpControllerTestCase;
use Laminas\Db\Sql;

class PictureServiceTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../config/application.config.php';

    /**
     * @suppress PhanDeprecatedFunction
     */
    private function getRandomVehicleId(): int
    {
        $services  = $this->getApplicationServiceLocator();
        $itemModel = $services->get(Item::class);

        $item = $itemModel->getRow([
            'item_type_id' => Item::VEHICLE,
            'order'        => new Sql\Expression('RAND()'),
        ]);

        $this->assertNotEmpty($item);

        return $item['id'];
    }

    /**
     * @suppress PhanDeprecatedFunction
     */
    public function testClearQueue()
    {
        $services = $this->getApplicationServiceLocator();
        $service  = $services->get(PictureService::class);

        $service->clearQueue();

        $vehicleId = $this->getRandomVehicleId();

        $picture = $service->addPictureFromFile(
            __DIR__ . '/_files/test.jpg',
            1,
            '127.0.0.1',
            $vehicleId,
            0,
            0,
            ''
        );

        $service->clearQueue();

        $pictureModel = $services->get(Picture::class);

        $this->assertTrue($pictureModel->isExists(['id' => $picture['id']]));

        $picturesCount = $pictureModel->getCount([]);

        $pictureModel->getTable()->update([
            'status'        => Picture::STATUS_REMOVING,
            'removing_date' => new Sql\Expression(
                'DATE_SUB(NOW(), INTERVAL ? DAY)',
                [PictureService::QUEUE_LIFETIME + 1]
            ),
        ], [
            'id' => $picture['id'],
        ]);

        $service->clearQueue();

        $newPicturesCount = $pictureModel->getCount([]);

        $this->assertEquals($picturesCount, $newPicturesCount + 1);
        $this->assertFalse($pictureModel->isExists(['id' => $picture['id']]));
    }
}
