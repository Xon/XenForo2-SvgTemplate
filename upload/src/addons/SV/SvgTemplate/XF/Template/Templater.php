<?php

namespace SV\SvgTemplate\XF\Template;



/**
 * Extends \XF\Template\Templater
 */
class Templater extends XFCP_Templater
{
    public function addDefaultHandlers()
    {
        parent::addDefaultHandlers();

        $this->addFunction('getsvgurl', [$this, 'fnGetSvgUrl']);
    }


    /**
     * @param \XF\Template\Templater $templater
     * @param string                 $escape
     * @param string                 $template
     * @param bool                   $includeValidation
     * @return string
     */
    public function fnGetSvgUrl($templater, &$escape, $template, $includeValidation = false)
    {
        if (!$template)
        {
            throw new \LogicException('$templateName is required');
        }

        $parts = pathinfo($template);
        $hasExtension = !empty($parts['extension']);
        if (($hasExtension && $parts['extension'] !== 'svg') || (!empty($parts['dirname']) && $parts['dirname'] !== '.'))
        {
            return $template;
        }

        if (!$hasExtension)
        {
            $template .= '.svg';
        }

        $app = \XF::app();

        $useFriendlyUrls = $app->options()->useFriendlyUrls;
        $style = $templater->getStyle();
        $styleId = $templater->getStyleId();
        $languageId = $templater->getLanguage()->getId();
        $lastModified = ($style ? $style->getLastModified() : \XF::$time);

        if ($useFriendlyUrls)
        {
            $url = "data/svg/{$styleId}/{$languageId}/{$lastModified}/{$template}";
        }
        else
        {
            $url = "svg.php?svg={$template}&s={$styleId}&l={$languageId}&d={$lastModified}";
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