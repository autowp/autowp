<?php

namespace Autowp\User\View\Helper;

use ArrayObject;
use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;
use Zend\Authentication\AuthenticationService;
use Zend\View\Helper\AbstractHelper;
use Zend\View\Exception\InvalidArgumentException;
use Zend\Permissions\Acl\Acl;
use Autowp\Commons\Db\Table\Row;
use Autowp\User\Model\User as UserModel;

class User extends AbstractHelper
{
    /**
     * @var UserModel
     */
    private $userModel;

    /**
     * @var array
     */
    private $users = [];

    /**
     * @var array|ArrayObject|null
     */
    private $user = null;

    /**
     * @var Acl
     */
    private $acl;

    public function __construct(Acl $acl, UserModel $userModel)
    {
        $this->acl = $acl;
        $this->userModel = $userModel;
    }

    private function user($id)
    {
        if (! $id) {
            return null;
        }

        if (! isset($this->users[$id])) {
            $this->users[$id] = $this->userModel->getRow(['id' => (int)$id]);
        }

        return $this->users[$id];
    }

    public function __invoke($user = null)
    {
        if ($user === null) {
            $user = $this->getLogedInUser();
        }

        if (! ($user instanceof ArrayObject || is_array($user))) {
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
        return (bool)$this->getLogedInUser();
    }

    /**
     * @return array|ArrayObject|null
     */
    public function get()
    {
        return $this->user;
    }

    /**
     * @suppress PhanUndeclaredMethod
     */
    public function __toString()
    {
        try {
            $user = $this->user;

            if (! $user) {
                return '';
            }

            if ($user['deleted']) {
                return '<span class="muted"><i class="fa fa-user" aria-hidden="true"></i> ' .
                           /* @phan-suppress-next-line PhanUndeclaredMethod */
                           $this->view->escapeHtml($this->view->translate('deleted-user')) .
                       '</span>';
            }

            $url = $this->view->url('ng', [
                'path' => '',
                'only_return_path' => true,
            ]) . 'users/' . ($user['identity'] ? $user['identity'] : 'user' . $user['id']);

            $classes = ['user'];
            $lastOnline = Row::getDateTimeByColumnType('timestamp', $user['last_online']);
            if ($lastOnline) {
                $date = new DateTime();
                $date->sub(new DateInterval('P6M'));
                if ($date > $lastOnline) {
                    $classes[] = 'long-away';
                }
            } else {
                $classes[] = 'long-away';
            }

            if ($this->isAllowed('status', 'be-green')) {
                $classes[] = 'green-man';
            }

            $result =
                '<span class="' . implode(' ', $classes) . '">' .
                    '<i class="fa fa-user" aria-hidden="true"></i>&#xa0;' .
                    /* @phan-suppress-next-line PhanUndeclaredMethod */
                    $this->view->htmlA($url, $user['name']) .
                '</span>';
        } catch (Exception $e) {
            $result = $e->getMessage();

            print $e->getTraceAsString();
        }

        return $result;
    }

    /**
     * @suppress PhanUndeclaredMethod
     */
    public function avatar(): string
    {
        $user = $this->user;

        if (! $user) {
            return '';
        }

        if ($user['img']) {
            /* @phan-suppress-next-line PhanUndeclaredMethod */
            $image = $this->view->img($user['img'], [
                'format' => 'avatar',
            ])->__toString();

            if ($image) {
                return $image;
            }
        }

        if ($user['e_mail']) {
            // gravatar
            return $this->view->gravatar($user['e_mail'], [
                'img_size'    => 70,
                'default_img' => 'https://www.autowp.ru/_.gif'
            ])->__toString();
        }

        return '';
    }

    /**
     * @suppress PhanTypeArraySuspiciousNullable
     * @param string $resource
     * @param string $privilege
     * @return bool
     */
    public function isAllowed(string $resource, string $privilege): bool
    {
        return $this->user
            && $this->user['role']
            && $this->acl->isAllowed($this->user['role'], $resource, $privilege);
    }

    /**
     * @suppress PhanTypeArraySuspiciousNullable
     * @param string $inherit
     * @return bool
     */
    public function inheritsRole(string $inherit): bool
    {
        return $this->user
            && $this->user['role']
            && $this->acl->hasRole($inherit)
            && $this->acl->inheritsRole($this->user['role'], $inherit);
    }

    /**
     * @suppress PhanTypeArraySuspiciousNullable
     */
    public function timezone()
    {
        return $this->user && $this->user['timezone']
            ? $this->user['timezone']
            : 'UTC';
    }

    /**
     * @suppress PhanUndeclaredMethod
     * @param DateTime|null $time
     * @return string
     */
    public function humanTime(DateTime $time = null)
    {
        if ($time === null) {
            throw new InvalidArgumentException('Expected parameter $time was not provided.');
        }

        $tz = $this->timezone();

        $time->setTimezone(new DateTimeZone($tz));

        return $this->view->humanTime($time);
    }

    /**
     * @suppress PhanUndeclaredMethod
     * @param DateTime|null $time
     * @return string
     */
    public function humanDate(DateTime $time = null)
    {
        if ($time === null) {
            throw new InvalidArgumentException('Expected parameter $time was not provided.');
        }

        $tz = $this->timezone();

        $time->setTimezone(new DateTimeZone($tz));

        return $this->view->humanDate($time);
    }
}
