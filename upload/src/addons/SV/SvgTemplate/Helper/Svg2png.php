<?php

namespace SV\SvgTemplate\Helper;

use SV\BrowserDetection\Listener;

/**
 * @since 2.3.0 rc1
 */
class Svg2png
{
    /**
     * @return bool
     */
    public static function isSvBrowserDetectionActive() : bool
    {
        return \array_key_exists(
            'SV/BrowserDetection',
            \XF::app()->container('addon.cache')
        );
    }

    /**
     * Returns if all the requirements for converting SVG to PNG pass.
     *
     * @return bool
     */
    public static function supportForSvg2PngEnabled() : bool
    {
        $renderSvgAsPng = \XF::app()->options()->svSvgTemplate_renderSvgAsPng ?? false;
        if (!$renderSvgAsPng)
        {
            return false;
        }

        if (!\extension_loaded('imagick'))
        {
            return false;
        }

        if (!\Imagick::queryFormats('SVG'))
        {
            return false;
        }

        if (!\Imagick::queryFormats('PNG'))
        {
            return false;
        }

        return true;
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
        if (!static::isSvBrowserDetectionActive())
        {
            return false;
        }

        $mobileDetect = Listener::getMobileDetection();
        return $mobileDetect->isMobile() || $mobileDetect->isTablet();
    }
}