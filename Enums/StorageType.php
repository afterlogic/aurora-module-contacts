<?php

namespace Aurora\Modules\Contacts\Enums;

class StorageType extends \Aurora\System\Enums\AbstractEnumeration
{
	const Personal = 'personal';
	const Collected = 'collected';
	const Team = 'team';
	const Shared = 'shared';
	const All = 'all';
	const AddressBook = 'addressbook';

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
