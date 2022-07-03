<?php

declare(strict_types=1);

namespace Application\Validator\User;

use Autowp\User\Model\User;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

use function array_replace;

class EmailNotExistsFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): EmailNotExists
    {
        return new EmailNotExists(array_replace($options ? $options : [], [
            'userModel' => $container->get(User::class),
        ]));
    }
}
