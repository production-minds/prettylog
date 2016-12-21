<?php

namespace PM\PrettyLog\Parser;

use PM\PrettyLog\JSONFormatter;
use Psr\Log\LogLevel;

abstract class MonologColorizer extends AbstractLogParser
{
    public static $LEVELS = array(
        'DEBUG'     => LogLevel::DEBUG,
        'INFO'      => LogLevel::INFO,
        'NOTICE'    => LogLevel::NOTICE,
        'WARNING'   => LogLevel::WARNING,
        'ERROR'     => LogLevel::ERROR,
        'CRITICAL'  => LogLevel::CRITICAL,
        'ALERT'     => LogLevel::ALERT,
        'EMERGENCY' => LogLevel::EMERGENCY,
    );

    /**
     * @var JSONFormatter
     */
    protected $jsonFormatter;

    public function __construct(JSONFormatter $jsonFormatter)
    {
        $this->jsonFormatter = $jsonFormatter;
    }

    /**
     * @return string
     */
    public function colorizeLine()
    {
        /** @var $channel   string */
        /** @var $level     string */
        /** @var $message   string */
        /** @var $context   array */
        /** @var $extra     array */
        extract($this->recognizedParts);

        $levelStyle = self::$LEVEL_STYLES[$this->level];

        $context = empty($context) ? '' : ltrim($this->jsonFormatter->formatJSON($context));
        $extra = empty($extra) ? '' : ltrim($this->jsonFormatter->formatJSON($extra));

        return "<$levelStyle>$channel.$level</$levelStyle> <message>$message</message> $context $extra";
    }
}

