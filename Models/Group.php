<?php

namespace Aurora\Modules\Contacts\Models;

use \Aurora\System\Classes\Model;
use Aurora\Modules\Core\Models\User;

class Group extends Model
{
	protected $table = 'contacts_groups';
	protected $foreignModel = User::class;
	protected $foreignModelIdColumn = 'IdUser'; // Column that refers to an external table

	public $Events = array();

	public $GroupContacts = array();

	protected $fillable = [
		'Id',
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

	protected $casts = [
        'Properties' => 'array',

		'IsOrganization' => 'boolean'
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
		return $this->belongsToMany(Contact::class, 'contacts_group_contact', 'GroupId', 'ContactId');
	}
}
