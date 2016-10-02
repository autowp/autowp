<?php

namespace Application\Auth\Adapter;

use Zend_Auth_Adapter_Exception;
use Zend_Auth_Adapter_Interface;
use Zend_Auth_Result;

use Application\Model\DbTable\User;

class Remember implements Zend_Auth_Adapter_Interface
{
    /**
     * $_credential - Credential values
     *
     * @var string
     */
    protected $_credential = null;

    /**
     * $_authenticateResultInfo
     *
     * @var array
     */
    protected $_authenticateResultInfo = null;

    public function authenticate()
    {
        $this->_authenticateSetup();

        $userTable = new User();

        $userRow = $userTable->fetchRow(
            $userTable->select(true)
                ->join('user_remember', 'users.id=user_remember.user_id', null)
                ->where('user_remember.token = ?', (string)$this->_credential)
                ->where('not users.deleted')
        );

        if (!$userRow) {
            $this->_authenticateResultInfo['code'] = Zend_Auth_Result::FAILURE_IDENTITY_NOT_FOUND;
            $this->_authenticateResultInfo['messages'][] = 'A record with the supplied identity could not be found.';
        } else {
            $this->_authenticateResultInfo['code'] = Zend_Auth_Result::SUCCESS;
            $this->_authenticateResultInfo['identity'] = (int)$userRow->id;
            $this->_authenticateResultInfo['messages'][] = 'Authentication successful.';
        }

        return $this->_authenticateCreateAuthResult();
    }

    /**
     * _authenticateSetup() - This method abstracts the steps involved with
     * making sure that this adapter was indeed setup properly with all
     * required pieces of information.
     *
     * @throws Zend_Auth_Adapter_Exception - in the event that setup was not done properly
     * @return true
     */
    protected function _authenticateSetup()
    {
        $exception = null;

        if ($this->_credential === null) {
            $exception = 'A credential value was not provided prior to authentication with Zend_Auth_Adapter_DbTable.';
        }

        if (null !== $exception) {
            throw new Zend_Auth_Adapter_Exception($exception);
        }

        $this->_authenticateResultInfo = [
            'code'     => Zend_Auth_Result::FAILURE,
            'identity' => null,
            'messages' => []
        ];

        return true;
    }

    /**
     * _authenticateCreateAuthResult() - Creates a Zend_Auth_Result object from
     * the information that has been collected during the authenticate() attempt.
     *
     * @return Zend_Auth_Result
     */
    protected function _authenticateCreateAuthResult()
    {
        return new Zend_Auth_Result(
            $this->_authenticateResultInfo['code'],
            $this->_authenticateResultInfo['identity'],
            $this->_authenticateResultInfo['messages']
        );
    }

    /**
     * setCredential() - set the credential value to be used
     *
     * @param  string $credential
     * @return Remember Provides a fluent interface
     */
    public function setCredential($credential)
    {
        $this->_credential = $credential;
        return $this;
    }
}