<?php

namespace SV\SvgTemplate\Pub\Controller;

use SV\SvgTemplate\Repository\Svg as SvgRepository;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Pub\Controller\AbstractController;

class SvgRenderer extends AbstractController
{
    public function actionIndex(ParameterBag $params): AbstractReply
    {
        $input = $params->params();

        // copied from svg.php, and needs to be kept in sync!!!
        $svSvgRepo = SvgRepository::get();
        $svSvgRepo->renderSvgResponse($input);

        // not very nice, but lets the above svg.php code remain unchanged, and we don't need to support other bits
        exit();
    }
}