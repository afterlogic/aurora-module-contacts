<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class AlterContactsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $prefix = Capsule::connection()->getTablePrefix();
        Capsule::connection()->statement("ALTER TABLE {$prefix}contacts ADD AddressBookId int(11) AFTER Storage");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $prefix = Capsule::connection()->getTablePrefix();
        Capsule::connection()->statement("ALTER TABLE {$prefix}contacts DROP COLUMN AddressBookId");
    }
}
