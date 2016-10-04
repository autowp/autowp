<?php

namespace Application\Controller\Console;

use Zend\Console\Console;
use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\BrandVehicle;

class CatalogueController extends AbstractActionController
{
    /**
     * @var BrandVehicle
     */
    private $brandVehicle;

    public function __construct(BrandVehicle $brandVehicle)
    {
        $this->brandVehicle = $brandVehicle;
    }

    public function refreshBrandVehicleAction()
    {
        $this->brandVehicle->refreshAllAuto();

        Console::getInstance()->writeLine("done");
    }
}