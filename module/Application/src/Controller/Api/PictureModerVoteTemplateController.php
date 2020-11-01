<?php

namespace Application\Controller\Api;

use Autowp\User\Controller\Plugin\User;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Http\PhpEnvironment\Response;
use Laminas\InputFilter\InputFilter;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

use function Autowp\Commons\currentFromResultSetInterface;

/**
 * @method User user($user = null)
 * @method ViewModel forbiddenAction()
 * @method ApiProblemResponse inputFilterResponse(InputFilter $inputFilter)
 */
class PictureModerVoteTemplateController extends AbstractRestfulController
{
    private TableGateway $table;

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

        $row = currentFromResultSetInterface($this->table->selectWith($select));
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

        /** @var Response $response */
        $response = $this->getResponse();
        return $response->setStatusCode(Response::STATUS_CODE_204);
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

        /** @var Response $response */
        $response = $this->getResponse();
        $response->getHeaders()->addHeaderLine(
            'Location',
            $this->url()->fromRoute('api/picture-moder-vote-template/item/get', [
                'id' => $id,
            ])
        );
        return $response->setStatusCode(Response::STATUS_CODE_201);
    }
}
