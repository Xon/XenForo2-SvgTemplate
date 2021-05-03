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
        $renderSvgAsPng = \XF::app()->options()->svSvgTemplate_renderSvgAsPng ?? false;
        if (!$renderSvgAsPng)
        {
            return false;
        }

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

        $options = \XF::app()->options();
        $conversationMethod = $options->svSvgTemplate_renderSvgAsPng ?? '';
        switch($conversationMethod)
        {
            case 'imagick':
                return $this->convertSvg2PngImagick($svg);
            case 'cli':
                $command = $options->svSvgTemplate_cliCommand ?? '';
                return $this->convertSvg2PngCli($command, $svg);
            case 'cli-pipe':
                $command = $options->svSvgTemplate_cliCommand ?? '';
                return $this->convertSvg2PngCliPipe($command, $svg);
            default:
                return '';
        }
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

    protected function convertSvg2PngCli(string $command, string $svg): string
    {
        if (!\strlen($command))
        {
            return '';
        }

        $dir = File::createTempDir();
        $tempSourceFile = $dir . '/file.svg';
        $tempDestFile = $dir . '/file.png';

        \file_put_contents($tempSourceFile, $svg);

        $command = \strtr($command, [
            'destFile' => $tempDestFile,
            'sourceFile' => $tempSourceFile,
        ]);

        // dead simple, no real input/output capturing
        \system($command);

        $img = @\file_get_contents($tempDestFile);

        return is_string($img) ? $img : '';
    }

    protected function convertSvg2PngCliPipe(string $command, string $svg): string
    {
        if (!\strlen($command))
        {
            return '';
        }

        $process = new Process($command);
        $process->setTimeout(null);
        $process->setCommandLine($process->getCommandLine());
        $process->setInput($svg);
        $process->run();
        $img = $process->getOutput();

        return is_string($img) ? $img : '';
    }
}