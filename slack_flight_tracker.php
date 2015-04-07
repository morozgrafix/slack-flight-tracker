<?php
/**
 * Slack slash command bot to track status of the flights
 *
 * @author 	Sergey Morozov <morozgrafix@gmail.com>
 * @copyright	Copyright (c) 2014 Sergey Morozov
 * @license 	The MIT License (MIT)
 * @version 	1.0
 * @link 	https://github.com/morozgrafix/slack-flight-tracker
 * 
 * The MIT License (MIT)
 * 
 * Copyright (c) 2014 Sergey Morozov
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
*/


	require_once './includes/simple_html_dom.php';
	
	// Authorized team tokens that you would need to get when creating a slash command. Same script can serve multiple teams, just keep adding tokens to the array below.
	$tokens = array(
		'<INSERT_YOUR_TEAM_TOKEN_HERE>',
	);
	
	if (!in_array($_REQUEST['token'], $tokens)) {
		echo ":no_good: *Unauthorized token!* Feel free to grab a copy of this script and host it yourself. https://github.com/morozgrafix/slack-flight-tracker";
		break;
	}
	
	$query = str_replace(' ', '+', strtolower($_REQUEST['text']));	// example query "us+airways+2029"

	$url = "https://www.google.com/search?q=$query";
	
	$opts = array(
		'http'=>array(
	    	'method'=>"GET",
			'header'=>"Accept-language: en-us\r\n" .
				  "User-Agent: Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1; WOW64; Trident/4.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; MS-RTC LM 8; InfoPath.3; .NET4.0C; .NET4.0E) chromeframe/8.0.552.224\r\n"
		)
	);
	
	$context = stream_context_create($opts);
	$rsp = file_get_contents($url, false, $context);
	$html = str_get_html($rsp);
	
	// Let's scrape the google page and see if there is a result resembling a flight status. This is probably the most fragile part of the whole thing. If Google
	// changes their format most of the stuff below will have to be redone.
	if($html->find('div#ires ol li table.obcontainer', 0)) {
		$result = $html->find('div#ires ol li table.obcontainer', 0)->find('tr',1);
		$data = array();
			
		$data['title'] 						= $result->find('td', 0)->find('div', 0)->plaintext;
		
		$result_table						= $result->find('div', 1)->find('table', 0);
		$data['total_rows']					= count($result_table->find('tr'));
		$data['number_of_matches']				= ($data['total_rows']+1)/7;
		for($i = 0; $i < $data['number_of_matches']; $i++) {
			$data[$i]['status'] 				= $result_table->find('tr', 0 + $i * 7)->find('td', 0)->plaintext;
			$data[$i]['time_estimate'] 			= $result_table->find('tr', 0 + $i * 7)->find('td', 1)->plaintext;
			
			$data[$i]['departure']['airport_code'] 		= $result_table->find('tr', 1 + $i * 7)->find('td',1)->plaintext;
			$data[$i]['departure']['actual_time'] 		= $result_table->find('tr', 1 + $i * 7)->find('td',2)->plaintext;
			$data[$i]['departure']['scheduled_time'] 	= $result_table->find('tr', 1 + $i * 7)->find('td',3)->plaintext;
			$data[$i]['departure']['terminal'] 		= str_replace('&mdash;', '', $result_table->find('tr', 1 + $i * 7)->find('td',4)->plaintext);
			$data[$i]['departure']['city'] 			= $result_table->find('tr', 2 + $i * 7)->find('td',1)->plaintext;
			$data[$i]['departure']['actual_date'] 		= $result_table->find('tr', 2 + $i * 7)->find('td',2)->plaintext;
			$data[$i]['departure']['scheduled_date'] 	= $result_table->find('tr', 2 + $i * 7)->find('td',3)->plaintext;
			$data[$i]['departure']['gate'] 			= $result_table->find('tr', 2 + $i * 7)->find('td',4)->plaintext;
			
			$data[$i]['arrival']['airport_code'] 		= $result_table->find('tr', 4 + $i * 7)->find('td',1)->plaintext;
			$data[$i]['arrival']['actual_time'] 		= $result_table->find('tr', 4 + $i * 7)->find('td',2)->plaintext;
			$data[$i]['arrival']['scheduled_time'] 		= $result_table->find('tr', 4 + $i * 7)->find('td',3)->plaintext;
			$data[$i]['arrival']['terminal'] 		= str_replace('&mdash;', '', $result_table->find('tr', 4 + $i * 7)->find('td',4)->plaintext);
			$data[$i]['arrival']['city'] 			= $result_table->find('tr', 5 + $i * 7)->find('td',1)->plaintext;
			$data[$i]['arrival']['actual_date'] 		= $result_table->find('tr', 5 + $i * 7)->find('td',2)->plaintext;
			$data[$i]['arrival']['scheduled_date'] 		= $result_table->find('tr', 5 + $i * 7)->find('td',3)->plaintext;
			$data[$i]['arrival']['gate'] 			= $result_table->find('tr', 5 + $i * 7)->find('td',4)->plaintext;
		}
		
		$data['source_url'] 					= 'http://www.google.com'.$result->find('div', 2)->find('table', 0)->find('tr', 0)->find('td',0)->find('a', 0)->getAttribute('href');
		$result->find('div', 2)->find('table', 0)->find('tr', 0)->find('td',0)->find('a', 0)->innertext = '';
		$data['source'] 					= substr($result->find('div', 2)->find('table', 0)->find('tr', 0)->find('td',0)->plaintext, 0, -3);
		
		
	} else {
		$data['title'] = "  ¯\_(ツ)_/¯ I could not find that flight! Try searching for something along the lines of 'US Airways 2029' or 'US2029'";
	}
	
	
	// Cleanup up what we scraped and show it in somewhat meaningful way. Using emoji and Slack message formatting.
	$output = ":airplane: *" . $data['title'] . "*\n";
	for($k=0; $k<$data['number_of_matches']; $k++) {
		$output .= "`" . $data[$k]['status'] . "`";
		if($data[$k]['time_estimate'] != '') {
			$output .= " - *" . $data[$k]['time_estimate'] . "*";
		}
		$output .= "\n";
		$output .= ":arrow_heading_up: " . $data[$k]['departure']['city'] . " (" . $data[$k]['departure']['airport_code'] . ") - " . $data[$k]['departure']['actual_date'] . " " . $data[$k]['departure']['actual_time'] . " " . $data[$k]['departure']['scheduled_date'] . " " . $data[$k]['departure']['scheduled_time'];
		
		if ($data[$k]['departure']['terminal'] OR $data[$k]['departure']['gate']) {
			$output .= " - ";
		}

		if ($data[$k]['departure']['terminal']) {
			$output .= $data[$k]['departure']['terminal'];
		}

		if ($data[$k]['departure']['terminal'] AND $data[$k]['departure']['gate']) {
			$output .= " : ";
		}

		if ($data[$k]['departure']['gate']) {
			$output .= $data[$k]['departure']['gate'];
		}

		$output .= "\n";
		
		$output .= ":arrow_heading_down: " . $data[$k]['arrival']['city'] . " (" . $data[$k]['arrival']['airport_code'] . ") - " . $data[$k]['arrival']['actual_date'] . " " . $data[$k]['arrival']['actual_time'] . " " . $data[$k]['arrival']['scheduled_date'] . " " . $data[$k]['arrival']['scheduled_time'];
		
		if ($data[$k]['arrival']['terminal'] OR $data[$k]['arrival']['gate']) {
			$output .= " - ";
		}

		if ($data[$k]['arrival']['terminal']) {
			$output .= $data[$k]['arrival']['terminal'];
		}

		if ($data[$k]['arrival']['terminal'] AND $data[$k]['arrival']['gate']) {
			$output .= " : ";
		}


		if ($data[$k]['arrival']['gate']) {
			$output .= $data[$k]['arrival']['gate'];
		}
		$output .= "\n \n";
	}
	
	$output .= $data['source'];
	

	// And we are done! Let's show it to the world.
	echo $output;
	
