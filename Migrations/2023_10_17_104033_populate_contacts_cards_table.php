<?php

use Afterlogic\DAV\Backend;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Capsule\Manager as Capsule;

class PopulateContactsCardsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $rows = Capsule::connection()->table('adav_cards')->get();
        foreach ($rows as $row) {
            Backend::Carddav()->updateCard($row->addressbookid, $row->uri, $row->carddata);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
}