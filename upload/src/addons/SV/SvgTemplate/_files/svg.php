<?php

$dir = __DIR__;
/** @noinspection PhpIncludeInspection */
require ($dir . '/src/XF.php');

XF::start($dir);
$app = XF::setupApp('XF\Pub\App', [
	'preLoad' => ['masterStyleModifiedDate', 'smilieSprites']
]);

$request = $app->request();
$input = $request->filter([
	'svg' => 'str',
	's' => 'uint',
	'l' => 'uint',
	'k' => 'str',
    'style' => 'uint',
    'langauge' => 'uint',
]);

//XF1 arguments compatibility
if (!$input['s'] && $input['style'])
{
    $input['s'] = $input['style'];
}
if (!$input['l'] && $input['langauge'])
{
    $input['l'] = $input['langauge'];
}

$templater = $app->templater();
$cache = $app->cache();
$c = $app->container();

$rendererClass = $app->extendClass('SV\SvgTemplate\svgRenderer');
$writerClass = $app->extendClass('SV\SvgTemplate\svgWriter');

/** @var \SV\SvgTemplate\svgRenderer $renderer */
$renderer = new $rendererClass($app, $templater, $cache);

/** @var \SV\SvgTemplate\svgWriter $writer */
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
    $response = $writer->run($svg, $input['s'], $input['l'], $input['k']);
    if ($showDebugOutput)
    {
        $response->contentType('text/html', 'utf-8');
        $response->body($app->debugger()->getDebugPageHtml($app));
    }
    $response->send($request);
}