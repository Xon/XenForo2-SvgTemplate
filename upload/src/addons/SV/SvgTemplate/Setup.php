<?php

namespace SV\SvgTemplate;

use SV\StandardLib\InstallerHelper;
use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;

/**
 * Add-on installation, upgrade, and uninstall routines.
 */
class Setup extends AbstractSetup
{
    use InstallerHelper {
        checkRequirements as protected checkRequirementsTrait;
    }

    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    public function postInstall(array &$stateChanges)
    {
        $this->syncSvgRouterIntegrationOption();
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

    //proc_open

    /**
     * @param array $errors
     * @param array $warnings
     */
    public function checkRequirements(&$errors = [], &$warnings = [])
    {
        $this->checkRequirementsTrait($errors, $warnings);

        if (\extension_loaded('imagick'))
        {
            if (!\Imagick::queryFormats('PNG'))
            {
                $warnings[] = 'imagick extension does not support PNGs, which is required to convert SVGs to PNGs using imagick';
            }
            else if (!\Imagick::queryFormats('SVG'))
            {
                $warnings[] = 'imagick extension does not support SVGs, which is required to convert SVGs to PNGs using imagick';
            }
        }

        if (!\is_callable('proc_open') || !\is_callable('system'))
        {
            $warnings[] = 'proc_open/system is required for converting SVGs to PNGs via CLI';
        }
    }
}
