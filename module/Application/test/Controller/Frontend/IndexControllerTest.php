<?php

namespace ApplicationTest\Frontend\Controller;

use Application\Controller\IndexController;

use Application\Test\AbstractHttpControllerTestCase;

class IndexControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    public function testIndexActionCanBeAccessed()
    {
        $this->dispatch('https://www.autowp.ru/', 'GET');

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(IndexController::class); // as specified in router's controller name alias
        $this->assertControllerClass('IndexController');
        $this->assertMatchedRouteName('index');
    }

    /*public function testIndexActionViewModelTemplateRenderedWithinLayout()
    {
        $this->dispatch('/', 'GET');
        $this->assertQuery('.container .jumbotron');
    }

    public function testInvalidRouteDoesNotCrash()
    {
        $this->dispatch('/invalid/route', 'GET');
        $this->assertResponseStatusCode(404);
    }*/
}
