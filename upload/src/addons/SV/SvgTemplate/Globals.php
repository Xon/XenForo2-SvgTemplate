<?php

namespace SV\SvgTemplate;

use SV\StandardLib\TemplaterHelper;
use SV\SvgTemplate\SV\StandardLib\TemplaterHelper as ExtendedTemplaterHelper;
use XF\Template\Templater;

abstract class Globals
{
    private function __construct() { }

    /**
     * NOT USED. Exists to ensure upgrades from older versions do not cause errors
     *
     * @deprecated
     */
    public static $templater = null;

    public static function templateHelper(Templater $templater): ExtendedTemplaterHelper
    {
        // Note; TemplaterHelper MUST NOT be the extended version!!!
        // The add-on will be in an effective zombie state so the XFCP parts will not be resolved
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return TemplaterHelper::get($templater);
    }
}