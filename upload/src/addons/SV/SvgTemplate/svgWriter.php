<?php

namespace SV\SvgTemplate;

use XF\CssWriter;

class svgWriter extends CssWriter
{
    public function finalizeOutput($output)
    {
        return $output;
    }

    public function getResponse($output)
    {
        $response = parent::getResponse($output);
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