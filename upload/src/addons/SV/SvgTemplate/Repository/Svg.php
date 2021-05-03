<?php

namespace SV\SvgTemplate\Repository;

use SV\BrowserDetection\Listener;
use XF\Mvc\Entity\Repository;

class Svg extends Repository
{
    /**
     * @return bool
     */
    public function isSvBrowserDetectionActive() : bool
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
    public function isSvg2PngEnabled() : bool
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
    public function requiresConvertingSvg2Png() : bool
    {
        if (!$this->isSvBrowserDetectionActive())
        {
            return false;
        }

        $mobileDetect = Listener::getMobileDetection();
        return $mobileDetect->isMobile() || $mobileDetect->isTablet();
    }
}