<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class CreateGroupContactTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Capsule::schema()->create('group_contact', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('UUID')->default('');
            $table->integer('GroupId')->unsigned()->index();
            $table->foreign('GroupId')->references('Id')->on('groups')->onDelete('cascade');
            $table->integer('ContactId')->unsigned()->index();
            $table->foreign('ContactId')->references('Id')->on('contacts')->onDelete('cascade');
            $table->timestamp(\Aurora\System\Classes\Model::CREATED_AT)->nullable();
            $table->timestamp(\Aurora\System\Classes\Model::UPDATED_AT)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Capsule::schema()->dropIfExists('group_contact');
    }
}
