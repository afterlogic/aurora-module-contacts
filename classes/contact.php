<?php

/* -AFTERLOGIC LICENSE HEADER- */

/**
 * @property string $IdContactStr
 * @property int $IdUser
 * @property int $IdDomain
 * @property int $IdTenant
 * @property array $GroupsIds
 * @property int $Type
 * @property string $IdTypeLink
 * @property string $FullName
 * @property bool $UseFriendlyName
 * @property int $PrimaryEmail
 * @property string $Title
 * @property string $FirstName
 * @property string $LastName
 * @property string $NickName
 * @property string $Skype
 * @property string $Facebook
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
 * @property string $OtherEmail
 * @property string $Notes
 * @property int $BirthdayDay
 * @property int $BirthdayMonth
 * @property int $BirthdayYear
 * @property bool $ReadOnly
 * @property bool $Global
 * @property bool $ItsMe
 * @property string $ETag
 * @property bool $Auto
 * @property bool $SharedToAll
 * @property bool $HideInGAB
 * @property int $DateModified
 *
 * @ignore
 * @package Contactsmain
 * @subpackage Classes
 */
class CContact extends AEntity
{
	const STR_PREFIX = '040000008200E00074C5B7101A82E008';

	/**
	 * @var bool
	 */
	public $__LOCK_DATE_MODIFIED__;

	/**
	 * @var bool
	 */
	public $__SKIP_VALIDATE__;

	public function __construct($sModule, $oParams)
	{
		parent::__construct(get_class($this), $sModule);

		$this->__USE_TRIM_IN_STRINGS__ = true;

		$this->setStaticMap(array(
			'IdContactStr'	=> array('string', ''), // 'str_id', false),
			'IdUser'		=> array('int', 0), // 'id_user'),
			'IdDomain'		=> array('int', 0), // 'id_domain'),
			'IdTenant'		=> array('int', 0), // 'id_tenant', true),

			'GroupsIds'			=> array('string', ''), //), array

			'Type'			=> array('int', EContactType::Personal), // 'type'),
			'IdTypeLink'	=> array('string', ''), // 'type_id'),

			'PrimaryEmail'		=> array('int', EContactsPrimaryEmail::Personal),
			'PrimaryPhone'		=> array('int', EContactsPrimaryPhone::Personal),
			'PrimaryAddress'	=> array('int', EContactsPrimaryAddress::Personal),

			'DateCreated'		=> array('datetime', ''), // 'date_created', true, false),
			'DateModified'		=> array('datetime', ''), // 'date_modified'),

			'UseFriendlyName'	=> array('bool', true), // 'use_friendly_nm'),

			'Title'			=> array('string', ''),
			'FullName'		=> array('string', ''), // 'fullname'),
			'FirstName'		=> array('string', ''), // 'firstname'),
			'LastName'		=> array('string', ''), // 'surname'),
			'NickName'		=> array('string', ''), // 'nickname'),
			'Skype'			=> array('string', ''), // 'skype'),
			'Facebook'		=> array('string', ''), // 'facebook'),

			'PersonalEmail'		=> array('string', ''), // 'h_email'),
			'PersonalAddress'	=> array('string', ''), // 'h_street'),
			'PersonalCity'		=> array('string', ''), // 'h_city'),
			'PersonalState'		=> array('string', ''), // 'h_state'),
			'PersonalZip'		=> array('string', ''), // 'h_zip'),
			'PersonalCountry'	=> array('string', ''), // 'h_country'),
			'PersonalWeb'		=> array('string', ''), // 'h_web'),
			'PersonalFax'		=> array('string', ''), // 'h_fax'),
			'PersonalPhone'		=> array('string', ''), // 'h_phone'),
			'PersonalMobile'	=> array('string', ''), // 'h_mobile'),

			'BusinessEmail'		=> array('string', ''), // 'b_email'),
			'BusinessCompany'	=> array('string', ''), // 'b_company'),
			'BusinessAddress'	=> array('string', ''), // 'b_street'),
			'BusinessCity'		=> array('string', ''), // 'b_city'),
			'BusinessState'		=> array('string', ''), // 'b_state'),
			'BusinessZip'		=> array('string', ''), // 'b_zip'),
			'BusinessCountry'	=> array('string', ''), // 'b_country'),
			'BusinessJobTitle'	=> array('string', ''), // 'b_job_title'),
			'BusinessDepartment'=> array('string', ''), // 'b_department'),
			'BusinessOffice'	=> array('string', ''), // 'b_office'),
			'BusinessPhone'		=> array('string', ''), // 'b_phone'),
			'BusinessFax'		=> array('string', ''), // 'b_fax'),
			'BusinessWeb'		=> array('string', ''), // 'b_web'),

			'OtherEmail'		=> array('string', ''), // 'other_email'),
			'Notes'				=> array('string', ''), // 'notes'),

			'BirthdayDay'		=> array('int', 0), // 'birthday_day'),
			'BirthdayMonth'		=> array('int', 0), // 'birthday_month'),
			'BirthdayYear'		=> array('int', 0), // 'birthday_year'),

			'ReadOnly'			=> array('bool', false), //),
			'Global'			=> array('bool', false), //),
			'ItsMe'				=> array('bool', false), //),

			'ETag'				=> array('string', ''), // 'etag'),
			
			'Auto'				=> array('bool', false), // 'auto_create'),
			'SharedToAll'		=> array('bool', false), // 'shared_to_all'),
			'HideInGAB'			=> array('bool', false), // 'hide_in_gab')
		));

		$this->__LOCK_DATE_MODIFIED__ = false;
		$this->__SKIP_VALIDATE__ = false;
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
	
	public function GetViewEmail()
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
	 * @return string
	 */
	public function GenerateStrId()
	{
		return \Sabre\DAV\UUIDUtil::getUUID().'.vcf';
	}

	/**
	 * @param stdClass $oRow
	 */
	public function InitByDbRow($oRow)
	{
		parent::InitByDbRow($oRow);

		if (!$this->ReadOnly && (EContactType::Global_ === $this->Type || EContactType::GlobalAccounts === $this->Type ||
			EContactType::GlobalMailingList === $this->Type))
		{
			$this->ReadOnly = true;
		}
		
		if (EContactType::GlobalAccounts === $this->Type || EContactType::GlobalMailingList === $this->Type)
		{
			$this->Global = true;
		}
	}

	/**
	 * @return bool
	 */
	public function initBeforeChange()
	{
//		parent::initBeforeChange();

		if (0 === strlen($this->IdContactStr) &&
			((is_int($this->iId) && 0 < $this->iId) ||
			(is_string($this->iId) && 0 < strlen($this->iId)))
		)
		{
			$this->IdContactStr = $this->GenerateStrId();
		}

		if (!$this->__LOCK_DATE_MODIFIED__)
		{
			$this->DateModified = time();
		}

		return true;
	}

	/**
	 * @return bool
	 */
	public function validate()
	{
		if (!$this->__SKIP_VALIDATE__)
		{
			switch (true)
			{
				case
					api_Validate::IsEmpty($this->FullName) &&
					api_Validate::IsEmpty($this->PersonalEmail) &&
					api_Validate::IsEmpty($this->BusinessEmail) &&
					api_Validate::IsEmpty($this->OtherEmail):

					throw new CApiValidationException(Errs::Validation_FieldIsEmpty_OutInfo);
			}
		}

		return true;
	}

	private function compareProperty($oContact, $sName)
	{
		if ($this->{$sName} !== $oContact->{$sName})
		{
			$this->{$sName} = $oContact->{$sName};
			return false;
		}

		return true;
	}

	/**
	 * @param CContact $oContact
	 * @retur bool
	 */
	public function CompareAndComputedByNewGlobalContact($oContact)
	{
		$iChanged = 1;

		foreach (array(
			'Title', 'FullName', 'FirstName', 'LastName', 'NickName', 'PrimaryEmail',
			'PersonalEmail', 'PersonalAddress', 'PersonalCity', 'PersonalState', 'PersonalZip', 'PersonalCountry',
			'PersonalPhone', 'PersonalFax', 'PersonalMobile', 'PersonalWeb',
			'BusinessEmail', 'BusinessCompany', 'BusinessAddress', 'BusinessCity', 'BusinessState', 'BusinessZip', 'BusinessCountry',
			'BusinessJobTitle', 'BusinessDepartment', 'BusinessOffice', 'BusinessPhone', 'BusinessFax', 'BusinessWeb',
			'OtherEmail', 'Notes', 'Skype', 'Facebook', 'BirthdayDay', 'BirthdayMonth', 'BirthdayYear', 'HideInGAB'
		) as $Prop)
		{
			$iChanged &= $this->compareProperty($oContact, $Prop);
		}

		return !$iChanged;
	}
	
		
	/**
	 * @param int $iUserId
	 * @param string $sData
	 */
	public function InitFromVCardStr($iUserId, $sData)
	{
		$oVCard = \Sabre\VObject\Reader::read($sData, \Sabre\VObject\Reader::OPTION_IGNORE_INVALID_LINES);
		return $this->InitFromVCardObject($iUserId, $oVCard);
	}
	
	/**
	 * @param int $iUserId
	 * @param \Sabre\VObject\Component\VCard $oVCardObject
	 */
	public function InitFromVCardObject($iUserId, $oVCardObject)
	{
		if ($oVCardObject)
		{
			$sUid = (isset($oVCardObject->UID)) ? (string)$oVCardObject->UID : \Sabre\VObject\UUIDUtil::getUUID();
			
			$this->IdUser = $iUserId;
			$this->UseFriendlyName = true;
			$this->IdContactStr = $sUid . '.vcf';

			$aResultGroupsIds = $this->GroupsIds;
			if (isset($oVCardObject->CATEGORIES))
			{
				$aGroupsIds = $oVCardObject->CATEGORIES->getParts();
				foreach($aGroupsIds as $sGroupsId)
				{
					if (!empty($sGroupsId))
					{
						$aResultGroupsIds[] = (string) $sGroupsId;
					}
				}
			}
			$this->GroupsIds = $aResultGroupsIds;

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
					$this->BirthdayYear = $aDate[0];
					$this->BirthdayMonth = $aDate[1];
					$this->BirthdayDay = $aDate[2];
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
	
	public function toResponseArray($aParameters = array())
	{
		return array(
			'IdUser' => $this->IdUser,
			'IdContact' => $this->iId,
			'IdContactStr' => $this->IdContactStr,

			'Global' => $this->Global,
			'ItsMe' => $this->ItsMe,

			'PrimaryEmail' => $this->PrimaryEmail,
			'UseFriendlyName' => $this->UseFriendlyName,

			'GroupsIds' => $this->GroupsIds,

			'FullName' => $this->FullName,
			'Title' => $this->Title,
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

			'BirthdayDay' => $this->BirthdayDay,
			'BirthdayMonth' => $this->BirthdayMonth,
			'BirthdayYear' => $this->BirthdayYear,
			'ReadOnly' => $this->ReadOnly,
			'ETag' => $this->ETag,
			'SharedToAll' => $this->SharedToAll
		);
	}
}
