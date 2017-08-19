<?php

error_reporting(1);

class Users{

	private $_params;
	private $_db;

	public function __construct($params,$db){
		$this->_params = $params;
		$this->_db = $db;
		writeToLogFile("Class constructed\n");
	}

	public function loginAction(){
		$username = @ mysql_real_escape_string($this->_params['username']);
		$password = @ mysql_real_escape_string($this->_params['password']);
		$pass = $password;
		if(!empty($password)){
			$pass = '******';
		}
		writeToLogFile("Login Params ;Username :$username\nPassword : $pass\n");

		$where = array("userName"=>$username);
		$userObj = $this->_db->doSelect('users',array('id','firstName','surname','userName','userTypeId','sessionId','password','active','branchId'),$where,1,array());

		if($userObj != 0){
			//Authenticate user
			$crypt = new PasswordLib\PasswordLib;
			if (!@ $crypt->verifyPasswordHash($password, $userObj->password)){
				$result['success'] = 0;
				$result['errormsg'] = "Login Failed. Wrong Password.";
				return $result;
			}
			//is user active
			if($userObj->active == 0){
				$result['success'] = 0;
				$result['errormsg'] = "Login Failed. Account Deactivated, contact system admin";
				return $result;
			}
			//add user rolls
			$userId = $userObj->id;
			//$where = array("id"=>$userObj->permissionId);
			//$userObj->rolls = $this->_db->doSelect('permissions',array(),$where,1,array());
			unset($userObj->password);
			unset($userObj->active);

			$nToken = $this->getToken(32);
			$userObj->sessionId = $nToken;

			$branch = $this->_db->doSelect('branches',array('name'),array('id'=>$userObj->branchId),1,array());
			$userObj->branchName = $branch->name;

			$updateObj = array("sessionId"=>$nToken,"ipAddress"=>$this->get_client_ip_server());
			$where = array("id"=>$userId);
			$this->_db->doUpdate('users',$updateObj,$where,0);

			$result['success'] = 1;
			$result['data'] = $userObj;
			return $result;
		}

		$result['success'] = 0;
		$result['errormsg'] = "Login Failed. Wrong Username.";
		return $result;
	}

	public function logoutAction(){
		
		$userid = @ mysql_real_escape_string($this->_params['user_id']);
		$sessionid = @ mysql_real_escape_string($this->_params['session_id']);
		writeToLogFile("Logout Params ;Session ID :$sessionid\User ID : $userid\n");

		$updateObj = array("sessionId"=>'',"ipAddress"=>$this->get_client_ip_server());
		$where = array("id"=>$userid);
		$flag = $this->_db->doUpdate('users',$updateObj,$where,0);

		$result['success'] = 1;
		return $result;
	}

	public function addcountryAction(){
		$name = @ mysql_real_escape_string($this->_params['name']);
		$userid = @ mysql_real_escape_string($this->_params['user_id']);
		$sessionid = @ mysql_real_escape_string($this->_params['session_id']);
		writeToLogFile("Add Country Params ;Name :$name\User ID : $userid\n");

		$result['success'] = 0;

		$userObj = $this->_db->doSelect('users',array('sessionId'),array("id"=>$userid),1,array());
		if($sessionid != $userObj->sessionId){
			$result['errormsg'] = "Sorry your transaction failed. Reason: Your login expired";
			return $result;
		}

		$name = strtoupper($name);
		$cObj = $this->_db->doSelect('countries',array(),array('name'=>$name),1,array());
		if($cObj == 0){
			$id = $this->_db->doInsert('countries',array('name'=>$name,'createdBy'=>$userid));
			if($id > 0){
				$msg = "You have successfully created a country.";
				$data = array();
				$countries = $this->_db->doSelect('countries',array(),array('active'=>1),1,array('id'),'DESC',10);
				$data['msg'] = $msg;
				$data['countries'] = $countries;// return the most recent added countries

				$result['success'] = 1;
				$result['data'] = $data;
				return $result;
			}
			$result['errormsg'] = "Sorry, your request failed. Reason : System currently unavailable. Please try again later.";
			return $result;
		}
		$result['errormsg'] = "Sorry, your request failed. Reason : Country already exists.";
		return $result;
	}

	public function addcurrencyAction(){
		$name = @ mysql_real_escape_string($this->_params['name']);
		$userid = @ mysql_real_escape_string($this->_params['user_id']);
		$symbol = @ mysql_real_escape_string($this->_params['symbol']);
		$sessionid = @ mysql_real_escape_string($this->_params['session_id']);
		writeToLogFile("Add Currency Params ;Name :$name\User ID : $userid\nSession ID : $sessionid\n");

		$result['success'] = 0;

		$userObj = $this->_db->doSelect('users',array('sessionId'),array("id"=>$userid),1,array());
		if($sessionid != $userObj->sessionId){
			$result['errormsg'] = "Sorry your transaction failed. Reason: Your login expired";
			return $result;
		}

		$name = strtoupper($name);
		$cObj = $this->_db->doSelect('currencies',array(),array('name'=>$name),1,array());
		if($cObj == 0){
			$id = $this->_db->doInsert('currencies',array('name'=>$name,'symbol'=>$symbol,'createdBy'=>$userid));
			if($id > 0){
				$msg = "You have successfully created a currency.";
				$data = array();
				$currencies = $this->_db->doSelect('currencies',array(),array('active'=>1),1,array('id'),'DESC',10);
				$data['msg'] = $msg;
				$data['currencies'] = $currencies;// return the most recent added currencies

				$result['success'] = 1;
				$result['data'] = $data;
				return $result;
			}
			$result['errormsg'] = "Sorry, your request failed. Reason : System currently unavailable. Contact Admin.";
			return $result;
		}
		$result['errormsg'] = "Sorry, your request failed. Reason : Currency already exists.";
		return $result;
	}

	public function addaccountAction(){
		$name = @ mysql_real_escape_string($this->_params['name']);
		$type = @ mysql_real_escape_string($this->_params['account_type_id']);
		$currencyId = @ mysql_real_escape_string($this->_params['currency_id']);
		$userid = @ mysql_real_escape_string($this->_params['user_id']);
		$branchid = @ mysql_real_escape_string($this->_params['branch_id']);
		$sessionid = @ mysql_real_escape_string($this->_params['session_id']);
		writeToLogFile("Add Account Params ;Name :$name\User ID : $userid\nType : $type\nCurrency : $currencyId\nBranch ID : $branchid\nSession ID : $sessionid\n");

		$result['success'] = 0;

		$userObj = $this->_db->doSelect('users',array('sessionId'),array("id"=>$userid),1,array());
		if($sessionid != $userObj->sessionId){
			$result['errormsg'] = "Sorry your transaction failed. Reason: Your login expired";
			return $result;
		}

		$name = strtoupper($name);
		$cObj = $this->_db->doSelect('accounts',array(),array('name'=>$name),1,array());
		if($cObj == 0){
			$id = $this->_db->doInsert('accounts',array('name'=>$name,'type_id'=>$type,'createdBy'=>$userid,'currency_id'=>$currencyId,'balance'=>0));
			if($id > 0){
				$msg = "You have successfully created an account.";
				$data = array();
				$accounts = $this->_db->doSelect('accounts',array(),array('active'=>1),1,array('id'),'DESC',10);
				$data['msg'] = $msg;
				$data['accounts'] = $accounts;// return the most recent added accounts

				$result['success'] = 1;
				$result['data'] = $data;
				return $result;
			}
			$result['errormsg'] = "Sorry, your request failed. Reason : System currently unavailable. Please try again later.";
			return $result;
		}
		$result['errormsg'] = "Sorry, your request failed. Reason : Account already exists.";
		return $result;
	}

	public function addidtypeAction(){
		$name = @ mysql_real_escape_string($this->_params['name']);
		$userid = @ mysql_real_escape_string($this->_params['user_id']);
		$sessionid = @ mysql_real_escape_string($this->_params['session_id']);
		writeToLogFile("Add ID Type Params ;Name :$name\nUser ID : $userid\nSession ID : $sessionid\n");

		$result['success'] = 0;

		$userObj = $this->_db->doSelect('users',array('sessionId'),array("id"=>$userid),1,array());
		if($sessionid != $userObj->sessionId){
			$result['errormsg'] = "Sorry your transaction failed. Reason: Your login expired";
			return $result;
		}

		$name = strtoupper($name);
		$cObj = $this->_db->doSelect('identification',array(),array('name'=>$name),1,array());
		if($cObj == 0){
			$id = $this->_db->doInsert('identification',array('name'=>$name,'createdBy'=>$userid));
			if($id > 0){
				$msg = "You have successfully created an ID Type.";
				$data = array();
				$idtypes = $this->_db->doSelect('identification',array(),array('active'=>1),1,array('id'),'DESC',10);
				$data['msg'] = $msg;
				$data['idtypes'] = $idtypes;// return the most recent added id types

				$result['success'] = 1;
				$result['data'] = $data;
				return $result;
			}
			$result['errormsg'] = "Sorry, your request failed. Reason : System currently unavailable. Please try again later.";
			return $result;
		}
		$result['errormsg'] = "Sorry, your request failed. Reason : ID Type already exists.";
		return $result;
	}

	public function addbranchAction(){
		$name = @ mysql_real_escape_string($this->_params['name']);
		$userid = @ mysql_real_escape_string($this->_params['user_id']);
		$countryid = @ mysql_real_escape_string($this->_params['country_id']);
		$supervisor = @ mysql_real_escape_string($this->_params['supervisor_id']);
		$location = @ mysql_real_escape_string($this->_params['location']);
		$sessionid = @ mysql_real_escape_string($this->_params['session_id']);
		writeToLogFile("Add Branch Params ;Name :$name\User ID : $userid\nCountry ID : $countryid\nLocation : $location\nSession ID : $sessionid\n");

		$result['success'] = 0;

		$userObj = $this->_db->doSelect('users',array('sessionId'),array("id"=>$userid),1,array());
		if($sessionid != $userObj->sessionId){
			$result['errormsg'] = "Sorry your transaction failed. Reason: Your login expired";
			return $result;
		}

		$name = strtoupper($name);
		$cObj = $this->_db->doSelect('branches',array(),array('name'=>$name),1,array());
		if($cObj == 0){
			$id = $this->_db->doInsert('branches',array('name'=>$name,'createdBy'=>$userid,'countryId'=>$countryid,'supervisorId'=>$supervisor,'location'=>$location));
			if($id > 0){
				//create branch accounts
				$currencies = $this->_db->doSelect('currencies',array('id','name'),array('active'=>1),1,array());
				if($currencies != 0){
					for($i=0;$i<count($currencies);$i++){
						$accountName = "$name-".$currencies[$i]->name." Account";
						//$accountName = $currencies[$i]->name." Account";
						$transferAccountTypeId = $this->getSetting('TransferAccountTypeId');
						$this->_db->doInsert('accounts',array('name'=>$accountName,'createdBy'=>$userid,'branchId'=>$id,'type_id'=>$transferAccountTypeId,'currency_id'=>$currencies[$i]->id,'balance'=>0));
					}
				}

				$msg = "You have successfully created a branch.";
				$data = array();
				$branches = $this->_db->doSelect('branches',array(),array('active'=>1),1,array('id'),'DESC',10);
				$data['msg'] = $msg;
				$data['branches'] = $branches;// return the most recent added branches

				$result['success'] = 1;
				$result['data'] = $data;
				return $result;
			}
			$result['errormsg'] = "Sorry, your request failed. Reason : System currently unavailable. Contact admin.";
			return $result;
		}
		$result['errormsg'] = "Sorry, your request failed. Reason : Branch already exists.";
		return $result;
	}

	public function adduserAction(){
		$fname = @ mysql_real_escape_string($this->_params['first_name']);
		$sname = @ mysql_real_escape_string($this->_params['sur_name']);
		$email = @ mysql_real_escape_string($this->_params['email']);
		$phone = @ mysql_real_escape_string($this->_params['phone']);
		$userid = @ mysql_real_escape_string($this->_params['user_id']);
		$branchid = @ mysql_real_escape_string($this->_params['branch_id']);
		$permissions = @ mysql_real_escape_string($this->_params['user_type_id']);
		$sessionid = @ mysql_real_escape_string($this->_params['session_id']);
		writeToLogFile("Add User Params ;First Name :$fname\User ID : $userid\nBranch ID : $branchid\nSurname : $sname\nEmail : $email\nPhone : $phone\nSession ID : $sessionid\n");

		$result['success'] = 0;

		$userObj = $this->_db->doSelect('users',array('sessionId'),array("id"=>$userid),1,array());
		if($sessionid != $userObj->sessionId){
			$result['errormsg'] = "Sorry your transaction failed. Reason: Your login expired";
			return $result;
		}

		$cObj = $this->_db->doSelect('users',array(),array('email'=>$email,'phone'=>$phone),0,array());
		if($cObj == 0){
			//$permissions['createdBy'] = $userid;
			//$id = $this->_db->doInsert('permissions',$permissions);
			$id = $this->_db->doInsert('users',array('firstName'=>$fname,'createdBy'=>$userid,'surname'=>$sname,'email'=>$email,'phone'=>$phone,'branchId'=>$branchid,'userTypeId'=>$permissions));
			if($id > 0){

				$nToken = $this->getToken(32);
				$userObj->sessionId = $nToken;

				$updateObj = array("sessionId"=>$nToken,"ipAddress"=>$this->get_client_ip_server());
				$where = array("id"=>$id);
				$this->_db->doUpdate('users',$updateObj,$where,0);

				$emailLink = "http://localhost/jquery_mobile/mtransfer/completeuser.html?cmp=$nToken&name=$fname";
				writeToLogFile("User Link : $emailLink\n");

				$msg = "You have successfully created a user. An email inviting $fname has been sent";
				$data = array();
				$users = $this->_db->doSelect('users',array(),array('active'=>1),1,array('id'),'DESC',10);
				$data['msg'] = $msg;
				//$data['users'] = $users;// return the most recent added users

				$result['success'] = 1;
				$result['data'] = $msg;//$data;
				return $result;
			}
			$result['errormsg'] = "Sorry, your request failed. Reason : System currently unavailable. Please try again later.";
			return $result;
		}
		$result['errormsg'] = "Sorry, your request failed. You are not a valid system users";
		return $result;
	}

	public function createloginAction(){

		$username = @ mysql_real_escape_string($this->_params['username']);
		$password = @ mysql_real_escape_string($this->_params['password']);
		$sessionid = @ mysql_real_escape_string($this->_params['session_id']);
		$pass = '';
		if(!empty($password)){
			$pass = '******';
		}
		writeToLogFile("Login Creation Params ;SessionID :$sesion\Username : $username\nPassword : $pass\n");

		$result['success'] = 0;

		$userObj = $this->_db->doSelect('users',array('sessionId'),array("id"=>$userid),1,array());
		if($sessionid != $userObj->sessionId){
			$result['errormsg'] = "Sorry your transaction failed. Reason: Your login expired";
			return $result;
		}

		$cObj = $this->_db->doSelect('users',array(),array('sessionId'=>$sesion),0,array());
		if($cObj != 0){
			$userid = $cObj->id;
			$crypt = new PasswordLib\PasswordLib;
			$pass = @ $crypt->createPasswordHash($password, '$2a$');

			$nToken = $this->getToken(32);
			$cObj->sessionId = $nToken;

			$cObj->userName = $username;
			$cObj->password = $pass;

			$updateObj = array("sessionId"=>$nToken,"ipAddress"=>$this->get_client_ip_server(),'password'=>$pass,'userName'=>$username);
			$where = array("id"=>$userid);

			$resp = $this->_db->doUpdate('users',$updateObj,$where,$userid);
			if($resp == 1){
				$cObj->rolls = $this->_db->doSelect('permissions',array(),array('id'=>$cObj->permissionId),1,array());
				$result['success'] = 1;
				$result['data'] = $cObj;
				return $result;
			}
			$result['errormsg'] = "Sorry, your request failed. Reason : Service currently unavailable. Please try again later.";
			return $result;
		}else{
			$result['errormsg'] = "Sorry, your request failed. Reason : Sesion expired.";
			return $result;
		}
	}

	public function resetpasswordAction(){

		$userid = @ mysql_real_escape_string($this->_params['user_id']);
		$sessionid = @ mysql_real_escape_string($this->_params['session_id']);
		$oldpass = @ mysql_real_escape_string($this->_params['old_password']);
		$newpass = @ mysql_real_escape_string($this->_params['new_password']);

		$pass1 = '';
		$pass2 = '';
		if(!empty($oldpass)){
			$pass1 = '******';
		}

		if(!empty($newpass)){
			$pass2 = '******';
		}
		writeToLogFile("Password Reset Params :\nSessionID :$sessionid\nUser ID : $userid\nNew Password : $pass2\nOld Password : $pass1\n");

		$where = array("id"=>$userid);
		$userObj = $this->_db->doSelect('users',array('sessionId','password','active'),$where,1,array());

		if($userObj != 0){
			if($sessionid != $userObj->sessionId){
				$result['errormsg'] = "Sorry your transaction failed. Reason: Your login expired";
				return $result;
			}

			$crypt = new PasswordLib\PasswordLib;

			if (!@ $crypt->verifyPasswordHash($oldpass, $userObj->password)){
				$result['success'] = 0;
				$result['errormsg'] = "Login Failed. Wrong Old Password.";
				return $result;
			}

			$pass = @ $crypt->createPasswordHash($newpass, '$2a$');

			$resp = $this->_db->doUpdate('users',array('password'=>$pass),array("id"=>$userid),$userid);
			if($resp == 1){
				$result['success'] = 1;
				$result['data'] = "Password has been reset successfully";
				return $result;
			}
		}

		$result['success'] = 0;
		$result['errormsg'] = "Login Failed. Wrong Old Password.";
		return $result;
	}

	public function formsdetailsAction(){
		$sessionid = @ mysql_real_escape_string($this->_params['sessionid']);
		$userid = @ mysql_real_escape_string($this->_params['userid']);
		writeToLogFile("Details Params :\nSessionID :$sessionid\nUser ID : $userid\n");

		$brancObj = $this->_db->doSelect('branches',array(),array('active'=>1),1,array());
		if(is_array($brancObj)){
			$result['data']['branches'] = $brancObj;
		}else{
			if($brancObj != 0){
				$result['data']['branches'] = array($brancObj);
			}else{
				$result['data']['branches'] = array();
			}
		}

		$countrObj = $this->_db->doSelect('countries',array(),array('active'=>1),1,array());
		if(is_array($countrObj)){
			$result['data']['countries'] = $countrObj;
		}else{
			if($countrObj != 0){
				$result['data']['countries'] = array($countrObj);
			}else{
				$result['data']['countries'] = array();
			}
		}

		$idsObj = $this->_db->doSelect('identification',array(),array('active'=>1),1,array());
		if(is_array($currObj)){
			$result['data']['ids'] = $idsObj;
		}else{
			if($idsObj != 0){
				$result['data']['ids'] = array($idsObj);
			}else{
				$result['data']['ids'] = array();
			}
		}

		$usersObj = $this->_db->doSelect('users',array(),array('active'=>1),1,array());
		if(is_array($usersObj)){
			$result['data']['users'] = $usersObj;
		}else{
			if($usersObj != 0){
				$result['data']['users'] = array($usersObj);
			}else{
				$result['data']['users'] = array();
			}
		}

		$where = array('active'=>1,'type_id'=>1);
		if($usersObj->userTypeId = 3){
			$userBranch = $usersObj->branchId;
			$where = $where = array('active'=>1,'type_id'=>1,'branchId'=>$userBranch);
		}

		$accountsObj = $this->_db->doSelect('accounts',array('id','name','branchId','currency_id'),$where,1,array());
		if(is_array($accountsObj)){
			$result['data']['accounts'] = $accountsObj;
		}else{
			if($accountsObj != 0){
				$result['data']['accounts'] = array($accountsObj);
			}else{
				$result['data']['accounts'] = array();
			}
		}

		//update Opening and closing balances
		$length = count($accountsObj);
		for($i=0;$i<$length;$i++){
			$account = $accountsObj[$i];
			$accountId = $account->id;
			$branchId = $account->branchId;
			$this->createBalances($branchId,$accountId,0);
		}

		$currObj = $this->_db->doSelect('currencies',array(),array('active'=>1),1,array());
		if(is_array($currObj)){
			$result['data']['currencies'] = $currObj;
		}else{
			if($currObj != 0){
				$result['data']['currencies'] = array($currObj);
			}else{
				$result['data']['currencies'] = array();
			}
		}

		$saccountsObj = $this->_db->doSelect('accounts',array(),array('active'=>1,'type_id'=>4),1,array());
		if(is_array($saccountsObj)){
			$result['data']['superaccount'] = $saccountsObj;
		}else{
			if($saccountsObj != 0){
				$result['data']['superaccount'] = array($saccountsObj);
			}else{
				$result['data']['superaccount'] = array();
			}
		}

		$accountTypewObj = $this->_db->doSelect('account_types',array(),array('active'=>1),1,array());
		if(is_array($accountTypewObj)){
			$result['data']['accountTypes'] = $accountTypewObj;
		}else{
			if($accountTypewObj != 0){
				$result['data']['accountTypes'] = array($accountTypewObj);
			}else{
				$result['data']['accountTypes'] = array();
			}
		}

		$userTypeswObj = $this->_db->doSelect('user_types',array(),array('active'=>1),1,array());
		if(is_array($userTypeswObj)){
			$result['data']['userTypes'] = $userTypeswObj;
		}else{
			if($userTypeswObj != 0){
				$result['data']['userTypes'] = array($userTypeswObj);
			}else{
				$result['data']['userTypes'] = array();
			}
		}

		$result['success'] = 1;
		return $result;
	}

	public function getSetting($key){
		$settingObj = $this->_db->doSelect('settings',array(),array("name"=>$key),1,array());
		return $settingObj->value;
	}

	public function createBalances($branchId,$accountId,$amount){
	    writeToLogFile("Balance Update Params :\nBranch Id : $branchId\nAccount ID : $accountId\nAmount : $amount\n");
		$balanceObj = $this->_db->doSelect('balances',array(),array('branchId'=>$branchId,'accountId'=>$accountId),1,array());
		if($amount == 0 && $balanceObj == 0){
			return;
		}
		$today = date('Y-m-d');
		if($balanceObj == 0){
			$id = $this->_db->doInsert('balances',array('branchId'=>$branchId,'accountId'=>$accountId,'openingBalance'=>$amount,'closingBalance'=>$amount,'date'=>$today));
			writeToLogFile("Balance Create Insert ID :$id\n ");
		}else{
			$balanceObj = $this->_db->doSelect('balances',array(),array('branchId'=>$branchId,'accountId'=>$accountId,'date'=>$today),1,array());
			if($balanceObj == 0){
				//get closing balance of yesterday
				$dateYesterday = date("Y-m-d", time() - 60 * 60 * 24);
				$balanceObjYest = $this->_db->doSelect('balances',array('closingBalance'),array('branchId'=>$branchId,'accountId'=>$accountId),1,array('date'),'DESC',1);
				if($balanceObjYest == 0){
					$id = $this->_db->doInsert('balances',array('branchId'=>$branchId,'accountId'=>$accountId,'openingBalance'=>$amount,'closingBalance'=>$amount,'date'=>$today));
					writeToLogFile("Balance Create Insert ID Today 1:$id\n ");
				}else{
					$closingBalYest = $balanceObjYest->closingBalance;
					$openingBalToday = $amount + $closingBalYest;
					$id = $this->_db->doInsert('balances',array('branchId'=>$branchId,'accountId'=>$accountId,'openingBalance'=>$openingBalToday,'closingBalance'=>$openingBalToday,'date'=>$today));
					writeToLogFile("Balance Create Insert ID Today 2:$id\n ");
				}
			}else{
				$closingBal = $balanceObj->closingBalance;
				writeToLogFile("The final amount: $amount\n ");
				$closingBal = $closingBal+$amount;
				$flag = $this->_db->doUpdate('balances',array('closingBalance'=>$closingBal),array('branchId'=>$branchId,'accountId'=>$accountId,'date'=>$today),0);
				writeToLogFile("Balance Update Status 2:$flag\n ");
			}
		}
	}

	private function getToken($length){
		$token = "";
		$codeAlphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
		$codeAlphabet.= "abcdefghijklmnopqrstuvwxyz";
		$codeAlphabet.= "0123456789";
		$max = strlen($codeAlphabet) - 1;
		for ($i=0; $i < $length; $i++) {
			$token .= $codeAlphabet[$this->crypto_rand_secure(0, $max)];
		}
		return $token;
	}

	private function crypto_rand_secure($min, $max){
		$range = $max - $min;
		if ($range < 1) return $min; // not so random...
			$log = ceil(log($range, 2));
			$bytes = (int) ($log / 8) + 1; // length in bytes
			$bits = (int) $log + 1; // length in bits
			$filter = (int) (1 << $bits) - 1; // set all lower bits to 1
		do{
			$rnd = hexdec(bin2hex(openssl_random_pseudo_bytes($bytes)));
			$rnd = $rnd & $filter; // discard irrelevant bits
		}while ($rnd >= $range);
		return $min + $rnd;
	}

	public function get_client_ip_server() {
    	$ipaddress = '';
    	if (isset($_SERVER['HTTP_CLIENT_IP']))
      		$ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    	else if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        	$ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
      	else if (isset($_SERVER['HTTP_X_FORWARDED']))
        	$ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        else if (isset($_SERVER['HTTP_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if (isset($_SERVER['HTTP_FORWARDED']))
        	$ipaddress = $_SERVER['HTTP_FORWARDED'];
      	else if (isset($_SERVER['REMOTE_ADDR']))
        	$ipaddress = $_SERVER['REMOTE_ADDR'];
       	else
        	$ipaddress = 'UNKNOWN';
    	return $ipaddress;
  	}
}

?>
