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
			$aCsv = $this->csvToArray($sTempFileName);
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
	
	/**
	 * @return string $sFileName
	 * @return string
	 */
	private static function csvToArray($sFileName)
	{
		if (!file_exists($sFileName) || !is_readable($sFileName))
		{
			return false;
		}

		$aHeaders = null;
		$aData = array();

		@setlocale(LC_CTYPE, 'en_US.UTF-8');
		\ini_set('auto_detect_line_endings', true);
		
		if (false !== ($rHandle = @fopen($sFileName, 'rb')))
		{
			$sDelimiterSearchString = @fread($rHandle, 2000);
			rewind($rHandle);

			$sDelimiter = (
				(int) substr_count($sDelimiterSearchString, ',') > (int) substr_count($sDelimiterSearchString, ';'))
					? ',' : ';';

			while (false !== ($mRow = fgetcsv($rHandle, 5000, $sDelimiter, '"')))
			{
				$mRow = preg_replace('/[\r\n]+/', "\n", $mRow);
				if (!is_array($mRow) || count($mRow) === 0 || count($mRow) === 1 && empty($mRow[0]))
				{
					continue;
				}
				if (null === $aHeaders)
				{
					if (3 >= count($mRow))
					{
						CApi::Log('Invalid csv headers');
						CApi::LogObject($mRow);
						fclose($rHandle);
						return $aData;
					}

					$aHeaders = $mRow;
				}
				else
				{
					$aNewItem = array();
					foreach ($aHeaders as $iIndex => $sHeaderValue)
					{
						$aNewItem[@iconv('utf-8', 'utf-8//IGNORE', $sHeaderValue)] =
							isset($mRow[$iIndex]) ? $mRow[$iIndex] : '';
					}

					$aData[] = $aNewItem;
				}
			}

			fclose($rHandle);
		}

		ini_set('auto_detect_line_endings', false);

		return $aData;
	}
}
