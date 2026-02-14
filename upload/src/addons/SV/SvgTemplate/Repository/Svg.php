<?php

namespace SV\SvgTemplate\Repository;

use Imagick;
use ImagickPixel;
use LogicException;
use SV\BrowserDetection\Listener;
use SV\StandardLib\Helper;
use SV\SvgTemplate\svgRenderer;
use SV\SvgTemplate\svgWriter;
use SV\SvgTemplate\XF\Template\Exception\UnsupportedExtensionProvidedException;
use Symfony\Component\Process\Process;
use XF\Entity\ClassExtension as ClassExtensionEntity;
use XF\Finder\ClassExtension as ClassExtensionFinder;
use XF\Http\Response;
use XF\Mvc\Entity\Repository;
use XF\Template\Templater;
use XF\Util\File;
use function array_key_exists;
use function array_keys;
use function array_values;
use function base64_encode;
use function extension_loaded;
use function file_get_contents;
use function file_put_contents;
use function in_array;
use function is_callable;
use function is_string;
use function pathinfo;
use function preg_replace;
use function str_replace;
use function strlen;
use function strtr;
use function system;
use function trim;
use function unlink;
use function urlencode;

class Svg extends Repository
{
    public static function get(): self
    {
        return Helper::repository(self::class);
    }

    public function isSvBrowserDetectionActive(): bool
    {
        return array_key_exists('SV/BrowserDetection', \XF::app()->container('addon.cache'));
    }

    public function isSvg2PngEnabled(): bool
    {
        $renderSvgAsPng = \XF::options()->svSvgTemplate_renderSvgAsPng ?? [];
        $conversationMethod = $renderSvgAsPng['type'] ?? '';
        switch ($conversationMethod)
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

    public function requiresConvertingSvg2Png(): bool
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
        switch ($conversationMethod)
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
                '{destFile}'   => $tempDestFile,
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

    public function renderSvgResponse(array $input): void
    {
        $app = \XF::app();

        /** @var svgRenderer $renderer */
        /** @var Response $response */
        [$renderer, $response] = $this->renderSvg($app->templater(), $input['svg'] ?? '', $input['s'], $input['l'], $input['k'], $input['d']);
        if ($response->httpCode() === 304)
        {
            $response->send($app->request());
        }

        if ($renderer->showDebugOutput)
        {
            $response->contentType('text/html', 'utf-8');
            $response->body($app->debugger()->getDebugPageHtml($app));
        }
        $response->send($app->request());
    }

    public function renderSvg(Templater $templater, string $template, ?int $styleId, ?int $languageId, ?bool $validation, ?int $date = null, bool $allow304 = true): array
    {
        $app = \XF::app();

        $renderer = svgRenderer::factory($app, $templater);
        $writer = svgWriter::factory($app, $renderer);
        $writer->setValidator($app->container('css.validator'));

        if ($allow304 && !$renderer->showDebugOutput && $writer->canSend304($app->request()))
        {
            return [$renderer, $writer->get304Response()];
        }

        return [$renderer, $writer->run([$template], $styleId, $languageId, $validation, $date)];
    }

    protected function parseTemplateName(string $template, bool $pngSupport, bool $autoUrlRewrite, string $forceExtension): ?array
    {
        if ($template === '')
        {
            throw new LogicException('$templateName is required');
        }

        $parts = @pathinfo($template);
        $extension = $parts['extension'] ?? '';
        $dirname = $parts['dirname'] ?? '';
        $filename = $parts['filename'] ?? $template;
        $hasExtension = strlen($extension) !== 0;

        $supportedExtensions = $pngSupport ? ['svg', 'png'] : ['svg'];
        if ($forceExtension !== '')
        {
            if (!in_array($forceExtension, $supportedExtensions, true))
            {
                return null;
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

                return null;
            }

            if ($forceExtension)
            {
                return null;
            }

            throw new UnsupportedExtensionProvidedException($template);
        }

        return [$filename, $finalExtension];
    }

    public function renderSvgAsInlineCss(Templater $templater, string $template, bool $base64Encode, bool $escapeAllWhiteSpace): string
    {
        $templateInfo = $this->parseTemplateName($template, false, false, 'svg');
        if ($templateInfo === null)
        {
            return '';
        }
        [$filename, $finalExtension] = $templateInfo;

        $template = 'public:' . $filename . '.' . $finalExtension;

        $renderer = svgRenderer::factory(\XF::app(), $templater);
        $error = null;
        $output = $renderer->renderTemplate($template, $error);
        if ($error !== null)
        {
            throw new \LogicException("Failed to render {$template} as inline css");
        }
        else if ($output === '')
        {
            $output = '<svg xmlns=\'http://www.w3.org/2000/svg\'></svg>';
        }

        if ($base64Encode)
        {
            $output = base64_encode($output);

            return 'data:image/svg+xml;base64,' . $output;
        }

        // https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/Values/url_function#url
        // The quotes are generally optional—they are required if the URL includes parentheses, whitespace, or quotes (unless these characters are escaped), or if the address includes control characters above 0x7e.
        // Normal string syntax rules apply: double quotes cannot occur inside double quotes and single quotes cannot occur inside single quotes unless escaped

        // replacements for shorter colors due to # being escaped
        $replacements = $this->shorterCssColorNames();
        if ($escapeAllWhiteSpace)
        {
            $replacements['%20'] = '/\s+/';
        }
        $output = preg_replace(array_values($replacements), array_keys($replacements), $output);

        // unconditional replacements
        $output = str_replace(['"', '#'], ["'", '%23'], $output);

        return '"data:image/svg+xml,' . $output . '"';
    }

    protected function shorterCssColorNames() : array
    {
        /** @noinspection SpellCheckingInspection */
        return [
            'aqua'     => '/#00ffff(ff)?(?!\w)|#0ff(f)?(?!\w)/i',
            'azure'    => '/#f0ffff(ff)?(?!\w)/i',
            'beige'    => '/#f5f5dc(ff)?(?!\w)/i',
            'bisque'   => '/#ffe4c4(ff)?(?!\w)/i',
            'black'    => '/#000000(ff)?(?!\w)|#000(f)?(?!\w)/i',
            'blue'     => '/#0000ff(ff)?(?!\w)|#00f(f)?(?!\w)/i',
            'brown'    => '/#a52a2a(ff)?(?!\w)/i',
            'coral'    => '/#ff7f50(ff)?(?!\w)/i',
            'cornsilk' => '/#fff8dc(ff)?(?!\w)/i',
            'crimson'  => '/#dc143c(ff)?(?!\w)/i',
            'cyan'     => '/#00ffff(ff)?(?!\w)|#0ff(f)?(?!\w)/i',
            'darkblue' => '/#00008b(ff)?(?!\w)/i',
            'darkcyan' => '/#008b8b(ff)?(?!\w)/i',
            'darkgrey' => '/#a9a9a9(ff)?(?!\w)/i',
            'darkred'  => '/#8b0000(ff)?(?!\w)/i',
            'deeppink' => '/#ff1493(ff)?(?!\w)/i',
            'dimgrey'  => '/#696969(ff)?(?!\w)/i',
            'gold'     => '/#ffd700(ff)?(?!\w)/i',
            'green'    => '/#008000(ff)?(?!\w)/i',
            'grey'     => '/#808080(ff)?(?!\w)/i',
            'honeydew' => '/#f0fff0(ff)?(?!\w)/i',
            'hotpink'  => '/#ff69b4(ff)?(?!\w)/i',
            'indigo'   => '/#4b0082(ff)?(?!\w)/i',
            'ivory'    => '/#fffff0(ff)?(?!\w)/i',
            'khaki'    => '/#f0e68c(ff)?(?!\w)/i',
            'lavender' => '/#e6e6fa(ff)?(?!\w)/i',
            'lime'     => '/#00ff00(ff)?(?!\w)|#0f0(f)?(?!\w)/i',
            'linen'    => '/#faf0e6(ff)?(?!\w)/i',
            'maroon'   => '/#800000(ff)?(?!\w)/i',
            'moccasin' => '/#ffe4b5(ff)?(?!\w)/i',
            'navy'     => '/#000080(ff)?(?!\w)/i',
            'oldlace'  => '/#fdf5e6(ff)?(?!\w)/i',
            'olive'    => '/#808000(ff)?(?!\w)/i',
            'orange'   => '/#ffa500(ff)?(?!\w)/i',
            'orchid'   => '/#da70d6(ff)?(?!\w)/i',
            'peru'     => '/#cd853f(ff)?(?!\w)/i',
            'pink'     => '/#ffc0cb(ff)?(?!\w)/i',
            'plum'     => '/#dda0dd(ff)?(?!\w)/i',
            'purple'   => '/#800080(ff)?(?!\w)/i',
            'red'      => '/#ff0000(ff)?(?!\w)|#f00(f)?(?!\w)/i',
            'salmon'   => '/#fa8072(ff)?(?!\w)/i',
            'seagreen' => '/#2e8b57(ff)?(?!\w)/i',
            'seashell' => '/#fff5ee(ff)?(?!\w)/i',
            'sienna'   => '/#a0522d(ff)?(?!\w)/i',
            'silver'   => '/#c0c0c0(ff)?(?!\w)/i',
            'skyblue'  => '/#87ceeb(ff)?(?!\w)/i',
            'snow'     => '/#fffafa(ff)?(?!\w)/i',
            'tan'      => '/#d2b48c(ff)?(?!\w)/i',
            'teal'     => '/#008080(ff)?(?!\w)/i',
            'thistle'  => '/#d8bfd8(ff)?(?!\w)/i',
            'tomato'   => '/#ff6347(ff)?(?!\w)/i',
            'violet'   => '/#ee82ee(ff)?(?!\w)/i',
            'wheat'    => '/#f5deb3(ff)?(?!\w)/i',
            'white'    => '/#ffffff(ff)?(?!\w)|#fff(f)?(?!\w)/i',
        ];
    }

    public function getSvgUrl(Templater $templater, &$escape, string $template, bool $pngSupport, bool $autoUrlRewrite, bool $includeValidation, string $forceExtension): string
    {
        $templateInfo = $this->parseTemplateName($template, $pngSupport, $autoUrlRewrite, $forceExtension);

        if ($templateInfo === null)
        {
            return '';
        }
        [$filename, $finalExtension] = $templateInfo;

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