<?php
/**
 * @noinspection PhpFullyQualifiedNameUsageInspection
 */

use SV\SvgTemplate\Repository\Svg as SvgRepository;

$dir = __DIR__;
/** @noinspection PhpIncludeInspection */
require ($dir . '/src/XF.php');

XF::start($dir);
$app = XF::setupApp(\XF\Pub\App::class, [
	'preLoad' => ['masterStyleModifiedDate', 'smilieSprites']
]);

$request = $app->request();
$input = $request->filter([
	'svg' => 'str',
	's' => 'uint',
	'l' => 'uint',
	'k' => 'str',
    'd' => 'uint',
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

$svSvgRepo = SvgRepository::get();
$svSvgRepo->renderSvgResponse($input);
