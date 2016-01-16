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
            'last_ip'          => new Zend_Db_Expr($ipExpr)
        ));
        $user->save();

        $service = new Application_Service_Specifications();
        $service->refreshUserConflicts($user->id);

        $user->updateVotesLimit();

        $this->sendRegistrationConfirmEmail($user, $host['hostname']);

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
}