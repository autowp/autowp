<?php

namespace Autowp\User\Auth\Adapter;

use Autowp\User\Model\User;
use Laminas\Authentication\Adapter\AdapterInterface;
use Laminas\Authentication\Adapter\Exception\InvalidArgumentException;
use Laminas\Authentication\Result;
use Laminas\Db\Sql;

use function mb_strpos;

class Login implements AdapterInterface
{
    /**
     * Identity value
     */
    private string $identity;

    /**
     * $_credential - Credential values
     */
    private Sql\Expression $credentialExpr;

    private array $authenticateResultInfo;

    private User $userModel;

    public function __construct(User $userModel, string $identity, Sql\Expression $credentialExpr)
    {
        $this->userModel      = $userModel;
        $this->identity       = $identity;
        $this->credentialExpr = $credentialExpr;
    }

    /**
     * @suppress PhanPluginMixedKeyNoKey
     */
    public function authenticate(): Result
    {
        $this->authenticateSetup();

        $filter = [
            'not deleted',
            'password' => $this->credentialExpr,
        ];
        if (mb_strpos($this->identity, '@') !== false) {
            $filter['e_mail'] = (string) $this->identity;
        } else {
            $filter['login'] = (string) $this->identity;
        }

        $userRow = $this->userModel->getTable()->select($filter)->current();

        if (! $userRow) {
            $this->authenticateResultInfo['code']       = Result::FAILURE_IDENTITY_NOT_FOUND;
            $this->authenticateResultInfo['messages'][] = 'A record with the supplied identity could not be found.';
        } else {
            $this->authenticateResultInfo['code']       = Result::SUCCESS;
            $this->authenticateResultInfo['identity']   = (int) $userRow['id'];
            $this->authenticateResultInfo['messages'][] = 'Authentication successful.';
        }

        return $this->authenticateCreateAuthResult();
    }

    /**
     * authenticateSetup() - This method abstracts the steps involved with
     * making sure that this adapter was indeed setup properly with all
     * required pieces of information.
     *
     * @throws InvalidArgumentException - in the event that setup was not done properly.
     */
    private function authenticateSetup(): bool
    {
        $exception = null;

        if ($this->identity === '') {
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
            'messages' => [],
        ];

        return true;
    }

    /**
     * authenticateCreateAuthResult() - Creates a Result object from
     * the information that has been collected during the authenticate() attempt.
     */
    private function authenticateCreateAuthResult(): Result
    {
        return new Result(
            $this->authenticateResultInfo['code'],
            $this->authenticateResultInfo['identity'],
            $this->authenticateResultInfo['messages']
        );
    }
}
