<?php

namespace ApplicationTest\Controller\Frontend;

use Zend\Db\Adapter\Adapter;
use Zend\Json\Json;
use Zend\Http\Header\Cookie;
use Zend\Http\Request;

use Application\Controller\CommentsController;
use Application\Test\AbstractHttpControllerTestCase;

class CommentsControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    private function fetchLastComment()
    {
        // get comment row
        $db = $this->getApplication()->getServiceManager()->get(\Zend\Db\Adapter\AdapterInterface::class);
        return $db->query(
            'select * from comment_message order by id desc limit 1',
            Adapter::QUERY_MODE_EXECUTE
        )->current();
    }

    public function testCreateCommentAndSubcomment()
    {
        // create comment
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/comments/add/type_id/1/item_id/1', Request::METHOD_POST, [
            'moderator_attention' => 0,
            'parent_id'           => null,
            'message'             => 'Test comment'
        ]);

        $this->assertResponseStatusCode(302);
        $this->assertModuleName('application');
        $this->assertControllerName(CommentsController::class);
        $this->assertMatchedRouteName('comments/add');
        $this->assertActionName('add');

        $comment = $this->fetchLastComment();

        // create sub-comment
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $url = 'https://www.autowp.ru/comments/add/type_id/'.$comment['type_id'].'/item_id/' . $comment['item_id'];
        $this->dispatch($url, Request::METHOD_POST, [
            'moderator_attention' => 0,
            'parent_id'           => $comment['id'],
            'message'             => 'Test comment'
        ]);

        $this->assertResponseStatusCode(302);
        $this->assertModuleName('application');
        $this->assertControllerName(CommentsController::class);
        $this->assertMatchedRouteName('comments/add');
        $this->assertActionName('add');
    }

    public function testCreateCommentAndVote()
    {
        // create comment
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/comments/add/type_id/1/item_id/1', Request::METHOD_POST, [
            'moderator_attention' => 0,
            'parent_id'           => null,
            'message'             => 'Test comment'
        ]);

        $this->assertResponseStatusCode(302);
        $this->assertModuleName('application');
        $this->assertControllerName(CommentsController::class);
        $this->assertMatchedRouteName('comments/add');
        $this->assertActionName('add');

        $comment = $this->fetchLastComment();

        // vote positive
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=token'));
        $this->dispatch('https://www.autowp.ru/comments/vote', Request::METHOD_POST, [
            'id'   => $comment['id'],
            'vote' => 1
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CommentsController::class);
        $this->assertMatchedRouteName('comments/vote');
        $this->assertActionName('vote');

        $json = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);

        $this->assertTrue($json['ok']);

        // vote negative
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=token'));
        $this->dispatch('https://www.autowp.ru/comments/vote', Request::METHOD_POST, [
            'id'   => $comment['id'],
            'vote' => -1
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CommentsController::class);
        $this->assertMatchedRouteName('comments/vote');
        $this->assertActionName('vote');

        $json = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);

        $this->assertTrue($json['ok']);

        // get votes
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=token'));
        $this->dispatch('https://www.autowp.ru/comments/votes', Request::METHOD_GET, [
            'id' => $comment['id']
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CommentsController::class);
        $this->assertMatchedRouteName('comments/votes');
        $this->assertActionName('votes');
    }

    public function testCreateCommentAndDeleteAndRestore()
    {
        // create comment
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/comments/add/type_id/1/item_id/1', Request::METHOD_POST, [
            'moderator_attention' => 0,
            'parent_id'           => null,
            'message'             => 'Test comment'
        ]);

        $this->assertResponseStatusCode(302);
        $this->assertModuleName('application');
        $this->assertControllerName(CommentsController::class);
        $this->assertMatchedRouteName('comments/add');
        $this->assertActionName('add');

        $comment = $this->fetchLastComment();

        // delete
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/comments/delete', Request::METHOD_POST, [
            'comment_id' => $comment['id']
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CommentsController::class);
        $this->assertMatchedRouteName('comments/delete');
        $this->assertActionName('delete');

        $json = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);

        $this->assertTrue($json['ok']);

        // restore
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/comments/restore', Request::METHOD_POST, [
            'comment_id' => $comment['id']
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CommentsController::class);
        $this->assertMatchedRouteName('comments/restore');
        $this->assertActionName('restore');

        $json = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);

        $this->assertTrue($json['ok']);
    }

    public function testCreateCommentAndResolve()
    {
        // create comment
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/comments/add/type_id/1/item_id/1', Request::METHOD_POST, [
            'moderator_attention' => 0,
            'parent_id'           => null,
            'message'             => 'Test comment',
            'moderator_attention' => 1
        ]);

        $this->assertResponseStatusCode(302);
        $this->assertModuleName('application');
        $this->assertControllerName(CommentsController::class);
        $this->assertMatchedRouteName('comments/add');
        $this->assertActionName('add');

        $comment = $this->fetchLastComment();

        // resolve
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $url = 'https://www.autowp.ru/comments/add/type_id/'.$comment['type_id'].'/item_id/' . $comment['item_id'];
        $this->dispatch($url, Request::METHOD_POST, [
            'moderator_attention' => 0,
            'parent_id'           => $comment['id'],
            'message'             => 'Resolve',
            'resolve'             => 1
        ]);

        $this->assertResponseStatusCode(302);
        $this->assertModuleName('application');
        $this->assertControllerName(CommentsController::class);
        $this->assertMatchedRouteName('comments/add');
        $this->assertActionName('add');
    }
}
