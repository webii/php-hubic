<?php
/*
Copyright 2014 Webii

Licensed under the Apache License, Version 2.0 (the "License"); you may not
use this file except in compliance with the License. You may obtain a copy of
the License at

http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
License for the specific language governing permissions and limitations under
the License.

*/

class PHPHubic {
	
	/**
	 * Send curl request
	 * 
	 * @param string $url
	 * @param array $post
	 * @param string $type request type - default POST, may be PUT/OPTION/...
	 * @param array $_headers array with extra headers
	 * @param bool $is_file flag used while uploading files
	 * @return string
	 */
	private function _post($url, $post, $type = "POST", $_headers = array(), $is_file = false) {
		$c = curl_init($url);
		
		curl_setopt($c, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.2; WOW64; rv:23.0) Gecko/20100101 Firefox/23.0");
		curl_setopt($c, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($c, CURLOPT_BINARYTRANSFER, TRUE);
		curl_setopt($c, CURLINFO_HEADER_OUT, true);
        curl_setopt($c, CURLOPT_VERBOSE, false);
        curl_setopt($c, CURLOPT_TIMEOUT, 3600);
        curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 10);

		#shows received header in output
		curl_setopt($c, CURLOPT_HEADER, true);
		
		#cookies
        curl_setopt($c, CURLOPT_COOKIEFILE, "cookie.txt");
        curl_setopt($c, CURLOPT_COOKIEJAR, "cookie.txt");
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
		
		# communication is splitted in two main ways
		# post x-www-form-urlencoded and sending files
		# using form-data which is automatically done by curl
		
		if (is_array($post)) {
			curl_setopt ($c, CURLOPT_POST, 1);
			
			if ($is_file === false) {
				$postinfo = array();
				
				foreach($post as $key=>$value) {
					$postinfo[] = $key.'='.urlencode($value);
				}
				
				$postinfo = implode("&", $postinfo);
				
				$headers[] = "Content-Length: ".strlen($postinfo);
				$headers[] = "Content-Type: application/x-www-form-urlencoded";
				curl_setopt ($c, CURLOPT_POSTFIELDS, $postinfo);
			} else {
				curl_setopt ($c, CURLOPT_POSTFIELDS, $post);
			}
		}
		
		$headers[] = "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8";
		
		if (is_array($_headers) && count($_headers) > 0) {
			foreach ($_headers as $h) {
				$headers[] = $h;
			}
		}
		
		curl_setopt($c, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($c, CURLOPT_CUSTOMREQUEST, $type);		
		curl_setopt($c, CURLINFO_HEADER_OUT, true);
		
		$content = curl_exec($c);
		
		curl_close($c);
	
		#header is in output and then is removed
		#it is only for debug purpose
		
		$is_header = true;
	
		$content = explode("\r\n", $content);
		$re = array();
		
		foreach ($content as $r) {
			if ($is_header) {
				if (empty($r)) {
					$is_header = false;
				}
			} else {
				$re[] = $r;
			}
		}

		return implode("\r\n", $re);
	}
	
	/**
	 * Log in to the Hubic
	 * 
	 * @param string $name
	 * @param string $password
	 */
	public function login($name, $password) {
		$post = array("action" => "", "offer" => "", "sign-in-email" => $name, "sign-in-password" => $password);
				
		$this->_post("https://hubic.com/home/logcheck.php", $post);
	}

	/**
	 * Browse root directoy
	 */
	public function browse() {
		$post = array("container" => "", "sortby" => "name", "sortdir" => "sortasc", "uri" => "/");
		
		$this->_post("https://hubic.com/home/browser/get/", $post);
	}
	
	/**
	 * Upload local file to a given directory
	 * 
	 * @param string $source
	 * @param string $target
	 */
	public function upload($source, $target = "/default/") {
		
		if (!is_file($source)) {
			return false;
		}
		
		$exp = (time()+3600)*1000;
		
		$post = array(
			"expires" => $exp,
			"max_file_count" => 1,
			"max_file_size" => filesize($source),
			"name" => array_pop(explode("/", $source)),
			"path" => "/default/",
			"redirect" => ""
		);
		
		$re = post("https://hubic.com/home/browser/prepareUpload/", $post);

		$re = json_decode($re);

		$this->_post($re->answer->hubic->url.$target, false, "OPTIONS", array("Access-Control-Request-Method: POST", "Origin: https://hubic.com"));
		
		unset($post["path"]);
		$post["signature"] = $re->answer->hubic->signature;
		$post["file1"] = "@".realpath($source).';type='.mime_content_type($source);
		
		$this->_post($re->answer->hubic->url.$target, $post, "POST", array("Origin: https://hubic.com", "Referer: https://hubic.com/home/browser/", "Expect:"), true);
	}
	
	public function mdir($name) {
		//https://hubic.com/home/browser/createDirectory/
		/*
		 * container	default
			folder	/
			name	test
		 */
	}
	
	public function delete($name) {
		#uri	default/test.jpg
	}
}