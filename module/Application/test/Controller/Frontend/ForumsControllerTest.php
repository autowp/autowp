<?php

namespace ApplicationTest\Controller\Frontend;

use Application\Controller\Api\ForumController;
use Application\Test\AbstractHttpControllerTestCase;
use ApplicationTest\Data;
use Laminas\Http\Header\Location;
use Laminas\Http\Request;
use Laminas\Http\Response;

use function count;
use function explode;

class ForumsControllerTest extends AbstractHttpControllerTestCase
{
    protected string $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    public function testNewIsForbidden(): void
    {
        $this->dispatch('https://www.autowp.ru/api/forum/topic', Request::METHOD_POST);

        $this->assertResponseStatusCode(403);
    }

    public function testCreateTopic(): void
    {
        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
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

        /** @var Response $response */
        $response = $this->getResponse();
        /** @var Location $location */
        $location = $response->getHeaders()->get('Location');
        $uri      = $location->uri();
        $parts    = explode('/', $uri->getPath());
        $topicId  = $parts[count($parts) - 1];

        $this->assertNotEmpty($topicId);

        // unsubscribe
        $this->reset();
        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
        $this->dispatch('https://www.autowp.ru/api/forum/topic/' . $topicId, Request::METHOD_PUT, [
            'subscription' => 0,
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertControllerName(ForumController::class);
        $this->assertMatchedRouteName('api/forum/topic/item/put');
        $this->assertActionName('put-topic');

        // subscribe
        $this->reset();
        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
        $this->dispatch('https://www.autowp.ru/api/forum/topic/' . $topicId, Request::METHOD_PUT, [
            'subscription' => 1,
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertControllerName(ForumController::class);
        $this->assertMatchedRouteName('api/forum/topic/item/put');
        $this->assertActionName('put-topic');

        // close
        $this->reset();
        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
        $this->dispatch('https://www.autowp.ru/api/forum/topic/' . $topicId, Request::METHOD_PUT, [
            'status' => 'closed',
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertControllerName(ForumController::class);
        $this->assertMatchedRouteName('api/forum/topic/item/put');
        $this->assertActionName('put-topic');

        // open
        $this->reset();
        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
        $this->dispatch('https://www.autowp.ru/api/forum/topic/' . $topicId, Request::METHOD_PUT, [
            'status' => 'normal',
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertControllerName(ForumController::class);
        $this->assertMatchedRouteName('api/forum/topic/item/put');
        $this->assertActionName('put-topic');

        // subscribes
        $this->reset();
        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
        $this->dispatch('https://www.autowp.ru/api/forum/topic', Request::METHOD_GET, [
            'subscription' => 1,
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertControllerName(ForumController::class);
        $this->assertMatchedRouteName('api/forum/topic/get');
        $this->assertActionName('get-topics');

        // delete topic
        $this->reset();
        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
        $this->dispatch('https://www.autowp.ru/api/forum/topic/' . $topicId, Request::METHOD_PUT, [
            'status' => 'deleted',
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertControllerName(ForumController::class);
        $this->assertMatchedRouteName('api/forum/topic/item/put');
        $this->assertActionName('put-topic');
    }
}
