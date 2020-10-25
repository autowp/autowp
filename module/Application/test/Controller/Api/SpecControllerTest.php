<?php

namespace ApplicationTest\Controller\Api;

use Application\Controller\Api\SpecController;
use Application\Test\AbstractHttpControllerTestCase;
use ApplicationTest\Data;
use Laminas\Http\Request;
use Laminas\Json\Json;

class SpecControllerTest extends AbstractHttpControllerTestCase
{
    protected string $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    /**
     * @suppress PhanUndeclaredMethod
     */
    public function testDelete(): void
    {
        $this->getRequest()->getHeaders()->addHeader(Data::getAdminAuthHeader());
        $this->dispatch('https://www.autowp.ru/api/spec', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(SpecController::class);
        $this->assertMatchedRouteName('api/spec/get');
        $this->assertActionName('index');

        $this->assertResponseHeaderContains('Content-Type', 'application/json; charset=utf-8');

        Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);
    }
}
