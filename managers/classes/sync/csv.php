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
	 * @param array $aContacts
	 *
	 * @return string
	 */
	public function Export($aContacts)
	{
		$sResult = '';
		
		if (is_array($aContacts))
		{
			foreach ($aContacts as $oContact)
			{
				if ($oContact)
				{
					$this->oFormatter->setContainer($oContact);
					$this->oFormatter->form();
					$sResult .= $this->oFormatter->getValue();
				}
			}
		}

		return $sResult;
	}

	/**
	 * @param int $iUserId
	 * @param int $iTenantId
	 * @param string $sTempFileName
	 * @param int $iParsedCount
	 * @param string $sStorage
	 * @param string $sGroupUUID
	 *
	 * @return int
	 */
	public function Import($iUserId, $iTenantId, $sTempFileName, &$iParsedCount, $sStorage = '', $sGroupUUID = '')
	{
		$iCount = -1;
		$iParsedCount = 0;
		if (file_exists($sTempFileName))
		{
			$aCsv = api_Utils::CsvToArray($sTempFileName);
			if (is_array($aCsv))
			{
				$iCount = 0;
				foreach ($aCsv as $aCsvItem)
				{
					set_time_limit(30);

					$this->oParser->reset();

					$oContact = \CContact::createInstance();
					$oContact->IdUser = $iUserId;

					$this->oParser->setContainer($aCsvItem);
					$aParameters = $this->oParser->getParameters();

					foreach ($aParameters as $sPropertyName => $mValue)
					{
						if (isset($oContact->{$sPropertyName}))
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

					$oContact->IdTenant = $iTenantId;
					$oContact->Storage = $sStorage;
					
					$oContact->SetViewEmail();
					$oContact->AddGroup($sGroupUUID);

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
