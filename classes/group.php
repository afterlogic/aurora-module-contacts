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

	public function populate($aGroup)
	{
		if (isset($aGroup['IsOrganization']))
		{
			$this->IsOrganization = $aGroup['IsOrganization'];
		}
		if (isset($aGroup['Name']))
		{
			$this->Name = $aGroup['Name'];
		}
		if (isset($aGroup['Email']))
		{
			$this->Email = $aGroup['Email'];
		}
		if (isset($aGroup['Country']))
		{
			$this->Country = $aGroup['Country'];
		}
		if (isset($aGroup['City']))
		{
			$this->City = $aGroup['City'];
		}
		if (isset($aGroup['Company']))
		{
			$this->Company = $aGroup['Company'];
		}
		if (isset($aGroup['Fax']))
		{
			$this->Fax = $aGroup['Fax'];
		}
		if (isset($aGroup['Phone']))
		{
			$this->Phone = $aGroup['Phone'];
		}
		if (isset($aGroup['State']))
		{
			$this->State = $aGroup['State'];
		}
		if (isset($aGroup['Street']))
		{
			$this->Street = $aGroup['Street'];
		}
		if (isset($aGroup['Web']))
		{
			$this->Web = $aGroup['Web'];
		}
		if (isset($aGroup['Zip']))
		{
			$this->Zip = $aGroup['Zip'];
		}
		
		$this->GroupContacts = array();
		if (isset($aGroup['Contacts']) && is_array($aGroup['Contacts']))
		{
			$aContactUUIDs = $aGroup['Contacts'];
			foreach ($aContactUUIDs as $sContactUUID)
			{
				$oGroupContact = \CGroupContact::createInstance();
				$oGroupContact->ContactUUID = $sContactUUID;
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
			$aContacts = $oContactsModule->oApiContactsManager->getContacts(\EContactSortField::Name, \ESortOrder::ASC, 0, 299, [], $this->sUUID);

			$mResult = array(
				'IdUser' => $this->IdUser,
				'UUID' => $this->sUUID,
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
