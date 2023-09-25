<?php

/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Contacts\Classes;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 *
 * @package Classes
 * @subpackage ContactListItem
 */
class ContactListItem
{
    /**
     * @var mixed
     */
    public $Id;

    /**
     * @var string
     */
    public $IdStr;

    /**
     *  @var int $IdUser
     */
    public $IdUser;

    /**
     * @var string
     */
    public $ETag;

    /**
     * @var bool
     */
    public $IsGroup;

    /**
     * @var bool
     */
    public $IsOrganization;


    /**
     * @var string
     */
    public $Name;

    /**
     * @var string
     */
    public $Email;

    /**
     * @var array
     */
    public $Emails;

    /**
     * @var array
     */
    public $Phones;

    /**
     * @var int
     */
    public $Frequency;

    /**
     * @var bool
     */
    public $UseFriendlyName;

    /**
     * @var bool
     */
    public $Global;

    /**
     * @var bool
     */
    public $ItsMe;

    /**
     * @var bool
     */
    public $ReadOnly;

    /**
     * @var bool
     */
    public $Auto;

    /**
     * @var bool
     */
    public $ForSharedToAll;

    /**
     * @var bool
     */
    public $SharedToAll;

    /**
     * @var int
     */
    public $LastModified;

    /**
     * @var array
     */
    public $Events;

    /**
     * @var int
     */
    public $AgeScore;

    public $DateModified;

    public function __construct()
    {
        $this->Id = null;
        $this->IdStr = null;
        $this->IdUser = null;
        $this->ETag = null;
        $this->IsGroup = false;
        $this->IsOrganization = false;
        $this->Name = '';
        $this->Email = '';
        $this->Emails = array();
        $this->Phones = array();
        $this->Frequency = 0;
        $this->UseFriendlyName = false;
        $this->Global = false;
        $this->ItsMe = false;
        $this->ReadOnly = false;
        $this->Auto = false;
        $this->ForSharedToAll = false;
        $this->SharedToAll = false;
        $this->Events = array();
        $this->AgeScore = 1;
        $this->DateModified = 0;
    }

    /**
     * @param \Sabre\VObject\Component\VCard $oVCard
     */
    public function InitBySabreCardDAVCard($oVCard)
    {
        if ($oVCard) {
            if ($oVCard->name == 'VCARD') {
                if (isset($oVCard->UID)) {
                    $this->Id = (string)$oVCard->UID;
                    $this->IdStr = $this->Id;
                }
                $this->IsGroup = false;

                if (isset($oVCard->FN)) {
                    $this->Name = (string)$oVCard->FN;
                }

                if (isset($oVCard->EMAIL)) {
                    /** @var \Sabre\VObject\Property\FlatText $oEmail */
                    $oEmail = $oVCard->EMAIL[0];
                    $this->Email = (string)$oEmail;
                    foreach ($oVCard->EMAIL as $oEmail) {
                        if ($oTypes = $oEmail['TYPE']) {
                            if ($oTypes->has('PREF')) {
                                $this->Email = (string)$oEmail;
                                break;
                            }
                        }
                    }
                }
                if (isset($oVCard->{'X-AFTERLOGIC-USE-FREQUENCY'})) {
                    $this->Frequency = (int)$oVCard->{'X-AFTERLOGIC-USE-FREQUENCY'};
                }

                $this->UseFriendlyName = true;
                if (isset($oVCard->{'X-AFTERLOGIC-USE-FRIENDLY-NAME'})) {
                    $this->UseFriendlyName = '1' === (string)$oVCard->{'X-AFTERLOGIC-USE-FRIENDLY-NAME'};
                }
            }
        }
    }

    /**
     * @param string $sRowType
     * @param array $aRow
     */
    public function InitByLdapRowWithType($sRowType, $aRow)
    {
        if ($aRow) {
            switch ($sRowType) {
                case 'contact':
                    $this->Id = $aRow['un'][0];
                    $this->IdStr = $this->Id;
                    $this->IsGroup = false;
                    $this->Name = (string) $aRow['cn'][0];
                    $this->Email = isset($aRow['mail'][0]) ? (string) $aRow['mail'][0] :
                        (isset($aRow['homeemail'][0]) ? (string) $aRow['homeemail'][0] : '');
                    $this->Frequency = 0;
                    $this->UseFriendlyName = true;
                    break;

                case 'group':
                    $this->Id = $aRow['un'][0];
                    $this->IdStr = $this->Id;
                    $this->IsGroup = true;
                    $this->Name = $aRow['cn'][0];
                    $this->Email = '';
                    $this->Frequency = 0;
                    $this->UseFriendlyName = true;
                    break;
            }
        }
    }

    /**
     * @return string
     */
    public function ToString()
    {
        return ($this->UseFriendlyName && 0 < strlen(trim($this->Name)) && !$this->IsGroup)
            ? '"' . trim($this->Name) . '" <' . trim($this->Email) . '>'
            : (($this->IsGroup) ? trim($this->Name) : trim($this->Email));
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->ToString();
    }
}
