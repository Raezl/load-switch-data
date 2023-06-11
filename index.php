<?php

$start_time = microtime(TRUE);

require('switch_data.php');
require('database_client.php');
require('helper/switch_options.php');
require('helper/phone_number.php');
require('helper/log.php');
require('vendor/autoload.php');

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();


//Switch tables
$active_calls_table = $_ENV['SWITCH_TBL_CALLS'];
$cps_table = $_ENV['SWITCH_TBL_CPS'];
$rps_table = $_ENV['SWITCH_TBL_RPS'];
$oodrps_table = $_ENV['SWITCH_TBL_OODRPS'];

$switch_data = new SwitchData();

$calls_table_filter = SwitchOptions::filter('agg', 'and', 'cond', 'incoming_gateway_name', 'like', '%');
$calls_table_sort = SwitchOptions::sort('incoming_gateway_name', 'asc');

$active_call_count = $switch_data->get_row_count($active_calls_table, $calls_table_filter, $calls_table_sort);
$active_calls = $switch_data->get_rows($active_calls_table, $calls_table_filter, $calls_table_sort);

$source_gw_count;
$termination_gw_count;
$calls_per_account_count;
$account_name;

$db_client = new DatabaseClient();
$country_codes = $db_client->get_all_country_codes();


//Only 1 call active on switch
if ($active_call_count == 1) {
	foreach ($active_calls->item->item as $call_info) {
		$incoming_dest_number_exist = false;

		//count number of active calls per src gateway
		$source_gw_count = $switch_data->count_per_source_gw($call_info);

		//count number of active call per termination gateway
		$termination_gw_count = $switch_data->count_per_termination_gw($call_info);


		//account names/numbers extracted from gateway name
		if ($call_info->name == 'incoming_gateway_name') {
			$account_name = strtok($call_info->value, '-');
		}


		if ($call_info->name == 'incoming_dst_number') {
			$incoming_dest_number_exist = true;
			$normalised_number = PhoneNumber::normalise($call_info->value);
			$iso2 = PhoneNumber::get_iso2($normalised_number, $country_codes);
		}
	}

	// count call per carrier and dest country
	$calls_per_account_count = $switch_data->count_calls_per_account_by_dest($account_name, $iso2);

	//no destination number or iso2 fraud
	if (!$incoming_dest_number_exist || $iso2 == 'none') {
		$db_client->add_fraud($account_name, $iso2, $account_name);
	}

}

//Multiple calls active on switch
else {
	foreach ($active_calls->item as $item) {
		foreach ($item->item as $call_info) {

			$incoming_dest_number_exist = false;

			//count number of active calls per src gateway
			$source_gw_count = $switch_data->count_per_source_gw($call_info);

			//count number of active call per termination gateway
			$termination_gw_count = $switch_data->count_per_termination_gw($call_info);


			//account names/numbers extracted from gateway name
			if ($call_info->name == 'incoming_gateway_name') {
				$account_name = strtok($call_info->value, '-');
			}


			if ($call_info->name == 'incoming_dst_number') {
				$incoming_dest_number_exist = true;
				$normalised_number = PhoneNumber::normalise($call_info->value);
				$iso2 = PhoneNumber::get_iso2($normalised_number, $country_codes);
			}
		}


		// count call per carrier and dest country
		$calls_per_account_count = $switch_data->count_calls_per_account_by_dest($account_name, $iso2);

		//no destination number or iso2 fraud
		if (!$incoming_dest_number_exist || $iso2 == 'none') {
			$db_client->add_fraud($account_name, $iso2, $account_name);
		}
	}
}


//check if call count exceeded per account
foreach ($calls_per_account_count as $key => $call_count) {

	/**
	 * call_per_account associative array strcuture:
	 * key: account_name iso2    value: call_count
	 * */

	$keys = explode(' ', $key);
	$call_limit = $db_client->get_call_limit($keys[0], $keys[1]);

	//compare with the limit specified on the database
	if ($call_count > $call_limit) {
		$db_client->add_fraud($keys[0], $keys[1], $call_count);
	}
}



/**
 * Add CPS, RPS and OoDRPS data from switch to database
 *  */

$realm_filter = SwitchOptions::filter('agg', 'and', 'cond', 'Realm', 'like', '%');
$realm_sort = SwitchOptions::sort('Realm', 'asc');

//get data from the switch
$cps_data = $switch_data->get_rows($cps_table, $realm_filter, $realm_sort);
$rps_data = $switch_data->get_rows($rps_table, $realm_filter, $realm_sort);
$oodrps_data = $switch_data->get_rows($oodrps_table, $realm_filter, $realm_sort);


$cps_values = '';
$rps_values = '';
$oodrps_values = '';

//extract data from soap return object and format it to ('value1', 'value2') 
for ($x = 0; $x < count($cps_data->item); $x++) {
	$date_time = date('Y-m-d G:i:s');

	$cps_row = "'" . $date_time . "'";
	$rps_row = "'" . $date_time . "'";
	$oodrps_row = "'" . $date_time . "'";

	for ($i = 0; $i < 5; $i++) {

		if ($rps_data->item[$x]->item[$i]->name == 'rsp_limit' && $rps_data->item[$x]->item[$i]->value == 'NULL')
			$rps_data->item[$x]->item[$i]->value = 0;
		if ($oodrps_data->item[$x]->item[$i]->name == 'oodrps_limit' && $oodrps_data->item[$x]->item[$i]->value == 'NULL')
			$oodrps_data->item[$x]->item[$i]->value = 0;

		$cps_row .= ',' . $db_client->value_string($cps_data->item[$x]->item[$i]->name, $cps_data->item[$x]->item[$i]->value);
		$rps_row .= ',' . $db_client->value_string($rps_data->item[$x]->item[$i]->name, $rps_data->item[$x]->item[$i]->value);
		$oodrps_row .= ',' . $db_client->value_string($oodrps_data->item[$x]->item[$i]->name, $oodrps_data->item[$x]->item[$i]->value);
	}
	$cps_values .= ',(' . $cps_row . ')';
	$rps_values .= ',(' . $cps_row . ')';
	$oodrps_values .= ',(' . $cps_row . ')';
}

//add data to the database
$db_client->add_cps(substr($cps_values, 1));
$db_client->add_rps(substr($rps_values, 1));
$db_client->add_oodrps(substr($oodrps_values, 1));

$end_time = microtime(TRUE);
Log::debug('Done in ' . $end_time - $start_time);