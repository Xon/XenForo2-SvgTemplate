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
use XF\Mvc\Reply\AbstractReply;

/**
 * Extends \XF\Template\Templater
 */
class Templater extends XFCP_Templater
{
    public $automaticSvgUrlWriting = true;
    public $svPngSupportEnabled = false;

    public function __construct(App $app, Language $language, $compiledPath)
    {
        parent::__construct($app, $language, $compiledPath);
        Globals::$templater = $this;
        $this->svPngSupportEnabled = Svg2png::supportForSvg2PngEnabled();
    }

    protected function injectSvgArgs(array &$xf)
    {
        $xf['svg'] = [
            'enabled' => true,
            'as' => [
                'png' => $this->svPngSupportEnabled,
            ]
        ];
    }

    public function addDefaultParams(array $params)
    {
        if (isset($params['xf']))
        {
            $this->injectSvgArgs($params['xf']);
        }

        parent::addDefaultParams($params);
    }

    public function addDefaultParam($name, $value)
    {
        if ($name === 'xf' && is_array($value))
        {
            $this->injectSvgArgs($value);
        }

        parent::addDefaultParam($name, $value);
    }

    public function getDefaultParam($name)
    {
        return $this->defaultParams[$name] ?? null;
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
        $this->addFunction('getsvgurlas', [$this, 'fnGetSvgUrlAs']);
    }


    /**
     * @param \XF\Template\Templater $templater
     * @param string                 $escape
     * @param string                 $template
     * @param bool                   $includeValidation
     * @return string
     */
    public function fnGetSvgUrlAs($templater, &$escape, $template, $extension, $includeValidation = false)
    {
        return $this->getSvgUrlInternal($templater, $escape, $template, $includeValidation, $extension);
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
        return $this->getSvgUrlInternal($templater, $escape, $template, $includeValidation);
    }

    protected function getSvgUrlInternal($templater, &$escape, $template, $includeValidation = false, $forceExtension = '')
    {
        if (!$template)
        {
            throw new \LogicException('$templateName is required');
        }

        $parts = \pathinfo($template);
        $extension = $parts['extension'];
        $hasExtension = !empty($extension);

        $supportedExtensions = $this->svPngSupportEnabled ? ['svg', 'png'] : ['svg'];
        if ($forceExtension)
        {
            if (!\in_array($forceExtension, $supportedExtensions, true))
            {
                return '';
            }
            $finalExtension = $forceExtension;
        }
        else
        {
            $finalExtension = $this->automaticSvgUrlWriting && Svg2png::requiresConvertingSvg2Png() ? 'png' : 'svg';
        }

        if (
            ($hasExtension && !\in_array($extension, $supportedExtensions, true)) // unsupported extension
            || (!empty($parts['dirname']) && $parts['dirname'] !== '.') // contains path info
        )
        {
            if ($forceExtension)
            {
                return '';
            }

            throw new UnsupportedExtensionProvidedException($template);
        }

        $template = $parts['filename'] . '.' . $finalExtension;

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