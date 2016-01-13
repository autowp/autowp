<?php

class Project_View_Helper_HtmlImg extends Zend_View_Helper_HtmlElement
{
    public function htmlImg($attribs)
    {
        if (!is_array($attribs)) {
            $attribs = array('src' => $attribs);
        }

        if (!isset($attribs['alt'])) {
            $attribs['alt'] = '';
        }

        if (isset($attribs['shuffle']) && $attribs['shuffle']) {
            unset($attribs['shuffle']);
            $attribs = $this->_shuffleAttribs($attribs);
        }

        return '<img' . $this->_htmlAttribs($attribs) . $this->getClosingBracket();
    }

    private function _shuffleAttribs($attribs)
    {
        $keys = array_keys($attribs);
        shuffle($keys);
        return array_merge(array_flip($keys), $attribs);
    }
}