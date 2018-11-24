<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHelper;

use Exception;

class Breadcrumbs extends AbstractHelper
{
    private $data = [];

    public function reset()
    {
        $this->data = [];
    }

    public function __invoke($url = null, $name = null, $placement = 'append')
    {
        if ($url || $name) {
            $node = ['url' => $url, 'name' => $name];
            switch ($placement) {
                case 'append':
                    $this->data[] = $node;
                    break;
                case 'prepend':
                    array_unshift($this->data, $node);
                    break;
            }
        }

        return $this;
    }

    public function __toString()
    {
        try {
            $items = [];
            foreach ($this->data as $node) {
                $name = $node['name'];
                $url = $node['url'];

                if ($url) {
                    /* @phan-suppress-next-line PhanUndeclaredMethod */
                    $items[] = '<li class="breadcrumb-item">'.$this->view->htmlA(['href' => $url], $name).'</li>';
                } else {
                    /* @phan-suppress-next-line PhanUndeclaredMethod */
                    $items[] = '<li class="breadcrumb-item">'.$this->view->escapeHtml($name).'</li>';
                }
            }

            array_pop($items);

            if (! $items) {
                return '';
            }

            return  '<nav aria-label="breadcrumb"><ol class="breadcrumb">'. implode($items). '</ol></nav>';
        } catch (Exception $e) {
            print $e->getMessage();
        }
    }
}
