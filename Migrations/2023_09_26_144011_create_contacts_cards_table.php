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
            $table->boolean('IsGroup')->default(false);

            $table->json('Properties')->nullable();
        });

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
        Capsule::schema()->dropIfExists('contacts_cards');
    }
}
