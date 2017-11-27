<?php

namespace ApplicationTest\Controller\Frontend;

use Zend\Db\Adapter\Adapter;
use Zend\Http\Header\Cookie;
use Zend\Http\Request;

use Application\Controller\Api\CommentController;
use Application\Controller\Api\PictureController;
use Application\Controller\CommentsController;
use Application\Test\AbstractHttpControllerTestCase;

class CommentsControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    private function mockDuplicateFinder()
    {
        $serviceManager = $this->getApplicationServiceLocator();

        $tables = $serviceManager->get('TableManager');

        $mock = $this->getMockBuilder(\Application\DuplicateFinder::class)
            ->setMethods(['indexImage'])
            ->setConstructorArgs([
                $tables->get('df_hash'),
                $tables->get('df_distance')
            ])
            ->getMock();

        $mock->method('indexImage')->willReturn(true);

        $serviceManager->setService(\Application\DuplicateFinder::class, $mock);
    }

    private function addPictureToItem($itemId)
    {
        $this->reset();

        $this->mockDuplicateFinder();

        $request = $this->getRequest();
        $request->getHeaders()
            ->addHeader(Cookie::fromString('Cookie: remember=admin-token'))
            ->addHeaderLine('Content-Type', 'multipart/form-data');
        $request->getServer()->set('REMOTE_ADDR', '127.0.0.1');

        $file = tempnam(sys_get_temp_dir(), 'upl');
        $filename = 'test1.jpg';
        copy(__DIR__ . '/../../_files/' . $filename, $file);

        $request->getFiles()->fromArray([
            'file' => [
                'tmp_name' => $file,
                'name'     => $filename,
                'error'    => UPLOAD_ERR_OK,
                'type'     => 'image/jpeg'
            ]
        ]);
        $this->dispatch('https://www.autowp.ru/api/picture', Request::METHOD_POST, [
            'item_id' => $itemId
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertModuleName('application');
        $this->assertControllerName(PictureController::class);
        $this->assertMatchedRouteName('api/picture/post');
        $this->assertActionName('post');

        $headers = $this->getResponse()->getHeaders();
        $uri = $headers->get('Location')->uri();
        $parts = explode('/', $uri->getPath());
        $pictureId = $parts[count($parts) - 1];

        return $pictureId;
    }

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
        $pictureId = $this->addPictureToItem(1);

        // create comment
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/comments/add/type_id/1/item_id/' . $pictureId, Request::METHOD_POST, [
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
        $pictureId = $this->addPictureToItem(1);

        // create comment
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/comments/add/type_id/1/item_id/' . $pictureId, Request::METHOD_POST, [
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
        $this->dispatch('https://www.autowp.ru/api/comment/' . $comment['id'], Request::METHOD_PUT, [
            'user_vote' => 1
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CommentController::class);
        $this->assertMatchedRouteName('api/comment/item/put');
        $this->assertActionName('put');

        // vote negative
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=token'));
        $this->dispatch('https://www.autowp.ru/api/comment/' . $comment['id'], Request::METHOD_PUT, [
            'user_vote' => -1
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CommentController::class);
        $this->assertMatchedRouteName('api/comment/item/put');
        $this->assertActionName('put');

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
        $pictureId = $this->addPictureToItem(1);

        // create comment
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/comments/add/type_id/1/item_id/' . $pictureId, Request::METHOD_POST, [
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
        $this->dispatch('https://www.autowp.ru/api/comment/' . $comment['id'], Request::METHOD_PUT, [
            'deleted' => 1
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CommentController::class);
        $this->assertMatchedRouteName('api/comment/item/put');
        $this->assertActionName('put');

        // restore
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/api/comment/' . $comment['id'], Request::METHOD_PUT, [
            'deleted' => 0
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CommentController::class);
        $this->assertMatchedRouteName('api/comment/item/put');
        $this->assertActionName('put');
    }

    public function testCreateCommentAndResolve()
    {
        $pictureId = $this->addPictureToItem(1);

        // create comment
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/comments/add/type_id/1/item_id/' . $pictureId, Request::METHOD_POST, [
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
