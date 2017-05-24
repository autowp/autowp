<?php

namespace Application\Controller\Api;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql;
use Zend\Db\TableGateway\Feature\RowGatewayFeature;
use Zend\Db\RowGateway\RowGateway;
use Zend\Db\TableGateway\TableGateway;
use Zend\InputFilter\InputFilter;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use ZF\ApiProblem\ApiProblem;
use ZF\ApiProblem\ApiProblemResponse;

use Application\Model\DbTable\Page;

class PageController extends AbstractRestfulController
{
    /**
     * @var TableGateway
     */
    private $table;

    /**
     * @var InputFilter
     */
    private $putInputFilter;

    public function __construct(Adapter $adapter, InputFilter $putInputFilter)
    {
        $this->table = new TableGateway('pages', $adapter, new RowGatewayFeature('id'));
        $this->putInputFilter = $putInputFilter;
    }

    public function indexAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return new ApiProblemResponse(new ApiProblem(403, 'Forbidden'));
        }

        return new JsonModel([
            'items' => $this->getPagesList(null)
        ]);
    }

    private function getPagesList($parentId)
    {
        $result = [];

        $select = new Sql\Select($this->table->getTable());

        $select->order('position');
        if ($parentId) {
            $select->where(['parent_id' => $parentId]);
        } else {
            $select->where(['parent_id IS NULL']);
        }
        $rows = $this->table->selectWith($select);
        foreach ($rows as $page) {
            $result[] = [
                'id'            => (int)$page['id'],
                'name'          => $page['name'],
                'breadcrumbs'   => $page['breadcrumbs'],
                'is_group_node' => (bool)$page['is_group_node'],
                'childs'        => $this->getPagesList($page['id'])
            ];
        }

        return $result;
    }


    public function itemAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return new ApiProblemResponse(new ApiProblem(403, 'Forbidden'));
        }

        $page = $this->table->select(['id' => (int)$this->params('id')])->current();
        if (! $page) {
            return new ApiProblemResponse(new ApiProblem(404, 'Not found'));
        }

        return new JsonModel([
            'id'              => (int)$page['id'],
            'parent_id'       => $page['parent_id'],
            'name'            => $page['name'],
            'title'           => $page['title'],
            'url'             => $page['url'],
            'breadcrumbs'     => $page['breadcrumbs'],
            'is_group_node'   => (bool)$page['is_group_node'],
            'registered_only' => (bool)$page['registered_only'],
            'guest_only'      => (bool)$page['guest_only'],
            'class'           => $page['class'],
        ]);
    }

    public function itemPutAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return new ApiProblemResponse(new ApiProblem(403, 'Forbidden'));
        }

        $page = $this->table->select(['id' => (int)$this->params('id')])->current();
        if (! $page) {
            return new ApiProblemResponse(new ApiProblem(404, 'Not found'));
        }

        $data = $this->processBodyContent($this->getRequest());

        $fields = [];
        foreach (array_keys($data) as $key) {
            if ($this->putInputFilter->has($key)) {
                $fields[] = $key;
            }
        }

        $this->putInputFilter->setValidationGroup($fields);

        $this->putInputFilter->setData($data);

        if (! $this->putInputFilter->isValid()) {
            return $this->inputFilterResponse($this->putInputFilter);
        }

        $data = $this->putInputFilter->getValues();

        $position = isset($data['position']) ? (string)$data['position'] : null;

        switch ($position) {
            case 'up':
                $select = new Sql\Select($this->table->getTable());
                $select
                    ->where([
                        'parent_id'    => $page['parent_id'],
                        'position < ?' => $page['position']
                    ])
                    ->order('position DESC')
                    ->limit(1);
                $prevPage = $this->table->selectWith($select)->current();

                if ($prevPage) {
                    $prevPagePos = $prevPage['position'];

                    $prevPage['position'] = 10000;
                    $prevPage->save();

                    $pagePos = $page['position'];
                    $page['position'] = $prevPagePos;
                    $page->save();

                    $prevPage['position'] = $pagePos;
                    $prevPage->save();
                }
                break;
            case 'down':
                $select = new Sql\Select($this->table->getTable());
                $select
                    ->where([
                        'parent_id'    => $page['parent_id'],
                        'position > ?' => $page['position']
                    ])
                    ->order('position ASC')
                    ->limit(1);
                $nextPage = $this->table->selectWith($select)->current();

                if ($nextPage) {
                    $nextPagePos = $nextPage['position'];

                    $nextPage['position'] = 10000;
                    $nextPage->save();

                    $pagePos = $page['position'];
                    $page['position'] = $nextPagePos;
                    $page->save();

                    $nextPage['position'] = $pagePos;
                    $nextPage->save();
                }
                break;
        }

        $update = [];

        if (array_key_exists('parent_id', $data)) {
            $update['parent_id'] = $data['parent_id'] ? (int)$data['parent_id'] : null;
        }

        if (array_key_exists('name', $data)) {
            $update['name'] = $data['name'];
        }

        if (array_key_exists('title', $data)) {
            $update['title'] = $data['title'];
        }

        if (array_key_exists('breadcrumbs', $data)) {
            $update['breadcrumbs'] = $data['breadcrumbs'];
        }

        if (array_key_exists('url', $data)) {
            $update['url'] = $data['url'];
        }

        if (array_key_exists('class', $data)) {
            $update['class'] = $data['class'];
        }

        if (array_key_exists('is_group_node', $data)) {
            $update['is_group_node'] = $data['is_group_node'] ? 1 : 0;
        }

        if (array_key_exists('registered_only', $data)) {
            $update['registered_only'] = $data['registered_only'] ? 1 : 0;
        }

        if (array_key_exists('guest_only', $data)) {
            $update['guest_only'] = $data['guest_only'] ? 1 : 0;
        }

        if ($update) {
            $this->table->update($update, [
                'id' => $page['id']
            ]);
        }

        return $this->getResponse()->setStatusCode(200);
    }
}
