<?php

namespace Application\Controller\Api;

use Application\Hydrator\Api\AbstractRestHydrator;
use Application\Model\ItemParent;
use Autowp\User\Controller\Plugin\User;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\InputFilter\InputFilter;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

use function array_key_exists;
use function array_keys;

/**
 * @method User user($user = null)
 * @method ViewModel forbiddenAction()
 * @method ApiProblemResponse inputFilterResponse(InputFilter $inputFilter)
 */
class ItemParentLanguageController extends AbstractRestfulController
{
    private TableGateway $table;

    private AbstractRestHydrator $hydrator;

    private ItemParent $itemParent;

    private InputFilter $putInputFilter;

    public function __construct(
        TableGateway $table,
        AbstractRestHydrator $hydrator,
        ItemParent $itemParent,
        InputFilter $putInputFilter
    ) {
        $this->table          = $table;
        $this->hydrator       = $hydrator;
        $this->itemParent     = $itemParent;
        $this->putInputFilter = $putInputFilter;
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function indexAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $rows = $this->table->select([
            'item_id'   => (int) $this->params('item_id'),
            'parent_id' => (int) $this->params('parent_id'),
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
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $row = $this->table->select([
            'item_id'   => (int) $this->params('item_id'),
            'parent_id' => (int) $this->params('parent_id'),
            'language'  => (string) $this->params('language'),
        ])->current();

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
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $data = $this->processBodyContent($this->getRequest());

        $fields = [];
        foreach (array_keys($data) as $key) {
            if ($this->putInputFilter->has($key)) {
                $fields[] = $key;
            }
        }

        $this->putInputFilter->setValidationGroup($fields);

        if (! $fields) {
            return new ApiProblemResponse(new ApiProblem(400, 'Invalid request'));
        }

        $this->putInputFilter->setData($data);

        if (! $this->putInputFilter->isValid()) {
            return $this->inputFilterResponse($this->putInputFilter);
        }

        $data = $this->putInputFilter->getValues();

        $language = (string) $this->params('language');

        $itemId   = (int) $this->params('item_id');
        $parentId = (int) $this->params('parent_id');

        if (array_key_exists('name', $data)) {
            $this->itemParent->setItemParentLanguage($parentId, $itemId, $language, [
                'name' => $data['name'],
            ], false);
        }

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return $this->getResponse()->setStatusCode(200);
    }
}
