<?php

namespace Aurora\Modules\Contacts\Enums;

class Access extends \Aurora\System\Enums\AbstractEnumeration
{
    public const NoAccess = 0;
    public const Write	 = 1;
    public const Read   = 2;

    /**
     * @var array
     */
    protected $aConsts = array(
        'NoAccess'	=> self::NoAccess,
        'Write'	=> self::Write,
        'Read'	=> self::Read,
    );
}
