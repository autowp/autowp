<?php

namespace ApplicationTest\Controller\Frontend;

use Zend\Db\Adapter\Adapter;
use Zend\Json\Json;
use Zend\Http\Header\Cookie;
use Zend\Http\Request;

use Application\Controller\CommentsController;
use Application\Controller\UploadController;
use Application\Test\AbstractHttpControllerTestCase;

class CommentsControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    private function addPictureToItem($itemId)
    {
        $this->reset();

        $request = $this->getRequest();
        $request->getHeaders()
            ->addHeader(Cookie::fromString('Cookie: remember=admin-token'))
            ->addHeaderLine('Content-Type', 'multipart/form-data');
        $request->getServer()->set('REMOTE_ADDR', '127.0.0.1');

        $file = tempnam(sys_get_temp_dir(), 'upl');
        $filename = 'test1.jpg';
        copy(__DIR__ . '/../../_files/' . $filename, $file);

        $request->getFiles()->fromArray([
            'picture' => [
                [
                    'tmp_name' => $file,
                    'name'     => $filename,
                    'error'    => UPLOAD_ERR_OK,
                    'type'     => 'image/jpeg'
                ]
            ]
        ]);
        $this->dispatch('https://www.autowp.ru/upload/send/type/1/item_id/' . $itemId, Request::METHOD_POST, [], true);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(UploadController::class);
        $this->assertMatchedRouteName('upload/params');
        $this->assertActionName('send');

        $this->assertResponseHeaderContains('Content-Type', 'application/json; charset=utf-8');

        $json = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);

        $this->assertInternalType('array', $json);
        $this->assertNotEmpty($json);

        foreach ($json as $item) {
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('html', $item);
            $this->assertArrayHasKey('width', $item);
            $this->assertArrayHasKey('height', $item);
        }

        return $json[0]['id'];
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
