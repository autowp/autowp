<?php

namespace ApplicationTest\Controller\Api;

use Application\Controller\Api\BrandsController;
use Application\Test\AbstractHttpControllerTestCase;
use ApplicationTest\Data;
use JsonException;
use Laminas\Http\Header\Location;
use Laminas\Http\Request;
use Laminas\Http\Response;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

use function count;
use function explode;

class BrandsControllerTest extends AbstractHttpControllerTestCase
{
    protected string $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    public function testBrandsIndex(): void
    {
        $this->dispatch('https://www.autowp.ru/api/brands', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(BrandsController::class);
        $this->assertMatchedRouteName('api/brands/get');
        $this->assertActionName('index');
    }
}
