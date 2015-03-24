<?php

namespace PM\PrettyLog\Parser;

use PM\PrettyLog\JSONFormatter;
use Psr\Log\LogLevel;

class MonologLineParser extends AbstractLogParser
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

    protected  function doParseLine($line)
    {
        $extra = $this->popJSON($line);
        if ($extra === null) {
            return null;
        }

        $context = $this->popJSON($line);
        if ($context === null) {
            return null;
        }

        // from \Monolog\Formatter\LineFormatter:
        // "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n"
        if (!preg_match('/\\[(?P<date>.*)\\]\\s+(?P<channel>\\w+)\\.(?P<level>\\w+):\\s+(?P<message>.*)/', $line, $parts)) {
            return null;
        }
        //var_dump($parts); exit;

        // filter lines by error level
        $level = strtoupper($parts['level']);
        if (!isset(self::$LEVELS[$level])) {
            return null;
        }
        $this->level = self::$LEVELS[$level];

        // TODO: time zone?
        $dateTime = $parts['date'];
        $this->timestamp = strtotime($dateTime);
        if ($this->timestamp === false) {
            return null;
        }

        return array(
            'level'   => $level,
            'channel' => $parts['channel'],
            'message' => $parts['message'],
            'context' => $context,
            'extra'   => $extra,
        );
    }

    private function popJSON(&$line)
    {
        // empty JSON array?
        if (substr($line, -2) === '[]') {
            $line = rtrim(substr($line, 0, -2));
            return array();
        } elseif (substr($line, -1) === '}') {
            $search = strlen($line);
            while ($search > 0) {
                $start = strrpos($line, ' {', -(strlen($line) - $search));
                if ($start === false) {
                    break;
                }
                $decoded = json_decode(substr($line, $start + 1), true);
                if ($decoded !== null) {
                    $line = rtrim(substr($line, 0, $start));
                    return $decoded;
                }
                $search = $start - 1;
            }
        }
        return null;
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
        $messageStyle = 'options=bold';

        $context = empty($context) ? '' : ltrim($this->jsonFormatter->formatJSON($context));
        $extra = empty($extra) ? '' : ltrim($this->jsonFormatter->formatJSON($extra));

        return "<$levelStyle>$channel.$level</$levelStyle> <$messageStyle>$message</$messageStyle> $context $extra";
    }
}

