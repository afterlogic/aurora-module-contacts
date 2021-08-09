<?php

namespace Aurora\Modules\Contacts\Models;

use \Aurora\System\Classes\Model;
class Ctag extends Model
{
	protected $table = 'contacts_ctag';
	
	protected $fillable = [
		'Id',
		'IdUser',
		'Storage',
		'CTag'
	];
}
