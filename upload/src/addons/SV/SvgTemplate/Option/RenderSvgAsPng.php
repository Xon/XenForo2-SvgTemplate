<?php

namespace SV\SvgTemplate\Option;

use SV\SvgTemplate\Helper\Svg2png;
use XF\Entity\Option as OptionEntity;
use SV\SvgTemplate\XF\Entity\Option as ExtendedOptionEntity;
use XF\Option\AbstractOption;

/**
 * @since 2.3.0 rc1
 */
class RenderSvgAsPng extends AbstractOption
{
    protected static function getOptionTemplateName(OptionEntity $option) : string
    {
        return 'admin:option_template_' . $option->option_id;
    }

    protected static function getOptionTemplateParams(OptionEntity $option) : array
    {
        $params = [
            'browserDetectionStatus' => Svg2png::isSvBrowserDetectionActive(),
            'imagickStatus' => \extension_loaded('imagick'),
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

    /**
     * @param OptionEntity|ExtendedOptionEntity $option
     * @param array $htmlParams
     *
     * @return string
     */
    public static function renderOption(OptionEntity $option, array $htmlParams) : string
    {
        $optionTemplate = static::getOptionTemplateName($option);
        $optionParams = static::getOptionTemplateParams($option);

        return self::getTemplate($optionTemplate, $option, $htmlParams, $optionParams);
    }
}