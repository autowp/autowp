<?php

namespace Application\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;

use Application\Model\Catalogue as CatalogueModel;

class Catalogue extends AbstractPlugin
{
    /**
     * @var CatalogueModel
     */
    private $catalogue;

    /**
     * @return CatalogueModel
     */
    public function __invoke()
    {
        return $this->getCatalogue();
    }

    /**
     * @return CatalogueModel
     */
    public function getCatalogue()
    {
        return $this->catalogue
            ? $this->catalogue
            : $this->catalogue = new CatalogueModel();
    }
}
