<?php
error_reporting(1);

require_once dirname(__FILE__).'/../html2pdf/vendor/autoload.php';

use Spipu\Html2Pdf\Html2Pdf;
use Spipu\Html2Pdf\Exception\Html2PdfException;
use Spipu\Html2Pdf\Exception\ExceptionFormatter;

function generateReciept($trxnId,$qrcode){
    $serviceId = '';
    $amount = 0;
    $userid = '';
    $transactionstatus = '';
    $date = '';
    $charge = 0;
	$tokenLabel = 'Total Accepted';
	$labels = array('Transaction Time','Transaction ID','Operation','Amount','Total Accepted','Charge','Total Transacted','QRCode',
					'merchantName','merchantId','contact','location','msg','Sender Name','Sender Phone','Sender Email','Receiver Name',
				   'Receiver Phone','Receiver Email');
	$values = array();
    
	$values[$labels[1]] = $trxnId;
	$values[$labels[10]] = $qrcode;
	
    $sql = "SELECT utilityid,amount,userid,transactionstatus,date FROM utility_orders where status = 'Success' AND id = $trxnId";
    writeToLogFile("SQL for Utility_Table : $sql\n");
    $resultSet = mysql_query($sql);
    $rows = mysql_num_rows($resultSet);
    writeToLogFile("Rows of Utility_Table : $rows\n");
    if($resultSet && $rows > 0){
        $row = mysql_fetch_object($resultSet);

        $serviceId = $row->utilityid;
        $amount = $row->amount;
        $userid = $row->userid;
        $transactionstatus = $row->transactionstatus;
		$values[$labels[0]] = $row->date;
    }else{
        return 0;
    }
    
    $sql = "SELECT name FROM utilities WHERE id = $serviceId";
    $resultSet = mysql_query($sql);
    $row = mysql_fetch_object($resultSet);
	$serviceName = $row->name;
	$values[$labels[15]] = ' Your payment for '.$serviceName.' has been accepted';
	$values[$labels[2]] =  $serviceName;
		
    $sql = "SELECT * FROM members WHERE id = $userid";
    $resultSet = mysql_query($sql);
    $userObj = mysql_fetch_object($resultSet);
    
	$values[$labels[11]] = $userObj->name;
    $values[$labels[12]] = 'n/a';
    $values[$labels[14]] = 'n/a';
    if($userObj->isMerchant){
		$values[$labels[11]] = $userObj->companyName;
        $values[$labels[12]] = $userObj->billerCode;
        $values[$labels[14]] = '';
    }
	$values[$labels[13]] = $userObj->phone;
	
    //echo $transactionstatus;
    $jsonObj = json_decode($transactionstatus);
    $data = (array) $jsonObj;
    
    $charge = $data['data']->Charges;
	$values[$labels[8]] = number_format($charge);
    $newAmount = $amount-$charge;
	$values[$labels[9]] = 'UGX'.number_format($amount);
	$values[$labels[3]] = number_format($newAmount);
	$values[$labels[4]] = $data['data']->account;
	$values[$labels[5]] = $data['data']->customer;
	$values[$labels[6]] = $data['data']->phone;
	
	$values[$labels[7]] = 'UGX'.$values[$labels[3]];
	if($serviceId == 18){
		$tokenLabel = 'Energy Token';
		
		$labels[7] = $tokenLabel;
		$values[$labels[7]] = $data['data']->rechargePIN;
	}
    
    try{
		$content = reciept($labels,$values);
		
        $html2pdf = new Html2Pdf('P', 'A4', 'fr');
        $html2pdf->pdf->SetDisplayMode('fullpage');
		$html2pdf->setDefaultFont('FreeMono');
        $html2pdf->writeHTML($content);
        //$html2pdf->Output('exemple07.pdf');
        $html2pdf->Output("/var/www/html/tfbw/api/logs/".$values[$labels[1]].".pdf",'F');
        return 1;
    }catch(Html2PdfException $e){
        $formatter = new ExceptionFormatter($e);
        writeToLogFile("Exception Occured : ".$e."\n");
        return 0;
    }
}

function generateTransferReciept($trxnId,$qrcode,$table,$type){
	
	$labels = array('Transaction Time','Transaction ID','Transaction Type','Amount','Debit Account Name','Debit Account Number','Credit Account Name','Credit Account Number','Transaction Reason','','QRCode','merchantName','merchantId','contact','location','msg');
	$values = array();
	
	$values[$labels[1]] = $trxnId;
	$values[$labels[2]] = $type;
	$values[$labels[10]] = $qrcode;
	$values[$labels[15]] = 'Your '.$type.' has been accepted';
	
	$fromid = 0;
	$toid = 0;
	
	$sql = "SELECT date,amount,fromid,toid,reason FROM $table where type = 'transfer' AND id = $trxnId";
    writeToLogFile("SQL for Transfer Trxn : $sql\n");
    $resultSet = mysql_query($sql);
    $rows = mysql_num_rows($resultSet);
    writeToLogFile("Rows of Transfer_Table : $rows\n");
    if($resultSet && $rows > 0){
		$row = mysql_fetch_object($resultSet);

        $values[$labels[0]] = $row->date;
		$values[$labels[3]] = $row->amount;
		$values[$labels[8]] = $row->reason;
		$fromid = $row->fromid;
		$toid = $row->toid;
	}else{
		return 0;
	}
	
	//debit account details
	$sql = "SELECT * FROM members WHERE id = $fromid";
    $resultSet = mysql_query($sql);
    $userObj = mysql_fetch_object($resultSet);
    
	$values[$labels[4]] = $userObj->name;
    $values[$labels[5]] = $userObj->phone;
	$values[$labels[11]] = $values[$labels[4]];
    if($userObj->isMerchant){
		$values[$labels[4]] = $userObj->companyName;
		
		$values[$labels[11]] = $userObj->companyName;
        $values[$labels[12]] = $userObj->billerCode;
        $values[$labels[14]] = '';
    }
	$values[$labels[13]] = $userObj->phone;
	
	//credit account details
	$sql = "SELECT * FROM members WHERE id = $toid";
    $resultSet = mysql_query($sql);
    $userObj = mysql_fetch_object($resultSet);
    
	$values[$labels[6]] = $userObj->name;
    $values[$labels[7]] = $userObj->phone;
    if($userObj->isMerchant){
		$values[$labels[6]] = $userObj->companyName;
    }
	
	try{
		$content = reciept($labels,$values);
		
        $html2pdf = new Html2Pdf('P', 'A4', 'fr');
        $html2pdf->pdf->SetDisplayMode('fullpage');
		$html2pdf->setDefaultFont('FreeMono');
        $html2pdf->writeHTML($content);
        //$html2pdf->Output('exemple07.pdf');
        $html2pdf->Output("/var/www/html/tfbw/api/logs/".$values[$labels[1]].".pdf",'F');
        return 1;
    }catch(Html2PdfException $e){
        $formatter = new ExceptionFormatter($e);
        writeToLogFile("Exception Occured : ".$e."\n");
        return 0;
    }
	
}

function reciept($labels,$values){
	$content = '<page>
            <div style="margin-bottom:20px;margin-left:20%;margin-right:20%;background-color:black;color:white;widith:60%;text-align:center;vertical-align:middle;height:30px;font-size:x-small;"><b>MTransfer Receipt</b></div>
            <table width="100%" style="border:10px solid #ddd;border-collapse:collapse;">
                <tr>
                    <td colspan="2" style="width:350px;text-align:center;padding:15px;border-left:10px solid #ddd;border-top:10px solid #ddd;border-bottom:10px solid #ddd;border-right: 10px solid #ddd;color :grey;font-size:medium;">'.$values[$labels[15]].'</td>
                    <td rowspan="4" colspan="2" style="width:280px;text-align:center;color:white;border-top:10px solid #ddd;border-right:10px solid #ddd;">
                        <img src="/var/www/html/tfbw/images/screen.jpg" height="100">
                    </td>
                </tr>
                <tr>
                    <td style="padding: 5px;color :grey;font-size:small;border-left: 10px solid #ddd;"><b>'.$labels[0].'</b></td>
                    <td style="border-right: 10px solid #ddd;padding: 5px;color :grey;font-size:small;">'.$values[$labels[0]].'</td>
                </tr>
                <tr>
                    <td style="padding: 5px;color :grey;font-size:small;border-left: 10px solid #ddd;"><b>'.$labels[1].'</b></td>
                    <td style="border-right: 10px solid #ddd;padding: 5px;color :grey;font-size:small;">'.$values[$labels[1]].'</td>
                </tr>
                <tr>
                    <td style="padding: 5px;color :grey;font-size:small;border-left: 10px solid #ddd;"><b>'.$labels[2].'</b></td>
                    <td style="border-right: 10px solid #ddd;padding: 5px;color :grey;font-size:small;">'.$values[$labels[2]].'</td>
                </tr>
                <tr>
                    <td style="padding: 5px;color :grey;font-size:small;border-left: 10px solid #ddd;"><b>'.$labels[3].'</b></td>
                    <td style="border-right: 10px solid #ddd;padding: 5px;color :grey;font-size:small;">UGX'.$values[$labels[3]].'</td>
                    <td style="color :grey;font-size:small;border-top: 10px solid #ddd;"><b>Agent Name</b></td>
                    <td style="color :grey;font-size:small;border-top: 10px solid #ddd;border-right:10px solid #ddd;">'.$values[$labels[11]].'</td>
                </tr>
                <tr>
                    <td style="padding: 5px;color :grey;font-size:small;border-left: 10px solid #ddd;"><b>'.$labels[4].'</b></td>
                    <td style="border-right: 10px solid #ddd;padding: 5px;color :grey;font-size:small;">'.$values[$labels[4]].'</td>
                    <td style="color :grey;font-size:small;"><b>Agent ID</b></td>
                    <td style="color :grey;font-size:small;border-right:10px solid #ddd;">'.$values[$labels[12]].'</td>
                </tr>
                <tr>
                    <td style="padding: 5px;color :grey;font-size:small;border-left: 10px solid #ddd;"><b>'.$labels[5].'</b></td>
                    <td style="border-right: 10px solid #ddd;padding: 5px;color :grey;font-size:small;">'.$values[$labels[5]].'</td>
                    <td style="color :grey;font-size:small;"><b>Agent Contact</b></td>
                    <td style="color :grey;font-size:small;border-right:10px solid #ddd;">'.$values[$labels[13]].'</td>
                </tr>
                <tr>
                    <td style="padding: 5px;color :grey;font-size:small;border-left: 10px solid #ddd;"><b>'.$labels[6].'</b></td>
                    <td style="border-right: 10px solid #ddd;padding: 5px;color :grey;font-size:small;">'.$values[$labels[6]].'</td>
                    <td style="color :grey;font-size:small;border-bottom: 10px solid #ddd;"><b>Agent Location</b></td>
                    <td style="color :grey;font-size:small;border-bottom: 10px solid #ddd;border-right:10px solid #ddd;">'.$values[$labels[14]].'</td>
                </tr>
                <tr>
                    <td style="padding: 5px;color :grey;font-size:small;border-left: 10px solid #ddd;"><b>'.$labels[7].'</b></td>
                    <td style="border-right: 10px solid #ddd;padding: 5px;color :grey;font-size:small;">'.$values[$labels[7]].'</td>
                    <td rowspan="3" colspan="2" style="text-align:center;border-right:10px solid #ddd;border-bottom:10px solid #ddd;"> <img src="'.$values[$labels[10]].'"> </td>
                </tr>
                <tr>
                    <td style="padding: 5px;color :grey;color :grey;font-size:small;border-left: 10px solid #ddd;"><b>'.$labels[8].'</b></td>
                    <td style="border-right: 10px solid #ddd;padding: 5px;color :grey;">'.$values[$labels[8]].'</td>
                </tr>
                <tr>
                    <td style="padding: 5px;color :grey;font-size:small;border-left: 10px solid #ddd;border-bottom: 10px solid #ddd;"><b>'.$labels[9].'</b></td>
                    <td style="border-right: 10px solid #ddd;padding: 5px;color :grey;font-size:small;border-bottom: 10px solid #ddd;">'.$values[$labels[9]].'</td>
                </tr>
            </table>
            <p style="margin-top:40px;margin-bottom:5px;color:grey;font-size:small;">Disclaimer</p>
            <table width="100%" style="border-collapse: collapse;">
                <tr>
                    <td rowspan="2" style="width:400px;height:590px;vertical-align:top;color :grey;font-size:10;border-top:1px solid #ddd;border-bottom: 1px solid #ddd;border-left: 1px solid #ddd;border-right: 1px solid #ddd;">
                        Top Finance Bank provides the details herein only for information.The client should obtain and view their account statement to confirm whether the transaction has been processed successfully.<br>
                        Top Finance Bank therefore makes no representation or warranty, whether express or implied, as to the integrity,accuracy,completeness or reliability of any information contained herein
                    </td>
                    <td width="40%"></td>
                </tr>
                <tr>
                    <td width="40%" style="text-align:center;" column="2">
                        <img src="/var/www/html/tfbw/images/screen.jpg" height="100">
                        <p style="color :grey;font-size:10">www.topfinancebank.co.ug</p>
                        <p style="color :grey;font-size:10">info@topfinancebank.co.ug, 256 321 300 699</p>
                        <p style="color :grey;font-size:10">Plot 3 Dundas roard, Kololo Courts, P.O.Box33913 Kampala</p>
                    </td>
                </tr>
                <tr>
                    <td width="40%" column="2"></td>
                </tr>
            </table>
        </page>
        <page_footer><p style="color:grey;font-size:10;text-align:center;">© 2017 Top Finance Bank LTD. All Rights Reserved</p></page_footer>';
	return $content;
}