<?php

namespace Aurora\Modules\Contacts\Enums;

class PrimaryEmail extends \Aurora\System\Enums\AbstractEnumeration
{
    public const Personal = 0;
    public const Business = 1;
    public const Other = 2;

    /**
     * @var array
     */
    protected $aConsts = array(
        'Personal' => self::Personal,
        'Business' => self::Business,
        'Other' => self::Other
    );
}
