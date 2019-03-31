<?php

namespace Application\Service;

use Application\Language;
use Application\LanguagePicker;
use Application\Model\Categories;
use Autowp\Message\MessageService;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\MainMenu;

class MainMenuFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return MainMenu
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
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
