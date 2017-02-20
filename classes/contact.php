<?php
/**
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 * 
 * @package Modules
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
 *
 * @ignore
 * @package Contactsmain
 * @subpackage Classes
 */
class CContact extends AEntity
{
	public $GroupsContacts = array();
	
	public $ExtendedInformation = array();
	
	/**
	 * 
	 * @param string $sModule
	 */
	public function __construct($sModule)
	{
		parent::__construct(get_class($this), $sModule);

		$this->setStaticMap(array(
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
		));
	}
	
	/**
	 * Creates instance of CContact
	 * @param string $sModule Module name
	 * @return \CContact
	 */
	public static function createInstance($sModule = 'Contacts')
	{
		return new CContact($sModule);
	}

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
			$oContactsDecorator = \CApi::GetModuleDecorator('Contacts');
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
						$oGroup = \CGroup::createInstance();
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
			$oGroupContact = \CGroupContact::createInstance();
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
		$oCoreDecorator = \CApi::GetModuleDecorator('Core');
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
		$aGroupUUIDs = array();
		foreach ($this->GroupsContacts as $oGroupContact)
		{
			$aGroupUUIDs[] = $oGroupContact->GroupUUID;
		}
		
		$aRes = array(
			'IdUser' => $this->IdUser,
			'UUID' => $this->UUID,
			'Storage' => $this->Storage,
			'FullName' => $this->FullName,
			'PrimaryEmail' => $this->PrimaryEmail,
			'PrimaryPhone' => $this->PrimaryPhone,
			'PrimaryAddress' => $this->PrimaryAddress,
			'FirstName' => $this->FirstName,
			'LastName' => $this->LastName,
			'NickName' => $this->NickName,
			'Skype' => $this->Skype,
			'Facebook' => $this->Facebook,

			'PersonalEmail' => $this->PersonalEmail,
			'PersonalAddress' => $this->PersonalAddress,
			'PersonalCity' => $this->PersonalCity,
			'PersonalState' => $this->PersonalState,
			'PersonalZip' => $this->PersonalZip,
			'PersonalCountry' => $this->PersonalCountry,
			'PersonalWeb' => $this->PersonalWeb,
			'PersonalFax' => $this->PersonalFax,
			'PersonalPhone' => $this->PersonalPhone,
			'PersonalMobile' => $this->PersonalMobile,

			'BusinessEmail' => $this->BusinessEmail,
			'BusinessCompany' => $this->BusinessCompany,
			'BusinessAddress' => $this->BusinessAddress,
			'BusinessCity' => $this->BusinessCity,
			'BusinessState' => $this->BusinessState,
			'BusinessZip' => $this->BusinessZip,
			'BusinessCountry' => $this->BusinessCountry,
			'BusinessJobTitle' => $this->BusinessJobTitle,
			'BusinessDepartment' => $this->BusinessDepartment,
			'BusinessOffice' => $this->BusinessOffice,
			'BusinessPhone' => $this->BusinessPhone,
			'BusinessFax' => $this->BusinessFax,
			'BusinessWeb' => $this->BusinessWeb,

			'OtherEmail' => $this->OtherEmail,
			'Notes' => $this->Notes,

			'BirthDay' => $this->BirthDay,
			'BirthMonth' => $this->BirthMonth,
			'BirthYear' => $this->BirthYear,
			'ETag' => $this->ETag,
			
			'GroupUUIDs' => $aGroupUUIDs
		);
		
		foreach ($this->ExtendedInformation as $sKey => $mValue)
		{
			$aRes[$sKey] = $mValue;
		}
		
		return $aRes;
	}
}
