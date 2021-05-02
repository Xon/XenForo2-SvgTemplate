<?php

namespace SV\SvgTemplate\XF;

use XF\App;
use XF\Template\Templater;

/**
 * Extends \XF\CssRenderer
 *
 * @property \SV\SvgTemplate\XF\Template\Templater templater
 */
class CssRenderer extends XFCP_CssRenderer
{
    public function __construct(App $app, Templater $templater, \Doctrine\Common\Cache\CacheProvider $cache = null)
    {
        parent::__construct($app, $templater, $cache);
        $this->templater->automaticSvgUrlWriting = false;
    }

    public function setTemplater(Templater $templater)
    {
        $this->templater = $templater;
        $this->templater->automaticSvgUrlWriting = false;
    }

    protected function getRenderParams()
    {
        $params = parent::getRenderParams();

        $params['xf'] = $this->templater->getDefaultParam('xf');

        return $params;
    }
}