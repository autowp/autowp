<?php

namespace Application\Controller\Plugin;

use Application\Model\Catalogue as CatalogueModel;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;

class Catalogue extends AbstractPlugin
{
    private CatalogueModel $catalogue;

    public function __construct(CatalogueModel $catalogue)
    {
        $this->catalogue = $catalogue;
    }

    public function __invoke(): CatalogueModel
    {
        return $this->getCatalogue();
    }

    public function getCatalogue(): CatalogueModel
    {
        return $this->catalogue;
    }
}
