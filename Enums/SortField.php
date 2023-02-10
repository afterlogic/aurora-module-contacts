<?php

namespace Aurora\Modules\Contacts\Enums;

class SortField extends \Aurora\System\Enums\AbstractEnumeration
{
    public const Name = 1;
    public const Email = 2;
    public const Frequency = 3;
    public const FirstName = 4;
    public const LastName = 5;

    /**
     * @var array
     */
    protected $aConsts = [
        'Name' => self::Name,
        'Email' => self::Email,
        'Frequency' => self::Frequency,
        'FirstName' => self::FirstName,
        'LastName' => self::LastName
    ];
}
