<?php

namespace SV\SvgTemplate;

use SV\StandardLib\TemplaterHelper;
use SV\SvgTemplate\SV\StandardLib\TemplaterHelper as ExtendedTemplaterHelper;

/**
 * Class Globals
 *
 * @package SV\SvgTemplate
 */
class Globals
{
    // NOT USED. Exists to ensure upgrades from older versions do not cause errors
    public static $templater = null;

    /**
     * @return ExtendedTemplaterHelper
     */
    public static function templateHelper(\XF\Template\Templater $templater): TemplaterHelper
    {
        // Note; TemplaterHelper MUST NOT be the extended version!!!
        return TemplaterHelper::get($templater);
    }
}