<?php

namespace Application\Hydrator\Api;

use Application\Model\Picture;
use Application\Model\UserAccount;
use ArrayAccess;
use Autowp\Commons\Db\Table\Row;
use Autowp\User\Model\User;
use Casbin\Enforcer;
use Casbin\Exceptions\CasbinException;
use DateInterval;
use DateTime;
use Exception;
use Laminas\Hydrator\Exception\InvalidArgumentException;
use Laminas\Hydrator\Strategy\DateTimeFormatterStrategy;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Stdlib\ArrayUtils;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Traversable;

use function hash;
use function inet_ntop;
use function is_array;
use function md5;
use function sprintf;
use function strtolower;
use function trim;
use function urlencode;

class UserHydrator extends AbstractRestHydrator
{
    protected int $userId = 0;

    private ?string $userRole;

    private Enforcer $acl;

    private User $userModel;

    private UserAccount $userAccount;

    private Picture $picture;

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function __construct(ServiceLocatorInterface $serviceManager)
    {
        parent::__construct();

        $this->acl         = $serviceManager->get(Enforcer::class);
        $this->userModel   = $serviceManager->get(User::class);
        $this->userAccount = $serviceManager->get(UserAccount::class);
        $this->picture     = $serviceManager->get(Picture::class);

        $strategy = new DateTimeFormatterStrategy();
        $this->addStrategy('last_online', $strategy);
        $this->addStrategy('reg_date', $strategy);
        $this->addStrategy('rename_date', $strategy);

        $strategy = new Strategy\Image($serviceManager); // @phpstan-ignore-line
        $this->addStrategy('image', $strategy);

        $strategy = new Strategy\Image($serviceManager); // @phpstan-ignore-line
        $this->addStrategy('img', $strategy);

        $strategy = new Strategy\Image($serviceManager); // @phpstan-ignore-line
        $this->addStrategy('avatar', $strategy);
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
     * @throws Exception
     */
    public function extract($object): ?array
    {
        $deleted = (bool) $object['deleted'];

        $isMe = (int) $object['id'] === $this->userId;

        if ($deleted) {
            $user = [
                'id'       => null,
                'name'     => null,
                'deleted'  => true,
                'url'      => null,
                'longAway' => false,
                'green'    => false,
            ];
        } else {
            $longAway   = false;
            $lastOnline = Row::getDateTimeByColumnType('timestamp', $object['last_online']);
            if ($lastOnline) {
                $date = new DateTime();
                $date->sub(new DateInterval('P6M'));
                if ($date > $lastOnline) {
                    $longAway = true;
                }
            } else {
                $longAway = true;
            }

            $isGreen = $object['role'] && $this->acl->enforce($object['role'], 'status', 'be-green');

            $user = [
                'id'           => (int) $object['id'],
                'name'         => $object['name'],
                'deleted'      => false,
                'route'        => ['/users', $object['identity'] ? $object['identity'] : 'user' . $object['id']],
                'long_away'    => $longAway,
                'green'        => $isGreen,
                'identity'     => $object['identity'],
                'specs_weight' => (float) $object['specs_weight'],
            ];

            if ($this->filterComposite->filter('last_online')) {
                $lastOnline          = Row::getDateTimeByColumnType('timestamp', $object['last_online']);
                $user['last_online'] = $this->extractValue('last_online', $lastOnline);
            }

            if ($this->filterComposite->filter('reg_date')) {
                $regDate          = Row::getDateTimeByColumnType('timestamp', $object['reg_date']);
                $user['reg_date'] = $this->extractValue('reg_date', $regDate);
            }

            if ($this->filterComposite->filter('image')) {
                $user['image'] = $this->extractValue('image', [
                    'image' => $object['img'],
                ]);
            }

            $canViewEmail = $isMe;
            if (! $canViewEmail) {
                $canViewEmail = $this->isModer();
            }

            if ($canViewEmail && $this->filterComposite->filter('email')) {
                $user['email'] = $object['e_mail'];
            }

            $canViewLogin = $isMe;
            if (! $canViewLogin) {
                $canViewLogin = $this->isModer();
            }

            if ($canViewLogin && $this->filterComposite->filter('login')) {
                $user['login'] = $object['login'];
            }

            if ($this->filterComposite->filter('img')) {
                $user['img'] = $this->extractValue('img', [
                    'image' => $object['img'],
                ]);
            }

            if ($this->filterComposite->filter('avatar')) {
                $user['avatar'] = $this->extractValue('image', [
                    'image'  => $object['img'],
                    'format' => 'avatar',
                ]);
            }

            if ($this->filterComposite->filter('photo')) {
                $user['photo'] = $this->extractValue('image', [
                    'image'  => $object['img'],
                    'format' => 'photo',
                ]);
            }

            if ($this->filterComposite->filter('gravatar') && $object['e_mail']) {
                $user['gravatar'] = sprintf(
                    'https://www.gravatar.com/avatar/%s?s=70&d=%s&r=g',
                    hash('sha256', strtolower(trim($object['e_mail']))),
                    urlencode('https://www.autowp.ru/_.gif')
                );
            }

            if ($this->filterComposite->filter('gravatar_hash') && $object['e_mail']) {
                $user['gravatar_hash'] = md5($object['e_mail']);
            }

            if ($isMe && $this->filterComposite->filter('language')) {
                $user['language'] = $object['language'];
            }

            if ($isMe && $this->filterComposite->filter('timezone')) {
                $user['timezone'] = $object['timezone'];
            }

            if ($isMe && $this->filterComposite->filter('votes_left')) {
                $user['votes_left'] = (int) $object['votes_left'];
            }

            if ($isMe && $this->filterComposite->filter('votes_per_day')) {
                $user['votes_per_day'] = (int) $object['votes_per_day'];
            }

            if ($this->filterComposite->filter('is_moder')) {
                $user['is_moder'] = $this->acl->enforce($object['role'], 'global', 'moderate');
            }

            if ($this->filterComposite->filter('accounts')) {
                $user['accounts'] = $this->userAccount->getAccounts($object['id']);
            }

            if ($this->filterComposite->filter('pictures_added')) {
                $user['pictures_added'] = (int) $object['pictures_added'];
            }

            if ($this->filterComposite->filter('pictures_accepted_count')) {
                $user['pictures_accepted_count'] = $this->picture->getCount([
                    'user'   => $user['id'],
                    'status' => Picture::STATUS_ACCEPTED,
                ]);
            }

            if ($this->filterComposite->filter('last_ip')) {
                $canViewIp = false;
                $role      = $this->getUserRole();
                if ($role) {
                    $canViewIp = $this->acl->enforce($role, 'user', 'ip');
                }

                if ($canViewIp) {
                    $user['last_ip'] = inet_ntop($object['last_ip']);
                }
            }
        }

        return $user;
    }

    /**
     * @throws CasbinException
     * @throws Exception
     */
    private function isModer(): bool
    {
        $role = $this->getUserRole();
        if (! $role) {
            return false;
        }

        return $this->acl->enforce($role, 'global', 'moderate');
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

    public function setUserId(int $userId): self
    {
        if ($this->userId !== $userId) {
            $this->userId   = $userId;
            $this->userRole = null;
        }

        return $this;
    }

    /**
     * @throws Exception
     */
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
