<?php

require_once('Users.class.php');

class Transfers{

	private $_params;
	private $_db;
	private $crypto;
	private $users;
	//private $mandatoryFields = array('user_pass_code','transaction_password','user_id','session_id','amount','currency_id','rbranch_id','sfirst_name','');

	public function __construct($params,$db){
		$this->_params = $params;
		$this->_db = $db;
		$this->users = new Users(null,$db);
		$this->crypto = new PasswordLib\PasswordLib;
		writeToLogFile("Class constructed\n");
	}

	public function createtransferAction(){
		$transferArray = array();
		//get transfer details
		$passcode = @ mysql_real_escape_string($this->_params['user_pass_code']);
		$password = @ mysql_real_escape_string($this->_params['transaction_password']);

		$userid = @ mysql_real_escape_string($this->_params['user_id']);
		$transferArray['userIdSource'] = $userid;
		$sessionid = @ mysql_real_escape_string($this->_params['session_id']);

		$amount =@ mysql_real_escape_string($this->_params['amount']);
		$transferArray['amount'] = $amount;

		$amountAfterXchage =@ mysql_real_escape_string($this->_params['amount_from_rate']);
		//$transferArray['amount'] = $amount;

		$currencyid = @ mysql_real_escape_string($this->_params['to_currency_id']);
		$fromCurrency = @ mysql_real_escape_string($this->_params['from_currency_id']);
		$transferArray['currency'] = $currencyid;
		$countryid = @ mysql_real_escape_string($this->_params['country_id']);
		$transaction_type_id = @ mysql_real_escape_string($this->_params['transaction_type_id']);
		$branch_id = @ mysql_real_escape_string($this->_params['branch_id']);
		if(empty($countryid)){
			$countryid = 0;
		}
		$transferArray['destinationCountryId'] = $countryid;
		$rbranchid = @ mysql_real_escape_string($this->_params['preffered_branch_id']);
		if(empty($rbranchid)){
			$rbranchid = 0;
		}
		$transferArray['toBranch'] = $rbranchid;
		$charge = @ mysql_real_escape_string($this->_params['charge']);

		$chargecurr = @ mysql_real_escape_string($this->_params['charge_currency_id']);

		$xchangerate = @ mysql_real_escape_string($this->_params['exchange_rate']);

		//get sender details
		$sfname = @ mysql_real_escape_string($this->_params['sfirst_name']);
		$transferArray['senderFname'] = $sfname;
		$ssurname = @ mysql_real_escape_string($this->_params['ssur_name']);
		$transferArray['senderSurname'] = $ssurname;
		$semail = @ mysql_real_escape_string($this->_params['semail']);
		$transferArray['senderEmail'] = $semail;
		$sphone = @ mysql_real_escape_string($this->_params['sphone_number']);
		$transferArray['senderPhone'] = $sphone;
		$sidtype = @ mysql_real_escape_string($this->_params['sid_type_id']);
		if(empty($sidtype)){
			$sidtype = 0;
		}
		$transferArray['senderIdType'] = $sidtype;
		$sid = @ mysql_real_escape_string($this->_params['sid_no']);
		if(empty($sid)){
			$sid = 0;
		}
		$transferArray['senderId'] = $sid;
		//get receiver details
		$rfname = @ mysql_real_escape_string($this->_params['rfirst_name']);
		$transferArray['receiverFname'] = $rfname;
		$rsurname = @ mysql_real_escape_string($this->_params['rsur_name']);
		$transferArray['receiverSurname'] = $rsurname;
		$remail = @ mysql_real_escape_string($this->_params['remail']);
		$transferArray['receiverEmail'] = $remail;
		$rphone = @ mysql_real_escape_string($this->_params['rphone_number']);
		$transferArray['receiverPhone'] = $rphone;

		$raccountnumber = @ mysql_real_escape_string($this->_params['account_no']);

		$pass = '';
		if(!empty($password)){
			$pass = '******';
		}

		$code = '';
		if(!empty($passcode)){
			$code = '******';
		}

		$paramLog = "Transfer Creation Params ;Amount :$amount\currencyid : $currencyid\nCountry : $countryid\nCharge : $charge\nsfname : $sfname\nssurname : $ssurname\nAccount Number : $raccountnumber\n";
		$paramLog .= "semail : $semail\nsphone : $sphone\nsidtype : $sidtype\nsid : $sid\nrfname : $rfname\nrsurname : $rsurname\nremail : $remail\nrphone : $rphone\nReceiver Branch : $rbranchid\n";
		$paramLog .= "passcode : $code\npassword : $pass\nuserid : $userid\nsessionid : $sessionid\nExchange Rate : $xchangerate\nAmountAfterXchange : $amountAfterXchage\nCharge Currency : $chargecurr";
		writeToLogFile($paramLog);

		$accTransfer = array();
		//added by henry bbosa
		$branchTransaction = array();
		$branchTransaction['transaction_type_id'] =$transaction_type_id;
		$branchTransaction['branch_id'] = $branch_id;
		$branchTransaction['amount']=$amount;
		$branchTransaction['from_currency_id']=$fromCurrency;
		$branchTransaction['to_currency_id']=$currencyid;
		$branchTransaction['charge']=$charge;
		$branchTransaction['charge_currency_id']=$chargecurr;
		$branchTransaction['sender_name']=$ssurname;
		$branchTransaction['receiver_name']=$rsurname;
		$branchTransaction['date']= date('Y-m-d', time());

		//added by henry bbosa
		$balanceAmt = $amount;
		$accTransfer['amountAfterXchange'] = $amount;
		if($xchangerate > 0){
			$accTransfer['amountB4Xchange'] = $amount;
			$accTransfer['amountAfterXchange'] = $amountAfterXchage;
			//$balanceAmt = $amountAfterXchage;
			$accTransfer['currencyB4Xchange'] = $fromCurrency;
			$accTransfer['currencyAfterXchange'] = $currencyid;
			$accTransfer['exchangeRate'] = $xchangerate;
			$transferArray['amountAfterExchange'] = $amountAfterXchage;
			$transferArray['from_currency'] = $fromCurrency;
		}else{
			  $currencyid = $fromCurrency;
    		$transferArray['currency'] = $currencyid;
				$transferArray['amountAfterExchange'] = $amount;
				$transferArray['from_currency'] = $fromCurrency;

		}

		if($charge != 0){
			$transferArray['charge'] = $charge;
			$accTransfer['charge'] = $charge;
			if(intval($chargecurr)>0){
			    $accTransfer['chargeCurrency'] = $chargecurr;
					$transferArray['charge_currency_id'] = $chargecurr;
			}else{
			    $accTransfer['chargeCurrency'] = $fromCurrency;
					$transferArray['charge_currency_id'] = $fromCurrency;
			}
		}else{
			$charge = 0;
		}

		//get suspense account
		$fromAccount = $this->users->getSetting('SuspenseAccount');

		if($currencyid == 1){
			$transferArray['receiverAccountNumber'] = $raccountnumber;
			$fromAccount = $this->users->getSetting('ToChinaAccount');
		}

		$result['success'] = 0;

		$userObj = $this->_db->doSelect('users',array(),array("id"=>$userid),1,array());
		$branchId = 0;

		/*if($sessionid != $userObj->sessionId){
			$result['errormsg'] = "Sorry your transaction failed. Reason: Your login expired";
			return $result;
		}*/

		if($userObj != 0){
			$branchId = $userObj->branchId;
			$transferArray['fromBranch'] = $branchId;

			$passcode = @ $this->crypto->createPasswordHash($passcode, '$2a$');
			$transferArray['passcode'] = $passcode;

			if(!@ $this->crypto->verifyPasswordHash($password, $userObj->password)){
				$result['errormsg'] = "Sorry your transaction failed. Reason: Wrong Transaction Password.";
				return $result;
			}
		}else{
			$result['errormsg'] = "Sorry your transaction failed. Reason: System unavailable, contact Admin.";
			return $result;
		}

		if($branchId == $rbranchid){
			$result['errormsg'] = "Sorry your transaction failed. Reason: Desitination branch should be different from source branch";
			return $result;
		}

		//insert transfer
		$transferArray['status'] = 'Pending';
		$transferArray['sourceIpAddress'] = $this->users->get_client_ip_server();
		$transferId = $this->_db->doInsert('transfers',$transferArray);
		if($transferId>0){
			$trxnRef = str_pad($branchId, 3, '0', STR_PAD_LEFT).str_pad($countryid, 2, '0', STR_PAD_LEFT).$userid.$transferId;
			//update transfer table with trxn ref
			$flag = $this->_db->doUpdate('transfers',array('transferRef'=>$trxnRef),array('id'=>$transferId),$userid);
			if($flag == 1){
				//get Accounts
				//$recievingAcc = $this->_db->doSelect('accounts',array('id'),array("branchId"=>$branchId,'currency_id'=>$currencyid),1,array());
				$recievingAcc = $this->_db->doSelect('accounts',array('id'),array("branchId"=>$branchId,'currency_id'=>$fromCurrency),1,array());
				if($recievingAcc != 0){
					$toAccount = $recievingAcc->id;
				}else{
					$result['errormsg'] = "Sorry your transaction failed. Reason: Branch does not have an account for this currency";
					return $result;
				}

				$accTransfer2 = array('type'=>'transfer','amount'=>$amount,'charge'=>$charge,'fromId'=>$fromAccount,'toId'=>$toAccount,'transferRef'=>$trxnRef,'userId'=>$userid);
				$accTransfer = array_merge($accTransfer,$accTransfer2);
				$this->_db->doInsert('account_transfers',$accTransfer);
				$this->_db->doInsert('branch_transaction',$branchTransaction);

				//update balances with transfer amount
				$this->users->createBalances($branchId,$toAccount,intval($balanceAmt));
				$newAmt = intval($balanceAmt) * -1;
				$this->users->createBalances(0,$fromAccount,$newAmt);

				//update balances with transaction charges
				$chargeAcc = $this->_db->doSelect('accounts',array('id'),array("branchId"=>$branchId,'currency_id'=>$chargecurr),1,array());
				$acc = 0;
				if($chargeAcc != 0){
					$acc = $chargeAcc->id;
				}

				$newCharge = intval($charge);
				$this->users->createBalances($branchId,$acc,$newCharge);
				//writeToLogFile("NEW CHARGE : $newCharge\n");
				if($newCharge > 0){
					$accTransfer = array('type'=>'transfer','amount'=>$newCharge,'charge'=>0,'fromId'=>$fromAccount,'toId'=>$acc,'transferRef'=>0,'userId'=>$userid,'amountAfterXchange'=>$newCharge,'amountB4Xchange'=>$newCharge);
					$this->_db->doInsert('account_transfers',$accTransfer);
				}

				$transferRecords = $this->_db->doSelect('transfers',array(),array("fromBranch"=>$branchId,'toBranch'=>$branchId,'destinationCountryId'=>$countryid),0,array());
				$data = array();
				$data['msg'] = "Your request was successfully processed.\nTransaction Ref : $trxnRef";
				//$data['transfers'] = $transferRecords;

				$result['success'] = 1;
				$result['data'] = $trxnRef;
				return $result;
			}
		}

		$result['errormsg'] = "Sorry your transaction failed. Reason: System unavailable, contact Admin.";
		return $result;
	}

	public function directdebitcashoutAction(){

		$sender = @ mysql_real_escape_string($this->_params['sender']);
		$reciever = @ mysql_real_escape_string($this->_params['reciever']);
		$amount = @ mysql_real_escape_string($this->_params['amount']);
		$currency = @ mysql_real_escape_string($this->_params['currency_id']);
		$accountNumber = @ mysql_real_escape_string($this->_params['account_number']);
		$userid = @ mysql_real_escape_string($this->_params['user_id']);
		$sessionid = @ mysql_real_escape_string($this->_params['session_id']);
		$branch_id = @ mysql_real_escape_string($this->_params['branch_id']);

		$result['success'] = 0;

		writeToLogFile("Sender: $sender\nReceiver : $reciever\nAmount : $amount\nCurrency : $currency\nAccount : $accountNumber\nUserId : $userid\nSession ID : $sessionid\nBranchId : $branch_id");

		$userObj = $this->_db->doSelect('users',array('sessionId','branchId'),array("id"=>$userid),1,array());
		/*if($sessionid != $userObj->sessionId){
			$result['errormsg'] = "Sorry your transaction failed. Reason: Your login expired";
			return $result;
		}*/

		$branchId = $userObj->branchId;
		//get suspense account
		$toAccount = $this->users->getSetting('SuspenseAccount');

		$fromAccountObj = $this->_db->doSelect('accounts',array('id'),array("branchId"=>$branchId,'currency_id'=>$currency),1,array());

		$transferId = $this->_db->doInsert('transfers',array("fromBranch"=>0,"toBranch"=>$branchId,"amount"=>$amount,"currency"=>$currency,"userIdDestination"=>$userid,"status"=>'Success',"senderFname"=>$sender,"receiverFname"=>$reciever,"receiverAccountNumber"=>$accountNumber,"destinationIpAddress"=>$this->users->get_client_ip_server()));
		if($transferId > 0){
			$flag = $this->_db->doInsert('account_transfers',array("type"=>'transfer',"amount"=>$amount,"amountB4Xchange"=>$amount,"amountAfterXchange"=>$amount,"fromId"=>$fromAccountObj->id,"toId"=>$toAccount,"userId"=>$userid,"createdBy"=>$userid));
			if($flag >0){
				if($flag >0){

					//update balances
					$newAmt = intval($amount);
					$this->users->createBalances(0,$toAccount,$newAmt);
					$newAmt =  $newAmt * -1;
					$this->users->createBalances($branchId,$fromAccountObj->id,$newAmt);

					$dir_cashout_branch_transaction = array();
					$dir_cashout_branch_transaction['branch_id']=$branch_id;
					$dir_cashout_branch_transaction['transaction_type_id']=3;
					$dir_cashout_branch_transaction['amount']=$amount;
					$dir_cashout_branch_transaction['currency_id']=$currency;
					$dir_cashout_branch_transaction['sender_name']=$sender;
					$dir_cashout_branch_transaction['receiver_name']=$reciever;
					$dir_cashout_branch_transaction['account_number']=$accountNumber;
					$dir_cashout_branch_transaction['date']=date('Y-m-d', time());
					$branch_dir_cashout_insertion = $this->_db->doInsert('branch_transaction',$dir_cashout_branch_transaction);



					$result['success'] = 1;
					$result['data'] = 'Direct transfer cashed out successfully';
					return $result;
				}
			}
		}

		$result['errormsg'] = "Sorry your transaction failed. Reason: System unavailable, contact Admin.";
		return $result;
	}

	public function transfercashoutAction(){
		$passcode = @ mysql_real_escape_string($this->_params['user_pass_code']);
		$trxnRef = @ mysql_real_escape_string($this->_params['transaction_reference']);
		//$trxnPass = @ mysql_real_escape_string($this->_params['transaction_password']);
		$userid = @ mysql_real_escape_string($this->_params['user_id']);
		$sessionid = @ mysql_real_escape_string($this->_params['session_id']);

		$code = '';
		if(!empty($passcode)){
			$code = '******';
		}

		$result['success'] = 0;

		writeToLogFile("Passcode : $code\nTransaction Ref : $trxnRef\nUserID : $userid\nSession ID : $sessionid\n");

		$userObj = $this->_db->doSelect('users',array('sessionId'),array("id"=>$userid),1,array());
		/*if($sessionid != $userObj->sessionId){
			$result['errormsg'] = "Sorry your transaction failed. Reason: Your login expired";
			return $result;
		}*/

		$transferObj = $this->_db->doSelect('transfers',array(),array("transferRef"=>$trxnRef),1,array());

		if($transferObj != 0){
			$crypt = new PasswordLib\PasswordLib;
			if (!@ $this->crypto->verifyPasswordHash($passcode, $transferObj->passcode)){
				$result['errormsg'] = "Sorry your request failed. Reason : Invalid Passcode";
				return $result;
			}

			$countryObj = $this->_db->doSelect('countries',array('name'),array("id"=>$transferObj->destinationCountryId),1,array());
			$branchObj = $this->_db->doSelect('branches',array('name'),array("id"=>$transferObj->fromBranch),1,array());
			$currencyObj = $this->_db->doSelect('currencies',array('name'),array("id"=>$transferObj->currency),1,array());

			$transferObj->fromBranchName = $branchObj->name;
			$transferObj->fromCountry = $countryObj->name;
			$transferObj->currencyName = $currencyObj->name;

			$result['success'] = 1;
			$result['data'] = $transferObj;
			return $result;

		}else{
			$result['errormsg'] = "Sorry your request failed. Reason : Transaction with reference does not exist";
			return $result;
		}
	}

	public function completetransfercashoutAction(){

		$trxnRef = @ mysql_real_escape_string($this->_params['transaction_reference']);
		$trxnPass = @ mysql_real_escape_string($this->_params['transaction_password']);
		$idtype = @ mysql_real_escape_string($this->_params['rid_type_id']);
		$idnumber = @ mysql_real_escape_string($this->_params['rid_no']);
		$branch_id = @ mysql_real_escape_string($this->_params['branch_id']);
		//$chargeCurr = @ mysql_real_escape_string($this->_params['rcharge_currency']);
		$userid = @ mysql_real_escape_string($this->_params['user_id']);
		$sessionid = @ mysql_real_escape_string($this->_params['session_id']);

		$pass = '';
		if(!empty($trxnPass)){
			$pass = '******';
		}

		$result['success'] = 0;

		writeToLogFile("Password : $pass\nTransaction Ref : $trxnRef\nUserID : $userid\nSession ID : $sessionid\nID Number : $idnumber\nID Type : $idtype\nCharge : $charge\nCharge Currency : $chargeCurr\n");
		//authenticate user
		$userObj = $this->_db->doSelect('users',array(),array("id"=>$userid),1,array());
		/*if($sessionid != $userObj->sessionId){
			$result['errormsg'] = "Sorry your transaction failed. Reason: Your login expired";
			return $result;
		}*/

		if(empty($idtype)){
			$idtype = 0;
		}

		$receiverBranchId = 0;
		if($userObj != 0){
			$receiverBranchId = $userObj->branchId;

			if(!@ $this->crypto->verifyPasswordHash($trxnPass, $userObj->password)){
				$result['errormsg'] = "Sorry your transaction failed. Reason: Wrong Transaction Password.";
				return $result;
			}
		}else{
			$result['errormsg'] = "Sorry your transaction failed. Reason: System unavailable, contact Admin.";
			return $result;
		}
		//get transfer record
		$tansferObj = $this->_db->doSelect('transfers',array(),array("transferRef"=>$trxnRef,'status'=>'Pending'),1,array());
		$senderBranchid = $tansferObj->fromBranch;
		//prevent cashing out from sending branch
		if($senderBranchid == $receiverBranchId){
			$result['errormsg'] = "Sorry your transaction failed. Reason: You can not cashout from sending branch.";
			return $result;
		}

		//prevent sending user from cashing out
		if($userid == $tansferObj->userIdSource){
			$result['errormsg'] = "Sorry your transaction failed. Reason: Sending Agent should not be cashing out Agent.";
			return $result;
		}

		if($tansferObj != 0){
			$receiverIP = $this->users->get_client_ip_server();
			$now = date('Y-m-d H:m:s');

			$completeArray = array('destinationIpAddress'=>$receiverIP,'receiverIdType'=>$idtype,'receiverId'=>$idnumber,'toBranch'=>$receiverBranchId,'Status'=>'Success','userIdDestination'=>$userid,'withdrawTs'=>$now);
			//update transfer record
			$flag = $this->_db->doUpdate('transfers',$completeArray,array("transferRef"=>$trxnRef),$userid);

			if($flag == 1){

				$fromAccount = $this->users->getSetting('SuspenseAccount');
				if($tansferObj->currency == 1){
					$fromAccount = $this->users->getSetting('ToChinaAccount');
				}

				$tansferRecord = $this->_db->doSelect('account_transfers',array(),array("transferRef"=>$trxnRef),1,array());

				$toAccountObj = $this->_db->doSelect('accounts',array('id'),array("branchId"=>$receiverBranchId,'currency_id'=>$tansferObj->currency),1,array());
				if($toAccountObj == 0){
					$result['errormsg'] = "Sorry your transaction failed. Reason: Branch does not have an account for this currency";
					return $result;
				}
				$transferAmount = $tansferRecord->amountAfterXchange;
				$amtBeforeXchange = $tansferRecord->amountB4Xchange;
				writeToLogFile("Transfer Amount : $transferAmount\n");
				$tansferRecord->toId = $fromAccount;
				$tansferRecord->fromId = $toAccountObj->id;
				$tansferRecord->userId = $userid;
				$tansferRecord->chargeCurrency = 0;
				$tansferRecord->createdBy = $userid;
				unset($tansferRecord->id);
				unset($tansferRecord->ts);
				unset($tansferRecord->currencyB4Xchange);
				unset($tansferRecord->currencyAfterXchange);

				$flag = $this->_db->doInsert('account_transfers',$tansferRecord);
				if($flag >0){

					//update balances
					$this->users->createBalances(0,$fromAccount,$amtBeforeXchange);
					$newAmt =  $transferAmount * -1;
					$this->users->createBalances($receiverBranchId,$toAccountObj->id,$newAmt);

					$result['success'] = 1;

					$trans_Obj = $this->_db->doSelect('transfers',array(),array("transferRef"=>$trxnRef,'status'=>'Success'),1,array());
					$cashout_branch_transaction = array();
					$cashout_branch_transaction['branch_id']= $branch_id;
					$cashout_branch_transaction['user_id']= $userid;
					$cashout_branch_transaction['transaction_type_id']=2;
					$cashout_branch_transaction['amount']=$trans_Obj->amountAfterExchange;
					$cashout_branch_transaction['currency_id']=$trans_Obj->currency;
					$cashout_branch_transaction['sender_name']=$trans_Obj->senderSurname;
					$cashout_branch_transaction['receiver_name']=$trans_Obj->receiverSurname;
					$cashout_branch_transaction['date']= date('Y-m-d', time());


					$branch_trans_insertion = $this->_db->doInsert('branch_transaction',$cashout_branch_transaction);
					writeToLogFile("Transaction Insertion:\nObject is : $branch_trans_insertion");
					$result['data'] = 'Transfer cashed out successfully';

					return $result;
				}else{
					$result['errormsg'] = "Sorry your transaction failed. Reason : system unavailable. contact admin";
					return $result;
				}
			}
		}else{
			$result['errormsg'] = "Sorry your transaction failed. Reason: Transaction does not exist";
			return $result;
		}

	}

	public function creditrmbAction(){
		$amount = @ mysql_real_escape_string($this->_params['amount']);
		$user1 = @ mysql_real_escape_string($this->_params['user_name1']);
		$password1 = @ mysql_real_escape_string($this->_params['password1']);
		$user2 = @ mysql_real_escape_string($this->_params['user_name2']);
		$password2 = @ mysql_real_escape_string($this->_params['password2']);
		$userid = @ mysql_real_escape_string($this->_params['user_id']);
		$sessionid = @ mysql_real_escape_string($this->_params['session_id']);

		$count = 0;
		$createdBy = '';

		//$pass1 = '',$pass2 = '';
		if(!empty($password1)){
			$pass1 = '******';
		}

		if(!empty($password2)){
			$pass2= '******';
		}

		writeToLogFile("Credit RMB Account Params:\nAmount : $amount\nUsername1 : $user1\nPassword1 : $pass1\nUsername2 : $user2\nnPassword1 : $pass2\nUserid : $userid\nSessionID : $sessionid\n");

		$result['success'] = 0;

		if(!empty($user1)){
			$userObj = $this->_db->doSelect('users',array(),array("userName"=>$user1),1,array());
			/*if($sessionid != $userObj->sessionId){
				$result['errormsg'] = "Sorry your transaction failed. Reason: Your login expired";
				return $result;
			}*/
			$createdBy = $userObj->id;

			if(@ $this->crypto->verifyPasswordHash($password1, $userObj->password) && $userObj->userTypeId == 1){
				$count++;
			}
		}

		if(!empty($user2)){
			$userObj = $this->_db->doSelect('users',array(),array("userName"=>$user2),1,array());
			if(@ $this->crypto->verifyPasswordHash($password2, $userObj->password) && $userObj->userTypeId == 1){
				$count++;
				$createdBy .= '-'.$userObj->id;
			}

		}

		if($count > 0){
			$toid = $this->users->getSetting('RNBAccount');
			$id = $this->_db->doInsert('account_transfers',array('type'=>'deposit','amount'=>$amount,'createdBy'=>$createdBy,'toId'=>$toid));
			if($id > 0){
				$this->users->createBalances(0,$toid,intval($amount));
				$result['success'] = 1;
				$result['data'] = "Your request was processed successfully";
				return $result;
			}

			$result['errormsg'] = "Sorry your request failed. Reason : System unavailable at the moment, contact admin";
			return $result;
		}

		$result['errormsg'] = "Sorry your request failed. Reason : Wrong password or one of the users is not permitted to perform this action";
		return $result;
	}

	public function credittransferaccountAction(){

		$amount = @ mysql_real_escape_string($this->_params['amount']);
		$exhangerate = @ mysql_real_escape_string($this->_params['exchange_rate']);
		$accountid = @ mysql_real_escape_string($this->_params['transfer_account_id']);
		$amountafterxchange = @ mysql_real_escape_string($this->_params['amount_from_rate']);
		$password = @ mysql_real_escape_string($this->_params['transaction_password']);
		$userid = @ mysql_real_escape_string($this->_params['user_id']);
		$sessionid = @ mysql_real_escape_string($this->_params['session_id']);
		$bran_id = @ mysql_real_escape_string($this->_params['branch_id']);

		$credittransferaccount_transaction = array();

		$credittransferaccount_transaction['amount'] =$amount;
		$credittransferaccount_transaction['exchange_rate'] =$exhangerate;
		$credittransferaccount_transaction['account_id'] =$accountid;
		$credittransferaccount_transaction['amount_from_rate'] =$amountafterxchange;
		$credittransferaccount_transaction['user_id'] = $userid;
		$credittransferaccount_transaction['branch_id'] = $bran_id;
		$credittransferaccount_transaction['transaction_type_id'] =8;





		$count = 0;
		$createdBy = '';

		$pass = '';
		if(!empty($password)){
			$pass = '******';
		}

		if(empty($exhangerate)){
			$exhangerate = 0;
		}

		if(empty($amountafterxchange)){
			$amountafterxchange = 0;
		}else{

		}

		writeToLogFile("Credit Transfer Account Params:\nAmount : $amount\nExchange Rate : $exhangerate\nAccountId : $accountid\nAmount After Xchange : $amountafterxchange\nPassword : $pass\nUserid : $userid\nSessionID : $sessionid\n");

		$result['success'] = 0;

		$userObj = $this->_db->doSelect('users',array(),array("id"=>$userid),1,array());
		/*if($sessionid != $userObj->sessionId){
			$result['errormsg'] = "Sorry your transaction failed. Reason: Your login expired";
			return $result;
		}*/

		if($userObj->userTypeId != 1 && $userObj->userTypeId != 2){
			$result['errormsg'] = "Sorry your request failed. Reason : You are not permitted to perform this action";
			return $result;
		}

		$fromId = $this->users->getSetting('RNBAccount');
		$id = $this->_db->doInsert('account_transfers',array('type'=>'transfer','amount'=>$amount,'createdBy'=>$userid,'toId'=>$accountid,'fromId'=>$fromId,'userId'=>$userid,'exchangeRate'=>$exhangerate,'amountAfterXchange'=>$amountafterxchange));
		if($id > 0){
			$accObj = $this->_db->doSelect('accounts',array(),array("id"=>$accountid),1,array());
			$this->users->createBalances($accObj->branchId,$accountid,intval($amountafterxchange));
			$this->_db->doInsert('branch_transaction',$credittransferaccount_transaction);
			$result['success'] = 1;
			$result['data'] = "Your request was processed successfully";
			return $result;
		}

		$result['errormsg'] = "Sorry your request failed. Reason : System unavailable at the moment, contact admin";
		return $result;
	}

	public function sendmoneyAction(){

		$amount = @ mysql_real_escape_string($this->_params['amount']);
		$charge = @ mysql_real_escape_string($this->_params['charge']);
		$fromaccountid = @ mysql_real_escape_string($this->_params['from_account_id']);
		$toaccountid = @ mysql_real_escape_string($this->_params['to_account_id']);
		$password = @ mysql_real_escape_string($this->_params['transaction_password']);
		$userid = @ mysql_real_escape_string($this->_params['user_id']);
		$sessionid = @ mysql_real_escape_string($this->_params['session_id']);
		$branch_id = @ mysql_real_escape_string($this->_params['branch_id']);




		$count = 0;
		$createdBy = '';

		$pass = '';
		if(!empty($password)){
			$pass = '******';
		}

		if(empty($exhangerate)){
			$exhangerate = 0;
		}

		if(empty($amountafterxchange)){
			$amountafterxchange = 0;
		}else{

		}

		writeToLogFile("Send Money Params:\nAmount : $amount\nCharge : $charge\nFromAccountId : $fromaccountid\nToAccountId : $toaccountid\nPassword : $pass\nUserid : $userid\nSessionID : $sessionid\n");

		$result['success'] = 0;

		/*$userObj = $this->_db->doSelect('users',array(),array("id"=>$userid),1,array());
		if($sessionid != $userObj->sessionId){
			$result['errormsg'] = "Sorry your transaction failed. Reason: Your login expired";
			return $result;
		}*/

		/*if($userObj->userTypeId != 1 && $userObj->userTypeId != 2){
			$result['errormsg'] = "Sorry your request failed. Reason : You are not permitted to perform this action";
			return $result;
		}*/

		$accObj = $this->_db->doSelect('accounts',array(),array("id"=>$fromaccountid),1,array());
		$fromBranch = $accObj->branchId;
		$from_currency_id = $accObj->currency_id;

		$BranchObj = $this->_db->doSelect('branches',array(),array("id"=>$branch_id),1,array());


		$accObj = $this->_db->doSelect('accounts',array(),array("id"=>$toaccountid),1,array());
		$toBranch = $accObj->branchId;
		$toBranchObj = $this->_db->doSelect('branches',array(),array("id"=>$toBranch),1,array());

		$to_currency_id = $accObj->currency_id;
		$total_amount = intval($amount) + intval($charge);

		$id = $this->_db->doInsert('account_transfers',array('type'=>'transfer','amount'=>$amount,'createdBy'=>$userid,'toId'=>$toaccountid,'fromId'=>$fromaccountid,'userId'=>$userid,'charge'=>$charge));
		if($id > 0){
			//creadit toAccount
			$newAmount = intval($amount);//-intval($charge);
			$this->users->createBalances($toBranch,$toaccountid,$newAmount);
			//debit from account
			$newAmount = intval($total_amount)*-1;
			$this->users->createBalances($fromBranch,$fromaccountid,$newAmount);

			$send_branch_transaction = array();
			$send_branch_transaction['branch_id'] =$branch_id;
			$send_branch_transaction['transaction_type_id'] =5;
			$send_branch_transaction['amount'] =$amount;
			$send_branch_transaction['to_branch_id'] =$toBranch;
			$send_branch_transaction['branch_name'] =$BranchObj->name;
			$send_branch_transaction['to_branch_name'] =$toBranchObj->name;
			$send_branch_transaction['user_id'] =$userid;
			$send_branch_transaction['from_currency_id'] =$from_currency_id;
			$send_branch_transaction['to_currency_id'] =$to_currency_id;
			$send_branch_transaction['from_account_id'] =$from_account_id;
			$send_branch_transaction['to_account_id'] =$toaccountid;
			$send_branch_transaction['charge'] =$charge;
			$send_branch_transaction['date'] =date('Y-m-d', time());

			$branch_send_insertion = $this->_db->doInsert('branch_transaction',$send_branch_transaction);

			$result['success'] = 1;
			$result['data'] = "Your request was processed successfully";
			return $result;
		}

		$result['errormsg'] = "Sorry your request failed. Reason : System unavailable at the moment, contact admin";
		return $result;
	}

	public function createexpenseAction(){

		$amount = @ mysql_real_escape_string($this->_params['amount']);
		$currency = @ mysql_real_escape_string($this->_params['currency_id']);
		$detail = @ mysql_real_escape_string($this->_params['details']);
		$receiver = @ mysql_real_escape_string($this->_params['receiver']);
		$userid = @ mysql_real_escape_string($this->_params['user_id']);
		$sessionid = @ mysql_real_escape_string($this->_params['session_id']);
		$branch_id =  @ mysql_real_escape_string($this->_params['branch_id']);
		$exp_toggle =  @ mysql_real_escape_string($this->_params['exp_toggle']);
		$charge =  @ mysql_real_escape_string($this->_params['charge']);


		writeToLogFile("UserID : $userid\nSessionId : $sessionid\nCurrency ID : $currency\nReason : $detail\nAmount : $amount\n");

		$result['success'] = 0;

		$userObj = $this->_db->doSelect('users',array('sessionId','branchId'),array("id"=>$userid),1,array());
		/*if($sessionid != $userObj->sessionId){
			$result['errormsg'] = "Sorry your transaction failed. Reason: Your login expired";
			return $result;
		}*/
		$total_amount = $amount+$charge;

		$expenseId = $this->_db->doInsert('expenses',array('amount'=>$total_amount,'currencyId'=>$currency,'description'=>$detail,'createdBy'=>$userid,'branchId'=>$userObj->branchId));
		if($expenseId>0){
			$accountObj = $this->_db->doSelect('accounts',array('id'),array("branchId"=>$userObj->branchId,'currency_id'=>$currency),1,array());

			$accTransfer = array('type'=>'transfer','amount'=>$total_amount,'charge'=>0,'fromId'=>$accountObj->id,'toId'=>0,'transferRef'=>0,'userId'=>$userid,'amountAfterXchange'=>$total_amount,'amountB4Xchange'=>$total_amount);
			$this->_db->doInsert('account_transfers',$accTransfer);

			$newAmount = intval($total_amount) * -1;
			$this->users->createBalances($userObj->branchId,$accountObj->id,$newAmount);

			$expense_branch_transaction = array();
			$expense_branch_transaction['branch_id'] =$branch_id;

			$expense_branch_transaction['amount'] =$amount;
			$expense_branch_transaction['currency_id'] =$currency;

			if($exp_toggle == 1){
				$expense_branch_transaction['transaction_type_id'] = 6;
				$expense_branch_transaction['charge']= $charge;
				$expense_branch_transaction['charge_currency_id']= $currency;
				$expense_branch_transaction['receiver_name']= $receiver;
			}else{
				$expense_branch_transaction['transaction_type_id'] = 4;
			}
			$expense_branch_transaction['details'] =$detail;
			$expense_branch_transaction['user_id'] =$userid;
			$expense_branch_transaction['date'] =date('Y-m-d', time());

			$branch_expense_insertion = $this->_db->doInsert('branch_transaction',$expense_branch_transaction);


			$result['success'] = 1;

			$result['data'] = "Your request was processed successfuly";
			return $result;
		}

		$result['errormsg'] = "Sorry your request was not processed. Contact System Admin";
		return $result;
	}

	public function getallaccountbalancesAction(){
		$userid = @ mysql_real_escape_string($this->_params['user_id']);
		$sessionid = @ mysql_real_escape_string($this->_params['session_id']);
		$branchid = @ mysql_real_escape_string($this->_params['branch_id']);
		writeToLogFile("UserID : $userid\nSessionId : $sessionid\nBranch ID : $branchid\n");

		$userObj = $this->_db->doSelect('users',array('sessionId'),array("id"=>$userid),1,array());
		/*if($sessionid != $userObj->sessionId){
			$result['errormsg'] = "Sorry your transaction failed. Reason: Your login expired";
			return $result;
		}*/

		$accountsArray = array();
		$accounts = $this->_db->doSelect('accounts',array('name','id'),array('active'=>1,'branchId'=>$branchid),1,array());
		$branchObj = $this->_db->doSelect('branches',array('name'),array('id'=>$branchid),1,array());
		$countAcc = count($accounts);
		if($countAcc > 0){
			if($countAcc == 1){
				$accounts->balance = $this->_db->getAccountBalance($accounts->id);
				$accountsArray[] = $accounts;
			}else{
				for($i = 0; $i<$countAcc;$i++){
					$acc = $accounts[$i];
					$acc->balance = $this->_db->getAccountBalance($acc->id);
					$accountsArray[] = $acc;
				}
			}
		}

		if(!$accountsArray){
			$result['success'] = 0;
			$result['errormsg'] = "unable to process request. Contact admin";
			return $result;
		}

		$result['success'] = 1;
		$result['data'] = $accountsArray;
		$result['branchName'] = $branchObj->name;
		return $result;
	}

	public function getbalancesAction(){
		$userid = @ mysql_real_escape_string($this->_params['user_id']);
		$branchid = @ mysql_real_escape_string($this->_params['branch_id']);
		$sessionid = @ mysql_real_escape_string($this->_params['session_id']);
		$date = @ mysql_real_escape_string($this->_params['my_date']);
		writeToLogFile("UserID : $userid\nSessionId : $sessionid\nBranch ID : $branchid\nDate : $date\n");

		//$result['success'] = 0;

		$userObj = $this->_db->doSelect('users',array('sessionId','branchId','userTypeId'),array("id"=>$userid),1,array());
		/*if($sessionid != $userObj->sessionId){
			$result['errormsg'] = "Sorry your transaction failed. Reason: Your login expired";
			return $result;
		}*/

		if(empty($date)){
			$date =  date('Y-m-d');
		}

		$where = array('date'=>$date);
		$data = array();

		if(!empty($branchid)){
			$where['branchId'] = $branchid;
		}

		if($userObj->userTypeId != 2){
			$where['branchId'] = $userObj->branchId;
		}

		$balances = $this->_db->doSelect('balances',array('closingBalance','openingBalance','accountId','branchId','date'),$where,1,array());
		if($balances != 0){
			if(is_array($balances)){
				$length = count($balances);
				for($i=0;$i<$length;$i++){
					$balance = $balances[$i];
					$account = $this->_db->doSelect('accounts',array('name'),array('id'=>$balance->accountId),1,array());
					$balance->accountName = $account->name;
					$branches = $this->_db->doSelect('branches',array('name'),array('id'=>$balance->branchId),1,array());
					$balance->branchName = $branches->name;
					$data[] = $balance;
				}
			}else{
				$account = $this->_db->doSelect('accounts',array('name'),array('id'=>$balances->accountId),1,array());
				$balances->accountName = $account->name;
				$branches = $this->_db->doSelect('branches',array('name'),array('id'=>$balances->branchId),1,array());
				$balances->branchName = $branches->name;
				$data[] = $balances;
			}
		}
		$result['success'] = 1;
		$result['data'] = $data;
		return $result;
	}

	public function getexchangetrxnsAction(){
		$userid = @ mysql_real_escape_string($this->_params['user_id']);
		$sessionid = @ mysql_real_escape_string($this->_params['session_id']);
		$date = @ mysql_real_escape_string($this->_params['my_date']);
		writeToLogFile("UserID : $userid\nSessionId : $sessionid\n");

		$result['success'] = 0;

		$userObj = $this->_db->doSelect('users',array('sessionId','branchId','userTypeId'),array("id"=>$userid),1,array());
		/*if($sessionid != $userObj->sessionId){
			$result['errormsg'] = "Sorry your transaction failed. Reason: Your login expired";
			return $result;
		}*/

		if(empty($date)){
			$date =  date('Y-m-d');
		}

		$where = array();
		$data = array();
		if($userObj->userTypeId != 2){
			$where['branchId'] = $userObj->branchId;
		}

		$exchanges = $this->_db->doSelect('currency_exchange',array(),$where,1,array());
		if($exchanges != 0){
			$length = count($exchanges);
			for($i=0;$i<$length;$i++){
				$row = $exchanges[$i];
				$currency = $this->_db->doSelect('currencies',array('name'),array('id'=>$row->fromCurrencyId),1,array());
				$row['originalCurrency'] = $currency->name;
				$currency = $this->_db->doSelect('currencies',array('name'),array('id'=>$row->toCurrencyId),1,array());
				$row['toCurrency'] = $currency->name;

				$user = $this->_db->doSelect('users',array('userName'),array('id'=>$row->userId),1,array());
				$row['agent'] = $user->userName;
				$data[] = $row;
			}
		}
		$result['success'] = 1;
		$result['data'] = $data;
		return $result;
	}
}

?>
