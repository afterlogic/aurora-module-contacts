<?php

/* -AFTERLOGIC LICENSE HEADER- */

/**
 * @internal
 * 
 * @package Contacts
 * @subpackage Helpers
 */
class CApiContactsSyncCsv
{
	/**
	 * @var CApiContactsCsvFormatter
	 */
	protected $oApiContactsManager;

	/**
	 * @var CApiContactsCsvParser
	 */
	protected $oFormatter;

	/**
	 * @var CApiContactsCsvParser
	 */
	protected $oParser;

	public function __construct($oApiContactsManager)
	{
		$this->oApiContactsManager = $oApiContactsManager;
		$this->oFormatter = new CApiContactsCsvFormatter();
		$this->oParser = new CApiContactsCsvParser();
	}

	/**
	 * @param int $iUserId
	 *
	 * @return string
	 */
	public function Export($iUserId)
	{
		$iOffset = 0;
		$iRequestLimit = 50;

		$sResult = '';

		$iCount = $this->oApiContactsManager->getContactsCount($iUserId);
		if (0 < $iCount)
		{
			while ($iOffset < $iCount)
			{
				$aList = $this->oApiContactsManager->getContacts(EContactSortField::Name, ESortOrder::ASC,
					$iOffset, $iRequestLimit, ['IdUser' => [$iUserId, '=']], 0);

				if (is_array($aList))
				{
					foreach ($aList as $oContact)
					{
						if ($oContact)
						{
							$this->oFormatter->setContainer($oContact);
							$this->oFormatter->form();
							$sResult .= $this->oFormatter->getValue();
						}
					}

					$iOffset += $iRequestLimit;
				}
				else
				{
					break;
				}
			}
		}

		return $sResult;
	}

	/**
	 * @param int $iUserId
	 * @param string $sTempFileName
	 * @param int $iParsedCount
	 * @param string $sGroupUUID
	 *
	 * @return int
	 */
	public function Import($iUserId, $sTempFileName, &$iParsedCount, $sGroupUUID)
	{
		$iCount = -1;
		$iParsedCount = 0;
		if (file_exists($sTempFileName))
		{
			$aCsv = api_Utils::CsvToArray($sTempFileName);
			if (is_array($aCsv))
			{
				$oApiUsersManager = CApi::GetSystemManager('users');
				$oAccount = $oApiUsersManager->getDefaultAccount($iUserId);

				$iCount = 0;
				foreach ($aCsv as $aCsvItem)
				{
					set_time_limit(30);

					$this->oParser->reset();

					$oContact = new CContact();
					$oContact->IdUser = $iUserId;

					$this->oParser->setContainer($aCsvItem);
					$aParameters = $this->oParser->getParameters();

					foreach ($aParameters as $sPropertyName => $mValue)
					{
						if ($oContact->IsProperty($sPropertyName))
						{
							$oContact->{$sPropertyName} = $mValue;
						}
					}

					if (0 === strlen($oContact->FullName))
					{
						$oContact->FullName = trim($oContact->FirstName.' '.$oContact->LastName);
					}
					
					if (0 !== strlen($oContact->PersonalEmail))
					{
						$oContact->PrimaryEmail = \EContactsPrimaryEmail::Personal;
					}
					else if (0 !== strlen($oContact->BusinessEmail))
					{
						$oContact->PrimaryEmail = \EContactsPrimaryEmail::Business;
					}
					else if (0 !== strlen($oContact->OtherEmail))
					{
						$oContact->PrimaryEmail = \EContactsPrimaryEmail::Other;
					}
					
					if (strlen($oContact->BirthYear) === 2)
					{
						$oDt = DateTime::createFromFormat('y', $oContact->BirthYear);
						$oContact->BirthYear = $oDt->format('Y');
					}					

					$iParsedCount++;
					$oContact->__SKIP_VALIDATE__ = true;

					if ($oAccount)
					{
						$oContact->IdTenant = $oAccount->IdTenant;
					}
					$oContact->GroupUUIDs = array($sGroupUUID);

					if ($this->oApiContactsManager->createContact($oContact))
					{
						$iCount++;
					}

					unset($oContact, $aParameters, $aCsvItem);
				}
			}
		}

		return $iCount;
	}
}
