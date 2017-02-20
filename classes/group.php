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
 * @property string $Name
 * @property bool $IsOrganization
 * @property string $Email
 * @property string $Company
 * @property string $Street
 * @property string $City
 * @property string $State
 * @property string $Zip
 * @property string $Country
 * @property string $Phone
 * @property string $Fax
 * @property string $Web
 * @property array $Events
 *
 * @ignore
 * @package Contactsmain
 * @subpackage Classes
 */
class CGroup extends CEntity
{
	public $Events = array();
	
	public $GroupContacts = array();
	
	protected $aStaticMap = array(
		'IdUser'			=> array('int', 0),

		'Name'				=> array('string', ''),
		'IsOrganization'	=> array('bool', false),

		'Email'				=> array('string', ''),
		'Company'			=> array('string', ''),
		'Street'			=> array('string', ''),
		'City'				=> array('string', ''),
		'State'				=> array('string', ''),
		'Zip'				=> array('string', ''),
		'Country'			=> array('string', ''),
		'Phone'				=> array('string', ''),
		'Fax'				=> array('string', ''),
		'Web'				=> array('string', ''),
		'Events'			=> array('string', ''),
	);

	public function populate($aGroup)
	{
		parent::populate($aGroup);
		
		$this->GroupContacts = array();
		if (isset($aGroup['Contacts']) && is_array($aGroup['Contacts']))
		{
			$aContactUUIDs = $aGroup['Contacts'];
			foreach ($aContactUUIDs as $sContactUUID)
			{
				$oGroupContact = \CGroupContact::createInstance();
				$oGroupContact->ContactUUID = $sContactUUID;
				$this->GroupContacts[] = $oGroupContact;
			}
		}
	}

	public function toResponseArray()
	{
		$mResult = null; 
		$oContactsModule = \CApi::GetModule('Contacts');
		 if ($oContactsModule)
		 {
			$aContacts = $oContactsModule->oApiContactsManager->getContacts(
				\EContactSortField::Name, \ESortOrder::ASC, 0, 299, [], $this->UUID
			);

			$mResult = array(
				'IdUser' => $this->IdUser,
				'UUID' => $this->UUID,
				'Name' => $this->Name,

				'IsOrganization' => $this->IsOrganization,
				'Email'		=> $this->Email,
				'Company'	=> $this->Company,
				'Street'	=> $this->Street,
				'City'		=> $this->City,
				'State'		=> $this->State,
				'Zip'		=> $this->Zip,
				'Country'	=> $this->Country,
				'Phone'		=> $this->Phone,
				'Fax'		=> $this->Fax,
				'Web'		=> $this->Web,

				'Contacts' => \CApiResponseManager::GetResponseObject($aContacts)
			);
		 }
		 
		return $mResult;
	}
}
