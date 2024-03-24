<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SvgTemplate\XF\Template;

use XF\Language;
use XF\Template\Compiler\Ast;

class Compiler extends \XF\Template\Compiler
{
    public $previousCode = null;

    public function compileAst(Ast $ast, ?Language $language = null)
    {
        $code = parent::compileAst($ast, $language);

        $this->previousCode = $code;

        return $code;
    }
}