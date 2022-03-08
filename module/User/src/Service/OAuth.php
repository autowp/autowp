<?php

namespace Autowp\User\Service;

use Application\Model\UserAccount;
use Exception;
use Firebase\JWT\JWT;
use Laminas\Cache\Exception\ExceptionInterface;
use Laminas\Cache\Storage\StorageInterface;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Expression;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Http\PhpEnvironment\Request;
use UnexpectedValueException;

use function array_filter;
use function Autowp\Commons\currentFromResultSetInterface;
use function count;
use function explode;
use function file_get_contents;
use function json_decode;
use function mb_strtolower;
use function rtrim;
use function trim;
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

    private array $hosts;

    private TableGateway $userAccountTable;

    private TableGateway $userTable;

    public function __construct(
        UserAccount $userAccount,
        Request $request,
        array $keycloakConfig,
        StorageInterface $cache,
        array $hosts,
        TableGateway $userAccountTable,
        TableGateway $userTable
    ) {
        $this->userAccount      = $userAccount;
        $this->request          = $request;
        $this->keycloakConfig   = $keycloakConfig;
        $this->cache            = $cache;
        $this->hosts            = $hosts;
        $this->userAccountTable = $userAccountTable;
        $this->userTable        = $userTable;
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
                $decoded = JWT::decode($parts[1], $this->getCert(), [self::ALG]);
                $this->ensureUserImported($decoded);
                $userGuid = (string) ($decoded->sub ?? '');
            } catch (UnexpectedValueException $e) {
                $userGuid = '';
            }

            $this->userId = $this->userAccount->getUserId("keycloak", $userGuid);
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

        $row = currentFromResultSetInterface($this->userAccountTable->select([
            'service_id'  => 'keycloak',
            'external_id' => $guid,
        ]));

        if (! $row) {
            $this->userTable->insert([
                'login'            => null,
                'e_mail'           => $emailAddr,
                'password'         => null,
                'email_to_check'   => null,
                'hide_e_mail'      => 1,
                'email_check_code' => null,
                'name'             => $name,
                'reg_date'         => new Expression('NOW()'),
                'last_online'      => new Expression('NOW()'),
                'timezone'         => $language['timezone'],
                'last_ip'          => $remoteAddr,
                'language'         => $locale,
                'role'             => 'user',
            ]);
            $userId = $this->userTable->getLastInsertValue();
        } else {
            $userId = (int) $row['id'];

            $this->userTable->update([
                'e_mail'  => $emailAddr,
                'name'    => $name,
                'last_ip' => $remoteAddr,
            ], [
                'id' => $userId,
            ]);
        }

        /** @var Adapter $adapter */
        $adapter = $this->userAccountTable->getAdapter();
        $stmt    = $adapter->query('
            INSERT INTO user_account (user_id, service_id, external_id, used_for_reg, name, link)
            VALUES (?, ?, ?, 0, ?, "")
            ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), name=VALUES(name)
        ');
        $stmt->execute([$userId, "keycloak", $guid, $name]);
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
