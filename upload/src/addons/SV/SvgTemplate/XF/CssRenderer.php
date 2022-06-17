<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SvgTemplate\XF;

use SV\SvgTemplate\Globals;
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
        Globals::templateHelper($this->templater)->automaticSvgUrlWriting = false;
    }

    public function setTemplater(Templater $templater)
    {
        $this->templater = $templater;
        Globals::templateHelper($this->templater)->automaticSvgUrlWriting = false;
    }

    protected function getRenderParams()
    {
        $params = parent::getRenderParams();

        Globals::templateHelper($this->templater)->injectSvgArgs($params['xf']);

        return $params;
    }
}