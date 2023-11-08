<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class CreateContactsCardsIndexes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $prefix = Capsule::connection()->getTablePrefix();

        Capsule::schema()->table('contacts_cards', function (Blueprint $table) use ($prefix) {
            $table->index('CardId');
            $table->index('AddressBookId');
            $table->index('IsGroup');
            $table->index('Frequency');

            Capsule::connection()->statement(
                "CREATE FULLTEXT INDEX contacts_cards_fullname_index ON {$prefix}contacts_cards (FullName)"
            );
            Capsule::connection()->statement(
                "CREATE FULLTEXT INDEX contacts_cards_viewemail_index ON {$prefix}contacts_cards (ViewEmail)"
            );
            Capsule::connection()->statement(
                "CREATE FULLTEXT INDEX contacts_cards_personalemail_index ON {$prefix}contacts_cards (PersonalEmail)"
            );
            Capsule::connection()->statement(
                "CREATE FULLTEXT INDEX contacts_cards_businessemail_index ON {$prefix}contacts_cards (BusinessEmail)"
            );
            Capsule::connection()->statement(
                "CREATE FULLTEXT INDEX contacts_cards_businesscompany_index ON {$prefix}contacts_cards (BusinessCompany)"
            );
            Capsule::connection()->statement(
                "CREATE FULLTEXT INDEX contacts_cards_otheremail_index ON {$prefix}contacts_cards (OtherEmail)"
            );
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Capsule::schema()->table('contacts_cards', function (Blueprint $table) {
            $table->dropIndex(['CardId']);
            $table->dropIndex(['AddressBookId']);
            $table->dropIndex('IsGroup');
            $table->dropIndex('Frequency');
            $table->dropIndex(['FullName']);
            $table->dropIndex(['PersonalEmail']);
            $table->dropIndex(['ViewEmail']);
            $table->dropIndex(['BusinessEmail']);
            $table->dropIndex(['BusinessCompany']);
            $table->dropIndex(['OtherEmail']);
        });
    }
}
