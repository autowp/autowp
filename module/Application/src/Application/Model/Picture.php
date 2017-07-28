<?php

namespace Application\Model;

class Picture
{
    const
        STATUS_ACCEPTED = 'accepted',
        STATUS_REMOVING = 'removing',
        STATUS_REMOVED  = 'removed',
        STATUS_INBOX    = 'inbox';

    const MAX_NAME = 255;
}
