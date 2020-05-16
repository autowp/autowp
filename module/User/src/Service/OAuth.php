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

    private int $userId;

    public function __construct(Request $request, string $key)
    {
        $this->request = $request;
        $this->key     = $key;
    }

    public function getUserID(): int
    {
        if (! isset($this->userId)) {
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
                $userId  = (int) ($decoded->sub ?? 0);
            } catch (UnexpectedValueException $e) {
                $userId = 0;
            }

            $this->userId = $userId;
        }

        return $this->userId;
    }
}
