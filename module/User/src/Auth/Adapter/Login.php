<?php

namespace Autowp\User\Auth\Adapter;

use Zend\Authentication\Adapter\AdapterInterface;
use Zend\Authentication\Result;
use Zend\Authentication\Adapter\Exception\InvalidArgumentException;

use Autowp\User\Model\DbTable\User;

class Login implements AdapterInterface
{
    /**
     * Identity value
     *
     * @var string
     */
    private $identity = null;

    /**
     * $_credential - Credential values
     *
     * @var string
     */
    private $credentialExpr = null;

    /**
     * @var array
     */
    private $authenticateResultInfo = null;

    public function __construct($identity, $credentialExpr)
    {
        $this->identity = (string)$identity;
        $this->credentialExpr = (string)$credentialExpr;
    }

    public function authenticate()
    {
        $this->authenticateSetup();

        $userTable = new User();
        $filter = [
            'not deleted',
            'password = ' . $this->credentialExpr
        ];
        if (mb_strpos($this->identity, '@') !== false) {
            $filter['e_mail = ?'] = (string)$this->identity;
        } else {
            $filter['login = ?'] = (string)$this->identity;
        }

        $userRow = $userTable->fetchRow($filter);

        if (! $userRow) {
            $this->authenticateResultInfo['code'] = Result::FAILURE_IDENTITY_NOT_FOUND;
            $this->authenticateResultInfo['messages'][] = 'A record with the supplied identity could not be found.';
        } else {
            $this->authenticateResultInfo['code'] = Result::SUCCESS;
            $this->authenticateResultInfo['identity'] = (int)$userRow['id'];
            $this->authenticateResultInfo['messages'][] = 'Authentication successful.';
        }

        return $this->authenticateCreateAuthResult();
    }

    /**
     * authenticateSetup() - This method abstracts the steps involved with
     * making sure that this adapter was indeed setup properly with all
     * required pieces of information.
     *
     * @throws InvalidArgumentException - in the event that setup was not done properly
     * @return true
     */
    private function authenticateSetup()
    {
        $exception = null;

        if ($this->identity == '') {
            $exception = 'A value for the identity was not provided prior to authentication.';
        } elseif ($this->credentialExpr === null) {
            $exception = 'A credential value was not provided prior to authentication.';
        }

        if (null !== $exception) {
            throw new InvalidArgumentException($exception);
        }

        $this->authenticateResultInfo = [
            'code'     => Result::FAILURE,
            'identity' => null,
            'messages' => []
        ];

        return true;
    }

    /**
     * authenticateCreateAuthResult() - Creates a Result object from
     * the information that has been collected during the authenticate() attempt.
     *
     * @return Result
     */
    private function authenticateCreateAuthResult()
    {
        return new Result(
            $this->authenticateResultInfo['code'],
            $this->authenticateResultInfo['identity'],
            $this->authenticateResultInfo['messages']
        );
    }
}
