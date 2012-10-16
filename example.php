<?php
/**
 * Turn on errors
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

include_once "Socrata_model.php";



$temp_dir = sprintf('.%scache%1$s', DIRECTORY_SEPARATOR);
$log_file = "{$temp_dir}curl_log.txt";
$root_url = "https://data.taxpayer.net/api";
$data_set_path = realpath("{$temp_dir}test-merge-data-e8bf92fd9f63adac2aa34c6967bf08d8-dump-7-1.csv");
$data_set_name = "Test data set";



$options = Array(
    "temp_dir" => $temp_dir,
    "root_url" => $root_url,
    /**
     * those are active, please don't share witrh other people
     */
    "app_token" => "token",
    "user_name" => "user name",
    "password" => "password",
    "blueprint" => Array(),
    "translation" => Array(),
    "log_file" => $log_file
);


$socrata = new Socrata_model( $options );
$data = $socrata->post_file( $data_set_path, $data_set_name );
echo "<hr>";
echo "File was uploaded";
echo "<hr>";
$data["id"];
flush();
if( empty( $data["id"] ) || !preg_match( "/[\w\d]{4}\-[\w\d]{4}/",$data["id"]) ){
    throw new Exception("The id returned by socrata is in a wrong format");
}

$data = $socrata->append_file( $data["id"], $data_set_path);
echo "<hr>";
echo "File was appended";
echo "<hr>";
var_dump( $data );



