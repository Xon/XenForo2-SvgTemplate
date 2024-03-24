<?php

namespace SV\SvgTemplate\SV\StandardLib;

use SV\SvgTemplate\Repository\Svg;
use XF\Mvc\Reply\AbstractReply;
use XF\PreEscaped;
use XF\Template\Templater as BaseTemplater;

/**
 * Extends \SV\StandardLib\TemplaterHelper
 */
class TemplaterHelper extends XFCP_TemplaterHelper
{
    public $automaticSvgUrlWriting = true;
    public $svPngSupportEnabled = false;

    /** @var Svg */
    protected $svSvgRepo;

    public function setup()
    {
        $this->svSvgRepo = $this->app->repository('SV\SvgTemplate:Svg');
        $this->svPngSupportEnabled = $this->svSvgRepo->isSvg2PngEnabled();

        parent::setup();
    }

    public function addDefaultHandlers()
    {
        parent::addDefaultHandlers();

        $this->addFunction('getsvgurl', 'fnGetSvgUrl');
        $this->addFunction('getsvgurlas', 'fnGetSvgUrlAs');
    }

    protected function populateTemplaterGlobalData(array &$data, ?AbstractReply $reply = null)
    {
        parent::populateTemplaterGlobalData($data, $reply);
        $this->injectSvgArgs($data);
    }

    public function injectSvgArgs(array &$xf)
    {
        $xf['svg'] = [
            'enabled' => true,
            'as' => [
                'png' => $this->svPngSupportEnabled,
            ]
        ];
    }

    /**
     * @param BaseTemplater $templater
     * @param bool|null     $escape
     * @param string        $template
     * @param string        $extension
     * @param bool          $includeValidation
     * @return string|PreEscaped
     * @noinspection PhpMissingParamTypeInspection
     */
    public function fnGetSvgUrlAs(BaseTemplater $templater, &$escape, string $template, string $extension, bool $includeValidation = false)
    {
        return $this->svSvgRepo->getSvgUrl($templater, $escape, $template, $this->svPngSupportEnabled , $this->automaticSvgUrlWriting, $includeValidation, $extension);
    }

    /**
     * @param BaseTemplater $templater
     * @param bool|null     $escape
     * @param string        $template
     * @param bool          $includeValidation
     * @return string|PreEscaped
     * @noinspection PhpMissingParamTypeInspection
     */
    public function fnGetSvgUrl(BaseTemplater $templater, &$escape, string $template, bool $includeValidation = false)
    {
        return $this->svSvgRepo->getSvgUrl($templater, $escape, $template, $this->svPngSupportEnabled , $this->automaticSvgUrlWriting, $includeValidation, '');
    }
}