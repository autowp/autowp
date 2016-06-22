<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHtmlElement;

class HtmlImg extends AbstractHtmlElement
{
    public function __invoke($attribs)
    {
        if (!is_array($attribs)) {
            $attribs = ['src' => $attribs];
        }

        if (!isset($attribs['alt'])) {
            $attribs['alt'] = '';
        }

        if (isset($attribs['shuffle']) && $attribs['shuffle']) {
            unset($attribs['shuffle']);
            $attribs = $this->shuffleAttribs($attribs);
        }

        return '<img' . $this->htmlAttribs($attribs) . $this->getClosingBracket();
    }

    private function shuffleAttribs($attribs)
    {
        $keys = array_keys($attribs);
        shuffle($keys);
        return array_merge(array_flip($keys), $attribs);
    }
}