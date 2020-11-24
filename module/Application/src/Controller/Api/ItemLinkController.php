<?php

namespace Application\Controller\Api;

use Application\Hydrator\Api\AbstractRestHydrator;
use Autowp\User\Controller\Plugin\User;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Http\PhpEnvironment\Response;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

use function array_key_exists;
use function array_keys;
use function Autowp\Commons\currentFromResultSetInterface;

/**
 * @method User user($user = null)
 * @method ApiProblemResponse inputFilterResponse(InputFilterInterface $inputFilter)
 * @method ViewModel forbiddenAction()
 */
class ItemLinkController extends AbstractRestfulController
{
    private TableGateway $table;

    private AbstractRestHydrator $hydrator;

    private InputFilter $putInputFilter;

    private InputFilter $postInputFilter;

    private InputFilter $listInputFilter;

    public function __construct(
        TableGateway $table,
        AbstractRestHydrator $hydrator,
        InputFilter $listInputFilter,
        InputFilter $putInputFilter,
        InputFilter $postInputFilter
    ) {
        $this->table           = $table;
        $this->hydrator        = $hydrator;
        $this->listInputFilter = $listInputFilter;
        $this->putInputFilter  = $putInputFilter;
        $this->postInputFilter = $postInputFilter;
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function indexAction()
    {
        $this->listInputFilter->setData($this->params()->fromQuery());

        if (! $this->listInputFilter->isValid()) {
            return $this->inputFilterResponse($this->listInputFilter);
        }

        $data = $this->listInputFilter->getValues();

        $rows = $this->table->select([
            'item_id' => $data['item_id'],
        ]);

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->hydrator->extract($row);
        }

        return new JsonModel([
            'items' => $items,
        ]);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function getAction()
    {
        if (! $this->user()->enforce('global', 'moderate')) {
            return $this->forbiddenAction();
        }

        $row = currentFromResultSetInterface($this->table->select([
            'id' => (int) $this->params('id'),
        ]));

        if (! $row) {
            return $this->notFoundAction();
        }

        return new JsonModel($this->hydrator->extract($row));
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function putAction()
    {
        if (! $this->user()->enforce('car', 'edit_meta')) {
            return $this->forbiddenAction();
        }

        $data = $this->processBodyContent($this->getRequest());

        $fields = [];
        foreach (array_keys($data) as $key) {
            if ($this->putInputFilter->has($key)) {
                $fields[] = $key;
            }
        }

        if (! $fields) {
            return new ApiProblemResponse(new ApiProblem(400, 'Invalid request'));
        }

        $this->putInputFilter->setValidationGroup($fields);

        $this->putInputFilter->setData($data);

        if (! $this->putInputFilter->isValid()) {
            return $this->inputFilterResponse($this->putInputFilter);
        }

        $data = $this->putInputFilter->getValues();

        $row = currentFromResultSetInterface($this->table->select([
            'id' => (int) $this->params('id'),
        ]));

        if (! $row) {
            return $this->notFoundAction();
        }

        $set = [];

        if (array_key_exists('name', $data)) {
            $set['name'] = $data['name'];
        }

        if (array_key_exists('url', $data)) {
            $set['url'] = $data['url'];
        }

        if (array_key_exists('type_id', $data)) {
            $set['type'] = $data['type_id'];
        }

        if ($set) {
            $this->table->update($set, ['id' => $row['id']]);
        }

        /** @var Response $response */
        $response = $this->getResponse();
        return $response->setStatusCode(Response::STATUS_CODE_200);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function postAction()
    {
        if (! $this->user()->enforce('car', 'edit_meta')) {
            return $this->forbiddenAction();
        }

        /** @var Request $request */
        $request = $this->getRequest();
        if ($this->requestHasContentType($request, self::CONTENT_TYPE_JSON)) {
            $data = $this->jsonDecode($request->getContent());
        } else {
            $data = $request->getPost()->toArray();
        }

        $this->postInputFilter->setData($data);

        if (! $this->postInputFilter->isValid()) {
            return $this->inputFilterResponse($this->postInputFilter);
        }

        $data = $this->postInputFilter->getValues();

        $this->table->insert([
            'item_id' => $data['item_id'],
            'name'    => $data['name'],
            'url'     => $data['url'],
            'type'    => $data['type_id'],
        ]);

        $url = $this->url()->fromRoute('api/item-link/item/get', [
            'id' => $this->table->getLastInsertValue(),
        ]);

        /** @var Response $response */
        $response = $this->getResponse();
        $response->getHeaders()->addHeaderLine('Location', $url);
        return $response->setStatusCode(Response::STATUS_CODE_201);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function deleteAction()
    {
        if (! $this->user()->enforce('car', 'edit_meta')) {
            return $this->forbiddenAction();
        }

        $row = currentFromResultSetInterface($this->table->select([
            'id' => (int) $this->params('id'),
        ]));

        if (! $row) {
            return $this->notFoundAction();
        }

        $this->table->delete(['id' => $row['id']]);

        /** @var Response $response */
        $response = $this->getResponse();
        return $response->setStatusCode(Response::STATUS_CODE_200);
    }
}
