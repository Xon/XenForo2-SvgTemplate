<?php

namespace SV\SvgTemplate\Pub\Controller;

use SV\SvgTemplate\svgWriter;
use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class SvgRenderer extends AbstractController
{

    public function actionIndex(ParameterBag $params)
    {
        $app = $this->app();
        $request = $app->request();
        $input = $params->params();

        // copied from svg.php, and needs to be kept in sync!!!
        $templater = $app->templater();
        $cache = $app->cache();
        $c = $app->container();

        $rendererClass = $app->extendClass(\SV\SvgTemplate\svgRenderer::class);
        $writerClass = $app->extendClass(\SV\SvgTemplate\svgWriter::class);

        /** @var \SV\SvgTemplate\svgRenderer $renderer */
        $renderer = new $rendererClass($app, $templater, $cache);

        /** @var svgWriter $writer */
        $writer = new $writerClass($app, $renderer);
        $writer->setValidator($c['css.validator']);

        $showDebugOutput = (\XF::$debugMode && $request->get('_debug'));

        if (!$showDebugOutput && $writer->canSend304($request))
        {
            $writer->get304Response()->send($request);
        }
        else
        {
            $svg = $input['svg'] ? [$input['svg']] : [];
            $response = $writer->run($svg, $input['s'], $input['l'], $input['k'], $input['d']);
            if ($showDebugOutput)
            {
                $response->contentType('text/html', 'utf-8');
                $response->body($app->debugger()->getDebugPageHtml($app));
            }
            $response->send($request);
        }

        // not very nice, but lets the above svg.php code remain unchanged, and we don't need to support other bits
        exit();
    }
}