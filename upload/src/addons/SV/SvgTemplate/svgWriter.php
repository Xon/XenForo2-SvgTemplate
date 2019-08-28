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
        $response->contentType('image/svg+xml', 'utf-8');
        $hasOutput = false;
        if ($output instanceof ResponseStream)
        {
            if ($output->getLength())
            {
                $hasOutput = true;
                $response->compressIfAble(false);
                $response->header('content-encoding', 'gzip');
                $response->header('vary', 'Accept-Encoding');
            }
        }
        else if ($output)
        {
            $hasOutput = true;
        }
        if (!$hasOutput)
        {
            $response->body('');
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