<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class CreateContactsIndexes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Capsule::schema()->table('contacts', function (Blueprint $table) {
            $table->index('IdUser');
            $table->index('IdTenant');
            $table->index('UUID');
        });

        Capsule::schema()->table('contacts_groups', function (Blueprint $table) {
            $table->index('IdUser');
        });

        Capsule::schema()->table('contacts_addressbooks', function (Blueprint $table) {
            $table->index('UserId');
        });

        Capsule::schema()->table('contacts_ctags', function (Blueprint $table) {
            $table->index('UserId');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Capsule::schema()->table('contacts', function (Blueprint $table) {
            $table->dropIndex(['IdUser']);
            $table->dropIndex(['IdTenant']);
            $table->dropIndex(['UUID']);
        });

        Capsule::schema()->table('contacts_groups', function (Blueprint $table) {
            $table->dropIndex(['IdUser']);
        });

        Capsule::schema()->table('contacts_addressbooks', function (Blueprint $table) {
            $table->dropIndex(['UserId']);
        });

        Capsule::schema()->table('contacts_ctags', function (Blueprint $table) {
            $table->dropIndex(['UserId']);
        });
    }
}
