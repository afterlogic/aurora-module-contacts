<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AGPL-3.0 or AfterLogic Software License
 *
 * This code is licensed under AGPLv3 license or AfterLogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Contacts;

/**
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
	public $oApiContactsManager = null;

	protected $aImportExportFormats = ['csv', 'vcf'];
	
	/**
	 * Initializes Contacts Module.
	 * 
	 * @ignore
	 */
	public function init() 
	{
		$this->incClass('contact');
		$this->incClass('group-contact');
		$this->incClass('group');
		$this->incClass('enum');
		$this->incClass('vcard-helper');

		$this->oApiContactsManager = $this->GetManager();
		
		$this->subscribeEvent('Mail::AfterUseEmails', array($this, 'onAfterUseEmails'));
		$this->subscribeEvent('Mail::GetBodyStructureParts', array($this, 'onGetBodyStructureParts'));
		$this->subscribeEvent('Mail::ExtendMessageData', array($this, 'onExtendMessageData'));
		$this->subscribeEvent('MobileSync::GetInfo', array($this, 'onGetMobileSyncInfo'));
		$this->subscribeEvent('AdminPanelWebclient::DeleteEntity::before', array($this, 'onBeforeDeleteEntity'));
		
		$this->extendObject('CUser', array(
				'ContactsPerPage' => array('int', $this->getConfig('ContactsPerPage', 20)),
			)
		);
	}
	
	/***** public functions *****/
	/**
	 * Returns API contacts manager.
	 * @return \CApiContactsManager
	 */
	public function GetApiContactsManager()
	{
		return $this->oApiContactsManager;
	}
	/***** public functions *****/
	
	/***** public functions might be called with web API *****/
	/**
	 * @apiDefine Contacts Contacts Module
	 * Main Contacts module. It provides PHP and Web APIs for managing contacts.
	 */
	
	/**
	 * @api {post} ?/Api/ GetSettings
	 * @apiName GetSettings
	 * @apiGroup Contacts
	 * @apiDescription Obtains list of module settings for authenticated user.
	 * 
	 * @apiHeader {string} [Authorization] "Bearer " + Authentication token which was received as the result of Core.Login method.
	 * @apiHeaderExample {json} Header-Example:
	 *	{
	 *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
	 *	}
	 * 
	 * @apiParam {string=Contacts} Module Module name
	 * @apiParam {string=GetSettings} Method Method name
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'GetSettings'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name.
	 * @apiSuccess {string} Result.Method Method name.
	 * @apiSuccess {mixed} Result.Result List of module settings in case of success, otherwise **false**.
	 * @apiSuccess {int} Result.Result.ContactsPerPage=20 Count of contacts that will be displayed on one page.
	 * @apiSuccess {string} Result.Result.ImportContactsLink=&quot;&quot; Link for learning more about CSV format.
	 * @apiSuccess {array} Result.Result.Storages='[]' List of storages wich will be shown in the interface.
	 * @apiSuccess {array} Result.Result.ImportExportFormats='[]' List of formats that can be used for import and export contacts.
	 * @apiSuccess {array} Result.Result.EContactsPrimaryEmail='[]' Enumeration with primary email values.
	 * @apiSuccess {array} Result.Result.EContactsPrimaryPhone='[]' Enumeration with primary phone values.
	 * @apiSuccess {array} Result.Result.EContactsPrimaryAddress='[]' Enumeration with primary address values.
	 * @apiSuccess {array} Result.Result.EContactSortField='[]' Enumeration with sort field values.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'GetSettings',
	 *	Result: { ContactsPerPage: 20, ImportContactsLink: '', Storages: ['personal', 'team'],
	 * ImportExportFormats: ['csv', 'vcf'], EContactsPrimaryEmail: {'Personal': 0, 'Business': 1, 'Other': 2},
	 * EContactsPrimaryPhone: {'Mobile': 0, 'Personal': 1, 'Business': 2},
	 * EContactsPrimaryAddress: {'Personal': 0, 'Business': 1},
	 * EContactSortField: {'Name': 1, 'Email': 2, 'Frequency': 3} }
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'GetSettings',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	
	/**
	 * Obtains list of module settings for authenticated user.
	 * @return array
	 */
	public function GetSettings()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		$aStorages = array();
		$this->broadcastEvent('GetStorage', $aStorages);
		
		$oUser = \Aurora\System\Api::getAuthenticatedUser();
		$ContactsPerPage = $this->getConfig('ContactsPerPage', 20);
		if ($oUser && $oUser->Role === \EUserRole::NormalUser && isset($oUser->{$this->GetName().'::ContactsPerPage'}))
		{
			$ContactsPerPage = $oUser->{$this->GetName().'::ContactsPerPage'};
		}
		
		return array(
			'ContactsPerPage' => $ContactsPerPage,
			'ImportContactsLink' => $this->getConfig('ImportContactsLink', ''),
			'Storages' => $aStorages,
			'EContactsPrimaryEmail' => (new \EContactsPrimaryEmail)->getMap(),
			'EContactsPrimaryPhone' => (new \EContactsPrimaryPhone)->getMap(),
			'EContactsPrimaryAddress' => (new \EContactsPrimaryAddress)->getMap(),
			'EContactSortField' => (new \EContactSortField)->getMap(),
			'ImportExportFormats' => $this->aImportExportFormats,
			'SaveVcfServerModuleName' => \Aurora\System\Api::GetModuleManager()->ModuleExists('DavContacts') ? 'DavContacts' : ''
		);
	}
	
	/**
	 * @api {post} ?/Api/ UpdateSettings
	 * @apiName UpdateSettings
	 * @apiGroup Contacts
	 * @apiDescription Updates module's settings - saves them to config.json file.
	 * 
	 * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
	 * @apiHeaderExample {json} Header-Example:
	 *	{
	 *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
	 *	}
	 * 
	 * @apiParam {string=Contacts} Module Module name
	 * @apiParam {string=UpdateSettings} Method Method name
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **ContactsPerPage** *int* Count of contacts per page.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'UpdateSettings',
	 *	Parameters: '{ ContactsPerPage: 10 }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name
	 * @apiSuccess {string} Result.Method Method name
	 * @apiSuccess {bool} Result.Result Indicates if settings were updated successfully.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'UpdateSettings',
	 *	Result: true
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'UpdateSettings',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	
	/**
	 * Updates module's settings - saves them to config.json file or to user settings in db.
	 * @param int $ContactsPerPage Count of contacts per page.
	 * @return boolean
	 */
	public function UpdateSettings($ContactsPerPage)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oUser = \Aurora\System\Api::getAuthenticatedUser();
		if ($oUser)
		{
			if ($oUser->Role === \EUserRole::NormalUser)
			{
				$oCoreDecorator = \Aurora\System\Api::GetModuleDecorator('Core');
				$oUser->{$this->GetName().'::ContactsPerPage'} = $ContactsPerPage;
				return $oCoreDecorator->UpdateUserObject($oUser);
			}
			if ($oUser->Role === \EUserRole::SuperAdmin)
			{
				$oSettings =&\Aurora\System\Api::GetSettings();
				$oSettings->SetConf('ContactsPerPage', $ContactsPerPage);
				return $oSettings->Save();
			}
		}
		
		return false;
	}
	
	/**
	 * @api {post} ?/Api/ Export
	 * @apiName Export
	 * @apiGroup Contacts
	 * @apiDescription Exports specified contacts to a file with specified format.
	 * 
	 * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
	 * @apiHeaderExample {json} Header-Example:
	 *	{
	 *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
	 *	}
	 * 
	 * @apiParam {string=Contacts} Module Module name
	 * @apiParam {string=Export} Method Method name
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **Format** *string* File format that should be used for export.<br>
	 * &emsp; **Filters** *array* Filters for obtaining specified contacts.<br>
	 * &emsp; **GroupUUID** *string* UUID of group that should contain contacts for export.<br>
	 * &emsp; **ContactUUIDs** *array* List of UUIDs of contacts that should be exported.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'Export',
	 *	Parameters: '{ Format: "csv", Filters: [], GroupUUID: "", ContactUUIDs: [] }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name
	 * @apiSuccess {string} Result.Method Method name
	 * @apiSuccess {mixed} Result.Result Contents of CSV or VCF file in case of success, otherwise **false**.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * contents of CSV or VCF file 
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'Export',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	
	/**
	 * Exports specified contacts to a file with specified format.
	 * @param string $Format File format that should be used for export.
	 * @param array $Filters Filters for obtaining specified contacts.
	 * @param string $GroupUUID UUID of group that should contain contacts for export.
	 * @param array $ContactUUIDs List of UUIDs of contacts that should be exported.
	 */
	public function Export($Format, $Filters = [], $GroupUUID = '', $ContactUUIDs = [])
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$aFilters = $this->prepareFilters($Filters);
		$aFilters = ['$OR' => $aFilters];
		
		$aContacts = $this->oApiContactsManager->getContacts(\EContactSortField::Name, \ESortOrder::ASC, 0, 0, $aFilters, $GroupUUID, $ContactUUIDs);
		
		$sOutput = '';
		
		if (is_array($aContacts))
		{
			switch ($Format)
			{
				case 'csv':
					$this->incClass('csv-formatter');
					$this->incClass('csv-parser');
					$this->incClass('csv-sync');

					if (class_exists('CApiContactsSyncCsv'))
					{
						$oSync = new \CApiContactsSyncCsv();
						$sOutput = $oSync->Export($aContacts);
					}
					break;
				case 'vcf':
					foreach ($aContacts as $oContact)
					{
						$oVCard = new \Sabre\VObject\Component\VCard();
						\CApiContactsVCardHelper::UpdateVCardFromContact($oContact, $oVCard);
						$sOutput .= $oVCard->serialize();
					}
					break;
			}
		}
		
		if (is_string($sOutput) && !empty($sOutput))
		{
			header('Pragma: public');
			header('Content-Type: text/csv');
			header('Content-Disposition: attachment; filename="export.' . $Format . '";');
			header('Content-Transfer-Encoding: binary');
		}
		
		echo $sOutput;
	}
	
	/**
	 * @api {post} ?/Api/ GetGroups
	 * @apiName GetGroups
	 * @apiGroup Contacts
	 * @apiDescription Returns all groups for authenticated user.
	 * 
	 * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
	 * @apiHeaderExample {json} Header-Example:
	 *	{
	 *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
	 *	}
	 * 
	 * @apiParam {string=Contacts} Module Module name
	 * @apiParam {string=GetGroups} Method Method name
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'GetGroups'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name
	 * @apiSuccess {string} Result.Method Method name
	 * @apiSuccess {mixed} Result.Result List of groups in case of success, otherwise **false**.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'GetGroups',
	 *	Result: [{ City: '', Company: '', Contacts: [], Country: '', Email: '', Fax: '', IdUser: 3,
	 * IsOrganization: false, Name: 'group_name', Phone: '', State: '', Street: '', UUID: 'uuid_value',
	 * Web: '', Zip: '' }]
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'GetGroups',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	
	/**
	 * Returns all groups for authenticated user.
	 * @return array
	 */
	public function GetGroups()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$iUserId = \Aurora\System\Api::getAuthenticatedUserId();
		
		return $this->oApiContactsManager->getGroups($iUserId);
	}
	
	/**
	 * @api {post} ?/Api/ GetGroup
	 * @apiName GetGroup
	 * @apiGroup Contacts
	 * @apiDescription Returns group with specified UUID.
	 * 
	 * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
	 * @apiHeaderExample {json} Header-Example:
	 *	{
	 *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
	 *	}
	 * 
	 * @apiParam {string=Contacts} Module Module name
	 * @apiParam {string=GetGroup} Method Method name
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **$UUID** *string* UUID of group to return.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'GetGroup',
	 *	Parameters: '{ UUID: "group_uuid" }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name
	 * @apiSuccess {string} Result.Method Method name
	 * @apiSuccess {mixed} Result.Result Group object in case of success, otherwise **false**.
	 * @apiSuccess {string} Result.Result.City=&quot;&quot;
	 * @apiSuccess {string} Result.Result.Company=&quot;&quot;
	 * @apiSuccess {array} Result.Result.Contacts='[]'
	 * @apiSuccess {string} Result.Result.Country=&quot;&quot;
	 * @apiSuccess {string} Result.Result.Email=&quot;&quot;
	 * @apiSuccess {string} Result.Result.Fax=&quot;&quot;
	 * @apiSuccess {int} Result.Result.IdUser=0
	 * @apiSuccess {bool} Result.Result.IsOrganization=false
	 * @apiSuccess {string} Result.Result.Name=&quot;&quot;
	 * @apiSuccess {string} Result.Result.Phone=&quot;&quot;
	 * @apiSuccess {string} Result.Result.Street=&quot;&quot;
	 * @apiSuccess {string} Result.Result.UUID=&quot;&quot;
	 * @apiSuccess {string} Result.Result.Web=&quot;&quot;
	 * @apiSuccess {string} Result.Result.Zip=&quot;&quot;
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'GetGroup',
	 *	Result: { City: '', Company: 'group_company', Contacts: [], Country: '', Email: '', Fax: '',
	 * IdUser: 3, IsOrganization: true, Name: 'group_name', Phone:'', State:'', Street:'',
	 * UUID: 'group_uuid', Web:'', Zip: '' }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'GetGroup',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	
	/**
	 * Returns group with specified UUID.
	 * @param string $UUID UUID of group to return.
	 * @return \CGroup
	 */
	public function GetGroup($UUID)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		return $this->oApiContactsManager->getGroup($UUID);
	}
	
	/**
	 * @api {post} ?/Api/ GetContacts
	 * @apiName GetContacts
	 * @apiGroup Contacts
	 * @apiDescription Returns list of contacts for specified parameters.
	 * 
	 * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
	 * @apiHeaderExample {json} Header-Example:
	 *	{
	 *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
	 *	}
	 * 
	 * @apiParam {string=Contacts} Module Module name
	 * @apiParam {string=GetContacts} Method Method name
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **Offset** *int* Offset of contacts list.<br>
	 * &emsp; **Limit** *int* Limit of result contacts list.<br>
	 * &emsp; **SortField** *int* Name of field order by.<br>
	 * &emsp; **SortOrder** *int* Sorting direction.<br>
	 * &emsp; **Storage** *string* Storage value.<br>
	 * &emsp; **Search** *string* Search string.<br>
	 * &emsp; **GroupUUID** *string* UUID of group that should contain all returned contacts.<br>
	 * &emsp; **Filters** *array* Other conditions for obtaining contacts list.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'GetContacts',
	 *	Parameters: '{ Offset: 0, Limit: 20, SortField: 1, SortOrder: 0, Storage: "personal",
	 *		Search: "", GroupUUID: "", Filters: [] }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name
	 * @apiSuccess {string} Result.Method Method name
	 * @apiSuccess {mixed} Result.Result Object with contacts data in case of success, otherwise **false**.
	 * @apiSuccess {int} Result.Result.ContactCount Count of contacts that are obtained with specified conditions.
	 * @apiSuccess {array} Result.Result.List List of contacts objects.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'GetContacts',
	 *	Result: '{ "ContactCount": 6, "List": [{ "UUID": "contact_uuid", "IdUser": 3, "Name": "",
	 *		"Email": "contact@email.com", "Storage": "personal" }] }'
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'GetContacts',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	
	/**
	 * Returns list of contacts for specified parameters.
	 * @param int $Offset Offset of contacts list.
	 * @param int $Limit Limit of result contacts list.
	 * @param int $SortField Name of field order by.
	 * @param int $SortOrder Sorting direction.
	 * @param string $Search Search string.
	 * @param string $GroupUUID UUID of group that should contain all returned contacts.
	 * @param array $Filters Other conditions for obtaining contacts list.
	 * @return array
	 */
	public function GetContacts($Offset = 0, $Limit = 20, $SortField = \EContactSortField::Name, $SortOrder = \ESortOrder::ASC, $Search = '', $GroupUUID = '', $Filters = array())
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);

		$aFilters = $this->prepareFilters($Filters);
		
		if (!empty($Search))
		{
			$aSearchFilters = [
				'FullName' => ['%'.$Search.'%', 'LIKE'],
				'PersonalEmail' => ['%'.$Search.'%', 'LIKE'],
				'BusinessEmail' => ['%'.$Search.'%', 'LIKE'],
				'OtherEmail' => ['%'.$Search.'%', 'LIKE'],
			];
			if (count($aFilters) > 0)
			{
				$aFilters = [
					'$AND' => [
						'1$OR' => $aFilters, 
						'2$OR' => $aSearchFilters
					]
				];
			}
			else
			{
				$aFilters = [
					'$OR' => $aSearchFilters
				];
			}
		}
		elseif (count($aFilters) > 1)
		{
			$aFilters = ['$OR' => $aFilters];
		}
		
		if ($SortField === \EContactSortField::Frequency)
		{
			$aFilters['Frequency'] = ['-1', '!='];
			$aFilters = [
				'$AND' => $aFilters
			];
		}

		$iCount = $this->oApiContactsManager->getContactsCount($aFilters, $GroupUUID);
		$aContacts = $this->oApiContactsManager->getContacts($SortField, $SortOrder, $Offset, $Limit, $aFilters, $GroupUUID);
		
		$aList = array();
		if (is_array($aContacts))
		{
			foreach ($aContacts as $oContact)
			{
				$aList[] = array(
					'UUID' => $oContact->UUID,
					'IdUser' => $oContact->IdUser,
					'FullName' => $oContact->FullName,
					'ViewEmail' => $oContact->ViewEmail,
					'Storage' => $oContact->Storage,
					'Frequency' => $oContact->Frequency,
				);
			}
		}

		return array(
			'ContactCount' => $iCount,
			'List' => \Aurora\System\Managers\Response::GetResponseObject($aList)
		);		
	}	
	
	/**
	 * @api {post} ?/Api/ GetContact
	 * @apiName GetContact
	 * @apiGroup Contacts
	 * @apiDescription Returns contact with specified UUID.
	 * 
	 * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
	 * @apiHeaderExample {json} Header-Example:
	 *	{
	 *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
	 *	}
	 * 
	 * @apiParam {string=Contacts} Module Module name
	 * @apiParam {string=GetContact} Method Method name
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **UUID** *string* UUID of contact to return.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'GetContact',
	 *	Parameters: '{ UUID: "contact_uuid" }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name
	 * @apiSuccess {string} Result.Method Method name
	 * @apiSuccess {mixed} Result.Result Object with contact data in case of success, otherwise **false**.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'GetContact',
	 *	Result: '{ "IdUser": 3, "UUID": "group_uuid", "Storage": "personal", "FullName": "", "PrimaryEmail": 0,
	 * "PrimaryPhone": 1, "PrimaryAddress": 0, "FirstName": "", "LastName": "", "NickName": "", "Skype": "",
	 * "Facebook": "", "PersonalEmail": "contact@email.com", "PersonalAddress": "", "PersonalCity": "",
	 * "PersonalState": "", "PersonalZip": "", "PersonalCountry": "", "PersonalWeb": "", "PersonalFax": "",
	 * "PersonalPhone": "", "PersonalMobile": "123-234-234", "BusinessEmail": "", "BusinessCompany": "",
	 * "BusinessAddress": "", "BusinessCity": "", "BusinessState": "", "BusinessZip": "", "BusinessCountry": "",
	 * "BusinessJobTitle": "", "BusinessDepartment": "", "BusinessOffice": "", "BusinessPhone": "",
	 * "BusinessFax": "", "BusinessWeb": "", "OtherEmail": "", "Notes": "", "BirthDay": 0, "BirthMonth": 0,
	 * "BirthYear": 0, "ETag": "", "GroupUUIDs": ["group1_uuid", "group2_uuid"] }'
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'GetContact',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	
	/**
	 * Returns contact with specified UUID.
	 * @param string $UUID UUID of contact to return.
	 * @return \CContact
	 */
	public function GetContact($UUID)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		return $this->oApiContactsManager->getContact($UUID);
	}

	/**
	 * @api {post} ?/Api/ GetContactsByEmails
	 * @apiName GetContactsByEmails
	 * @apiGroup Contacts
	 * @apiDescription Returns list of contacts with specified emails.
	 * 
	 * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
	 * @apiHeaderExample {json} Header-Example:
	 *	{
	 *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
	 *	}
	 * 
	 * @apiParam {string=Contacts} Module Module name
	 * @apiParam {string=GetContactsByEmails} Method Method name
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **Emails** *array* List of emails of contacts to return.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'GetContactsByEmails',
	 *	Parameters: '{ Emails: ["contact@email.com"] }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name
	 * @apiSuccess {string} Result.Method Method name
	 * @apiSuccess {mixed} Result.Result List of contacts in case of success, otherwise **false**.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'GetContactsByEmails',
	 *	Result: [{ "IdUser": 3, "UUID": "group_uuid", "Storage": "personal", "FullName": "", "PrimaryEmail": 0,
	 * "PrimaryPhone": 1, "PrimaryAddress": 0, "FirstName": "", "LastName": "", "NickName": "", "Skype": "",
	 * "Facebook": "", "PersonalEmail": "contact@email.com", "PersonalAddress": "", "PersonalCity": "",
	 * "PersonalState": "", "PersonalZip": "", "PersonalCountry": "", "PersonalWeb": "", "PersonalFax": "",
	 * "PersonalPhone": "", "PersonalMobile": "123-234-234", "BusinessEmail": "", "BusinessCompany": "",
	 * "BusinessAddress": "", "BusinessCity": "", "BusinessState": "", "BusinessZip": "", "BusinessCountry": "",
	 * "BusinessJobTitle": "", "BusinessDepartment": "", "BusinessOffice": "", "BusinessPhone": "",
	 * "BusinessFax": "", "BusinessWeb": "", "OtherEmail": "", "Notes": "", "BirthDay": 0, "BirthMonth": 0,
	 * "BirthYear": 0, "ETag": "", "GroupUUIDs": ["group1_uuid", "group2_uuid"] }]
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'GetContactsByEmails',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	
	/**
	 * Returns list of contacts with specified emails.
	 * @param array $Emails List of emails of contacts to return.
	 * @return array
	 */
	public function GetContactsByEmails($Emails)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oUser = \Aurora\System\Api::getAuthenticatedUser();
		
		$aFilters = [
			'$AND' => [
				'IdUser' => [$oUser->EntityId, '='],
				'ViewEmail' => [$Emails, 'IN'],
				'Auto' => [false, '=']
			]
		];
		
		$aContacts = $this->oApiContactsManager->getContacts(\EContactSortField::Name, \ESortOrder::ASC, 0, 0, $aFilters, 0);
		
		return $aContacts;
	}	
	
	/**
	 * @api {post} ?/Api/ CreateContact
	 * @apiName CreateContact
	 * @apiGroup Contacts
	 * @apiDescription Creates contact with specified parameters.
	 * 
	 * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
	 * @apiHeaderExample {json} Header-Example:
	 *	{
	 *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
	 *	}
	 * 
	 * @apiParam {string=Contacts} Module Module name
	 * @apiParam {string=CreateContact} Method Method name
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **Contact** *object* Parameters of contact to create.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'CreateContact',
	 *	Parameters: '{ "Contact": { "UUID": "", "PrimaryEmail": 0, "PrimaryPhone": 0, "PrimaryAddress": 0,
	 * "FullName": "second", "FirstName": "", "LastName": "", "NickName": "", "Storage": "personal",
	 * "ItsMe": false, "Skype": "", "Facebook": "", "PersonalEmail": "contact2@email.com", "PersonalAddress": "",
	 * "PersonalCity": "", "PersonalState": "", "PersonalZip": "", "PersonalCountry": "", "PersonalWeb": "",
	 * "PersonalFax": "", "PersonalPhone": "", "PersonalMobile": "", "BusinessEmail": "", "BusinessCompany": "",
	 * "BusinessJobTitle": "", "BusinessDepartment": "", "BusinessOffice": "", "BusinessAddress": "",
	 * "BusinessCity": "", "BusinessState": "", "BusinessZip": "", "BusinessCountry": "", "BusinessFax": "",
	 * "BusinessPhone": "", "BusinessWeb": "", "OtherEmail": "", "Notes": "", "ETag": "", "BirthDay": 0,
	 * "BirthMonth": 0, "BirthYear": 0, "GroupUUIDs": [] } }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name
	 * @apiSuccess {string} Result.Method Method name
	 * @apiSuccess {mixed} Result.Result New contact UUID in case of success, otherwise **false**.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'CreateContact',
	 *	Result: 'new_contact_uuid'
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'CreateContact',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	
	/**
	 * Creates contact with specified parameters.
	 * @param array $Contact Parameters of contact to create.
	 * @param int $iUserId Identifier of user that should own a new contact.
	 * @return bool|string
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function CreateContact($Contact, $iUserId = 0)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oUser = \Aurora\System\Api::getAuthenticatedUser();
		
		if ($iUserId > 0 && $iUserId !== $oUser->EntityId)
		{
			\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::SuperAdmin);
			
			$oCoreDecorator = \Aurora\System\Api::GetModuleDecorator('Core');
			if ($oCoreDecorator)
			{
				$oUser = $oCoreDecorator->GetUser($iUserId);
			}
		}
		
		$oContact = \CContact::createInstance('CContact', $this->GetName());
		$oContact->populate($Contact);
		if ($oUser instanceof \CUser)
		{
			$oContact->IdUser = $oUser->EntityId;
			$oContact->IdTenant = $oUser->IdTenant;
		}

		$mResult = $this->oApiContactsManager->createContact($oContact);
		return $mResult && $oContact ? $oContact->UUID : false;
	}	
	
	/**
	 * @api {post} ?/Api/ UpdateContact
	 * @apiName UpdateContact
	 * @apiGroup Contacts
	 * @apiDescription Updates contact with specified parameters.
	 * 
	 * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
	 * @apiHeaderExample {json} Header-Example:
	 *	{
	 *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
	 *	}
	 * 
	 * @apiParam {string=Contacts} Module Module name
	 * @apiParam {string=UpdateContact} Method Method name
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **Contact** *array* Parameters of contact to update.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'UpdateContact',
	 *	Parameters: '{ "Contact": { "UUID": "contact2_uuid", "PrimaryEmail": 0, "PrimaryPhone": 0,
	 * "PrimaryAddress": 0, "FullName": "contact2", "FirstName": "", "LastName": "", "NickName": "",
	 * "Storage": "personal", "ItsMe": false, "Skype": "", "Facebook": "", "PersonalEmail": "contact2@email.com",
	 * "PersonalAddress": "", "PersonalCity": "", "PersonalState": "", "PersonalZip": "", "PersonalCountry": "",
	 * "PersonalWeb": "", "PersonalFax": "", "PersonalPhone": "", "PersonalMobile": "", "BusinessEmail": "",
	 * "BusinessCompany": "", "BusinessJobTitle": "", "BusinessDepartment": "", "BusinessOffice": "",
	 * "BusinessAddress": "", "BusinessCity": "", "BusinessState": "", "BusinessZip": "", "BusinessCountry": "",
	 * "BusinessFax": "", "BusinessPhone": "", "BusinessWeb": "", "OtherEmail": "", "Notes": "", "ETag": "",
	 * "BirthDay": 0, "BirthMonth": 0, "BirthYear": 0, "GroupUUIDs": [] } }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name
	 * @apiSuccess {string} Result.Method Method name
	 * @apiSuccess {bool} Result.Result Indicates if contact was updated successfully.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'UpdateContact',
	 *	Result: true
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'UpdateContact',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	
	/**
	 * Updates contact with specified parameters.
	 * @param array $Contact Parameters of contact to update.
	 * @return bool
	 */
	public function UpdateContact($Contact)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oContact = $this->oApiContactsManager->getContact($Contact['UUID']);
		if ($oContact)
		{
			$oContact->populate($Contact);
			return $this->oApiContactsManager->updateContact($oContact);
		}
		
		return false;
	}
	
	/**
	 * @api {post} ?/Api/ DeleteContacts
	 * @apiName DeleteContacts
	 * @apiGroup Contacts
	 * @apiDescription Deletes contacts with specified UUIDs.
	 * 
	 * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
	 * @apiHeaderExample {json} Header-Example:
	 *	{
	 *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
	 *	}
	 * 
	 * @apiParam {string=Contacts} Module Module name
	 * @apiParam {string=DeleteContacts} Method Method name
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **UUIDs** *array* Array of strings - UUIDs of contacts to delete.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'DeleteContacts',
	 *	Parameters: '{ UUIDs: ["uuid1", "uuid"] }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name
	 * @apiSuccess {string} Result.Method Method name
	 * @apiSuccess {bool} Result.Result Indicates if contacts were deleted successfully.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'DeleteContacts',
	 *	Result: true
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'DeleteContacts',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	
	/**
	 * Deletes contacts with specified UUIDs.
	 * @param array $UUIDs Array of strings - UUIDs of contacts to delete.
	 * @return bool
	 */
	public function DeleteContacts($UUIDs)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		return $this->oApiContactsManager->deleteContacts($UUIDs);
	}	
	
	/**
	 * @api {post} ?/Api/ CreateGroup
	 * @apiName CreateGroup
	 * @apiGroup Contacts
	 * @apiDescription Creates group with specified parameters.
	 * 
	 * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
	 * @apiHeaderExample {json} Header-Example:
	 *	{
	 *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
	 *	}
	 * 
	 * @apiParam {string=Contacts} Module Module name
	 * @apiParam {string=CreateGroup} Method Method name
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **Group** *object* Parameters of group to create.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'CreateGroup',
	 *	Parameters: '{ "Group": { "UUID": "", "Name": "new_group_name", "IsOrganization": "0", "Email": "",
	 * "Country": "", "City": "", "Company": "", "Fax": "", "Phone": "", "State": "", "Street": "",
	 * "Web": "", "Zip": "", "Contacts": [] } }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name
	 * @apiSuccess {string} Result.Method Method name
	 * @apiSuccess {mixed} Result.Result New group UUID in case of success, otherwise **false**.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'CreateGroup',
	 *	Result: 'new_group_uuid'
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'CreateGroup',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	
	/**
	 * Creates group with specified parameters.
	 * @param array $Group Parameters of group to create.
	 * @return string|bool
	 */
	public function CreateGroup($Group)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oGroup = \CGroup::createInstance('CGroup', $this->GetName());
		$oGroup->IdUser = \Aurora\System\Api::getAuthenticatedUserId();

		$oGroup->populate($Group);

		$this->oApiContactsManager->createGroup($oGroup);
		return $oGroup ? $oGroup->UUID : false;
	}	
	
	/**
	 * @api {post} ?/Api/ UpdateGroup
	 * @apiName UpdateGroup
	 * @apiGroup Contacts
	 * @apiDescription Updates group with specified parameters.
	 * 
	 * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
	 * @apiHeaderExample {json} Header-Example:
	 *	{
	 *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
	 *	}
	 * 
	 * @apiParam {string=Contacts} Module Module name
	 * @apiParam {string=UpdateGroup} Method Method name
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **Group** *object* Parameters of group to update.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'UpdateGroup',
	 *	Parameters: '{ "Group": { "UUID": "group_uuid", "Name": "group_name", "IsOrganization": "0",
	 * "Email": "", "Country": "", "City": "", "Company": "", "Fax": "", "Phone": "", "State": "",
	 * "Street": "", "Web": "", "Zip": "", "Contacts": [] } }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name
	 * @apiSuccess {string} Result.Method Method name
	 * @apiSuccess {bool} Result.Result Indicates if group was updated successfully.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'UpdateGroup',
	 *	Result: true
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'UpdateGroup',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	
	/**
	 * Updates group with specified parameters.
	 * @param array $Group Parameters of group to update.
	 * @return boolean
	 */
	public function UpdateGroup($Group)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oGroup = $this->oApiContactsManager->getGroup($Group['UUID']);
		if ($oGroup)
		{
			$oGroup->populate($Group);
			return $this->oApiContactsManager->updateGroup($oGroup);
		}

		return false;
	}	
	
	/**
	 * @api {post} ?/Api/ DeleteGroup
	 * @apiName DeleteGroup
	 * @apiGroup Contacts
	 * @apiDescription Deletes group with specified UUID.
	 * 
	 * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
	 * @apiHeaderExample {json} Header-Example:
	 *	{
	 *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
	 *	}
	 * 
	 * @apiParam {string=Contacts} Module Module name
	 * @apiParam {string=DeleteGroup} Method Method name
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **UUID** *string* UUID of group to delete.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'DeleteGroup',
	 *	Parameters: '{ UUID: "group_uuid" }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name
	 * @apiSuccess {string} Result.Method Method name
	 * @apiSuccess {bool} Result.Result Indicates if group was deleted successfully.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'DeleteGroup',
	 *	Result: true
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'DeleteGroup',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	
	/**
	 * Deletes group with specified UUID.
	 * @param string $UUID UUID of group to delete.
	 * @return bool
	 */
	public function DeleteGroup($UUID)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		return $this->oApiContactsManager->deleteGroups([$UUID]);
	}
	
	/**
	 * @api {post} ?/Api/ AddContactsToGroup
	 * @apiName AddContactsToGroup
	 * @apiGroup Contacts
	 * @apiDescription Adds specified contacts to specified group.
	 * 
	 * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
	 * @apiHeaderExample {json} Header-Example:
	 *	{
	 *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
	 *	}
	 * 
	 * @apiParam {string=Contacts} Module Module name
	 * @apiParam {string=AddContactsToGroup} Method Method name
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **GroupUUID** *string* UUID of group.<br>
	 * &emsp; **ContactUUIDs** *array* Array of strings - UUIDs of contacts to add to group.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'AddContactsToGroup',
	 *	Parameters: '{ GroupUUID: "group_uuid", ContactUUIDs: ["contact1_uuid", "contact2_uuid"] }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name
	 * @apiSuccess {string} Result.Method Method name
	 * @apiSuccess {bool} Result.Result Indicates if contacts were successfully added to group.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'AddContactsToGroup',
	 *	Result: true
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'AddContactsToGroup',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	
	/**
	 * Adds specified contacts to specified group.
	 * @param string $GroupUUID UUID of group.
	 * @param array $ContactUUIDs Array of strings - UUIDs of contacts to add to group.
	 * @return boolean
	 */
	public function AddContactsToGroup($GroupUUID, $ContactUUIDs)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		if (is_array($ContactUUIDs) && !empty($ContactUUIDs))
		{
			return $this->oApiContactsManager->addContactsToGroup($GroupUUID, $ContactUUIDs);
		}
		
		return true;
	}
	
	/**
	 * @api {post} ?/Api/ RemoveContactsFromGroup
	 * @apiName RemoveContactsFromGroup
	 * @apiGroup Contacts
	 * @apiDescription Removes specified contacts from specified group.
	 * 
	 * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
	 * @apiHeaderExample {json} Header-Example:
	 *	{
	 *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
	 *	}
	 * 
	 * @apiParam {string=Contacts} Module Module name
	 * @apiParam {string=RemoveContactsFromGroup} Method Method name
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **GroupUUID** *string* UUID of group.<br>
	 * &emsp; **ContactUUIDs** *array* Array of strings - UUIDs of contacts to remove from group.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'RemoveContactsFromGroup',
	 *	Parameters: '{ GroupUUID: "group_uuid", ContactUUIDs: ["contact1_uuid", "contact2_uuid"] }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name
	 * @apiSuccess {string} Result.Method Method name
	 * @apiSuccess {bool} Result.Result Indicates if contacts were successfully removed from group.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'RemoveContactsFromGroup',
	 *	Result: true
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'RemoveContactsFromGroup',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	
	/**
	 * Removes specified contacts from specified group.
	 * @param string $GroupUUID UUID of group.
	 * @param array $ContactUUIDs Array of strings - UUIDs of contacts to remove from group.
	 * @return boolean
	 */
	public function RemoveContactsFromGroup($GroupUUID, $ContactUUIDs)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		if (is_array($ContactUUIDs) && !empty($ContactUUIDs))
		{
			return $this->oApiContactsManager->removeContactsFromGroup($GroupUUID, $ContactUUIDs);
		}
		
		return true;
	}	
	
	/**
	 * @api {post} ?/Api/ Import
	 * @apiName Import
	 * @apiGroup Contacts
	 * @apiDescription Imports contacts from file with specified format.
	 * 
	 * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
	 * @apiHeaderExample {json} Header-Example:
	 *	{
	 *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
	 *	}
	 * 
	 * @apiParam {string=Contacts} Module Module name
	 * @apiParam {string=Import} Method Method name
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **UploadData** *array* Array of uploaded file data.<br>
	 * &emsp; **Storage** *string* Storage name.<br>
	 * &emsp; **GroupUUID** *array* Group UUID.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'Import',
	 *	Parameters: '{ "UploadData": { "tmp_name": "tmp_name_value", "name": "name_value" },
	 *		"Storage": "personal", "GroupUUID": "" }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name
	 * @apiSuccess {string} Result.Method Method name
	 * @apiSuccess {mixed} Result.Result Object with counts of imported and parsed contacts in case of success, otherwise **false**.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'Import',
	 *	Result: { "ImportedCount" : 2, "ParsedCount": 3}
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Contacts',
	 *	Method: 'Import',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	
	/**
	 * Imports contacts from file with specified format.
	 * @param array $UploadData Array of uploaded file data.
	 * @param array $GroupUUID Group UUID.
	 * @return array
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function Import($UploadData, $GroupUUID)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$aResponse = array(
			'ImportedCount' => 0,
			'ParsedCount' => 0
		);
		
		$oUser = \Aurora\System\Api::getAuthenticatedUser();

		if (is_array($UploadData))
		{
			$oApiFileCacheManager = \Aurora\System\Api::GetSystemManager('Filecache');
			$sTempFileName = 'import-post-' . md5($UploadData['name'] . $UploadData['tmp_name']);
			if ($oApiFileCacheManager->moveUploadedFile($oUser->UUID, $sTempFileName, $UploadData['tmp_name']))
			{
				$sTempFilePath = $oApiFileCacheManager->generateFullFilePath($oUser->UUID, $sTempFileName);

				$aImportResult = array();
				
				$sFileExtension = strtolower(\Aurora\System\Utils::GetFileExtension($UploadData['name']));
				switch ($sFileExtension)
				{
					case 'csv':
						$this->incClass('csv-formatter');
						$this->incClass('csv-parser');
						$this->incClass('csv-sync');

						if (class_exists('CApiContactsSyncCsv'))
						{
							$oSync = new \CApiContactsSyncCsv();
							$aImportResult = $oSync->Import($oUser->EntityId, $sTempFilePath, $GroupUUID);
						}
						break;
					case 'vcf':
						$aImportResult = $this->importVcf($oUser->EntityId, $sTempFilePath);
						break;
				}

				if (is_array($aImportResult) && isset($aImportResult['ImportedCount']) && isset($aImportResult['ParsedCount']))
				{
					$aResponse['ImportedCount'] = $aImportResult['ImportedCount'];
					$aResponse['ParsedCount'] = $aImportResult['ParsedCount'];
				}
				else
				{
					throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::IncorrectFileExtension);
				}

				$oApiFileCacheManager->clear($oUser->UUID, $sTempFileName);
			}
			else
			{
				throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::UnknownError);
			}
		}
		else
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::UnknownError);
		}

		return $aResponse;
	}
	
	private function importVcf($iUserId, $sTempFilePath)
	{
		$aImportResult = array(
			'ParsedCount' => 0,
			'ImportedCount' => 0,
		);
		// You can either pass a readable stream, or a string.
		$oHandler = fopen($sTempFilePath, 'r');
		$oSplitter = new \Sabre\VObject\Splitter\VCard($oHandler);
		$oContactsDecorator = \Aurora\System\Api::GetModuleDecorator('Contacts');
		$oApiContactsManager = $oContactsDecorator ? $oContactsDecorator->GetApiContactsManager() : null;
		if ($oApiContactsManager)
		{
			while ($oVCard = $oSplitter->getNext())
			{
				$aContactData = \CApiContactsVCardHelper::GetContactDataFromVcard($oVCard);
				$oContact = isset($aContactData['UUID']) ? $oApiContactsManager->getContact($aContactData['UUID']) : null;
				$aImportResult['ParsedCount']++;
				if (!isset($oContact) || empty($oContact))
				{
					if ($oContactsDecorator->CreateContact($aContactData, $iUserId))
					{
						$aImportResult['ImportedCount']++;
					}
				}
			}
		}
		return $aImportResult;
	}
	
//	public function GetGroupEvents($UUID)
//	{
//		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
//		
//		return [];
//	}	
	
//	public function UpdateSharedContacts($UUIDs)
//	{
//		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
//		return true;
//	}	
	
	public function AddContactsFromFile($File)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);

		if (empty($File))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}

		$oUser = \Aurora\System\Api::getAuthenticatedUser();
		$oApiFileCache = \Aurora\System\Api::GetSystemManager('Filecache');
		
		$sTempFilePath = $oApiFileCache->generateFullFilePath($oUser->UUID, $File);
		$aImportResult = $this->importVcf($oUser->EntityId, $sTempFilePath);

		return is_array($aImportResult) && isset($aImportResult['ImportedCount']) && $aImportResult['ImportedCount'] > 0;
	}	
	/***** public functions might be called with web API *****/
	
	/***** private functions *****/
	private function prepareFilters($aRawFilters)
	{
		$aFilters = [];
		
		if (is_array($aRawFilters))
		{
			$iAndIndex = 1;
			$iOrIndex = 1;
			foreach ($aRawFilters as $aSubFilters)
			{
				if (is_array($aSubFilters))
				{
					foreach ($aSubFilters as $sKey => $a2ndSubFilters)
					{
						if (is_array($a2ndSubFilters))
						{
							$sNewKey = $sKey;
							if ($sKey === '$AND')
							{
								$sNewKey = $iAndIndex.'$AND';
								$iAndIndex++;
							}
							if ($sKey === '$OR')
							{
								$sNewKey = $iOrIndex.'$OR';
								$iOrIndex++;
							}
							$aFilters[$sNewKey] = $a2ndSubFilters;
						}
					}
				}
			}
		}
		
		return $aFilters;
	}
	
	public function onAfterUseEmails($Args, &$Result)
	{
		$aAddresses = $Args['Emails'];
		$iUserId = \Aurora\System\Api::getAuthenticatedUserId();
		foreach ($aAddresses as $sEmail => $sName)
		{
			$oContact = $this->oApiContactsManager->getContactByEmail($iUserId, $sEmail);
			if ($oContact)
			{
				if ($oContact->Frequency !== -1)
				{
					$oContact->Frequency = $oContact->Frequency + 1;
					$this->oApiContactsManager->updateContact($oContact);
				}
			}
			else
			{
				$oContactsDecorator = \Aurora\System\Api::GetModuleDecorator('Contacts');
				if ($oContactsDecorator)
				{
					$oContactsDecorator->CreateContact([
						'FullName' => $sName,
						'PersonalEmail' => $sEmail,
						'Auto' => true,
					]);
				}
			}
		}
	}
	
	public function onGetBodyStructureParts($aParts, &$aResultParts)
	{
		foreach ($aParts as $oPart)
		{
			if ($oPart instanceof \MailSo\Imap\BodyStructure && 
					($oPart->ContentType() === 'text/vcard' || $oPart->ContentType() === 'text/x-vcard'))
			{
				$aResultParts[] = $oPart;
			}
		}
	}
	
	public function onExtendMessageData($aData, &$oMessage)
	{
		$oApiFileCache = \Aurora\System\Api::GetSystemManager('Filecache');
		
		$oUser = \Aurora\System\Api::getAuthenticatedUser();
		
		foreach ($aData as $aDataItem)
		{
			$oPart = $aDataItem['Part'];
			$bVcard = $oPart instanceof \MailSo\Imap\BodyStructure && 
					($oPart->ContentType() === 'text/vcard' || $oPart->ContentType() === 'text/x-vcard');
			$sData = $aDataItem['Data'];
			if ($bVcard && !empty($sData))
			{
				$oContact = \CContact::createInstance('CContact', $this->GetName());
				$oContact->InitFromVCardStr($oUser->EntityId, $sData);

				$oContact->UUID = '';

				$bContactExists = false;
				if (0 < strlen($oContact->ViewEmail))
				{
					$aLocalContacts = $this->GetContactsByEmails([$oContact->ViewEmail]);
					$oLocalContact = count($aLocalContacts) > 0 ? $aLocalContacts[0] : null;
					if ($oLocalContact)
					{
						$oContact->UUID = $oLocalContact->UUID;
						$bContactExists = true;
					}
				}

				$sTemptFile = md5($sData).'.vcf';
				if ($oApiFileCache && $oApiFileCache->put($oUser->UUID, $sTemptFile, $sData))
				{
					$oVcard = \CApiMailVcard::createInstance('CApiMailVcard', $this->GetName());

					$oVcard->Uid = $oContact->UUID;
					$oVcard->File = $sTemptFile;
					$oVcard->Exists = !!$bContactExists;
					$oVcard->Name = $oContact->FullName;
					$oVcard->Email = $oContact->ViewEmail;

					$oMessage->addExtend('VCARD', $oVcard);
				}
				else
				{
					\Aurora\System\Api::Log('Can\'t save temp file "'.$sTemptFile.'"', ELogLevel::Error);
				}					
			}
		}
	}	
	
    public function onGetMobileSyncInfo($aArgs, &$mResult)
	{
		$iUserId = \Aurora\System\Api::getAuthenticatedUserId();
		$oDavModule = \Aurora\System\Api::GetModuleDecorator('Dav');

		$sDavLogin = $oDavModule->GetLogin();
		$sDavServer = $oDavModule->GetServerUrl();

		$mResult['Dav']['Contacts'] = [
			[
				'Name' => $this->i18N('LABEL_PERSONAL_CONTACTS', $iUserId),
				'Url' => $sDavServer.'/addressbooks/'.$sDavLogin.'/'.\Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME
			],
			[
				'Name' => $this->i18N('LABEL_COLLECTED_ADDRESSES', $iUserId),
				'Url' => $sDavServer.'/addressbooks/'.$sDavLogin.'/'.\Afterlogic\DAV\Constants::ADDRESSBOOK_COLLECTED_NAME
			],
			[
				'Name' => $this->i18N('LABEL_SHARED_ADDRESS_BOOK', $iUserId),
				'Url' => $sDavServer.'/addressbooks/'.$sDavLogin.'/'.\Afterlogic\DAV\Constants::ADDRESSBOOK_SHARED_WITH_ALL_NAME
			],
			[
				'Name' => $this->i18N('LABEL_GLOBAL_ADDRESS_BOOK', $iUserId),
				'Url' => $sDavServer.'/gab'
			]
		];
		
		$mResult['Dav']['Contacts'] = array(
			'PersonalContactsUrl' => $sDavServer.'/addressbooks/'.$sDavLogin.'/'.\Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME,
			'CollectedAddressesUrl' => $sDavServer.'/addressbooks/'.$sDavLogin.'/'.\Afterlogic\DAV\Constants::ADDRESSBOOK_COLLECTED_NAME,
			'SharedWithAllUrl' => $sDavServer.'/addressbooks/'.$sDavLogin.'/'.\Afterlogic\DAV\Constants::ADDRESSBOOK_SHARED_WITH_ALL_NAME,
			'GlobalAddressBookUrl' => $sDavServer.'/gab'
		);
	}
	
	public function onBeforeDeleteEntity(&$aArgs, &$mResult)
	{
		if ($aArgs['Type'] === 'User')
		{
			$aGroups = $this->oApiContactsManager->getGroups($aArgs['Id']);
			if (count($aGroups) > 0)
			{
				$aGroupUUIDs = [];
				foreach ($aGroups as $oGroup)
				{
					$aGroupUUIDs[] = $oGroup->UUID;
				}
				$this->oApiContactsManager->deleteGroups($aGroupUUIDs);
			}
		}
	}
	/***** private functions *****/
}
