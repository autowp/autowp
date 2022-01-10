<?php

namespace Autowp\User\Service;

use Application\Model\UserAccount;
use Exception;
use Firebase\JWT\JWT;
use Laminas\Cache\Exception\ExceptionInterface;
use Laminas\Cache\Storage\StorageInterface;
use Laminas\Http\PhpEnvironment\Request;
use UnexpectedValueException;

use function array_filter;
use function count;
use function explode;
use function file_get_contents;
use function json_decode;
use function rtrim;
use function urlencode;

use const JSON_THROW_ON_ERROR;

class OAuth
{
    private UserAccount $userAccount;

    private Request $request;

    private int $userId;

    private array $keycloakConfig;

    private StorageInterface $cache;

    private const ALG = 'RS256';

    public function __construct(
        UserAccount $userAccount,
        Request $request,
        array $keycloakConfig,
        StorageInterface $cache
    ) {
        $this->userAccount    = $userAccount;
        $this->request        = $request;
        $this->keycloakConfig = $keycloakConfig;
        $this->cache          = $cache;
    }

    /**
     * @throws Exception
     */
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
                $decoded  = JWT::decode($parts[1], $this->getCert(), [self::ALG]);
                $userGuid = (string) ($decoded->sub ?? '');
            } catch (UnexpectedValueException $e) {
                $userGuid = '';
            }

            $this->userId = $this->userAccount->getUserId("keycloak", $userGuid);
        }

        return $this->userId;
    }

    /**
     * @throws Exception|ExceptionInterface
     */
    private function getCert(): string
    {
        $cacheKey = 'KEYCLOAK_CERT';
        $success  = false;
        $cert     = $this->cache->getItem($cacheKey, $success);
        if (! $success) {
            $cert = $this->downloadCerts();

            $this->cache->setItem($cacheKey, $cert);
        }

        return (string) $cert;
    }

    /**
     * @throws Exception
     */
    private function downloadCerts(): string
    {
        $url = rtrim($this->keycloakConfig['url'], '/') . '/auth/realms/'
            . urlencode($this->keycloakConfig['realm']) . '/protocol/openid-connect/certs';

        $json = file_get_contents($url);

        if ($json === false) {
            throw new Exception("Failed to download $url");
        }

        $response = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $keys = array_filter($response['keys'], fn($key) => $key['alg'] === self::ALG && $key['use'] === 'sig');

        if (! isset($keys[0]['x5c'][0])) {
            throw new Exception("Key not found");
        }

        return "-----BEGIN CERTIFICATE-----\n{$keys[0]['x5c'][0]}\n-----END CERTIFICATE-----";
    }
}
