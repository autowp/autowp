<?php

namespace ApplicationTest\Controller\Frontend;

use Zend\Http\Request;
use Application\Test\AbstractHttpControllerTestCase;

use Application\Controller\ForumsController;
use Zend\Http\Header\Cookie;
use Zend\Json\Json;

class ForumsControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../_files/application.config.php';

    public function testIndex()
    {
        $this->dispatch('https://www.autowp.ru/forums', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(ForumsController::class);
        $this->assertMatchedRouteName('forums');
        $this->assertActionName('index');
    }

    public function testTopic()
    {
        $this->dispatch('https://www.autowp.ru/forums/topic/1', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(ForumsController::class);
        $this->assertMatchedRouteName('forums/topic');
        $this->assertActionName('topic');
    }

    public function testNewIsForbidden()
    {
        $this->dispatch('https://www.autowp.ru/forums/new/theme_id/1', Request::METHOD_GET);

        $this->assertResponseStatusCode(403);
    }

    public function testNew()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/forums/new/theme_id/1', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(ForumsController::class);
        $this->assertMatchedRouteName('forums/new');
        $this->assertActionName('new');
    }

    public function testCreateTopic()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/forums/new/theme_id/1', Request::METHOD_POST, [
            'name'                => 'Test topic',
            'text'                => 'Test topic text',
            'moderator_attention' => 0,
            'subscribe'           => true
        ]);

        $this->assertResponseStatusCode(302);
        $this->assertModuleName('application');
        $this->assertControllerName(ForumsController::class);
        $this->assertMatchedRouteName('forums/new');
        $this->assertActionName('new');

        $headers = $this->getResponse()->getHeaders();
        $uri = $headers->get('Location')->uri();
        $parts = explode('/', $uri->getPath());
        $topicId = $parts[count($parts) - 1];

        $this->assertNotEmpty($topicId);

        // unsubscribe
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/forums/unsubscribe/topic_id/' . $topicId, Request::METHOD_POST);

        $this->assertResponseStatusCode(302);
        $this->assertModuleName('application');
        $this->assertControllerName(ForumsController::class);
        $this->assertMatchedRouteName('forums/unsubscribe');
        $this->assertActionName('unsubscribe');

        // subscribe
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/forums/subscribe/topic_id/' . $topicId, Request::METHOD_POST);

        $this->assertResponseStatusCode(302);
        $this->assertModuleName('application');
        $this->assertControllerName(ForumsController::class);
        $this->assertMatchedRouteName('forums/subscribe');
        $this->assertActionName('subscribe');

        // close
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/forums/close', Request::METHOD_POST, [
            'topic_id' => $topicId
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(ForumsController::class);
        $this->assertMatchedRouteName('forums/close');
        $this->assertActionName('close');

        $json = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);
        $this->assertTrue($json['ok']);

        // open
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/forums/open', Request::METHOD_POST, [
            'topic_id' => $topicId
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(ForumsController::class);
        $this->assertMatchedRouteName('forums/open');
        $this->assertActionName('open');

        $json = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);
        $this->assertTrue($json['ok']);

        // subscribes
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/forums/subscribes', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(ForumsController::class);
        $this->assertMatchedRouteName('forums/subscribes');
        $this->assertActionName('subscribes');

        // post message
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/forums/topic/' . $topicId, Request::METHOD_POST, [
            'message'             => 'Test message',
            'moderator_attention' => 0,
            'parent_id'           => null
        ]);

        $this->assertResponseStatusCode(302);
        $this->assertModuleName('application');
        $this->assertControllerName(ForumsController::class);
        $this->assertMatchedRouteName('forums/topic');
        $this->assertActionName('topic');

        // delete topic
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/forums/delete', Request::METHOD_POST, [
            'topic_id' => $topicId
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(ForumsController::class);
        $this->assertMatchedRouteName('forums/delete');
        $this->assertActionName('delete');

        $json = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);
        $this->assertTrue($json['ok']);
    }
}
