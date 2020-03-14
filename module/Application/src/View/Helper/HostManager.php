<?php

namespace Application\View\Helper;

use Application\HostManager as AppHostManager;
use Laminas\View\Helper\AbstractHelper;

class HostManager extends AbstractHelper
{
    private AppHostManager $hostManager;

    public function __construct(AppHostManager $hostManager)
    {
        $this->hostManager = $hostManager;
    }

    public function __invoke(): AppHostManager
    {
        return $this->hostManager;
    }
}
