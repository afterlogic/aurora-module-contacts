<?php

namespace Aurora\Modules\Contacts\Classes;

use Aurora\Modules\Core\Models\User;
use Aurora\System\EventEmitter;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Aurora\Modules\Contacts\Models\Group
 *
 * @property integer $Id
 * @property string $UUID
 * @property integer $IdUser
 * @property string $Name
 * @property boolean $IsOrganization
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
 * @property string $Events
 * @property array|null $Properties
 * @property \Illuminate\Support\Carbon|null $CreatedAt
 * @property \Illuminate\Support\Carbon|null $UpdatedAt
 * @property array $Contacts
 * @property-read int|null $contacts_count
 * @property-read mixed $entity_id
 */
class Group
{
    public int $Id;
    public int $IdUser;
    public string $UUID;
    public string $Name;
    public bool $IsOrganization;
    public string $Email;
    public string $Company;
    public string $Street;
    public string $City;
    public string $State;
    public string $Zip;
    public string $Country;
    public string $Phone;
    public string $Fax;
    public string $Web;
    public $Contacts;

    public function __construct()
    {
        $this->Id = 0;
        $this->IdUser = 0;
        $this->UUID = '';
        $this->Name = '';
        $this->IsOrganization = false;
        $this->Email = '';
        $this->Company = '';
        $this->Street = '';
        $this->City = '';
        $this->State = '';
        $this->Zip = '';
        $this->Country = '';
        $this->Phone = '';
        $this->Fax = '';
        $this->Web = '';
        $this->Contacts = [];
    }

    public function populate($aGroup)
    {
        foreach ($aGroup as $key => $value) {
            if (property_exists($this, $key) && ($key !== 'Contacts')) {
                $this->$key = $value;
            }
        }

        if (!empty($aGroup['UUID'])) {
            $this->UUID = $aGroup['UUID'];
        } elseif (empty($this->UUID)) {
            $this->UUID = \Sabre\DAV\UUIDUtil::getUUID();
        }

        if (isset($aGroup['Contacts']) && is_array($aGroup['Contacts']) && count($aGroup['Contacts']) > 0) {
            $contactsIds = array_map(function ($item) {
                return $item . '.vcf';
            }, $aGroup['Contacts']);

            $contactsIds = Capsule::connection()->table('adav_cards')
                ->select('id')
                ->whereIn('uri', $contactsIds)->get()->all();

            $contactsIds = array_map(function ($item) {
                return $item->id;
            }, $contactsIds);

            if ($contactsIds) {
                $query = Capsule::connection()->table('contacts_cards')
                    ->join('adav_cards', 'contacts_cards.CardId', '=', 'adav_cards.id')
                    ->join('adav_addressbooks', 'adav_cards.addressbookid', '=', 'adav_addressbooks.id')
                    ->select('adav_cards.id as card_id');

                $aArgs = [
                    'UserId' => $this->IdUser,
                    'UUID' => array_values($contactsIds)
                ];

                // build a query to obtain the addressbook_id and card_uri with checking access to the contact
                $query->where(function ($q) use (&$aArgs, $query) {
                    $aArgs['Query'] = & $query;
                    EventEmitter::getInstance()->emit('Contacts', 'ContactQueryBuilder', $aArgs, $q);
                });

                $rows = $query->distinct()->get()->map(function ($value) {
                    return $value->card_id;
                });

                $this->Contacts = $rows->all();
            }
        }
    }

    public function toResponseArray()
    {
        return (array) $this;
    }
}
