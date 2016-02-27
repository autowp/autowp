<?php
class Moder_SpecController extends Zend_Controller_Action
{
    public function preDispatch()
    {
        parent::preDispatch();

        if (!$this->_helper->user()->inheritsRole('moder')) {
            return $this->_forward('forbidden', 'error', 'default');
        }
    }

    public function indexAction()
    {
        $userValues = new Attrs_User_Values();
        $cars = new Cars();
        $equipes = new Equipes();

        $select = $userValues->select(true)
            ->where('update_date > DATE_SUB(CURDATE(), INTERVAL 1 MONTH)')
            ->order('update_date desc');

        $paginator = Zend_Paginator::factory($select)
            ->setItemCountPerPage(20)
            ->setPageRange(20)
            ->setCurrentPageNumber($this->_getParam('page'));

        $items = array();
        foreach ($paginator->getCurrentItems() as $item) {
            $itemType = $item->findParentAttrs_Item_Types();
            $attr = $item->findParentAttrs_Attributes();
            $user = $item->findParentUsers();

            $attrName = array();
            $curAttr = $attr;
            do {
                $attrName[] = $curAttr->name;
                $curAttr = $curAttr->findParentAttrs_Attributes();
            } while ($curAttr);

            $itemName = '';
            $itemUrl = '';
            if ($itemType->id == 1) {
                $car = $cars->find($item->item_id)->current();
                $itemName = $car->getFullName();
                $itemUrl = $this->_helper->url->url(array(
                    'module'     => 'default',
                    'controller' => 'cars',
                    'action'     => 'car-specifications-editor',
                    'car_id'     => $car->id
                ), 'default', true);
            } elseif ($itemType->id == 2) {
                $car = $equipes->find($item->item_id)->current()->findParentCars();
                $itemName = $car->getFullName();
                $itemUrl = $this->_helper->url->url(array(
                    'module'     => 'default',
                    'controller' => 'cars',
                    'action'     => 'car-specifications-editor',
                    'car_id'     => $car->id
                ), 'default', true);
            }

            $items[] = array(
                'date'     => $item->getDate('update_date'),
                'user'     => $user,
                'attr'     => implode(' / ', array_reverse($attrName)),
                'value'    => $attr->getUserValueText($itemType, $item->item_id, $user),
                'unit'     => $attr->findParentAttrs_Units(),
                'type'     => $itemType,
                'itemName' => $itemName,
                'itemUrl'  => $itemUrl
            );
        }

        $this->view->assign(array(
            'paginator' => $paginator,
            'items'     => $items
        ));
    }


}