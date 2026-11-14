<?php

namespace App\EventSubscriber;

/**
 * Holds session cookie names for partner and admin areas.
 * Cookie selection is applied by AreaSessionFactory.
 */
final class SplitSessionSubscriber
{
    public const USER_SESSION_COOKIE = 'PARTNER_USER_SESSID';
    public const ADMIN_SESSION_COOKIE = 'PARTNER_ADMIN_SESSID';
    public const ADMIN_PATH_PREFIX = '/admin_9fg!lp';
}
