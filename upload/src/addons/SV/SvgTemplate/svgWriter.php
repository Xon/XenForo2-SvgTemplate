<?php

namespace SV\SvgTemplate;

use XF\CssWriter;
use XF\Http\ResponseStream;

class svgWriter extends CssWriter
{
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

    public function finalizeOutput($output)
    {
        return $output;
    }

    public function getResponse($output)
    {
        $response = parent::getResponse($output);
        if ($output instanceof ResponseStream)
        {
            $response->compressIfAble(false);
            $response->header('content-encoding', 'gzip');
            $response->header('vary', 'Accept-Encoding');
        }
        if ($output)
        {
            $response->contentType('image/svg+xml', 'utf-8');
        }
        else
        {
            $response->contentType('text/html', 'utf-8');
            $message = \XF::phrase('requested_page_not_found');
            $response->body($message);
            $response->httpCode(404);
        }

        return $response;
    }

    public function get304Response()
    {
        $response = parent::get304Response();
        $response->contentType('image/svg+xml', 'utf-8');

        return $response;
    }
}