<?php

namespace SV\SvgTemplate\XF\Entity;

use SV\SvgTemplate\Repository\Svg as SvgRepository;

/**
 * @extends \XF\Entity\Option
 */
class Option extends XFCP_Option
{
    protected function _postSave()
    {
        parent::_postSave();

        if ($this->option_id === 'svSvgTemplateRouterIntegration' && $this->isChanged('option_value'))
        {
            SvgRepository::get()->syncSvgRouterIntegrationOption($this->option_value);
        }
    }
}