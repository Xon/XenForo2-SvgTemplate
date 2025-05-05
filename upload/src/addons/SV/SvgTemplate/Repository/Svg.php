<?php

namespace SV\SvgTemplate\Repository;

use Imagick;
use ImagickPixel;
use LogicException;
use SV\BrowserDetection\Listener;
use SV\StandardLib\Helper;
use SV\SvgTemplate\XF\Template\Exception\UnsupportedExtensionProvidedException;
use Symfony\Component\Process\Process;
use XF\Entity\ClassExtension as ClassExtensionEntity;
use XF\Finder\ClassExtension as ClassExtensionFinder;
use XF\Mvc\Entity\Repository;
use XF\Template\Templater;
use XF\Util\File;
use function array_key_exists, trim, strlen, pathinfo, system, file_get_contents,is_string, is_callable, extension_loaded, strtr, in_array;
use function file_put_contents;
use function unlink;
use function urlencode;

class Svg extends Repository
{
    public static function get(): self
    {
        return Helper::repository(self::class);
    }

    public function isSvBrowserDetectionActive() : bool
    {
        return array_key_exists('SV/BrowserDetection', \XF::app()->container('addon.cache'));
    }

    public function isSvg2PngEnabled() : bool
    {
        $renderSvgAsPng = \XF::options()->svSvgTemplate_renderSvgAsPng ?? [];
        $conversationMethod = $renderSvgAsPng['type'] ?? '';
        switch($conversationMethod)
        {
            case 'imagick':
                return $this->convertSvg2PngImagickEnabled();
            case 'cli':
                $command = trim($renderSvgAsPng['cli'] ?? '');
                return $this->convertSvg2PngCliEnabled($command);
            case 'cli-pipe':
                $command = trim($renderSvgAsPng['cli_pipe'] ?? '');
                return $this->convertSvg2PngCliPipeEnabled($command);
            default:
                return false;
        }
    }

    public function requiresConvertingSvg2Png() : bool
    {
        if (!$this->isSvBrowserDetectionActive())
        {
            return false;
        }

        $mobileDetect = Listener::getMobileDetection();
        if (!$mobileDetect)
        {
            return false;
        }

        return $mobileDetect->isMobile() || $mobileDetect->isTablet();
    }

    public function convertSvg2Png(string $svg): string
    {
        if (strlen($svg) === 0)
        {
            return '';
        }

        $renderSvgAsPng = \XF::options()->svSvgTemplate_renderSvgAsPng ?? [];
        $conversationMethod = $renderSvgAsPng['type'] ?? '';
        switch($conversationMethod)
        {
            case 'imagick':
                if ($this->convertSvg2PngImagickEnabled())
                {
                    return $this->convertSvg2PngImagick($svg);
                }
                break;
            case 'cli':
                $command = trim($renderSvgAsPng['cli'] ?? '');
                if ($this->convertSvg2PngCliEnabled($command))
                {
                    return $this->convertSvg2PngCli($command, $svg);
                }
                break;
            case 'cli-pipe':
                $command = trim($renderSvgAsPng['cli_pipe'] ?? '');
                if ($this->convertSvg2PngCliPipeEnabled($command))
                {
                    return $this->convertSvg2PngCliPipe($command, $svg);
                }
                break;
            default:
                return '';
        }

        return '';
    }

    protected function convertSvg2PngImagickEnabled(): bool
    {
        if (!extension_loaded('imagick'))
        {
            return false;
        }

        if (!Imagick::queryFormats('SVG'))
        {
            return false;
        }

        if (!Imagick::queryFormats('PNG'))
        {
            return false;
        }

        return true;
    }

    protected function convertSvg2PngImagick(string $output): string
    {
        $im = new Imagick();
        $im->setBackgroundColor(new ImagickPixel('transparent'));
        $im->readImageBlob('<?xml version="1.0" encoding="UTF-8" standalone="no" ?>' . $output);
        $im->setImageFormat('png');
        $img = $im->getImageBlob();
        $im->clear();
        if (is_callable([$im, 'destroy']))
        {
            /** @noinspection PhpDeprecationInspection */
            $im->destroy();
        }

        return $img;
    }

    protected function convertSvg2PngCliEnabled(string $command): bool
    {
        if (strlen($command) === 0)
        {
            return false;
        }

        if (!is_callable('system'))
        {
            return false;
        }

        return true;
    }

    protected function convertSvg2PngCli(string $command, string $svg): string
    {
        // createTempDir has a race condition, so create a temp file via tempnam, and then add .svg/.png onto the end
        // The file is left created as otherwise tempnam may reuse it
        $filename = File::getTempFile();
        $tempSourceFile = $filename . '.svg';
        $tempDestFile = $filename . '.png';

        file_put_contents($tempSourceFile, $svg);
        try
        {
            $command = strtr($command, [
                '{destFile}' => $tempDestFile,
                '{sourceFile}' => $tempSourceFile,
            ]);

            // dead simple, no real input/output capturing
            system($command);

            $img = @file_get_contents($tempDestFile);
        }
        finally
        {
            @unlink($tempSourceFile);
            @unlink($tempDestFile);
        }

        return is_string($img) ? $img : '';
    }

    protected function convertSvg2PngCliPipeEnabled(string $command): bool
    {
        if (strlen($command) === 0)
        {
            return false;
        }

        if (!is_callable('proc_open'))
        {
            return false;
        }

        return true;
    }

    /** @noinspection RedundantSuppression */
    protected function convertSvg2PngCliPipe(string $command, string $svg): string
    {
        if (\XF::$versionId >= 2030000)
        {
            /** @noinspection PhpUndefinedMethodInspection */
            $process = Process::fromShellCommandline($command);
        }
        else
        {
            /** @noinspection PhpParamsInspection */
            $process = new Process($command);
            /** @noinspection PhpUndefinedMethodInspection */
            $process->setCommandLine($process->getCommandLine());
        }
        $process->setTimeout(null);
        $process->setInput($svg);
        $process->run();
        $img = $process->getOutput();

        return is_string($img) ? $img : '';
    }

    public function getSvgUrl(Templater $templater, &$escape, string $template, bool $pngSupport, bool $autoUrlRewrite, bool $includeValidation, string $forceExtension): string
    {
        if (!$template)
        {
            throw new LogicException('$templateName is required');
        }

        $parts = @pathinfo($template);
        $extension = $parts['extension'] ?? '';
        $dirname = $parts['dirname'] ?? '';
        $filename = $parts['filename'] ?? $template;
        $hasExtension = strlen($extension) !== 0;

        $supportedExtensions = $pngSupport ? ['svg', 'png'] : ['svg'];
        if ($forceExtension)
        {
            if (!in_array($forceExtension, $supportedExtensions, true))
            {
                return '';
            }
            $finalExtension = $forceExtension;
        }
        else
        {
            $finalExtension = ($pngSupport && $autoUrlRewrite && $this->requiresConvertingSvg2Png()) ? 'png' : 'svg';
        }

        if (
            ($hasExtension && !in_array($extension, $supportedExtensions, true)) // unsupported extension
            || ($dirname !== '' && $dirname !== '.') // contains path info
        )
        {
            if (!$pngSupport && $extension === 'png' && \XF::$debugMode)
            {
                \XF::logError("Requesting a png for {$filename}.svg, but is svg => png transcoding is not enabled");
                return '';
            }

            if ($forceExtension)
            {
                return '';
            }

            throw new UnsupportedExtensionProvidedException($template);
        }

        $template = $filename . '.' . $finalExtension;

        $useFriendlyUrls = \XF::options()->useFriendlyUrls;
        $style = $templater->getStyle() ?: \XF::app()->style();
        $styleId = $style->getId();
        $languageId = $templater->getLanguage()->getId();
        $lastModified = $style->getLastModified();

        if ($useFriendlyUrls)
        {
            $url = "data/svg/{$styleId}/{$languageId}/{$lastModified}/{$template}";
        }
        else
        {
            $url = "svg.php?svg={$template}&s={$styleId}&l={$languageId}&d={$lastModified}";
        }

        if ($includeValidation)
        {
            $validationKey = $templater->getCssValidationKey([$template]);
            if ($validationKey)
            {
                $url .= ($useFriendlyUrls ? '?' : '&') . 'k=' . urlencode($validationKey);
            }
        }

        $urlMode = \XF::$versionId >= 2020371 ? 'full' : 'canonical';

        return $templater->fnBaseUrl($templater, $escape, $url, $urlMode);
    }

    public function syncSvgRouterIntegrationOption(?bool $value = null): void
    {
        $value = $value ?? \XF::options()->svSvgTemplateRouterIntegration ?? true;

        /** @var ClassExtensionEntity|null $classExtension */
        $classExtension = Helper::finder(ClassExtensionFinder::class)
                                ->where('from_class', '=', 'XF\Mvc\Router')
                                ->where('to_class', '=', 'SV\SvgTemplate\XF\Mvc\Router')
                                ->fetchOne();
        if ($classExtension !== null)
        {
            $classExtension->active = $value;
            $classExtension->saveIfChanged();
        }
    }
}