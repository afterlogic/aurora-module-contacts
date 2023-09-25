<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class AlterContactsTableDropColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $prefix = Capsule::connection()->getTablePrefix();
        Capsule::connection()->statement("ALTER TABLE {$prefix}contacts_group_contact DROP COLUMN UUID");
        Capsule::connection()->statement("ALTER TABLE {$prefix}contacts_group_contact DROP COLUMN CreatedAt");
        Capsule::connection()->statement("ALTER TABLE {$prefix}contacts_group_contact DROP COLUMN UpdatedAt");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {}
}
