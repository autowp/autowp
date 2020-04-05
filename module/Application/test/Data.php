<?php

namespace ApplicationTest;

use Laminas\Http\Header\Authorization;

class Data
{
    public const ADMIN_AUTH_HEADER = 'Authorization: Bearer '
        . 'eyJhbGciOiJIUzUxMiIsInR5cCI6IkpXVCJ9.eyJhdWQiOiJkZWZhdWx0IiwiZXhwIjoxODgwMDAwMDAwLCJzdWIiOiIxIn0.'
        . 'TJUsXWgvn1uEay6OIdFUHYoMsZuOjNOKKl-cQrMWsEcTTa-4_-Cob-w97VTWJOx0QJlGNhofw1vo2UUi-xxF-g';

    public static function getAdminAuthHeader(): Authorization
    {
        return Authorization::fromString(self::ADMIN_AUTH_HEADER);
    }
}
