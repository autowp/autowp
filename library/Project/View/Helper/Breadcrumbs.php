<?php

class Project_View_Helper_Breadcrumbs extends Zend_View_Helper_Abstract
{
    protected $_data = array();

    public function reset()
    {
        $this->_data = $this->_params = array();
    }

    public function breadcrumbs($url = null, $name = null, $placement = 'append')
    {
        if ($url || $name)
        {
            $node = array('url' => $url, 'name' => $name);
            switch ($placement)
            {
                case 'append':
                    $this->_data[] = $node;
                    break;
                case 'prepend':
                    array_unshift($this->_data, $node);
                    break;
            }

        }

        return $this;
    }

    public function __toString()
    {
        try {
            $a = array();
            foreach ($this->_data as $node) {

                $name = $node['name'];
                $url = $node['url'];

                if ($url)
                    $a[] = '<li>'.$this->view->htmlA(array('href' => $url), $name).'</li>';
                else
                    $a[] = '<li>'.$this->view->escape($name).'</li>';
            }

            array_pop($a);

            if (!$a) {
                return '';
            }

            $endTag = ' />';
            if (($this->view instanceof Zend_View_Abstract) && !$this->view->doctype()->isXhtml()) {
                $endTag= '>';
            }

            return  '<ul class="breadcrumb">'.
                        /*'<strong style="margin-right:8px">'.
                            $this->view->escape($this->view->translate('breadcrumbs/title')) .
                        ':</strong>'.*/
                        implode($a).
                    '</ul>';
        } catch (Exception $e) {
            print $e->getMessage();
        }
    }

}