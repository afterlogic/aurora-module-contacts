<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Capsule\Manager as Capsule;

class AddEmailIndexesToContactsCardsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $prefix = Capsule::connection()->getTablePrefix();

        Capsule::connection()->statement(
            "CREATE INDEX contacts_cards_business_email_index ON {$prefix}contacts_cards (BusinessEmail(191))"
        );
        Capsule::connection()->statement(
            "CREATE INDEX contacts_cards_view_email_index ON {$prefix}contacts_cards (ViewEmail(191))"
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $prefix = Capsule::connection()->getTablePrefix();

        Capsule::connection()->statement(
            "DROP INDEX contacts_cards_business_email_index ON {$prefix}contacts_cards"
        );
        Capsule::connection()->statement(
            "DROP INDEX contacts_cards_view_email_index ON {$prefix}contacts_cards"
        );
    }
}
