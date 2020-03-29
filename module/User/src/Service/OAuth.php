<?php

namespace Autowp\User\Service;

use Firebase\JWT\JWT;
use Laminas\Http\PhpEnvironment\Request;
use UnexpectedValueException;

use function count;
use function explode;

class OAuth
{
    private Request $request;

    private string $key;

    private int $userID;

    public function __construct(Request $request, string $key)
    {
        $this->request = $request;
        $this->key     = $key;
    }

    public function getUserID(): int
    {
        if (! isset($this->userID)) {
            $header = $this->request->getHeader('Authorization');
            if (! $header) {
                return 0;
            }

            $parts = explode(' ', $header->getFieldValue());
            if (count($parts) !== 2) {
                return 0;
            }
            if ($parts[0] !== 'Bearer') {
                return 0;
            }
            
            try {
                $decoded = JWT::decode($parts[1], $this->key, ['HS512']);
                $userID  = (int) ($decoded->sub ?? 0);
            } catch (UnexpectedValueException $e) {
                $userID = 0;
            }

            $this->userID = $userID;
        }

        return $this->userID;
    }
}
