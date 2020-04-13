<?php

/**
 * @param $keyword
 * @return bool
 */
function process_running($keyword)
{
    $command = "ps axu | grep '{$keyword}' | grep -v grep";

    exec($command, $output);

    return empty($output) || count($output) < 2 ? false : true;
}

/**
 * Kill somebody
 *
 * @param $pid
 * @param int $signo
 * @return int
 */
function kill_process($pid, $signo = SIGTERM)
{
    return swoole_process::kill($pid, $signo);
}