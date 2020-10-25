<?php

namespace ApplicationTest\Controller\Api;

use Application\Controller\Api\RatingController;
use Application\Test\AbstractHttpControllerTestCase;
use Laminas\Http\Request;

class RatingControllerTest extends AbstractHttpControllerTestCase
{
    protected string $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    public function testSpecsRating(): void
    {
        $this->dispatch('https://www.autowp.ru/api/rating/specs', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(RatingController::class);
        $this->assertMatchedRouteName('api/rating/specs');
    }

    public function testPicturesRating(): void
    {
        $this->dispatch('https://www.autowp.ru/api/rating/pictures', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(RatingController::class);
        $this->assertMatchedRouteName('api/rating/pictures');
    }

    public function testLikesRating(): void
    {
        $this->dispatch('https://www.autowp.ru/api/rating/likes', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(RatingController::class);
        $this->assertMatchedRouteName('api/rating/likes');
    }

    public function testPicturesLikesRating(): void
    {
        $this->dispatch('https://www.autowp.ru/api/rating/picture-likes', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(RatingController::class);
        $this->assertMatchedRouteName('api/rating/picture-likes');
    }
}
