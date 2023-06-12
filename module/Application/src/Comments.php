<?php

namespace Application;

use Autowp\Comments\CommentsService;

class Comments
{
    public const PICTURES_TYPE_ID = 1;
    public const ITEM_TYPE_ID     = 2;
    public const VOTINGS_TYPE_ID  = 3;
    public const ARTICLES_TYPE_ID = 4;
    public const FORUMS_TYPE_ID   = 5;

    private CommentsService $service;

    public function __construct(CommentsService $service)
    {
        $this->service = $service;
    }

    public function service(): CommentsService
    {
        return $this->service;
    }
}
