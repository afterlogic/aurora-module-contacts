<?php

namespace Aurora\Modules\Contacts\Models;

use \Aurora\System\Classes\Model;
class Ctag extends Model
{
	protected $table = 'contacts_ctags';
	
	protected $fillable = [
		'Id',
		'IdUser',
		'Storage',
		'CTag'
	];
}
