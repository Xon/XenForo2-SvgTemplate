<?php
/**
 * @noinspection PhpMissingParamTypeInspection
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SvgTemplate\XF\Mail;

use SV\SvgTemplate\Globals;
use XF\App;
use XF\Language;

/**
 * Extends \XF\Mail\Templater
 */
class Templater extends XFCP_Templater
{
    public $automaticSvgUrlWriting = false;
    public $svPngSupportEnabled = false;

    /** @var \SV\SvgTemplate\Repository\Svg */
    private $svSvgRepo;

    public function __construct(App $app, Language $language, $compiledPath)
    {
        parent::__construct($app, $language, $compiledPath);
        Globals::$templater = $this;
        $this->svSvgRepo = $this->app->repository('SV\SvgTemplate:Svg');
        $this->svPngSupportEnabled = $this->svSvgRepo->isSvg2PngEnabled();
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
        if ($name === 'xf' && \is_array($value))
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
        return $this->svSvgRepo->getSvgUrl($templater, $escape, $template, $this->svPngSupportEnabled , $this->automaticSvgUrlWriting, $includeValidation, $extension);
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
        return $this->svSvgRepo->getSvgUrl($templater, $escape, $template, $this->svPngSupportEnabled , $this->automaticSvgUrlWriting, $includeValidation, '');
    }
}