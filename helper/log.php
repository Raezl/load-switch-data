<?php

class Log
{

    //debug message
    public static function debug($message)
    {
        $data = 'DEBUG: ' . date('Y-m-d H:i:s') . ' ' . $message;
        Log::write($data);
    }

    //error message
    public static function error($message)
    {
        $data = 'ERROR: ' . date('Y-m-d H:i:s') . ' ' . $message;
        Log::write($data);
    }

    //write to log file
    private static function write($message)
    {

        if (!file_exists('logs')) {
            // create directory/folder uploads.
            mkdir('logs', 0777, true);
        }

        $log_file = './logs/log_' . date('Y-m-d') . '.log';
        file_put_contents($log_file, $message . "\n", FILE_APPEND);
    }

}