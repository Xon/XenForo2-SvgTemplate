<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SvgTemplate;

use SV\SvgTemplate\Repository\Svg as SvgRepo;
use XF\App as BaseApp;
use XF\CssWriter;
use XF\Http\ResponseStream;
use XF\Http\Response;
use function strpos, gzdecode, md5;

/**
 * @property svgRenderer $renderer
 */
class svgWriter extends CssWriter
{
    /** @var int */
    public const PNG_CACHE_TIME = 3600; // 1 hour

    public function run(array $templates, $styleId, $languageId, $validation = null, int $date = null): Response
    {
        $request = \XF::app()->request();
        $renderer = $this->renderer;

        if ($date !== null)
        {
            $renderer->setInputModifiedDate($date);
        }

        $showDebugOutput = (\XF::$debugMode && $request->get('_debug'));
        if (!$showDebugOutput && strpos($request->getServer('HTTP_ACCEPT_ENCODING', ''), 'gzip') !== false)
        {
            $renderer->setForceRawCache(true);
        }

        return parent::run($templates, $styleId, $languageId, $validation);
    }

    public function getRenderer() : svgRenderer
    {
        return $this->renderer;
    }

    public function isRenderingPng() : bool
    {
        return $this->getRenderer()->isRenderingPng();
    }

    /**
     * @param string|Response $output
     * @return string|Response
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function finalizeOutput($output)
    {
        return $output;
    }

    protected function setupResponse(Response $response): Response
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

    /**
     * @param string|Response $output
     * @return string|Response
     */
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
                try
                {
                    @\ini_set('zlib.output_compression', 'Off');
                }
                catch (\Throwable $e) {}
                if (!$this->isRenderingPng())
                {
                    $response->header('content-encoding', 'gzip');
                    $response->header('vary', 'Accept-Encoding');
                }
            }
        }
        else if (is_string($output) && $output !== '')
        {
            $hasOutput = true;
        }

        if (!$hasOutput)
        {
            $response->body('');
            $response->httpCode(404);

            return '';
        }

        if (!$this->isRenderingPng())
        {
            return $response;
        }

        /** @noinspection PhpConditionAlreadyCheckedInspection */
        if ($output instanceof ResponseStream)
        {
            $output = gzdecode($output->getContents());
        }

        $cacheKey = $cacheObj = $img = null;
        $caching = $this->app()->options()->svSvgTemplate_cacheRenderedSvg ?? false;
        if ($caching)
        {
            $cacheKey = 'svSvg_Png_' . md5($output);
            $cacheObj = $this->app()->cache('sv-svg-img', false);
            if (!$cacheObj)
            {
                $cacheObj = $this->app()->cache('css', false);
            }
        }

        if ($cacheObj)
        {
            $img = $cacheObj->fetch($cacheKey);
        }

        if (!$img)
        {
            /** @var SvgRepo $svgRepo */
            $svgRepo = \XF::repository('SV\SvgTemplate:Svg');
            $img = $svgRepo->convertSvg2Png($output);

            if ($cacheObj)
            {
                $cacheObj->save($cacheKey, $img, static::PNG_CACHE_TIME);
            }
        }

        if ($img)
        {
            $response->compressIfAble(false);
            $response->body($img);
        }
        else
        {
            $response->body('');
            $response->httpCode(404);
        }

        return $response;
    }

    /** @noinspection PhpUnnecessaryLocalVariableInspection */
    public function get304Response()
    {
        $response = parent::get304Response();

        $response = $this->setupResponse($response);

        return $response;
    }

    protected function app() : BaseApp
    {
        return $this->app;
    }
}