<?php

namespace Application\Service;

use Zend\Mail;

use Autowp\User\Auth\Adapter\Login as LoginAuthAdapter;
use Autowp\User\Model\DbTable\User;
use Autowp\User\Model\DbTable\User\PasswordRemind as UserPasswordRemind;
use Autowp\User\Model\DbTable\User\Remember as UserRemember;
use Autowp\User\Model\DbTable\User\Row as UserRow;

use Application\Model\DbTable\Comment\Message as CommentMessage;
use Application\Model\DbTable\Picture;
use Application\Service\SpecificationsService;

use DateTime;
use Exception;

use Zend_Db_Expr;

use Autowp\Image;

class UsersService
{
    /**
     * @var User
     */
    private $table;

    /**
     * @var string
     */
    private $salt = null;

    private $emailSalt = null;

    /**
     * @var array
     */
    private $hosts = [];

    private $translator;

    private $transport;

    /**
     * @var SpecificationsService
     */
    private $specsService = null;

    /**
     * @var Image\Storage
     */
    private $imageStorage;

    /**
     * @return User
     */
    private function getTable()
    {
        return $this->table
            ? $this->table
            : $this->table = new User();
    }

    public function __construct(
        array $options,
        array $hosts,
        $translator,
        $transport,
        SpecificationsService $specsService,
        Image\Storage $imageStorage
    ) {

        $this->salt = $options['salt'];
        $this->emailSalt = $options['emailSalt'];

        $this->hosts = $hosts;
        $this->translator = $translator;
        $this->transport = $transport;
        $this->specsService = $specsService;
        $this->imageStorage = $imageStorage;
    }

    /**
     * @param string $password
     * @return string
     */
    private function passwordHashExpr($password)
    {
        if (strlen($password) <= 0) {
            throw new Exception("Password cannot be empty");
        }

        $db = $this->getTable()->getAdapter();

        return 'MD5(CONCAT(' . $db->quote($this->salt) . ', ' . $db->quote($password) . '))';
    }

    /**
     * @param string $email
     * @return string
     */
    private function emailCheckCode($email)
    {
        return md5($this->emailSalt. $email . microtime());
    }

    /**
     * @param string $language
     * @throws Exception
     * @return array
     */
    private function getHostOptions($language)
    {
        if (! isset($this->hosts[$language])) {
            throw new Exception("Host with language `$language` is not supported");
        }

        return $this->hosts[$language];
    }

    /**
     * @param array $values
     * @param string $hostname
     */
    public function addUser(array $values, $language)
    {
        $host = $this->getHostOptions($language);

        $emailCheckCode = $this->emailCheckCode($values['email']);

        $table = $this->getTable();
        $db = $table->getAdapter();

        $passwordExpr = $this->passwordHashExpr($values['password']);
        $ipExpr = $db->quoteInto('INET6_ATON(?)', $values['ip']);

        $user = $table->createRow([
            'login'            => null,
            'e_mail'           => null,
            'password'         => new Zend_Db_Expr($passwordExpr),
            'email_to_check'   => $values['email'],
            'hide_e_mail'      => 1,
            'email_check_code' => $emailCheckCode,
            'name'             => $values['name'],
            'reg_date'         => new Zend_Db_Expr('NOW()'),
            'last_online'      => new Zend_Db_Expr('NOW()'),
            'timezone'         => $host['timezone'],
            'last_ip'          => new Zend_Db_Expr($ipExpr),
            'language'         => $language
        ]);
        $user->save();

        $this->specsService->refreshUserConflicts($user->id);

        $this->sendRegistrationConfirmEmail($user, $host['hostname']);

        $this->updateUserVoteLimit($user->id);

        return $user;
    }

    /**
     * @param UserRow $user
     * @param string $email
     * @param string $language
     */
    public function changeEmailStart(UserRow $user, $email, $language)
    {
        $host = $this->getHostOptions($language);

        $emailCheckCode = $this->emailCheckCode($email);

        $user->email_to_check = $email;
        $user->email_check_code = $emailCheckCode;
        $user->save();

        $this->sendChangeConfirmEmail($user, $host['hostname']);
    }

    /**
     * @param string $code
     * @return boolean|UserRow
     */
    public function emailChangeFinish($code)
    {
        $userTable = $this->getTable();
        $user = $userTable->fetchRow(
            $userTable->select(true)
                ->where('not deleted')
                ->where('email_check_code = ?', (string)$code)
                ->where('LENGTH(email_check_code)')
                ->where('LENGTH(email_to_check)')
        );

        if (! $user) {
            return false;
        }

        $user->setFromArray([
            'e_mail'           => $user->email_to_check,
            'email_check_code' => null,
            'email_to_check'   => null
        ]);
        $user->save();

        return $user;
    }

    /**
     * @param UserRow $user
     * @param string $hostname
     */
    public function sendRegistrationConfirmEmail(UserRow $user, $hostname)
    {
        if ($user->email_to_check && $user->email_check_code) {
            $values = [
                'email' => $user->email_to_check,
                'name'  => $user->name,
                'url'   => 'http://'.$hostname.'/account/emailcheck/' . $user->email_check_code
            ];

            $subject = $this->translator->translate('users/registration/email-confirm-subject');
            $message = $this->translator->translate('users/registration/email-confirm-message');

            $subject = sprintf($subject, $hostname);
            $message = sprintf(
                $message,
                'http://'.$hostname.'/',
                $values['email'],
                $values['url'],
                $hostname
            );

            $mail = new Mail\Message();
            $mail
                ->setEncoding('utf-8')
                ->setFrom('no-reply@autowp.ru', 'robot autowp.ru')
                ->setBody($message)
                ->addTo($values['email'], $values['name'])
                ->setSubject($subject);

            $this->transport->send($mail);
        }
    }

    /**
     * @param UserRow $user
     * @param string $hostname
     */
    public function sendChangeConfirmEmail(UserRow $user, $hostname)
    {
        if ($user->email_to_check && $user->email_check_code) {
            $values = [
                'email' => $user->email_to_check,
                'name'  => $user->name,
                'url'   => 'http://'.$hostname.'/account/emailcheck/' . $user->email_check_code
            ];

            $subject = $this->translator->translate('users/change-email/confirm-subject');
            $message = $this->translator->translate('users/change-email/confirm-message');

            $subject = sprintf($subject, $hostname);
            $message = sprintf(
                $message,
                $hostname,
                $values['email'],
                $values['url']
            );

            $mail = new Mail\Message();
            $mail
                ->setEncoding('utf-8')
                ->setFrom('no-reply@autowp.ru', 'robot autowp.ru')
                ->setBody($message)
                ->addTo($values['email'], $values['name'])
                ->setSubject($subject);

            $this->transport->send($mail);
        }
    }

    /**
     * @param string $login
     * @param string $password
     * @return LoginAuthAdapter
     */
    public function getAuthAdapterLogin($login, $password)
    {
        return new LoginAuthAdapter(
            $login,
            $this->passwordHashExpr($password)
        );
    }

    public function updateUsersVoteLimits()
    {
        $userTable = $this->getTable();
        $db = $userTable->getAdapter();

        $ids = $db->fetchCol(
            $db->select()
                ->from($userTable->info('name'), 'id')
                ->where('not deleted')
                ->where('last_online > DATE_SUB(NOW(), INTERVAL 3 MONTH)')
        );

        $affected = 0;
        foreach ($ids as $id) {
            $this->updateUserVoteLimit($id);
            $affected++;
        }

        return $affected;
    }

    public function updateUserVoteLimit($userId)
    {
        $userRow = $this->getTable()->find($userId)->current();
        if (! $userRow) {
            return false;
        }

        $default = 10;

        $commentTable = new CommentMessage();
        $db = $commentTable->getAdapter();

        $avgVote = $db->fetchOne(
            $db->select()
                ->from($commentTable->info('name'), new Zend_Db_Expr('avg(vote)'))
                ->where('author_id = ?', $userRow->id)
                ->where('vote <> 0')
        );

        $age = 0;
        $regDate = $userRow->getDateTime('reg_date');
        if ($regDate) {
            $now = new DateTime();
            $diff = $now->getTimestamp() - $regDate->getTimestamp();
            $age = ((($diff / 60) / 60) / 24) / 365;
        }

        $pictureTable = new Picture();
        $db = $pictureTable->getAdapter();
        $picturesExists = $db->fetchOne(
            $db->select()
                ->from($pictureTable->info('name'), [new Zend_Db_Expr('COUNT(1)')])
                ->where('owner_id = ?', $userRow->id)
                ->where('status = ?', Picture::STATUS_ACCEPTED)
        );

        $value = round($default + $avgVote + $age + $picturesExists / 100);
        if ($value < 0) {
            $value = 0;
        }

        $userRow->votes_per_day = $value;
        $userRow->save();
    }

    public function restoreVotes()
    {
        $this->getTable()->update([
            'votes_left' => new Zend_Db_Expr('votes_per_day')
        ], [
            'votes_left < votes_per_day',
            'not deleted'
        ]);
    }

    public function setPassword(UserRow $user, $password)
    {
        $uTable = $this->getTable();
        $passwordExpr = $this->passwordHashExpr($password);

        $user->password = new Zend_Db_Expr($passwordExpr);
        $user->save();
    }

    public function createRememberToken($userId)
    {
        $table = new UserRemember();

        do {
            $token = md5($this->salt . microtime());
            $row = $table->fetchRow([
                'token = ?' => $token
            ]);
        } while ($row);

        $table->insert([
            'user_id' => $userId,
            'token'   => $token,
            'date'    => new Zend_Db_Expr('NOW()')
        ]);

        return $token;
    }

    public function createRestorePasswordToken($userId)
    {
        $uprTable = new UserPasswordRemind();

        do {
            $code = md5($this->salt . uniqid());
            $exists = (bool)$uprTable->find($code)->current();
        } while ($exists);

        $uprTable->insert([
            'user_id' => $userId,
            'hash'    => $code,
            'created' => new Zend_Db_Expr('NOW()')
        ]);

        return $code;
    }

    public function checkPassword($userId, $password)
    {
        $passwordExpr = $this->passwordHashExpr($password);

        return (bool)$this->getTable()->fetchRow([
            'id = ?'       => (int)$userId,
            'password = ?' => new Zend_Db_Expr($passwordExpr)
        ]);
    }

    public function deleteUnused()
    {
        $table = $this->getTable();
        $db = $table->getAdapter();

        $rows = $table->fetchAll(
            $table->select(true)
                ->where('users.last_online < DATE_SUB(NOW(), INTERVAL 2 YEAR)')
                ->where('users.role = ?', 'user')
                ->order('users.id')
                ->joinLeft('attrs_user_values', 'users.id = attrs_user_values.user_id', null)
                ->where('attrs_user_values.user_id is null')
                ->joinLeft('comments_messages', 'users.id = comments_messages.author_id', null)
                ->where('comments_messages.author_id is null')
                ->joinLeft('forums_topics', 'users.id = forums_topics.author_id', null)
                ->where('forums_topics.author_id is null')
                ->joinLeft('pictures', 'users.id = pictures.owner_id', null)
                ->where('pictures.owner_id is null')
                ->joinLeft('voting_variant_vote', 'users.id = voting_variant_vote.user_id', null)
                ->where('voting_variant_vote.user_id is null')
                ->joinLeft(['pmf' => 'personal_messages'], 'users.id = pmf.from_user_id', null)
                ->where('pmf.from_user_id is null')
                ->joinLeft(['pmt' => 'personal_messages'], 'users.id = pmt.to_user_id', null)
                ->where('pmt.to_user_id is null')
                ->limit(1000)
        );

        foreach ($rows as $row) {
            print 'Delete ' . $row->id . ' ' . $row->name . ' ' . PHP_EOL;

            $this->delete($row->id);
        }
    }

    private function delete($userId)
    {
        $table = $this->getTable();
        $db = $table->getAdapter();

        $row = $table->find($userId)->current();
        if (! $row) {
            return;
        }

        if ($row->img) {
            $imageId = $row->img;
            $row->img = null;
            $row->save();

            $this->imageStorage->removeImage($imageId);
        }

        $db->delete('log_events_user', [
            'user_id = ?' => $row->id
        ]);

        $row->delete();
    }
}
