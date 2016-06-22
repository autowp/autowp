<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHelper;

class Acl extends AbstractHelper
{
    /**
     * @var \Application\Acl
     */
    private $acl;

    public function __construct(\Application\Acl $acl)
    {
        $this->acl = $acl;
    }

    /**
     * @return Acl
     */
    public function __invoke()
    {
        return $this->acl;
    }
}
