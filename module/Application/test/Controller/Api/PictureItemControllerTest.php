<?php

namespace ApplicationTest\Controller\Api;

use Application\Controller\Api\PictureItemController;
use Application\Test\AbstractHttpControllerTestCase;
use ApplicationTest\Data;
use Laminas\Http\Request;
use Laminas\Json\Json;

class PictureItemControllerTest extends AbstractHttpControllerTestCase
{
    protected string $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    /**
     * @suppress PhanUndeclaredMethod
     */
    public function testGetList(): void
    {
        $this->getRequest()->getHeaders()->addHeader(Data::getAdminAuthHeader());
        $this->dispatch('https://www.autowp.ru/api/picture-item', Request::METHOD_GET, [
            'fields' => 'item,picture,area',
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(PictureItemController::class);
        $this->assertMatchedRouteName('api/picture-item/get');
        $this->assertActionName('index');

        $json = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);

        $this->assertNotEmpty($json['items']);

        foreach ($json['items'] as $item) {
            $this->assertArrayHasKey('item_id', $item);
            $this->assertArrayHasKey('picture_id', $item);
            $this->assertArrayHasKey('type', $item);
            $this->assertArrayHasKey('area', $item);
            $this->assertArrayHasKey('item', $item);
            $this->assertArrayHasKey('picture', $item);
        }
    }
}
