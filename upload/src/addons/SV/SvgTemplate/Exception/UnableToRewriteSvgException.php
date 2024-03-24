<?php

namespace SV\SvgTemplate\Exception;

use Throwable;

/**
 * @since 2.3.0 rc1
 */
class UnableToRewriteSvgException extends \InvalidArgumentException
{
    /**
     * @var string
     */
    protected $templateName;

    public function __construct(string $templateName, string $message = 'Unable to rewrite SVG', $code = 0, ?Throwable $previous = null)
    {
        $this->templateName = $templateName;

        parent::__construct($message, $code, $previous);
    }

    public function getTemplateName(): string
    {
        return $this->templateName;
    }
}