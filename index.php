<?php

    /*
     * SCAN TOOL FOR MARTERPLACE
     * Matthieu HiPay 2016-09-30
     * PRESENTATION
     * Display Marketplace accounts and activity.
     * This tool will collect all created accounts in a merchant group, and request all important details for each account.
     * If an account is not identified it will also request the complete list of sent and pending KYC.
     *
     * HOW TO - NOTES
     * Insert the Marketplace in the database mvalezy, table ScanMarketplace. You'll need to insert the credentials of the technical account and the important email.
     * A MerchantID will be generated for each new Marketplace (increment).
     * For now, it only works for a Marketplace with 1 technical account.
     * Customize
     * - the cache age with $cachetime
     * - the list of existing KYC with $DocumentType
     * - the SQL connection in functions.php
     *
     * CACHING
     * Each scan will be stored in the cache folder, and instead of scanning each time, the tool will display the memorized data.
     * The default cache age is 6 days, you can customize it with $cachetime
     * To force a new scan, click on the Clear cache link at the bottom of the table, or call the MerchantID with the GET parameter &purge=1
     *
     * DEBUG
     * call the merchant with the GET parameter &debug=1
     *
     * CHANGELOG
     * 2016-10-07 : Fix Request to get Uploaded KYC. Deleted max KYC and Added an Array to manage Existing KYC List. Added script loading time.
     * 2016-10-05 : Added Loader image to prevent a user to scan 2 Marketplace at a time. Added Changelog
     * 2016-09-30 : First release
     *
     */

    /*
     * Initialize Vars
     */

    // Config Cache Time (seconds);
    $cachetime = 518400; // 518400 = 6 jours

    // KYC List
    $DocumentType = array(
        1 => "ID proof",
        2 => "Proof of address",
        3 => "Identity card",
        4 => "Company Registration",
        5 => "Distribution of power",
        6 => "Bank",
        7 => "ID proof",
        8 => "Company Registration",
        9 => "Tax status",
        10 => '',
        11 => "President of association",
        12 => "Official Journal",
        13 => "Association status"
    );

    /*
     * Set fonctions Matthieu HiPay
     */
    // Connexion BDD
    require("/home/mvalezy/public_html/tools/functions.php");
    // Classes API Wallet Cash-out
    require("/home/mvalezy/public_html/tools/ScanMarketplace/ScanMarketplace.class.php");

    /*
     * GET Vars
     */
    if(isset($_GET['MerchantID']) && $_GET['MerchantID'] > 0) {
        $MerchantID = (int) $_GET['MerchantID'];
    }
    else $MerchantID = 0;

    if(isset($_GET['debug']) && $_GET['debug'] > 0) { $debug = $_GET['debug']; }
    else $debug = 0;

    if(isset($_GET['purge']) && $_GET['purge'] > 0) { $purge = $_GET['purge']; }
    else $purge = 0;

    // Initialize Class
    $ScanMarketplace = new ScanMarketplace($MerchantID);
    $ScanMarketplace->debug = $debug;

?><!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-type" content="text/html; charset=utf-8">
        <meta charset="utf-8">
        <title>Scan Marketplace<?php if($MerchantID) { echo " - $ScanMarketplace->Marketplace - ".date('Ymd'); } ?></title>
        
        <script src="//code.jquery.com/jquery-3.1.0.min.js" integrity="sha256-cCueBR6CsyA4/9szpPfrX3s49M9vUU5BgtiJj06wt/s=" crossorigin="anonymous"></script>
    
        <!-- Latest compiled and minified CSS -->
        <link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">

        <!-- Optional theme -->
        <link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap-theme.min.css" integrity="sha384-rHyoN1iRsVXV4nD0JutlnGaslCJuC7uwjduW9SVrLvRYooPp2bWYgmgJQIXwl/Sp" crossorigin="anonymous">

        <!-- Latest compiled and minified JavaScript -->
        <script src="//maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>
    
        <!-- Datatables -->
        <link href="//cdn.datatables.net/1.10.12/css/dataTables.bootstrap.min.css" media='screen' rel='stylesheet' type='text/css'/>
        <script src="//cdn.datatables.net/1.10.12/js/jquery.dataTables.min.js" type='text/javascript'></script>
        <script src="//cdn.datatables.net/1.10.12/js/dataTables.bootstrap.min.js" type='text/javascript'></script>
        
        <!-- Datatable Buttons -->
        <link rel="stylesheet" href="//cdn.datatables.net/buttons/1.2.2/css/buttons.dataTables.min.css" crossorigin="anonymous">
        <script src="//cdn.datatables.net/buttons/1.2.2/js/dataTables.buttons.min.js" type='text/javascript'></script>
        <script src="//cdnjs.cloudflare.com/ajax/libs/jszip/2.5.0/jszip.min.js" type='text/javascript'></script>
        <script src="//cdn.rawgit.com/bpampuch/pdfmake/0.1.18/build/pdfmake.min.js" type='text/javascript'></script>
        <script src="//cdn.rawgit.com/bpampuch/pdfmake/0.1.18/build/vfs_fonts.js" type='text/javascript'></script>
        <script src="//cdn.datatables.net/buttons/1.2.2/js/buttons.html5.min.js" type='text/javascript'></script>
        
        <script type="text/javascript">
            $(document).ready(function() {

                $('a.showloader').click(function(e) {
                    $('.loader').show();
                });

                $('#merchants').DataTable({
                    "columnDefs": [ {
                        "targets": [<?php $loopmax = count($DocumentType) * -1; for($i=-1; $i>=$loopmax; $i--) { echo "$i,"; } ?>],
                        "orderable": false
                      } ],
                    dom: 'Bfrtlip',
                    buttons: [
                        'copyHtml5',
                        'excelHtml5',
                        'csvHtml5',
                        'pdfHtml5'
                    ]
                });
            });
        </script>
        
        <style>
            .dataTables_length { float:left; margin-top:3px; }
            .dataTables_info  { float:left; padding-left:20px;}
            .btn-circle {
                width: 30px;
                height: 30px;
                text-align: center;
                padding: 6px 0;
                font-size: 12px;
                line-height: 1.428571429;
                border-radius: 15px;
            }
            .btn-circle.btn-lg {
                width: 50px;
                height: 50px;
                padding: 10px 16px;
                font-size: 18px;
                line-height: 1.33;
                border-radius: 25px;
            }
            .btn-circle.btn-xl {
                width: 70px;
                height: 70px;
                padding: 10px 16px;
                font-size: 24px;
                line-height: 1.33;
                border-radius: 35px;
            }
            .loader {
                display:none;
            }
            .loader div {
                position: fixed;
                left: 0px;
                top: 0px;
                width:100%;
                height:100%;
            }

            .loader-bg {
                z-index: 9998;
                filter:alpha(opacity=50);
                opacity:0.5;
                background-color:white;
            }

            .loader-img {
                z-index: 9999;
                background: url('../images/Preloader_7.gif') 50% 50% no-repeat;
                filter:alpha(opacity=100);
                opacity:1;
            }

        </style>
        
        </head>
    <body>
        
        <div class="container-fluid">
            <h1><a href="index.php">Scan Marketplace</a> <?php if($MerchantID > 0) { echo '<a href="index.php" class="btn btn-primary"><span class="glyphicon glyphicon-list"></span> Back to list</a></title>'; } ?></h1>

<?php

if($MerchantID > 0) {
    
    /*
     * CACHE MANAGEMENT
     */
    
    $url = $_SERVER["SCRIPT_NAME"];
    $break = Explode('/', $url);
    $file = $break[count($break) - 1];
    $cachefile = '/home/mvalezy/public_html/tools/ScanMarketplace/cache/cached-'.substr_replace($file ,"",-4).$MerchantID.'.html';

    // Serve from the cache if it is younger than $cachetime
    if ($debug == 0 && $purge == 0 && (file_exists($cachefile) && time() - $cachetime < filemtime($cachefile))) {
        $cachemessage = "Cached extract for Merchant #$MerchantID - created ".date('d/m H:i:s', filemtime($cachefile));
        echo "<!-- $cachemessage -->\n";
        include($cachefile);
    }
    else {
        ob_start(); // Start the output buffer

        /*
         * TECHNICAL ACCOUNT
         * Traitement à part car si une limite est fixée ce compte pourrait ne pas être traité
         */

        $TechnicalAccount = new stdClass();
        if($ScanMarketplace->getAccountInfos(0, $ScanMarketplace->TechnicalAccountLogin)) {
            //$TechnicalAccount->callbackUrl  = $ScanMarketplace->Merchant->callbackUrl;
            $TechnicalAccount->getBalance   = $ScanMarketplace->getBalance();
        }
        else die("Technical Account not found.");

        /*
         * USER ACCOUNT
         * GET MERCHANTS
         */
        if($ScanMarketplace->GetMerchantsGroupAccounts()) {

            // Display Title
            echo "<h2><a title='Merchant Group ID = $ScanMarketplace->merchantGroupId' href=\"index.php?MerchantID=$MerchantID\">".$ScanMarketplace->merchantGroupName."</a></h2>";

            // Display Balance
            echo '<h3><button type="button" class="btn btn-secondary btn-circle"><i class="glyphicon glyphicon-piggy-bank"></i></button> Technical account</h3>';
            echo '<p>Balance on '.date("d/m/Y H:i").' : <strong>'.$TechnicalAccount->getBalance.'</strong></p>';
            
            if(isset($TechnicalAccount->callbackUrl) && $TechnicalAccount->callbackUrl != '') {
                echo '<p>Callback URL : <small><em>'.$TechnicalAccount->getBalance.'</em></small></p>';
            }
            /*else {
                echo '<p class="text-danger">No callback Url on this marketplace.</p>';
            }*/
        }

        /*
         * DISPLAY MERCHANT TABLE
         */
        if(isset($ScanMarketplace->Merchants) && is_array($ScanMarketplace->Merchants) && count($ScanMarketplace->Merchants) > 1) {
            if($debug) { echo 'Merchants'; krumo($ScanMarketplace->Merchants); }
    ?>
            <h3><button type="button" class="btn btn-secondary btn-circle"><i class="glyphicon glyphicon-user"></i></button> Accounts List</h3>
            <div class="table-responsive">
            <table id="merchants" class="table" cellspacing="0" width="100%"><!--  table-striped -->
            <thead>
                <tr>
                    <th rowspan="2">#</th>
                    <th rowspan="2">Account<br />ID</th>
                    <th rowspan="2">Email</th>
                    <th rowspan="2">Created</th>
                    <th rowspan="2">Balance</th>
                    <th rowspan="2">Bank Info Status</th>
                    <th rowspan="2">Identified</th>
                    <th rowspan="2">KYC</th>
                    <th colspan="<?=count($DocumentType)?>">Document type</th>
                </tr>
                <tr>
                    <?php for($i=1; $i<count($DocumentType); $i++) { echo '<th><span data-toggle="tooltip" data-placement="top" title="'.$DocumentType[$i].'">'.$i.'</span></th>'; } ?>
                </tr>
            </thead>
            <tbody>
    <?php
        foreach($ScanMarketplace->Merchants as $MerchantId => $Detail) {
            if($ScanMarketplace->CommissionAccountLogin == $Detail->Merchant->accountLogin) { $color = 'info'; }
            elseif($Detail->Merchant->identified == 'no') { $color = 'danger'; }
            elseif($Detail->Merchant->bankInfosStatus == 'Non saisies') { $color = 'warning'; }
            else $color = 'success';

            if($Detail->Merchant->identified == 'no') { $identified_icon = '<a href="#" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-remove"></span> </a>'; }
            else { $identified_icon = '<a href="#" class="btn btn-xs btn-success"><span class="glyphicon glyphicon-ok"></span> </a>'; }

            if($Detail->Merchant->bankInfosStatus == 'No bank information') { $bankinfo_icon = '<a href="#" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-remove"></span> </a>'; }
            elseif($Detail->Merchant->bankInfosStatus == 'Validated') { $bankinfo_icon = '<a href="#" class="btn btn-xs btn-success"><span class="glyphicon glyphicon-ok"></span> </a>'; }
            else { $bankinfo_icon = '<a href="#" class="btn btn-xs btn-warning"><span class="glyphicon glyphicon-time"></span> </a>'; }
    ?>
                <tr>
                    <td><?=$MerchantId+1?></td>
                    <td class="<?=$color?>"><?=$Detail->Merchant->accountId?></td>
                    <td class="<?=$color?>"><?=$Detail->Merchant->accountLogin?></td>
                    <td><?=$Detail->Merchant->creationDate?></td>
                    <td align="right"><?=$Detail->Merchant->getBalance?></td>
                    <td><?=$bankinfo_icon?> <?=$Detail->Merchant->bankInfosStatus?></td>
                    <td><?=$identified_icon?> <?=$Detail->Merchant->identified?></td>
<?php
                    if(isset($Detail->Merchant->documents) && is_array($Detail->Merchant->documents)) {
?>
                    <td><?php if(count($Detail->Merchant->documents) > 0) { echo 'Pending'; }
                    else { echo 'No KYC'; } ?></td>
                    <?php
                        for($i=1; $i<count($DocumentType); $i++) {
                            if(isset($Detail->Merchant->documents[$i])) { ?>
                            <td><?=$Detail->Merchant->documents[$i]?></td>
                        <?php } else echo "<td>&nbsp;</td>"; ?>
                    <?php
                        }
                    }
                    else { ?><td><?=$Detail->Merchant->documents?></td><?php for($i=1; $i<count($DocumentType); $i++) { echo "<td>&nbsp;</td>"; } } ?>
                </tr>
    <?php
            }
    ?>
        </tbody>
        </table>
        </div><!-- Fin Div Table -->
        </div><!-- Fin Div Globale -->
    <?php 
        } // END DISPLAY MERCHANTS TABLE

        // Cache the contents to a file
        $cached = fopen($cachefile, 'w');
        fwrite($cached, ob_get_contents());
        fclose($cached);
        ob_end_flush(); // Send the output to the browser

        // Display Loading time
        // Loading Time
        $loading_time = microtime();
        $loading_time = explode(' ', $loading_time);
        $loading_time = $loading_time[1] + $loading_time[0];
        $loading_finish = $loading_time;
        $loading_total_time = round(($loading_finish - $loading_start), 4);
        ?>
        <div class="container-fluid">
            <p><small>Page generated in <?=$loading_total_time?> seconds</small></p>
        </div>
<?php

        } // End IF NO CACHE

}
else {

    /*
     * LIST MERCHANTS IN DB
     */  

    $ScanMarketplace->ListMerchants();
?>
        <div class="col-md-5">
            <div class="list-group">
                <a href="#" class="list-group-item list-group-item-action active">
                  <h5 class="list-group-item-heading">Select the Marketplace to analyze :</h5>
                  <p class="list-group-item-text">Loading may take more than 2 minuts.<br />Please, do NOT spam click, only load 1&nbsp;marketplace at a time.</p>
                </a>
<?php
    if(is_array($ScanMarketplace->Merchants)) {
        foreach($ScanMarketplace->Merchants as $Merchant) {
            echo '<a href="index.php?MerchantID=' . $Merchant->ID . '" data-loading-text="Loading..." class="list-group-item list-group-item-action showloader">';
            echo '<h5 class="list-group-item-heading">' . $Merchant->Marketplace . '</h5>';
            echo '<p class="list-group-item-text">'.$Merchant->API_ENV.'</p>';
            echo '</a>';
        }
    }
?>
            </div>
        </div><!-- Fin Div Table -->
        </div><!-- Fin Div Globale -->
        <div class="container-fluid" style="margin-top:50px;"><p><small><em>Scan version 1.0 - <?=date("d/m/Y H:i:s", filemtime("/home/mvalezy/public_html/tools/ScanMarketplace/index.php"))?>.</em></small></p></div>
<?php }

// Display Cache message
if(isset($cachemessage)) {
?>
        <div class="container-fluid">
            <p><small><?=$cachemessage?> - <a href="index.php?MerchantID=<?=$MerchantID?>&purge=1" class="showloader">Clear cache</a></small></p>
        </div>
<?php } ?>
        </div>
        <div class="loader"><div class="loader-bg"></div><div class="loader-img"></div></div>
    </body>
</html>