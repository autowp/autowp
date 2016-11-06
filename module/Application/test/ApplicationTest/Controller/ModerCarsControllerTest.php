<?php

namespace ApplicationTest\Controller;

use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;

use Application\Controller\Moder\CarsController;
use Zend\Http\Request;
use Zend\Http\Header\Cookie;

class ModerCarsControllerTest extends AbstractHttpControllerTestCase
{
    public function setUp()
    {
        $this->setApplicationConfig(include __DIR__ . '/../_files/application.config.php');

        parent::setUp();
    }

    public function testGroupIsNotForbidden()
    {
        /**
         * @var Request $request
         */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/cars/car/car_id/1', 'GET');

        //$this->assertResponseStatusCode(403);
        $this->assertModuleName('application');
        $this->assertControllerName(CarsController::class);
        $this->assertMatchedRouteName('moder/cars/params');
        $this->assertActionName('car');

        $this->assertXpathQuery("//h1[contains(text(), 'test car')]");
    }
}
