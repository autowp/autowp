<?php

namespace Application\Controller\Plugin;

use Application\Model\Log as Model;
use Autowp\User\Controller\Plugin\User;
use Exception;
use Laminas\Mvc\Controller\AbstractController;
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
        /** @var AbstractController $controller */
        $controller = $this->getController();
        /** @var User $userPlugin */
        $userPlugin = $controller->getPluginManager()->get('user');

        $user = $userPlugin->get();
        if (! $user) {
            throw new Exception('User id not detected');
        }

        $this->log->addEvent($user['id'], $message, $objects);
    }
}
