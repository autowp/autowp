<?php

namespace ApplicationTest\Controller\Frontend;

use Zend\Http\Request;
use Application\Test\AbstractHttpControllerTestCase;

use Application\Controller\Api\LoginController;

class LoginControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    public function testLoginByEmail()
    {
        $this->dispatch('https://www.autowp.ru/api/login', Request::METHOD_POST, [
            'login'    => 'test@example.com',
            'password' => '123456'
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertModuleName('application');
        $this->assertControllerName(LoginController::class);
        $this->assertMatchedRouteName('api/login/login');
        $this->assertActionName('login');
    }

    public function testLoginByLogin()
    {
        $this->dispatch('https://www.autowp.ru/api/login', Request::METHOD_POST, [
            'login'    => 'test',
            'password' => '123456'
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertModuleName('application');
        $this->assertControllerName(LoginController::class);
        $this->assertMatchedRouteName('api/login/login');
        $this->assertActionName('login');
    }

    private function mockExternalLoginFactory($photoUrl)
    {
        $serviceManager = $this->getApplicationServiceLocator();

        $config = $serviceManager->get('config');

        $serviceMock = $this->getMockBuilder(\Autowp\ExternalLoginService\Facebook::class)
            ->setMethods(['getData', 'callback'])
            ->setConstructorArgs([
                $config['external_login_services'][\Autowp\ExternalLoginService\Facebook::class]
            ])
            ->getMock();

        $serviceMock->method('getData')->willReturnCallback(function () use ($serviceMock, $photoUrl) {
            return new \Autowp\ExternalLoginService\Result([
                'externalId' => 'test-external-id',
                'name'       => 'test-name',
                'profileUrl' => 'http://example.com/',
                'photoUrl'   => $photoUrl, //'http://example.com/photo.jpg',
                'birthday'   => new \DateTime(),
                'email'      => 'test@example.com',
                'gender'     => 1,
                'location'   => 'London',
                'language'   => 'en'
            ]);
        });

        $serviceMock->method('callback')->willReturnCallback(function () {
            return true;
        });

        $mock = $this->getMockBuilder(\Autowp\ExternalLoginService\PluginManager::class)
            ->setMethods(['get'])
            ->setConstructorArgs([$serviceManager])
            ->getMock();

        $mock->method('get')->willReturn($serviceMock);

        $serviceManager->setService('ExternalLoginServiceManager', $mock);
    }

    public function testAuthorizeBySerevice()
    {
        $this->mockExternalLoginFactory(null);

        $this->dispatch('https://www.autowp.ru/api/login/start', Request::METHOD_GET, [
            'type' => 'facebook'
        ]);

        $this->assertModuleName('application');
        $this->assertControllerName(LoginController::class);
        $this->assertActionName('start');
        $this->assertMatchedRouteName('api/login/start');
        $this->assertResponseStatusCode(302);
        $this->assertHasResponseHeader('Location');

        $headers = $this->getResponse()->getHeaders();
        $uri = $headers->get('Location')->uri();

        $this->assertRegExp(
            '|^https://www\.facebook\.com/v[0-9.]+/dialog/oauth'.
                '\?scope=public_profile%2Cuser_friends&state=[0-9a-z]+' .
                '&response_type=code&approval_prompt=auto' .
                '&redirect_uri=http%3A%2F%2Fen\.localhost%2Flogin%2Fcallback' .
                '&client_id=facebook_test_clientid$|iu',
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
            'code'  => 'zzzz'
        ]);

        $this->assertResponseStatusCode(302);
    }
}
