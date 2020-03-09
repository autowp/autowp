<?php

namespace Autowp\User\Controller\Plugin;

use ArrayObject;
use Autowp\User\Model\User as UserModel;
use Laminas\Authentication\AuthenticationService;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Laminas\Permissions\Acl\Acl;

use function array_key_exists;
use function is_array;

class User extends AbstractPlugin
{
    /** @var Acl */
    private $acl;

    /** @var UserModel */
    private $userModel;

    /** @var array */
    private $users = [];

    /** @var array|ArrayObject */
    private $user;

    public function __construct(Acl $acl, UserModel $userModel)
    {
        $this->acl       = $acl;
        $this->userModel = $userModel;
    }

    /**
     * @return array|ArrayObject
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
     * @param null|array|ArrayObject $user
     */
    public function __invoke($user = null): self
    {
        if ($user === null) {
            $user = $this->getLogedInUser();
        }

        if (! (is_array($user) || $user instanceof ArrayObject)) {
            $user = $this->user($user);
        }

        $this->user = $user;

        return $this;
    }

    /**
     * @return array|ArrayObject|null
     */
    private function getLogedInUser()
    {
        $auth = new AuthenticationService();

        if (! $auth->hasIdentity()) {
            return null;
        }

        return $this->user($auth->getIdentity());
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
        return $this->user;
    }

    public function isAllowed(string $resource, string $privilege): bool
    {
        return $this->user
            && $this->user['role']
            && $this->acl->isAllowed($this->user['role'], $resource, $privilege);
    }

    public function inheritsRole(string $inherit): bool
    {
        return $this->user
            && $this->user['role']
            && $this->acl->hasRole($inherit)
            && $this->acl->inheritsRole($this->user['role'], $inherit);
    }

    public function timezone(): string
    {
        return $this->user && $this->user['timezone']
            ? $this->user['timezone']
            : 'UTC';
    }
}
