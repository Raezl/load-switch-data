<?php


class SwitchData
{
    protected SoapClient $client;
    protected $gateway_src_count = array();
    protected $gateway_ter_count = array();
    protected $account_names = array();
    protected $calls_per_account_count = array();

    public function __construct()
    {
        $this->client = $this->create_soap_client();
    }

    private function create_soap_client()
    {
        try {
            $options['exceptions'] = true;
            $options['stream_context'] = stream_context_create(
                array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                )
            );

            $headers = array();
            $headers[] = new SoapHeader('auth', 'Login', $_ENV['SWITCH_USERNAME']);
            $headers[] = new SoapHeader('auth', 'Password', $_ENV['SWITCH_PASSWORD']);



            $client = new SoapClient($_ENV['SWITCH_URL'], $options);
            $client->__setSoapHeaders($headers);
            return $client;

        } catch (\SoapFault $exception) {
            $client_err = $client->__getLastResponse();
            Log::error('Soap client error. Details: ' . $client_err);
            exit();
        }

    }

    //get all active call details from the switch
    public function get_rows($table, $filter, $sort)
    {
        return $this->client->selectRowset($table, $filter, $sort, 100000, 0);
    }

    //get number of active calls on the switch
    public function get_row_count($table, $filter, $sort)
    {
        return $this->client->countRowset($table, $filter);
    }

    //count by gateway sources
    public function count_per_source_gw($call_info): array
    {
        if ($call_info->name == 'incoming_gateway_name') {
            array_key_exists($call_info->value, $this->gateway_src_count) ? $this->gateway_src_count[$call_info->value] += 1 : $this->gateway_src_count[$call_info->value] = 1;
        }
        return $this->gateway_src_count;
    }

    //count by gateway terminations
    public function count_per_termination_gw($call_info): array
    {
        if ($call_info->name == 'outgoing_gateway_name') {

            if (empty($call_info->value)) {
                $call_info->value = "mera-trying";
            }

            array_key_exists($call_info->value, $this->gateway_ter_count) ? $this->gateway_ter_count[$call_info->value] += 1 : $this->gateway_ter_count[$call_info->value] = 1;
        }
        return $this->gateway_ter_count;
    }

    //count call per account by destination
    public function count_calls_per_account_by_dest($account_name, $iso2): array
    {
        /**
         * call_per_account associative array strcuture:
         * key: account_name iso2    value: call_count
         * */

        $key = "{$account_name} {$iso2}";
        array_key_exists($key, $this->calls_per_account_count) ? $this->calls_per_account_count[$key] += 1 : $this->calls_per_account_count[$key] = 1;
        return $this->calls_per_account_count;
    }

}