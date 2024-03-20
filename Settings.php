<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Contacts;

use Aurora\System\SettingsProperty;

/**
 * @property bool $Disabled
 * @property int $ContactsPerPage
 * @property string $ImportContactsLink
 * @property bool $AllowAddressBooksManagement
 * @property bool $AllowEditTeamContactsByTenantAdmins
 * @property array $ContactsSortBy
 */

class Settings extends \Aurora\System\Module\Settings
{
    protected function initDefaults()
    {
        $this->aContainer = [
            "Disabled" => new SettingsProperty(
                false,
                "bool",
                null,
                "Setting to true disables the module",
            ),
            "ContactsPerPage" => new SettingsProperty(
                20,
                "int",
                null,
                "Number of contacts displayer ped page",
            ),
            "ImportContactsLink" => new SettingsProperty(
                "",
                "string",
                null,
                "URL of documentation page that explains structure of CSV files used",
            ),
            "AllowAddressBooksManagement" => new SettingsProperty(
                false,
                "bool",
                null,
                "Setting to true enables managing multiple address books",
            ),
            "AllowEditTeamContactsByTenantAdmins" => new SettingsProperty(
                false,
                "bool",
                null,
                "Setting to true allows tenant admin to edit Team address book entries",
            ),
            "ContactsSortBy" => new SettingsProperty(
                [
                    "Allow" => false,
                    "DisplayOptions" => [
                        "Name",
                        "Email",
                    ],
                    "DefaultSortBy" => "Name",
                    "DefaultSortOrder" => "Asc",
                ],
                "array",
                null,
                "Defines a set of rules for sorting contacts. Name|Email|Frequency|FirstName|LastName. DefaultSortOrder - Asc|Desc",
            ),
        ];
    }
}
