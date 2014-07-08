<html>
	<head>
		<meta charset="utf-8"> 
	</head>
<body>
<pre>
<?php
$ticker = new Ticker();
$ticker -> echoCoins();
$ticker -> echoTotal();
$ticker -> debug(FALSE);

class Ticker {
	private $maxTries = 10;
	private $exchanges = array("bittrex" => "https://bittrex.com/api/v1/public/getmarketsummaries", 
						       "polo" => "https://poloniex.com/ticker",
							   "mintpal" => "https://api.mintpal.com/v1/market/summary/");
	
	public function __construct() {
		$ini = parse_ini_file("data.ini", true);
		foreach ($ini as $exchange => $pairs) {
			$this -> setExchange($exchange);
			foreach ($pairs as $pair => $value) {
				$price = false;
				$tmp = explode(",", $value);
				$amount = $tmp[0];
				if (isset($tmp[1]))
					$price  = $tmp[1];
				$this -> add($pair, $amount, $price); 
			}
		}
	}
	
	public function listExchanges() {
		echo "Exchanges:\n";
		foreach ($this -> exchanges as $name => $value) {
			echo "$name\n";
		}
	}
	
	public function debug($enabled = FALSE) {
		if ($enabled) {
			foreach ($this -> debug as $line) {
				echo "$line\n";
			}
		}
	}
	
	public function echoTotal() {
		$value = 0;
		$paid = 0;
		foreach($this -> coins as $coin) {
			$value += $coin -> getValue();
			$paid += $coin -> getPaid();
		}
		echo "\t\t\t\t$value\t$paid\n";
	}
	
	public function setExchange($exchange) {
		if (isset($this -> data[$exchange])) {
			$this -> output[] = $exchange;
			$this -> debug[] = "Exchange $exchange wurde schon geladen.";
			$this -> currentExchange = $exchange;
			return(true);
		}
			
		// Gibts keinen Eintrag für
		if (!isSet($this -> exchanges[$exchange])) {
			$this -> debug[] = "Exchange $exchange gibt es keine Konfiguration f&uuml;r.";
			return(false);
		}
		$url = $this -> exchanges[$exchange];
		$this -> debug[] = "Lade Ticker von $exchange";	
		$ctx = stream_context_create(array('http' => array('timeout' => 2)));
		$maxTries = $this -> maxTries;
		for ($try = 1; $try <= $maxTries; $try++) {
			$this -> debug[] = "#$try";
			$data = @file_get_contents($url, 0, $ctx);
			if ($data)
				break;
		}
		if (!$data) {
			$this -> debug[] = "$exchange API konnte nicht aufgerufen werden, Server down?\n$url\n$data";
			return(false);
		}
		
		$data = json_decode($data);
		if (!$data) {
			$this -> debug[] = "Kein g&uuml;ltiges JSON!";
			return(false);
		}
		$this -> debug[] = "Done";
		$this -> data[$exchange] = $data;
		$this -> currentExchange = $exchange;
		return(true);		
	}
	
	public function add($pair, $amount, $price = 0) {
		$name = strtolower($pair);
		$value = false;
		// Es wurde noch keine Exchange gesetzt, direkt raus
		if (!$this -> currentExchange) {
			$this -> debug[] = "Es wurde keine Exchange gesetzt...";
			return(false);
		}
		
		if ($this -> currentExchange == "bittrex") {
			$prefix = "BTC-";
			$pair = strtoupper($prefix.$pair);
			$data = $this -> data[$this -> currentExchange];
			foreach ($data -> result as $market) {
				if ($market -> MarketName == $pair) {
					$value = $market -> Last;
					break;
				}
			}
		}
		elseif ($this -> currentExchange == "polo") {
			$prefix = "BTC_";
			$pair = strtoupper($prefix.$pair);
			$data = $this -> data[$this -> currentExchange];
			if (isset($data -> $pair)) {
				$value = $data -> $pair;
			}
		}
		elseif ($this -> currentExchange == "mintpal") {
			$data = $this -> data[$this -> currentExchange];
			foreach ($data as $market) {
				if ($market -> code == strtoupper($pair) && $market -> exchange == "BTC") {
					$value = $market -> last_price;
					break;
				}
			}
		}
		
		// Wert gefunden, alles gut
		if ($value) {
			$this -> debug[] = "+$name => ".number_format($value, 8);
			$label = $name;
			for ($i = 1; (isset($this -> coins[$label])); $i++) {
				$label = $name.$i;
			}
			$this -> coins[$label] = new Coin($name, $value, $amount, $price);
				return(true);
		}
		$this -> debug[] = "$pair wurde auf ".$this -> currentExchange." nicht gefunden.";
		return(false);
	}
	
	public function echoCoins() {
		if (!count($this -> coins))
			return(false);
		$result = "<pre>";
		foreach ($this -> coins as $coin) {
			$result .= $coin;
		}
		$result .= "</pre>";
		echo $result;
	}
}

class Coin {
	private $amount;
	private $paid;
	private $rate;
	
	public function __construct($name, $rate, $amount, $price) {
		$this -> name = $name;
		$this -> rate = number_format($rate, 8);
		$this -> amount = $amount;
		$this -> paid = $price;
	}
	
	function getValue() {
		return(round($this -> amount * $this -> rate,4));
	}
	
	function getPaid() {
		// Wenn die Coins nicht gekauft wurden muss der aktuelle Wert drauf gerechnet werden
		if (!$this -> paid)
			return(round($this -> amount * $this -> rate,4));
			
		return(round($this -> amount * $this -> paid, 4));
	}
	
	function getQuote() {
		if (!$this -> paid)
			return(false);
		$paid = $this -> amount * $this -> paid;
		$value = $this -> getValue();
		$profit = $value - $paid;
		$quote = round($value / $paid * 100);
		return($quote."%");
	}
	public function __toString() {
		$result = $this -> name;
		$result .= "\t";
		$result .= $this -> rate;
		$result .= "\t";
		$result .= $this -> amount;
		$result .= "\t";
		$result .= $this -> getValue();
		$result .= "\t";
		$result .= $this -> getQuote();
		$result .= "\n";
		return($result);
	}
}
?>
</table>
</body>
</html>
