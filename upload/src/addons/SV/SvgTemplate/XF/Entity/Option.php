<?php

namespace SV\SvgTemplate\XF\Entity;

use SV\StandardLib\Helper;
use XF\Finder\ClassExtension as ClassExtensionFinder;

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
            $classExtension = Helper::finder(ClassExtensionFinder::class)
                                    ->where('from_class', '=', 'XF\Mvc\Router')
                                    ->where('to_class', '=', 'SV\SvgTemplate\XF\Mvc\Router')
                                    ->fetchOne();
            if ($classExtension)
            {
                $classExtension->active = (bool)$this->option_value;
                $classExtension->saveIfChanged();
            }
        }
    }
}