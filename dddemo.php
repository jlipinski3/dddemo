<!doctype HTML>
<html>
<head>
<title>Demo for Direct Digital (Joshua Lipinski)</title>
<style>
html {font-size: 100%; font: .9em arial narrow}
td {text-align: right}
th {text-align: center}
</style>
</head>
<body>
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$mysqli = new mysqli("localhost", "freshed_dddemo", "ddd3m0", "freshed_dddemo"); //very secure here :-)
if($mysqli->connect_errno){die("db connect failed");}

$key_query = array(
"ads" => "SELECT ad_id, sum(daily_views) as total_views from ad_stats group by ad_id",
"orders" => "SELECT * FROM orders JOIN leads on leads.lead_id = orders.lead_id"
//,"leads" => "SELECT * FROM leads JOIN ad_stats on ad_stats.ad_id = leads.ad_id"
);

//CACHE THE DATA BECAUSE THE QUERIES TAKE FOREVER...
$cache_file = dirname(__FILE__) . "/dddemo.json"; //where to save json data
$cached_data = @json_decode(file_get_contents($cache_file), true); //check if this data is stored locally
//$cached_data = ""; //just to force hard pull

//FOR FIRST PULL OR IF CACHE ISNT AVAILABLE
if(empty($cached_data))
{
	foreach($key_query as $k => &$v){
		$fetch = $mysqli->query($v); //grab whats needed
		$v = [];
		while($row = $fetch->fetch_assoc()){$v[] = $row;} //overwrite query array with results
	}
	file_put_contents($cache_file, json_encode($key_query)); //save json because the query takes forever
} else {
	foreach($key_query as $k => &$v){$v = $cached_data[$k];}
}

//DO PHP MANIPULATION HERE
foreach($key_query["ads"] as &$ad){
	if(!isset($ad["orders"])){$ad["orders"]=[];} //create orders subarray
	$ad["orders"] = array_values(array_filter($key_query["orders"], function($v)use($ad){return $v["ad_id"]==$ad["ad_id"];})); //use array filter to add orders data to each ad
	$add_vals = array("total_rev" => 0, "average_age" => 0, "states" => array(), "ctr" => 0, "conversions" => array(), "conversion_rate" => 0);
	
	//ADD ORDERS-RELATED DATA TO AD LEVEL
	array_walk($ad["orders"], function($v)use(&$add_vals){
		$add_vals["total_rev"] += ($v["unit_price"] * $v["quantity"] + $v["shipping"]); //unit price times quantity plus shipping
		if(!isset($add_vals["states"][$v["state"]])){$add_vals["states"][$v["state"]] = 1;}else{$add_vals["states"][$v["state"]]++;} //tally up sales by state
		if(!in_array($v["lead_id"], $add_vals["conversions"])){$add_vals["conversions"][] = $v["lead_id"];} //tally up conversions being careful not to dupe
		$dob = new DateTime($v["dob"]);
		$now = new DateTime();
		$diff = abs($dob->getTimestamp()-$now->getTimestamp());
		$add_vals["average_age"] += $diff;
	});
	
	//ADD LEADS-RELATED DATA TO AD LEVEL - cant grab this data as the leads data is too heavy for this system
	/*if(!isset($ad["leads"])){$ad["leads"]=[];} //create leads subarray
	$ad["leads"] = array_values(array_filter($key_query["leads"], function($v)use($ad){return $v["ad_id"]==$ad["ad_id"];})); //use array filter to add leads data to each ad
	array_walk($ad["leads"], function($v)use(&$add_vals){
		$dob = new DateTime($v["dob"]);
		$now = new DateTime();
		$diff = abs($dob->getTimestamp()-$now->getTimestamp());
		$add_vals["average_age"] += $diff;
	});*/
	
	//STATES
	arsort($add_vals["states"]); //sort states so most is first
	$best = reset($add_vals["states"]);
	$worst = end($add_vals["states"]);
	$add_vals["best_state"] = implode(", ", array_keys(array_filter($add_vals["states"], function($v)use($best){return $v==$best;}))) . " (" . $best . ")"; //get string of best states by value
	$add_vals["worst_state"] = implode(", ", array_keys(array_filter($add_vals["states"], function($v)use($worst){return $v==$worst;}))) . " (" . $worst . ")"; //get string of worst states by value
	
	//CONVERSIONS
	$add_vals["conversion_rate"] = (count($add_vals["conversions"])/$ad["total_views"]*100); //total conversions divided by total views
	
	//CLICK-THRU - cant complete due to missing leads data
	
	//AGE
	$add_vals["average_age"] /= count($ad["orders"]); //set average age number of seconds
	$add_vals["average_age"] /= (60 * 60 * 24 * 365); //60s in 60min in 24hr in 365days to a year...not accounting for leaps
	//print_r($add_vals);
	$ad = array_merge($ad, $add_vals);
}

//echo sprintf("<pre>%s</pre>", print_r(array_values($key_query["ads"]), true)); //for debug

usort($key_query["ads"], function($a,$b){return $a["conversion_rate"]<$b["conversion_rate"];}); //sort descending by conv rate
$output = ""; //to compile html
array_walk($key_query["ads"], function($v)use(&$output){ //have to walk through each key because of multiple formats and types
	$output .= "<tr><td>" . $v["ad_id"] . "</td><td>" . number_format($v["total_views"]) . "</td><td>" . $v["ctr"] . "</td><td>" . number_format($v["conversion_rate"], 2) . "%</td><td>\$" . number_format($v["total_rev"], 2) . "</td><td>" . number_format($v["average_age"], 1) . " years</td><td>" . $v["best_state"] . "</td><td>" . $v["worst_state"] . "</td></tr>";
});

echo "<table><tr>
    <th>Ad ID</th>
    <th>All-time Total Views</th>
    <th>All-time Click Through Rate %</th>
    <th>All-time Conversion Rate %</th>
    <th>All-time Total revenue</th>
    <th>All-time Average Customer Age (for orders)</th>
    <th>All-time Best State(s)</th>
    <th>All-time Worst State(s)</th>
</tr>" . $output . "</table>"; //display table

?>
</body>
</html>