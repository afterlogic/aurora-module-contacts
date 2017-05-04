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
 * @property int $IdUser
 * @property int $IdTenant
 * @property string $Storage
 * @property string $FullName
 * @property bool $UseFriendlyName
 * @property int $PrimaryEmail
 * @property int $PrimaryPhone
 * @property int $PrimaryAddress
 * @property string $ViewEmail
 * @property string $Title
 * @property string $FirstName
 * @property string $LastName
 * @property string $NickName
 * @property string $Skype
 * @property string $Facebook
 * 
 * @property string $PersonalEmail
 * @property string $PersonalAddress
 * @property string $PersonalCity
 * @property string $PersonalState
 * @property string $PersonalZip
 * @property string $PersonalCountry
 * @property string $PersonalWeb
 * @property string $PersonalFax
 * @property string $PersonalPhone
 * @property string $PersonalMobile
 * @property string $BusinessEmail
 * @property string $BusinessCompany
 * @property string $BusinessAddress
 * @property string $BusinessCity
 * @property string $BusinessState
 * @property string $BusinessZip
 * @property string $BusinessCountry
 * @property string $BusinessJobTitle
 * @property string $BusinessDepartment
 * @property string $BusinessOffice
 * @property string $BusinessPhone
 * @property string $BusinessFax
 * @property string $BusinessWeb
 * 
 * @property string $OtherEmail
 * @property string $Notes
 * @property int $BirthDay
 * @property int $BirthMonth
 * @property int $BirthYear
 * 
 * @property string $ETag
 * @property bool $Auto
 * @property int $Frequency
 *
 * @ignore
 * @package Contactsmain
 * @subpackage Classes
 */
class CContact extends \Aurora\System\EAV\Entity
{
	protected $aStaticMap = array(
		'IdUser'			=> array('int', 0),
		'IdTenant'			=> array('int', 0),
		'Storage'			=> array('string', ''),
		'FullName'			=> array('string', ''),
		'UseFriendlyName'	=> array('bool', true),
		'PrimaryEmail'		=> array('int', EContactsPrimaryEmail::Personal),
		'PrimaryPhone'		=> array('int', EContactsPrimaryPhone::Personal),
		'PrimaryAddress'	=> array('int', EContactsPrimaryAddress::Personal),
		'ViewEmail'			=> array('string', ''),

		'Title'				=> array('string', ''),
		'FirstName'			=> array('string', ''),
		'LastName'			=> array('string', ''),
		'NickName'			=> array('string', ''),
		'Skype'				=> array('string', ''),
		'Facebook'			=> array('string', ''),

		'PersonalEmail'		=> array('string', ''),
		'PersonalAddress'	=> array('string', ''),
		'PersonalCity'		=> array('string', ''),
		'PersonalState'		=> array('string', ''),
		'PersonalZip'		=> array('string', ''),
		'PersonalCountry'	=> array('string', ''),
		'PersonalWeb'		=> array('string', ''),
		'PersonalFax'		=> array('string', ''),
		'PersonalPhone'		=> array('string', ''),
		'PersonalMobile'	=> array('string', ''),

		'BusinessEmail'		=> array('string', ''),
		'BusinessCompany'	=> array('string', ''),
		'BusinessAddress'	=> array('string', ''),
		'BusinessCity'		=> array('string', ''),
		'BusinessState'		=> array('string', ''),
		'BusinessZip'		=> array('string', ''),
		'BusinessCountry'	=> array('string', ''),
		'BusinessJobTitle'	=> array('string', ''),
		'BusinessDepartment'=> array('string', ''),
		'BusinessOffice'	=> array('string', ''),
		'BusinessPhone'		=> array('string', ''),
		'BusinessFax'		=> array('string', ''),
		'BusinessWeb'		=> array('string', ''),

		'OtherEmail'		=> array('string', ''),
		'Notes'				=> array('string', ''),

		'BirthDay'			=> array('int', 0),
		'BirthMonth'		=> array('int', 0),
		'BirthYear'			=> array('int', 0),

		'ETag'				=> array('string', ''),
		'Auto'				=> array('bool', false),
		'Frequency'			=> array('int', 0),
	);
	
	public $GroupsContacts = array();
	
	public $ExtendedInformation = array();

	/**
	 * @param string $sKey
	 * @param mixed $mValue
	 */
	public function __set($sKey, $mValue)
	{
		if (is_string($mValue))
		{
	        $mValue = str_replace(array("\r","\n\n"), array('\n','\n'), $mValue);
		}

		parent::__set($sKey, $mValue);
	}
	
	/**
	 * Adds groups to contact. Groups are specified by names.
	 * @param array $aGroupNames List of group names.
	 */
	protected function addGroupsFromNames($aGroupNames)
	{
		if (is_array($aGroupNames) && count($aGroupNames) > 0)
		{
			$oContactsDecorator = \Aurora\System\Api::GetModuleDecorator('Contacts');
			$oApiContactsManager = $oContactsDecorator ? $oContactsDecorator->GetApiContactsManager() : null;
			if ($oApiContactsManager)
			{
				foreach($aGroupNames as $sGroupName)
				{
					$aGroups = $oApiContactsManager->getGroups($this->IdUser, ['Name' => [$sGroupName, '=']]);
					if (is_array($aGroups) && count($aGroups) > 0)
					{
						$this->addGroup($aGroups[0]->UUID);
					}
					elseif (!empty($sGroupName))
					{
						$oGroup = new CGroup();
						$oGroup->IdUser = $this->IdUser;
						$oGroup->Name = $sGroupName;

						$oApiContactsManager->createGroup($oGroup);
						$this->addGroup($oGroup->UUID);
					}
				}
			}
		}
	}

	/**
	 * Add group to contact.
	 * @param string $sGroupUUID Group UUID.
	 */
	protected function addGroup($sGroupUUID)
	{
		if (!empty($sGroupUUID))
		{
			$oGroupContact = \CGroupContact::createInstance('CGroupContact', $this->getModule());
			$oGroupContact->ContactUUID = $this->UUID;
			$oGroupContact->GroupUUID = $sGroupUUID;
			$this->GroupsContacts[] = $oGroupContact;
		}
	}
	
	/**
	 * Returns value of email that is specified as primary.
	 * @return string
	 */
	protected function getViewEmail()
	{
		switch ((int) $this->PrimaryEmail)
		{
			default:
			case EContactsPrimaryEmail::Personal:
				return (string) $this->PersonalEmail;
			case EContactsPrimaryEmail::Business:
				return (string) $this->BusinessEmail;
			case EContactsPrimaryEmail::Other:
				return (string) $this->OtherEmail;
		}
	}
	
	/**
	 * Sets ViewEmail field.
	 */
	public function SetViewEmail()
	{
		$this->ViewEmail = $this->getViewEmail();
	}
	
	/**
	 * Inits contacts from Vcard string.
	 * @param int $iUserId User identifier.
	 * @param string $sData Vcard string.
	 * @param string $sUid Contact UUID.
	 */
	public function InitFromVCardStr($iUserId, $sData, $sUid = '')
	{
		$oVCard = \Sabre\VObject\Reader::read($sData, \Sabre\VObject\Reader::OPTION_IGNORE_INVALID_LINES);
		$aContactData = CApiContactsVCardHelper::GetContactDataFromVcard($oVCard);
		$this->populate($aContactData);
		
		$oUser = null;
		$oCoreDecorator = \Aurora\System\Api::GetModuleDecorator('Core');
		if ($oCoreDecorator)
		{
			$oUser = $oCoreDecorator->GetUser($iUserId);
			if ($oUser instanceof \CUser)
			{
				$this->IdUser = $oUser->IdUser;
				$this->IdTenant = $oUser->IdTenant;
			}
		}
		
		if (!empty($sUid))
		{
			$this->UUID = $sUid;
		}
	}
	
	/**
	 * Populate contact with specified data.
	 * @param array $aContact List of contact data.
	 */
	public function populate($aContact)
	{
		parent::populate($aContact);

		$this->GroupsContacts = array();
		if (isset($aContact['GroupUUIDs']) && is_array($aContact['GroupUUIDs']))
		{
			foreach ($aContact['GroupUUIDs'] as $sGroupUUID)
			{
				$this->addGroup($sGroupUUID);
			}
		}
		
		if (isset($aContact['GroupNames']))
		{
			$this->addGroupsFromNames($aContact['GroupNames']);
		}
		
		$this->SetViewEmail();
	}
	
	/**
	 * Returns array with contact data.
	 * @return array
	 */
	public function toResponseArray()
	{
		$aRes = parent::toResponseArray();
		
		$aGroupUUIDs = array();
		foreach ($this->GroupsContacts as $oGroupContact)
		{
			$aGroupUUIDs[] = $oGroupContact->GroupUUID;
		}
		$aRes['GroupUUIDs'] = $aGroupUUIDs;
		
		foreach ($this->ExtendedInformation as $sKey => $mValue)
		{
			$aRes[$sKey] = $mValue;
		}
		
		return $aRes;
	}
}
