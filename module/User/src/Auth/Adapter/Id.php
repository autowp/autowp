<?php

namespace Autowp\User\Auth\Adapter;

use Zend\Authentication\Adapter\AdapterInterface;
use Zend\Authentication\Result;
use Zend\Authentication\Adapter\Exception\InvalidArgumentException;

use Autowp\User\Model\DbTable\User;

class Id implements AdapterInterface
{
    /**
     * Identity value
     *
     * @var string
     */
    private $identity = null;

    /**
     * $authenticateResultInfo
     *
     * @var array
     */
    private $authenticateResultInfo = null;

    public function authenticate()
    {
        $this->authenticateSetup();

        $userTable = new User();
        $userRow = $userTable->fetchRow([
            'not deleted',
            'id = ?' => (int)$this->identity
        ]);

        if (! $userRow) {
            $this->authenticateResultInfo['code'] = Result::FAILURE_IDENTITY_NOT_FOUND;
            $this->authenticateResultInfo['messages'][] = 'A record with the supplied identity could not be found.';
        } else {
            $this->authenticateResultInfo['code'] = Result::SUCCESS;
            $this->authenticateResultInfo['identity'] = (int)$userRow->id;
            $this->authenticateResultInfo['messages'][] = 'Authentication successful.';
        }

        return new Result(
            $this->authenticateResultInfo['code'],
            $this->authenticateResultInfo['identity'],
            $this->authenticateResultInfo['messages']
        );
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
        if ($this->identity == '') {
            $exception = 'A value for the identity was not provided prior to authentication.';
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
     * setIdentity() - set the value to be used as the identity
     *
     * @param  string $value
     * @return Id Provides a fluent interface
     */
    public function setIdentity($value)
    {
        $this->identity = $value;
        return $this;
    }
}
