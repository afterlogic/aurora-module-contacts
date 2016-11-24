<?php

/* -AFTERLOGIC LICENSE HEADER- */

/**
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
class CGroup extends AEntity
{
	const STR_PREFIX = '5765624D61696C50726F';

	public $Events = array();
	
	public $GroupContacts = array();
	
	public function __construct($sModule, $oParams)
	{
		parent::__construct(get_class($this), $sModule);

		$this->__USE_TRIM_IN_STRINGS__ = true;
		
		$this->setStaticMap(array(
			'IdUser'			=> array('int', 0),
			
			'Name'				=> array('string', ''),
			'IsOrganization'	=> array('bool', false),

			'Email'				=> array('string', ''),
			'Company'			=> array('string', ''),
			'Street'			=> array('string', ''),
			'City'				=> array('string', ''),
			'State'				=> array('string', ''),
			'Zip'				=> array('string', ''),
			'Country'			=> array('string', ''),
			'Phone'				=> array('string', ''),
			'Fax'				=> array('string', ''),
			'Web'				=> array('string', ''),
			'Events'			=> array('string', ''),
		));
	}

	public static function createInstance($sModule = 'Contacts', $oParams = array())
	{
		return new CGroup($sModule, $oParams);
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

	public function populate($aGroup)
	{
		$this->IsOrganization = $aGroup['IsOrganization'];
		$this->Name = $aGroup['Name'];
		$this->Email = $aGroup['Email'];
		$this->Country = $aGroup['Country'];
		$this->City = $aGroup['City'];
		$this->Company = $aGroup['Company'];
		$this->Fax = $aGroup['Fax'];
		$this->Phone = $aGroup['Phone'];
		$this->State = $aGroup['State'];
		$this->Street = $aGroup['Street'];
		$this->Web = $aGroup['Web'];
		$this->Zip = $aGroup['Zip'];
		
		$this->GroupContacts = array();
		if (!empty($aGroup['Contacts']) && is_array($aGroup['Contacts']))
		{
			$aContactIds = $aGroup['Contacts'];
			foreach ($aContactIds as $sContactId)
			{
				$oGroupContact = \CGroupContact::createInstance();
				$oGroupContact->IdContact = (int) $sContactId;
				$this->GroupContacts[] = $oGroupContact;
			}
		}
	}

	public function toResponseArray()
	{
		$mResult = null; 
		$oContactsModule = \CApi::GetModule('Contacts');
		 if ($oContactsModule)
		 {
			$aContacts = $oContactsModule->oApiContactsManager->getContacts(\EContactSortField::Name, \ESortOrder::ASC, 0, 299, [], $this->iId);

			$mResult = array(
				'IdUser' => $this->IdUser,
				'Id' => $this->iId,
				'IdGroup' => $this->iId,
				'Name' => $this->Name,

				'IsOrganization' => $this->IsOrganization,
				'Email'		=> $this->Email,
				'Company'	=> $this->Company,
				'Street'	=> $this->Street,
				'City'		=> $this->City,
				'State'		=> $this->State,
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
