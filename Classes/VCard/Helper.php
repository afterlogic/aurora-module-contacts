<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Contacts\Classes\VCard;

use Aurora\Modules\Contacts\Enums\StorageType;
use Sabre\VObject\Property;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 *
 * @ignore
 * @package Contactsmain
 * @subpackage Helpers
 */
class Helper
{
    public static function GetContactDataFromVcard($oVCard, $sUUID = '')
    {
        $aContact = [];

        // if (!empty($sUUID)) {
        //     $aContact['UUID'] = (string) $sUUID;
        // }

        if (isset($oVCard->UID)) {
            $aContact['UUID'] = \str_replace('urn:uuid:', '', (string) $oVCard->UID);
        }

        $aContact['FullName'] = isset($oVCard->FN) ? (string) $oVCard->FN : '';

        if (isset($oVCard->N)) {
            $aNames = $oVCard->N->getParts();
            if (count($aNames) >= 2) {
                $aContact['LastName'] = !empty($aNames[0]) ? (string) $aNames[0] : '';
                $aContact['FirstName'] = !empty($aNames[1]) ? (string) $aNames[1] : '';
            }
        }

        $aContact['NickName'] = isset($oVCard->NICKNAME) ? (string) $oVCard->NICKNAME : '';
        $aContact['Notes'] = isset($oVCard->NOTE) ? (string) $oVCard->NOTE : '';

        if (isset($oVCard->BDAY)) {
            $aDateTime = explode('T', (string) $oVCard->BDAY);
            if (isset($aDateTime[0])) {
                $aDate = explode('-', $aDateTime[0]);
                if (count($aDate) >= 3) {
                    $aContact['BirthYear'] = (int) $aDate[0];
                    $aContact['BirthMonth'] = (int) $aDate[1];
                    $aContact['BirthDay'] = (int) $aDate[2];
                }
            }
        }

        if (isset($oVCard->ORG)) {
            $aOrgs = $oVCard->ORG->getParts();

            if (count($aOrgs) >= 2) {
                $aContact['BusinessCompany'] = !empty($aOrgs[0]) ? (string) $aOrgs[0] : '';
                $aContact['BusinessDepartment'] = !empty($aOrgs[1]) ? (string) $aOrgs[1] : '';
            }
        }

        $aContact['BusinessJobTitle'] = isset($oVCard->TITLE) ? (string) $oVCard->TITLE : '';

        if (isset($oVCard->ADR)) {
            foreach ($oVCard->ADR as $oAdr) {
                $aAdrs = $oAdr->getParts();
                $oTypes = $oAdr['TYPE'];
                if ($oTypes) {
                    if ($oTypes->has('WORK')) {
                        $aContact['BusinessAddress'] = isset($aAdrs[2]) ? (string) $aAdrs[2] : '';
                        $aContact['BusinessCity'] = isset($aAdrs[3]) ? (string) $aAdrs[3] : '';
                        $aContact['BusinessState'] = isset($aAdrs[4]) ? (string) $aAdrs[4] : '';
                        $aContact['BusinessZip'] = isset($aAdrs[5]) ? (string) $aAdrs[5] : '';
                        $aContact['BusinessCountry'] = isset($aAdrs[6]) ? (string) $aAdrs[6] : '';
                    }
                    if ($oTypes->has('HOME')) {
                        $aContact['PersonalAddress'] = isset($aAdrs[2]) ? (string) $aAdrs[2] : '';
                        $aContact['PersonalCity'] = isset($aAdrs[3]) ? (string) $aAdrs[3] : '';
                        $aContact['PersonalState'] = isset($aAdrs[4]) ? (string) $aAdrs[4] : '';
                        $aContact['PersonalZip'] = isset($aAdrs[5]) ? (string) $aAdrs[5] : '';
                        $aContact['PersonalCountry'] = isset($aAdrs[6]) ? (string) $aAdrs[6] : '';
                    }
                } else {
                    $aContact['PersonalAddress'] = isset($aAdrs[2]) ? (string) $aAdrs[2] : '';
                    $aContact['PersonalCity'] = isset($aAdrs[3]) ? (string) $aAdrs[3] : '';
                    $aContact['PersonalState'] = isset($aAdrs[4]) ? (string) $aAdrs[4] : '';
                    $aContact['PersonalZip'] = isset($aAdrs[5]) ? (string) $aAdrs[5] : '';
                    $aContact['PersonalCountry'] = isset($aAdrs[6]) ? (string) $aAdrs[6] : '';
                }
            }
        }

        if (isset($oVCard->EMAIL)) {
            foreach ($oVCard->EMAIL as $oEmail) {
                $oType = $oEmail['TYPE'];
                if ($oType) {
                    if ($oType->has('WORK') || $oType->has('INTERNET')) {
                        $aContact['BusinessEmail'] = (string) $oEmail;
                        if ($oType->has('PREF')) {
                            $aContact['PrimaryEmail'] = \Aurora\Modules\Contacts\Enums\PrimaryEmail::Business;
                        }
                    } elseif ($oType->has('HOME')) {
                        $aContact['PersonalEmail'] = (string) $oEmail;
                        if ($oType->has('PREF')) {
                            $aContact['PrimaryEmail'] = \Aurora\Modules\Contacts\Enums\PrimaryEmail::Personal;
                        }
                    } elseif ($oType->has('OTHER')) {
                        $aContact['OtherEmail'] = (string) $oEmail;
                        if ($oType->has('PREF')) {
                            $aContact['PrimaryEmail'] = \Aurora\Modules\Contacts\Enums\PrimaryEmail::Other;
                        }
                    } elseif ($oEmail->group && isset($oVCard->{$oEmail->group . '.X-ABLABEL'}) &&
                        strtolower((string) $oVCard->{$oEmail->group . '.X-ABLABEL'}) === '_$!<other>!$_') {
                        $aContact['OtherEmail'] = (string) $oEmail;
                        if ($oType->has('PREF')) {
                            $aContact['PrimaryEmail'] = \Aurora\Modules\Contacts\Enums\PrimaryEmail::Other;
                        }
                    }
                } else {
                    $aContact['OtherEmail'] = (string) $oEmail;
                    $aContact['PrimaryEmail'] = \Aurora\Modules\Contacts\Enums\PrimaryEmail::Other;
                }
            }
            if (empty($aContact['PrimaryEmail'])) {
                if (!empty($aContact['BusinessEmail'])) {
                    $aContact['PrimaryEmail'] = \Aurora\Modules\Contacts\Enums\PrimaryEmail::Business;
                } elseif (!empty($aContact['PersonalEmail'])) {
                    $aContact['PrimaryEmail'] = \Aurora\Modules\Contacts\Enums\PrimaryEmail::Personal;
                } elseif (!empty($aContact['OtherEmail'])) {
                    $aContact['PrimaryEmail'] = \Aurora\Modules\Contacts\Enums\PrimaryEmail::Other;
                }
            }
        }

        if (isset($oVCard->URL)) {
            foreach ($oVCard->URL as $oUrl) {
                $oTypes = $oUrl['TYPE'];
                if ($oTypes) {
                    if ($oTypes->has('HOME')) {
                        $aContact['PersonalWeb'] = (string) $oUrl;
                    } elseif ($oTypes->has('WORK')) {
                        $aContact['BusinessWeb'] = (string) $oUrl;
                    }
                } else {
                    $aContact['PersonalWeb'] = (string) $oUrl;
                }
            }
        }

        if (isset($oVCard->TEL)) {
            foreach ($oVCard->TEL as $oTel) {
                $oTypes = $oTel['TYPE'];
                if ($oTypes) {
                    if ($oTypes->has('FAX')) {
                        if ($oTypes->has('HOME')) {
                            $aContact['PersonalFax'] = (string) $oTel;
                        }
                        if ($oTypes->has('WORK')) {
                            $aContact['BusinessFax'] = (string) $oTel;
                        }
                    } else {
                        if ($oTypes->has('CELL')) {
                            $aContact['PersonalMobile'] = (string) $oTel;
                        } elseif ($oTypes->has('HOME')) {
                            $aContact['PersonalPhone'] = (string) $oTel;
                        } elseif ($oTypes->has('WORK')) {
                            $aContact['BusinessPhone'] = (string) $oTel;
                        }
                    }
                } else {
                    $aContact['PersonalPhone'] = (string) $oTel;
                }
            }
        }

        if (isset($oVCard->{'X-OFFICE'})) {
            $aContact['BusinessOffice'] = (string) $oVCard->{'X-OFFICE'};
        }

        if (isset($oVCard->{'X-USE-FRIENDLY-NAME'})) {
            $aContact['UseFriendlyName'] = '1' === (string) $oVCard->{'X-USE-FRIENDLY-NAME'};
        }

        if ($oVCard->IMPP && count($oVCard->IMPP) > 0) {
            foreach ($oVCard->IMPP as $impp) {
                [$type, $value] = explode(':', $impp->getValue());
                if (strtolower($type) === 'skype') {
                    $aContact['Skype'] = $value;
                }
                if (strtolower($type) === 'facebook') {
                    $aContact['Facebook'] = $value;
                }
            }
        }

        $aContact['Storage'] = StorageType::Personal;

        return $aContact;
    }

    public static function GetGroupDataFromVcard($oVCard, $sUUID = '')
    {
        $aGroup = [];

        if (!empty($sUUID)) {
            $aGroup['UUID'] = (string) $sUUID;
        } elseif (isset($oVCard->UID)) {
            $aGroup['UUID'] = \str_replace('urn:uuid:', '', (string) $oVCard->UID);
        }

        if (isset($oVCard->FN)) {
            $aGroup['Name'] = (string) $oVCard->FN;
        } elseif (isset($oVCard->N)) {
            $aNames = $oVCard->N->getParts();
            $aGroup['Name'] = \implode(' ', $aNames);
        }

        if (isset($oVCard->ORG)) {
            $aParts = $oVCard->ORG->getParts();
            $aGroup['Company'] = isset($aParts[0]) ? $aParts[0] : '';
        }

        if (isset($oVCard->{'X-AFTERLOGIC-IS-ORG'})) {
            $aGroup['IsOrganization'] = (int) $oVCard->{'X-AFTERLOGIC-IS-ORG'}->getValue() === 1;
        }

        if (isset($oVCard->ADR)) {
            foreach ($oVCard->ADR as $oAdr) {
                $aAdrs = $oAdr->getParts();
                $oTypes = $oAdr['TYPE'];
                if ($oTypes) {
                    if ($oTypes->has('WORK')) {
                        $aGroup['Street'] = isset($aAdrs[2]) ? (string) $aAdrs[2] : '';
                        $aGroup['City'] = isset($aAdrs[3]) ? (string) $aAdrs[3] : '';
                        $aGroup['State'] = isset($aAdrs[4]) ? (string) $aAdrs[4] : '';
                        $aGroup['Zip'] = isset($aAdrs[5]) ? (string) $aAdrs[5] : '';
                        $aGroup['Country'] = isset($aAdrs[6]) ? (string) $aAdrs[6] : '';
                    }
                }
            }
        }

        if (isset($oVCard->TEL)) {
            foreach ($oVCard->TEL as $oTel) {
                $oTypes = $oTel['TYPE'];
                if ($oTypes) {
                    if ($oTypes->has('WORK')) {
                        if ($oTypes->has('FAX')) {
                            $aGroup['Fax'] = $oTel->getValue();
                        } else {
                            $aGroup['Phone'] = $oTel->getValue();
                        }
                    }
                }
            }
        }

        if (isset($oVCard->URL)) {
            foreach ($oVCard->URL as $oUrl) {
                $oTypes = $oUrl['TYPE'];
                if ($oTypes) {
                    if ($oTypes->has('WORK')) {
                        $aGroup['Web'] = $oUrl->getValue();
                    }
                }
            }
        }

        if (isset($oVCard->EMAIL)) {
            foreach ($oVCard->EMAIL as $oEmail) {
                $oTypes = $oEmail['TYPE'];
                if ($oTypes) {
                    if ($oTypes->has('WORK')) {
                        $aGroup['Email'] = $oEmail->getValue();
                    }
                }
            }
        }

        $aMembers = [];
        if (isset($oVCard->MEMBER)) {
            $aMembers = $oVCard->MEMBER;
        } elseif (isset($oVCard->{'X-ADDRESSBOOKSERVER-MEMBER'})) {
            $aMembers = $oVCard->{'X-ADDRESSBOOKSERVER-MEMBER'};
        }

        $aGroup['Contacts'] = [];
        foreach ($aMembers as $sMember) {
            $aGroup['Contacts'][] = \str_replace('urn:uuid:', '', $sMember);
        }

        return $aGroup;
    }


    /**
    * @param \Aurora\Modules\Contacts\Classes\Contact $oContact
    * @param \Sabre\VObject\Component $oVCard
    * @return void
    */
    public static function UpdateVCardAddressesFromContact($oContact, &$oVCard)
    {
        $bFindHome = false;
        $bFindWork = false;

        $oVCardCopy = clone $oVCard;

        $sADRHome = array(
            '',
            '',
            $oContact->PersonalAddress,
            $oContact->PersonalCity,
            $oContact->PersonalState,
            $oContact->PersonalZip,
            $oContact->PersonalCountry
        );

        if (empty($oContact->PersonalAddress) && empty($oContact->PersonalCity) &&
                empty($oContact->PersonalState) && empty($oContact->PersonalZip) &&
                        empty($oContact->PersonalCountry)) {
            $bFindHome = true;
        }

        $sADRWork = array(
            '',
            '',
            $oContact->BusinessAddress,
            $oContact->BusinessCity,
            $oContact->BusinessState,
            $oContact->BusinessZip,
            $oContact->BusinessCountry
        );

        if (empty($oContact->BusinessAddress) && empty($oContact->BusinessCity) &&
                empty($oContact->BusinessState) && empty($oContact->BusinessZip) &&
                        empty($oContact->BusinessCountry)) {
            $bFindWork = true;
        }

        if (isset($oVCardCopy->ADR)) {
            unset($oVCard->ADR);
            foreach ($oVCardCopy->ADR as $oAdr) {
                if ($oTypes = $oAdr['TYPE']) {
                    if ($oTypes->has('HOME')) {
                        if ($bFindHome) {
                            continue;
                        } else {
                            $oAdr->setValue($sADRHome);
                            $bFindHome = true;
                        }
                    }
                    if ($oTypes->has('WORK')) {
                        if ($bFindWork) {
                            continue;
                        } else {
                            $oAdr->setValue($sADRWork);
                            $bFindWork = true;
                        }
                    }
                }
                $oVCard->add($oAdr);
            }
        }

        if (!$bFindHome) {
            $oVCard->add('ADR', $sADRHome, array('TYPE' => array('HOME')));
        }
        if (!$bFindWork) {
            $oVCard->add('ADR', $sADRWork, array('TYPE' => array('WORK')));
        }
    }

    /**
    * @param \Aurora\Modules\Contacts\Classes\Contact $oContact
    * @param \Sabre\VObject\Component\VCard $oVCard
    * @return void
    */
    public static function UpdateVCardEmailsFromContact($oContact, &$oVCard)
    {
        $bFindHome = (empty($oContact->PersonalEmail)) ? false : true;
        $bFindWork = (empty($oContact->BusinessEmail)) ? false : true;
        $bFindOther = (empty($oContact->OtherEmail)) ? false : true;

        $oVCardCopy = clone $oVCard;

        if (isset($oVCardCopy->EMAIL)) {
            unset($oVCard->EMAIL);
            foreach ($oVCardCopy->EMAIL as $oEmail) {
                if ($oTypes = $oEmail['TYPE']) {
                    $aTypes = array();
                    foreach ($oTypes as $sType) {
                        if ('PREF' !== strtoupper($sType)) {
                            $aTypes[] = $sType;
                        }
                    }
                    $oTypes->setValue($aTypes);

                    if ($oTypes->has('HOME')) {
                        if (!$bFindHome) {
                            unset($oEmail);
                        } else {
                            $bFindHome = false;
                            if ($oContact->PrimaryEmail == \Aurora\Modules\Contacts\Enums\PrimaryEmail::Personal) {
                                $oTypes->addValue('PREF');
                            }
                            $oEmail->setValue($oContact->PersonalEmail);
                        }
                    } elseif ($oTypes->has('WORK')) {
                        if (!$bFindWork) {
                            unset($oEmail);
                        } else {
                            $bFindWork = false;
                            if ($oContact->PrimaryEmail == \Aurora\Modules\Contacts\Enums\PrimaryEmail::Business) {
                                $oTypes->addValue('PREF');
                            }
                            $oEmail->setValue($oContact->BusinessEmail);
                        }
                    } elseif ($oTypes->has('OTHER')) {
                        if (!$bFindOther) {
                            unset($oEmail);
                        } else {
                            $bFindOther = false;
                            if ($oContact->PrimaryEmail == \Aurora\Modules\Contacts\Enums\PrimaryEmail::Other) {
                                $oTypes->addValue('PREF');
                            }
                            $oEmail->setValue($oContact->OtherEmail);
                        }
                    } elseif ($oEmail->group && isset($oVCardCopy->{$oEmail->group . '.X-ABLabel'}) &&
                            (strtolower((string) $oVCardCopy->{$oEmail->group . '.X-ABLabel'}) === '_$!<other>!$_') ||
                            (strtolower((string) $oVCardCopy->{$oEmail->group . '.X-ABLabel'}) === 'other')) {
                        if (!$bFindOther) {
                            unset($oVCardCopy->{$oEmail->group . '.X-ABLabel'});
                            unset($oEmail);
                        } else {
                            $bFindOther = false;
                            if ($oContact->PrimaryEmail == \Aurora\Modules\Contacts\Enums\PrimaryEmail::Other) {
                                $oTypes->addValue('PREF');
                            }
                            $oEmail->setValue($oContact->OtherEmail);
                        }
                    }
                }
                if (isset($oEmail)) {
                    $oVCard->add($oEmail);
                }
            }
        }

        if ($bFindHome) {
            $aTypes = array('HOME');
            if ($oContact->PrimaryEmail == \Aurora\Modules\Contacts\Enums\PrimaryEmail::Personal) {
                $aTypes[] = 'PREF';
            }
            $oEmail = $oVCard->add('EMAIL', $oContact->PersonalEmail, array('TYPE' => $aTypes));
        }
        if ($bFindWork) {
            $aTypes = array('WORK');
            if ($oContact->PrimaryEmail == \Aurora\Modules\Contacts\Enums\PrimaryEmail::Business) {
                $aTypes[] = 'PREF';
            }
            $oEmail = $oVCard->add('EMAIL', $oContact->BusinessEmail, array('TYPE' => $aTypes));
        }
        if ($bFindOther) {
            $aTypes = array('OTHER');
            if ($oContact->PrimaryEmail == \Aurora\Modules\Contacts\Enums\PrimaryEmail::Other) {
                $aTypes[] = 'PREF';
            }
            $oEmail = $oVCard->add('EMAIL', $oContact->OtherEmail, array('TYPE' => $aTypes));
        }
    }

    /**
    * @param \Aurora\Modules\Contacts\Classes\Contact $oContact
    * @param \Sabre\VObject\Component $oVCard
    * @return void
    */
    public static function UpdateVCardUrlsFromContact($oContact, &$oVCard)
    {
        $bFindHome = false;
        $bFindWork = false;

        if (empty($oContact->PersonalWeb)) {
            $bFindHome = true;
        }
        if (empty($oContact->BusinessWeb)) {
            $bFindWork = true;
        }

        if (isset($oVCard->URL)) {
            foreach ($oVCard->URL as $oUrl) {
                if ($oTypes = $oUrl['TYPE']) {
                    if ($oTypes->has('HOME')) {
                        if ($bFindHome) {
                            continue;
                        } else {
                            $oUrl->setValue($oContact->PersonalWeb);
                            $bFindHome = true;
                        }
                    }
                    if ($oTypes->has('WORK')) {
                        if ($bFindWork) {
                            continue;
                        } else {
                            $oUrl->setValue($oContact->BusinessWeb);
                            $bFindWork = true;
                        }
                    }
                }
            }
        }

        if (!$bFindHome) {
            $oVCard->add('URL', $oContact->PersonalWeb, array('TYPE' => array('HOME')));
        }
        if (!$bFindWork) {
            $oVCard->add('URL', $oContact->BusinessWeb, array('TYPE' => array('WORK')));
        }
    }

    /**
    * @param \Aurora\Modules\Contacts\Classes\Contact $oContact
    * @param \Sabre\VObject\Component\VCard $oVCard
    * @return void
    */
    public static function UpdateVCardPhonesFromContact($oContact, &$oVCard)
    {
        $bFindHome = false;
        $bFindWork = false;
        $bFindCell = false;
        $bFindPersonalFax = false;
        $bFindWorkFax = false;

        $oVCardCopy = clone $oVCard;

        if (empty($oContact->PersonalPhone)) {
            $bFindHome = true;
        }
        if (empty($oContact->BusinessPhone)) {
            $bFindWork = true;
        }
        if (empty($oContact->PersonalMobile)) {
            $bFindCell = true;
        }
        if (empty($oContact->PersonalFax)) {
            $bFindPersonalFax = true;
        }
        if (empty($oContact->BusinessFax)) {
            $bFindWorkFax = true;
        }

        if (isset($oVCardCopy->TEL)) {
            unset($oVCard->TEL);
            foreach ($oVCardCopy->TEL as $oTel) {
                if ($oTypes = $oTel['TYPE']) {
                    if ($oTypes->has('VOICE')) {
                        if ($oTypes->has('HOME')) {
                            if ($bFindHome) {
                                continue;
                            } else {
                                $oTel->setValue($oContact->PersonalPhone);
                                $bFindHome = true;
                            }
                        }
                        if ($oTypes->has('WORK')) {
                            if ($bFindWork) {
                                continue;
                            } else {
                                $oTel->setValue($oContact->BusinessPhone);
                                $bFindWork = true;
                            }
                        }
                        if ($oTypes->has('CELL')) {
                            if ($bFindCell) {
                                continue;
                            } else {
                                $oTel->setValue($oContact->PersonalMobile);
                                $bFindCell = true;
                            }
                        }
                    } elseif ($oTypes->has('FAX')) {
                        if ($oTypes->has('HOME')) {
                            if ($bFindPersonalFax) {
                                continue;
                            } else {
                                $oTel->setValue($oContact->PersonalFax);
                                $bFindPersonalFax = true;
                            }
                        }
                        if ($oTypes->has('WORK')) {
                            if ($bFindWorkFax) {
                                continue;
                            } else {
                                $oTel->setValue($oContact->BusinessFax);
                                $bFindWorkFax = true;
                            }
                        }
                    }
                }
                if (isset($oTel)) {
                    $oVCard->add($oTel);
                }
            }
        }

        if (!$bFindHome) {
            $oVCard->add('TEL', $oContact->PersonalPhone, array('TYPE' => array('VOICE', 'HOME')));
        }
        if (!$bFindWork) {
            $oVCard->add('TEL', $oContact->BusinessPhone, array('TYPE' => array('VOICE', 'WORK')));
        }
        if (!$bFindCell) {
            $oVCard->add('TEL', $oContact->PersonalMobile, array('TYPE' => array('VOICE', 'CELL')));
        }
        if (!$bFindPersonalFax) {
            $oVCard->add('TEL', $oContact->PersonalFax, array('TYPE' => array('FAX', 'HOME')));
        }
        if (!$bFindWorkFax) {
            $oVCard->add('TEL', $oContact->BusinessFax, array('TYPE' => array('FAX', 'WORK')));
        }
    }

    /**
    * @param \Aurora\Modules\Contacts\Classes\Contact $oContact
    * @param \Sabre\VObject\Component\VCard $oVCard
    * @param bool $bIsUpdate = false
    * @return void
    */
    public static function UpdateVCardFromContact($oContact, &$oVCard, $bIsUpdate = false)
    {
        $oVCard->VERSION = '3.0';

        if (!empty($oContact->UUID) && !is_numeric($oContact->UUID)) {
            $oVCard->UID = $oContact->UUID;
        }
        $oVCard->FN = $oContact->FullName;
        $oVCard->N = array(
            $oContact->LastName,
            $oContact->FirstName,
            '',
            $oContact->Title,
            '',
            ''
        );
        $oVCard->{'X-OFFICE'} = $oContact->BusinessOffice;
        $oVCard->{'X-USE-FRIENDLY-NAME'} = $oContact->UseFriendlyName ? '1' : '0';
        $oVCard->{'X-FREQUENCY'} = $oContact->Frequency;
        $oVCard->TITLE = $oContact->BusinessJobTitle;
        $oVCard->NICKNAME = $oContact->NickName;
        $oVCard->NOTE = $oContact->Notes;
        $oVCard->ORG = array(
            $oContact->BusinessCompany,
            $oContact->BusinessDepartment
        );

        $aCategories = [];
        // foreach ($oContact->GroupsContacts as $oGroupsContact) {
        //     $oContactsModule = \Aurora\Modules\Contacts\Module::Decorator();
        //     $oGroup = $oContactsModule->GetGroup($oContact->IdUser, $oGroupsContact->GroupUUID);
        //     if ($oGroup) {
        //         $aCategories[] = $oGroup->Name;
        //     }
        // }

        unset($oVCard->CATEGORIES);
        if (count($aCategories) > 0) {
            $catProp = $oVCard->add('CATEGORIES');
            if ($catProp instanceof Property) {
                $catProp->setParts($aCategories);
            }
        }

        self::UpdateVCardAddressesFromContact($oContact, $oVCard);
        self::UpdateVCardEmailsFromContact($oContact, $oVCard);
        self::UpdateVCardUrlsFromContact($oContact, $oVCard);
        self::UpdateVCardPhonesFromContact($oContact, $oVCard);

        unset($oVCard->BDAY);
        if ($oContact->BirthYear !== 0 && $oContact->BirthMonth !== 0 && $oContact->BirthDay !== 0) {
            $sBDayDT = $oContact->BirthYear . '-' . $oContact->BirthMonth . '-' . $oContact->BirthDay;
            $oVCard->add('BDAY', $sBDayDT);
        }

        $bFoundSkype = false;
        $bFoundFacebook = false;
        if ($oVCard->IMPP && count($oVCard->IMPP) > 0) {
            foreach ($oVCard->IMPP as $impp) {
                [$type, $value] = explode(':', $impp->getValue());
                if (strtolower($type) === 'skype') {
                    $bFoundSkype = true;
                    $impp->setValue('skype:' . $oContact->Skype);
                }
                if (strtolower($type) === 'facebook') {
                    $bFoundFacebook = true;
                    $impp->setValue('facebook:' . $oContact->Facebook);
                }
            }
        }
        if (!$bFoundSkype) {
            $oVCard->add('IMPP', 'skype:' . $oContact->Skype);
        }
        if (!$bFoundFacebook) {
            $oVCard->add('IMPP', 'facebook:' . $oContact->Facebook);
        }


        // $props = $oContact->getExtendedProps();
        // foreach ($props as $key => $prop) {
        //     $key = str_replace('::', '--', $key);
        //     $oVCard->add('X-' . $key, $prop);
        // }
    }

    /**
    * @param \Aurora\Modules\Contacts\Classes\Group $oGroup
    * @param \Sabre\VObject\Component\VCard $oVCard
    * @param bool $bIsUpdate = false
    * @return void
    */
    public static function UpdateVCardFromGroup($oGroup, &$oVCard, $bIsUpdate = false)
    {
        $oVCard->VERSION = '3.0';
        if (!empty($oGroup->UUID) && !is_numeric($oGroup->UUID)) {
            $oVCard->UID = $oGroup->UUID;
        }
        $oVCard->FN = $oGroup->Name;
        $oVCard->{'X-ADDRESSBOOKSERVER-KIND'} = 'GROUP';
        $oVCard->ORG = [$oGroup->Company, ''];
        $oVCard->{'X-AFTERLOGIC-IS-ORG'} = $oGroup->IsOrganization ? 1 : 0;

        $adr =  $oVCard->getByType('ADR', 'WORK');
        if ($adr) {
            $adr->setValue(['', '', $oGroup->Street, $oGroup->City, $oGroup->State, $oGroup->Zip, $oGroup->Country]);
        } else {
            $oVCard->add('ADR', ['', '', $oGroup->Street, $oGroup->City, $oGroup->State, $oGroup->Zip, $oGroup->Country], ['TYPE' => ['WORK']]);
        }

        $tel =  $oVCard->getByType('TEL', 'VOICE');
        if ($tel) {
            $tel->setValue($oGroup->Phone);
        } else {
            $oVCard->add('TEL', $oGroup->Phone, array('TYPE' => array('VOICE', 'WORK')));
        }

        $fax =  $oVCard->getByType('TEL', 'FAX');
        if ($fax) {
            $fax->setValue($oGroup->Fax);
        } else {
            $oVCard->add('TEL', $oGroup->Fax, array('TYPE' => array('FAX', 'WORK')));
        }

        $url =  $oVCard->getByType('URL', 'WORK');
        if ($url) {
            $url->setValue($oGroup->Web);
        } else {
            $oVCard->add('URL', $oGroup->Web, array('TYPE' => array('WORK')));
        }

        $email =  $oVCard->getByType('EMAIL', 'WORK');
        if ($email) {
            $email->setValue($oGroup->Email);
        } else {
            $oVCard->add('EMAIL', $oGroup->Email, array('TYPE' => array('WORK')));
        }

        unset($oVCard->{'X-ADDRESSBOOKSERVER-MEMBER'});
        if (is_array($oGroup->Contacts)) {
            foreach ($oGroup->Contacts as $sContactUID) {
                $oVCard->add('X-ADDRESSBOOKSERVER-MEMBER', 'urn:uuid:' . $sContactUID);
            }
        }
    }
}
