<?php

namespace SV\SvgTemplate\Option;

use SV\SvgTemplate\Repository\Svg as SvgRepo;
use XF\Entity\Option as OptionEntity;
use XF\Option\AbstractOption;
use XF\Repository\Style;
use function is_callable, extension_loaded;

/**
 * @since 2.3.0 rc1
 */
class RenderSvgAsPng extends AbstractOption
{
    protected static function getOptionTemplateName(OptionEntity $option) : string
    {
        return 'admin:option_template_' . $option->option_id;
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected static function getOptionTemplateParams(OptionEntity $option) : array
    {
        /** @var SvgRepo $svgRepo */
        $svgRepo = \SV\StandardLib\Helper::repository(\SV\SvgTemplate\Repository\Svg::class);

        $params = [
            'procOpenCallStatus' => is_callable('proc_open'),
            'systemCallStatus' => is_callable('system'),
            'browserDetectionStatus' => $svgRepo->isSvBrowserDetectionActive(),
            'imagickStatus' => extension_loaded('imagick'),
            'imagickSvgFormatStatus' => false,
            'imagickPngFormatStatus' => false
        ];

        if ($params['imagickStatus'])
        {
            $params['imagickSvgFormatStatus'] = \Imagick::queryFormats('SVG');
            $params['imagickPngFormatStatus'] = \Imagick::queryFormats('PNG');
        }

        return $params;
    }

    public static function renderOption(OptionEntity $option, array $htmlParams) : string
    {
        $optionTemplate = static::getOptionTemplateName($option);
        $optionParams = static::getOptionTemplateParams($option);

        return self::getTemplate($optionTemplate, $option, $htmlParams, $optionParams);
    }

    public static function verifyOption(&$value, OptionEntity $option): bool
    {
        $type = $value['type'] ?? '';
        if (!$type)
        {
            $value = [];
        }

        $oldValue = $option->option_value;
        \XF::runLater(function() use ($oldValue, $option) {
            if ($oldValue !== $option->option_value)
            {
                /** @var Style $styleRepo */
                $styleRepo = \SV\StandardLib\Helper::repository(\XF\Repository\Style::class);
                $styleRepo->updateAllStylesLastModifiedDateLater();
            }
        });

        return true;
    }
}