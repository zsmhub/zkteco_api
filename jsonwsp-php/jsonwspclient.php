<?php

/*******************************************************************************

Copyright 2014 MV-Nordic, www.mv-nordic.com

This library is free software; you can redistribute it and/or
modify it under the terms of the GNU Lesser General Public
License as published by the Free Software Foundation; either 
version 2.1 of the License, or (at your option) any later version.

This library is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
Lesser General Public License for more details.

You should have received a copy of the GNU Lesser General Public 
License along with this library.  If not, see <http://www.gnu.org/licenses/>.

*******************************************************************************/


/*******************************************************************************
	
	JSONWSP PHP Client library classes
	
	This file contains a php implementation of the jsonwsp client protocol. 
	It is able to make requests using the jsonwsp protocol to fetch a service
	description and call methods. The methods are called by name and using an
	associative array to hold arguments. The returned data is wrapped into a 
	response object and you can fetch response data as json by calling the  
	getJsonResponse method. View the documentation on the jsonwsp protocol
	for more information on what data are sent and returned.
	
	For the serverpart, visit www.ladonize.org
	
	JSONWSP specification on wikipedia: http://en.wikipedia.org/wiki/JSON-WSP
	
	For more information, view the description for the classes and methods
	in this file, for further usage examples view the examples in the example
	folder.

*******************************************************************************/

/**
 * 
 * JsonWspClient
 * The primary class for the client, used to load a description from a url to a
 * jsonwsp service and then call the methods declared by the service.
 * 
 */
class JsonWspClient
{

	// Private members
	private $m_serviceUrlFromDescription;
	private $m_serviceUrl;
	private $m_descriptionUrl;
	private $m_cookies = array();
	private $m_headers = array();
	private $m_ignoreSSLWarnings = false;

	/**
	 * Constructor
	 * Creates a new client from a description url. It loads the service description and reads the service url
	 * so methods can be called on the server.
	 * @param $description_url The url to the jsonwsp service description
	 * @param $ignoreSSLWarnings Set to true to ignore ssl warnings on request
	 * @param $cookies A list of cookies to use in requests. Use cookie name as index and cookie content as value.
	 */
	public function __construct($description_url,$ignoreSSLWarnings=false,$cookies=null,$headers=null)
	{
		
		if($cookies == null || !is_array($cookies))
		{
			$this->m_cookies = array();
		}
		else 
		{
			$this->m_cookies = $cookies;
		}
		
		if($headers == null || !is_array($headers))
		{
			$this->m_headers = array();
		}
		else 
		{
			$this->m_headers = $headers;
		}
	
		$this->m_ignoreSSLWarnings = $ignoreSSLWarnings;
		$this->m_descriptionUrl = $description_url;
		$response = $this->SendRequest($description_url,"","application/json",$ignoreSSLWarnings,$cookies,$headers);

		if($response->getJsonWspType() == JsonWspType::Description)
		{
			$jsonResponse = json_decode($response->getResponseText(),true);
			$this->m_serviceUrl = $jsonResponse["url"];
			$this->m_serviceUrlFromDescription = $this->m_serviceUrl;
		}
	}

	/**
	 * 
	 * Used to tell the client if the service url from the server description should be used, or the url from the description url
	 * should be used. This means that if setViaProxy is set to false, the description url will be used, if it is set to true
	 * the client is forced to use the description url and not the native service url from the description..
	 * @param $enable If true, use the description url to call methods
	 */
	public function setViaProxy($enable)
	{
		if($enable)
		{
			$tmp = explode("/",$this->m_descriptionUrl);
			array_pop($tmp);
			$this->m_serviceUrl = implode("/",$tmp);
		}
		else
		{
			$this->m_serviceUrl = $this->m_serviceUrlFromDescription;
		}
	}
	
	/**
	 * 
	 * Adds a cookie to the server requests. Can be used if the server needs specific cookies set, to authenticate or dispatch
	 * the request in a specific way. Multiple cookies can be added, but only one at a time.
	 * @param $name The name of the cookie
	 * @param $value The cookie value
	 */
	public function addCookie($name,$value)
	{
		$this->m_cookies[$name] = $value;
	}

	/**
	 * 
	 * Removes a cookie by its name
	 * @param $name The name of the cookie
	 */
	public function removeCookie($name)
	{
		if(isset($this->m_cookies[$name]))
		{
			unset($this->m_cookies[$name]);
		}
	}

	/**
	 * 
	 * Adds a HTTP header to the server requests. Multiple headers can be added, but only one at a time.
	 * @param $name The name of the header
	 * @param $value The header value
	 */
	public function addHeader($name,$value)
	{
		$this->m_headers[$name] = $value;
	}

	/**
	 * 
	 * Removes a header by its name
	 * @param $name The name of the header
	 */
	public function removeHeader($name)
	{
		if(isset($this->m_headers[$name]))
		{
			unset($this->m_headers[$name]);
		}
	}

	/**
	 * 
	 * Calls a service method on the service using a service name and optional arguments as an associative array
	 * @param $methodname The name of the method to call on the service
	 * @param $args The arguments to send with the service call as an associative array, using the keys as argument names.
	 * @param $cookies List of cookies to use in the call, merges with the cookies already set on the client, but is not stored for future calls
	 * @return JsonWspResponse object that contains the response information
	 */
	public function CallMethod($methodname,$args=null,$cookies=null,$headers=null)
	{
		// No arguments given, use empty array
		if($args == null) $args = array();

		// Create response data
		$reqDict = array("methodname" => $methodname, "type" => "jsonwsp/request", "args" => $args);

		if(is_array($cookies)) $cookieList = array_merge($this->m_cookies,$cookies);
		else $cookieList = $this->m_cookies;
		
		if(is_array($headers)) $headerList = array_merge($this->m_headers,$headers);
		else $headerList = $this->m_headers;
		
		// Send a request to the service url and return the jsonwsp response
		return self::SendRequest($this->m_serviceUrl,json_encode($reqDict),"application/json",$this->m_ignoreSSLWarnings,$cookieList,$headerList);
	}
	
	/**
	 * 
	 * Sends a jsonwsp request to the server. Takes url, data to send and optional a content type and returns a jsonwsp response object.
	 * @param $url Url to send request to
	 * @param $data Data to send in the request, as a raw string value.
	 * @param $content_type The contenttype to send in the request, defaults to application/json
	 * @return JsonWspResponse object that contains the response information
	 */
	public static function SendRequest($url,$data="",$content_type="application/json",$ignoreSSLWarnings=false,$cookies=null,$headers=null)
	{
		$headerList = array("Content-Type: ".$content_type, "Content-length: ".strlen($data));
		if($headers != null && count($headers) > 0)
		{
			foreach($headers as $hName => $hValue)
			{
				$headerList[] = $hName.": ".$hValue;
			}
		}

		if($cookies != null || count($cookies) > 0)
		{
			$cookieString = "";
			foreach($cookies as $cName => $cValue)
			{
				$cookieString .= ($cookieString != "" ? "; " : "").$cName."=".$cValue;
			}
			
			$headerList[] = "Cookie: ".$cookieString;
		}
		
		// Init curl
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headerList);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, 1);
		
		if($ignoreSSLWarnings)
		{
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		}
		
		// Contains data, make POST request
		if(strlen($data) > 0)
		{
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
			curl_setopt($ch, CURLOPT_POST, 1);
		}
		// No data, make GET request
		else
		{
			curl_setopt($ch, CURLOPT_HTTPGET, 1);
		}

		// Send request and close curl session
		$response = curl_exec($ch);
		curl_close($ch);

		// Create reponse and return it
		return new JsonWspResponse($response);
	}

}

/*
 * Enum classes, uses constants to describe values in the response and fault objects
 */

/**
 * Used to describe the fault type on jsonwsp faults
 */
class JsonWspFaultType
{
	const ServerFault = 1;	// Fault occured on the server
	const ClientFault = 2;	// Fault occured in the client
}

/**
 * JsonWspCallResult
 * Used to descripbe the result of a jsonwsp call
 */
class JsonWspCallResult
{
	const NetworkFault = 1;	// Fault occured on the network transfer
	const ServiceFault = 2;	// Fault occured on the service level
	const Success = 3;		// Service call completed successfully
}

/**
 * JsonWspType
 * Used to describe the type of service response 
 */
class JsonWspType
{
	const NoType = 1;		// Type not detected or unknown type
	const Description = 2;	// JSONWSP service description response
	const Response = 3;		// JSONWSP method call response
	const Fault = 4;		// JSONWSP fault returned in the response
}	


/**
 * 
 * The JsonWspResponse is the object that parses and holds data that is returned when calling the service.
 * The class can be used to read the status (success or not), type of response and the data returned by the service.
 */
class JsonWspResponse
{

	// Private members
	private $m_statusCode;
	private $m_statusDesc;
	private $m_responseText;
	private $m_jsonResponse;
	private $m_jsonWspType;
	private $m_callResult;
	private $m_fault;

	/**
	 * Constructor that parses the response from a raw http response string containing the http header
	 * @param $response The complete http response from the jsonwsp service
	 */
	public function __construct($response)
	{
		$body = $response;
		// Loop through the headers and use the last header as the primary header
		while (preg_match("/^HTTP\S*\s+(\d+)\s+(.+?)\r\n/", $body, $parts))
		{	// Yes, we have an HTTP header - separate it from the body:
			list($header, $body) = explode("\r\n\r\n", $body, 2);
			// We expect another header after these headers, therefore ignoring these: 100
			if (in_array($parts[1], array(100)))
				continue;
			// Hopefully an HTTP 200 header, but all accepted at this point:
			$this->m_statusCode = $parts[1];
			$this->m_statusDesc = $parts[2];
			$this->m_responseText = $body;
		}
		// Statuscode is OK
		if($this->m_statusCode = 200)
		{
			// Decode json
			$this->m_jsonResponse = json_decode($this->m_responseText,true);

			// Check for different response types and handle accordingly
			if($this->m_jsonResponse["type"] == "jsonwsp/description")
			{
				$this->m_jsonWspType = JsonWspType::Description;
				$this->m_callResult = JsonWspCallResult::Success;
			}

			else if($this->m_jsonResponse["type"] == "jsonwsp/response")
			{
				$this->m_jsonWspType = JsonWspType::Response;
				$this->m_callResult = JsonWspCallResult::Success;
			}

			else if($this->m_jsonResponse["type"] == "jsonwsp/fault")
			{
				$this->m_jsonWspType = JsonWspType::Fault;
				$this->m_callResult = JsonWspCallResult::ServiceFault;
				$this->m_fault = new JsonWspFault($this->m_jsonResponse["fault"]);
			}

			else if($this->m_jsonResponse == null)
			{
				$this->m_jsonWspType = JsonWspType::NoType;
				$this->m_callResult = JsonWspCallResult::ServiceFault;
			}

		}

		else
		{
			$this->m_jsonWspType = JsonWspType::NoType;
			$this->m_callResult  = JsonWspCallResult::NetworkFault;
		}

	}

	/**
	 * Returns the http status code from the response
	 */
	public function getStatusCode()
	{
		return $this->m_statusCode;
	}

	/**
	 * Returns the http status description from the response
	 */
	public function getStatusString()
	{
		return $this->m_statusDesc;
	}

	/**
	 * Returns the http body as non-parsed clean text
	 */
	public function getResponseText()
	{
		return $this->m_responseText;
	}

	/**
	 * Returns the decoded json response as an associative array.
	 */
	public function getJsonResponse()
	{
		return $this->m_jsonResponse;
	}

	/**
	 * Get the result of the service call as JsonWspCallResult constant
	 */
	public function getCallResult()
	{
		return $this->m_callResult;
	}

	/**
	 * Get the response type as a JsonWspType constant
	 */
	public function getJsonWspType()
	{
		return $this->m_jsonWspType;
	}

	/**
	 * If there is a service fault, get the fault as a JsonWspFault object. If no fault data is found, this will return null.
	 */
	public function getServiceFault()
	{
		return $this->m_fault;
	}

}

/**
 * The JsonWspFault object wraps the information from a JSONWSP Fault.
 * It extracts the information from the fault data structure and makes them
 * accessible through the get methods.
 */
class JsonWspFault
{
	// Private members
	private $m_faultType;
	private $m_details;
	private $m_errorString;
	private $m_filename;
	private $m_lineno;

	/**
	 * Constructor, takes the fault data structure and saves the information into the correct members, to make them easy to access
	 * @param $fault array containing the fault data
	 */
	public function __construct($fault)
	{
		$this->m_details = implode("\n",$fault["detail"]);
		$this->m_errorString = $fault["string"];
		$this->m_faultType = ($fault["code"] == "server" ? JsonWspFaultType::ServerFault :  JsonWspFaultType::ClientFault);
		$this->m_filename = $fault["filename"];
		$this->m_lineno = intval($fault["lineno"]);
	}

	/**
	 * Returns the fault type as JsonWspFaultType constant
	 */
	public function getFaultType()
	{
		return $this->m_faultType;
	}

	/**
	 * Returns the error string from the service fault
	 */
	public function getString()
	{
		return $this->m_errorString;
	}

	/**
	 * Returns the error details from the service fault
	 */
	public function getDetails()
	{
		return $this->m_details;
	}
	
	/**
	 * Returns the filename where the fault occured
	 */
	public function getFilename()
	{
		return $this->m_filename;
	}

	/**
	 * Returns the linenumber where the fault occured
	 */
	public function getLineNo()
	{
		return $this->m_lineno;
	}

}

