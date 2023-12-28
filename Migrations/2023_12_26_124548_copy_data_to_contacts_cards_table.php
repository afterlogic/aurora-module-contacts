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
    protected $filterByAddressBooks = false;
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
                    $properties = null;
                    if (isset($row->Properties)) {
                        $properties = json_decode($row->Properties, true);
                        $uid = $row->UUID;
                        if (isset($properties['DavContacts::UID'])) {
                            $uid = $properties['DavContacts::UID'];
                            unset($properties['DavContacts::UID']);
                        }
                        if (isset($properties['DavContacts::VCardUID'])) {
                            unset($properties['DavContacts::VCardUID']);
                        }
                    }

                    $userPublicId = Api::getUserPublicIdById($row->IdUser);
                    $userPrincipal = Constants::PRINCIPALS_PREFIX . $userPublicId;

                    if (isset($properties) || $row->Frequency > 0) {
                        $query = Capsule::connection()->table('contacts_cards')
                            ->select('contacts_cards.Id', 'contacts_cards.CardId', 'contacts_cards.Properties')
                            ->join('adav_addressbooks', 'contacts_cards.AddressBookId', '=', 'adav_addressbooks.id')
                            ->join('adav_cards', 'contacts_cards.CardId', '=', 'adav_cards.id')
                            ->where('adav_addressbooks.principaluri', $userPrincipal)
                            ->where('contacts_cards.ViewEmail', $row->ViewEmail)
                            ->where('adav_cards.uri', $uid . '.vcf');

                        if ($this->filterByAddressBooks) {
                            $storage = $row->Storage;
                            $storagesMapToAddressbooks = \Aurora\Modules\Contacts\Module::Decorator()->GetStoragesMapToAddressbooks();
                            $addressbookUri = null;
                            $isCustomAddressBook = false;
                            if (isset($storagesMapToAddressbooks[$storage])) {
                                $addressbookUri = $storagesMapToAddressbooks[$storage];
                            } elseif ($row->AddressBookId) {
                                $isCustomAddressBook = true;
                            }

                            if ($isCustomAddressBook) {
                                $query->join('contacts_addressbooks', 'contacts_addressbooks.UUID', '=', 'adav_addressbooks.uri')
                                    ->where('contacts_addressbooks.Id', $row->AddressBookId);
                            } elseif ($addressbookUri) {
                                $query->where('adav_addressbooks.uri', $addressbookUri);
                            }
                        }
                        $cardsInfo = $query->get();

                        foreach ($cardsInfo as $info) {
                            $update = [];
                            if (isset($properties)) {
                                if (isset($info->Properties)) {
                                    $oldProperties = \json_decode($info->Properties, true);
                                    $properties = array_merge($oldProperties, $properties);
                                }
                                $update['Properties'] = \json_encode($properties);
                            }
                            if ($row->Frequency > 0) {
                                $update['Frequency'] = $row->Frequency;
                            }
                            if (count($update) > 0) {
                                if (!!ContactCard::where('Id', $info->Id)->update($update)) {
                                    Api::Log('Contact migrated successfuly:', \Aurora\System\Enums\LogLevel::Full, 'contacts-migration-');
                                    Api::Log('User.PublicId: ' . $userPublicId, \Aurora\System\Enums\LogLevel::Full, 'contacts-migration-');
                                    Api::Log('Contact.UUID: ' . $uid, \Aurora\System\Enums\LogLevel::Full, 'contacts-migration-');
                                    Api::Log('Conatct.ViewEmail: ' . $row->ViewEmail, \Aurora\System\Enums\LogLevel::Full, 'contacts-migration-');
                                    Api::Log('ContactCard.CardId: ' . $info->CardId, \Aurora\System\Enums\LogLevel::Full, 'contacts-migration-');
                                    Api::Log('', \Aurora\System\Enums\LogLevel::Full, 'contacts-migration-');
                                }
                            }
                        }
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
