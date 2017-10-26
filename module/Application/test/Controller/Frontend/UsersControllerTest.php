<?php

namespace ApplicationTest\Frontend\Controller;

use Application\Controller\UsersController;

use Application\Test\AbstractHttpControllerTestCase;
use Zend\Http\Request;

class UsersControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    public function testPictures()
    {
        $this->dispatch('https://www.autowp.ru/users/admin/pictures', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(UsersController::class);
        $this->assertMatchedRouteName('users/user/pictures');
    }

    public function testBrandPictures()
    {
        $this->dispatch('https://www.autowp.ru/users/admin/pictures/bmw', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(UsersController::class);
        $this->assertMatchedRouteName('users/user/pictures/brand');
    }
}
