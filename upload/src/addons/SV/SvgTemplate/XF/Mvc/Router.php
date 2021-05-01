<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SvgTemplate\XF\Mvc;

use XF\Http\Request;
use XF\Mvc\RouteMatch;

/**
 * Extends \XF\Mvc\Router
 */
class Router extends XFCP_Router
{
    protected $friendlySvgUrl = 'data/svg/';
    protected $rawSvgUrl = 'svg.php';
    protected $skipSvgTemplateRouterIntegration = true;

    public function __construct($linkFormatter = null, array $routes = [])
    {
        parent::__construct($linkFormatter, $routes);
        $this->skipSvgTemplateRouterIntegration = empty(\XF::options()->svSvgTemplateRouterIntegration);
    }

    public function routeToController($path, Request $request = null)
    {
        // if $request is null, we're probably just testing a link
        if ($request === null || $this->skipSvgTemplateRouterIntegration)
        {
            return parent::routeToController($path, $request);
        }

        // strncasecmp should be very fast
        if (strncasecmp($path, $this->friendlySvgUrl, strlen($this->friendlySvgUrl)) === 0)
        {
            /** @noinspection RegExpRedundantEscape */
            if (\preg_match('#^data/svg/(?<s>[^/]+)/(?<l>[^/]+)/(?<d>[^/]+)/(?<svg>[^\.]+\.(?:svg|png))$#i', $path, $matches))
            {
                $input = $request->filter([
                    'k' => 'str'
                ]);

                $match = new RouteMatch();
                $match->setController('SV\SvgTemplate:SvgRenderer');
                $match->setAction('index');
                $match->setParams($matches + $input, false);

                return $match;
            }
        }
        else if (strncasecmp($path, $this->rawSvgUrl, strlen($this->rawSvgUrl)) === 0)
        {
            $input = $request->filter([
                'svg' => 'str',
                's' => 'uint',
                'l' => 'uint',
                'k' => 'str',
                // XF1 arguments
                'style' => 'uint',
                'langauge' => 'uint',
                'd' => 'uint',
            ]);
            //XF1 arguments compatibility
            if (!$input['s'] && $input['style'])
            {
                $input['s'] = $input['style'];
                unset($input['style']);
            }
            if (!$input['l'] && $input['langauge'])
            {
                $input['l'] = $input['langauge'];
                unset($input['langauge']);
            }
            $match = new RouteMatch();
            $match->setController('SV\SvgTemplate:SvgRenderer');
            $match->setAction('index');
            $match->setParams($input, false);

            return $match;
        }

        return parent::routeToController($path, $request);
    }
}