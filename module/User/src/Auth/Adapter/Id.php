<?php

namespace Autowp\User\Auth\Adapter;

use Autowp\User\Model\User;
use Laminas\Authentication\Adapter\AdapterInterface;
use Laminas\Authentication\Adapter\Exception\InvalidArgumentException;
use Laminas\Authentication\Result;

class Id implements AdapterInterface
{
    /**
     * Identity value
     */
    private int $identity;

    /**
     * $authenticateResultInfo
     */
    private array $authenticateResultInfo;

    private User $userModel;

    public function __construct(User $userModel)
    {
        $this->userModel = $userModel;
    }

    public function authenticate()
    {
        $this->authenticateSetup();

        $userRow = $this->userModel->getRow([
            'not_deleted' => true,
            'id'          => (int) $this->identity,
        ]);

        if (! $userRow) {
            $this->authenticateResultInfo['code']       = Result::FAILURE_IDENTITY_NOT_FOUND;
            $this->authenticateResultInfo['messages'][] = 'A record with the supplied identity could not be found.';
        } else {
            $this->authenticateResultInfo['code']       = Result::SUCCESS;
            $this->authenticateResultInfo['identity']   = (int) $userRow['id'];
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
     */
    private function authenticateSetup(): bool
    {
        if (! $this->identity) {
            $exception = 'A value for the identity was not provided prior to authentication.';
            throw new InvalidArgumentException($exception);
        }

        $this->authenticateResultInfo = [
            'code'     => Result::FAILURE,
            'identity' => null,
            'messages' => [],
        ];

        return true;
    }

    /**
     * setIdentity() - set the value to be used as the identity
     *
     * @param  int $value
     */
    public function setIdentity($value): self
    {
        $this->identity = (int) $value;
        return $this;
    }
}
