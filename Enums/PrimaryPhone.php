<?php

namespace Aurora\Modules\Contacts\Enums;

class PrimaryPhone extends \Aurora\System\Enums\AbstractEnumeration
{
    public const Mobile = 0;
    public const Personal = 1;
    public const Business = 2;

    /**
     * @var array
     */
    protected $aConsts = array(
        'Mobile' => self::Mobile,
        'Personal' => self::Personal,
        'Business' => self::Business
    );
}
