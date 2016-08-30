<?php

namespace PM\PrettyLog\Parser;

class MonologLineParser extends MonologColorizer
{

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
}

