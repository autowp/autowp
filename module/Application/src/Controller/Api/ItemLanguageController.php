<?php

namespace Application\Controller\Api;

use Application\Controller\Plugin\Car;
use Application\HostManager;
use Application\Hydrator\Api\AbstractRestHydrator;
use Application\Model\Item;
use Application\Model\ItemParent;
use Application\Model\UserItemSubscribe;
use ArrayObject;
use Autowp\Message\MessageService;
use Autowp\TextStorage\Service as TextStorage;
use Autowp\User\Controller\Plugin\User;
use Exception;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Http\PhpEnvironment\Response;
use Laminas\InputFilter\InputFilter;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Stdlib\ResponseInterface;
use Laminas\Uri\Uri;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

use function array_key_exists;
use function array_keys;
use function Autowp\Commons\currentFromResultSetInterface;
use function htmlspecialchars;
use function implode;
use function sprintf;

/**
 * @method User user($user = null)
 * @method ViewModel forbiddenAction()
 * @method ApiProblemResponse inputFilterResponse(InputFilter $inputFilter)
 * @method string language()
 * @method Car car()
 * @method void log(string $message, array $objects)
 * @method string translate(string $message, string $textDomain = 'default', $locale = null)
 */
class ItemLanguageController extends AbstractRestfulController
{
    private TableGateway $table;

    private TextStorage $textStorage;

    private AbstractRestHydrator $hydrator;

    private ItemParent $itemParent;

    private HostManager $hostManager;

    private InputFilter $putInputFilter;

    private UserItemSubscribe $userItemSubscribe;

    private Item $item;

    private MessageService $message;

    public function __construct(
        TableGateway $table,
        TextStorage $textStorage,
        AbstractRestHydrator $hydrator,
        ItemParent $itemParent,
        HostManager $hostManager,
        InputFilter $putInputFilter,
        MessageService $message,
        UserItemSubscribe $userItemSubscribe,
        Item $item
    ) {
        $this->table             = $table;
        $this->textStorage       = $textStorage;
        $this->hydrator          = $hydrator;
        $this->itemParent        = $itemParent;
        $this->hostManager       = $hostManager;
        $this->putInputFilter    = $putInputFilter;
        $this->message           = $message;
        $this->userItemSubscribe = $userItemSubscribe;
        $this->item              = $item;
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
            'item_id'       => (int) $this->params('id'),
            'language <> ?' => 'xx',
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

        $row = currentFromResultSetInterface($this->table->select([
            'item_id'  => (int) $this->params('id'),
            'language' => (string) $this->params('language'),
        ]));

        if (! $row) {
            return $this->notFoundAction();
        }

        return new JsonModel($this->hydrator->extract($row));
    }

    /**
     * @return ViewModel|ResponseInterface|array
     * @throws Exception
     */
    public function putAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $item = $this->item->getRow(['id' => (int) $this->params('id')]);
        if (! $item) {
            return $this->notFoundAction();
        }

        $user = $this->user()->get();

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

        $language = (string) $this->params('language');

        $row = currentFromResultSetInterface($this->table->select([
            'item_id'  => $item['id'],
            'language' => $language,
        ]));

        $set = [];

        $changes = [];

        if (array_key_exists('name', $data)) {
            $oldName = $row ? $row['name'] : '';
            $newName = (string) $data['name'];

            if ($oldName !== $newName) {
                $set['name'] = $newName;
                $changes[]   = 'moder/vehicle/name';
            }
        }

        if (array_key_exists('text', $data)) {
            $text        = (string) $data['text'];
            $textChanged = false;
            if ($row && $row['text_id']) {
                $textChanged = $text !== $this->textStorage->getText($row['text_id']);

                $this->textStorage->setText($row['text_id'], $text, $user['id']);
            } elseif ($text) {
                $textChanged = true;

                $textId         = $this->textStorage->createText($text, $user['id']);
                $set['text_id'] = $textId;
            }

            if ($textChanged) {
                $changes[] = 'moder/item/short-description';
            }
        }

        if (array_key_exists('full_text', $data)) {
            $fullText        = $data['full_text'];
            $fullTextChanged = false;
            if ($row && $row['full_text_id']) {
                $fullTextChanged = $fullText !== $this->textStorage->getText($row['full_text_id']);

                $this->textStorage->setText($row['full_text_id'], $fullText, $user['id']);
            } elseif ($fullText) {
                $fullTextChanged = true;

                $fullTextId          = $this->textStorage->createText($fullText, $user['id']);
                $set['full_text_id'] = $fullTextId;
            }

            if ($fullTextChanged) {
                $changes[] = 'moder/item/full-description';
            }
        }

        if ($set) {
            $values     = [
                'item_id'  => $item['id'],
                'language' => $language,
            ];
            $sqlInserts = ['item_id', 'language'];
            $sqlValues  = [':item_id', ':language'];
            $sqlUpdates = [];
            foreach ($set as $key => $value) {
                $sqlInserts[] = $key;
                $sqlValues[]  = ':' . $key;
                $sqlUpdates[] = $key . ' = VALUES(' . $key . ')';
                $values[$key] = $value;
            }

            $sql = '
                INSERT INTO item_language (' . implode(', ', $sqlInserts) . ')
                VALUES (' . implode(', ', $sqlValues) . ')
                ON DUPLICATE KEY UPDATE ' . implode(', ', $sqlUpdates) . '
            ';

            /** @var Adapter $adapter */
            $adapter = $this->table->getAdapter();
            $adapter->query($sql, $values);

            $this->itemParent->refreshAutoByVehicle($item['id']);
        }

        if ($changes) {
            $this->userItemSubscribe->subscribe($user['id'], $item['id']);

            $language = $this->language();

            foreach ($this->userItemSubscribe->getItemSubscribers($item['id']) as $subscriber) {
                if ($subscriber && ((int) $subscriber['id'] !== (int) $user['id'])) {
                    $uri = $this->hostManager->getUriByLanguage($subscriber['language']);

                    $changesStr = [];
                    foreach ($changes as $field) {
                        $changesStr[] = $this->translate(
                            $field,
                            'default',
                            $subscriber['language']
                        ) . ' (' . $language . ')';
                    }

                    $message = sprintf(
                        $this->translate(
                            'pm/user-%s-edited-item-language-%s-%s',
                            'default',
                            $subscriber['language']
                        ),
                        $this->userModerUrl($user, $uri),
                        $this->car()->formatName($item, $subscriber['language']),
                        $this->itemModerUrl($item['id'], $uri),
                        implode("\n", $changesStr)
                    );

                    $this->message->send(null, $subscriber['id'], $message);
                }
            }

            $this->log(sprintf(
                'Редактирование языковых названия, описания и полного описания автомобиля %s',
                htmlspecialchars($this->car()->formatName($item, 'en')) //TODO: formatter
            ), [
                'items' => $item['id'],
            ]);
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
}
