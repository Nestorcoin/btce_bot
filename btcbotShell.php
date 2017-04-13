<?php
namespace App\Shell;

use Cake\Console\Shell;

    const LOW_VOLUME = 0;          // Program state 0 Low volume. Maybe profit?
    const AVG_VOLUME = 1;          // Program state 1 Just a normal average day. We try to profit from them.
    const HIGH_VOLUME =  2;        // Program state 2 Something is going on with markets. 

    const TRADE_SIZE = 0.1;

    const EXPENSES = 1.0;
    const PROFIT_MARGIN =  3.0; // Profit per trade in USD

    // Trade costs are 0.4? 2usd = percent. 5usd = ~2 percent profit margin.
    // 2.5usd = ~1 percent profit margin.

class HelloShell extends Shell
{
    public $run = 0; // mark all trades made with this run with primary id of the first trade, set 0 in start of script 
                         // and once $run is set, is is kept as the primary key value of first entity created on this run

    public function initialize()
    {
        parent::initialize();
        $this->loadModel('Trades');
    }

    public function main()
    {

        while(1)
        {
            $result = $this->btce_query('getInfo');
            //$result = btce_query('Trade', array('pair' => 'btc_usd', 'type' => 'buy', 'amount' => 1, 'rate' => 10)); //buy 1 BTC @ 10 USD

            sleep(4);
                    //system("clear");
            sleep(1);

            $state = AVG_VOLUME; // hardcoded state 
            switch($state) {
                case LOW_VOLUME: 

                break;
                case AVG_VOLUME: 

                    echo "\n\nUSD: ",$result['return']['funds']['usd'];
                    echo "\nBTC: ",$result['return']['funds']['btc'];
                    $ticker = $this->btce_ticker();
                    echo "\n\nVolume:   ",$ticker['btc_usd']['vol'];
                    echo "\nPrice high: ",$ticker['btc_usd']['high'];
                    echo "\nPrice Avg:  ",$ticker['btc_usd']['avg'];
                    echo "\nPrice Low:  ",$ticker['btc_usd']['low'];

                    echo "\n\nBuy:  ",$ticker['btc_usd']['buy'];
                    echo "\nSell: ",$ticker['btc_usd']['sell'];

                    $trade = $this->Trades->find('all')->toArray();
                    $trade = array_pop($trade);
                    
                    if($trade->sell_price > 0 AND $trade->buy_price > 0)
                    { // SELL

/* existing, old trade. nothing to do
*/
                        $trade = $this->Trades->newEntity();
                        $result = $this->btce_query('Trade', array('pair' => 'btc_usd', 'type' => 'sell', 'amount' => TRADE_SIZE, 'rate' => $ticker['btc_usd']['sell']));
                        echo "SELL";
                        debug($result);
                        $trade->sell_price = $ticker['btc_usd']['sell'];
                        $trade->sell_timestamp = time();
                        echo "Creating new trade,";
                        echo "\n\n Sold at ", $ticker['btc_usd']['sell'];
                        $this->Trades->save($trade);
        
                    }
                    if(empty($trade))// OR (($trade->sell_price < 1) AND ($trade->buy_price < 1)) )  // We have an entity
                    { // SELL No trade exists. Can be empty (nothing in sql) or 
                        $trade = $this->Trades->newEntity();

                        if($this->run = 0) { // Set run to be id of the first trade made with this run of script
                            $this->$run = $trade->id; // mark all trades made with this run with primary id of the first trade 
                        }
                        if($this->run > 0) {
                            $trade->run = $this->run;
                        }

                        $result = $this->btce_query('Trade', array('pair' => 'btc_usd', 'type' => 'sell', 'amount' => TRADE_SIZE, 'rate' => $ticker['btc_usd']['sell']));
                        echo "SELL";
                        debug($result);
                        echo "Creating first trade,";
                        $trade->sell_price = $ticker['btc_usd']['sell'];
                        $trade->sell_timestamp = time();
                        echo "\n\n Sold at ", $ticker['btc_usd']['sell'];
                        $this->Trades->save($trade);
                    }
                    if( ($trade->sell_price > 0) && (empty($trade->buy_price)) ) // Has been sold but not yet bought back
                    { // BUY 
                        $profit = $trade->sell_price - PROFIT_MARGIN;
                        echo "\nBuy at target: ", $profit;

                        if($ticker['btc_usd']['buy'] < ($profit) )
                        {        
                            echo "BUY";
                            if($this->run > 0) {
                                $trade->run = $this->run;
                            }
                            echo "\n\n Bought at ", $ticker['btc_usd']['buy'];
                            echo "\n\n profit margin: ", ($trade->sell_price + PROFIT_MARGIN);
                            $result = $this->btce_query('Trade', array('pair' => 'btc_usd', 'type' => 'buy', 'amount' => TRADE_SIZE, 'rate' => $ticker['btc_usd']['buy']));
                            debug($result);
                            $trade->buy_price = $ticker['btc_usd']['buy'];
                            $trade->buy_timestamp = time();
                            $this->Trades->save($trade);
                        }
                    }

                    // Myy kolikko.
                    // Jos hinta on pienempi kuin kantaan merktty myyntihinta
                    // Osta takaisin
                    // if($trade->sell_price - $ticker['btc_usd']['buy']  

                    // Jos nykyinen hinta on rempi kuin jo myydyn ostohinta
                    // Jos nykyinen hinta on suurempi kuin jo myydyn ostohinta
                    
                break;

                case HIGH_VOLUME: 

                break;
            }
        }
    }

    public function btce_ticker()
    {
        $ticker = file_get_contents("https://btc-e.com/api/3/ticker/btc_usd");
        $ticker = json_decode($ticker,true);
        return $ticker;
    }

    public function btce_query($method, array $req = array()) {
        // API settings
        $key = ''; // your API-key
        $secret = ''; // your Secret-key
 
        $req['method'] = $method;
        $mt = explode(' ', microtime());
        $req['nonce'] = $mt[1];
       
        // generate the POST data string
        $post_data = http_build_query($req, '', '&');
 
        $sign = hash_hmac('sha512', $post_data, $secret);
 
        // generate the extra headers
        $headers = array(
            'Sign: '.$sign,
            'Key: '.$key,
        );
 
        // our curl handle (initialize if required)
        static $ch = null;
        if (is_null($ch)) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; BTCE PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
        }
        curl_setopt($ch, CURLOPT_URL, 'https://btc-e.com/tapi/');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
 
        // run the query
        $res = curl_exec($ch);
        if ($res === false) throw new Exception('Could not get reply: '.curl_error($ch));
        $dec = json_decode($res, true);
        //if (!$dec) throw new Exception('Invalid data received, please make sure connection is working and requested API exists');
        return $dec;
    }
}
/*
                        if($this->run = 0) { // Set run to be id of the first trade made with this run of script
                            $this->$run = $trade->id; // mark all trades made with this run with primary id of the first trade 
                        }
                        if($this->run > 0) {
                            $trade->run = $this->run;
                        }
*/
