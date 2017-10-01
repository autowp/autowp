<?php

namespace ApplicationTest\Controller\Frontend;

use Zend\Http\Header\Cookie;
use Zend\Http\Request;

use Autowp\Forums\Controller\FrontendController;

use Application\Controller\Api\ForumController;
use Application\Test\AbstractHttpControllerTestCase;

class ForumsControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    public function testTopic()
    {
        $this->dispatch('https://www.autowp.ru/forums/topic/1', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertControllerName(FrontendController::class);
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
        $this->assertControllerName(FrontendController::class);
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
        $this->assertControllerName(FrontendController::class);
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
        $this->assertControllerName(FrontendController::class);
        $this->assertMatchedRouteName('forums/unsubscribe');
        $this->assertActionName('unsubscribe');

        // subscribe
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/forums/subscribe/topic_id/' . $topicId, Request::METHOD_POST);

        $this->assertResponseStatusCode(302);
        $this->assertControllerName(FrontendController::class);
        $this->assertMatchedRouteName('forums/subscribe');
        $this->assertActionName('subscribe');

        // close
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/api/forum/topic/' . $topicId, Request::METHOD_PUT, [
            'status' => 'closed'
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertControllerName(ForumController::class);
        $this->assertMatchedRouteName('api/forum/topic/item/put');
        $this->assertActionName('put-topic');

        // open
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/api/forum/topic/' . $topicId, Request::METHOD_PUT, [
            'status' => 'normal'
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertControllerName(ForumController::class);
        $this->assertMatchedRouteName('api/forum/topic/item/put');
        $this->assertActionName('put-topic');

        // subscribes
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/forums/subscribes', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertControllerName(FrontendController::class);
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
        $this->assertControllerName(FrontendController::class);
        $this->assertMatchedRouteName('forums/topic');
        $this->assertActionName('topic');

        // delete topic
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/api/forum/topic/' . $topicId, Request::METHOD_PUT, [
            'status' => 'deleted'
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertControllerName(ForumController::class);
        $this->assertMatchedRouteName('api/forum/topic/item/put');
        $this->assertActionName('put-topic');
    }
}
