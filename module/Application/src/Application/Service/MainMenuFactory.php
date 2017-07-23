<?php

namespace Application\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\MainMenu;

class MainMenuFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get(\Application\Db\TableManager::class);
        return new MainMenu(
            $container->get('HttpRouter'),
            $container->get(\Application\Language::class),
            $container->get('longCache'),
            $container->get('Config')['hosts'],
            $container->get('MvcTranslator'),
            $container->get(\Application\LanguagePicker::class),
            $container->get(\Autowp\Message\MessageService::class),
            $container->get(\Application\Model\Categories::class),
            $tables->get('pages')
        );
    }
}
