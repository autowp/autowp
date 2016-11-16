<?php

namespace Application\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use Zend\View\Model\ViewModel;

use Application\Model\BrandNav;

class Sidebar extends AbstractPlugin
{
    /**
     * @var BrandNav
     */
    private $brandNav;

    public function __construct(BrandNav $brandNav)
    {
        $this->brandNav = $brandNav;
    }

    public function brand(array $params)
    {
        $defaults = [
            'brand_id'    => null,
            'car_id'      => null,
            'type'        => null,
            'is_concepts' => false,
            'is_engines'  => false
        ];
        $params = array_replace($defaults, $params);

        $sideBarModel = new ViewModel([
            'sections' => $this->brandNav->getMenu(array_replace([
                'language' => $this->getController()->language()
            ], $params))
        ]);
        $sideBarModel->setTemplate('application/sidebar/brand');
        $this->getController()->layout()->addChild($sideBarModel, 'sidebar');

        return;
    }
}
