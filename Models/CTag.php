<?php

namespace Aurora\Modules\Contacts\Models;

use \Aurora\System\Classes\Model;
use Aurora\Modules\Core\Models\User;
class CTag extends Model
{
	protected $table = 'contacts_ctags';

	protected $foreignModel = User::class;
	protected $foreignModelIdColumn = 'UserId'; // Column that refers to an external table
	
	protected $fillable = [
		'Id',
		'UserId',
		'Storage',
		'CTag'
	];
}
