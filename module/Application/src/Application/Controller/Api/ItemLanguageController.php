<?php

namespace Application\Controller\Api;

use Zend\Db\TableGateway\TableGateway;
use Zend\InputFilter\InputFilter;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use ZF\ApiProblem\ApiProblemResponse;

use Autowp\Message\MessageService;
use Autowp\TextStorage\Service as TextStorage;
use Autowp\User\Controller\Plugin\User;

use Application\Controller\Plugin\Car;
use Application\Controller\Plugin\ForbiddenAction;
use Application\HostManager;
use Application\Hydrator\Api\RestHydrator;
use Application\Model\ItemParent;
use Application\Model\UserItemSubscribe;
use Application\Model\Item;

/**
 * Class ItemLanguageController
 * @package Application\Controller\Api
 *
 * @method User user()
 * @method ForbiddenAction forbiddenAction()
 * @method ApiProblemResponse inputFilterResponse(InputFilter $inputFilter)
 * @method string language()
 * @method Car car()
 * @method void log(string $message, array $objects)
 * @method string translate(string $message, string $textDomain = 'default', $locale = null)
 */
class ItemLanguageController extends AbstractRestfulController
{
    /**
     * @var TableGateway
     */
    private $table;

    /**
     * @var TextStorage
     */
    private $textStorage;

    /**
     * @var RestHydrator
     */
    private $hydrator;

    /**
     * @var ItemParent
     */
    private $itemParent;

    /**
     * @var HostManager
     */
    private $hostManager;

    /**
     * @var InputFilter
     */
    private $putInputFilter;

    /**
     * @var UserItemSubscribe
     */
    private $userItemSubscribe;

    /**
     * @var Item
     */
    private $item;

    /**
     * @var MessageService
     */
    private $message;

    public function __construct(
        TableGateway $table,
        TextStorage $textStorage,
        RestHydrator $hydrator,
        ItemParent $itemParent,
        HostManager $hostManager,
        InputFilter $putInputFilter,
        MessageService $message,
        UserItemSubscribe $userItemSubscribe,
        Item $item
    ) {
        $this->table = $table;
        $this->textStorage = $textStorage;
        $this->hydrator = $hydrator;
        $this->itemParent = $itemParent;
        $this->hostManager = $hostManager;
        $this->putInputFilter = $putInputFilter;
        $this->message = $message;
        $this->userItemSubscribe = $userItemSubscribe;
        $this->item = $item;
    }

    public function indexAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $rows = $this->table->select([
            'item_id'       => (int)$this->params('id'),
            'language <> ?' => 'xx'
        ]);

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->hydrator->extract($row);
        }

        return new JsonModel([
            'items' => $items
        ]);
    }

    public function getAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $row = $this->table->select([
            'item_id'  => (int)$this->params('id'),
            'language' => (string)$this->params('language')
        ])->current();

        if (! $row) {
            return $this->notFoundAction();
        }

        return new JsonModel($this->hydrator->extract($row));
    }

    public function putAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $item = $this->item->getRow(['id' => (int)$this->params('id')]);
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

        $language = (string)$this->params('language');

        $row = $this->table->select([
            'item_id'  => $item['id'],
            'language' => $language
        ])->current();

        $set = [];

        $changes = [];

        if (array_key_exists('name', $data)) {
            $oldName = $row ? $row['name'] : '';
            $newName = (string)$data['name'];

            if ($oldName !== $newName) {
                $set['name'] = $newName;
                $changes[] = 'moder/vehicle/name';
            }
        }

        if (array_key_exists('text', $data)) {
            $text = $data['text'];
            $textChanged = false;
            if ($row && $row['text_id']) {
                $textChanged = ($text != $this->textStorage->getText($row['text_id']));

                $this->textStorage->setText($row['text_id'], $text, $user['id']);
            } elseif ($text) {
                $textChanged = true;

                $textId = $this->textStorage->createText($text, $user['id']);
                $set['text_id'] = $textId;
            }

            if ($textChanged) {
                $changes[] = 'moder/item/short-description';
            }
        }

        if (array_key_exists('full_text', $data)) {
            $fullText = $data['full_text'];
            $fullTextChanged = false;
            if ($row && $row['full_text_id']) {
                $fullTextChanged = ($fullText != $this->textStorage->getText($row['full_text_id']));

                $this->textStorage->setText($row['full_text_id'], $fullText, $user['id']);
            } elseif ($fullText) {
                $fullTextChanged = true;

                $fullTextId = $this->textStorage->createText($fullText, $user['id']);
                $set['full_text_id'] = $fullTextId;
            }

            if ($fullTextChanged) {
                $changes[] = 'moder/item/full-description';
            }
        }

        if ($set) {
            $primaryKey = [
                'item_id'  => $item['id'],
                'language' => $language
            ];

            if ($row) {
                $this->table->update($set, $primaryKey);
            } else {
                $this->table->insert(array_merge($set, $primaryKey));
            }

            $this->itemParent->refreshAutoByVehicle($item['id']);
        }

        if ($changes) {
            $this->userItemSubscribe->subscribe($user['id'], $item['id']);

            $language = $this->language();

            foreach ($this->userItemSubscribe->getItemSubscribers($item['id']) as $subscriber) {
                if ($subscriber && ($subscriber['id'] != $user['id'])) {
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
                        $this->userModerUrl($user, true, $uri),
                        $this->car()->formatName($item, $subscriber['language']),
                        $this->itemModerUrl($item, true, null, $uri),
                        implode("\n", $changesStr)
                    );

                    $this->message->send(null, $subscriber['id'], $message);
                }
            }

            $this->log(sprintf(
                'Редактирование языковых названия, описания и полного описания автомобиля %s',
                htmlspecialchars($this->car()->formatName($item, 'en')) //TODO: formatter
            ), [
                'items' => $item['id']
            ]);
        }

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return $this->getResponse()->setStatusCode(200);
    }

    /**
     * @param array|\ArrayObject $user
     * @param bool $full
     * @param \Zend\Uri\Uri $uri
     * @return string
     */
    private function userModerUrl($user, $full = false, $uri = null)
    {
        return $this->url()->fromRoute('ng', ['path' => ''], [
            'force_canonical' => $full,
            'uri'             => $uri
        ]) . 'users/' . ($user['identity'] ? $user['identity'] : 'user' . $user['id']);
    }

    /**
     * @param array|\ArrayObject $car
     * @return string
     */
    private function itemModerUrl($item, $full = false, $tab = null, $uri = null)
    {
        $url = 'moder/items/item/' . $item['id'];

        if ($tab) {
            $url .= '?' . http_build_query([
                'tab' => $tab
            ]);
        }

        return $this->url()->fromRoute('ng', ['path' => ''], [
            'force_canonical' => $full,
            'uri'             => $uri
        ]) . $url;
    }
}
