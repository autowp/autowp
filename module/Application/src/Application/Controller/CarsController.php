<?php

namespace Application\Controller;

use Zend\Db\TableGateway\TableGateway;
use Zend\Mvc\Controller\AbstractActionController;

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
                'icon'  => 'fa fa-table',
                'title' => 'specifications-editor/tabs/result',
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

    private function userUrl($user, $uri = null)
    {
        return $this->url()->fromRoute('ng', ['path' => ''], [
            'force_canonical' => true,
            'uri'             => $uri
        ]) . 'users/' . ($user['identity'] ? $user['identity'] : 'user' . $user['id']);
    }

    public function lowWeightAction()
    {
    }
}
