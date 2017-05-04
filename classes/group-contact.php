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
 * @package Classes
 * @subpackage GroupContact
 * 
 * @property string $GroupUUID
 * @property string $ContactUUID
 */
class CGroupContact extends \Aurora\System\EAV\Entity
{
	protected $aStaticMap = array(
		'GroupUUID'	=> array('string', ''),
		'ContactUUID'	=> array('string', 0),
	);
}
