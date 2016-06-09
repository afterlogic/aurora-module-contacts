<?php

/* -AFTERLOGIC LICENSE HEADER- */

/**
 * @property mixed $IdGroup
 * @property string $IdGroupStr
 * @property int $IdUser
 * @property string $Name
 * @property bool $IsOrganization
 * @property string $Email
 * @property string $Company
 * @property string $Street
 * @property string $City
 * @property string $State
 * @property string $Zip
 * @property string $Country
 * @property string $Phone
 * @property string $Fax
 * @property string $Web
 * @property array $Events
 *
 * @ignore
 * @package Contactsmain
 * @subpackage Classes
 */
class CGroup extends APropertyBag
{
	const STR_PREFIX = '5765624D61696C50726F';

	public $Events = array();
	
	public function __construct()
	{
		parent::__construct(get_class($this), $sModule);

		$this->__USE_TRIM_IN_STRINGS__ = true;

		$this->SetDefaults();

		CApi::Plugin()->RunHook('api-group-construct', array(&$this));
	}

	/**
	 * @return string
	 */
	public function GenerateStrId()
	{
		return self::STR_PREFIX.$this->IdGroup;
	}

	/**
	 * @return bool
	 */
	public function initBeforeChange()
	{
		parent::initBeforeChange();

		if (0 === strlen($this->IdGroupStr))
		{
			$this->IdGroupStr = $this->GenerateStrId();
		}

		return true;
	}

	/**
	 * @return bool
	 */
	public function validate()
	{
		switch (true)
		{
			case api_Validate::IsEmpty($this->Name):
				throw new CApiValidationException(Errs::Validation_FieldIsEmpty, null, array(
					'{{ClassName}}' => 'CGroup', '{{ClassField}}' => 'Name'));
		}

		return true;
	}

	/**
	 * @return array
	 */
	public function getMap()
	{
		return self::getStaticMap();
	}

	/**
	 * @return array
	 */
	public static function getStaticMap()
	{
		return array(
			'IdGroup'		=> array('string', ''), // 'id_group', false, false),
			'IdGroupStr'	=> array('string', ''), // (100)'group_str_id', false),
			'IdUser'		=> array('int', 0), // 'id_user'),

			'Name'			=> array('string', ''), // (255)'group_nm'),

			'IsOrganization'	=> array('bool', false), // 'organization'),

			'Email'		=> array('string', ''), //(255) 'email'),
			'Company'	=> array('string', ''), //(200) 'company'),
			'Street'	=> array('string', ''), //(255) 'street'),
			'City'		=> array('string', ''), //(200) 'city'),
			'State'		=> array('string', ''), //(200) 'state'),
			'Zip'		=> array('string', ''), //(10) 'zip'),
			'Country'	=> array('string', ''), //(200) 'country'),
			'Phone'		=> array('string', ''), //(50) 'phone'),
			'Fax'		=> array('string', ''), //(50) 'fax'),
			'Web'		=> array('string', ''), //(255) 'web')
			
			'Events'	=> array('array', '')
		);
	}
	
	public function toResponseArray($aParameters = array())
	{
		$mResult = null; 
		$oContactsModule = \CApi::GetModule('Contacts');
		 if ($oContactsModule)
		 {
			$aContacts = $oContactsModule->oApiContactsManager->getContactItems(
				$this->IdUser, \EContactSortField::Name, \ESortOrder::ASC, 0, 299, '', '', $this->IdGroup);

			$mResult = array(
				'IdUser' => $this->IdUser,
				'IdGroup' => $this->IdGroup,
				'IdGroupStr' => $this->IdGroupStr,
				'Name' => $this->Name,

				'IsOrganization' => $this->IsOrganization,
				'Email'		=> $this->Email,
				'Company'	=> $this->Company,
				'Street'	=> $this->Street,
				'City'		=> $this->City,
				'State'		=> $this->City,
				'Zip'		=> $this->Zip,
				'Country'	=> $this->Country,
				'Phone'		=> $this->Phone,
				'Fax'		=> $this->Fax,
				'Web'		=> $this->Web,

				'Contacts' => \CApiResponseManager::GetResponseObject($aContacts)
			);
		 }
		 
		return $mResult;
	}
}
