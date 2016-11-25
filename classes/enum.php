<?php
/*
 * @copyright Copyright (c) 2016, Afterlogic Corp.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 * 
 */

/**
 * @package Api
 * @subpackage Enum
 */
class EContactsPrimaryEmail extends AEnumeration
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
class EContactsPrimaryPhone extends AEnumeration
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
class EContactsPrimaryAddress extends AEnumeration
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
class EContactFileType extends AEnumeration
{
	const CSV = 'csv';
	const VCF = 'vcf';
}	

/**
 * @package Api
 * @subpackage Enum
 */
class EContactSortField extends AEnumeration
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
