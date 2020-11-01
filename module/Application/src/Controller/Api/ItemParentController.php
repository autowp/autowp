<?php

namespace Application\Controller\Api;

use Application\Controller\Plugin\Car;
use Application\HostManager;
use Application\Hydrator\Api\AbstractRestHydrator;
use Application\Model\Item;
use Application\Model\ItemParent;
use Application\Model\UserItemSubscribe;
use Application\Model\VehicleType;
use Application\Service\SpecificationsService;
use ArrayObject;
use Autowp\Message\MessageService;
use Autowp\User\Controller\Plugin\User;
use Exception;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Http\PhpEnvironment\Response;
use Laminas\InputFilter\InputFilter;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Paginator;
use Laminas\Stdlib\ResponseInterface;
use Laminas\Uri\Uri;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

use function array_key_exists;
use function array_keys;
use function get_object_vars;
use function htmlspecialchars;
use function sprintf;
use function strlen;

/**
 * @method User user($user = null)
 * @method ApiProblemResponse inputFilterResponse(InputFilter $inputFilter)
 * @method string language()
 * @method ViewModel forbiddenAction()
 * @method void log(string $message, array $objects)
 * @method Car car()
 * @method string translate(string $message, string $textDomain = 'default', $locale = null)
 */
class ItemParentController extends AbstractRestfulController
{
    private AbstractRestHydrator $hydrator;

    private ItemParent $itemParent;

    private SpecificationsService $specificationsService;

    private HostManager $hostManager;

    private MessageService $message;

    private InputFilter $listInputFilter;

    private InputFilter $itemInputFilter;

    private InputFilter $postInputFilter;

    private UserItemSubscribe $userItemSubscribe;

    private Item $itemModel;

    private VehicleType $vehicleType;

    private InputFilter $putInputFilter;

    public function __construct(
        AbstractRestHydrator $hydrator,
        InputFilter $listInputFilter,
        InputFilter $itemInputFilter,
        InputFilter $postInputFilter,
        InputFilter $putInputFilter,
        ItemParent $itemParent,
        SpecificationsService $specificationsService,
        HostManager $hostManager,
        MessageService $message,
        UserItemSubscribe $userItemSubscribe,
        Item $itemModel,
        VehicleType $vehicleType
    ) {
        $this->hydrator        = $hydrator;
        $this->listInputFilter = $listInputFilter;
        $this->itemInputFilter = $itemInputFilter;
        $this->postInputFilter = $postInputFilter;
        $this->putInputFilter  = $putInputFilter;

        $this->itemParent            = $itemParent;
        $this->specificationsService = $specificationsService;
        $this->hostManager           = $hostManager;
        $this->message               = $message;
        $this->userItemSubscribe     = $userItemSubscribe;
        $this->itemModel             = $itemModel;
        $this->vehicleType           = $vehicleType;
    }

    /**
     * @suppress PhanDeprecatedFunction
     * @return ViewModel|ResponseInterface|array
     */
    public function indexAction()
    {
        $user = $this->user()->get();

        $isModer = $this->user()->inheritsRole('moder');

        $this->listInputFilter->setData($this->params()->fromQuery());

        if (! $this->listInputFilter->isValid()) {
            return $this->inputFilterResponse($this->listInputFilter);
        }

        $data = $this->listInputFilter->getValues();

        $group = false;

        $select = new Sql\Select($this->itemParent->getTable()->getTable());
        $select->join('item', 'item_parent.item_id = item.id', []);

        if (strlen($data['type_id']) > 0) {
            $select->where(['item_parent.type' => (int) $data['type_id']]);
        }

        if ($data['item_type_id']) {
            $select->where(['item.item_type_id' => $data['item_type_id']]);
        }

        if ($data['parent_id']) {
            $select->where(['item_parent.parent_id' => $data['parent_id']]);
        }

        if ($data['catname']) {
            $select->where(['item_parent.catname' => $data['catname']]);
        }

        if (strlen($data['concept'])) {
            if ($data['concept']) {
                $select->where(['item.is_concept']);
            } else {
                $select->where(['NOT item.is_concept']);
            }
        }

        if (strlen($data['concept_inherit'])) {
            if ($data['concept_inherit']) {
                $select->where(['item.is_concept_inherit']);
            } else {
                $select->where(['NOT item.is_concept_inherit']);
            }
        }

        if ($data['exclude_concept']) {
            $select->where(['not item.is_concept']);
        }

        if ($data['ancestor_id']) {
            $select
                ->join('item_parent_cache', 'item_parent.item_id = item_parent_cache.item_id', [])
                ->where(['item_parent_cache.parent_id' => $data['ancestor_id']])
                ->group(['item_parent.item_id', 'item_parent.parent_id']);

            $group = true;
        }

        if ($isModer) {
            if ($data['item_id']) {
                $select->where(['item_parent.item_id' => $data['item_id']]);
            }

            if ($data['is_group']) {
                $select->where(['item.is_group']);
            }
        }

        switch ($data['order']) {
            case 'categories_first':
                $select->order([
                    'item_parent.type',
                    new Sql\Expression('item.item_type_id = ? DESC', [Item::CATEGORY]),
                    'item.begin_order_cache',
                    'item.end_order_cache',
                    'item.name',
                    'item.body',
                    'item.spec_id',
                ]);
                break;
            case 'type_auto':
                $select->order([
                    'item_parent.type',
                    'item.begin_order_cache',
                    'item.end_order_cache',
                    'item.name',
                    'item.body',
                    'item.spec_id',
                ]);
                break;
            default:
                $select->order([
                    'item_parent.type',
                    'item.name',
                    'item.body',
                    'item.spec_id',
                    'item.begin_order_cache',
                    'item.end_order_cache',
                ]);
                break;
        }

        if ($group) {
            $select->group(['item_parent.item_id', 'item_parent.parent_id']);
        }

        /** @var Adapter $adapter */
        $adapter   = $this->itemParent->getTable()->getAdapter();
        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\DbSelect($select, $adapter)
        );

        $limit = $data['limit'] ? $data['limit'] : 1;

        $paginator
            ->setItemCountPerPage($limit)
            ->setCurrentPageNumber($data['page']);

        $this->hydrator->setOptions([
            'language' => $this->language(),
            'fields'   => $data['fields'],
            'user_id'  => $user ? $user['id'] : null,
        ]);

        $items = [];
        foreach ($paginator->getCurrentItems() as $row) {
            $items[] = $this->hydrator->extract($row);
        }

        return new JsonModel([
            'paginator' => get_object_vars($paginator->getPages()),
            'items'     => $items,
        ]);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function itemAction()
    {
        $user = $this->user()->get();
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $this->itemInputFilter->setData($this->params()->fromQuery());

        if (! $this->itemInputFilter->isValid()) {
            return $this->inputFilterResponse($this->itemInputFilter);
        }

        $data = $this->itemInputFilter->getValues();

        $row = $this->itemParent->getRow(
            (int) $this->params('parent_id'),
            (int) $this->params('item_id')
        );
        if (! $row) {
            return $this->notFoundAction();
        }

        $this->hydrator->setOptions([
            'language' => $this->language(),
            'fields'   => $data['fields'],
            'user_id'  => $user ? $user['id'] : null,
        ]);

        return new JsonModel($this->hydrator->extract($row));
    }

    /**
     * @throws Exception
     * @return ViewModel|ResponseInterface|array
     */
    public function postAction()
    {
        $canMove = $this->user()->isAllowed('car', 'move');
        if (! $canMove) {
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

        $item = $this->itemModel->getRow([
            'id' => (int) $data['item_id'],
        ]);
        if (! $item) {
            return $this->notFoundAction();
        }

        $parentItem = $this->itemModel->getRow([
            'id' => (int) $data['parent_id'],
        ]);
        if (! $parentItem) {
            return $this->notFoundAction();
        }

        $params = [];
        if (array_key_exists('catname', $data)) {
            $params['catname'] = $data['catname'];
        }
        if (array_key_exists('type_id', $data)) {
            $params['type'] = $data['type_id'];
        }

        $this->itemParent->create((int) $parentItem['id'], (int) $item['id'], $params);

        $this->itemModel->updateInteritance($item['id']);

        $this->vehicleType->refreshInheritanceFromParents($item['id']);

        $this->specificationsService->updateActualValues($item['id']);

        $message = sprintf(
            '%s выбран как родительский для %s',
            htmlspecialchars($this->car()->formatName($parentItem, 'en')),
            htmlspecialchars($this->car()->formatName($item, 'en'))
        );
        $this->log($message, [
            'items' => [$item['id'], $parentItem['id']],
        ]);

        $user = $this->user()->get();

        $subscribers = [];
        foreach ($this->userItemSubscribe->getItemSubscribers($item['id']) as $subscriber) {
            $subscribers[$subscriber['id']] = $subscriber;
        }

        foreach ($this->userItemSubscribe->getItemSubscribers($parentItem['id']) as $subscriber) {
            $subscribers[$subscriber['id']] = $subscriber;
        }

        foreach ($subscribers as $subscriber) {
            if ((int) $subscriber['id'] !== (int) $user['id']) {
                $uri = $this->hostManager->getUriByLanguage($subscriber['language']);

                $message = sprintf(
                    $this->translate(
                        'pm/user-%s-adds-item-%s-%s-to-item-%s-%s',
                        'default',
                        $subscriber['language']
                    ),
                    $this->userModerUrl($user, $uri),
                    $this->car()->formatName($item, $subscriber['language']),
                    $this->itemModerUrl($item['id'], $uri),
                    $this->car()->formatName($parentItem, $subscriber['language']),
                    $this->itemModerUrl($parentItem['id'], $uri)
                );

                $this->message->send(null, $subscriber['id'], $message);
            }
        }

        $url = $this->url()->fromRoute('api/item-parent/item/get', [
            'parent_id' => $parentItem['id'],
            'item_id'   => $item['id'],
        ]);

        /** @var Response $response */
        $response = $this->getResponse();
        $response->getHeaders()->addHeaderLine('Location', $url);
        return $response->setStatusCode(Response::STATUS_CODE_201);
    }

    /**
     * @throws Exception
     * @return ViewModel|ResponseInterface|array
     */
    public function putAction()
    {
        $canMove = $this->user()->isAllowed('car', 'move');
        if (! $canMove) {
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

        $row = $this->itemParent->getRow(
            $this->params('parent_id'),
            $this->params('item_id')
        );
        if (! $row) {
            return $this->notFoundAction();
        }

        $values = [];

        if (array_key_exists('catname', $data)) {
            $values['catname'] = $data['catname'];
        }

        if (array_key_exists('type_id', $data)) {
            $values['type'] = $data['type_id'];
        }

        $this->itemParent->setItemParent($row['parent_id'], $row['item_id'], $values, false);

        if (array_key_exists('parent_id', $data) && $data['parent_id']) {
            $success = $this->itemParent->move($row['item_id'], $row['parent_id'], $data['parent_id']);
            if ($success) {
                $item      = $this->itemModel->getRow(['id' => $row['item_id']]);
                $oldParent = $this->itemModel->getRow(['id' => $row['parent_id']]);
                $newParent = $this->itemModel->getRow(['id' => $data['parent_id']]);

                $message = sprintf(
                    '%s перемещен из %s в %s',
                    htmlspecialchars($this->car()->formatName($item, 'en')),
                    htmlspecialchars($this->car()->formatName($oldParent, 'en')),
                    htmlspecialchars($this->car()->formatName($newParent, 'en'))
                );
                $this->log($message, [
                    'items' => [$item['id'], $newParent['id'], $oldParent['id']],
                ]);

                $this->itemModel->updateInteritance($item['id']);

                $this->specificationsService->updateActualValues($row['item_id']);
            }
        }

        /** @var Response $response */
        $response = $this->getResponse();
        return $response->setStatusCode(Response::STATUS_CODE_200);
    }

    /**
     * @param array|ArrayObject $user
     */
    private function userModerUrl($user, Uri $uri): string
    {
        $u = clone $uri;
        $u->setPath('/users/' . ($user['identity'] ? $user['identity'] : 'user' . $user['id']));

        return $u->toString();
    }

    private function itemModerUrl(int $itemId, Uri $uri): string
    {
        $u = clone $uri;
        $u->setPath('/moder/items/item/' . $itemId);

        return $u->toString();
    }

    /**
     * @suppress PhanPluginMixedKeyNoKey
     * @throws Exception
     * @return ViewModel|ResponseInterface|array
     */
    public function deleteAction()
    {
        if (! $this->user()->isAllowed('car', 'move')) {
            return $this->forbiddenAction();
        }

        $this->itemInputFilter->setData($this->params()->fromQuery());

        if (! $this->itemInputFilter->isValid()) {
            return $this->inputFilterResponse($this->itemInputFilter);
        }

        $row = $this->itemParent->getRow(
            (int) $this->params('parent_id'),
            (int) $this->params('item_id')
        );
        if (! $row) {
            return $this->notFoundAction();
        }

        $item = $this->itemModel->getRow(['id' => $row['item_id']]);
        if (! $item) {
            return $this->notFoundAction();
        }

        $parentItem = $this->itemModel->getRow(['id' => $row['parent_id']]);
        if (! $parentItem) {
            return $this->notFoundAction();
        }

        $this->itemParent->remove($parentItem['id'], $item['id']);

        $this->itemModel->updateInteritance($item['id']);

        $this->vehicleType->refreshInheritanceFromParents($item['id']);

        $this->specificationsService->updateActualValues($item['id']);

        $message = sprintf(
            '%s перестал быть родительским автомобилем для %s',
            htmlspecialchars($this->car()->formatName($parentItem, 'en')),
            htmlspecialchars($this->car()->formatName($item, 'en'))
        );
        $this->log($message, [
            'items' => $item['id'],
            $parentItem['id'],
        ]);

        $user = $this->user()->get();

        $subscribers = [];
        foreach ($this->userItemSubscribe->getItemSubscribers($item['id']) as $subscriber) {
            $subscribers[$subscriber['id']] = $subscriber;
        }

        foreach ($this->userItemSubscribe->getItemSubscribers($parentItem['id']) as $subscriber) {
            $subscribers[$subscriber['id']] = $subscriber;
        }

        foreach ($subscribers as $subscriber) {
            if ((int) $subscriber['id'] !== (int) $user['id']) {
                $uri = $this->hostManager->getUriByLanguage($subscriber['language']);

                $message = sprintf(
                    $this->translate(
                        'pm/user-%s-removed-item-%s-%s-from-item-%s-%s',
                        'default',
                        $subscriber['language']
                    ),
                    $this->userModerUrl($user, $uri),
                    $this->car()->formatName($item, $subscriber['language']),
                    $this->itemModerUrl($item['id'], $uri),
                    $this->car()->formatName($parentItem, $subscriber['language']),
                    $this->itemModerUrl($parentItem['id'], $uri)
                );

                $this->message->send(null, $subscriber['id'], $message);
            }
        }

        /** @var Response $response */
        $response = $this->getResponse();
        return $response->setStatusCode(Response::STATUS_CODE_204);
    }
}
