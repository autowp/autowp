<?php

namespace Application\Auth\Adapter;

use Zend_Auth_Adapter_Exception;
use Zend_Auth_Adapter_Interface;
use Zend_Auth_Result;

use Users;

class Login implements Zend_Auth_Adapter_Interface
{
    /**
     * $_identity - Identity value
     *
     * @var string
     */
    protected $_identity = null;

    /**
     * $_credential - Credential values
     *
     * @var string
     */
    protected $_credentialExpr = null;

    /**
     * $_authenticateResultInfo
     *
     * @var array
     */
    protected $_authenticateResultInfo = null;

    public function __construct($identity, $credentialExpr)
    {
        $this->_identity = (string)$identity;
        $this->_credentialExpr = (string)$credentialExpr;
    }

    public function authenticate()
    {
        $this->_authenticateSetup();

        $userTable = new Users();
        $filter = [
            'not deleted',
            'password = ' . $this->_credentialExpr
        ];
        if (mb_strpos($this->_identity, '@') !== false) {
            $filter['e_mail = ?'] = (string)$this->_identity;
        } else {
            $filter['login = ?'] = (string)$this->_identity;
        }

        $userRow = $userTable->fetchRow($filter);

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

        if ($this->_identity == '') {
            $exception = 'A value for the identity was not provided prior to authentication with Zend_Auth_Adapter_DbTable.';
        } elseif ($this->_credentialExpr === null) {
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
}