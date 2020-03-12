<?php

namespace Application\Controller\Api;

use Autowp\User\Controller\Plugin\User;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\InputFilter\InputFilter;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

/**
 * @method User user($user = null)
 * @method ViewModel forbiddenAction()
 * @method ApiProblemResponse inputFilterResponse(InputFilter $inputFilter)
 */
class PictureModerVoteTemplateController extends AbstractRestfulController
{
    /** @var TableGateway */
    private TableGateway $table;

    /** @var InputFilter */
    private InputFilter $createInputFilter;

    public function __construct(InputFilter $createInputFilter, TableGateway $table)
    {
        $this->table             = $table;
        $this->createInputFilter = $createInputFilter;
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function indexAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $user = $this->user()->get();

        $select = new Sql\Select($this->table->getTable());
        $select
            ->columns(['id', 'reason', 'vote'])
            ->where(['user_id' => $user['id']])
            ->order('reason');

        $items = [];
        foreach ($this->table->selectWith($select) as $row) {
            $items[] = [
                'id'   => (int) $row['id'],
                'name' => $row['reason'],
                'vote' => (int) $row['vote'],
            ];
        }

        return new JsonModel([
            'items' => $items,
        ]);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function itemAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $user = $this->user()->get();

        $select = new Sql\Select($this->table->getTable());
        $select
            ->columns(['id', 'reason', 'vote'])
            ->where([
                'user_id' => $user['id'],
                'id'      => (int) $this->params('id'),
            ]);

        $row = $this->table->selectWith($select)->current();
        if (! $row) {
            return $this->notFoundAction();
        }

        return new JsonModel([
            'id'   => (int) $row['id'],
            'name' => $row['reason'],
            'vote' => (int) $row['vote'],
        ]);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function deleteAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $this->table->delete([
            'user_id' => $this->user()->get()['id'],
            'id'      => (int) $this->params('id'),
        ]);

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return $this->getResponse()->setStatusCode(204);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function createAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $user = $this->user()->get();

        $this->createInputFilter->setData(
            $this->processBodyContent($this->getRequest())
        );

        if (! $this->createInputFilter->isValid()) {
            return $this->inputFilterResponse($this->createInputFilter);
        }

        $data = $this->createInputFilter->getValues();

        $this->table->insert([
            'user_id' => $user['id'],
            'reason'  => $data['name'],
            'vote'    => $data['vote'],
        ]);

        $id = $this->table->getLastInsertValue();

        $this->getResponse()->getHeaders()->addHeaderLine(
            'Location',
            $this->url()->fromRoute('api/picture-moder-vote-template/item/get', [
                'id' => $id,
            ])
        );

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return $this->getResponse()->setStatusCode(201);
    }
}
