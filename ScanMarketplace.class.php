<?php

/**
 * Created by PhpStorm.
 * User: mvalezy
 * Date: 30/09/2016
 * Time: 11:22
 */
class ScanMarketplace
{
    /*
     * String
     */
    public  $debug;
    public  $ID, $MerchantID;
    public  $Marketplace;
    public  $wsLogin;
    public  $wsPassword;
    public  $merchantGroupId;
    public  $TechnicalAccountLogin;
    public  $CommissionAccountLogin;
    public  $pastDate;
    public  $locale;
    
    private $API_ENV;

    private $db;
    public  $merchantGroupName;
    public  $accountId;
    public  $accountLogin;
    public  $getBalance;

    /*
     * Array
     */
    public  $Merchants;

    /*
     * Object
     */
    public  $Merchant;
    private  $client;
    //private  $resultgetAccountInfos;
    //private  $resultgetBalance;
    //private  $resultGetMerchantsGroupAccounts;
    private  $resultbankInfosStatus;
    private  $resultgetKYC;


    public function __construct($MerchantID = 0) {

       /*
        * Connect DB
        */
        $this->db = connecti('mvalezy');
        
        /*
         * Inizialize Vars
         */
        $MerchantID = $this->db->real_escape_string($MerchantID);
        $this->Merchants = array();

        /*
         * Query Merchant Credentials
         */
        if($MerchantID > 0) {
            
            $this->MerchantID = $MerchantID;

            $query = "SELECT * FROM ScanMarketplace WHERE ID = $MerchantID LIMIT 1;";
            $sql = $this->db->query($query);
            if(isset($sql->num_rows) && $sql->num_rows > 0) {
                $row = $sql->fetch_object();

                $this->ID = $row->ID;
                $this->API_ENV = $row->API_ENV;
                $this->Marketplace = $row->Marketplace;
                $this->merchantGroupId = $row->merchantGroupId;
                $this->wsLogin = $row->wsLogin;
                $this->wsPassword = $row->wsPassword;
                $this->TechnicalAccountLogin = $row->TechnicalAccountLogin;
                $this->CommissionAccountLogin = $row->CommissionAccountLogin;
                $this->pastDate = $row->pastDate;
                $this->Limit = $row->Limit;
                $this->Delay = $row->Delay;
            }
            else die("No merchant found with ID $MerchantID.");

            $this->locale = 'en_GB';

            $this->EndPoint();
            $this->SoapUserAccount();
        }
    }

    /*
     * LIST MERCHANTS IN DB
     */
    /**
     *
     */
    public function ListMerchants () {

        $query = "SELECT ID, API_ENV, Marketplace FROM ScanMarketplace WHERE merchantGroupId > 0 ORDER BY Marketplace ASC ";
        $sql = $this->db->query($query);

        $i=0;
        if(isset($sql->num_rows) && $sql->num_rows > 0) {
            while ($row = $sql->fetch_object()) {
                
                $this->Merchants[$i] = new stdClass();

                $this->Merchants[$i]->ID            = $row->ID;
                $this->Merchants[$i]->Marketplace   = $row->Marketplace;
                $this->Merchants[$i]->API_ENV       = $row->API_ENV;

                if ($row->API_ENV == 'PROD') {
                    $this->Merchants[$i]->color = 'danger';
                } else {
                    $this->Merchants[$i]->color = 'warning';
                }

                $i++;
            }
        }
    }

    /*
     * DEFINE ENDPOINTS
     */
    private function EndPoint () {
        define('API_ENV', $this->API_ENV);
        if(API_ENV == 'TEST') {
            define('API_SOAP_ENDPOINT', 'https://test-ws.hipay.com/soap/');
            define('API_REST_ENDPOINT', 'https://test-merchant.hipaywallet.com/api/identification.json');
        }
        elseif(API_ENV == 'PROD') {
            define('API_SOAP_ENDPOINT', 'https://ws.hipay.com/soap/');
            define('API_REST_ENDPOINT', 'https://merchant.hipaywallet.com/api/identification.json');
        }
    }

    /*
     * INITIALIZE SOAP FOR USER ACCOUNT
     */
    private function SoapUserAccount () {

        // SOAP FLOW OPTIONS
        $soap_options = array(
            'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'soap_version' => SOAP_1_1,
            'encoding' => 'UTF-8',
            'trace' => true,
        );

        // USER ACCOUNT SOAP
        $this->client = new SoapClient(API_SOAP_ENDPOINT.'user-account-v2?wsdl', $soap_options);
    }


    /*
     * USER ACCOUNT
     * GET MERCHANTS
     */
    public function GetMerchantsGroupAccounts() {
        
        if(!$this->client) { die("Soap call not initialized."); }
        
        $resultGetMerchantsGroupAccounts = $this->client->GetMerchantsGroupAccounts(array('parameters'=>array(
            'merchantGroupId' => $this->merchantGroupId,
            'pastDate' => $this->pastDate,
            'wsLogin' => $this->wsLogin,
            'wsPassword' => $this->wsPassword,
        )));
        if($this->debug) { echo 'resultGetMerchantsGroupAccounts'; krumo($resultGetMerchantsGroupAccounts); }

        // IF Merchant List found
        if(isset($resultGetMerchantsGroupAccounts->getMerchantsGroupAccountsResult->dataMerchantsGroupAccounts) && $resultGetMerchantsGroupAccounts->getMerchantsGroupAccountsResult->code == 0 && is_array($resultGetMerchantsGroupAccounts->getMerchantsGroupAccountsResult->dataMerchantsGroupAccounts->item) && count($resultGetMerchantsGroupAccounts->getMerchantsGroupAccountsResult->dataMerchantsGroupAccounts->item) > 1) {

            // Store Title
            $this->merchantGroupName = $resultGetMerchantsGroupAccounts->getMerchantsGroupAccountsResult->merchantGroupName;

            // List Merchants
            foreach($resultGetMerchantsGroupAccounts->getMerchantsGroupAccountsResult->dataMerchantsGroupAccounts->item as $MerchantId => $Merchant) {

                if ($MerchantId < $this->Limit && $this->TechnicalAccountLogin != $Merchant->email) {
                    
                    /*
                     * USER ACCOUNT
                     * GET ACCOUNT INFO
                     */
                    // If Account exists : Process Identification, KYC and BankInfo, getBalance
                    if($this->getAccountInfos($Merchant->accountId)) {

                        $this->Merchants[$MerchantId] = new stdClass();

                        /*
                         * STORE MERCHANT USER ACCOUNT DATA (identified)
                         */
                        $this->Merchants[$MerchantId]->Merchant = $this->Merchant;

                        /*
                         * STORE MERCHANT GENERIC DATA
                         */
                        $this->Merchants[$MerchantId]->Merchant->accountId       = $Merchant->accountId;
                        $this->Merchants[$MerchantId]->Merchant->accountLogin    = $Merchant->email;
                        $this->Merchants[$MerchantId]->Merchant->creationDate    = $Merchant->creationDate;

                        /*
                         * USER ACCOUNT
                         * GET KYC IF NOT IDENTIFIED
                         */
                        // If Account not identified
                        if($this->Merchants[$MerchantId]->Merchant->identified == 'no') {
                            if($this->getKYC($Merchant->accountId)) {

                                // Browse Documents
                                $this->Merchants[$MerchantId]->Merchant->documents = array();

                                foreach ($this->responsegetKYC->documents as $document) {
                                    switch($document->status_code) {
                                        case -1: // No document has been uploaded
                                            break;
                                        case 0: // The document has been uploaded but not sent
                                        case 1: // The document has been sent to HiPay
                                        case 5: // The document is being reviewed
                                        case 9: // A new review of the document is in progress
                                        $this->Merchants[$MerchantId]->Merchant->documents[$document->type] = '<span data-toggle="tooltip" data-placement="top" title="Status code: '.$document->status_code.'">'.trim($document->status_label).'</span>';
                                            break;
                                        case 2: // The document has been validated for identification
                                            $this->Merchants[$MerchantId]->Merchant->documents[$document->type] = '<span data-toggle="tooltip" data-placement="top" title="Status code: '.$document->status_code.'">'.trim($document->status_label).'</span>';
                                            break;
                                        case 3: // The document has been refused because it is falsified, expired or inconsistent
                                        case 8: // The document has been refused
                                        if(!isset($document->message) OR $document->message == "") { $document->message = 'N/A'; }
                                        $this->Merchants[$MerchantId]->Merchant->documents[$document->type] = '<span data-toggle="tooltip" data-placement="top" title="Status code: '.$document->status_code.'">'.trim($document->status_label).': '.trim($document->message).'</span>';
                                            break;
                                    }

                                }

                            }
                            else {
                                // No documents
                                $this->Merchants[$MerchantId]->Merchant->documents = "No KYC";
                            }
                        }
                        else {
                            // If account identified set KYC OK
                            $this->Merchants[$MerchantId]->Merchant->documents = "OK";
                        }


                        /*
                         * USER ACCOUNT
                         * GET BANK INFO STATUS
                         */
                        if($this->bankInfosStatus($Merchant->accountId)) {
                            $this->Merchants[$MerchantId]->Merchant->bankInfosStatus = trim($this->resultbankInfosStatus->bankInfosStatusResult->status);
                        }
                        else {
                            $this->Merchants[$MerchantId]->Merchant->bankInfosStatus = 'N/A';
                        }
                        
                        /*
                        * GET ACCOUNT BALANCE
                        */
                        $this->Merchants[$MerchantId]->Merchant->getBalance = $this->getBalance($Merchant->accountId);

                    }
                    else {
                        // If Account cannot be Set vars to not found
                        $this->Merchants[$MerchantId]->Merchant->identified = 'N/A';
                        $this->Merchants[$MerchantId]->Merchant->bankInfosStatus = 'N/A';
                    }
                }

                // Delay between 2 requests
                sleep($this->Delay);

            }
        }


        return true;
    }



    /*
     * USER ACCOUNT
     * GET ACCOUNT INFO
     */
    public function getAccountInfos($accountId = 0, $accountLogin = '') {
        
        $this->Merchant = new stdClass();
        
        if(!$this->client) { die("Soap call not initialized."); }

        // Soap call on getBalance getAccountInfos method for Vendor or Commission
        if($accountId > 0) {
            $resultgetAccountInfos = $this->client->getAccountInfos(array('parameters'=>array(
                'accountId' => $accountId,
                'wsLogin' => $this->wsLogin,
                'wsPassword' => $this->wsPassword,
            )));
        }

        // Soap call on getBalance getAccountInfos method for Technical Account
        else {
            $resultgetAccountInfos = $this->client->getAccountInfos(array('parameters'=>array(
                'accountLogin' => $accountLogin,
                'wsLogin' => $this->wsLogin,
                'wsPassword' => $this->wsPassword,
            )));
        }

        if($this->debug) { echo 'resultgetAccountInfos'; krumo($resultgetAccountInfos); }

        if(isset($resultgetAccountInfos->getAccountInfosResult) && $resultgetAccountInfos->getAccountInfosResult->code == 0) {
            $this->Merchant->identified      = trim($resultgetAccountInfos->getAccountInfosResult->identified);
            //$this->Merchant->callbackUrl     = $resultgetAccountInfos->getAccountInfosResult->callbackUrl;

            return true;
        }
        else {
            unset($this->Merchant);
            return false;
        }
    }


     /*
     * GET ACCOUNT BALANCE
     */
     public function getBalance($accountId = 0) {
         
         if(!$this->client) { die("Soap call not initialized."); }

         // Soap call on getBalance getBalance method for Vendor or Commission
         if($accountId > 0) {
             $resultgetBalance = $this->client->getBalance(array('parameters'=>array(
                 'wsSubAccountId' => $accountId,
                 'wsLogin' => $this->wsLogin,
                 'wsPassword' => $this->wsPassword,
             )));
         }

         // Soap call on getBalance getBalance method for Technical Account
         else {
             $resultgetBalance = $this->client->getBalance(array('parameters'=>array(
                 'wsLogin' => $this->wsLogin,
                 'wsPassword' => $this->wsPassword,
             )));
         }

         if ($this->debug) { echo 'resultgetBalance'; krumo($resultgetBalance); }

         // IF Balance found
         if (isset($resultgetBalance->getBalanceResult) && $resultgetBalance->getBalanceResult->code == 0 && isset($resultgetBalance->getBalanceResult->balances->item)) {
             if (is_array($resultgetBalance->getBalanceResult->balances->item)) {
                 foreach ($resultgetBalance->getBalanceResult->balances->item as $key => $balance) {
                     if (isset($balance->userAccountType) && $balance->userAccountType == 'main') {
                         $getBalance = $balance->balance . ' ' . $balance->currency;
                     }
                 }
             } else {
                 $getBalance = $resultgetBalance->getBalanceResult->balances->item->balance . ' ' . $resultgetBalance->getBalanceResult->balances->item->currency;
             }
         } 
         else {
             $this->getBalance = 'N/A';
         }

         return $getBalance;
     }


     /*
     * USER ACCOUNT
     * GET KYC IF NOT IDENTIFIED
     */
    public function getKYC ($accountId) {

        // Rest call on get KYC method
        $credentials = $this->wsLogin . ':' . $this->wsPassword;
        $resource = API_REST_ENDPOINT;

        $curl = curl_init();

        $header = array(
            'Accept: application/json',
            'php-auth-subaccount-id: ' . $accountId,
        );

        $rest_options = array(
            CURLOPT_URL => $resource,
            CURLOPT_HTTPHEADER => $header,
            CURLOPT_USERPWD => $credentials,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FAILONERROR => false,
            CURLOPT_HEADER => false,
            CURLOPT_POST => false,
        );

        foreach ($rest_options as $option => $value) {
            curl_setopt($curl, $option, $value);
        }

        if (false === ($resultgetKYC = curl_exec($curl))) {
            throw new RuntimeException(curl_error($curl), curl_errno($curl));
        }

        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $this->responsegetKYC = json_decode($resultgetKYC);
        curl_close($curl);

        if ($this->debug) { echo 'responsegetKYC'; krumo($this->responsegetKYC); }

        // Test If documents uploaded ?
        if (isset($this->responsegetKYC) && $this->responsegetKYC->code == 0) {
            // Found documents ?
            if (isset($this->responsegetKYC->documents) && is_array($this->responsegetKYC->documents) && count($this->responsegetKYC->documents) > 0) {
                return true;
            } else {
                // No documents ?
                return false;
            }
        }

    }




    /*
      * USER ACCOUNT
      * GET BANK INFO STATUS
      */
    public function bankInfosStatus($accountId) {
        
        if(!$this->client) { die("Soap call not initialized."); }
        
        // Soap call on bankInfosStatus method
        $this->resultbankInfosStatus = $this->client->bankInfosStatus(array('parameters' => array(
            'wsSubAccountId' => $accountId,
            'locale' => $this->locale,
            'wsLogin' => $this->wsLogin,
            'wsPassword' => $this->wsPassword,
        )));

        if ($this->debug) { echo 'resultbankInfosStatus'; krumo($this->resultbankInfosStatus); }
        if (isset($this->resultbankInfosStatus->bankInfosStatusResult) && $this->resultbankInfosStatus->bankInfosStatusResult->code == 0) {
            return true;
        } else {
            return false;
        }

    }







}