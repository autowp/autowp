<?php

namespace ApplicationTest\Controller\Frontend;

use Zend\Http\Header\Cookie;
use Zend\Http\Request;
use Zend\Json\Json;

use Application\Controller\AccountController;
use Application\Test\AbstractHttpControllerTestCase;

class AccountControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../_files/application.config.php';

    public function testSendMessage()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/account/send-personal-message', Request::METHOD_POST, [
            'user_id' => 1,
            'message' => 'Test personal message'
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(AccountController::class);
        $this->assertMatchedRouteName('account/send-personal-message');
        $this->assertActionName('send-personal-message');

        $json = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);

        $this->assertTrue($json['ok']);
    }

    public function testGetInboxMessages()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/account/pm', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(AccountController::class);
        $this->assertMatchedRouteName('account/personal-messages');
        $this->assertActionName('personal-messages-inbox');
    }

    public function testGetSentMessages()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/account/pm/sent', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(AccountController::class);
        $this->assertMatchedRouteName('account/personal-messages/sent');
        $this->assertActionName('personal-messages-sent');
    }

    public function testGetSystemMessages()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/account/pm/system', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(AccountController::class);
        $this->assertMatchedRouteName('account/personal-messages/system');
        $this->assertActionName('personal-messages-system');
    }

    public function testGetUserMessages()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/account/pm/user1', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(AccountController::class);
        $this->assertMatchedRouteName('account/personal-messages/user');
        $this->assertActionName('personal-messages-user');
    }
}
