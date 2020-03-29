<?php

namespace ApplicationTest;

use Laminas\Http\Header\Authorization;

class Data
{
    public const ADMIN_AUTH_HEADER = 'Authorization: Bearer '
        . 'eyJhbGciOiJIUzUxMiIsInR5cCI6IkpXVCJ9.eyJhdWQiOiJkZWZhdWx0IiwiZXhwIjoxNTg1NTA4MzUzLCJzdWIiOiIxIn0.'
        . 'Ux76cJNicZLB15-t6TLmMGg2NtjM5zUA4BWkaqKk0dvB5TihzOtKkAH1cM_D18RIOZj6jDHH98sFMdbYFFdzRg';

    public static function getAdminAuthHeader(): Authorization
    {
        return Authorization::fromString(self::ADMIN_AUTH_HEADER);
    }
}
