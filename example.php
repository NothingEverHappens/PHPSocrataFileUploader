<?php
/**
 * Turn on errors
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

include_once "Socrata_model.php";


$temp_dir = sprintf('.%sdata%1$s', DIRECTORY_SEPARATOR);
$log_file = "{$temp_dir}curl_log.txt";
$root_url = "https://data.taxpayer.net/api";
$data_set_path = realpath("{$temp_dir}insert_data_set.csv");
$append_data_set_path = realpath("{$temp_dir}append_data_set.csv");
$data_set_name = "Test data set";



$options = Array(
    "temp_dir" => $temp_dir,
    "root_url" => $root_url,
    /**
     * Edit this before uploading
     */
    "app_token" => "token",
    "user_name" => "username",
    "password" => "pass",

    /**
     * We set up place column as location, which will
     */
    "blueprint" => Array("place"=>"Location"),
    "translation" => Array(),
    "log_file" => $log_file
);

if( $options["app_token"] === "token"){
    throw new Exception("Please set up your credentials and token");
}


$socrata = new Socrata_model( $options );
/**
 * Let's upload a file
 */
$data = $socrata->post_file( $data_set_path, $data_set_name );
echo "<hr>";
echo "File was uploaded";
echo "<hr>";
var_dump( $data );
flush();
if( empty( $data["id"] ) || !preg_match( "/[\w\d]{4}\-[\w\d]{4}/",$data["id"]) ){
    throw new Exception("The id returned by socrata is in a wrong format");
}

$data_set_id = $data["id"];
/**
 * Let's append a file to the data set
 */
$data = $socrata->append_file( $data_set_id, $append_data_set_path);
echo "<hr>";
echo "File was appended";
echo "<hr>";
var_dump( $data );

/*
 * Socrata support says there is no way to set up unique row ID via API, so we this
 * call will result in appending data set.
 */
$working_copy_id = $socrata->create_working_copy( $data[ "socrata_id" ] );
$result = $socrata->upsert_file( $working_copy_id, $data_set_path );
$socrata->publish_working_copy( $working_copy_id );

echo "<hr>";
echo "File was upserted";
echo "<hr>";
var_dump( $data );

/**
 * now let's see what we have
 */
$data = $socrata->get_view( $data_set_id );
echo "<hr>";
echo "This is our new data set";
echo "<hr>";
var_dump( $data );
