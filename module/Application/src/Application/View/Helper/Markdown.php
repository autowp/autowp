<?php

namespace Application\View\Helper;

use Laminas\View\Helper\AbstractHtmlElement;

class Markdown extends AbstractHtmlElement
{
    public function __invoke($text)
    {
        return \Michelf\Markdown::defaultTransform($text);
    }
}
