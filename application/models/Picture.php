<?php

class Picture extends Project_Db_Table
{
    const
        UNSORTED_TYPE_ID = 0,
        CAR_TYPE_ID      = 1,
        LOGO_TYPE_ID     = 2,
        MIXED_TYPE_ID    = 3,
        ENGINE_TYPE_ID   = 4,
        MODEL_TYPE_ID    = 5,
        INTERIOR_TYPE_ID = 6;

    const
        STATUS_NEW      = 'new',
        STATUS_ACCEPTED = 'accepted',
        STATUS_REMOVING = 'removing',
        STATUS_REMOVED  = 'removed',
        STATUS_INBOX    = 'inbox',
        DEFAULT_STATUS  = self::STATUS_INBOX;
}