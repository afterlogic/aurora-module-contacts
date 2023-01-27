<?php

namespace Aurora\Modules\Contacts\Enums;

class PrimaryAddress extends \Aurora\System\Enums\AbstractEnumeration
{
    public const Personal = 0;
    public const Business = 1;

    /**
     * @var array
     */
    protected $aConsts = array(
        'Personal' => self::Personal,
        'Business' => self::Business,
    );
}
