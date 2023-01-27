<?php

namespace Aurora\Modules\Contacts\Enums;

class SortField extends \Aurora\System\Enums\AbstractEnumeration
{
    public const Name = 1;
    public const Email = 2;
    public const Frequency = 3;

    /**
     * @var array
     */
    protected $aConsts = array(
        'Name' => self::Name,
        'Email' => self::Email,
        'Frequency' => self::Frequency
    );
}
