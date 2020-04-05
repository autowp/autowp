<?php

namespace Autowp\User\Controller\Plugin;

use ArrayObject;
use Autowp\User\Model\User as UserModel;
use Autowp\User\Service\OAuth;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Laminas\Permissions\Acl\Acl;

use function array_key_exists;
use function is_array;

class User extends AbstractPlugin
{
    private Acl $acl;

    private UserModel $userModel;

    private array $users = [];

    /** @var array|ArrayObject */
    private $user;

    private OAuth $oauth;

    public function __construct(Acl $acl, UserModel $userModel, OAuth $oauth)
    {
        $this->acl       = $acl;
        $this->userModel = $userModel;
        $this->oauth     = $oauth;
    }

    /**
     * @return array|ArrayObject|null
     */
    private function user(int $id)
    {
        if (! $id) {
            return null;
        }

        if (! array_key_exists($id, $this->users)) {
            $this->users[$id] = $this->userModel->getRow(['id' => (int) $id]);
        }

        return $this->users[$id];
    }

    /**
     * @param null|array|ArrayObject|int $user
     */
    public function __invoke($user = null): self
    {
        if ($user === null) {
            $user = $this->getLogedInUser();
        }

        if (! (is_array($user) || $user instanceof ArrayObject)) {
            $user = $this->user((int) $user);
        }

        $this->user = $user;

        return $this;
    }

    /**
     * @return array|ArrayObject|null
     */
    private function getLogedInUser()
    {
        $userID = $this->oauth->getUserID();
        if (! $userID) {
            return null;
        }

        return $this->user($userID);
    }

    public function logedIn(): bool
    {
        return (bool) $this->getLogedInUser();
    }

    /**
     * @return array|ArrayObject
     */
    public function get()
    {
        return $this->user ?? null;
    }

    public function isAllowed(string $resource, string $privilege): bool
    {
        return isset($this->user)
            && $this->user['role']
            && $this->acl->isAllowed($this->user['role'], $resource, $privilege);
    }

    public function inheritsRole(string $inherit): bool
    {
        return isset($this->user)
            && $this->user['role']
            && $this->acl->hasRole($inherit)
            && $this->acl->inheritsRole($this->user['role'], $inherit);
    }

    public function timezone(): string
    {
        return isset($this->user) && $this->user['timezone']
            ? $this->user['timezone']
            : 'UTC';
    }
}
