<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class CreateContactsCardsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Capsule::schema()->create('contacts_cards', function (Blueprint $table) {
            $table->increments('Id');
            $table->integer('CardId')->default(0);
            $table->integer('AddressBookId')->default(0);
            $table->string('FullName')->default('');
            $table->integer('PrimaryEmail')->default(\Aurora\Modules\Contacts\Enums\PrimaryEmail::Personal);
            $table->string('ViewEmail')->default('');
            $table->string('FirstName')->default('');
            $table->string('LastName')->default('');
            $table->string('PersonalEmail')->default('');
            $table->string('BusinessEmail')->default('');
            $table->string('OtherEmail')->default('');
            $table->string('BusinessCompany')->default('');
            $table->integer('Frequency')->default(0);

            $table->json('Properties')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Capsule::schema()->dropIfExists('contacts_cards');
    }
}
