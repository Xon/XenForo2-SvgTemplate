<?php

namespace SV\SvgTemplate\Repository;

use SV\BrowserDetection\Listener;
use Symfony\Component\Process\Process;
use XF\Mvc\Entity\Repository;
use XF\Util\File;

class Svg extends Repository
{
    /**
     * @return bool
     */
    public function isSvBrowserDetectionActive() : bool
    {
        return \array_key_exists(
            'SV/BrowserDetection',
            \XF::app()->container('addon.cache')
        );
    }

    /**
     * Returns if all the requirements for converting SVG to PNG pass.
     *
     * @return bool
     */
    public function isSvg2PngEnabled() : bool
    {
        $renderSvgAsPng = $this->app()->options()->svSvgTemplate_renderSvgAsPng ?? [];
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

    /**
     * Returns if the SVG needs to be converted as PNG.
     *
     * @return bool
     *
     * @throws \Exception
     */
    public function requiresConvertingSvg2Png() : bool
    {
        if (!$this->isSvBrowserDetectionActive())
        {
            return false;
        }

        $mobileDetect = Listener::getMobileDetection();
        return $mobileDetect->isMobile() || $mobileDetect->isTablet();
    }

    public function convertSvg2Png(string $svg): string
    {
        if (!\strlen($svg))
        {
            return '';
        }

        $renderSvgAsPng = $this->app()->options()->svSvgTemplate_renderSvgAsPng ?? [];
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
        if (!\extension_loaded('imagick'))
        {
            return false;
        }

        if (!\Imagick::queryFormats('SVG'))
        {
            return false;
        }

        if (!\Imagick::queryFormats('PNG'))
        {
            return false;
        }

        return true;
    }

    protected function convertSvg2PngImagick(string $output): string
    {
        $im = new \Imagick();
        $im->setBackgroundColor(new \ImagickPixel('transparent'));
        $im->readImageBlob('<?xml version="1.0" encoding="UTF-8" standalone="no" ?>' . $output);
        $im->setImageFormat('png');
        $img = $im->getImageBlob();
        $im->clear();
        $im->destroy();

        return $img;
    }

    protected function convertSvg2PngCliEnabled(string $command): bool
    {
        if (!\strlen($command))
        {
            return false;
        }

        if (!\is_callable('system'))
        {
            return false;
        }

        return true;
    }

    protected function convertSvg2PngCli(string $command, string $svg): string
    {
        $dir = File::createTempDir();
        $tempSourceFile = $dir . '/file.svg';
        $tempDestFile = $dir . '/file.png';

        \file_put_contents($tempSourceFile, $svg);

        $command = \strtr($command, [
            '{destFile}' => $tempDestFile,
            '{sourceFile}' => $tempSourceFile,
        ]);

        // dead simple, no real input/output capturing
        \system($command);

        $img = @\file_get_contents($tempDestFile);

        return is_string($img) ? $img : '';
    }

    protected function convertSvg2PngCliPipeEnabled(string $command): bool
    {
        if (!\strlen($command))
        {
            return false;
        }

        if (!\is_callable('proc_open'))
        {
            return false;
        }

        return true;
    }

    protected function convertSvg2PngCliPipe(string $command, string $svg): string
    {
        $process = new Process($command);
        $process->setTimeout(null);
        $process->setCommandLine($process->getCommandLine());
        $process->setInput($svg);
        $process->run();
        $img = $process->getOutput();

        return is_string($img) ? $img : '';
    }
}