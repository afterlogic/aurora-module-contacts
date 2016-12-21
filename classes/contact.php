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

		$this->__USE_TRIM_IN_STRINGS__ = true;

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
						$this->addGroup($aGroups[0]->sUUID);
					}
					elseif (!empty($sGroupName))
					{
						$oGroup = \CGroup::createInstance();
						$oGroup->IdUser = $this->IdUser;

						$oGroup->populate(['Name' => $sGroupName]);

						$oApiContactsManager->createGroup($oGroup);
						$this->addGroup($oGroup->sUUID);
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
			$oGroupContact->ContactUUID = $this->sUUID;
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
		
		$oUser = null;
		$oCoreDecorator = \CApi::GetModuleDecorator('Core');
		if ($oCoreDecorator)
		{
			$oUser = $oCoreDecorator->GetUser($iUserId);
		}
		
		$this->Populate($aContactData, $oUser);
		
		if (!empty($sUid))
		{
			$this->sUUID = $sUid;
		}
	}
	
	/**
	 * Populate contact with specified data.
	 * @param array $aContact List of contact data.
	 * @param \CUser $oUser User.
	 */
	public function Populate($aContact, $oUser = null)
	{
		if (isset($oUser))
		{
			$this->IdUser = $oUser->iId;
			$this->IdTenant = $oUser->IdTenant;
		}
		
		if (isset($aContact['UUID']))
		{
			$this->sUUID = $aContact['UUID'];
		}
		if (isset($aContact['Storage']))
		{
			$this->Storage = $aContact['Storage'];
		}
		if (isset($aContact['FullName']))
		{
			$this->FullName = $aContact['FullName'];
		}
		if (isset($aContact['PrimaryEmail']))
		{
			$this->PrimaryEmail = $aContact['PrimaryEmail'];
		}
		if (isset($aContact['PrimaryPhone']))
		{
			$this->PrimaryPhone = $aContact['PrimaryPhone'];
		}
		if (isset($aContact['PrimaryAddress']))
		{
			$this->PrimaryAddress = $aContact['PrimaryAddress'];
		}
		if (isset($aContact['FirstName']))
		{
			$this->FirstName = $aContact['FirstName'];
		}
		if (isset($aContact['LastName']))
		{
			$this->LastName = $aContact['LastName'];
		}
		if (isset($aContact['NickName']))
		{
			$this->NickName = $aContact['NickName'];
		}
		if (isset($aContact['Skype']))
		{
			$this->Skype = $aContact['Skype'];
		}
		if (isset($aContact['Facebook']))
		{
			$this->Facebook = $aContact['Facebook'];
		}
		
		if (isset($aContact['PersonalEmail']))
		{
			$this->PersonalEmail = $aContact['PersonalEmail'];
		}
		if (isset($aContact['PersonalAddress']))
		{
			$this->PersonalAddress = $aContact['PersonalAddress'];
		}
		if (isset($aContact['PersonalCity']))
		{
			$this->PersonalCity = $aContact['PersonalCity'];
		}
		if (isset($aContact['PersonalState']))
		{
			$this->PersonalState = $aContact['PersonalState'];
		}
		if (isset($aContact['PersonalZip']))
		{
			$this->PersonalZip = $aContact['PersonalZip'];
		}
		if (isset($aContact['PersonalCountry']))
		{
			$this->PersonalCountry = $aContact['PersonalCountry'];
		}
		if (isset($aContact['PersonalWeb']))
		{
			$this->PersonalWeb = $aContact['PersonalWeb'];
		}
		if (isset($aContact['PersonalFax']))
		{
			$this->PersonalFax = $aContact['PersonalFax'];
		}
		if (isset($aContact['PersonalPhone']))
		{
			$this->PersonalPhone = $aContact['PersonalPhone'];
		}
		if (isset($aContact['PersonalMobile']))
		{
			$this->PersonalMobile = $aContact['PersonalMobile'];
		}
		
		if (isset($aContact['BusinessCompany']))
		{
			$this->BusinessCompany = $aContact['BusinessCompany'];
		}
		if (isset($aContact['BusinessJobTitle']))
		{
			$this->BusinessJobTitle = $aContact['BusinessJobTitle'];
		}
		if (isset($aContact['BusinessDepartment']))
		{
			$this->BusinessDepartment = $aContact['BusinessDepartment'];
		}
		if (isset($aContact['BusinessOffice']))
		{
			$this->BusinessOffice = $aContact['BusinessOffice'];
		}
		if (isset($aContact['BusinessAddress']))
		{
			$this->BusinessAddress = $aContact['BusinessAddress'];
		}
		if (isset($aContact['BusinessCity']))
		{
			$this->BusinessCity = $aContact['BusinessCity'];
		}
		if (isset($aContact['BusinessState']))
		{
			$this->BusinessState = $aContact['BusinessState'];
		}
		if (isset($aContact['BusinessZip']))
		{
			$this->BusinessZip = $aContact['BusinessZip'];
		}
		if (isset($aContact['BusinessCountry']))
		{
			$this->BusinessCountry = $aContact['BusinessCountry'];
		}
		if (isset($aContact['BusinessFax']))
		{
			$this->BusinessFax = $aContact['BusinessFax'];
		}
		if (isset($aContact['BusinessPhone']))
		{
			$this->BusinessPhone = $aContact['BusinessPhone'];
		}
		if (isset($aContact['BusinessWeb']))
		{
			$this->BusinessWeb = $aContact['BusinessWeb'];
		}
		
		if (isset($aContact['OtherEmail']))
		{
			$this->OtherEmail = $aContact['OtherEmail'];
		}
		if (isset($aContact['Notes']))
		{
			$this->Notes = $aContact['Notes'];
		}
		if (isset($aContact['BusinessEmail']))
		{
			$this->BusinessEmail = $aContact['BusinessEmail'];
		}
		if (isset($aContact['BirthDay']))
		{
			$this->BirthDay = $aContact['BirthDay'];
		}
		if (isset($aContact['BirthMonth']))
		{
			$this->BirthMonth = $aContact['BirthMonth'];
		}
		if (isset($aContact['BirthYear']))
		{
			$this->BirthYear = $aContact['BirthYear'];
		}

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
			'UUID' => $this->sUUID,
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
