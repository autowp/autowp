<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Application\Model\Item;

class FactoriesController extends AbstractActionController
{
    /**
     * @var Item
     */
    private $itemModel;

    public function __construct(
        Item $itemModel
    ) {
        $this->itemModel = $itemModel;
    }

    public function newcarsAction()
    {
        $factory = $this->itemModel->getRow([
            'item_type_id' => Item::FACTORY,
            'id'           => (int)$this->params('item_id')
        ]);
        if (! $factory) {
            return $this->notFoundAction();
        }

        $language = $this->language();

        $rows = $this->itemModel->getRows([
            'item_type_id' => [
                Item::VEHICLE,
                Item::ENGINE
            ],
            'parent' => [
                'id'             => $factory['id'],
                'linked_in_days' => 7,
            ],
            'order' => 'ip1.timestamp DESC',
            'limit' => 20
        ]);

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->itemModel->getNameData($row, $language);
        }

        $viewModel = new ViewModel([
            'items' => $items
        ]);
        $viewModel->setTerminal(true);

        return $viewModel;
    }
}
