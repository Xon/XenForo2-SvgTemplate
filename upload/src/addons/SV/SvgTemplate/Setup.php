<?php

namespace SV\SvgTemplate;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;

/**
 * Add-on installation, upgrade, and uninstall routines.
 */
class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    public function installStep1()
    {
    }

    public function postUpgrade($previousVersion, array &$stateChanges)
    {
        $this->syncSvgRouterIntegrationOption();
    }

    protected function syncSvgRouterIntegrationOption()
    {
        $options = \XF::options();
        if (!$options->offsetExists('svSvgTemplateRouterIntegration'))
        {
            return;
        }

        /** @var \XF\Entity\ClassExtension $classExtension */
        $classExtension = \XF::finder('XF:ClassExtension')
                             ->where('from_class', '=', 'XF\Mvc\Router')
                             ->where('to_class', '=', 'SV\SvgTemplate\XF\Mvc\Router')
                             ->fetchOne();
        if ($classExtension)
        {
            $classExtension->active = (bool)$options->svSvgTemplateRouterIntegration;
            $classExtension->saveIfChanged();
        }
    }
}
