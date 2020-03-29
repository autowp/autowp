<?php

namespace ApplicationTest\Controller\Frontend;

use Application\Controller\Api\CommentController;
use Application\Controller\Api\ForumController;
use Application\Test\AbstractHttpControllerTestCase;
use Laminas\Http\Header\Cookie;
use Laminas\Http\Request;

use function count;
use function explode;

class ForumsControllerTest extends AbstractHttpControllerTestCase
{
    protected string $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    public function testTopic()
    {
        $this->dispatch('https://www.autowp.ru/api/forum/topic/1', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertControllerName(ForumController::class);
        $this->assertMatchedRouteName('api/forum/topic/item/get');
        $this->assertActionName('get-topic');
    }

    public function testNewIsForbidden()
    {
        $this->dispatch('https://www.autowp.ru/api/forum/topic', Request::METHOD_POST);

        $this->assertResponseStatusCode(403);
    }

    /**
     * @suppress PhanUndeclaredMethod
     */
    public function testCreateTopic()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/api/forum/topic', Request::METHOD_POST, [
            'theme_id'            => 2,
            'name'                => 'Test topic',
            'text'                => 'Test topic text',
            'moderator_attention' => 0,
            'subscribe'           => 1,
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertControllerName(ForumController::class);
        $this->assertMatchedRouteName('api/forum/topic/post');
        $this->assertActionName('post-topic');

        $headers = $this->getResponse()->getHeaders();
        $uri     = $headers->get('Location')->uri();
        $parts   = explode('/', $uri->getPath());
        $topicId = $parts[count($parts) - 1];

        $this->assertNotEmpty($topicId);

        // unsubscribe
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/api/forum/topic/' . $topicId, Request::METHOD_PUT, [
            'subscription' => 0,
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertControllerName(ForumController::class);
        $this->assertMatchedRouteName('api/forum/topic/item/put');
        $this->assertActionName('put-topic');

        // subscribe
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/api/forum/topic/' . $topicId, Request::METHOD_PUT, [
            'subscription' => 1,
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertControllerName(ForumController::class);
        $this->assertMatchedRouteName('api/forum/topic/item/put');
        $this->assertActionName('put-topic');

        // close
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/api/forum/topic/' . $topicId, Request::METHOD_PUT, [
            'status' => 'closed',
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertControllerName(ForumController::class);
        $this->assertMatchedRouteName('api/forum/topic/item/put');
        $this->assertActionName('put-topic');

        // open
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/api/forum/topic/' . $topicId, Request::METHOD_PUT, [
            'status' => 'normal',
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertControllerName(ForumController::class);
        $this->assertMatchedRouteName('api/forum/topic/item/put');
        $this->assertActionName('put-topic');

        // subscribes
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/api/forum/topic', Request::METHOD_GET, [
            'subscription' => 1,
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertControllerName(ForumController::class);
        $this->assertMatchedRouteName('api/forum/topic/get');
        $this->assertActionName('get-topics');

        // post message
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/api/comment', Request::METHOD_POST, [
            'item_id'             => $topicId,
            'type_id'             => 5,
            'message'             => 'Test message',
            'moderator_attention' => 0,
            'parent_id'           => null,
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertControllerName(CommentController::class);
        $this->assertMatchedRouteName('api/comment/post');
        $this->assertActionName('post');

        // delete topic
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/api/forum/topic/' . $topicId, Request::METHOD_PUT, [
            'status' => 'deleted',
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertControllerName(ForumController::class);
        $this->assertMatchedRouteName('api/forum/topic/item/put');
        $this->assertActionName('put-topic');
    }
}
