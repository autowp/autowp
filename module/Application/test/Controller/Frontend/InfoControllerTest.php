<?php

namespace ApplicationTest\Frontend\Controller;

use Application\Controller\InfoController;
use Application\Test\AbstractHttpControllerTestCase;

class InfoControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    public function testIndexActionCanBeAccessed()
    {
        $this->dispatch('http://www.autowp.ru/info/spec', 'GET');

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(InfoController::class); // as specified in router's controller name alias
        $this->assertControllerClass('InfoController');
        $this->assertMatchedRouteName('info/spec');
    }
}
