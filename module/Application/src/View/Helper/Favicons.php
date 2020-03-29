<?php

namespace Application\View\Helper;

use Laminas\Json\Json;
use Laminas\View\Helper\AbstractHelper;

use function file_exists;
use function file_get_contents;
use function implode;

use const PHP_EOL;

class Favicons extends AbstractHelper
{
    public function __invoke(): string
    {
        $file = 'public_html/dist/iconstats.json';
        if (file_exists($file)) {
            $stats = Json::decode(file_get_contents($file), Json::TYPE_ARRAY);
            return implode(PHP_EOL, $stats['html']);
        }

        return '';
    }
}
