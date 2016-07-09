<?php

namespace Application\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;

use \Catalogue as CatalogueModel;

class Catalogue extends AbstractPlugin
{
    /**
     * @var CatalogueModel
     */
    private $_catalogue;

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
        return $this->_catalogue
            ? $this->_catalogue
            : $this->_catalogue = new CatalogueModel();
    }
}
