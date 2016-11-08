<?php

/* -AFTERLOGIC LICENSE HEADER- */

/**
 * @property int $IdGroup
 * @property int $IdContact
 */
class CGroupContact extends AEntity
{
	public function __construct($sModule)
	{
		parent::__construct(get_class($this), $sModule);

		$this->__USE_TRIM_IN_STRINGS__ = true;

		$this->setStaticMap(array(
			'IdGroup'	=> array('int', 0),
			'IdContact'	=> array('int', 0),
		));
	}

	public static function createInstance($sModule = 'Contacts')
	{
		return new CGroupContact($sModule);
	}
}
