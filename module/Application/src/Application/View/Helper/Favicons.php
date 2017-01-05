<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHelper;
use Zend\Json\Json;

class Favicons extends AbstractHelper
{
    public function __invoke()
    {
        $file = 'public_html/dist/iconstats.json';
        if (file_exists($file)) {
            $stats = Json::decode(file_get_contents($file), Json::TYPE_ARRAY);
            return implode(PHP_EOL, $stats['html']);
        }

        return '';
    }
}
