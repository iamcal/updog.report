<?php

	function http_test($url, $port){

		$options = array(
			'connect_timeout'	=> 10*1000,
			'transfer_timeout'	=> 10*1000,
			'useragent'		=> 'Updog/1.0 (http://updog.report/)',
			'max_follows'		=> 3,
		);

		$headers = array(
		);

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_PROTOCOLS,		CURLPROTO_HTTP | CURLPROTO_HTTPS);
		curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS,	CURLPROTO_HTTP | CURLPROTO_HTTPS);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_HTTPHEADER, _http_prepare_outgoing_headers($headers));
		curl_setopt($ch, CURLOPT_URL, $url);

		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $options['connect_timeout']);
		curl_setopt($ch, CURLOPT_TIMEOUT_MS, $options['transfer_timeout']);
		curl_setopt($ch, CURLOPT_NOSIGNAL, 1);

		curl_setopt($ch, CURLINFO_HEADER_OUT, true);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_HEADERFUNCTION, '_http_header_callback');
		curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
		curl_setopt($ch, CURLOPT_USERAGENT, $options['useragent']);
		
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);

		curl_setopt($ch, CURLOPT_PORT, $port);

		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, $options['max_follows']);


		$start = microtime_ms();

		$GLOBALS['_http_headers'][(string)$ch] = '';

		$raw = curl_exec($ch);
		$info = curl_getinfo($ch);

		$info['curl_error_code'] = curl_errno($ch);
		$info['curl_error_msg'] = curl_error($ch);

		$end = microtime_ms();

		curl_close($ch);

		$GLOBALS['timings']['http_count']++;
		$GLOBALS['timings']['http_time'] += $end-$start;
		$GLOBALS['timings']['http_last_request_time'] = $end-$start;

		$ret = _http_parse_response($ch, $raw, $info);

		$ret['req_url'] = $url;
		$ret['total_ms'] = $end-$start;

		return $ret;
	}


	function _http_parse_response($ch, $raw, $info){

		$head_raw = $GLOBALS['_http_headers'][(string)$ch];
		unset($GLOBALS['_http_headers'][(string)$ch]);

		$exploded = explode("\r\n\r\n", $head_raw, $info['redirect_count']+1);
		$head = $exploded[$info['redirect_count']];
		$body = $raw;

		if ($info['redirect_count']){
			$info['redirect_headers'] = array();
			for ($i=0; $i<$info['redirect_count']; $i++){
				$info['redirect_headers'][$i] = http_parse_headers($exploded[$i], '_status');
			}
		}

		list($head_out, $body_out) = explode("\r\n\r\n", $info['request_header'], 2);
		unset($info['request_header']);

		$headers_in = http_parse_headers($head, '_status');
		$headers_out = http_parse_headers($head_out, '_request');

		preg_match("/^([A-Z]+)\s/", $headers_out['_request'], $m);
		$method = $m[1];

		#log_notice("http", "{$method}-{$info['http_code']} {$info['url']}", $GLOBALS['timings']['http_last_request_time']);

		if ($info['curl_error_code']){

			return array(
				'ok'		=> 0,
				'error'		=> 'curl_error',
				'code'		=> $info['http_code'],
				'curl_errno'	=> $info['curl_error_code'],
				'curl_error'	=> $info['curl_error_msg'],
				'method'	=> $method,
				'url'		=> $info['url'],
				'info'		=> $info,
				'req_headers'	=> $headers_out,
				'headers'	=> $headers_in,
				'body'		=> $body,
				'rsp_len'	=> strlen($raw),
			);
		}

		# http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html#sec10.2
		# http://en.wikipedia.org/wiki/List_of_HTTP_status_codes#2xx_Success (note HTTP 207 WTF)

		if (($info['http_code'] < 200) || ($info['http_code'] > 299)){

			return array(
				'ok'		=> 0,
				'error'		=> 'http_failed',
				'code'		=> $info['http_code'],
				'method'	=> $method,
				'url'		=> $info['url'],
				'info'		=> $info,
				'req_headers'	=> $headers_out,
				'headers'	=> $headers_in,
				'body'		=> $body,
				'rsp_len'	=> strlen($raw),
			);
		}

		return array(
			'ok'		=> 1,
			'code'		=> $info['http_code'],
			'method'	=> $method,
			'url'		=> $info['url'],
			'info'		=> $info,
			'req_headers'	=> $headers_out,
			'headers'	=> $headers_in,
			'body'		=> $body,
			'rsp_len'	=> strlen($raw),
		);
	}

	function _http_header_callback($ch, $header_line){

		$GLOBALS['_http_headers'][(string)$ch] .= $header_line;

		return strlen($header_line);
	}

	function http_parse_headers($raw, $first){

		$raw = trim($raw);

		#
		# first, deal with folded lines
		#

		$raw_lines = explode("\r\n", $raw);

		$lines = array();
		$lines[] = array_shift($raw_lines);

		foreach ($raw_lines as $line){
			if (preg_match("!^[ \t]!", $line)){
				$lines[count($lines)-1] .= ' '.trim($line);
			}else{
				$lines[] = trim($line);
			}
		}


		#
		# now split them out
		#

		$out = array(
			$first => array_shift($lines),
		);

		foreach ($lines as $line){
			list($k, $v) = explode(':', $line, 2);
			$out[StrToLower($k)] = trim($v);
		}

		return $out;
	}

	function _http_prepare_outgoing_headers($headers=array()){

		$prepped = array();

		if (! isset($headers['Expect'])){
			$headers['Expect'] = '';	# Get around error 417
		}

		foreach ($headers as $key => $value){
			$prepped[] = "{$key}: {$value}";
		}

		return $prepped;
	}


	function microtime_ms(){
		list($usec, $sec) = explode(" ", microtime());
		return intval(1000 * ((float)$usec + (float)$sec));
	}

	function dumper($foo){

		echo "<pre style=\"text-align: left;\">";
		if (is_resource($foo)){
			var_dump($foo);
		}else{
			echo HtmlSpecialChars(var_export($foo, 1));
		}
		echo "</pre>\n";
	}

