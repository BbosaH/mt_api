<?php

require_once('Users.class.php');

class Reports{

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

	public function gettransferlogsAction(){

		$userid = @ mysql_real_escape_string($this->_params['user_id']);
		$branchid = @ mysql_real_escape_string($this->_params['branch_id']);
		$accountId = @ mysql_real_escape_string($this->_params['account_id']);
		$sessionid = @ mysql_real_escape_string($this->_params['session_id']);
		$date = @ mysql_real_escape_string($this->_params['my_date']);
		writeToLogFile("UserID : $userid\nSessionId : $sessionid\nBranch ID : $branchid\nDate : $date\nAccount ID : $accountId\n");

		$result['success'] = 0;

		$userObj = $this->_db->doSelect('users',array('sessionId','branchId','userTypeId'),array("id"=>$userid),1,array());
		/*if($sessionid != $userObj->sessionId){
			$result['errormsg'] = "Sorry your transaction failed. Reason: Your login expired";
			return $result;
		}*/

		if(empty($date)){
			$date =  date('Y-m-d',time()- date("Z"));
		}

		$where = array();//'date'=>$date
		$data = array();
		$where['active'] = 1;
		if(!empty($branchid)){
			$where['fromBranch'] = $branchid;
			$where['toBranch'] = $branchid;
		}

		if($userObj->userTypeId != 2){
			$where['fromBranch'] = $userObj->branchId;
			$where['toBranch'] = $userObj->branchId;
		}

		if(!empty($accountId)){
			$accObj = $this->_db->doSelect('accounts',array('currency_id'),array("id"=>$accountId),1,array());
			$where['currency'] = $accObj->currency_id;
		}
		$fields = array('id','fromBranch','toBranch','amount','currency','charge','transferRef','userIdSource','userIdDestination','ts','withdrawTs',
						'status','senderFname','senderSurname','senderPhone','senderEmail','receiverFname','receiverSurname','receiverPhone','receiverAccountNumber',
					   'destinationCountryId');
		$transfers = $this->_db->doSelect('transfers',$fields,$where,0,array('ts'),'DESC',20);
		if($transfers != 0){
			if(is_array($transfers)){
				$length = count($transfers);
				for($i=0;$i<$length;$i++){
					$row = $transfers[$i];

					$branch = $this->_db->doSelect('branches',array('name'),array('id'=>$row->fromBranch),1,array());
					$row->fromBranchName = $branch->name;

					$branch = $this->_db->doSelect('branches',array('name'),array('id'=>$row->toBranch),1,array());
					$row->toBranchName = $branch->name;

					$currency = $this->_db->doSelect('currencies',array('name'),array('id'=>$row->currency),1,array());
					$row->currency = $currency->name;

					$user = $this->_db->doSelect('users',array('userName'),array("id"=>$row->userIdSource),1,array());
					$row->agentSource = $user->userName;

					$user = $this->_db->doSelect('users',array('userName'),array("id"=>$row->userIdDestination),1,array());
					$row->agentDestination = $user->userName;

					$counrtyObj = $this->_db->doSelect('countries',array('name'),array("id"=>$row->destinationCountryId),1,array());
					$row->destinationCountry = $user->name;

					$data[] = $row;
				}
			}else{
				$branch = $this->_db->doSelect('branches',array('name'),array('id'=>$transfers->fromBranch),1,array());
				$transfers->fromBranchName = $branch->name;

				$branch = $this->_db->doSelect('branches',array('name'),array('id'=>$transfers->toBranch),1,array());
				$transfers->toBranchName = $branch->name;

				$currency = $this->_db->doSelect('currencies',array('name'),array('id'=>$transfers->currency),1,array());
				$transfers->currency = $currency->name;

				$user = $this->_db->doSelect('users',array('userName'),array("id"=>$transfers->userIdSource),1,array());
				$transfers->agentSource = $user->userName;

				$user = $this->_db->doSelect('users',array('userName'),array("id"=>$transfers->userIdDestination),1,array());
				$transfers->agentDestination = $user->userName;

				$counrtyObj = $this->_db->doSelect('countries',array('name'),array("id"=>$transfers->destinationCountryId),1,array());
				$transfers->destinationCountry = $user->name;

				$data[] = $transfers;
			}
		}

		$branch = $this->_db->doSelect('branches',array('name'),array('id'=>$branchid),1,array());

		$result['branchName'] = $branch->name;
		$result['success'] = 1;
		$result['data'] = $data;
		return $result;

	}


	public function getreportdataAction(){

    writeToLogFile("kyakabi");
		$userid = @ mysql_real_escape_string($this->_params['user_id']);
		writeToLogFile("kyakabi1");
		$user_type_id = @ mysql_real_escape_string($this->_params['user_type_id']);
		writeToLogFile("kyakabi2");
		$branchid = @ mysql_real_escape_string($this->_params['branch_id']);
		writeToLogFile("kyakabi3");
		$sessionid = @ mysql_real_escape_string($this->_params['session_id']);
		$date = date('Y-m-d', time()-date("Z"));


		writeToLogFile("UserID : $userid\nSessionId : $sessionid\nBranch ID : $branchid\n");
		$result['success'] = 0;


			if($user_type_id==1 || $user_type_id==2){

					$branches = $this->_db->doSelect('branches',array(),array(),1,array());
					$full_branches= array();
					foreach($branches as $branch){

						$first_tra_today= $this->_db->doSelect('branch_transaction',array(),array('branch_id'=>$branch->id,'date'=>$date),1,array());

						$second_tra_today= $this->_db->doSelect('branch_transaction',array(),array('to_branch_id'=>$branch->id,'date'=>$date),1,array());
						$accounts= $this->_db->doSelect('accounts',array(),array('branchId'=>$branch->id,),1,array());
						$balances = $this->_db->doSelect('balances',array(),array('branchId'=>$branch->id,'date'=>$date),1,array());

						$accounts = (is_array($accounts)) ? $accounts : array($accounts);
						$balances = (is_array($balances)) ? $balances : array($balances);
						$first_tra_today = (is_array($first_tra_today)) ? $first_tra_today : array($first_tra_today);
						$second_tra_today = (is_array($second_tra_today)) ? $second_tra_today : array($second_tra_today);

						$transactions_today = array_merge($first_tra_today,$second_tra_today);

						//$transactions_today = (is_array($transactions_today)) ? $transactions_today : array($transactions_today);
						//$cashins = (is_array($cashins)) ? $cashins : array($cashins);

						$full_branch = array(

							'branch' => $branch,
							'transactions_today'=>$transactions_today,
							'accounts'=>$accounts,
							'balances'=>$balances

						);

						array_push($full_branches,$full_branch);

					}
					$result['success'] = 1;
					$result['today'] = $date;
					$result['data'] = $full_branches;
					return $result;
					//return $full_branches;



		 	}
			else if ($user_type_id==3 || $user_type_id==5){

				$branch = $this->_db->doSelect('branches',array(),array('id'=>$branchid ),1,array());
				$transactions_today= $this->_db->doSelect('branch_transaction',array(),array('branch_id'=>$branchid,'date'=>$date),1,array());
				$accounts= $this->_db->doSelect('accounts',array(),array('branchId'=>$branchid),0,array());
				$balances = $this->_db->doSelect('balances',array(),array('branchId'=>$branchid,'date'=>$date),1,array());

				$accounts = (is_array($accounts)) ? $accounts : array($accounts);
				$balances = (is_array($balances)) ? $balances : array($balances);
				$transactions_today = (is_array($transactions_today)) ? $transactions_today : array($transactions_today);

				$full_branch = array(

					'branch' => $branch,
					'transactions_today'=>$transactions_today,
					'accounts'=>$accounts,
					'balances'=>$balances

				);
				$result['success'] = 1;
				$result['today'] = $date;
				$result['data'] = $full_branch;
				return $result;
			}

		return;
	}

	public function gettransferchargesAction(){
		$userid = @ mysql_real_escape_string($this->_params['user_id']);
		$branchid = @ mysql_real_escape_string($this->_params['branch_id']);
		$accountId = @ mysql_real_escape_string($this->_params['account_id']);
		$sessionid = @ mysql_real_escape_string($this->_params['session_id']);
		$date = @ mysql_real_escape_string($this->_params['my_date']);
		writeToLogFile("UserID : $userid\nSessionId : $sessionid\nBranch ID : $branchid\nDate : $date\n");

		$result['success'] = 0;

		$userObj = $this->_db->doSelect('users',array('sessionId','branchId','userTypeId'),array("id"=>$userid),1,array());
		/*if($sessionid != $userObj->sessionId){
			$result['errormsg'] = "Sorry your transaction failed. Reason: Your login expired";
			return $result;
		}*/

		if(empty($date)){
			$date =  date('Y-m-d',time()- date("Z"));
		}

		$where = array();//'date'=>$date
		$data = array();
		$refs = array();

		if(!empty($branchid)){
			$where['fromBranch'] = $branchid;
			$where['toBranch'] = $branchid;
		}

		if($userObj->userTypeId != 2){
			$where['fromBranch'] = $userObj->branchId;
			$where['toBranch'] = $userObj->branchId;
		}

		if(!empty($accountId)){
			$accObj = $this->_db->doSelect('accounts',array('currency_id'),array("id"=>$accountId),1,array());
			$where['currency'] = $accObj->currency_id;
		}

		$transfers = $this->_db->doSelect('transfers',array('transferRef'),$where,0,array('ts'),'DESC',20);
		if($transfers != 0){
			if(is_array($transfers)){
				$length = count($transfers);
				for($i=0;$i<$length;$i++){
					$row = $transfers[$i];
					$data[] = $row;
				}
			}else{
				$refs[] = $transfers->transferRef;
			}
		}

		if(count($refs) == 0){
			$result['success'] = 1;
			$result['data'] = $data;
			return $result;
		}

		$accTransfers = $this->_db->doSelect('account_transfers',array('charge','chargeCurrency','transferRef','amountB4Xchange','amountAfterXchange','currencyB4Xchange','currencyAfterXchange'),$refs,0,array('ts'),'DESC',20);
		if($accTransfers != 0){
			if(is_array($accTransfers)){
				$length = count($accTransfers);
				for($i=0;$i<$length;$i++){
					$row = $accTransfers[$i];

					$currency = $this->_db->doSelect('currencies',array('name'),array('id'=>$row->chargeCurrency),1,array());
					$row->chargeCurrencyName = $currency->name;

					$currency = $this->_db->doSelect('currencies',array('name'),array('id'=>$row->currencyB4Xchange),1,array());
					$row->currencyNameBe4Xchange = $currency->name;

					$currency = $this->_db->doSelect('currencies',array('name'),array('id'=>$row->currencyAfterXchange),1,array());
					$row->currencyNameAfterXchange = $currency->name;

					$data[] = $row;
				}
			}else{
				$currency = $this->_db->doSelect('currencies',array('name'),array('id'=>$accTransfers->chargeCurrency),1,array());
				$accTransfers->chargeCurrencyName = $currency->name;

				$currency = $this->_db->doSelect('currencies',array('name'),array('id'=>$accTransfers->currencyB4Xchange),1,array());
				$accTransfers->currencyNameBe4Xchange = $currency->name;

				$currency = $this->_db->doSelect('currencies',array('name'),array('id'=>$accTransfers->currencyAfterXchange),1,array());
				$accTransfers->currencyNameAfterXchange = $currency->name;

				$data[] = $accTransfers;
			}
		}

		$branch = $this->_db->doSelect('branches',array('name'),array('id'=>$branchid),1,array());

		$result['branchName'] = $branch->name;
		$result['success'] = 1;
		$result['data'] = $data;
		return $result;
	}
}

?>
