<?php

namespace ApplicationTest\Controller\Frontend;

use Application\Controller\Api\LoginController;
use Application\Test\AbstractHttpControllerTestCase;
use Autowp\ExternalLoginService\Facebook;
use Autowp\ExternalLoginService\PluginManager;
use Autowp\ExternalLoginService\Result;
use DateTime;
use Laminas\Http\Request;
use Laminas\Json\Json;
use Laminas\Uri\UriFactory;

class LoginControllerTest extends AbstractHttpControllerTestCase
{
    protected string $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    public function testLoginByEmail(): void
    {
        $this->dispatch('https://www.autowp.ru/api/login', Request::METHOD_POST, [
            'login'    => 'test@example.com',
            'password' => '123456',
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertModuleName('application');
        $this->assertControllerName(LoginController::class);
        $this->assertMatchedRouteName('api/login/login');
        $this->assertActionName('login');
    }

    public function testLoginByLogin(): void
    {
        $this->dispatch('https://www.autowp.ru/api/login', Request::METHOD_POST, [
            'login'    => 'test',
            'password' => '123456',
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertModuleName('application');
        $this->assertControllerName(LoginController::class);
        $this->assertMatchedRouteName('api/login/login');
        $this->assertActionName('login');
    }

    private function mockExternalLoginFactory(?string $photoUrl): void
    {
        $serviceManager = $this->getApplicationServiceLocator();

        $config = $serviceManager->get('config');

        $serviceMock = $this->getMockBuilder(Facebook::class)
            ->onlyMethods(['getData', 'callback'])
            ->setConstructorArgs([
                $config['external_login_services'][Facebook::class],
            ])
            ->getMock();

        $serviceMock->method('getData')->willReturnCallback(function () use ($photoUrl) {
            return new Result([
                'externalId' => 'test-external-id',
                'name'       => 'test-name',
                'profileUrl' => 'http://example.com/',
                'photoUrl'   => $photoUrl, //'http://example.com/photo.jpg',
                'birthday'   => new DateTime(),
                'email'      => 'test@example.com',
                'gender'     => 1,
                'location'   => 'London',
                'language'   => 'en',
            ]);
        });

        $serviceMock->method('callback')->willReturnCallback(function () {
            return true;
        });

        $mock = $this->getMockBuilder(PluginManager::class)
            ->onlyMethods(['get'])
            ->setConstructorArgs([$serviceManager])
            ->getMock();

        $mock->method('get')->willReturn($serviceMock);

        $serviceManager->setService('ExternalLoginServiceManager', $mock);
    }

    public function testAuthorizeByService(): void
    {
        $this->mockExternalLoginFactory(null);

        $this->dispatch('https://www.autowp.ru/api/login/start', Request::METHOD_GET, [
            'type' => 'facebook',
        ]);

        $this->assertModuleName('application');
        $this->assertControllerName(LoginController::class);
        $this->assertActionName('start');
        $this->assertMatchedRouteName('api/login/start');
        $this->assertResponseStatusCode(200);

        $json = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);

        $this->assertArrayHasKey('url', $json);

        $uri = UriFactory::factory($json['url']);

        $this->assertRegExp(
            '|^https://www\.facebook\.com/v[0-9.]+/dialog/oauth'
                . '\?scope=public_profile&state=[0-9a-z]+'
                . '&response_type=code&approval_prompt=auto'
                . '&redirect_uri=https%3A%2F%2Fen\.localhost%2Flogin%2Fcallback'
                . '&client_id=facebook_test_clientid$|iu',
            $uri->toString()
        );

        $query = $uri->getQueryAsArray();
        $state = $query['state'];

        $this->assertNotEmpty($state);

        /*$this->assertEquals('Bearer', $params['token_type']);

        $token = $params['access_token'];

        $this->assertNotEmpty($token);*/

        // check token valid
        $this->reset();

        $this->mockExternalLoginFactory(null);

        $this->dispatch('https://www.autowp.ru/login/callback', Request::METHOD_GET, [
            'state' => $state,
            'code'  => 'zzzz',
        ]);

        $this->assertResponseStatusCode(302);
    }
}
