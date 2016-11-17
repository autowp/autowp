<?php

namespace Application\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\MainMenu;

class MainMenuFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new MainMenu(
            $container->get('HttpRouter'),
            $container->get(\Application\Language::class),
            $container->get('longCache'),
            $container->get('Config')['hosts'],
            $container->get('MvcTranslator'),
            $container->get(\Application\LanguagePicker::class),
            $container->get(\Application\Model\Message::class)
        );
    }
}
