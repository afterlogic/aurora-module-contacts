<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class CreateContactsFulltextIndexes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $prefix = Capsule::connection()->getTablePrefix();

        Capsule::schema()->table('contacts', function (Blueprint $table) use ($prefix) {
            $table->index('Storage');
            $table->index('Frequency');

            Capsule::connection()->statement(
                "CREATE FULLTEXT INDEX contacts_fullname_index ON {$prefix}contacts (FullName)"
            );
            Capsule::connection()->statement(
                "CREATE FULLTEXT INDEX contacts_viewemail_index ON {$prefix}contacts (ViewEmail)"
            );
            Capsule::connection()->statement(
                "CREATE FULLTEXT INDEX contacts_personalemail_index ON {$prefix}contacts (PersonalEmail)"
            );
            Capsule::connection()->statement(
                "CREATE FULLTEXT INDEX contacts_businessemail_index ON {$prefix}contacts (BusinessEmail)"
            );
            Capsule::connection()->statement(
                "CREATE FULLTEXT INDEX contacts_businesscompany_index ON {$prefix}contacts (BusinessCompany)"
            );
            Capsule::connection()->statement(
                "CREATE FULLTEXT INDEX contacts_otheremail_index ON {$prefix}contacts (OtherEmail)"
            );
        });

        Capsule::schema()->table('contacts_groups', function (Blueprint $table) use ($prefix) {
            Capsule::connection()->statement(
                "CREATE FULLTEXT INDEX contacts_groups_name_index ON {$prefix}contacts_groups (Name)"
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
        Capsule::schema()->table('contacts', function (Blueprint $table) {
            $table->dropIndex(['Storage']);
            $table->dropIndex(['Frequency']);
            $table->dropIndex(['FullName']);
            $table->dropIndex(['PersonalEmail']);
            $table->dropIndex(['ViewEmail']);
            $table->dropIndex(['BusinessEmail']);
            $table->dropIndex(['BusinessCompany']);
            $table->dropIndex(['OtherEmail']);
        });

        Capsule::schema()->table('contacts_groups', function (Blueprint $table) {
            $table->dropIndex(['Name']);
        });
    }
}
