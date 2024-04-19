<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Contacts\Classes;

use Aurora\Modules\Contacts\Classes\VCard\Helper;
use Aurora\Modules\Contacts\Enums\StorageType;
use Aurora\Modules\Contacts\Models\ContactCard;
use Aurora\System\EventEmitter;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 *
 * @package Classes
 * @subpackage Contact
 */
class Contact
{
    public int $Id;
    public string $UUID;
    public int $IdUser;
    public int $IdTenant;
    public string $Storage;
    public string $FullName;
    public bool $UseFriendlyName;
    public int $PrimaryEmail;
    public int $PrimaryPhone;
    public int $PrimaryAddress;
    public string $ViewEmail;
    public string $Title;
    public string $FirstName;
    public string $LastName;
    public string $NickName;
    public string $Skype;
    public string $Facebook;
    public string $PersonalEmail;
    public string $PersonalAddress;
    public string $PersonalCity;
    public string $PersonalState;
    public string $PersonalZip;
    public string $PersonalCountry;
    public string $PersonalWeb;
    public string $PersonalFax;
    public string $PersonalPhone;
    public string $PersonalMobile;
    public string $BusinessEmail;
    public string $BusinessCompany;
    public string $BusinessAddress;
    public string $BusinessCity;
    public string $BusinessState;
    public string $BusinessZip;
    public string $BusinessCountry;
    public string $BusinessJobTitle;
    public string $BusinessDepartment;
    public string $BusinessOffice;
    public string $BusinessPhone;
    public string $BusinessFax;
    public string $BusinessWeb;
    public string $OtherEmail;
    public string $Notes;
    public int $BirthDay;
    public int $BirthMonth;
    public int $BirthYear;
    public string $ETag;
    public bool $Auto;
    public int $Frequency;
    public float $AgeScore;
    public string $AddressBookId;
    public array $GroupUUIDs;

    public $ExtendedInformation = [];
    public $Properties = [];

    public function __construct()
    {
        $this->Id = 0;
        $this->UUID = '';
        $this->IdUser = 0;
        $this->IdTenant = 0;
        $this->Storage = '';
        $this->FullName = '';
        $this->UseFriendlyName = false;
        $this->PrimaryEmail = 0;
        $this->PrimaryPhone = 0;
        $this->PrimaryAddress = 0;
        $this->ViewEmail = '';
        $this->Title = '';
        $this->FirstName = '';
        $this->LastName = '';
        $this->NickName = '';
        $this->Skype = '';
        $this->Facebook = '';
        $this->PersonalEmail = '';
        $this->PersonalAddress = '';
        $this->PersonalCity = '';
        $this->PersonalState = '';
        $this->PersonalZip = '';
        $this->PersonalCountry = '';
        $this->PersonalWeb = '';
        $this->PersonalFax = '';
        $this->PersonalPhone = '';
        $this->PersonalMobile = '';
        $this->BusinessEmail = '';
        $this->BusinessCompany = '';
        $this->BusinessAddress = '';
        $this->BusinessCity = '';
        $this->BusinessState = '';
        $this->BusinessZip = '';
        $this->BusinessCountry = '';
        $this->BusinessJobTitle = '';
        $this->BusinessDepartment = '';
        $this->BusinessOffice = '';
        $this->BusinessPhone = '';
        $this->BusinessFax = '';
        $this->BusinessWeb = '';
        $this->OtherEmail = '';
        $this->Notes = '';
        $this->BirthDay = 0;
        $this->BirthMonth = 0;
        $this->BirthYear = 0;
        $this->ETag = '';
        $this->Auto = false;
        $this->Frequency = 0;
        $this->AgeScore = 0;
        $this->AddressBookId = 0;
        $this->GroupUUIDs = [];
    }

    public function toResponseArray()
    {
        $aRes = [];
        foreach ($this->ExtendedInformation as $sKey => $mValue) {
            $aRes[$sKey] = $mValue;
        }

        foreach ($this->getExtendedProps() as $sKey => $mValue) {
            $aRes[$sKey] = $mValue;
        }
        $this->Properties = [];

        return array_merge($aRes, (array) $this);
    }

    /**
     * Returns value of email that is specified as primary.
     * @return string
     */
    protected function getViewEmail()
    {
        switch ((int) $this->PrimaryEmail) {
            default:
            case \Aurora\Modules\Contacts\Enums\PrimaryEmail::Personal:
                return (string) $this->PersonalEmail;
            case \Aurora\Modules\Contacts\Enums\PrimaryEmail::Business:
                return (string) $this->BusinessEmail;
            case \Aurora\Modules\Contacts\Enums\PrimaryEmail::Other:
                return (string) $this->OtherEmail;
        }
    }

    /**
     * Sets ViewEmail field.
     */
    public function setViewEmail()
    {
        $this->ViewEmail = $this->getViewEmail();
    }

    /**
     * Populate contact with specified data.
     * @param array $aContact List of contact data.
     */
    public function populate($aContact)
    {
        if (isset($aContact['Storage'])) {
            $aStorageParts = \explode('-', (string)$aContact['Storage']);
            if (isset($aStorageParts[0], $aStorageParts[1]) && $aStorageParts[0] === StorageType::AddressBook) {
                $aContact['AddressBookId'] = (int) $aStorageParts[1];
                $aContact['Storage'] = StorageType::AddressBook;
            }
        }

        foreach ($aContact as $key => $value) {
            if (property_exists($this, $key) && $value !== null) {
                $this->$key = $value;
            }
        }

        if (!empty($aContact['UUID'])) {
            $this->UUID = $aContact['UUID'];
        } elseif (empty($this->UUID)) {
            $this->UUID = \Sabre\DAV\UUIDUtil::getUUID();
        }
        $this->setViewEmail();

        EventEmitter::getInstance()->emit('Contacts', 'PopulateContactModel', $this);
    }

    /**
     * Inits contacts from Vcard string.
     * @param int $iUserId User identifier.
     * @param string $sData Vcard string.
     * @param string $sUid Contact UUID.
     */
    public function InitFromVCardStr($iUserId, $sData, $sUid = '')
    {
        $oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserWithoutRoleCheck($iUserId);
        if ($oUser instanceof \Aurora\Modules\Core\Models\User) {
            $this->IdUser = $oUser->Id;
            $this->IdTenant = $oUser->IdTenant;
        }

        if (!empty($sUid)) {
            $this->UUID = $sUid;
        }

        $this->populate(
            Helper::GetContactDataFromVcard(
                \Sabre\VObject\Reader::read(
                    $sData,
                    \Sabre\VObject\Reader::OPTION_IGNORE_INVALID_LINES
                )
            )
        );
    }

    public function setExtendedProp($key, $value)
    {
        $card = ContactCard::select('Id', 'Properties')->where('CardId', $this->Id)->first();
        if ($card) {
            $properties = $card->Properties;
            $properties[$key] = $value;
            $card->Properties = $properties;

            $card->save();
        }
    }

    public function unsetExtendedProp($key)
    {
        $card = ContactCard::where('CardId', $this->Id)->first();
        if ($card) {
            $properties = $card->Properties;
            if (isset($properties[$key])) {
                unset($properties[$key]);
            }
            $card->Properties = $properties;

            $card->save();
        }
    }

    public function setExtendedProps($props)
    {
        $card = ContactCard::where('CardId', $this->Id)->first();
        if ($card) {
            $properties = is_array($card->Properties) ? $card->Properties : [];
            $card->Properties = array_merge($properties, $props);

            $card->save();
        }
    }

    public function getExtendedProp($key)
    {
        $result = null;
        $card = ContactCard::select('Properties')->where('CardId', $this->Id)->first();

        if ($card) {
            if (isset($card->Properties[$key])) {
                $result =  $card->Properties[$key];
            }
        }

        return $result;
    }

    public function getExtendedProps()
    {
        $result = [];
        $card = ContactCard::select('Properties')->where('CardId', $this->Id)->first();

        if ($card) {
            if (isset($card->Properties)) {
                $result =  $card->Properties;
            }
        }

        return $result;
    }
}
