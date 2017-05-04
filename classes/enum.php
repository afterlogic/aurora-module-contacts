<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AGPL-3.0 or AfterLogic Software License
 *
 * This code is licensed under AGPLv3 license or AfterLogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

/**
 * @package Api
 * @subpackage Enum
 */
class EContactsPrimaryEmail extends \AbstractEnumeration
{
	const Personal = 0;
	const Business = 1;
	const Other = 2;

	/**
	 * @var array
	 */
	protected $aConsts = array(
		'Personal' => self::Personal,
		'Business' => self::Business,
		'Other' => self::Other
	);
}

/**
 * @package Api
 * @subpackage Enum
 */
class EContactsPrimaryPhone extends \AbstractEnumeration
{
	const Mobile = 0;
	const Personal = 1;
	const Business = 2;

	/**
	 * @var array
	 */
	protected $aConsts = array(
		'Mobile' => self::Mobile,
		'Personal' => self::Personal,
		'Business' => self::Business
	);
}

/**
 * @package Api
 * @subpackage Enum
 */
class EContactsPrimaryAddress extends \AbstractEnumeration
{
	const Personal = 0;
	const Business = 1;

	/**
	 * @var array
	 */
	protected $aConsts = array(
		'Personal' => self::Personal,
		'Business' => self::Business,
	);
}

/**
 * @package Api
 * @subpackage Enum
 */
class EContactFileType extends \AbstractEnumeration
{
	const CSV = 'csv';
	const VCF = 'vcf';
}	

/**
 * @package Api
 * @subpackage Enum
 */
class EContactSortField extends \AbstractEnumeration
{
	const Name = 1;
	const Email = 2;
	const Frequency = 3;

	/**
	 * @var array
	 */
	protected $aConsts = array(
		'Name' => self::Name,
		'Email' => self::Email,
		'Frequency' => self::Frequency
	);
}
