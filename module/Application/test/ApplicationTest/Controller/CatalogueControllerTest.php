<?php

namespace ApplicationTest\Controller;

use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;

use Application\Controller\CatalogueController;
use Zend\Json\Json;

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

    public function testCars()
    {
        $this->dispatch('https://www.autowp.ru/bmw/cars', 'GET');

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('cars');

        $this->assertXpathQuery("//h1[contains(text(), 'BMW')]");
    }

    public function testRecent()
    {
        $this->dispatch('https://www.autowp.ru/bmw/recent', 'GET');

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('recent');

        $this->assertXpathQuery("//h1[contains(text(), 'BMW')]");
    }

    public function testConcepts()
    {
        $this->dispatch('https://www.autowp.ru/bmw/concepts', 'GET');

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('concepts');

        $this->assertXpathQuery("//h1[contains(text(), 'BMW')]");
    }

    public function testOther()
    {
        $this->dispatch('https://www.autowp.ru/bmw/other', 'GET');

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('other');

        $this->assertQuery(".thumbnail");
    }

    public function testMixed()
    {
        $this->dispatch('https://www.autowp.ru/bmw/mixed', 'GET');

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('mixed');

        $this->assertQuery(".thumbnail");
    }

    public function testLogotypes()
    {
        $this->dispatch('https://www.autowp.ru/bmw/logotypes', 'GET');

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('logotypes');

        $this->assertQuery(".thumbnail");
    }

    public function testOtherPicture()
    {
        $this->dispatch('https://www.autowp.ru/bmw/other/2/', 'GET');

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('other-picture');

        $this->assertQuery(".thumbnail");
    }

    public function testMixedPicture()
    {
        $this->dispatch('https://www.autowp.ru/bmw/mixed/3/', 'GET');

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('mixed-picture');

        $this->assertQuery(".thumbnail");
    }

    public function testLogotypesPicture()
    {
        $this->dispatch('https://www.autowp.ru/bmw/logotypes/4/', 'GET');

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('logotypes-picture');

        $this->assertQuery(".thumbnail");
    }

    public function testOtherGallery()
    {
        $this->dispatch('https://www.autowp.ru/bmw/other/gallery/', 'GET');

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('other-gallery');
        $this->assertResponseHeaderContains('Content-Type', 'application/json; charset=utf-8');

        $data = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);

        $this->assertArrayHasKey('page', $data);
        $this->assertArrayHasKey('pages', $data);
        $this->assertArrayHasKey('count', $data);
        $this->assertArrayHasKey('items', $data);
    }

    public function testMixedGallery()
    {
        $this->dispatch('https://www.autowp.ru/bmw/mixed/gallery/', 'GET');

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('mixed-gallery');
        $this->assertResponseHeaderContains('Content-Type', 'application/json; charset=utf-8');

        $data = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);

        $this->assertArrayHasKey('page', $data);
        $this->assertArrayHasKey('pages', $data);
        $this->assertArrayHasKey('count', $data);
        $this->assertArrayHasKey('items', $data);
    }

    public function testLogotypesGallery()
    {
        $this->dispatch('https://www.autowp.ru/bmw/logotypes/gallery/', 'GET');

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CatalogueController::class);
        $this->assertMatchedRouteName('catalogue');
        $this->assertActionName('logotypes-gallery');
        $this->assertResponseHeaderContains('Content-Type', 'application/json; charset=utf-8');

        $data = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);

        $this->assertArrayHasKey('page', $data);
        $this->assertArrayHasKey('pages', $data);
        $this->assertArrayHasKey('count', $data);
        $this->assertArrayHasKey('items', $data);
    }
}
