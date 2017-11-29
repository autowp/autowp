<?php

namespace Application\Controller;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

use Autowp\Message\MessageService;
use Autowp\User\Model\User;

use Application\HostManager;
use Application\Model\Brand;
use Application\Model\Item;
use Application\Model\Perspective;
use Application\Model\UserItemSubscribe;
use Application\Service\SpecificationsService;
use Application\Model\Picture;

class CarsController extends AbstractActionController
{
    /**
     * @var HostManager
     */
    private $hostManager;

    /**
     * @var SpecificationsService
     */
    private $specsService = null;

    /**
     * @var MessageService
     */
    private $message;

    /**
     * @var UserItemSubscribe
     */
    private $userItemSubscribe;

    /**
     * @var Perspective
     */
    private $perspective;

    /**
     * @var Item
     */
    private $itemModel;

    /**
     * @var Picture
     */
    private $picture;

    /**
     * @var TableGateway
     */
    private $attributeTable;

    /**
     * @var TableGateway
     */
    private $userValueTable;

    /**
     * @var Brand
     */
    private $brand;

    /**
     * @var User
     */
    private $userModel;

    public function __construct(
        HostManager $hostManager,
        SpecificationsService $specsService,
        MessageService $message,
        UserItemSubscribe $userItemSubscribe,
        Perspective $perspective,
        Item $itemModel,
        Picture $picture,
        TableGateway $attributeTable,
        TableGateway $userValueTable,
        Brand $brand,
        User $userModel
    ) {

        $this->hostManager = $hostManager;
        $this->specsService = $specsService;
        $this->message = $message;
        $this->userItemSubscribe = $userItemSubscribe;
        $this->perspective = $perspective;
        $this->itemModel = $itemModel;
        $this->picture = $picture;
        $this->attributeTable = $attributeTable;
        $this->userValueTable = $userValueTable;
        $this->brand = $brand;
        $this->userModel = $userModel;
    }

    private function carModerUrl($item, $uri = null)
    {
        $url = 'moder/items/item/' . $item['id'];

        return $this->url()->fromRoute('ng', ['path' => ''], [
            'force_canonical' => true,
            'uri'             => $uri
        ]) . $url;
    }

    private function editorUrl($car, $tab = null)
    {
        return $this->url()->fromRoute('cars/params', [
            'action'  => 'car-specifications-editor',
            'item_id' => $car['id'],
            'tab'     => $tab
        ]);
    }

    public function carSpecificationsEditorAction()
    {
        if (! $this->user()->isAllowed('specifications', 'edit')) {
            return $this->forbiddenAction();
        }

        $editOnlyMode = $this->user()->get()['specs_weight'] < 0.10;

        $car = $this->itemModel->getRow([
            'id'           => (int)$this->params('item_id'),
            'item_type_id' => [Item::VEHICLE, Item::ENGINE]
        ]);
        if (! $car) {
            return $this->notFoundAction();
        }

        $user = $this->user()->get();

        $result = $this->specsService->getCarForm($car, $user, [
            'editOnlyMode' => $editOnlyMode
        ], $this->language());

        $carForm = $result['form'];
        $carFormData = $result['data'];

        $carForm->setAttribute('action', $this->url()->fromRoute('cars/params', [
            'form' => 'car',
            'tab'  => 'spec'
        ], [], true));

        $request = $this->getRequest();
        if ($request->isPost()) {
            $carForm->setData($this->params()->fromPost());
            if ($carForm->isValid()) {
                $this->specsService->saveCarAttributes($car, $carForm->getData(), $user);

                $this->userModel->invalidateSpecsVolume($user['id']);

                $contribPairs = $this->specsService->getContributors([$car['id']]);
                $contributors = [];
                if ($contribPairs) {
                    $contributors = $this->userModel->getRows([
                        'id' => array_keys($contribPairs),
                        'not_deleted' => true
                    ]);
                }

                foreach ($contributors as $contributor) {
                    if ($contributor['id'] != $user['id']) {
                        $uri = $this->hostManager->getUriByLanguage($contributor['language']);

                        $message = sprintf(
                            $this->translate('pm/user-%s-edited-vehicle-specs-%s', 'default', $contributor['language']),
                            $this->userUrl($user, $uri),
                            $this->car()->formatName($car, $contributor['language'])
                        );

                        $this->message->send(null, $contributor['id'], $message);
                    }
                }

                return $this->redirect()->toUrl($this->editorUrl($car, 'spec'));
            }
        }

        $engine = null;
        $engineInherited = $car['engine_inherit'];
        $engineInheritedFrom = [];
        if ($car['engine_item_id']) {
            $engine = $this->itemModel->getRow(['id' => (int)$car['engine_item_id']]);
            if ($engine && $car['engine_inherit']) {
                $carRows = $this->itemModel->getRows([
                    'descendant' => $car['id'],
                    'engine_id'  => $engine['id'],
                    'order'      => 'ipc1.diff desc'
                ]);

                foreach ($carRows as $carRow) {
                    $engineInheritedFrom[] = [
                        'name' => $this->car()->formatName($carRow, $this->language()),
                        'url'  => '/ng/moder/items/item/' . $carRow['id']
                    ];
                }
            }
        }

        $engineNameData = null;
        if ($engine) {
            $engineNameData = $this->itemModel->getNameData($engine, $this->language());
        }

        $tabs = [
            'info' => [
                'icon'  => 'fa fa-info',
                'title' => 'specifications-editor/tabs/info',
                'count' => 0,
            ],
            'engine' => [
                'icon'  => 'glyphicon glyphicon-align-left',
                'title' => 'specifications-editor/tabs/engine',
                'count' => (bool)$engine,
            ],
            'spec' => [
                'icon'  => 'fa fa-car',
                'title' => 'specifications-editor/tabs/specs',
                'count' => 0,
            ],
            'result' => [
                'icon'      => 'fa fa-table',
                'title'     => 'specifications-editor/tabs/result',
                'data-load' => $this->url()->fromRoute('cars/params', [
                    'action' => 'car-specs'
                ], [], true),
                'count' => 0,
            ],
            'admin' => [
                'icon'      => 'fa fa-cog',
                'title'     => 'specifications-editor/tabs/admin',
                'count' => 0,
            ],
        ];

        $currentTab = $this->params('tab', 'info');
        foreach ($tabs as $id => &$tab) {
            $tab['active'] = $id == $currentTab;
        }

        if ($car['item_type_id'] != Item::VEHICLE) {
            unset($tabs['engine']);
        }

        $isSpecsAdmin = $this->user()->isAllowed('specifications', 'admin');

        if (! $isSpecsAdmin) {
            unset($tabs['admin']);
        }

        return [
            'car'                 => $car,
            'nameData'            => $this->itemModel->getNameData($car, $this->language()),
            'engine'              => $engine,
            'engineInherited'     => $engineInherited,
            'engineInheritedFrom' => $engineInheritedFrom,
            'engineNameData'      => $engineNameData,
            'form'                => $carForm,
            'formData'            => $carFormData,
            'tabs'                => $tabs,
            'isSpecsAdmin'        => $isSpecsAdmin,
            'service'             => $this->specsService
        ];
    }

    public function carSpecsAction()
    {
        if (! $this->user()->isAllowed('specifications', 'edit')) {
            return $this->forbiddenAction();
        }

        $car = $this->itemModel->getRow(['id' => (int)$this->params('item_id')]);
        if (! $car) {
            return $this->notFoundAction();
        }

        $specs = $this->specsService->specifications([$car], [
            'language' => $this->language()
        ]);

        $viewModel = new ViewModel([
            'specs' => $specs,
        ]);

        return $viewModel->setTerminal(true);
    }

    private function userUrl($user, $uri = null)
    {
        return $this->url()->fromRoute('ng', ['path' => ''], [
            'force_canonical' => true,
            'uri'             => $uri
        ]) . 'users/' . ($user['identity'] ? $user['identity'] : 'user' . $user['id']);
    }

    public function cancelCarEngineAction()
    {
        if (! $this->user()->isAllowed('specifications', 'edit-engine')) {
            return $this->forbiddenAction();
        }

        $car = $this->itemModel->getRow(['id' => (int)$this->params('item_id')]);
        if (! $car) {
            return $this->notFoundAction();
        }

        $engine = $this->itemModel->getRow(['id' => (int)$car['engine_item_id']]);

        $this->itemModel->getTable()->update([
            'engine_inherit' => 0,
            'engine_item_id' => null
        ], [
            'id' => $car['id']
        ]);

        $this->itemModel->updateInteritance($car['id']);

        $this->specsService->updateActualValues($car['id']);

        if ($engine) {
            $message = sprintf(
                'У автомобиля %s убран двигатель (был %s)',
                htmlspecialchars($this->car()->formatName($car, 'en')),
                htmlspecialchars($engine['name'])
            );
            $this->log($message, [
                'items' => $car['id']
            ]);

            $user = $this->user()->get();

            foreach ($this->userItemSubscribe->getItemSubscribers($car['id']) as $subscriber) {
                if ($subscriber && ($subscriber['id'] != $user['id'])) {
                    $uri = $this->hostManager->getUriByLanguage($subscriber['language']);

                    $message = sprintf(
                        $this->translate(
                            'pm/user-%s-canceled-vehicle-engine-%s-%s-%s',
                            'default',
                            $subscriber['language']
                        ),
                        $this->userUrl($user, $uri),
                        $engine['name'],
                        $this->car()->formatName($car, $subscriber['language']),
                        $this->carModerUrl($car, $uri)
                    );

                    $this->message->send(null, $subscriber['id'], $message);
                }
            }
        }

        return $this->redirect()->toUrl($this->editorUrl($car, 'engine'));
    }

    public function inheritCarEngineAction()
    {
        if (! $this->user()->isAllowed('specifications', 'edit-engine')) {
            return $this->forbiddenAction();
        }

        $car = $this->itemModel->getRow(['id' => (int)$this->params('item_id')]);
        if (! $car) {
            return $this->notFoundAction();
        }

        if (! $car['engine_inherit']) {
            $this->itemModel->getTable()->update([
                'engine_inherit' => 1,
                'engine_item_id' => null
            ], [
                'id' => $car['id']
            ]);

            $this->itemModel->updateInteritance($car['id']);

            $this->specsService->updateActualValues($car['id']);

            $message = sprintf(
                'У автомобиля %s установлено наследование двигателя',
                htmlspecialchars($this->car()->formatName($car, 'en'))
            );
            $this->log($message, [
                'items' => $car['id']
            ]);

            $user = $this->user()->get();

            foreach ($this->userItemSubscribe->getItemSubscribers($car['id']) as $subscriber) {
                if ($subscriber && ($subscriber['id'] != $user['id'])) {
                    $uri = $this->hostManager->getUriByLanguage($subscriber['language']);

                    $message = sprintf(
                        $this->translate(
                            'pm/user-%s-set-inherited-vehicle-engine-%s-%s',
                            'default',
                            $subscriber['language']
                        ),
                        $this->userUrl($user, $uri),
                        $this->car()->formatName($car, $subscriber['language']),
                        $this->carModerUrl($car, $uri)
                    );

                    $this->message->send(null, $subscriber['id'], $message);
                }
            }
        }

        return $this->redirect()->toUrl($this->editorUrl($car, 'engine'));
    }

    private function enginesWalkTree($parentId)
    {
        $rows = $this->itemModel->getRows([
            'item_type_id' => Item::ENGINE,
            'order'        => 'item.name',
            'parent'       => $parentId
        ]);

        $engines = [];
        foreach ($rows as $row) {
            $engines[] = [
                'id'     => $row['id'],
                'name'   => $this->itemModel->getNameData($row, $this->language()),
                'childs' => $this->enginesWalkTree($row['id'])
            ];
        }

        return $engines;
    }

    public function selectCarEngineAction()
    {
        if (! $this->user()->isAllowed('specifications', 'edit-engine')) {
            return $this->forbiddenAction();
        }

        if (! $this->user()->isAllowed('specifications', 'edit')) {
            return $this->forbiddenAction();
        }

        $car = $this->itemModel->getRow([
            'id'           => (int)$this->params('item_id'),
            'item_type_id' => Item::VEHICLE
        ]);
        if (! $car) {
            return $this->notFoundAction();
        }

        $language = $this->language();

        $brand = $this->brand->getBrandByCatname((string)$this->params()->fromPost('brand'), $language);

        if (! $brand) {
            $brands = $this->brand->getList($language, function (Sql\Select $select) {
                $select
                    ->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', [])
                    ->join(['engine' => 'item'], 'item_parent_cache.item_id = engine.id', [])
                    ->where(['engine.item_type_id' => Item::ENGINE])
                    ->group('item.id');
            });

            return [
                'car'      => $car,
                'nameData' => $this->itemModel->getNameData($car, $language),
                'brand'    => false,
                'brands'   => $brands,
                'engines'  => []
            ];
        }

        $engine = $this->itemModel->getRow([
            'id'           => (int)$this->params()->fromPost('engine'),
            'item_type_id' => Item::ENGINE
        ]);
        if (! $engine) {
            return [
                'car'      => $car,
                'nameData' => $this->itemModel->getNameData($car, $language),
                'brand'    => $brand,
                'brands'   => [],
                'engines'  => $this->enginesWalkTree($brand['id'])
            ];
        }

        $this->itemModel->getTable()->update([
            'engine_inherit' => 0,
            'engine_item_id' => $engine['id']
        ], [
            'id' => $car['id']
        ]);

        $this->itemModel->updateInteritance($car['id']);

        $this->specsService->updateActualValues($car['id']);

        $user = $this->user()->get();

        $message = sprintf(
            'Автомобилю %s назначен двигатель %s',
            htmlspecialchars($this->car()->formatName($car, 'en')),
            htmlspecialchars($engine['name'])
        );
        $this->log($message, [
            'items' => $car['id']
        ]);

        foreach ($this->userItemSubscribe->getItemSubscribers($car['id']) as $subscriber) {
            if ($subscriber && ($subscriber['id'] != $user['id'])) {
                $uri = $this->hostManager->getUriByLanguage($subscriber['language']);

                $message = sprintf(
                    $this->translate('pm/user-%s-set-vehicle-engine-%s-%s-%s', 'default', $subscriber['language']),
                    $this->userUrl($user, $uri),
                    $engine['name'],
                    $this->car()->formatName($car, $subscriber['language']),
                    $this->carModerUrl($car, $uri)
                );

                $this->message->send(null, $subscriber['id'], $message);
            }
        }

        return $this->redirect()->toUrl($this->editorUrl($car, 'engine'));
    }

    public function refreshInheritanceAction()
    {
        if (! $this->user()->isAllowed('specifications', 'admin')) {
            return $this->forbiddenAction();
        }

        $car = $this->itemModel->getRow(['id' => (int)$this->params('item_id')]);
        if (! $car) {
            return $this->notFoundAction();
        }

        $this->itemModel->updateInteritance($car['id']);

        $this->specsService->updateActualValues($car['id']);

        return $this->redirect()->toUrl($this->editorUrl($car, 'admin'));
    }

    public function editValueAction()
    {
        if (! $this->user()->isAllowed('specifications', 'edit')) {
            return $this->forbiddenAction();
        }

        $attrId = (int)$this->params('attr');
        $itemTypeId = (int)$this->params('item_type');
        $itemId = (int)$this->params('item');

        $language = $this->language();

        $form = $this->specsService->getEditValueForm($attrId, $itemTypeId, $itemId, $language);
        if (! $form) {
            return $this->notFoundAction();
        }

        $form->setAction($this->url()->fromRoute(null, [], [], true));

        $viewModel = new ViewModel([
            'form' => $form
        ]);

        return $viewModel->setTerminal(true);
    }

    public function lowWeightAction()
    {
    }

    public function datelessAction()
    {
        if (! $this->user()->isAllowed('specifications', 'edit')) {
            return $this->forbiddenAction();
        }

        $listCars = [];

        $paginator = $this->itemModel->getPaginator([
            'dateless' => true,
            'order'    => $this->catalogue()->itemOrdering()
        ]);

        $paginator
            ->setItemCountPerPage(20)
            ->setCurrentPageNumber($this->params('page'));

        foreach ($paginator->getCurrentItems() as $row) {
            $listCars[] = $row;
        }

        return [
            'paginator'     => $paginator,
            'childListData' => $this->car()->listData($listCars, [
                'pictureFetcher' => new \Application\Model\Item\PerspectivePictureFetcher([
                    'pictureModel'         => $this->picture,
                    'itemModel'            => $this->itemModel,
                    'perspective'          => $this->perspective,
                    'type'                 => null,
                    'onlyExactlyPictures'  => false,
                    'dateSort'             => false,
                    'disableLargePictures' => false,
                    'perspectivePageId'    => null,
                    'onlyChilds'           => []
                ]),
                'listBuilder' => new \Application\Model\Item\ListBuilder([
                    'catalogue'    => $this->catalogue(),
                    'router'       => $this->getEvent()->getRouter(),
                    'picHelper'    => $this->getPluginManager()->get('pic'),
                    'specsService' => $this->specsService
                ]),
            ]),
        ];
    }
}
