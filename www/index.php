<?php
	error_reporting((E_ALL | E_STRICT) ^ E_NOTICE);
	ini_set('track_errors', true);
?>
<html>
<head>
<title>Updog</title>
<meta name="viewport" content="width=320" />
<meta name="viewport" content="initial-scale=1.0" />
<meta name="viewport" content="user-scalable=false" />
<link href='http://fonts.googleapis.com/css?family=Lato:300,400' rel='stylesheet' type='text/css'>
<style>
body {
	background-color: #eed;
	font-family: 'Lato', sans-serif;
	text-align: center;
	margin-bottom: 100px;
}

h1 {
	font-size: 400%;
	margin-top: 20%;
	color: #333;
	font-weight: normal;
}

h2 {
	font-weight: normal;
	margin-top: 0;
}

a {
	color: #333;
	text-decoration: none;
}

a:hover {
	text-decoration: underline;
}

#warning {
	color: red;
	display: none;
}

#results {
	width: 600px;
	margin: 0 auto;
	text-align: left;
	background-color: white;
	border: 1px solid #ccc;
	border-radius: 10px;
	padding: 16px;
}

input {
	font-size: 200%;
	padding: 8px;
	border: 1px solid #ccc;
	border-radius: 4px;
}

</style>
<script async src="https://www.googletagmanager.com/gtag/js?id=G-T0MJT6FEG8"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-T0MJT6FEG8');
</script>
</head>
<body>

	<h1>Updog?</h1>

<?php
	function clean_domain($in){
		return preg_replace('![^a-z0-9-.]!', '', StrToLower($in));
	}

	$domain = '';

	if ($_GET['domain']){
		include('../include/lib.php');
		$domain = clean_domain($_GET['domain']);
	}
?>

	<form id="form">
	<input type="text" id="input" value="<?php echo $domain; ?>" placeholder="foo.com" /><br />
	<div id="warning">That's not a valid domain name</div>
	</form>

<script>
document.getElementById('form').onsubmit = function(){
	var d = ""+document.getElementById('input').value;
	d = d.toLowerCase();
	var dt = d.replace(/[^a-z0-9-.]/, '');
	if (d.length && d === dt){
		window.location.href = "/"+d;
	}else{
		document.getElementById('warning').style.display = 'block';
	}
	return false;
};
</script>

<?php
	if (strlen($domain)){

		$ret = http_test("http://$domain/", 80);
		unset($ret['body']);

		echo "<div id=\"results\">\n";
		output($ret);
		dumper($ret);	
		echo "</div>\n";
	}
?>

</body>
</html>

<?php

	function output($ret){

		if ($ret['ok']){

			#
			# show timings
			#

			$ms = number_format($ret['total_ms']);

			echo "<h2>Success in {$ms}ms</h2>";

			return;
		}

		if ($ret['code']){
			echo "<h2>HTTP Reponse Code {$ret['code']}</h2>";
			return;
		}

		if ($ret['curl_errno']){
			echo "<h2>".HtmlSpecialChars($ret['curl_error'])."</h2>";
			return;
		}

		echo "<h2>Unknown Error :(</h2>";
	}

?>
