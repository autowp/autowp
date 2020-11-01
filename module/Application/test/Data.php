<?php

namespace ApplicationTest;

use Laminas\Http\Header\Authorization;

class Data
{
    public const ADMIN_AUTH_HEADER = 'Authorization: Bearer '
        . 'eyJhbGciOiJIUzUxMiIsInR5cCI6IkpXVCJ9.eyJhdWQiOiJkZWZhdWx0IiwiZXhwIjoxODgwMDAwMDAwLCJzdWIiOiIzIn0.'
        . '5l0HxtAvH9kmfpJXC85lpcEf2EzPucxFCLmXl1oatPwKEDb__YTIdEDaaINplD4oWg10HbOc0-vDJVoQngKn9g';

    public static function getAdminAuthHeader(): Authorization
    {
        return Authorization::fromString(self::ADMIN_AUTH_HEADER);
    }
}
