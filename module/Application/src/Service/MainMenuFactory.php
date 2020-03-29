<?php

namespace Application\Service;

use Application\Language;
use Application\LanguagePicker;
use Application\MainMenu;
use Application\Model\Categories;
use Autowp\Message\MessageService;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class MainMenuFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): MainMenu
    {
        $tables = $container->get('TableManager');
        return new MainMenu(
            $container->get('HttpRouter'),
            $container->get(Language::class),
            $container->get('longCache'),
            $container->get('Config')['hosts'],
            $container->get('MvcTranslator'),
            $container->get(LanguagePicker::class),
            $container->get(MessageService::class),
            $container->get(Categories::class),
            $tables->get('pages')
        );
    }
}
