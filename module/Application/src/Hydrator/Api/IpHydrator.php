<?php

namespace Application\Hydrator\Api;

use Autowp\Traffic\TrafficControl;
use Autowp\User\Model\User;
use Casbin\Enforcer;
use Exception;
use Laminas\Hydrator\Exception\InvalidArgumentException;
use Laminas\Hydrator\Strategy\DateTimeFormatterStrategy;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Stdlib\ArrayUtils;
use Traversable;

use function gethostbyaddr;
use function is_array;

class IpHydrator extends AbstractRestHydrator
{
    private int $userId = 0;

    private ?string $userRole;

    private Enforcer $acl;

    private TrafficControl $trafficControl;

    private User $userModel;

    public function __construct(ServiceLocatorInterface $serviceManager)
    {
        parent::__construct();

        $this->acl            = $serviceManager->get(Enforcer::class);
        $this->trafficControl = $serviceManager->get(TrafficControl::class);
        $this->userModel      = $serviceManager->get(User::class);

        $strategy = new Strategy\User($serviceManager);
        $this->addStrategy('user', $strategy);

        $strategy = new DateTimeFormatterStrategy();
        $this->addStrategy('up_to', $strategy);
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
     * @param int|null $userId
     */
    public function setUserId($userId = null): self
    {
        $this->userId = (int) $userId;

        //$this->getStrategy('content')->setUser($user);
        //$this->getStrategy('replies')->setUser($user);

        return $this;
    }

    /**
     * @param mixed $ip
     * @throws Exception
     */
    public function extract($ip): array
    {
        $result = [
            'address' => $ip,
        ];
        if ($this->filterComposite->filter('hostname')) {
            $result['hostname'] = gethostbyaddr($ip);
        }

        if ($this->filterComposite->filter('blacklist')) {
            $canView = false;
            $role    = $this->getUserRole();
            if ($role) {
                $canView = $this->acl->enforce($role, 'global', 'moderate');
            }

            if ($canView) {
                $result['blacklist'] = null;
                $ban                 = $this->trafficControl->getBanInfo($ip);
                if ($ban) {
                    $user         = $this->userModel->getRow((int) $ban['by_user_id']);
                    $ban['user']  = $user ? $this->extractValue('user', $user) : null;
                    $ban['up_to'] = $this->extractValue('up_to', $ban['up_to']);

                    $result['blacklist'] = $ban;
                }
            }
        }

        if ($this->filterComposite->filter('rights')) {
            $canBan = false;

            $role = $this->getUserRole();
            if ($role) {
                $canBan = $this->acl->enforce($role, 'user', 'ban');
            }

            $result['rights'] = [
                'add_to_blacklist'      => $canBan,
                'remove_from_blacklist' => $canBan,
            ];

            /*if ($canBan) {
                $this->banForm->setAttribute('action', $this->url()->fromRoute('ban/ban-ip', [
                    'ip' => inet_ntop($picture['ip'])
                ]));
                $this->banForm->populateValues([
                    'submit' => 'ban/ban'
                ]);
            }*/
        }

        return $result;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param object $object
     * @throws Exception
     */
    public function hydrate(array $data, $object): object
    {
        throw new Exception("Not supported");
    }

    private function getUserRole(): ?string
    {
        if (! $this->userId) {
            return null;
        }

        if (! isset($this->userRole)) {
            $this->userRole = $this->userModel->getUserRole($this->userId);
        }

        return $this->userRole;
    }
}
