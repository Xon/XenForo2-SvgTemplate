<?php
/**
 * @noinspection PhpMissingParamTypeInspection
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SvgTemplate\XF\Template;

use SV\SvgTemplate\Globals;
use SV\SvgTemplate\Helper\Svg2png;
use SV\SvgTemplate\XF\Template\Exception\UnsupportedExtensionProvidedException;
use XF\App;
use XF\Language;

/**
 * Extends \XF\Template\Templater
 */
class Templater extends XFCP_Templater
{
    public function __construct(App $app, Language $language, $compiledPath)
    {
        parent::__construct($app, $language, $compiledPath);
        Globals::$templater = $this;
    }

    public function addDefaultHandlers()
    {
        parent::addDefaultHandlers();

        $callable = [$this, 'fnGetSvgUrl'];
        $hasFromCallable = is_callable('\Closure::fromCallable');
        if ($hasFromCallable)
        {
            /** @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection */
            $callable = \Closure::fromCallable($callable);
        }

        $this->addFunction('getsvgurl', $callable);
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

        $parts = \pathinfo($template);
        $extension = $parts['extension'];

        $supportedExtensions = ['svg', 'png'];
        $hasExtension = !empty($extension);
        $finalExtension = Svg2png::requiresConvertingSvg2Png() ? 'png' : 'svg';

        if (
            ($hasExtension && !\in_array($extension, $supportedExtensions, true)) // unsupported extension
            || (!empty($parts['dirname']) && $parts['dirname'] !== '.') // contains path info
        )
        {
            throw new UnsupportedExtensionProvidedException($template);
        }

        if ($hasExtension)
        {
            $template = \preg_replace('/\..+$/', '.' . $finalExtension, $template);
        }
        else
        {
            $template .= '.' . $finalExtension;
        }

        $app = \XF::app();

        $useFriendlyUrls = $app->options()->useFriendlyUrls;
        $style = $this->getStyle() ?: $this->app->style();
        $styleId = $style->getId();
        $languageId = $templater->getLanguage()->getId();
        $lastModified = $style->getLastModified();

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