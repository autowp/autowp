<?php

namespace ApplicationTest\Controller\Api;

use Zend\Http\Header\Cookie;
use Zend\Http\Request;
use Zend\Json\Json;

use Application\Controller\Api\SpecController;
use Application\Test\AbstractHttpControllerTestCase;

class SpecControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    /**
     * @suppress PhanUndeclaredMethod
     */
    public function testDelete()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
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
