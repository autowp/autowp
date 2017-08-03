<?php

namespace ApplicationTest\Frontend\Controller;

use Application\Controller\UsersController;

use Application\Test\AbstractHttpControllerTestCase;
use Zend\Http\Request;

class UsersControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    private function getUser()
    {
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch(
            'https://www.autowp.ru/api/user/' . $userRow['id'],
            Request::METHOD_PUT,
            [
                'deleted' => 1
            ]
        );
    }

    public function testIndexActionCanBeAccessed()
    {
        $this->dispatch('https://www.autowp.ru/users/user1/comments', 'GET');

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(UsersController::class);
        $this->assertMatchedRouteName('users/user/comments');
    }

    public function testSpecsRating()
    {
        $this->dispatch('https://www.autowp.ru/users/rating', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(UsersController::class);
        $this->assertMatchedRouteName('users/rating');
    }

    public function testPicturesRating()
    {
        $this->dispatch('https://www.autowp.ru/users/rating/pictures', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(UsersController::class);
        $this->assertMatchedRouteName('users/rating/pictures');
    }

    public function testLikesRating()
    {
        $this->dispatch('https://www.autowp.ru/users/rating/likes', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(UsersController::class);
        $this->assertMatchedRouteName('users/rating/likes');
    }

    public function testPicturesLikesRating()
    {
        $this->dispatch('https://www.autowp.ru/users/rating/picture-likes', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(UsersController::class);
        $this->assertMatchedRouteName('users/rating/picture-likes');
    }

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
