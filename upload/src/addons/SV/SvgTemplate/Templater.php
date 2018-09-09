<?php

namespace SV\SvgTemplate;

class Templater
{
    /**
     * @param \XF\Container          $container
     * @param \XF\Template\Templater $templater
     */
    public static function setup(/** @noinspection PhpUnusedParameterInspection */ \XF\Container $container, \XF\Template\Templater &$templater)
    {
        $func = 'SV\\SvgTemplate\\Templater::fnGetSvgUrl';
        if (\is_callable('\Closure::fromCallable'))
        {
            $func = \Closure::fromCallable($func);
        }
        $templater->addFunction('getsvgurl', $func);
    }

    /**
     * @param \XF\Template\Templater $templater
     * @param string                 $escape
     * @param string                 $template
     * @param bool                   $includeValidation
     * @return string
     */
    public static function fnGetSvgUrl($templater, &$escape, $template, $includeValidation = false)
    {
        if (!$template)
        {
            throw new \LogicException('$templateName is required');
        }

        $parts = pathinfo($template);
        if (($parts['extension'] != 'svg' && $parts['extension'] != '') || ($parts['dirname'] != '' && $parts['dirname'] != '.'))
        {
            return $template;
        }

        $app = \XF::app();

        $useFriendlyUrls = $app->options()->useFriendlyUrls;
        $style = $templater->getStyle();
        $styleId = $templater->getStyleId();
        $languageId = $templater->getLanguage()->getId();
        $lastModified = ($style ? $style->getLastModified() : \XF::$time);

        if ($useFriendlyUrls)
        {
            $url = "/data/svg/{$styleId}/{$languageId}/{$lastModified}/{$template}.svg";
        }
        else
        {
            $url = "/svg.php?svg={$template}&s={$styleId}&l={$languageId}&d={$lastModified}";
        }

        if ($includeValidation)
        {
            $validationKey = $templater->getCssValidationKey([$template]);
            if ($validationKey)
            {
                $url .= ($useFriendlyUrls ? '?' : '&') . 'k=' . urlencode($validationKey);
            }
        }

        return $templater->fnBaseUrl($templater, $escape, $url, true);
    }
}