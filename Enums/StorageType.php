<?php

namespace Aurora\Modules\Contacts\Enums;

class StorageType extends \Aurora\System\Enums\AbstractEnumeration
{
    public const Personal = 'personal';
    public const Collected = 'collected';
    public const Team = 'team';
    public const Shared = 'shared';
    public const All = 'all';
    public const AddressBook = 'addressbook';

    /**
     * @var array
     */
    protected $aConsts = [
        'Personal' => self::Personal,
        'Collected' => self::Collected,
        'Team' => self::Team,
        'Shared' => self::Shared,
        'All' => self::All,
        'AddressBook' => self::AddressBook
    ];
}
