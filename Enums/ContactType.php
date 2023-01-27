<?php

namespace Aurora\Modules\Contacts\Enums;

class ContactType extends \Aurora\System\Enums\AbstractEnumeration
{
    public const Personal = 0;
    public const Global_ = 1;
    public const GlobalAccounts = 2;
    public const GlobalMailingList = 3;

    /**
     * @var array
     */
    protected $aConsts = array(
        'Personal' => self::Personal,
        'Global_' => self::Global_,
        'GlobalAccounts' => self::GlobalAccounts,
        'GlobalMailingList' => self::GlobalMailingList
    );
}
