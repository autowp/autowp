<?php

namespace Autowp\User\Auth\Adapter;

use Zend\Authentication\Adapter\AdapterInterface;
use Zend\Authentication\Result;
use Zend\Authentication\Adapter\Exception\InvalidArgumentException;

use Autowp\User\Model\DbTable\User;

class Remember implements AdapterInterface
{
    /**
     * Credential values
     *
     * @var string
     */
    private $credential = null;

    /**
     * @var array
     */
    private $authenticateResultInfo = null;

    public function authenticate()
    {
        $this->authenticateSetup();

        $userTable = new User();

        $userRow = $userTable->fetchRow(
            $userTable->select(true)
                ->join('user_remember', 'users.id = user_remember.user_id', [])
                ->where('user_remember.token = ?', (string)$this->credential)
                ->where('not users.deleted')
        );

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

        if ($this->credential === null) {
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

    /**
     * setCredential() - set the credential value to be used
     *
     * @param  string $credential
     * @return Remember Provides a fluent interface
     */
    public function setCredential($credential)
    {
        $this->credential = $credential;
        return $this;
    }
}
