<?php

namespace PM\PrettyLog\Parser;

use Psr\Log\LogLevel;

class PhpFpmLogParser extends AbstractLogParser {

    public static $LEVELS = array(
        'NOTICE'          => LogLevel::NOTICE,
        'WARNING'         => LogLevel::WARNING,
        'ERROR'           => LogLevel::ERROR,
        'PARSE'           => LogLevel::CRITICAL,
        'CORE_WARNING'    => LogLevel::ALERT,
        'CORE_ERROR'      => LogLevel::EMERGENCY,
        'COMPILE_ERROR'   => LogLevel::EMERGENCY,
        'COMPILE_WARNING' => LogLevel::ALERT,
        'USER_NOTICE'     => LogLevel::NOTICE,
        'USER_WARNING'    => LogLevel::WARNING,
        'USER_ERROR'      => LogLevel::ERROR,
    );

    protected function doParseLine($line)
    {
        // [11-Aug-2014 03:13:38] NOTICE: configuration file /etc/php5/fpm/php-fpm.conf test is successful
        if (!preg_match('/^\[(.*?)\]\s+(\w+):\s+(.*)/', $line, $parts)) {
            return null;
        }
        //var_dump($parts); exit;

        // TODO: time zone?
        $this->timestamp = strtotime($parts[1]);
        if ($this->timestamp === false) {
            return null;
        }

        // filter lines by error level
        $level = strtoupper($parts[2]);
        if (isset(self::$LEVELS[$level])) {
            $this->level = self::$LEVELS[$level];
        } else {
            $this->level = 'INFO';
        }

        return array(
            'level'     => $level,
            'message'   => $parts[3],
        );
    }

    /**
     * @return string
     */
    public function colorizeLine()
    {
        /** @var $level     string */
        /** @var $message   string */
        extract($this->recognizedParts);

        $levelStyle = $this->level;

        return "<$levelStyle>$level</$levelStyle> <message>$message</message>";
    }
}

