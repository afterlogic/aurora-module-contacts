<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Capsule\Manager as Capsule;

class SyncAddressBookIdInContactsCardsTable extends Migration
{
    private const ROWS_PER_UPDATE = 10000;

    /**
     * Run the migrations.
     *
     * Contacts are filtered by contacts_cards.AddressBookId, so it has to match adav_cards.addressbookid.
     * The table is updated in parts in order not to lock it for a long time.
     *
     * @return void
     */
    public function up()
    {
        $prefix = Capsule::connection()->getTablePrefix();
        $maxId = (int) Capsule::table('contacts_cards')->max('Id');

        for ($fromId = 0; $fromId < $maxId; $fromId += self::ROWS_PER_UPDATE) {
            Capsule::connection()->update(
                "UPDATE {$prefix}contacts_cards contact
                INNER JOIN {$prefix}adav_cards card ON card.id = contact.CardId
                SET contact.AddressBookId = card.addressbookid
                WHERE contact.Id > ? AND contact.Id <= ? AND contact.AddressBookId <> card.addressbookid",
                [$fromId, $fromId + self::ROWS_PER_UPDATE]
            );
        }
    }

    /**
     * Reverse the migrations.
     *
     * The previous values were wrong, there is nothing to restore.
     *
     * @return void
     */
    public function down() {}
}
