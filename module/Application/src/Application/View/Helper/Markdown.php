<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHtmlElement;

class Markdown extends AbstractHtmlElement
{
    public function __invoke($text)
    {
        return \Michelf\Markdown::defaultTransform($text);
    }
}