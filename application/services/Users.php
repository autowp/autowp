<?php

class Application_Service_Users
{
    /**
     * @var Users
     */
    private $_table;

    /**
     * @var string
     */
    private $_salt = null;

    private $_emailSalt = null;

    /**
     * @return Users
     */
    private function _getTable()
    {
        return $this->_table
            ? $this->_table
            : $this->_table = new Users();
    }

    public function __construct(array $options)
    {
        $this->_salt = $options['salt'];
        $this->_emailSalt = $options['emailSalt'];
    }

    /**
     * @param string $password
     * @return string
     */
    private function _passwordHashExpr($password)
    {
        if (strlen($password) <= 0) {
            throw new Exception("Password cannot be empty");
        }

        $db = $this->_getTable()->getAdapter();

        return 'MD5(CONCAT(' . $db->quote($this->_salt) . ', ' . $db->quote($password) . '))';
    }

    /**
     * @param string $email
     * @return string
     */
    private function _emailCheckCode($email)
    {
        return md5($this->_emailSalt. $email . microtime());
    }

    /**
     * @param string $language
     * @throws Exception
     * @return array
     */
    private function _getHostOptions($language)
    {
        $hosts = Zend_Controller_Front::getInstance()->getParam('bootstrap')->getOption('hosts');
        if (!isset($hosts[$language])) {
            throw new Exception("Host with language `$language` is not supported");
        }

        return $hosts[$language];
    }

    /**
     * @param array $values
     * @param string $hostname
     */
    public function addUser(array $values, $language)
    {
        $host = $this->_getHostOptions($language);

        $emailCheckCode = $this->_emailCheckCode($values['email']);

        $table = $this->_getTable();
        $db = $table->getAdapter();

        $passwordExpr = $this->_passwordHashExpr($values['password']);
        $ipExpr = $db->quoteInto('INET6_ATON(?)', $values['ip']);

        $user = $table->createRow(array(
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
        ));
        $user->save();

        $service = new Application_Service_Specifications();
        $service->refreshUserConflicts($user->id);

        $this->sendRegistrationConfirmEmail($user, $host['hostname']);

        $this->updateUserVoteLimit($user->id);

        return $user;
    }

    /**
     * @param Users_Row $user
     * @param string $email
     * @param string $language
     */
    public function changeEmailStart(Users_Row $user, $email, $language)
    {
        $host = $this->_getHostOptions($language);

        $emailCheckCode = $this->_emailCheckCode($email);

        $user->email_to_check = $email;
        $user->email_check_code = $emailCheckCode;
        $user->save();

        $this->sendChangeConfirmEmail($user, $host['hostname']);
    }

    /**
     * @param string $code
     * @return boolean|Users_Row
     */
    public function emailChangeFinish($code)
    {
        $userTable = $this->_getTable();
        $user = $userTable->fetchRow(
            $userTable->select(true)
                ->where('not deleted')
                ->where('email_check_code = ?', (string)$code)
                ->where('LENGTH(email_check_code)')
                ->where('LENGTH(email_to_check)')
        );

        if (!$user) {
            return false;
        }

        $user->setFromArray(array(
            'e_mail'           => $user->email_to_check,
            'email_check_code' => null,
            'email_to_check'   => null
        ));
        $user->save();

        return $user;
    }

    /**
     * @param Users_Row $user
     * @param string $hostname
     */
    public function sendRegistrationConfirmEmail(Users_Row $user, $hostname)
    {
        if ($user->email_to_check && $user->email_check_code) {

            $values = array(
                'email' => $user->email_to_check,
                'name'  => $user->name,
                'url'   => 'http://'.$hostname.'/account/emailcheck/' . $user->email_check_code
            );

            $translate = Zend_Registry::get('Zend_Translate');

            $subject = $translate->translate('users/registration/email-confirm-subject');
            $message = $translate->translate('users/registration/email-confirm-message');

            $subject = sprintf($subject, $hostname);
            $message = sprintf(
                $message,
                'http://'.$hostname.'/',
                $values['email'],
                $values['url'],
                $hostname
            );

            $mail = new Zend_Mail('utf-8');
            $mail->setBodyText($message, 'utf-8')
                ->addTo($values['email'], $values['name'])
                ->setSubject($subject)
                ->send();
        }
    }

    /**
     * @param Users_Row $user
     * @param string $hostname
     */
    public function sendChangeConfirmEmail(Users_Row $user, $hostname)
    {
        if ($user->email_to_check && $user->email_check_code) {

            $values = array(
                'email' => $user->email_to_check,
                'name'  => $user->name,
                'url'   => 'http://'.$hostname.'/account/emailcheck/' . $user->email_check_code
            );

            $translate = Zend_Registry::get('Zend_Translate');

            $subject = $translate->translate('users/change-email/confirm-subject');
            $message = $translate->translate('users/change-email/confirm-message');

            $subject = sprintf($subject, $hostname);
            $message = sprintf(
                $message,
                $hostname,
                $values['email'],
                $values['url']
            );

            $mail = new Zend_Mail('utf-8');
            $mail->setBodyText($message)
                 ->setFrom('no-reply@autowp.ru', 'robot autowp.ru')
                 ->addTo($values['email'], $values['name'])
                 ->setSubject($subject)
                 ->send();
        }
    }

    /**
     * @param string $login
     * @param string $password
     * @return Project_Auth_Adapter_Login
     */
    public function getAuthAdapterLogin($login, $password)
    {
        return new Project_Auth_Adapter_Login(
            $login,
            $this->_passwordHashExpr($password)
        );
    }

    public function updateUsersVoteLimits()
    {
        $userTable = $this->_getTable();
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
        $userRow = $this->_getTable()->find($userId)->current();
        if (!$userRow) {
            return false;
        }

        $default = 10;

        $commentTable = new Comment_Message();
        $db = $commentTable->getAdapter();

        $avgVote = $db->fetchOne(
            $db->select()
                ->from($commentTable->info('name'), new Zend_Db_Expr('avg(vote)'))
                ->where('author_id = ?', $userRow->id)
                ->where('vote <> 0')
        );

        $age = 0;
        $regDate = $userRow->getDate('reg_date');
        if ($regDate) {
            $diff = Zend_Date::now()->sub($regDate)->toValue();
            $age = ((($diff / 60) / 60) / 24) / 365;
        }

        $pictureTable = new Picture();
        $db = $pictureTable->getAdapter();
        $picturesExists = $db->fetchOne(
            $db->select()
                ->from($pictureTable->info('name'), array(new Zend_Db_Expr('COUNT(1)')))
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
        $this->_getTable()->update(array(
            'votes_left' => new Zend_Db_Expr('votes_per_day')
        ), array(
            'votes_left < votes_per_day',
            'not deleted'
        ));
    }
    
    public function setPassword(Users_Row $user, $password)
    {
        $uTable = $this->_getTable();
        $passwordExpr = $this->_passwordHashExpr($password);
        
        $user->password = new Zend_Db_Expr($passwordExpr);
        $user->save();
    }
    
    public function createRememberToken($userId)
    {
        $table = new User_Remember();
        
        do {
            $token = md5($this->_salt . microtime());
            $row = $table->fetchRow(array(
                'token = ?' => $token
            ));
        } while ($row);
        
        $table->insert(array(
            'user_id' => $userId,
            'token'   => $token,
            'date'    => new Zend_Db_Expr('NOW()')
        ));
        
        return $token;
    }
    
    public function createRestorePasswordToken($userId)
    {
        $uprTable = new User_Password_Remind();
        
        do {
            $code = md5($this->_salt . uniqid());
            $exists = (bool)$uprTable->find($code)->current();
        } while ($exists);
        
        $uprTable->insert(array(
            'user_id' => $userId,
            'hash'    => $code,
            'created' => new Zend_Db_Expr('NOW()')
        ));
        
        return $code;
    }
}