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
	'k' => 'str'
]);

$templater = $app->templater();
$cache = $app->cache();
$c = $app->container();
/** @var \SV\SvgTemplate\svgRenderer $renderer */
$rendererClass = $app->extendClass('SV\SvgTemplate\svgRenderer');
$renderer = new $rendererClass($app, $templater, $cache);
/** @var \SV\SvgTemplate\svgWriter $writer */
$class = $app->extendClass('SV\SvgTemplate\svgWriter');
$writer = new $class($app, $renderer);
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