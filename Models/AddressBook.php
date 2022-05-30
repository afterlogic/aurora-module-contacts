<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Contacts\Models;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 *
 * @package Models
 * @subpackage AddressBook
 *
 * @property int $UserId
 * @property string $Name
 */

use Aurora\Modules\Core\Models\User;
use \Aurora\System\Classes\Model;

class AddressBook extends Model
{
    public $table = 'contacts_addressbooks';

	protected $foreignModel = User::class;
	protected $foreignModelIdColumn = 'UserId'; // Column that refers to an external table

	protected $fillable = [
		'Id',
        'UUID',
		'UserId',
		'Name'
	];
}
