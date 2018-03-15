<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHtmlElement;

use Exception;

class HtmlPicture extends AbstractHtmlElement
{
    public function __invoke(array $attribs, array $sources)
    {
        $parts = [];

        foreach ($sources as $source) {
            $parts[] = $source['src'] . ' ' . $source['width'] . 'w';
        }

        if (count($sources)) {

            $attribs['srcset'] = implode(', ', $parts);

            $last = $sources[count($sources) - 1];
            $attribs['src'] = $source['src'];
        }

        return $this->view->htmlImg($attribs);
    }

}
