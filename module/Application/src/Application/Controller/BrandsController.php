<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Application\Model\Item;

class BrandsController extends AbstractActionController
{
    /**
     * @var Item
     */
    private $itemModel;

    public function __construct(Item $itemModel)
    {
        $this->itemModel = $itemModel;
    }

    public function newcarsAction()
    {
        /*if (!$this->getRequest()->isXmlHttpRequest()) {
            return $this->notFoundAction();
        }*/

        $brand = $this->itemModel->getRow([
            'item_type_id' => Item::BRAND,
            'id'           => (int)$this->params('brand_id')
        ]);

        if (! $brand) {
            return $this->notFoundAction();
        }

        $language = $this->language();

        $langName = $this->itemModel->getName($brand['id'], $language);

        $carList = $this->itemModel->getRows([
            'ancestor'        => $brand['id'],
            'created_in_days' => 7,
            'limit'           => 30,
            'order'           => 'item.add_datetime DESC'
        ]);

        $cars = [];
        foreach ($carList as $car) {
            $cars[] = $this->itemModel->getNameData($car, $language);
        }

        $viewModel = new ViewModel([
            'brand'     => $brand,
            'carList'   => $cars,
            'name'      => $langName ? $langName : $brand['name']
        ]);
        $viewModel->setTerminal(true);

        return $viewModel;
    }
}
