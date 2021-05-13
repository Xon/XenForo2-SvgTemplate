<?php

namespace SV\SvgTemplate\XF\Template;


use XF\Template\Compiler\Ast;

class Compiler extends \XF\Template\Compiler
{
    public $previousCode = null;

    public function compileAst(Ast $ast, \XF\Language $language = null)
    {
        $code = parent::compileAst($ast, $language);

        $this->previousCode = $code;

        return $code;
    }
}