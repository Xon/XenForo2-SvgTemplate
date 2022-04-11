<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SvgTemplate\XF;

use SV\SvgTemplate\SV\StandardLib\TemplaterHelper;
use XF\App;
use XF\Template\Templater;

/**
 * Extends \XF\CssRenderer
 *
 */
class CssRenderer extends XFCP_CssRenderer
{
    public function __construct(App $app, Templater $templater, \Doctrine\Common\Cache\CacheProvider $cache = null)
    {
        parent::__construct($app, $templater, $cache);
        $this->templateHelper()->automaticSvgUrlWriting = false;
    }

    protected function templateHelper(): TemplaterHelper
    {
        return TemplaterHelper::get($this->templater);
    }

    public function setTemplater(Templater $templater)
    {
        $this->templater = $templater;
        $this->templateHelper()->automaticSvgUrlWriting = false;
    }

    protected function getRenderParams()
    {
        $params = parent::getRenderParams();

        $this->templateHelper()->injectSvgArgs($params['xf']);

        return $params;
    }
}