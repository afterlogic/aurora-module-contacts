<?php

namespace Aurora\Modules\Contacts\Models;

use \Aurora\System\Classes\Model;
class Group extends Model
{
	public $Events = array();

	public $GroupContacts = array();

	protected $fillable = [
		'IdUser',
		'UUID',
		'Name',
		'IsOrganization',
		'Email',
		'Company',
		'Street',
		'City',
		'State',
		'Zip',
		'Country',
		'Phone',
		'Fax',
		'Web',
		'Events'
	];

	public function populate($aGroup)
	{
		parent::populate($aGroup);

		if(!empty($aContact['UUID']))
		{
			$this->UUID = $aContact['UUID'];
		}
		else if(empty($this->UUID))
		{
			$this->UUID = \Sabre\DAV\UUIDUtil::getUUID();
		}
		// $this->GroupContacts = array();
		// if (isset($aGroup['Contacts']) && is_array($aGroup['Contacts']))
		// {
		// 	$aContactUUIDs = $aGroup['Contacts'];
		// 	Contact::whereIn('UUID', $aGroup['Contacts'])->map(function($oContact) {
		// 		return $oContact->UUID;
		// 	});


		// 	foreach ($aContactUUIDs as $sContactUUID)
		// 	{
		// 		$oGroupContact = new \Aurora\Modules\Contacts\Classes\GroupContact($this->getModule());
		// 		$oGroupContact->ContactUUID = $sContactUUID;
		// 		$this->GroupContacts[] = $oGroupContact;
		// 	}
		// }
	}

	public function Contacts()
	{
		return $this->belongsToMany(Contact::class, 'group_contact', 'GroupId', 'ContactId');
	}
}
