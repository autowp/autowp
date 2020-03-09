<?php

namespace Application\View\Helper;

use Application\HostManager as AppHostManager;
use Laminas\View\Helper\AbstractHelper;

class HostManager extends AbstractHelper
{
    /** @var AppHostManager */
    private $hostManager;

    public function __construct(AppHostManager $hostManager)
    {
        $this->hostManager = $hostManager;
    }

    public function __invoke()
    {
        return $this->hostManager;
    }
}
