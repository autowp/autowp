<?php

namespace Application\Hydrator\Api;

use ArrayAccess;
use Autowp\User\Model\User;
use Exception;
use Laminas\Hydrator\Exception\InvalidArgumentException;
use Laminas\Permissions\Acl\Acl;
use Laminas\Router\Http\TreeRouteStack;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Stdlib\ArrayUtils;
use Traversable;

use function is_array;
use function urlencode;

class TrafficHydrator extends AbstractRestHydrator
{
    protected int $userId;

    private Acl $acl;

    private TreeRouteStack $router;

    private User $userModel;

    public function __construct(ServiceLocatorInterface $serviceManager)
    {
        parent::__construct();

        $this->router    = $serviceManager->get('HttpRouter');
        $this->acl       = $serviceManager->get(Acl::class);
        $this->userModel = $serviceManager->get(User::class);

        $strategy = new Strategy\User($serviceManager);
        $this->addStrategy('ban_user', $strategy);
    }

    /**
     * @param  array|Traversable $options
     * @throws InvalidArgumentException
     */
    public function setOptions($options): self
    {
        parent::setOptions($options);

        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        } elseif (! is_array($options)) {
            throw new InvalidArgumentException(
                'The options parameter must be an array or a Traversable'
            );
        }

        if (isset($options['user_id'])) {
            $this->setUserId($options['user_id']);
        }

        return $this;
    }

    /**
     * @param array|ArrayAccess $object
     */
    public function extract($object): ?array
    {
        /*$row['users'] = $users->fetchAll([
            'last_ip = inet_aton(inet6_ntoa(?))' => $row['ip']
        ]);*/

        if ($object['ban']) {
            $object['ban']['user'] = null;
            if ($object['ban']['by_user_id']) {
                $user = $this->userModel->getRow((int) $object['ban']['by_user_id']);
                if ($user) {
                    $object['ban']['user'] = $this->extractValue('ban_user', $user);
                }
            }
        }

        $object['whois_url'] = 'http://nic.ru/whois/?query=' . urlencode($object['ip']);
        unset($object['ban']['ip']);

        return $object;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param object $object
     * @throws Exception
     */
    public function hydrate(array $data, $object)
    {
        throw new Exception("Not supported");
    }

    public function setUserId(int $userId): self
    {
        if ($this->userId !== $userId) {
            $this->userId = $userId;
        }

        return $this;
    }
}
