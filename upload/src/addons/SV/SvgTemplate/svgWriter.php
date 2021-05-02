<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SvgTemplate;

use XF\App as BaseApp;
use XF\CssWriter;
use XF\Http\ResponseStream;
use XF\Http\Response;
use League\Flysystem\MountManager as FlysystemMountManager;
use XF\Util\File as FileUtil;

class svgWriter extends CssWriter
{
    const CACHE_PNG = false;
    const SVG_TO_PNG_ABSTRACT_PATH = 'internal-data://sv/svg_template/svg_rendered_png/%s.png';

    public function run(array $templates, $styleId, $languageId, $validation = null)
    {
        $request = \XF::app()->request();
        /** @var svgRenderer $renderer */
        $renderer = $this->renderer;

        $showDebugOutput = (\XF::$debugMode && $request->get('_debug'));
        if (!$showDebugOutput && strpos($request->getServer('HTTP_ACCEPT_ENCODING', ''), 'gzip') !== false)
        {
            $renderer->setForceRawCache(true);
        }

        return parent::run($templates, $styleId, $languageId, $validation);
    }

    /**
     * @return \XF\CssRenderer|svgRenderer
     */
    public function getRenderer() : svgRenderer
    {
        return $this->renderer;
    }

    /**
     * @return bool
     */
    public function isRenderingPng() : bool
    {
        return $this->getRenderer()->isRenderingPng();
    }

    public function finalizeOutput($output)
    {
        return $output;
    }

    protected function setupResponse(Response $response) : Response
    {
        if ($this->isRenderingPng())
        {
            // no clue why charset is empty string but trying to replicate whatever I saw in:
            // \XF\Pub\View\Error\EmbeddedImageRequest::renderRaw
            $response->contentType('image/png', '');
            $response->body('');
        }
        else
        {
            $response->contentType('image/svg+xml', 'utf-8');
        }

        return $response;
    }

    public function getResponse($output)
    {
        $response = parent::getResponse($output);
        $response = $this->setupResponse($response);

        $hasOutput = false;
        if ($output instanceof ResponseStream)
        {
            if ($output->getLength())
            {
                $hasOutput = true;
                $response->compressIfAble(false);
                if (!$this->isRenderingPng())
                {
                    $response->header('content-encoding', 'gzip');
                    $response->header('vary', 'Accept-Encoding');
                }
            }
        }
        else if ($output)
        {
            $hasOutput = true;
        }

        if ($hasOutput)
        {
            if ($this->isRenderingPng())
            {
                if ($output instanceof ResponseStream)
                {
                    $output = \gzdecode($output->getContents());
                }

                $img = null;
                $caching = static::CACHE_PNG;
                $hash = \md5($output);
                $abstractPath = \sprintf(static::SVG_TO_PNG_ABSTRACT_PATH, $hash);
                $fs = $this->fs();

                if ($caching && $fs->has($abstractPath))
                {
                    $img = $response->responseStream($fs->readStream($abstractPath), $fs->getSize($abstractPath));
                }

                if (!$img)
                {
                    $im = new \Imagick();
                    $im->setBackgroundColor(new \ImagickPixel('transparent'));
                    $im->readImageBlob($output);
                    $im->setImageFormat('png');
                    $img = $im->getImageBlob();
                    $im->clear();
                    $im->destroy();
                }

                if ($caching)
                {
                    $fs->write($abstractPath, $img);
                }

                $response->compressIfAble(false);
                $response->body($img);
            }
        }
        else
        {
            $response->body('');
            $response->httpCode(404);
        }

        return $response;
    }

    public function get304Response()
    {
        $response = parent::get304Response();

        $response = $this->setupResponse($response);

        return $response;
    }

    /**
     * @return BaseApp
     */
    protected function app() : BaseApp
    {
        return $this->app;
    }

    /**
     * @return FlysystemMountManager
     */
    protected function fs() : FlysystemMountManager
    {
        return $this->app()->fs();
    }
}