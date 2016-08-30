<?php

namespace PM\PrettyLog\Parser;


class MonologJSONParser extends MonologColorizer
{

    /**
     * @param string $line
     *
     * @return array|null
     */
    protected function doParseLine($line)
    {
        // CONSIDER: Logstash messages also contain keys "host", "type", "@version". Do we need to deal with those?

        if (substr($line, 0, 1) !== '{' && substr($line, -1) !== '}') {
            return null;
        }
        $fields = json_decode($line, true);
        if (empty($fields) || !is_array($fields)) {
            return null;
        }

        if (!isset($fields['@timestamp']) || !isset($fields['message'])) {
            return null;
        }
        $this->timestamp = strtotime($fields['@timestamp']);

        $level = strtoupper($fields['level']);
        if (!isset(self::$LEVELS[$level])) {
            return null;
        }
        $this->level = self::$LEVELS[$level];

        $extra = array();
        foreach ($fields as $key => $value) {
            if (strpos($key, 'extra_') === 0) {
                $extra[substr($key, 6)] = $value;
            }
        }

        return array(
            'level'   => $fields['level'],
            'channel' => $fields['channel'],
            'message' => $fields['message'],
            'context' => isset($fields['context']) ? $fields['context'] : array(),
            'extra'   => $extra,
        );
    }

}