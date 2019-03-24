<?php

namespace Application\Controller;

use Zend\Db\Sql;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

use Application\Model\Categories;
use Application\Model\Item;

class CategoryController extends AbstractActionController
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

    public function indexAction()
    {
        return $this->redirect()->toUrl('/ng/category');
    }

    public function newcarsAction()
    {
        $category = $this->itemModel->getRow([
            'item_type_id' => Item::CATEGORY,
            'id'           => (int)$this->params('item_id')
        ]);
        if (! $category) {
            return $this->notFoundAction();
        }

        $language = $this->language();

        $rows = $this->itemModel->getRows([
            'item_type_id' => [Item::VEHICLE, Item::ENGINE],
            'order' => new Sql\Expression('MAX(ip1.timestamp) DESC'),
            'parent' => [
                'item_type_id'     => Item::CATEGORY,
                'ancestor_or_self' => $category['id'],
                'linked_in_days'   => Categories::NEW_DAYS
            ],
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
