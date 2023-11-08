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
        Capsule::connection()->table('adav_cards')->orderBy('id')->chunk(100000, function ($rows) {
            foreach ($rows as $row) {
                try {
                    Backend::Carddav()->updateProperties($row->addressbookid, $row->uri, $row->carddata);
                } catch (\Exception $e) {
                    \Aurora\System\Api::Log('Contact migration exception', \Aurora\System\Enums\LogLevel::Error, 'contacts-migration-');
                    \Aurora\System\Api::LogObject($row, \Aurora\System\Enums\LogLevel::Error, 'contacts-migration-');
                    \Aurora\System\Api::Log($e->getMessage(), \Aurora\System\Enums\LogLevel::Error, 'contacts-migration-');
                    // \Aurora\System\Api::LogException($e, \Aurora\System\Enums\LogLevel::Error, 'contacts-migration-');
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {}
}
