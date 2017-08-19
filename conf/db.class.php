<?php

error_reporting(1);

class DB {
	private $dbhost;
	private $dbname;
	private $dbpassword;
	private $dbusername;
	private $dblink;

	public function __construct($config){
		$this->dbhost = $config->db->host;
		$this->dbname = $config->db->name;
		$this->dbpassword = $config->db->password;
		$this->dbusername = $config->db->user;

		$this->openDB();
	}

	private function openDB(){
		if(!(isset($this->dblink))){
	 		@ $this->dblink = mysql_connect($this->dbhost, $this->dbusername, $this->dbpassword);
			if(!$this->dblink) {
				die('Could not connect to database. db_config error<br/>'.mysql_error());
			}

			if (!( @ mysql_select_db($this->dbname, $this->dblink))) {
				die('Could not select database<br/>'.mysql_error());
			}
		}
	}

	public function closeDB(){
		mysql_close($this->dblink);
	}

	public function doSelect($table,$fields,$where,$whereType=1,$order,$sort='ASC',$limit=10){
		$columns = '*';
		$whereStr = '';
		$orderStr = '';
		//prepare table fields
		$fieldCount = count($fields);
		if($fieldCount>0){
			$columns = $fields[0];
			for($i=1;$i<$fieldCount;$i++){
				$columns .= ','.$fields[$i];
			}
		}
		//prepare WHERE close
		$whereCount = count($where);
		if($whereCount>0){
			foreach($where as $name=>$value){
				if($whereType == 1){
					$whereStr .= "AND $name = '$value' ";
				}else{
					$whereStr .= " OR $name = '$value' ";
				}
			}
			$whereStr = "WHERE ".substr_replace($whereStr,'',0,3);
		}
		//prepare order by section
		$orderCount = count($order);
		if($orderCount>0){
			$orderStr = 'ORDER BY '.$order[0];
			for($i=1;$i<$orderCount;$i++){
				$orderStr .= ', '.$order[$i];
			}
			$orderStr.= " $sort LIMIT $limit";
		}

		$sql = "SELECT $columns FROM $table $whereStr $orderStr";
		writeToLogFile("SELECT SQL : $sql\n");
		$query = mysql_query($sql);
		if($query && mysql_numrows($query)>0){
			if(mysql_numrows($query) == 1){//return a data row
				return mysql_fetch_object($query);
			}else{//return an array of data rows
				$myArray = array();
				while($row = mysql_fetch_object($query)){
					$myArray[] = $row;
				}
				return $myArray;
			}
		}

		return 0;
	}

	public function doInsert($table,$insertObj){
		if(count($insertObj)>0){
			$clmns = 'ts';
			$values = "'".date('Y-m-d H:m:i')."'";
			$sql = "INSERT INTO $table ";
			foreach($insertObj as $name=>$value){
				$clmns .= ','.$name;
				$values .= ",'$value'";
			}
			$clmns = " ($clmns) ";
			$values = " VALUES($values)";

			$sql .= $clmns.$values;
			writeToLogFile("INSERT SQL : $sql\n");
			$result = mysql_query($sql);
			if($result == 1){
				return mysql_insert_id();
			}
			return $result;
		}

		return 0;
	}

	public function doUpdate($table,$updateObj,$where,$userid){
		if(count($updateObj)>0){
			$sql = "UPDATE $table SET ";
			$setStr = '';
			$whereStr = '';
			foreach($updateObj as $name=>$value){
				$setStr .= ",$name = '$value'";
				$this->addAudit($table,$userid,array($name=>$value),$where);
			}
			$setStr = substr_replace($setStr,'',0,1);

			foreach($where as $name=>$value){
				$whereStr .= "AND $name = '$value' ";
			}
			$whereStr = ' WHERE '.substr_replace($whereStr,'',0,3);

			$sql .= $setStr.$whereStr;
			writeToLogFile("UPDATE SQL : $sql\n");
			return mysql_query($sql);
		}
		return 0;
	}

	private function addAudit($table,$userid,$changes,$where){
		if($userid > 0){
			foreach($changes as $name=>$value){
				$object = $this->doSelect($table,array('id',$name),$where,'1',array());
				$oldValue = $object->$name;
				$recordId = $object->id;
				$id = $this->doInsert('audit_trails',array('userId'=>$userid,'tableName'=>$table,'recordId'=>$recordId,'field'=>$name,'oldValue'=>$oldValue,'newValue'=>$value));
				writeToLogFile("audit_trails Insert ID : $id\n");
			}
		}
	}

	public function getAccountBalance($accountId){

		$fromDeposits = 0;
		$fromTransfers = 0;
		$toTransfers = 0;

		$q = "SELECT SUM(amount) as total FROM account_transfers WHERE toId=$accountId AND type='deposit' AND active = 1";
		$r = mysql_query($q);
		if ($r) {
			if (mysql_num_rows($r) > 0) {
				$o = mysql_fetch_object($r);
				$fromDeposits = $o->total;
			}
		}

		$q = "SELECT SUM(amount) as total FROM account_transfers WHERE toId=$accountId AND type='transfer' AND active = 1";
		$r = mysql_query($q);
		if ($r) {
			if (mysql_num_rows($r) > 0) {
				$o = mysql_fetch_object($r);
				$fromTransfers = $o->total;
			}
		}

		$q = "SELECT SUM(amount) as total FROM account_transfers WHERE fromId=$accountId AND type='transfer' AND active = 1";
		$r = mysql_query($q);
		if ($r) {
			if (mysql_num_rows($r) > 0) {
				$o = mysql_fetch_object($r);
				$toTransfers = $o->total;
			}
		}

		$total = (($fromDeposits + $fromTransfers) - $toTransfers);
		return $total;
	}
}

/*$currency_symbols = array(
	'AED' => '&#1583;.&#1573;', // ?
	'AFN' => '&#65;&#102;',
	'ALL' => '&#76;&#101;&#107;',
	'AMD' => '',
	'ANG' => '&#402;',
	'AOA' => '&#75;&#122;', // ?
	'ARS' => '&#36;',
	'AUD' => '&#36;',
	'AWG' => '&#402;',
	'AZN' => '&#1084;&#1072;&#1085;',
	'BAM' => '&#75;&#77;',
	'BBD' => '&#36;',
	'BDT' => '&#2547;', // ?
	'BGN' => '&#1083;&#1074;',
	'BHD' => '.&#1583;.&#1576;', // ?
	'BIF' => '&#70;&#66;&#117;', // ?
	'BMD' => '&#36;',
	'BND' => '&#36;',
	'BOB' => '&#36;&#98;',
	'BRL' => '&#82;&#36;',
	'BSD' => '&#36;',
	'BTN' => '&#78;&#117;&#46;', // ?
	'BWP' => '&#80;',
	'BYR' => '&#112;&#46;',
	'BZD' => '&#66;&#90;&#36;',
	'CAD' => '&#36;',
	'CDF' => '&#70;&#67;',
	'CHF' => '&#67;&#72;&#70;',
	'CLF' => '', // ?
	'CLP' => '&#36;',
	'CNY' => '&#165;',
	'COP' => '&#36;',
	'CRC' => '&#8353;',
	'CUP' => '&#8396;',
	'CVE' => '&#36;', // ?
	'CZK' => '&#75;&#269;',
	'DJF' => '&#70;&#100;&#106;', // ?
	'DKK' => '&#107;&#114;',
	'DOP' => '&#82;&#68;&#36;',
	'DZD' => '&#1583;&#1580;', // ?
	'EGP' => '&#163;',
	'ETB' => '&#66;&#114;',
	'EUR' => '&#8364;',
	'FJD' => '&#36;',
	'FKP' => '&#163;',
	'GBP' => '&#163;',
	'GEL' => '&#4314;', // ?
	'GHS' => '&#162;',
	'GIP' => '&#163;',
	'GMD' => '&#68;', // ?
	'GNF' => '&#70;&#71;', // ?
	'GTQ' => '&#81;',
	'GYD' => '&#36;',
	'HKD' => '&#36;',
	'HNL' => '&#76;',
	'HRK' => '&#107;&#110;',
	'HTG' => '&#71;', // ?
	'HUF' => '&#70;&#116;',
	'IDR' => '&#82;&#112;',
	'ILS' => '&#8362;',
	'INR' => '&#8377;',
	'IQD' => '&#1593;.&#1583;', // ?
	'IRR' => '&#65020;',
	'ISK' => '&#107;&#114;',
	'JEP' => '&#163;',
	'JMD' => '&#74;&#36;',
	'JOD' => '&#74;&#68;', // ?
	'JPY' => '&#165;',
	'KES' => '&#75;&#83;&#104;', // ?
	'KGS' => '&#1083;&#1074;',
	'KHR' => '&#6107;',
	'KMF' => '&#67;&#70;', // ?
	'KPW' => '&#8361;',
	'KRW' => '&#8361;',
	'KWD' => '&#1583;.&#1603;', // ?
	'KYD' => '&#36;',
	'KZT' => '&#1083;&#1074;',
	'LAK' => '&#8365;',
	'LBP' => '&#163;',
	'LKR' => '&#8360;',
	'LRD' => '&#36;',
	'LSL' => '&#76;', // ?
	'LTL' => '&#76;&#116;',
	'LVL' => '&#76;&#115;',
	'LYD' => '&#1604;.&#1583;', // ?
	'MAD' => '&#1583;.&#1605;.', //?
	'MDL' => '&#76;',
	'MGA' => '&#65;&#114;', // ?
	'MKD' => '&#1076;&#1077;&#1085;',
	'MMK' => '&#75;',
	'MNT' => '&#8366;',
	'MOP' => '&#77;&#79;&#80;&#36;', // ?
	'MRO' => '&#85;&#77;', // ?
	'MUR' => '&#8360;', // ?
	'MVR' => '.&#1923;', // ?
	'MWK' => '&#77;&#75;',
	'MXN' => '&#36;',
	'MYR' => '&#82;&#77;',
	'MZN' => '&#77;&#84;',
	'NAD' => '&#36;',
	'NGN' => '&#8358;',
	'NIO' => '&#67;&#36;',
	'NOK' => '&#107;&#114;',
	'NPR' => '&#8360;',
	'NZD' => '&#36;',
	'OMR' => '&#65020;',
	'PAB' => '&#66;&#47;&#46;',
	'PEN' => '&#83;&#47;&#46;',
	'PGK' => '&#75;', // ?
	'PHP' => '&#8369;',
	'PKR' => '&#8360;',
	'PLN' => '&#122;&#322;',
	'PYG' => '&#71;&#115;',
	'QAR' => '&#65020;',
	'RON' => '&#108;&#101;&#105;',
	'RSD' => '&#1044;&#1080;&#1085;&#46;',
	'RUB' => '&#1088;&#1091;&#1073;',
	'RWF' => '&#1585;.&#1587;',
	'SAR' => '&#65020;',
	'SBD' => '&#36;',
	'SCR' => '&#8360;',
	'SDG' => '&#163;', // ?
	'SEK' => '&#107;&#114;',
	'SGD' => '&#36;',
	'SHP' => '&#163;',
	'SLL' => '&#76;&#101;', // ?
	'SOS' => '&#83;',
	'SRD' => '&#36;',
	'STD' => '&#68;&#98;', // ?
	'SVC' => '&#36;',
	'SYP' => '&#163;',
	'SZL' => '&#76;', // ?
	'THB' => '&#3647;',
	'TJS' => '&#84;&#74;&#83;', // ? TJS (guess)
	'TMT' => '&#109;',
	'TND' => '&#1583;.&#1578;',
	'TOP' => '&#84;&#36;',
	'TRY' => '&#8356;', // New Turkey Lira (old symbol used)
	'TTD' => '&#36;',
	'TWD' => '&#78;&#84;&#36;',
	'TZS' => '',
	'UAH' => '&#8372;',
	'UGX' => '&#85;&#83;&#104;',
	'USD' => '&#36;',
	'UYU' => '&#36;&#85;',
	'UZS' => '&#1083;&#1074;',
	'VEF' => '&#66;&#115;',
	'VND' => '&#8363;',
	'VUV' => '&#86;&#84;',
	'WST' => '&#87;&#83;&#36;',
	'XAF' => '&#70;&#67;&#70;&#65;',
	'XCD' => '&#36;',
	'XDR' => '',
	'XOF' => '',
	'XPF' => '&#70;',
	'YER' => '&#65020;',
	'ZAR' => '&#82;',
	'ZMK' => '&#90;&#75;', // ?
	'ZWL' => '&#90;&#36;',
);*/

?>
