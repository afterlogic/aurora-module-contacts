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
	protected $oFormatter;

	/**
	 * @var CApiContactsCsvParser
	 */
	protected $oParser;

	public function __construct()
	{
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
	 * @param array $aArgs
	 * @param array $mImportResult
	 */
	public function Import($aArgs, &$mImportResult)
	{
		$iCount = -1;
		$iParsedCount = 0;
		if (file_exists($aArgs['TempFileName']))
		{
			$aCsv = $this->csvToArray($aArgs['TempFileName']);
			if (is_array($aCsv))
			{
				$iCount = 0;
				foreach ($aCsv as $aCsvItem)
				{
					set_time_limit(30);

					$this->oParser->reset();
					$this->oParser->setContainer($aCsvItem);
					$aContactData = $this->oParser->getParameters();

					if (!isset($aContactData['FullName']) || empty($aContactData['FullName']))
					{
						$aFullName = [];
						if (isset($aContactData['FirstName']) && !empty(trim($aContactData['FirstName'])))
						{
							$aFullName[] = trim($aContactData['FirstName']);
						}
						if (isset($aContactData['LastName']) && !empty(trim($aContactData['LastName'])))
						{
							$aFullName[] = trim($aContactData['LastName']);
						}
						if (count($aFullName) > 0)
						{
							$aContactData['FullName'] = join(' ', $aFullName);
						}
					}
					
					if (isset($aContactData['PersonalEmail']) && !empty($aContactData['PersonalEmail']))
					{
						$aContactData['PrimaryEmail'] = \EContactsPrimaryEmail::Personal;
					}
					else if (isset($aContactData['BusinessEmail']) && !empty($aContactData['BusinessEmail']))
					{
						$aContactData['PrimaryEmail'] = \EContactsPrimaryEmail::Business;
					}
					else if (isset($aContactData['OtherEmail']) && !empty($aContactData['OtherEmail']))
					{
						$aContactData['PrimaryEmail'] = \EContactsPrimaryEmail::Other;
					}
					
					if (isset($aContactData['BirthYear']))
					{
						if (strlen($aContactData['BirthYear']) === 2)
						{
							$oDt = DateTime::createFromFormat('y', $aContactData['BirthYear']);
							$aContactData['BirthYear'] = $oDt->format('Y');
						}
						$aContactData['BirthYear'] = (int) $aContactData['BirthYear'];
					}

					if (!empty($aArgs['GroupUUID']))
					{
						$aContactData['GroupUUIDs'] = [$aArgs['GroupUUID']];
					}

					$iParsedCount++;
					
					$oContactsDecorator = \CApi::GetModuleDecorator('Contacts');
					if ($oContactsDecorator && $oContactsDecorator->CreateContact($aContactData, $aArgs['User']->iId))
					{
						$iCount++;
					}

					unset($aContactData, $aCsvItem);
				}
			}
		}
		
		if ($iCount > -1)
		{
			$mImportResult['ParsedCount'] = $iParsedCount;
			$mImportResult['ImportedCount'] = $iCount;
		}
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
