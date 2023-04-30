<?php

namespace Autowp\User\Service;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Laminas\Cache\Exception\ExceptionInterface;
use Laminas\Cache\Storage\StorageInterface;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Http\PhpEnvironment\Request;
use UnexpectedValueException;

use function array_filter;
use function array_values;
use function count;
use function error_log;
use function explode;
use function file_get_contents;
use function in_array;
use function is_array;
use function json_decode;
use function mb_strtolower;
use function rtrim;
use function trim;
use function urlencode;

use const JSON_THROW_ON_ERROR;

class OAuth
{
    private Request $request;

    private int $userId;

    private array $keycloakConfig;

    private StorageInterface $cache;

    private const ALG = 'RS256';

    private array $hosts;

    private TableGateway $userTable;

    public function __construct(
        Request $request,
        array $keycloakConfig,
        StorageInterface $cache,
        array $hosts,
        TableGateway $userTable
    ) {
        $this->request        = $request;
        $this->keycloakConfig = $keycloakConfig;
        $this->cache          = $cache;
        $this->hosts          = $hosts;
        $this->userTable      = $userTable;
    }

    /**
     * @throws Exception
     * @throws ExceptionInterface
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
                $key     = new Key($this->getCert(), self::ALG);
                $decoded = JWT::decode($parts[1], $key);
                $this->ensureUserImported($decoded);
                $userGuid = (string) ($decoded->sub ?? '');
            } catch (UnexpectedValueException $e) {
                error_log($e->getMessage());
                $userGuid = '';
            }

            if (! $userGuid) {
                return 0;
            }

            /** @var Adapter $adapter */
            $adapter = $this->userTable->getAdapter();
            $row     = $adapter->query(
                'SELECT id FROM users WHERE uuid = UUID_TO_BIN(?)',
                [$userGuid]
            )->current();

            if (! $row) {
                return 0;
            }

            $this->userId = (int) $row['id'];
        }

        return $this->userId;
    }

    private function fullName(string $firstName, string $lastName, string $username): string
    {
        $result = trim($firstName . " " . $lastName);
        return $result ?: $username;
    }

    /**
     * @throws Exception
     */
    private function ensureUserImported(object $claims)
    {
        $locale = isset($claims->locale) ? mb_strtolower($claims->locale) : '';
        if (! isset($this->hosts[$locale])) {
            $locale = 'en';
        }

        $language = $this->hosts[$locale];
        if (! $language) {
            throw new Exception("language `$locale` is not defined");
        }

        $guid       = $claims->sub ?? null;
        $emailAddr  = $claims->email ?? null;
        $name       = $this->fullName(
            $claims->given_name ?? '',
            $claims->family_name ?? '',
            $claims->preferred_username ?? ''
        );
        $remoteAddr = $this->request->getServer('REMOTE_ADDR');
        if (! $remoteAddr) {
            $remoteAddr = '127.0.0.1';
        }
        $role = 'user';

        if (isset($claims->resource_access->autowp->roles)) {
            $roles = $claims->resource_access->autowp->roles;
            if (is_array($roles) && in_array('admin', $roles)) {
                $role = 'admin';
            }
        }

        /** @var Adapter $adapter */
        $adapter = $this->userTable->getAdapter();
        $adapter->query('
            INSERT INTO users (login, e_mail, password, email_to_check, hide_e_mail, email_check_code, name,
                               reg_date, last_online, timezone, last_ip, language, role, uuid)
            VALUES (NULL, ?, NULL, NULL, 1, NULL, ?, NOW(), NOW(), ?, ?, ?, ?, UUID_TO_BIN(?))
            ON DUPLICATE KEY UPDATE e_mail=VALUES(e_mail), name=VALUES(name), last_ip=VALUES(last_ip)
        ', [$emailAddr, $name, $language['timezone'], $remoteAddr, $locale, $role, $guid]);
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

        $keys = array_values(
            array_filter($response['keys'], fn($key) => $key['alg'] === self::ALG && $key['use'] === 'sig')
        );

        if (count($keys) === 0) {
            throw new Exception("No compatible keys found");
        }

        if (! isset($keys[0]['x5c'][0])) {
            throw new Exception("Key x5c not found in `$url`");
        }

        return "-----BEGIN CERTIFICATE-----\n{$keys[0]['x5c'][0]}\n-----END CERTIFICATE-----";
    }
}
