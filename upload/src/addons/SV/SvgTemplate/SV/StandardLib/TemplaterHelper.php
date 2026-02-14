<?php

namespace SV\SvgTemplate\SV\StandardLib;

use SV\SvgTemplate\Repository\Svg as SvgRepository;
use XF\Mvc\Reply\AbstractReply;
use XF\PreEscaped;
use XF\Template\Templater as BaseTemplater;

/**
 * @extends \SV\StandardLib\TemplaterHelper
 */
class TemplaterHelper extends XFCP_TemplaterHelper
{
    /** @var bool */
    public $automaticSvgUrlWriting = true;
    /** @var bool */
    public $svPngSupportEnabled = false;

    /** @var SvgRepository */
    protected $svSvgRepo;

    public function setup()
    {
        $this->svSvgRepo = SvgRepository::get();
        $this->svPngSupportEnabled = $this->svSvgRepo->isSvg2PngEnabled();

        parent::setup();
    }

    /** @noinspection SpellCheckingInspection */
    public function addDefaultHandlers()
    {
        parent::addDefaultHandlers();

        $this->addFunction('getsvgurl', 'fnGetSvgUrl');
        $this->addFunction('getsvgurlas', 'fnGetSvgUrlAs');
        $this->addFunction('getsvgasinlinecss', 'fnGetSvgAsInlineCss');
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
            'as'      => [
                'png' => $this->svPngSupportEnabled,
            ],
        ];
    }

    public function fnGetSvgAsInlineCss(BaseTemplater $templater, ?bool &$escape, string $template, bool $base64Encode = false, bool $escapeAllWhiteSpace = false): string
    {
        $escape = false;

        return $this->svSvgRepo->renderSvgAsInlineCss($templater, $template, $base64Encode, $escapeAllWhiteSpace);
    }

    /**
     * @param BaseTemplater $templater
     * @param bool|null     $escape
     * @param string        $template
     * @param ?string       $extension
     * @param bool          $includeValidation
     * @return string|PreEscaped
     * @noinspection PhpMissingParamTypeInspection
     */
    public function fnGetSvgUrlAs(BaseTemplater $templater, &$escape, string $template, ?string $extension, bool $includeValidation = false)
    {
        return $this->svSvgRepo->getSvgUrl($templater, $escape, $template, $this->svPngSupportEnabled, $this->automaticSvgUrlWriting, $includeValidation, $extension ?? '');
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
        return $this->svSvgRepo->getSvgUrl($templater, $escape, $template, $this->svPngSupportEnabled, $this->automaticSvgUrlWriting, $includeValidation, '');
    }
}