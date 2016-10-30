<?php

namespace Application\View\Helper;

use Zend\Json\Json;
use Zend\View\Helper\AbstractHelper;

class GulpRevFiles extends AbstractHelper
{
    public function __invoke()
    {
        $manifestFile = PUBLIC_DIR . '/rev-manifest.json';
        
        $manifest = Json::decode(file_get_contents($manifestFile), Json::TYPE_ARRAY);
        
        if (isset($manifest['styles.css'])) {
            $this->addStylesheet($manifest['styles.css']);
        } else {
            $this->addStylesheet('styles.css');
        }
    }
    
    private function addStylesheet($file)
    {
        $this->view->headLink()
            ->appendStylesheet('/css/' . $file, 'screen');
    }
}