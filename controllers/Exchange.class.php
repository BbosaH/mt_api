<?php

require_once('Users.class.php');

class Exchange{

	private $_params;
	private $_db;
	private $crypto;
	private $users;

	public function __construct($params,$db){
		$this->_params = $params;
		$this->_db = $db;
		$this->users = new Users(null,$db);
		$this->crypto = new PasswordLib\PasswordLib;
		writeToLogFile("Class constructed\n");
	}

	public function createexchangeAction(){
		$fromCurrency = @ mysql_real_escape_string($this->_params['from_currency_id']);
		$toCurrency = @ mysql_real_escape_string($this->_params['to_currency_id']);
		$amtB4 = @ mysql_real_escape_string($this->_params['amount']);
		$amtAfter = @ mysql_real_escape_string($this->_params['amount_from_rate']);
		$exchangeRate = @ mysql_real_escape_string($this->_params['exchange_rate']);
		$userid = @ mysql_real_escape_string($this->_params['user_id']);
		$sessionid = @ mysql_real_escape_string($this->_params['session_id']);
		$branch_id = @ mysql_real_escape_string($this->_params['branch_id']);

		$exchange_branch_transaction = array();
		$exchange_branch_transaction['from_currency_id'] = $fromCurrency;
		$exchange_branch_transaction['to_currency_id'] = $toCurrency;
		$exchange_branch_transaction['amount'] = $amtB4;
		$exchange_branch_transaction['exchange_rate'] = $exchangeRate;
		$exchange_branch_transaction['amount_from_rate'] = $amtAfter;
		$exchange_branch_transaction['user_id'] = $userid;
		$exchange_branch_transaction['branch_id'] = $branch_id;
		$exchange_branch_transaction['transaction_type_id'] = 7;
		$exchange_branch_transaction['date']=date('Y-m-d', time());



		writeToLogFile("Exchange Params:\nFromCurrency : $fromCurrency\nToCurrency : $toCurrency\nAmountBe4 : $amtB4\nAmount After : $amtAfter\nExhange Rate : $exchangeRate\nUserID : $userid\nSession ID : $sessionid\n");

		$result['success'] = 0;

		if(empty($fromCurrency)){
			$result['errormsg'] = "Sorry your request failed. Reason : You did not select from currency";
			return $result;
		}

		if(empty($toCurrency)){
			$result['errormsg'] = "Sorry your request failed. Reason : You did not select to currency";
			return $result;
		}

		if(empty($amtB4)){
			$result['errormsg'] = "Sorry your request failed. Reason : Enter amount for exchange";
			return $result;
		}

		if(empty($amtAfter)){
			$result['errormsg'] = "Sorry your request failed. Reason : No amount after exchange";
			return $result;
		}

		if(empty($exchangeRate)){
			$result['errormsg'] = "Sorry your request failed. Reason : You did not enter exchange rate";
			return $result;
		}

		$userObj = $this->_db->doSelect('users',array(),array("id"=>$userid),1,array());
		/*if($sessionid != $userObj->sessionId){
			$result['errormsg'] = "Sorry your transaction failed. Reason: Your login expired";
			return $result;
		}*/

		$branchId = $userObj->branchId;

		$exchange = array('fromCurrencyId'=>$fromCurrency,'toCurrencyId'=>$toCurrency,'AmountBeforeXchange'=>$amtB4,'amountAfterXchange'=>$amtAfter,'exhangeRate'=>$exchangeRate,'userId'=>$userid,"branchId"=>$branchId);
		$id = $this->_db->doInsert('currency_exchange',$exchange);

		$transferRef = "ex$id";

		if($id > 0){
			$fromAccountObj = $this->_db->doSelect('accounts',array('id'),array("branchId"=>$branchId,'currency_id'=>$fromCurrency),1,array());
			$toAccount = $this->users->getSetting('SuspenseAccount');

			$flag = $this->_db->doInsert('account_transfers',array("type"=>'transfer',"amount"=>$amtB4,"amountB4Xchange"=>$amtB4,"amountAfterXchange"=>$amtB4,"fromId"=>$fromAccountObj->id,"toId"=>$toAccount,"userId"=>$userid,"createdBy"=>$userid,"transferRef"=>$transferRef));
			if($flag >0){
				//update balances
				$newAmt = intval($amtB4);
				$this->users->createBalances($branchId,$fromAccountObj->id,$newAmt* -1);
				$this->users->createBalances(0,$toAccount,$newAmt);
			}else{
				$result['errormsg'] = "Sorry your transaction failed. Reason: System unavailable, contact Admin.";
				return $result;
			}

			$fromAccountObj = $this->_db->doSelect('accounts',array('id'),array("branchId"=>$branchId,'currency_id'=>$toCurrency),1,array());
			$flag = $this->_db->doInsert('account_transfers',array("type"=>'transfer',"amount"=>$amtAfter,"amountB4Xchange"=>$amtAfter,"amountAfterXchange"=>$amtAfter,"fromId"=>$toAccount,"toId"=>$fromAccountObj->id,"userId"=>$userid,"createdBy"=>$userid,"transferRef"=>$transferRef));
			if($flag >0){
				//update balances
				$newAmt = intval($amtAfter);
				$this->users->createBalances($branchId,$fromAccountObj->id,$newAmt);
				$this->users->createBalances(0,$toAccount,$newAmt* -1);
			}else{
				$result['errormsg'] = "Sorry your transaction failed. Reason: System unavailable, contact Admin.";
				return $result;
			}
      $this->_db->doInsert('branch_transaction',$exchange_branch_transaction);
			$result['success'] = 1;
			$result['data'] = 'Your request was successfully processed.';
			return $result;
		}

 		$result['errormsg'] = "Sorry your transaction failed. Reason: System unavailable. Contact admin";
		return $result;
	}
}
