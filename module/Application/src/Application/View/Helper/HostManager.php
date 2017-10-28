<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHelper;

use Application\HostManager as AppHostManager;

class HostManager extends AbstractHelper
{
    /**
     * @var AppHostManager
     */
    private $hostManager = null;

    public function __construct(AppHostManager $hostManager)
    {
        $this->hostManager = $hostManager;
    }

    public function __invoke()
    {
        return $this->hostManager;
    }
}
