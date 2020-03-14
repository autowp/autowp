<?php

namespace Application\Controller\Plugin;

use Application\Model\Log as Model;
use Exception;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;

class Log extends AbstractPlugin
{
    private Model $log;

    public function __construct(Model $log)
    {
        $this->log = $log;
    }

    public function __invoke(string $message, array $objects): void
    {
        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $user = $this->getController()->user()->get();
        if (! $user) {
            throw new Exception('User id not detected');
        }

        $this->log->addEvent($user['id'], $message, $objects);
    }
}
