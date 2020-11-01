<?php

namespace Application\View\Helper;

use Laminas\I18n\View\Helper\Translate;
use Laminas\View\Helper\AbstractHelper;
use Laminas\View\Renderer\PhpRenderer;

class Count extends AbstractHelper
{
    public function __invoke(int $count, ?int $new = null): string
    {
        /** @var PhpRenderer $view */
        $view = $this->view;
        /** @var Translate $translateHelper */
        $translateHelper = $view->getHelperPluginManager()->get('translate');

        if ($count === 0) {
            $result = $translateHelper('count 0');
        } else {
            $result = $count - $new;
            if ($new) {
                $result .= '+<span>' . $new . '</span>';
            }
        }

        return '<span class="count">(' . $result . ')</span>';
    }
}
