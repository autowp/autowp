<?php

namespace Application\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;

use Application\Model\Log as Model;

class Log extends AbstractPlugin
{
    /**
     * @var Model
     */
    private $log;

    public function __construct(Model $log)
    {
        $this->log = $log;
    }

    public function __invoke(string $message, array $objects)
    {
        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $user = $this->getController()->user()->get();
        if (! $user) {
            throw new \Exception('User id not detected');
        }

        $this->log->addEvent($user['id'], $message, $objects);
    }
}
