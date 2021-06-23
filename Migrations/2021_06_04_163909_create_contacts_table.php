<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class CreateContactsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Capsule::schema()->create('contacts', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('UUID')->default('');
            $table->integer('IdUser')->default(0);
            $table->integer('IdTenant')->default(0);
            $table->string('Storage')->default('');
            $table->string('FullName')->default('');
            $table->boolean('UseFriendlyName')->default(true);
            $table->integer('PrimaryEmail')->default(\Aurora\Modules\Contacts\Enums\PrimaryEmail::Personal);
            $table->integer('PrimaryPhone')->default(\Aurora\Modules\Contacts\Enums\PrimaryPhone::Personal);
            $table->integer('PrimaryAddress')->default(\Aurora\Modules\Contacts\Enums\PrimaryAddress::Personal);
            $table->string('ViewEmail')->default('');
            $table->string('Title')->default('');
            $table->string('FirstName')->default('');
            $table->string('LastName')->default('');
            $table->string('NickName')->default('');
            $table->string('Skype')->default('');
            $table->string('Facebook')->default('');
            $table->string('PersonalEmail')->default('');
            $table->string('PersonalAddress')->default('');
            $table->string('PersonalCity')->default('');
            $table->string('PersonalState')->default('');
            $table->string('PersonalZip')->default('');
            $table->string('PersonalCountry')->default('');
            $table->string('PersonalWeb')->default('');
            $table->string('PersonalFax')->default('');
            $table->string('PersonalPhone')->default('');
            $table->string('PersonalMobile')->default('');
            $table->string('BusinessEmail')->default('');
            $table->string('BusinessCompany')->default('');
            $table->string('BusinessAddress')->default('');
            $table->string('BusinessCity')->default('');
            $table->string('BusinessState')->default('');
            $table->string('BusinessZip')->default('');
            $table->string('BusinessCountry')->default('');
            $table->string('BusinessJobTitle')->default('');
            $table->string('BusinessDepartment')->default('');
            $table->string('BusinessOffice')->default('');
            $table->string('BusinessPhone')->default('');
            $table->string('BusinessFax')->default('');
            $table->string('BusinessWeb')->default('');
            $table->string('OtherEmail')->default('');
            $table->text('Notes')->default('');
            $table->integer('BirthDay')->default(0);
            $table->integer('BirthMonth')->default(0);
            $table->integer('BirthYear')->default(0);
            $table->string('ETag')->default('');
            $table->boolean('Auto')->default(false);
            $table->integer('Frequency')->default(0);
            $table->dateTime('DateModified')->nullable();

            $table->json('Properties')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Capsule::schema()->dropIfExists('contacts');
    }
}
