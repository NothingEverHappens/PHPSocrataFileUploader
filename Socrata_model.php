<?php
/**
 * @author Kirill Cherkashin <Kirill@kcherkashin.org>
 * @license MIT
 *
 * This class allows you to post data sets via with Socrata Publisher API.
 *
 */

class Socrata_model {

    /**
     * Default options
     * @var array
     */

    private $_options = Array(
        "imports" => "/imports2.json",
        "root_url" => "https://data.taxpayer.net/api",
        "blueprint" => Array(),
        "translation" => Array(),
        "chunk_size" => 50000000,
        "lines_in_header" => 1
    );


    /**
     * Constructor
     * See GitHub for the documentation.
     *
     * @param array $options
     */

    public function __construct( $options = Array() ) {
        if ( !extension_loaded( 'curl' ) ) {
            throw new Exception( "curl is not installed" );
        }
        $this->set_options( $options );
    }

    /**
     * Set options
     *
     * @param array $options
     * @throws InvalidArgumentException
     */

    public function set_options( $options = Array() ) {
        if ( !is_array( $options ) ) {
            throw new InvalidArgumentException( "Options must be an array" );
        }

        $this->_options = $options + $this->_options;
    }

    /**
     * Upsert
     *
     * @param string $view_id Socrata view ID xxxx-xxxx
     * @param string $file_path CSV file
     *
     */

    public function upsert_file( $view_id, $file_path, $skip_lines = 1 ) {

        $view = $this->get_view( $view_id );
        if ( empty( $view[ "columns" ] ) || !is_array( $view[ "columns" ] ) ) {
            throw new Exception( "Socrata API didn't return list of columns for the view" );
        }
        /**
         * Get list of columns based on information return by Socrata
         */
        $columns = Array();
        foreach ( $view[ "columns" ] as $column ) {
            $columns[ ] = $column[ "name" ];
        }

        $file = file( $file_path );

        /**
         * Turn each line into associative array using keys as column names.
         */
        foreach ( $file as $key => $line ) {
            $line = str_getcsv( $line );
            $file[ $key ] = array_combine( array_slice( $columns, 0, count( $line ) ), $line );
        }

        /**
         * Remove header rows
         */
        array_splice( $file, 0, $skip_lines );
        $data = json_encode( $file );

        return $this->post( "/id/$view_id.json", Array(),
            Array(
                CURLOPT_HTTPHEADER => Array( "Content-type: application/json" ),
                CURLOPT_POSTFIELDS => $data
            )
        );

    }

    /**
     * Get CURL
     *
     * @return resource CURL instance.
     * @throws Exception If CURL is not installed.
     */
    public function get_curl() {
        if ( !extension_loaded( 'curl' ) ) {
            throw new Exception( "curl is not installed" );
        }

        if ( isset( $this->_options[ "curl" ] ) && is_object( $this->_options[ "curl" ] ) ) {
            return $this->_options[ "curl" ];
        }

        return curl_init();
    }


    /**
     * Post query
     *
     * @param string $path  relative path to the API end point.
     * @param array $params Get parameters for the request
     * @param array $curl_options Extra curl parameters to be merged with parameters
     * used in this function.
     * @return string JSON string returned by API.
     * @throws Exception If applications token or user credentials are not set up
     */
    public function post( $path, $params = Array(), $curl_options = Array() ) {


        if ( empty( $this->_options[ "app_token" ] ) ) {
            throw new Exception( "Application token is not set" );
        }
        if ( empty( $this->_options[ "user_name" ] ) || empty( $this->_options[ "password" ] ) ) {
            throw new Exception( "User name and password required to make post requests" );
        }


        if ( empty( $curl_options[ CURLOPT_HTTPHEADER ] ) ) {
            $curl_options[ CURLOPT_HTTPHEADER ] = Array();
        }

        $curl_options[ CURLOPT_HTTPHEADER ] = array_merge( $curl_options[ CURLOPT_HTTPHEADER ], Array(
            "X-App-Token: " . $this->_options[ "app_token" ]
        ) );


        $curl_options += Array(
            CURLOPT_CUSTOMREQUEST => "POST"
        );

        $built_url = $this->build_url( $path, $params );

        $result = $this->query( $built_url, $curl_options );


        return $result;

    }

    /**
     * Get view ID
     *
     * @param $view_id
     * @return string
     */

    public function get_view( $view_id ) {
        return $this->get( "/views/$view_id.json" );
    }

    /**
     * Get
     *
     * @param string $path  relative path to the API end point.
     * @param array $params Get parameters for the request
     * @param array $curl_options Extra curl parameters to be merged with parameters defined in this function.
     * @return  string json returned by API.
     */

    public function get( $path, $params = Array(), $curl_options = Array() ) {


        $header = empty( $curl_options[ CURLOPT_HTTPHEADER ] ) ? Array() : $curl_options[ CURLOPT_HTTPHEADER ];
        $curl_options += Array(
            CURLOPT_HTTPHEADER => Array(
                'Accept: application/json',
                'Content-type: application/json',
                "X-App-Token: " . $this->_options[ "app_token" ]
            ) + $header
        );

        return $this->decode( $this->query( $this->build_url( $path, $params ), $curl_options ) );
    }

    /**
     * Get credentials options
     *
     * @return array In case username and password are set,  according CURL options are returned in order
     * to have  CURL authentificate with given credentials.
     */

    private function get_credentials_options() {
        if ( !empty( $this->_options[ "user_name" ] ) && !empty( $this->_options[ "password" ] ) ) {
            return Array( CURLOPT_USERPWD => $this->_options[ "user_name" ] . ":" . $this->_options[ "password" ] );
        }
        return Array();
    }

    /**
     * Get Progress Function Options
     *
     * @return array In case Progress function is set,  according CURL options are returned in order
     * to have CURL utilize the it.
     * @throws Exception
     */

    private function get_progress_function_options() {
        if ( empty( $this->_options[ "progress_function" ] ) ) {
            return Array();
        }
        if ( !is_callable( $this->_options[ "progress_function" ] ) ) {
            throw new Exception( "Progress function is not callable: " . $this->_options[ "log_file" ] );
        }

        return Array(
            CURLOPT_PROGRESSFUNCTION => $this->_options[ "progress_function" ],
            CURLOPT_NOPROGRESS => false
        );
    }


    /**
     * Get log options
     *
     * @return array In case "log_file" option is set,  according CURL options are returned in order
     * to have CURL dump the request information into the file provided..
     * @throws Exception if the file provided doesn't exist
     */

    private function get_log_options() {
        if ( empty( $this->_options[ "log_file" ] ) ) {
            return Array();
        }
        $log_file = fopen( $this->_options[ "log_file" ], 'w' );

        if ( $log_file === false ) {
            throw new Exception( "Can't open log file " . $this->_options[ "log_file" ] );
        }

        return Array(
            CURLOPT_VERBOSE => TRUE,
            CURLOPT_STDERR => $log_file
        );
    }


    /**
     * Build Url
     *
     * @param string $relative_path relative path to the API end point.
     * @param array $params Get parameters for the request
     * @return string full path to the API end point.
     */
    public function build_url( $relative_path, $params = Array() ) {
        return $this->_options[ "root_url" ] . $relative_path . ( empty( $params ) ? "" : "?" . http_build_query( $params ) );
    }

    /**
     * Query
     *
     * Generic CURL query
     *
     * @param string $url full path to API end point.
     * @param array $curl_options Extra curl parameters to be merged with parameters defined in this function.
     * @return string JSON string returned by API.
     * @throws Exception If gets an error from the API.
     */
    private function query( $url, $curl_options = Array() ) {

        //$this->log( "Url:" . $url );
        $curl_options += Array(
            CURLOPT_SSL_VERIFYPEER => FALSE,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FAILONERROR => true,
            CURLOPT_HEADER => 0,
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true
        );

        $curl_options += $this->get_progress_function_options();
        $curl_options += $this->get_log_options();
        $curl_options += $this->get_credentials_options();


        $curl = $this->get_curl();

        curl_setopt_array( $curl, $curl_options );

        /*
         *   Some timeouts for debug
         *   set_time_limit( 5 );
         *   $max_exe_time = 2000; // time in milliseconds
         *   curl_setopt($curl, CURLOPT_TIMEOUT_MS, $max_exe_time);
         *
         * */


        $result = curl_exec( $curl );

        if ( $err = curl_errno( $curl ) ) {
            throw new Exception("curl_error", curl_error( $curl ), curl_errno( $curl ) );
        }


        return $result;

    }

    /**
     * Temp File Name
     *
     * @param $file_name
     * @param $extension
     *
     */
    public function temp_file_name( $file_name, $extension = ".csv" ) {
        if ( empty( $this->_options[ "temp_dir" ] ) ) {
            $this->_options[ "temp_dir" ] = sys_get_temp_dir();
        }
        $this->_options[ "temp_dir" ] = rtrim( $this->_options[ "temp_dir" ], DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;


        if ( !is_writable( $this->_options[ "temp_dir" ] ) ) {
            throw new Exception( "Temporary dir is not writable " . $this->_options[ "temp_dir" ] );
        }
        return $this->_options[ "temp_dir" ] . $file_name . $extension;

    }

    /**
     * @param $data string
     * @return string JSON encoded string
     */
    public function encode( $data ) {
        return json_encode( $data );
    }

    /**
     * Decode
     *
     * @param mixed $data
     * @return mixed parsed object
     */
    public function decode( $data ) {
        if ( is_string( $data ) ) {
            return json_decode( $data, true );
        }

        return $data;
    }

    public function split_csv_by_size( $file, $chunk_size = 1000000 ) {

        $dir = dirname( $file ) . DIRECTORY_SEPARATOR . basename( $file, ".csv" );
        if ( !file_exists( $dir ) ) {
            mkdir( $dir );
        } else {
            return glob( $dir . DIRECTORY_SEPARATOR . "*.csv" );
        }

        $files = Array();

        $stream = fopen( $file, "r" );
        $done = false;
        if ( $stream !== false ) {
            $x = 0;

            do {
                $start_position = ftell( $stream );

                fseek( $stream, $chunk_size, SEEK_CUR );
                fgets( $stream );
                $end_position = ftell( $stream );
                fseek( $stream, $start_position, SEEK_SET );

                $chunk = fread( $stream, $end_position - $start_position );
                $file_name = $dir . DIRECTORY_SEPARATOR . count( $files ) . ".csv";
                $files[ ] = $file_name;
                file_put_contents( $file_name, $chunk );
                if ( strlen( $chunk ) < $chunk_size ) $done = true;
            } while ( !$done );
        }

        return $files;

    }

    /**
     * Split CSV By Size
     *
     * @param string $file_path
     * @param int $chunk_size in bytes.
     * @return array of files generated by the function
     * @throws InvalidArgumentException If file doesn't exist
     * @throws RuntimeException If chunk size is smaller than the biggest line in the file
     */

    /*............... TODO: Test and implement */
    public function not_tested_split_csv_by_size_v2( $file_path, $chunk_size = 1000000 ) {

        if ( !file_exists( $file_path ) ) {
            throw new InvalidArgumentException( "File doesn't exist " . $file_path );
        }

        if ( $chunk_size === 0 || filesize( $file_path ) <= $chunk_size ) {
            return Array( $file_path );
        }

        $files = Array();
        $stream = fopen( $file_path, "r" );

        do {
            $start_position = ftell( $stream );
            $unadjusted_end_position = $end_position = $start_position + $chunk_size;

            /**
             * We take first chunk_size symbols and search for the first occurence of a new line
             * from the end.
             */
            do {
                fseek( $stream, --$end_position );
            } while ( fgetc( $stream ) !== "\n" && $end_position > $start_position );

            $new_chunk_size = $end_position - $start_position;

            /**
             * If there is no new line in the whole chunk, there can be two reasons
             */
            if ( $new_chunk_size === 0 ) {
                /*............... TODO: Test this better */
                $data = fread( $stream, 1024 );


                fseek( $stream, $unadjusted_end_position );


                if ( feof( $stream ) ) {
                    /**
                     * It's the last one, and we want to put it in a separate file.
                     */
                    $new_chunk_size = $unadjusted_end_position;
                } elseif ( empty( $data ) ) {
                    break;
                } else {
                    /**
                     * Or the whole chunk is on one big line, which makes splitting of CSV files impossible.
                     */
                    throw new RuntimeException( "Maximum chunk size is smaller than the longest line in csv file." );
                }
            }
            fseek( $stream, $start_position );
            $chunk = fread( $stream, $new_chunk_size );

            $files[ ] = $this->temp_file_name( count( $files ) );
            file_put_contents( end( $files ), trim( $chunk ) );

        } while ( !feof( $stream ) );


        return $files;
    }


    private function log( $message ) {
        throw new Exception("Not implemented");
    }

    /**
     * Post huge file
     *
     *
     * @param string $file_path
     * @param string $data_set_name This parameter is passed to Socrata
     * @param int $time_limit maximum amount of time to be spent on posting the file.
     * @return string Information returned bu Socrata API after first file is
     * posted.
     */
    public function post_huge_file( $file_path, $data_set_name, $time_limit = 360000 ) {

        /**
         * Split file into parts
         */
        $chunks = $this->split_csv_by_size( $file_path, $this->_options[ "chunk_size" ] );
        $chunks_amount = count( $chunks );


        /**
         * Upload first file
         */
        $first_file = array_shift( $chunks );
        $this->log( "Posting file, 1 of  " . $chunks_amount );
        $data = $this->post_file( $first_file, $data_set_name );

        /**
         * Sometimes Socrata needs some time to process the file
         */
        if ( !empty( $data[ "code" ] ) && $data[ "code" ] == "accepted" && $data[ "ticket" ] ) {
            do {
                $time_limit -= 60;
                sleep( 60 );
                $data = $this->get( "/imports2.json", Array( "ticket" => $data[ "ticket" ] ) );
            } while ( $time_limit > 0 && empty( $data[ "id" ] ) );
        }

        $x = 2;
        foreach ( $chunks as $file_path ) {
            $this->log( "Posting file, " . $x++ . " of  " . $chunks_amount );
            $this->append_file( $data[ "id" ], $file_path, $data_set_name );
        }
        return $data;
    }

    /**
     * Scan file
     *
     * Submits the file to Socrata's scan method.
     *
     * @param $file_path
     * @return mixed Scan results.
     */
    public function scan_file( $file_path ) {

        $query = $this->post( "/imports2", Array( "method" => "scan" ), Array(
                CURLOPT_POSTFIELDS => Array(
                    "file" => "@" . $file_path
                )
            )
        );


        return $this->decode( $query );

    }


    /**
     * Append file
     * @param string $view_id Socrata view ID xxxx-xxxx
     * @param $file_name
     * @return mixed
     */
    public function append_file( $view_id, $file_name ) {


        while ( true ) {
            $data = $this->scan_file( $file_name );

            try {
                $query = $this->post( "/imports2.json", Array(
                        "name" => "temp.csv",
                        "fileId" => urlencode( $data[ "fileId" ] ),
                        "method" => "append",
                        "viewUid" => urlencode( $view_id )
                    )
                );
            } catch ( Exception $e ) {
                /**
                 * This part deals with 409 error.
                 * I wanted data sets to be uploaded ASAP,
                 * so I just retried every time I got an exception
                 *
                 */
                sleep( 60*10 );
                continue;
            }
            flush();

            return $this->decode( $query );
        }


        return false;
    }

    /**
     * Generate translation
     * http://dev.socrata.com/publisher/import-translations
     *
     *
     * @param $columns Array of columns in format
     *  Array( Array("name" => "id"), Array("name" => "location"))
     *  this format is required for compatibility with socrata blueprints
     * @return string Socrata translation based on the list of columns, e.g.
     * [col1, col2]
     * @throws InvalidArgumentException If defined blueprint is not an object or an array.
     */

    public function generate_translation( $columns ) {

        if ( empty( $this->_options[ "translation" ] ) ) {
            return "[]";
        }

        if ( !is_array( $this->_options[ "translation" ] ) && !is_object( $this->_options[ "translation" ] ) ) {
            throw new InvalidArgumentException( "Tranlation must be array or object" );
        }
        /**
         * It has a list of columns we want to modify using translation.
         * e.g.
         * Array("id"=>"{id}.substr(0,1)");
         *
         */
        $translation = (array)$this->_options[ "translation" ];

        /**
         * Create a map of columns in format Array("{id}"=>"col1", "{location}"=>"col2" )
         */
        $key_map = Array();
        foreach ( $columns as $key => $column ) {
            $key_map[ "{" . $column[ "name" ] . "}" ] = "col" . ( $key + 1 );
        }

        $result = Array();
        foreach ( $columns as $column ) {
            /**
             *  If user has not defined any translation for the column, we just push colN represenation.
             *  Otherwise we replace all occurences of {column_id} from key map to the ColN represenation, and push the result.
             */
            $result[ ] = empty( $translation[ $column[ "name" ] ] ) ?
                $key_map[ "{" . $column[ "name" ] . "}" ] :
                str_replace( array_keys( $key_map ), array_values( $key_map ), $translation[ $column[ "name" ] ] );
        }


        return "[" . join( ",", $result ) . "]";
    }

    /**
     * Create Working Copy
     * http://dev.socrata.com/publisher/workflow
     *
     * @param string $view Socrata view ID in xxxx-xxxx format
     * @throws exception
     */

    public function create_working_copy( $view ) {
        $response = $this->decode( $this->post( "/views/$view/publication.json?method=copy" ) );
        if ( empty( $response[ "id" ] ) ) {
            throw new Exception( "Socrata API didn't return valid ID of working copy." );
        }
        return $response[ "id" ];
    }

    /** Generate blueprint
     * http://dev.socrata.com/publisher/importing
     *
     * We want user to be able to explicitly set data types, by providing custom blueprint.
     * However we want user to define types only for columns he/she want.
     * e.g. Array( "zip" => "text" );
     *
     * Function generates Socrata Blueprint based on user defined blue print,
     * and types suggested by socrata scan results.
     *
     *
     * @param array $scan_results Scan Results returned by Socrata API
     * @param string $data_set_name
     * @return array Blueprint
     * @throws InvalidArgumentException If defined blueprint is not an object or an array.
     */


    public function generate_blueprint( $scan_results, $data_set_name ) {

        if ( empty( $scan_results[ "summary" ] ) ) {
            throw new InvalidArgumentException( "Scan results must be valid response from socrata API" );
        }

        if ( !is_array( $this->_options[ "blueprint" ] ) && !is_object( $this->_options[ "blueprint" ] ) ) {
            
            throw new InvalidArgumentException( "Blueprint  must be array or object" );
        }

        $blueprint = (array)$this->_options[ "blueprint" ];
        $scan_results[ "summary" ] = (array)$scan_results[ "summary" ];

        $columns = Array();
        /**
         * Go though the blueprint, if column type is not defined in user's blue print,
         * use the type suggested by Socrata.
         *
         */
        foreach ( $scan_results[ "summary" ][ "columns" ] as $column ) {
            if ( empty( $blueprint[ $column[ "name" ] ] ) ) {
                $type = $column[ "suggestion" ];
            } else {
                $type = $blueprint[ $column[ "name" ] ];
                unset( $blueprint[ $column[ "name" ] ] );
            }

            $columns[ ] = Array(
                "name" => $column[ "name" ],
                "datatype" => $type
            );
        }


        foreach ( $blueprint as $name => $type ) {
            $columns[ ] = Array(
                "name" => $name,
                "datatype" => $type
            );
        }


        return Array(
            "name" => $data_set_name,
            "skip" => $this->_options[ "lines_in_header" ],
            "columns" => $columns
        );
    }


    /**
     * Post file
     *
     * @param $file_path
     * @param $data_set_name
     * @return string result of the Query
     * @throws Exception
     */

    public function post_file( $file_path, $data_set_name ) {

        if ( !file_exists( $file_path ) ) {
            throw new Exception( "File doesn't exist " . $file_path );
        }

        /**
         * Socrata has limit for the file size. So if the file is too big, we'll have to split it
         * into multiple files, post the first one and then append  the rest..
         */
        if ( filesize( $file_path ) > 1.2 * $this->_options[ "chunk_size" ] ) {

            return $this->post_huge_file( $file_path, $data_set_name );
        }


        $scan_result = $this->scan_file( $file_path );

        $blueprint = $this->generate_blueprint( $scan_result, $data_set_name );

        $translation = $this->generate_translation( $blueprint[ "columns" ] );


        $query = $this->post( "/imports2.json", Array(
            "name" => urldecode( $data_set_name . ".csv" ),
            "fileId" => urlencode( $scan_result[ "fileId" ] ),
            "blueprint" => json_encode( $blueprint ),
            "translation" => $translation
        ) );


        return $this->decode( $query );


    }


}
