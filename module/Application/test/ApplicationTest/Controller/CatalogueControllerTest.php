<?php

namespace ApplicationTest\Controller;

use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;

use Application\Controller\CatalogueController;

class CatalogueControllerTest extends AbstractHttpControllerTestCase
{
    public function setUp()
    {
        $this->setApplicationConfig(include __DIR__ . '/../_files/application.config.php');

        parent::setUp();
    }

    public function testBrand()
    {
        $this->dispatch('https://www.autowp.ru/bmw', 'GET');

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('brand');

        $this->assertXpathQuery("//h1[contains(text(), 'BMW')]");
    }

    /*public function testCars()
    {
        $this->dispatch('https://www.autowp.ru/bmw/cars', 'GET');

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('cars');

        $this->assertXpathQuery("//h1[contains(text(), 'BMW')]");
    }*/
}
