<?php

namespace Aurora\Modules\Contacts\Models;

use \Aurora\System\Classes\Model;
class CTag extends Model
{
	protected $table = 'contacts_ctags';

	protected $foreignModel = 'Aurora\Modules\Core\Models\User';
	protected $foreignModelIdColumn = 'UserId'; // Column that refers to an external table
	
	protected $fillable = [
		'Id',
		'UserId',
		'Storage',
		'CTag'
	];
}
