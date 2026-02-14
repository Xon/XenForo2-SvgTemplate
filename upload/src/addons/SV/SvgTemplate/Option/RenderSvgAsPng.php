<?php

namespace SV\SvgTemplate\Option;

use Imagick;
use SV\StandardLib\Helper;
use SV\SvgTemplate\Repository\Svg as SvgRepository;
use XF\Entity\Option as OptionEntity;
use XF\Option\AbstractOption;
use XF\Repository\Style as StyleRepository;
use function extension_loaded;
use function is_callable;

/**
 * @since 2.3.0 rc1
 */
class RenderSvgAsPng extends AbstractOption
{
    protected static function getOptionTemplateName(OptionEntity $option): string
    {
        return 'admin:option_template_' . $option->option_id;
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected static function getOptionTemplateParams(OptionEntity $option): array
    {
        $svgRepo = SvgRepository::get();

        $params = [
            'procOpenCallStatus'     => is_callable('proc_open'),
            'systemCallStatus'       => is_callable('system'),
            'browserDetectionStatus' => $svgRepo->isSvBrowserDetectionActive(),
            'imagickStatus'          => extension_loaded('imagick'),
            'imagickSvgFormatStatus' => false,
            'imagickPngFormatStatus' => false,
        ];

        if ($params['imagickStatus'])
        {
            $params['imagickSvgFormatStatus'] = Imagick::queryFormats('SVG');
            $params['imagickPngFormatStatus'] = Imagick::queryFormats('PNG');
        }

        return $params;
    }

    public static function renderOption(OptionEntity $option, array $htmlParams): string
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
        \XF::runLater(function () use ($oldValue, $option) {
            if ($oldValue !== $option->option_value)
            {
                $styleRepo = Helper::repository(StyleRepository::class);
                $styleRepo->updateAllStylesLastModifiedDateLater();
            }
        });

        return true;
    }
}