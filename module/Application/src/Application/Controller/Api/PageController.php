<?php

namespace Application\Controller\Api;

use Autowp\User\Controller\Plugin\User;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\Feature\RowGatewayFeature;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\InputFilter\InputFilter;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;

use function array_key_exists;
use function array_keys;

/**
 * @method User user($user = null)
 * @method ApiProblemResponse inputFilterResponse(InputFilter $inputFilter)
 */
class PageController extends AbstractRestfulController
{
    private TableGateway $table;

    private InputFilter $putInputFilter;

    private InputFilter $postInputFilter;

    public function __construct(
        Adapter $adapter,
        InputFilter $putInputFilter,
        InputFilter $postInputFilter
    ) {
        $this->table           = new TableGateway('pages', $adapter, new RowGatewayFeature('id'));
        $this->putInputFilter  = $putInputFilter;
        $this->postInputFilter = $postInputFilter;
    }

    public function indexAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return new ApiProblemResponse(new ApiProblem(403, 'Forbidden'));
        }

        return new JsonModel([
            'items' => $this->getPagesList(null),
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
                'id' => (int) $page['id'],
                //'name'          => $page['name'],
                //'breadcrumbs'   => $page['breadcrumbs'],
                'is_group_node' => (bool) $page['is_group_node'],
                'childs'        => $this->getPagesList($page['id']),
                'url'           => $page['url'],
            ];
        }

        return $result;
    }

    public function itemAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return new ApiProblemResponse(new ApiProblem(403, 'Forbidden'));
        }

        $page = $this->table->select(['id' => (int) $this->params('id')])->current();
        if (! $page) {
            return new ApiProblemResponse(new ApiProblem(404, 'Not found'));
        }

        return new JsonModel([
            'id'              => (int) $page['id'],
            'parent_id'       => $page['parent_id'],
            'name'            => $page['name'],
            'title'           => $page['title'],
            'url'             => $page['url'],
            'breadcrumbs'     => $page['breadcrumbs'],
            'is_group_node'   => (bool) $page['is_group_node'],
            'registered_only' => (bool) $page['registered_only'],
            'guest_only'      => (bool) $page['guest_only'],
            'class'           => $page['class'],
        ]);
    }

    public function itemPutAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return new ApiProblemResponse(new ApiProblem(403, 'Forbidden'));
        }

        $page = $this->table->select(['id' => (int) $this->params('id')])->current();
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

        $position = isset($data['position']) ? (string) $data['position'] : null;

        switch ($position) {
            case 'up':
                $select = new Sql\Select($this->table->getTable());
                $select
                    ->where([
                        'parent_id'    => $page['parent_id'],
                        'position < ?' => $page['position'],
                    ])
                    ->order('position DESC')
                    ->limit(1);
                $prevPage = $this->table->selectWith($select)->current();

                if ($prevPage) {
                    $prevPagePos = $prevPage['position'];

                    $prevPage['position'] = 10000;
                    $prevPage->save();

                    $pagePos          = $page['position'];
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
                        'position > ?' => $page['position'],
                    ])
                    ->order('position ASC')
                    ->limit(1);
                $nextPage = $this->table->selectWith($select)->current();

                if ($nextPage) {
                    $nextPagePos = $nextPage['position'];

                    $nextPage['position'] = 10000;
                    $nextPage->save();

                    $pagePos          = $page['position'];
                    $page['position'] = $nextPagePos;
                    $page->save();

                    $nextPage['position'] = $pagePos;
                    $nextPage->save();
                }
                break;
        }

        $update = [];

        if (array_key_exists('parent_id', $data)) {
            $update['parent_id'] = $data['parent_id'] ? (int) $data['parent_id'] : null;
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
                'id' => $page['id'],
            ]);
        }

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return $this->getResponse()->setStatusCode(200);
    }

    /**
     * @suppress PhanDeprecatedFunction
     */
    public function postAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return new ApiProblemResponse(new ApiProblem(403, 'Forbidden'));
        }

        $data = $this->processBodyContent($this->getRequest());

        $this->postInputFilter->setData($data);

        if (! $this->postInputFilter->isValid()) {
            return $this->inputFilterResponse($this->postInputFilter);
        }

        $data = $this->postInputFilter->getValues();

        $select = new Sql\Select($this->table->getTable());
        $select->columns(['position' => new Sql\Expression('MAX(position)')]);

        if ($data['parent_id']) {
            $select->where(['parent_id' => $data['parent_id']]);
        } else {
            $select->where(['parent_id IS NULL']);
        }

        $sql       = new Sql\Sql($this->table->getAdapter());
        $statement = $sql->prepareStatementForSqlObject($select);
        $result    = $statement->execute();
        $row       = $result->current();
        $position  = 1 + $row['position'];

        $insert = [
            'parent_id'       => $data['parent_id'] ? (int) $data['parent_id'] : null,
            'name'            => $data['name'],
            'title'           => $data['title'],
            'breadcrumbs'     => $data['breadcrumbs'],
            'url'             => $data['url'],
            'class'           => $data['class'],
            'is_group_node'   => $data['is_group_node'] ? 1 : 0,
            'registered_only' => $data['registered_only'] ? 1 : 0,
            'guest_only'      => $data['guest_only'] ? 1 : 0,
            'position'        => $position,
        ];

        $this->table->insert($insert);

        $id = $this->table->getLastInsertValue();

        $url = $this->url()->fromRoute('api/page/item/get', [
            'id' => $id,
        ]);
        $this->getResponse()->getHeaders()->addHeaderLine('Location', $url);

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return $this->getResponse()->setStatusCode(201);
    }

    public function itemDeleteAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return new ApiProblemResponse(new ApiProblem(403, 'Forbidden'));
        }

        $page = $this->table->select(['id' => (int) $this->params('id')])->current();
        if (! $page) {
            return new ApiProblemResponse(new ApiProblem(404, 'Not found'));
        }

        $page->delete();

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return $this->getResponse()->setStatusCode(204);
    }

    public function parentsAction()
    {
        $result = [];

        $pageId = (int) $this->params()->fromQuery('id');

        do {
            $row = $this->table->select([
                'id' => $pageId,
            ])->current();

            if ($row) {
                $result[] = [
                    'id'        => (int) $row['id'],
                    'parent_id' => (int) $row['parent_id'],
                    //'name'          => $row['name'],
                    //'breadcrumbs'   => $row['breadcrumbs'],
                    //'title'         => $row['title'],
                    'is_group_node' => (bool) $row['is_group_node'],
                    'url'           => $row['url'],
                ];
            }

            $pageId = $row['parent_id'];
        } while ($pageId);

        return new JsonModel([
            'items' => $result,
        ]);
    }
}
