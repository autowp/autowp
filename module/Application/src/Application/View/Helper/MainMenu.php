<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHtmlElement;
use Application\MainMenu as Model;

class MainMenu extends AbstractHtmlElement
{
    /**
     * @var Model
     */
    private $mainMenu;

    public function __construct(Model $mainMenu)
    {
        $this->mainMenu = $mainMenu;
    }

    public function __invoke($data = false)
    {
        $user = $this->view->user()->get();
        $menu = $this->mainMenu->getMenu($user ? $user : null);
        
        if ($data) {
            return $menu;
        }

        return $this->view->partial('application/main-menu', $menu);
    }
}
