<?php

namespace Aurora\Modules\Contacts\Enums;

class Access extends \Aurora\System\Enums\AbstractEnumeration
{
	const NoAccess = 0;
	const Write	 = 1;
	const Read   = 2;

	/**
	 * @var array
	 */
	protected $aConsts = array(
		'NoAccess'	=> self::NoAccess,
		'Write'	=> self::Write,
		'Read'	=> self::Read,
	);
}