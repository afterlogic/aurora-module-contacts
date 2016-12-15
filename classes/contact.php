<?php

/* -AFTERLOGIC LICENSE HEADER- */

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
	
	public function __construct($sModule, $oParams)
	{
		parent::__construct(get_class($this), $sModule);

		$this->__USE_TRIM_IN_STRINGS__ = true;

		$this->setStaticMap(array(
			'IdUser'		=> array('int', 0),
			'IdTenant'		=> array('int', 0),
			'Storage'		=> array('string', ''),
			'FullName'		=> array('string', ''),
			'UseFriendlyName'	=> array('bool', true),
			'PrimaryEmail'		=> array('int', EContactsPrimaryEmail::Personal),
			'PrimaryPhone'		=> array('int', EContactsPrimaryPhone::Personal),
			'PrimaryAddress'	=> array('int', EContactsPrimaryAddress::Personal),
			'ViewEmail'			=> array('string', ''),

			'Title'			=> array('string', ''),
			'FirstName'		=> array('string', ''),
			'LastName'		=> array('string', ''),
			'NickName'		=> array('string', ''),
			'Skype'			=> array('string', ''),
			'Facebook'		=> array('string', ''),

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

			'BirthDay'		=> array('int', 0),
			'BirthMonth'		=> array('int', 0),
			'BirthYear'		=> array('int', 0),

			'ETag'				=> array('string', ''),
			
			'Auto'				=> array('bool', false),
		));
	}
	
	public static function createInstance($sModule = 'Contacts', $oParams = array())
	{
		return new CContact($sModule, $oParams);
	}

	/**
	 * @param string $sKey
	 * @param mixed $mValue
	 * @return void
	 */
	public function __set($sKey, $mValue)
	{
		if (is_string($mValue))
		{
	        $mValue = str_replace(array("\r","\n\n"), array('\n','\n'), $mValue);
		}

		parent::__set($sKey, $mValue);
	}
	
	public function SetViewEmail()
	{
		$this->ViewEmail = $this->getViewEmail();
	}
	
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
	 * @param int $iUserId
	 * @param string $sData
	 */
	public function InitFromVCardStr($iUserId, $sData, $sUid = '')
	{
		$oVCard = \Sabre\VObject\Reader::read($sData, \Sabre\VObject\Reader::OPTION_IGNORE_INVALID_LINES);
		return $this->InitFromVCardObject($iUserId, $oVCard, $sUid);
	}
	
	/**
	 * @param int $iUserId
	 * @param \Sabre\VObject\Component\VCard $oVCardObject
	 */
	public function InitFromVCardObject($iUserId, $oVCardObject, $sUid = '')
	{
		if ($oVCardObject)
		{
			if (empty($sUid))
			{
				$sUid = (isset($oVCardObject->UID)) ? (string)$oVCardObject->UID : \Sabre\VObject\UUIDUtil::getUUID();
			}
			
			$this->IdUser = $iUserId;
			$this->sUUID = $sUid;

			if (isset($oVCardObject->CATEGORIES))
			{
				$aGroupNames = $oVCardObject->CATEGORIES->getParts();
				if (is_array($aGroupNames) && count($aGroupNames) > 0)
				{
					$oContactsDecorator = \CApi::GetModuleDecorator('Contacts');
					$oApiContactsManager = $oContactsDecorator ? $oContactsDecorator->GetApiContactsManager() : null;
					if ($oApiContactsManager)
					{
						foreach($aGroupNames as $sGroupName)
						{
							$aGroups = $oApiContactsManager->getGroups($iUserId, ['Name' => [$sGroupName, '=']]);
							if (is_array($aGroups) && count($aGroups) > 0)
							{
								$this->AddGroup($aGroups[0]->sUUID);
							}
							elseif (!empty($sGroupName))
							{
								$oGroup = \CGroup::createInstance();
								$oGroup->IdUser = $iUserId;

								$oGroup->populate(['Name' => $sGroupName]);

								$oApiContactsManager->createGroup($oGroup);
								$this->AddGroup($oGroup->sUUID);
							}
						}
					}
				}
			}
			
			$this->FullName = (isset($oVCardObject->FN)) ? (string)$oVCardObject->FN : '';

			if (isset($oVCardObject->N))
			{
				$aNames = $oVCardObject->N->getParts();

				$this->LastName = (!empty($aNames[0])) ? $aNames[0] : '';
				$this->FirstName = (!empty($aNames[1])) ? $aNames[1] : '';
				$this->Title = (!empty($aNames[3])) ? $aNames[3] : '';
			}

			$this->NickName = (isset($oVCardObject->NICKNAME)) ? (string) $oVCardObject->NICKNAME : '';
			$this->Notes = (isset($oVCardObject->NOTE)) ? (string) $oVCardObject->NOTE : '';

			if (isset($oVCardObject->BDAY))
			{
				$aDateTime = explode('T', (string)$oVCardObject->BDAY);
				if (isset($aDateTime[0]))
				{
					$aDate = explode('-', $aDateTime[0]);
					$this->BirthYear = $aDate[0];
					$this->BirthMonth = $aDate[1];
					$this->BirthDay = $aDate[2];
				}
			}

			if (isset($oVCardObject->ORG))
			{
				$aOrgs = $oVCardObject->ORG->getParts();

				$this->BusinessCompany = (!empty($aOrgs[0])) ? $aOrgs[0] : '';
				$this->BusinessDepartment = (!empty($aOrgs[1])) ? $aOrgs[1] : '';
			}

			$this->BusinessJobTitle = (isset($oVCardObject->TITLE)) ? (string)$oVCardObject->TITLE : '';

			if (isset($oVCardObject->ADR))
			{
				foreach($oVCardObject->ADR as $oAdr)
				{
					$aAdrs = $oAdr->getParts();
					if ($oTypes = $oAdr['TYPE'])
					{
						if ($oTypes->has('WORK'))
						{
							$this->BusinessAddress = isset($aAdrs[2]) ? $aAdrs[2] : '';
							$this->BusinessCity = isset($aAdrs[3]) ? $aAdrs[3] : '';
							$this->BusinessState = isset($aAdrs[4]) ? $aAdrs[4] : '';
							$this->BusinessZip = isset($aAdrs[5]) ? $aAdrs[5] : '';
							$this->BusinessCountry = isset($aAdrs[6]) ? $aAdrs[6] : '';
						}
						if ($oTypes->has('HOME'))
						{
							$this->PersonalAddress = isset($aAdrs[2]) ? $aAdrs[2] : '';
							$this->PersonalCity = isset($aAdrs[3]) ? $aAdrs[3] : '';
							$this->PersonalState = isset($aAdrs[4]) ? $aAdrs[4] : '';
							$this->PersonalZip = isset($aAdrs[5]) ? $aAdrs[5] : '';
							$this->PersonalCountry = isset($aAdrs[6]) ? $aAdrs[6] : '';
						}
					}
				}
			}

			if (isset($oVCardObject->EMAIL))
			{
				foreach($oVCardObject->EMAIL as $oEmail)
				{
					if ($oType = $oEmail['TYPE'])
					{
						if ($oType->has('WORK') || $oType->has('INTERNET'))
						{
							$this->BusinessEmail = (string)$oEmail;
							if ($oType->has('PREF'))
							{
								$this->PrimaryEmail = EContactsPrimaryEmail::Business;
							}
						}
						else if ($oType->has('HOME'))
						{
							$this->PersonalEmail = (string)$oEmail;
							if ($oType->has('PREF'))
							{
								$this->PrimaryEmail = EContactsPrimaryEmail::Personal;
							}
						}
						else if ($oType->has('OTHER'))
						{
							$this->OtherEmail = (string)$oEmail;
							if ($oType->has('PREF'))
							{
								$this->PrimaryEmail = EContactsPrimaryEmail::Other;
							}
						}
						else if ($oEmail->group && isset($oVCardObject->{$oEmail->group.'.X-ABLABEL'}) &&
							strtolower((string) $oVCardObject->{$oEmail->group.'.X-ABLABEL'}) === '_$!<other>!$_')
						{
							$this->OtherEmail = (string)$oEmail;
							if ($oType->has('PREF'))
							{
								$this->PrimaryEmail = EContactsPrimaryEmail::Other;
							}
						}
					}
				}
				if (empty($this->PrimaryEmail))
				{
					if (!empty($this->PersonalEmail))
					{
						$this->PrimaryEmail = EContactsPrimaryEmail::Personal;
					}
					else if (!empty($this->BusinessEmail))
					{
						$this->PrimaryEmail = EContactsPrimaryEmail::Business;
					}
					else if (!empty($this->OtherEmail))
					{
						$this->PrimaryEmail = EContactsPrimaryEmail::Other;
					}
				}
			}

			if (isset($oVCardObject->URL))
			{
				foreach($oVCardObject->URL as $oUrl)
				{
					if ($oTypes = $oUrl['TYPE'])
					{
						if ($oTypes->has('HOME'))
						{
							$this->PersonalWeb = (string)$oUrl;
						}
						else if ($oTypes->has('WORK'))
						{
							$this->BusinessWeb = (string)$oUrl;
						}
					}
				}
			}

			if (isset($oVCardObject->TEL))
			{
				foreach($oVCardObject->TEL as $oTel)
				{
					if ($oTypes = $oTel['TYPE'])
					{
						if ($oTypes->has('FAX'))
						{
							if ($oTypes->has('HOME'))
							{
								$this->PersonalFax = (string)$oTel;
							}
							if ($oTypes->has('WORK'))
							{
								$this->BusinessFax = (string)$oTel;
							}
						}
						else
						{
							if ($oTypes->has('CELL'))
							{
								$this->PersonalMobile = (string)$oTel;
							}
							else if ($oTypes->has('HOME'))
							{
								$this->PersonalPhone = (string)$oTel;
							}
							else if ($oTypes->has('WORK'))
							{
								$this->BusinessPhone = (string)$oTel;
							}
						}
					}
				}
			}

			if (isset($oVCardObject->{'X-AFTERLOGIC-OFFICE'}))
			{
				$this->BusinessOffice = (string)$oVCardObject->{'X-AFTERLOGIC-OFFICE'};
			}

			if (isset($oVCardObject->{'X-AFTERLOGIC-USE-FRIENDLY-NAME'}))
			{
				$this->UseFriendlyName = '1' === (string)$oVCardObject->{'X-AFTERLOGIC-USE-FRIENDLY-NAME'};
			}
		}
	}
	
	public function AddGroup($sGroupUUID)
	{
		if (!empty($sGroupUUID))
		{
			$oGroupContact = \CGroupContact::createInstance();
			$oGroupContact->ContactUUID = $this->sUUID;
			$oGroupContact->GroupUUID = $sGroupUUID;
			$this->GroupsContacts[] = $oGroupContact;
		}
	}
	
	public function populate($aContact)
	{
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
				$this->AddGroup($sGroupUUID);
			}
		}
		
		$this->ViewEmail = $this->getViewEmail();
	}
	
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
