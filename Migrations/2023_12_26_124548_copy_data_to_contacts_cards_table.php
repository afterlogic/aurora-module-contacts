<?php

use Afterlogic\DAV\Backend;
use Afterlogic\DAV\Constants;
use Aurora\Modules\Contacts\Models\ContactCard;
use Aurora\System\Api;
use Aurora\System\EventEmitter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Capsule\Manager as Capsule;

class CopyDataToContactsCardsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Api::Init();
        Capsule::connection()->table('contacts')->orderBy('Id')->chunk(100000, function ($rows) {
            foreach ($rows as $row) {
                try {
                    $properties = json_decode($row->Properties, true);
                    $uid = $row->UUID;
                    if (isset($properties['DavContacts::UID'])) {
                        $uid = $properties['DavContacts::UID'];
                        unset($properties['DavContacts::UID']);
                    }
                    if (isset($properties['DavContacts::VCardUID'])) {
                        unset($properties['DavContacts::VCardUID']);
                    }

                    $userPrincipal = Constants::PRINCIPALS_PREFIX . Api::getUserPublicIdById($row->IdUser);
                    $storage = $row->Storage;
                    $storagesMapToAddressbooks = \Aurora\Modules\Contacts\Module::Decorator()->GetStoragesMapToAddressbooks();
                    $addressbookUri = null;
                    $isCustomAddressBook = false;
                    if (isset($storagesMapToAddressbooks[$storage])) {
                        $addressbookUri = $storagesMapToAddressbooks[$storage];
                    } elseif ($row->AddressBookId) {
                        $isCustomAddressBook = true;
                    }

                    $query = Capsule::connection()->table('contacts_cards')->select('contacts_cards.Id')
                        ->join('adav_addressbooks', 'contacts_cards.AddressBookId', '=', 'adav_addressbooks.id')
                            ->join('adav_cards', 'contacts_cards.CardId', '=', 'adav_cards.id')
                                ->where('adav_addressbooks.principaluri', $userPrincipal)
                                ->where('adav_cards.uri', $uid . '.vcf');

                    if ($isCustomAddressBook) {
                        $query->join('contacts_addressbooks', 'contacts_addressbooks.UUID', '=', 'adav_addressbooks.uri')
                            ->where('contacts_addressbooks.Id', $row->AddressBookId);
                    } elseif ($addressbookUri) {
                        $query->where('adav_addressbooks.uri', $addressbookUri);
                    }
                    $ids = $query->pluck('Id')->toArray();

                    if (count($ids) > 0) {
                        ContactCard::whereIn('Id', $ids)->update(['Properties' => \json_encode($properties), 'Frequency' => $row->Frequency]);
                    }
                } catch (\Exception $e) {
                    Api::Log('Contact migration exception', \Aurora\System\Enums\LogLevel::Error, 'contacts-migration-');
                    Api::LogObject($row, \Aurora\System\Enums\LogLevel::Error, 'contacts-migration-');
                    Api::Log($e->getMessage(), \Aurora\System\Enums\LogLevel::Error, 'contacts-migration-');
                    // \Aurora\System\Api::LogException($e, \Aurora\System\Enums\LogLevel::Error, 'contacts-migration-');
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {}
}
