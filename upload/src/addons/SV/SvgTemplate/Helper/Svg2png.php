<?php

namespace SV\SvgTemplate\Helper;

use SV\BrowserDetection\Listener;

/**
 * @since 2.3.0 rc1
 */
class Svg2png
{
    /**
     * Returns if all the requirements for converting SVG to PNG pass.
     *
     * @return bool
     */
    public static function supportForSvg2PngEnabled() : bool
    {
        $app = \XF::app();
        if (!$app->options()->svSvgTemplate_renderSvgAsPng)
        {
            return false;
        }

        $addOns = $app->container('addon.cache');
        if (!\array_key_exists('SV/BrowserDetection', $addOns))
        {
            return false;
        }

        return \extension_loaded('imagick');
    }

    /**
     * Returns if the SVG needs to be converted as PNG.
     *
     * @return bool
     *
     * @throws \Exception
     */
    public static function requiresConvertingSvg2Png() : bool
    {
        if (!static::supportForSvg2PngEnabled())
        {
            return false;
        }

        $mobileDetect = Listener::getMobileDetection();
        return $mobileDetect->isMobile() || $mobileDetect->isTablet();
    }
}