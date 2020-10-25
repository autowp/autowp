<?php

namespace ApplicationTest\Model;

use Application\Model\Item;
use Application\Test\AbstractHttpControllerTestCase;
use Exception;
use stdClass;

class ItemTest extends AbstractHttpControllerTestCase
{
    protected string $applicationConfigPath = __DIR__ . '/../../../../config/application.config.php';

    public function testInvalidRequestsHandled(): void
    {
        $serviceManager = $this->getApplicationServiceLocator();

        $model = $serviceManager->get(Item::class);

        $result = $model->getRows(['id' => null]);
        $this->assertNotEmpty($result);

        $result = $model->getRow(['id' => []]);
        $this->assertEmpty($result);

        $result = $model->getRow(['id' => false]);
        $this->assertEmpty($result);

        $result = $model->getRow(['id' => '']);
        $this->assertEmpty($result);

        $result = $model->getRow(['id' => 'asd']);
        $this->assertEmpty($result);

        $result = $model->getRow(['id' => 0]);
        $this->assertEmpty($result);

        try {
            $result = $model->getRow(['id' => new stdClass()]);
            $this->assertEmpty($result);
            $this->assertTrue(false);
        } catch (Exception $e) {
            $this->assertTrue(true);
        }

        $result = $model->getRow([
            'id' => [
                'test' => '',
            ],
        ]);
        $this->assertEmpty($result);

        try {
            $result = $model->getRow([
                'id' => [
                    'test' => null,
                ],
            ]);
            $this->assertEmpty($result);
        } catch (Exception $e) {
            $this->assertTrue(true);
        }

        $result = $model->getRow([
            'id' => [
                null,
                false,
            ],
        ]);
        $this->assertEmpty($result);
    }
}
