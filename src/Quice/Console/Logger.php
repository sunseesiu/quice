<?php
/**
 * Licensed to the Apache Software Foundation (ASF) under one or more
 * contributor license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright ownership.
 * The ASF licenses this file to You under the Apache License, Version 2.0
 * (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at
 *
 *	   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @package log4php
 */

namespace Quice\Console;

class Logger
{
    private $logs = array();
    private $startTime = 0;
    private $processTime = 0;

    /**
     * @var mixed The application supplied message of logging event.
     */
    private $message;

    /**
     * The name of thread in which this logging event was generated.
     * log4php saves here the process id via {@link PHP_MANUAL#getmypid getmypid()}
     * @var mixed
     */
    private $threadName = null;

    /**
    * @var LoggerLocationInfo Location information for the caller.
    */
    private $locationInfo = null;

    const LEVEL_OFF = 2147483647;
    const LEVEL_FATAL = 50000;
    const LEVEL_ERROR = 40000;
    const LEVEL_WARN = 30000;
    const LEVEL_INFO = 20000;
    const LEVEL_DEBUG = 10000;
    const LEVEL_ALL = -2147483647;

    public function __construct()
    {
        $this->startTime = microtime(true);
        $this->processTime = microtime(true);
    }

    /**
     * Return current Unix timestamp with microseconds.
     *
     * @return string
     */
    public function getStartTime()
    {
        return $this->startTime;
    }

    /**
     * Return process time with microseconds.
     *
     * @return string
     */
    public function getProcessTime()
    {
        $time = microtime(true) - $this->processTime;
        $this->processTime = microtime(true);
        return $time;
    }

    public function debug($message, $caller = null)
    {
        $this->logLevel($message, self::LEVEL_DEBUG, $caller);
    }

    public function info($message, $caller = null)
    {
        $this->logLevel($message, self::LEVEL_INFO, $caller);
    }

    public function warn($message, $caller = null)
    {
        $this->logLevel($message, self::LEVEL_WARN, $caller);
    }

    public function error($message, $caller = null)
    {
        $this->logLevel($message, self::LEVEL_ERROR, $caller);
    }

    public function fatal($message, $caller = null)
    {
        $this->logLevel($message, self::LEVEL_FATAL, $caller);
    }

    public function logLevel($message, $level, $caller)
    {
        $locationInfo = $this->getLocationInformation();
        $event = $locationInfo;
        $event['time'] = time();
        $event['message'] = is_string($message) ? $message : var_export($message, true);
        $event['level'] = $level;
        $event['thread'] = $this->getThreadName();

        if ($this->startTime !== 0) {
            $event['escape'] = $this->getProcessTime();
        } else {
            $event['escape'] = 0;
        }

        $this->logs[] = $event;
        $this->getStartTime();
    }

    /**
     * Set the location information for this logging event. The collected
     * information is cached for future use.
     *
     * <p>This method uses {@link PHP_MANUAL#debug_backtrace debug_backtrace()} function (if exists)
     * to collect informations about caller.</p>
     * <p>It only recognize informations generated by {@link Logger} and its subclasses.</p>
     * @return LoggerLocationInfo
     */
    public function getLocationInformation()
    {
        $locationInfo = array();
        $locationInfo['line'] = '';
        $locationInfo['file'] = '';

        if(function_exists('debug_backtrace')) {
            $trace = debug_backtrace();
            $prevHop = null;
            // make a downsearch to identify the caller
            $hop = array_pop($trace);
            while($hop !== null) {
                if(isset($hop['class'])) {
                    // we are sometimes in functions = no class available: avoid php warning here
                    $className = strtolower($hop['class']);
                    if(!empty($className) and ($className == 'maxim_system_runtime_logger')) {
                        $locationInfo['line'] = $hop['line'];
                        $locationInfo['file'] = $hop['file'];
                        break;
                    }
                }
                $prevHop = $hop;
                $hop = array_pop($trace);
            }
            $locationInfo['class'] = isset($prevHop['class']) ? $prevHop['class'] : 'main';
            if(isset($prevHop['function']) and
                $prevHop['function'] !== 'include' and
                $prevHop['function'] !== 'include_once' and
                $prevHop['function'] !== 'require' and
                $prevHop['function'] !== 'require_once') {

                $locationInfo['function'] = $prevHop['function'];
            } else {
                $locationInfo['function'] = 'main';
            }
        } else {
            $locationInfo['line'] = 0;
            $locationInfo['file'] = null;
            $locationInfo['class'] = 'main';
            $locationInfo['function'] = 'main';
        }

        return $locationInfo;
    }

    /**
     * @return mixed
     */
    public function getThreadName()
    {
        if ($this->threadName === null) {
            $this->threadName = (string)getmypid();
        }
        return $this->threadName;
    }

    public function clear()
    {
        $this->logs = null;
    }

    public function toArray()
    {
        return $this->logs;
    }

    /**
     * <pre>
     * 2009-09-09 00:27:35,787 [INFO] root: Hello World! (at src/examples/php/layout_pattern.php line 6)
     * 2009-09-09 00:27:35,787 [DEBUG] root: Second line (at src/examples/php/layout_pattern.php line 7)
     * </pre>
     */
    public function toString()
    {

    }

    public function getLevelString($level)
    {
        $levelStrings = array(
            self::LEVEL_OFF => 'OFF',
            self::LEVEL_FATAL => 'FATAL',
            self::LEVEL_ERROR => 'ERROR',
            self::LEVEL_WARN => 'WARN',
            self::LEVEL_INFO => 'INFO',
            self::LEVEL_DEBUG => 'DEBUG',
            self::LEVEL_ALL => 'ALL',
        );
        return isset($levelStrings[$level]) ? $levelStrings[$level] : 'ALL';
    }

    public function toHtml()
    {
        $sbuf = '';
        $sbuf .= "<table cellspacing=\"0\" cellpadding=\"4\" border=\"0\" bordercolor=\"#224466\" width=\"100%\" class=\"data\" style=\"margin:0\">";
        $sbuf .= "<thead><tr>";
        $sbuf .= "<th class=\"yellow\">Level</th>";
        $sbuf .= "<th class=\"blue\">Message</th>";
        $sbuf .= "<th class=\"green\">Trace</th>";
        $sbuf .= "<th class=\"orange\">Escape</th>";
        $sbuf .= "</tr></thead>\n<tbody>";

        $cycle = "odd";
        $totalTime = 0;
        $levelColor = array(
            'DEBUG' => 'blue', 'INFO' => 'green', 'WARN' => 'orange',
            'ERROR' => 'red', 'FATAL' => 'purple', 'ALL' => 'grey'
        );

        foreach ($this->logs as $log) {
            $file = ''; $sep = '';
            foreach (explode('/', trim(htmlentities($log['file']), '/')) as $name) {
                $sep .= '/' . $name;
                if (strlen($sep) > 60) {
                    $sep = '';
                    $file .= '<br />';
                }
                $file .= '/' . $name;
            }

            $sbuf .= "<tr class=\"{$cycle}\">";
            $level = $this->getLevelString($log['level']);
            $sbuf .= '<td><span style="color:'.$levelColor[$level].';">'.$level.'</span></td>';
            $sbuf .= "<td>{$log['message']}</td>";
            $sbuf .= "<td><span style=\"color:green\">".$file.":{$log['line']}</span>";
            $sbuf .= "<br />".$log['class'].':'.$log['function']."</td>";
            $sbuf .= "<td style=\"color:orange\">".sprintf("%0.6f", $log['escape']) . "</td>";
            $sbuf .= "</tr>";
            $cycle = $cycle == 'odd' ? 'even' : 'odd';
            // $totalTime += $log['escape'];
        }

        $totalTime = microtime(true) - $this->startTime;
        $sbuf .= "<tr><td colspan=\"5\" style=\"color:grey;\">Total time: " . sprintf("%0.6f", $totalTime) . "</td></tr></tbody>";
        $sbuf .= "</table>";

        return $sbuf;
    }
}
