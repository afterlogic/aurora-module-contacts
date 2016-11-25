<?php

/* -AFTERLOGIC LICENSE HEADER- */

/**
 * @property string $GroupUUID
 * @property string $ContactUUID
 */
class CGroupContact extends AEntity
{
	public function __construct($sModule)
	{
		parent::__construct(get_class($this), $sModule);

		$this->__USE_TRIM_IN_STRINGS__ = true;

		$this->setStaticMap(array(
			'GroupUUID'	=> array('string', ''),
			'ContactUUID'	=> array('string', 0),
		));
	}

	public static function createInstance($sModule = 'Contacts')
	{
		return new CGroupContact($sModule);
	}
}
