<?php

namespace PM\PrettyLog\Parser;

use Psr\Log\LogLevel;

/**
 * TODO: line splits (http://www.eyrie.org/~eagle/software/filter-syslog/docs.html)
 */
class SyslogParser extends AbstractLogParser
{
    /**
     * @var string
     */
    protected $regex;

    public function __construct()
    {
        // example:
        // "Mar 20 18:40:01 mongodb1 /USR/SBIN/CRON[8460]: (root) CMD (/usr/local/sbin/sysstat.py >> /var/log/sysstat.log)"
        $rxDate = '[A-Z][a-z]{2} +[0-9]{1,2} [0-9]+:[0-9]{2}:[0-9]{2}';
        $rxHost = '[-_.a-zA-Z0-9]+';
        $rxDaemon = '[-_.\\/a-zA-Z0-9]+';
        $this->regex = "/(?P<date>$rxDate)\\s+(?P<host>$rxHost)\\s+(?P<daemon>$rxDaemon)(\\[(?P<pid>\\d+)\\])?:\\s+(?P<message>.*)/";
    }

    protected  function doParseLine($line)
    {
        if (!preg_match($this->regex, $line, $parts)) {
            return null;
        }

        $dateTime = $parts['date'];
        $this->timestamp = strtotime($dateTime);
        if ($this->timestamp === false) {
            return null;
        }

        // guess level by search words
        $message = $parts['message'];
        if (stripos($message, 'error') !== false) {
            $this->level = LogLevel::ERROR;
        } elseif (stripos($message, 'warning') !== false) {
            $this->level = LogLevel::WARNING;
        } else {
            $this->level = LogLevel::INFO;
        }

        return array(
            'host'    => $parts['host'],
            'daemon'  => $parts['daemon'],
            'pid'     => isset($parts['pid']) ? $parts['pid'] : null,
            'message' => $message,
        );
    }

    /**
     * @return string
     */
    public function colorizeLine()
    {
        /** @var $host     string */
        /** @var $daemon   string */
        /** @var $pid      string */
        /** @var $message  string */
        extract($this->recognizedParts);

        $pid = empty($pid) ? '' : "[$pid]";

        $levelStyle = self::$LEVEL_STYLES[$this->level];

        return "$host <$levelStyle>$daemon</$levelStyle>$pid <message>$message</message>";
    }
}
