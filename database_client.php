<?php


class DatabaseClient
{
    protected $connection;
    protected $database_name;
    protected $database_host;
    protected $database_username;
    protected $database_password;

    public function __construct()
    {
        $this->database_name = $_ENV['DATABASE_NAME'];
        $this->database_host = $_ENV['DATABASE_HOST'];
        $this->database_username = $_ENV['DATABASE_USERNAME'];
        $this->database_password = $_ENV['DATABASE_PASSWORD'];
        $this->connection = $this->connect();
    }

    private function connect()
    {

        $connection = pg_connect("host={$this->database_host} dbname={$this->database_name} user={$this->database_username} password={$this->database_password}");

        if (!$connection) {
            Log::error('Could not connect to postgres database');
            exit();
        } else {
            return $connection;
        }
    }

    public function get_all_country_codes()
    {
        $codes = pg_query($this->connection, "SELECT phone_code, iso2 from country_codes");

        if (!$codes) {
            $this->query_error('Unable to retrive country codes.');
        }

        return pg_fetch_all($codes);
    }

    public function get_call_limit($account_name, $iso2)
    {

        $call_limit = pg_query($this->connection, "SELECT call_count from cc_limits where (customer='" . $account_name . "' or customer='default') and iso2='" . $iso2 . "' order by precedence desc limit 1");

        if (!$call_limit) {
            $this->query_error('Unable to retrive call limit.');
        }
        return pg_fetch_row($call_limit)[0];
    }

    //add fraud data to email_content table
    public function add_fraud($account_name, $iso2, $count)
    {

        $fraud_data = pg_query($this->connection, "insert into email_content (fraud_date, email_content, email_sent, sms_sent) values ('" . date('Y-m-d H:i:s') . "', '" . $account_name . " | " . $iso2 . " | " . $count . "', false, false);");

        if (!$fraud_data) {
            $error = pg_last_error($this->connection);
            Log::error('Unable to add fraud data. Details: ' . $error);
        }
    }

    //add statistics to gateway_stats table
    public function add_gateway_stats($gateway, $count, $direction)
    {

        $gateway_stats = pg_query($this->connection, "INSERT INTO gateway_stats (stat_date, gateway, call_count, direction) VALUES (' " . date('Y-m-d H:i:s') . "', '" . $gateway . "', " . $count . ",'" . $direction . "');");

        if (!$gateway_stats) {
            $error = pg_last_error($this->connection);
            Log::error('Unable to add gateway statistics. Details: ' . $error);
        }
    }

    //add statistics to gateway_stats table
    public function add_dest_usage($account_name, $count, $iso2)
    {

        $dest_usage = pg_query($this->connection, "INSERT INTO dest_usage (customer, iso2, call_count) VALUES ('" . $account_name . "', '" . $iso2 . "', " . $count . ");");

        if (!$dest_usage) {
            $error = pg_last_error($this->connection);
            Log::error('Unable to add destination usage. Details: ' . $error);
        }
    }
    
    //add CPS values to cps_load_stats table
    public function add_cps($values)
    {
        $cps_data = pg_query($this->connection, "INSERT INTO cps_load_stats (load_stat_date, realm, cps_limit, cps, cps_drops, node) VALUES $values");

        if (!$cps_data) {
            $error = pg_last_error($this->connection);
            Log::error('Unable to add CPS data. Details: ' . $error);
        }
    }

    //add RPS values to rps_load_stats table
    public function add_rps($values)
    {
        $rps_data = pg_query($this->connection, "INSERT INTO rps_load_stats (load_stat_date, realm, rps_limit, rps, rps_drops, node) VALUES $values");

        if (!$rps_data) {
            $error = pg_last_error($this->connection);
            Log::error('Unable to add RPS data. Details: ' . $error);
        }
    }

    //add OoDRPS values to oodrps_load_stats table
    public function add_oodrps($values)
    {
        $oodrps_data = pg_query($this->connection, "INSERT INTO oodrps_load_stats (load_stat_date, realm, oodrps_limit, oodrps, oodrps_drops, node) VALUES $values");

        if (!$oodrps_data) {
            $error = pg_last_error($this->connection);
            Log::error('Unable to add OoDRPS data. Details: ' . $error);
        }
    }

    //errors handling when retreving infromation from the database
    private function query_error($message)
    {
        $error = pg_last_error($this->connection);
        Log::error($message . ' Details: ' . $error);
        exit();
    }

    //add quotations to string values 
    public function value_string($column_name, $value)
    {
        if ($column_name == 'cps_limit' || $column_name == 'cps' || $column_name == 'cps_drop' || $column_name == 'rps_limit' || $column_name == 'rps' || $column_name == 'rps_drop' || $column_name == 'oodrps_limit' || $column_name == 'oodrps' || $column_name == 'oodrps_drop') {
            return $value;
        }
        return "'" . $value . "'";
    }

}