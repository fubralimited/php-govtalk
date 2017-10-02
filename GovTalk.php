<?php

#
#  GovTalk.php
#
#  Created by Jonathon Wardman on 14-07-2009.
#  Copyright 2009 - 2011, Fubra Limited. All rights reserved.
#
#  This program is free software: you can redistribute it and/or modify
#  it under the terms of the GNU General Public License as published by
#  the Free Software Foundation, either version 3 of the License, or
#  (at your option) any later version.
#
#  You may obtain a copy of the License at:
#  http://www.gnu.org/licenses/gpl-3.0.txt
#
#  This program is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#  GNU General Public License for more details.

/**
 * GovTalk API Client -- Builds, validates, sends, receives and validates
 * GovTalk messages for use with the UK government's GovTalk messaging system
 * (http://www.govtalk.gov.uk/). A generic wrapper designed to be extended for
 * use with the more specific interfaces provided by various government
 * departments. Generates valid GovTalk envelopes for agreed version 2.0.
 *
 * @author Jonathon Wardman
 * @copyright 2009 - 2011, Fubra Limited
 * @licence http://www.gnu.org/licenses/gpl-3.0.txt GNU General Public License
 */
class GovTalk {

 /* Server related variables. */

	/**
	 * GovTalk server.
	 *
	 * @var string
	 */
	private $_govTalkServer;
	/**
	 * GovTalk sender ID.
	 *
	 * @var string
	 */
	protected $_govTalkSenderId;
	/**
	 * GovTalk sender password.
	 *
	 * @var string
	 */
	protected $_govTalkPassword;

 /* General envelope related variables. */

	/**
	 * Additional XSI SchemaLocation URL.  Default is null, no additional schema.
	 *
	 * @var string
	 */
	private $_additionalXsiSchemaLocation = null;
	/**
	 * GovTalk test flag.  Default is 0, a real message.
	 *
	 * @var string
	 */
	private $_govTalkTest = '0';
	/**
	 * Body of the message to be sent.
	 *
	 * @var mixed Can either be of type XMLWriter, or a string.
	 */
	private $_messageBody;

 /* MessageDetails related variables */

	/**
	 * GovTalk message Class.
	 *
	 * @var string
	 */
	private $_messageClass;
	/**
	 * GovTalk message Qualifier.
	 *
	 * @var string
	 */
	private $_messageQualifier;
	/**
	 * GovTalk message Function.  Default is null, no specified function.
	 *
	 * @var string
	 */
	private $_messageFunction = null;
	/**
	 * GovTalk message CorrelationID.  Default is null, no correlation ID.
	 *
	 * @var string
	 */
	private $_messageCorrelationId = null;
	/**
	 * GovTalk message Transformation.  Default is null, return in standard XML.
	 *
	 * @var string
	 */
	private $_messageTransformation = 'XML';

 /* SenderDetails related variables. */

	/**
	 * GovTalk SenderDetail EmailAddress.  Default is null, no email address.
	 *
	 * @var string
	 */
	private $_senderEmailAddress = null;
	/**
	 * GovTalk message authentication type.
	 *
	 * @var string
	 */
	private $_messageAuthType;

 /* Keys related variables. */

	/**
	 * GovTalk keys array.
	 *
	 * @var array
	 */
	private $_govTalkKeys = array();

 /* Channel routing related variables. */

	/**
	 * GovTalk message channel routing array.
	 *
	 * @var array
	 */
	private $_messageChannelRouting = array();

 /* Target details related variables. */

	/**
	 * GovTalk target details / organisations array.
	 *
	 * @var array
	 */
	private $_messageTargetDetails = array();

 /* Full request/response data variables. */

	/**
	 * Full request data in string format (raw XML).
	 *
	 * @var string
	 */
	protected $_fullRequestString;
	/**
	 * Full return data in string format (raw XML).
	 *
	 * @var string
	 */
	protected $_fullResponseString;
	/**
	 * Full return data in object format (SimpleXML).
	 *
	 * @var string
	 */
	protected $_fullResponseObject;

 /* Error handling variables. */

	/**
	 * An array containing all reported errors.
	 *
	 * The error array is stored and returned in the following format, one
	 * one element for every error which has been reported:
	 *   time => The unix timestamp (with microseconds) that this error was generated.
	 *   code => A short error code. Defined by the function adding the error and not globally.
	 *   message => A more descriptive error message. Again defined by the function adding the error, but hopefully more helpful. (Optional.)
	 *   function => The name of the calling function. (Optional.)
	 *
	 * @since 0.4
	 * @var array
	 */
	protected $_errorArray = array();

 /* System / internal variables. */

	/**
	 * Transaction ID of the last message sent / received.
	 *
	 * @var string
	 */
	private $_transactionId = null;
	/**
	 * Flag indicating if the outgoing and incoming XML should be validated
	 * against the XML schema. By default these checks will be made.
	 *
	 * @var boolean
	 */
	private $_schemaValidation = true;

 /* Magic methods. */

	/**
	 * Instance constructor.
	 *
	 * @param string $govTalkServer GovTalk server URL.
	 * @param string $govTalkSenderId GovTalk sender ID.
	 * @param string $govTalkPassword GovTalk password.
	 */
	public function __construct($govTalkServer, $govTalkSenderId, $govTalkPassword) {

		$this->setGovTalkServer($govTalkServer);
		$this->_govTalkSenderId = $govTalkSenderId;
		$this->_govTalkPassword = $govTalkPassword;

	}

 /* Public methods. */

 /* Error handling funtions. */

	/**
	 * Returns the number of errors which have been logged in the error array
	 * since this instance was initialised, or the error array was last reset.
	 *
	 * @since 0.4
	 * @see logError(), clearErrors(), getErrors()
	 * @return int The number of errors since the error array was last reset.
	 */
	public function errorCount() {

		return count($this->_errorArray);

	}

	/**
	 * Returns the full error array.
	 *
	 * @since 0.4
	 * @see getLastError(), $_errorArray
	 * @return array The complete error array.
	 */
	public function getErrors() {

		return $this->_errorArray();

	}

	/**
	 * Returns the last error pushed onto the error array.
	 *
	 * @since 0.4
	 * @see getErrors(), $_errorArray
	 * @return array The last element pushed onto the error array.
	 */
	public function getLastError() {

		return end($this->_errorArray);

	}

	/**
	 * Clears all errors out of the error array.
	 *
	 * @since 0.4
	 * @see $_errorArray
	 * @return boolean This function always returns true.
	 */
	public function clearErrors() {

		$this->_errorArray = array();
		return true;

	}

 /* Logical / operational / conditional methods. */

	/**
	 * Tests if a response has errors.  Should be checked before further
	 * operations are carried out on the returned object.
	 *
	 * @return boolean True if errors are present, false if not.
	 */
	public function responseHasErrors() {

		if (isset($this->_fullResponseObject)) {
			if (isset($this->_fullResponseObject->GovTalkDetails->GovTalkErrors)) {
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}

	}

 /* System / internal get methods. */

	/**
	 * Returns the transaction ID used in the last message sent / received.
	 *
	 * @return string Transaction ID.
	 */
	public function getTransactionId() {

		return $this->_transactionId;

	}

	/**
	 * Returns the full XML request from the last Gateway request, if there is
	 * one.
	 *
	 * @return mixed The full text request from the Gateway, or false if this isn't set.
	 */
	public function getFullXMLRequest() {

		if (isset($this->_fullRequestString)) {
			return $this->_fullRequestString;
		} else {
			return false;
		}

	}

	/**
	 * Returns the full XML response from the last Gateway request, if there is
	 * one.
	 *
	 * @return mixed The full text response from the Gateway, or false if this isn't set.
	 */
	public function getFullXMLResponse() {

		if (isset($this->_fullResponseString)) {
			return $this->_fullResponseString;
		} else {
			return false;
		}

	}

 /* Response data get methods */

	/**
	 * Returns the Gateway response message qualifier of the last response
	 * received, if there is one.
	 *
	 * @return integer The response qualifier, or false if there is no response.
	 */
	public function getResponseQualifier() {

		if (isset($this->_fullResponseObject)) {
			return (string) $this->_fullResponseObject->Header->MessageDetails->Qualifer;
		} else {
			return false;
		}

	}

	/**
	 * Returns the Gateway timestamp of the last response received, if there is
	 * one.
	 *
	 * @return integer The Gateway timestamp as a unix timestamp, or false if this isn't set.
	 */
	public function getGatewayTimestamp() {

		if (isset($this->_fullResponseObject)) {
			return strtotime((string) $this->_fullResponseObject->Header->MessageDetails->GatewayTimestamp);
		} else {
			return false;
		}

	}

	/**
	 * Returns the correlation ID issued by the Gateway in the last response, if
	 * there was one.  Once an ID has been assigned by the Gateway, any
	 * subsequent communications regarding a message much include it.
	 *
	 * @return integer The Gateway timestamp as a unix timestamp, or false if this isn't set.
	 */
	public function getResponseCorrelationId() {

		if (isset($this->_fullResponseObject)) {
			if (isset($this->_fullResponseObject->Header->MessageDetails->CorrelationID)) {
				return (string) $this->_fullResponseObject->Header->MessageDetails->CorrelationID;
			} else {
				return false;
			}
		} else {
			return false;
		}

	}

	/**
	 * Returns information from the Gateway ResponseEndPoint including recomended
	 * retry times, if there is one.
	 *
	 * @return array The Gateway endpoint and retry interval, or false if this isn't set.
	 */
	public function getResponseEndpoint() {

		if (isset($this->_fullResponseObject)) {
			if (isset($this->_fullResponseObject->Header->MessageDetails->ResponseEndPoint)) {
				if (isset($this->_fullResponseObject->Header->MessageDetails->ResponseEndPoint['PollInterval'])) {
					$pollInterval = (string) $this->_fullResponseObject->Header->MessageDetails->ResponseEndPoint['PollInterval'];
				} else {
					$pollInterval = null;
				}
				$endpoint = (string) $this->_fullResponseObject->Header->MessageDetails->ResponseEndPoint;
				return array('endpoint' => $endpoint,
				             'interval' => $pollInterval);
			} else {
				return false;
			}
		} else {
			return false;
		}

	}

	/**
	 * Returns an array of errors, if any are present.  Errors can be 'fatal',
	 * 'recoverable', 'business' or 'warning'.  If no errors are found this
	 * function will return false.
	 *
	 * @return mixed Array of errors, or false if there are no errors.
	 */
	public function getResponseErrors() {

		if ($this->responseHasErrors()) {
			$errorArray = array('fatal' => array(),
			                    'recoverable' => array(),
			                    'recoverable' => array(),
			                    'business' => array(),
			                    'warning' => array());
			foreach ($this->_fullResponseObject->GovTalkDetails->GovTalkErrors->Error AS $responseError) {
				$errorDetails = array('number' => (string) $responseError->Number,
				                      'text' => (string) $responseError->Text);
				if (isset($responseError->Location) && (string) $responseError->Location !== '') {
					$errorDetails['location'] = (string) $responseError->Location;
				}
				$errorArray[(string) $responseError->Type][] = $errorDetails;
			}
			return $errorArray;
		} else {
			return false;
		}

	}

	/**
	 * Returns the contents of the response Body section, removing all GovTalk
	 * Message Envelope wrappers, as a SimpleXML object.
	 *
	 * @return mixed The message body as a SimpleXML object, or false if this isn't set.
	 */
	public function getResponseBody() {
	
		if (isset($this->_fullResponseObject)) {
			return $this->_fullResponseObject->Body;
		} else {
			return false;
		}

	}

 /* General envelope related set methods. */
 
	/**
	 * Change the URL used to talk to the Government Gateway from that set during
	 * the instance instantiation. Very handy when required to poll a different
	 * URL for the result of a submission request.
	 *
	 * @param string $govTalkServer GovTalk server URL.
	 */
	public function setGovTalkServer($govTalkServer) {
	
		$this->_govTalkServer = $govTalkServer;
	
	}

	/**
	 * An additional SchemaLocation for use in the GovTalk headers.  This URL
	 * should be the location of an additional xsd defining the body segment.
	 * By default if an additional schema is set then both incoming and outgoing
	 * XML data will be validated against it.  This can be disabled by passing
	 * false as the second argument when setting the schema.
	 *
	 * @param string $schemaLocation URL location of additional xsd.
	 * @param boolean $validate True to turn validation on, false to turn it off.
	 * @return boolean True if the URL is valid and set, false if it's invalid (and therefore not set).
	 */
	public function setSchemaLocation($schemaLocation, $validate = null) {

		if (preg_match('/^https?:\/\/[\w-.]+\.gov\.uk/', $schemaLocation)) {
			$this->_additionalXsiSchemaLocation = $schemaLocation;
			if ($validate !== null) {
				$this->setSchemaValidation($validate);
			}
			return true;
		} else {
			return false;
		}

	}
	
	/**
	 * Switch off (or on) schema validation of outgoing and incoming XML data
	 * against the additional XML schema.
	 *
	 * @param boolean $validationFlag True to turn validation on, false to turn it off.
	 * @return boolean True if the validation is set, false if setting the validation failed.
	 */
	public function setSchemaValidation($validate) {
	
		if (is_bool($validate)) {
			$this->_schemaValidation = $validate;
			return true;
		} else {
			return false;
		}
	
	}

	/**
	 * Sets the test flag.  A flag value of true tells the Gateway this message
	 * is a test, false (default) tells it this is a live message.
	 *
	 * @param boolean $testFlag The value to set the test flag to.
	 * @return boolean True if the flag is set successfully, false otherwise.
	 */
	public function setTestFlag($testFlag) {

		if (is_bool($testFlag)) {
			if ($testFlag === true) {
				$this->_govTalkTest = '1';
			} else {
				$this->_govTalkTest = '0';
			}
		} else {
			return false;
		}

	}

	/**
	 * Sets the message body. Message body can be either of type XMLWriter, or a
	 * static string.  The message body will be included between the Body tags
	 * of the GovTalk envelope just as it's set and therefore must be valid XML.
	 *
	 * Providing an XML schema URL will cause the function to validate the
	 * message body against the schema prior to setting it. If no schema is
	 * supplied no checks will be made at this stage.
	 *
	 * @param mixed $messageBody The XML body of the GovTalk message.
	 * @param string $xmlSchema The URL of an XML schema to check the XML body against.
	 * @return boolean True if the body is valid and set, false if it's invalid (and therefore not set).
	 */
	public function setMessageBody($messageBody, $xmlSchema = null) {

		if (is_string($messageBody) || is_a($messageBody, 'XMLWriter')) {
			if ($xmlSchema !== null) {
				$validate = new DOMDocument();
				if (is_string($messageBody)) {
					$validate->loadXML($messageBody);
				} else {
					$validate->loadXML($messageBody->outputMemory());
				}
				if ($validate->schemaValidate($xmlSchema)) {
					$this->_messageBody = $messageBody;
					return true;
				} else {
					return false;
				}
			} else {
				$this->_messageBody = $messageBody;
				return true;
			}
		} else {
			return false;
		}

	}

 /* MessageDetails related set methods. */

	/**
	 * Sets the message Class for use in MessageDetails header.
	 *
	 * @param string $messageClass The class to set.
	 * @return boolean True if the Class is valid and set, false if it's invalid (and therefore not set).
	 */
	public function setMessageClass($messageClass) {

		$messageClassLength = strlen($messageClass);
		if (($messageClassLength > 4) && ($messageClassLength < 32)) {
			$this->_messageClass = $messageClass;
		} else {
			return false;
		}

	}

	/**
	 * Sets the message Qualifier for use in MessageDetails header.  The
	 * Qualifier may be one of 'request', 'acknowledgement', 'response', 'poll'
	 * or 'error'. Any other values will not be set and will return false.
	 *
	 * @param string $messageQualifier The qualifier to set.
	 * @return boolean True if the Qualifier is valid and set, false if it's invalid (and therefore not set).
	 */
	public function setMessageQualifier($messageQualifier) {

		$messageQualifier = strtolower($messageQualifier);
		switch ($messageQualifier) {
			case 'request':
			case 'acknowledgement':
			case 'reponse':
			case 'poll':
			case 'error':
				$this->_messageQualifier = $messageQualifier;
				return true;
			break;
			default:
				return false;
			break;
		}

	}

	/**
	 * Sets the message Function for use in MessageDetails header. This function
	 * is designed to be extended by department-specific extensions to validate
	 * the possible options for message function, although can be used as-is.
	 *
	 * @param string $messageFunction The function to set.
	 * @return boolean True if the Function is valid and set, false if it's invalid (and therefore not set).
	 */
	public function setMessageFunction($messageFunction) {

		$this->_messageFunction = $messageFunction;
		return true;

	}

	/**
	 * Sets the message CorrelationID for use in MessageDetails header.
	 *
	 * @param string $messageCorrelationId The correlation ID to set.
	 * @return boolean True if the CorrelationID is valid and set, false if it's invalid (and therefore not set).
	 * @see function getResponseCorrelationId
	 */
	public function setMessageCorrelationId($messageCorrelationId) {

		if (preg_match('/[0-9A-F]{0,32}/', $messageCorrelationId)) {
			$this->_messageCorrelationId = $messageCorrelationId;
			return true;
		} else {
			return false;
		}

	}

	/**
	 * Sets the message Transformation for use in MessageDetails header. Possible
	 * values are 'XML', 'HTML', or 'text'. The default is XML.
	 *
	 * Note: setting this to anything other than XML will limit the functionality
	 * of the GovTalk class and some extensions as they are not currently able to
	 * parse HTML or text documents. You are advised against changing this value
	 * from the default.
	 *
	 * @param string $messageCorrelationId The correlation ID to set.
	 * @return boolean True if the CorrelationID is valid and set, false if it's invalid (and therefore not set).
	 * @see function getResponseCorrelationId
	 */
	public function setMessageTransformation($transformation) {

		switch ($transformation) {
			case 'XML':
			case 'HTML':
			case 'text':
				$this->_messageTransformation = $transformation;
				return true;
			break;
			default:
				return false;
			break;
		}

	}

 /* SenderDetails related set methods. */

	/**
	 * Sets the sender email address for use in SenderDetails header.  Note: the
	 * validation used when setting an email address here is that specified by
	 * the GovTalk 2.0 envelope specifcation and is somewhat limited.
	 *
	 * @param string $senderEmailAddress The email address to set.
	 * @return boolean True if the EmailAddress is valid and set, false if it's invalid (and therefore not set).
	 */
	public function setSenderEmailAddress($senderEmailAddress) {

		if (preg_match('/[A-Za-z0-9\.\-_]{1,64}@[A-Za-z0-9\.\-_]{1,64}/', $senderEmailAddress)) {
			$this->_senderEmailAddress = $senderEmailAddress;
			return true;
		} else {
			return false;
		}

	}

	/**
	 * Sets the type of authentication to use for with the message.  The message
	 * type must be one of 'alternative', 'clear', 'MD5' or 'W3Csigned'. Other
	 * values will not be set and will return false.
	 *
	 * @param string $messageAuthType The type of authentication to set.
	 * @return boolean True if the authentication type is valid and set, false if it's invalid (and therefore not set).
	 */
	public function setMessageAuthentication($messageAuthType) {

		switch ($messageAuthType) {
			case 'alternative':
			case 'clear':
			case 'MD5':
			case 'W3Csigned':
				$this->_messageAuthType = $messageAuthType;
				return true;
			break;
			default:
				return false;
			break;
		}

	}

 /* Channel routing related methods. */

	/**
	 * Adds a channel routing element to the message.  Channel routes should be
	 * added in order by every application which the message has passed through
	 * prior to being sent to the Gateway.  php-govtalk does not support name
	 * elements in channel routing.  If not defined the timestamp element will
	 * automatically be added at the moment the route is added.  Any optional
	 * arguments may be skipped by passing null as that argument.
	 *
	 * Applications using php-govtalk should <i>always</i> add at least one
	 * additional channel route before sending a message to the Gateway.
	 *
	 * Note: php-govtalk will always add itself as the last route in the chain.
	 * This is to identify the library to the Gateway and to assist in tracking
	 * down issues caused by the library itself.
	 *
	 * @param string $uri The URI of the owner of the process being added to the route.
	 * @param string $softwareName The name of the software generating this route entry.
	 * @param string $softwareVersion The version number of the software generating this route entry.
	 * @param array $id An array of IDs (themselves array of 'type' and 'value') to add as array elements.
	 * @param string $timestamp A timestamp representing the time this route processed the message (xsd:dateTime format).
	 * @param boolean $force If true the route already exists check is not carried out and the target is added regardless of duplicates. (Defaults to false.)
	 * @return boolean True if the route is valid and added, false if it's not valid (and therefore not added).
	 */
	public function addChannelRoute($uri, $softwareName = null, $softwareVersion = null, array $id = null, $timestamp = null, $force = false) {

		if (is_string($uri)) {
			$newRoute = array('uri' => $uri);
			if ($softwareName !== null) {
				$newRoute['product'] = $softwareName;
			}
			if ($softwareVersion !== null) {
				$newRoute['version'] = $softwareVersion;
			}
			if ($id !== null && is_array($id)) {
				foreach ($id AS $idElement) {
					if (is_array($idElement)) {
						$newRoute['id'][] = $idElement;
					}
				}
			}
			if (($timestamp !== null) && ($parsedTimestamp = strtotime($timestamp))) {
				$newRoute['timestamp'] = date('c', $parsedTimestamp);
			} else {
				$newRoute['timestamp'] = date('c');
			}
			if ($force === false) {
				$matchedChannel = false;
				foreach ($this->_messageChannelRouting AS $channelRoute) {
					if (($channelRoute['product'] == $newRoute['product']) && ($channelRoute['version'] == $newRoute['version'])) {
						$matchedChannel = true;
						break;
					}
				}
				if ($matchedChannel == false) {
					$this->_messageChannelRouting[] = $newRoute;
				}
				return true;
			} else {
				$this->_messageChannelRouting[] = $newRoute;
				return true;
			}
		} else {
			return false;
		}

	}

 /* Keys related methods. */

	/**
	 * Add a key-value pair to the set of keys to be sent with the message as
	 * part of the GovTalkDetails element.
	 *
	 * @param string $keyType The key type (type attribute).
	 * @param string $keyValue The key value.
	 * @return boolean True if the key is valid and added, false if it's not valid (and therefore not added).
	 */
	public function addMessageKey($keyType, $keyValue) {

		if (is_string($keyType) && $keyValue != '') {
			$this->_govTalkKeys[] = array('type' => $keyType,
			                              'value' => $keyValue);
			return true;
		} else {
			return false;
		}

	}

	/**
	 * Remove a key-value pair from the set of keys to be sent with the message
	 * as part of the GovTalkDetails element.
	 *
	 * Searching is done primarily on key type (type attribute) and all keys with
	 * a corresponding type attribute are deleted.  An optional value argument
	 * can be provided, and in these cases only keys with matching key type AND
	 * key value will be deleted (but again all keys which meeting these
	 * criterion will be deleted).
	 *
	 * @param string $keyType The key type (type attribute) to be deleted.
	 * @param string $keyValue The key value to be deleted.
	 * @return integer The number of keys deleted.
	 */
	public function deleteMessageKey($keyType, $keyValue = null) {

		$deletedCount = 0;
		$possibleMatches = array();
		foreach ($this->_govTalkKeys AS $arrayKey => $value) {
			if ($value['type'] == $keyType) {
				if (($keyValue !== null) && ($keyValue !== $value['value'])) {
					continue;
				}
				$deletedCount++;
				unset($this->_govTalkKeys[$arrayKey]);
			}
		}

		return $deletedCount;

	}

	/**
	 * Removes all GovTalkDetails Key key-value pairs.
	 *
	 * @return boolean Always returns true.
	 */
	public function resetMessageKeys() {

		$this->_govTalkKeys = array();
		return true;

	}

 /* Target details related methods. */

	/**
	 * Add an organisation to the TargetDetails section of the GovTalkDetail
	 * element.
	 *
	 * @param string $targetOrganisation The organisation to be added.
	 * @param boolean $force If true the target already exists check is not carried out and the target is added regardless of duplicates. (Defaults to false.)
	 * @return boolean True if the key is valid and added, false if it's not valid (and therefore not added).
	 */
	public function addTargetOrganisation($targetOrganisation, $force = false) {

		if (($targetOrganisation != '') && (strlen($targetOrganisation) < 65)) {
			if (($force === false) && in_array($targetOrganisation, $this->_messageTargetDetails)) {
				return true;
			} else {
				$this->_messageTargetDetails[] = $targetOrganisation;
				return true;
			}
		} else {
			return false;
		}

	}

	/**
	 * Remove an organisation from TargetDetails section of the GovTalkDetail
	 * element.
	 *
	 * If more than one organisation matches the given organisation name all are
	 * removed.
	 *
	 * @param string $targetOrganisation The organisation to be deleted.
	 * @return integer The number of organisations deleted.
	 */
	public function deleteTargetOrganisation($targetOrganisation) {

		if (($targetOrganisation != '') && (strlen($targetOrganisation) < 65)) {
			$deletedCount = 0;
			foreach ($this->_messageTargetDetails AS $key => $organisation) {
				if ($organisation == $targetOrganisation) {
					$deletedCount++;
					unset($this->_messageTargetDetails[$key]);
				}
			}
			return $deletedCount;
		} else {
			return false;
		}

	}

	/**
	 * Removes all GovTalkDetails TargetDetails organisations.
	 *
	 * @return boolean Always returns true.
	 */
	public function resetTargetOrganisations() {

		$this->_messageTargetDetails = array();
		return true;

	}
	
 /* Specific generic Gateway requests. */

	/**
	 * Sends a generic delete request. By default the request refers to the last
	 * stored correlation ID and class, but this behaviour can be over-ridden by
	 * providing both correlation ID and class to the method.
	 *
	 * @param string $govTalkServer The GovTalk server to send the delete request to. May be skipped with a null value.
	 * @param string $correlationId The correlation ID to be deleted.
	 * @param string $messageClass The class used when the request which generated the correlation ID was sent to the gateway.
	 * @return boolean True if message was successfully deleted from the gateway, false otherwise.
	 */
	public function sendDeleteRequest($correlationId = null, $messageClass = null) {
	
		if (($correlationId !== null) && ($messageClass !== null)) {
			if (preg_match('/[0-9A-F]{0,32}/', $correlationId)) {
				$correlationId = $correlationId;
				$messageClass = $messageClass;
			} else {
				return false;
			}
		} else {
			if ($correlationId = $this->getResponseCorrelationId()) {
				$messageClass = $this->_messageClass;
			} else {
				return false;
			}
		}
		
		$this->setMessageClass($messageClass);
		$this->setMessageQualifier('request');
		$this->setMessageFunction('delete');
		$this->setMessageCorrelationId($correlationId);
		$this->setMessageBody('');
		
		if ($this->sendMessage() && ($this->responseHasErrors() === false)) {
			return true;
		} else {
			return false;
		}
	
	}
	
	/**
	 * Submits and processes a generic list request. By default the request
	 * refers to the last stored message class, but this behaviour can be over-
	 * ridden by providing a different class to the method.
	 *
	 * @param string $messageClass The class of request to list
	 */
	public function sendListRequest($messageClass = null) {

		if ($messageClass === null) {
			$messageClass = $this->_messageClass;
		}
		
		$this->setMessageClass($messageClass);
		$this->setMessageQualifier('request');
		$this->setMessageFunction('list');
		$this->setMessageCorrelationId('');
		$this->setMessageBody('');

		if ($this->sendMessage() && ($this->responseHasErrors() === false)) {
			if ((string) $this->_fullResponseObject->Header->MessageDetails->Qualifier == 'response') {
				$returnArray = array();
				foreach ($this->_fullResponseObject->Body->StatusReport->StatusRecord AS $reportNode) {
					preg_match('#(\d{2})/(\d{2})/(\d{4}) (\d{2}):(\d{2}):(\d{2})#', $reportNode->TimeStamp, $timeChunks);
					$returnArray[] = array('timestamp' => mktime($timeChunks[4], $timeChunks[5], $timeChunks[6], $timeChunks[2], $timeChunks[1], $timeChunks[3]),
					                       'correlation' => (string) $reportNode->CorrelationID,
					                       'transaction' => (string) $reportNode->TransactionID,
					                       'status' => (string) $reportNode->Status);
				}
				return $returnArray;
			} else {
				return false;
			}
		} else {
			return false;
		}

	}

 /* Message sending related methods. */

	/**
	 * Sends the message currently stored in the object to the currently defined
	 * GovTalkServer and parses the response for use later.
	 *
	 * Note: the return value of this method does not reflect the success of the
	 * data transmitted to the Gateway, but that the message was transmitted
	 * correctly and that a response was received.  Applications must query
	 * the response methods to discover more informationa about the data recieved
	 * in the Gateway reply.
	 *
	 * @param mixed
	 * @return boolean True if the message was successfully submitted to the Gateway and a response was received, false if not.
	 */
	public function sendMessage() {
	
		if ($this->_fullRequestString = $this->_packageGovTalkEnvelope()) {
			$this->_fullResponseString = $this->_fullResponseObject = null;
		   if (function_exists('curl_init')) {
				$curlHandle = curl_init($this->_govTalkServer);
				curl_setopt($curlHandle, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
				curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $this->_fullRequestString);
				$gatewayResponse = curl_exec($curlHandle);
				curl_close($curlHandle);
			} else {
				$streamOptions = array('http' => array('method' => 'POST',
				                                       'header' => 'Content-Type: text/xml',
				                                       'content' => $this->_fullRequestString));
				if ($fileHandle = @fopen($this->_govTalkServer, 'r', false, stream_context_create($streamOptions))) {
					$gatewayResponse = stream_get_contents($fileHandle);
				} else {
					return false;
				}
			}
			if ($gatewayResponse !== false) {
				$this->_fullResponseString = $gatewayResponse;
				$validXMLResponse = false;
				if ($this->_messageTransformation == 'XML') {
					if (isset($this->_additionalXsiSchemaLocation) && ($this->_schemaValidation == true)) {
						$xsiSchemaHeaders = @get_headers($this->_additionalXsiSchemaLocation);
						if ($xsiSchemaHeaders[0] != 'HTTP/1.1 404 Not Found') {
							$validate = new DOMDocument();
							$validate->loadXML($this->_fullResponseString);
							if ($validate->schemaValidate($this->_additionalXsiSchemaLocation)) {
								$validXMLResponse = true;
							}
						} else {
							return false;
						}
					} else {
						$validXMLResponse = true;
					}
				}
				if ($validXMLResponse === true) {
					$this->_fullResponseObject = simplexml_load_string($gatewayResponse);
				}
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}

	}

 /* Protected methods. */

	/**
	 * This method is designed to be over-ridden by extending classes which
	 * require an alternative authentication algorithm.
	 *
	 * These methods should take the transaction ID as an argument and return
	 * an array of 'method' => the method string to use in IDAuthentication->
	 * Authentication->Method, 'token' => the token to use in IDAuthentication->
	 * Authentication->Value, or false on failure.
	 *
	 * @param string $transactionId Transaction ID to use generating the token.
	 * @return mixed The authentication array, or false on failure.
	 */
	protected function generateAlternativeAuthentication($transactionId) {

	   return false;

	}
	
	/**
	 * This method is designed to be over-ridden by extending classes which
	 * require the final XML package to be digested (and, perhaps, altered) in
	 * a special way prior to transmission.
	 *
	 * These methods should take the full XML package as an argument and return
	 * the new digested package. If the package is not altered by the digest it
	 * must return the passed package unaltered.
	 *
	 * @param string $package The package to digest.
	 * @return string The new (or unaltered) package after application of the digest.
	 */
	protected function packageDigest($package) {
	
		return $package;
	
	}

	/**
	 * Adds a new error to the end of the error array.
	 *
	 * @since 0.4
	 * @see $_errorArray
	 * @param string $errorCode An error code identifying this error being logged. While not globally unique, care should be taken to make this useful.
	 * @param string $errorMessage An error message in plain text. This might be displayed to the user by applications, so should be something pretty descriptive. (Optional.)
	 * @param string $function The function which generated this error. While this is optional, and might not be very helpful (depending on the error), it's easy to add with __FUNCTION__. (Optional.)
	 * @return boolean This function always returns true.
	 */
	protected function _logError($errorCode, $errorMessage = null, $function = null) {

		$this->_errorArray[] = array('time' => microtime(true),
		                             'code' => $errorCode,
		                             'message' => $errorMessage,
		                             'function' => $function);
		return true;

	}

	/**
	 * Packages the message currently stored in the object into a valid GovTalk
	 * envelope ready for sending.
	 *
	 * @return mixed The XML package (as a string) in GovTalk format, or false on failure.
	 */
	protected function _packageGovTalkEnvelope() {

	 // Firstly check we have everything we need to build the envelope...
		if (isset($this->_messageClass) && isset($this->_messageQualifier)) {
			if (isset($this->_govTalkSenderId) && isset($this->_govTalkPassword)) {
				if (isset($this->_messageAuthType)) {
	 // Generate the transaction ID...
					$this->_generateTransactionId();
					if (isset($this->_messageBody)) {
	 // Create the XML document (in memory)...
						$package = new XMLWriter();
						$package->openMemory();
						$package->setIndent(true);

	 // Packaging...
						$package->startElement('GovTalkMessage');
						$xsiSchemaLocation = 'http://www.govtalk.gov.uk/documents/envelope-v2-0.xsd';
						if ($this->_additionalXsiSchemaLocation !== null) {
							$xsiSchemaLocation .= ' '.$this->_additionalXsiSchemaLocation;
						}
						$package->writeAttribute('xmlns', 'http://www.govtalk.gov.uk/CM/envelope');
						$package->writeAttributeNS('xsi', 'schemaLocation', 'http://www.w3.org/2001/XMLSchema-instance', $xsiSchemaLocation);
							$package->writeElement('EnvelopeVersion', '2.0');

	 // Header...
							$package->startElement('Header');

	 // Message details...
								$package->startElement('MessageDetails');
									$package->writeElement('Class', $this->_messageClass);
									$package->writeElement('Qualifier', $this->_messageQualifier);
									if ($this->_messageFunction !== null) {
										$package->writeElement('Function', $this->_messageFunction);
									}
									$package->writeElement('TransactionID', $this->_transactionId);
									if ($this->_messageCorrelationId !== null) {
										$package->writeElement('CorrelationID', $this->_messageCorrelationId);
									}
									if ($this->_messageTransformation !== 'XML') {
										$package->writeElement('Transformation', $this->_messageTransformation);
									}
									$package->writeElement('GatewayTest', $this->_govTalkTest);
								$package->endElement(); # MessageDetails

	 // Sender details...
								$package->startElement('SenderDetails');

	 // Authentication...
									$package->startElement('IDAuthentication');
										$package->writeElement('SenderID', $this->_govTalkSenderId);
										$package->startElement('Authentication');
										switch ($this->_messageAuthType) {
											case 'alternative':
												if ($authenticationArray = $this->generateAlternativeAuthentication($this->_transactionId)) {
													$package->writeElement('Method', $authenticationArray['method']);
													$package->writeElement('Value', $authenticationArray['token']);
												} else {
													return false;
												}
											break;
											case 'clear':
												$package->writeElement('Method', 'clear');
												$package->writeElement('Value', $this->_govTalkPassword);
											break;
										}
										$package->endElement(); # Authentication
									$package->endElement(); # IDAuthentication
									if ($this->_senderEmailAddress !== null) {
										$package->writeElement('EmailAddress', $this->_senderEmailAddress);
									}

								$package->endElement(); # SenderDetails

							$package->endElement(); # Header

	 // GovTalk details...
							$package->startElement('GovTalkDetails');

	 // Keys...
								if (count($this->_govTalkKeys) > 0) {
									$package->startElement('Keys');
									foreach ($this->_govTalkKeys AS $keyPair) {
										$package->startElement('Key');
											$package->writeAttribute('Type', $keyPair['type']);
											$package->text($keyPair['value']);
										$package->endElement(); # Key
									}
									$package->endElement(); # Keys
								}

	 // Target details...
								if (count($this->_messageTargetDetails) > 0) {
									$package->startElement('TargetDetails');
									foreach ($this->_messageTargetDetails AS $targetOrganisation) {
										$package->writeElement('Organisation', $targetOrganisation);
									}
									$package->endElement(); # TargetDetails
								}

	 // Channel routing...
								$channelRouteArray = $this->_messageChannelRouting;
								$channelRouteArray[] = array('uri' => 'http://code.google.com/p/php-govtalk/',
								                             'product' => 'php-govtalk',
								                             'version' => '1.0',
								                             'timestamp' => date('c'));
								foreach ($channelRouteArray AS $channelRoute) {
									$package->startElement('ChannelRouting');
										$package->startElement('Channel');
											$package->writeElement('URI', $channelRoute['uri']);
											if (array_key_exists('product', $channelRoute)) {
												$package->writeElement('Product', $channelRoute['product']);
											}
											if (array_key_exists('version', $channelRoute)) {
												$package->writeElement('Version', $channelRoute['version']);
											}
										$package->endElement(); # Channel
										if (array_key_exists('id', $channelRoute) && is_array($channelRoute['id'])) {
											foreach ($channelRoute['id'] AS $channelRouteId) {
												$package->startElement('ID');
													$package->writeAttribute('type', $channelRouteId['type']);
													$package->text($channelRouteId['value']);
												$package->endElement(); # ID
											}
										}
										$package->writeElement('Timestamp', $channelRoute['timestamp']);
									$package->endElement(); # ChannelRouting
								}

							$package->endElement(); # GovTalkDetails

	 // Body...
							$package->startElement('Body');
							if (is_string($this->_messageBody)) {
								$package->writeRaw("\n".trim($this->_messageBody)."\n");
							} else if (is_a($this->_messageBody, 'XMLWriter')) {
								$package->writeRaw("\n".trim($this->_messageBody->outputMemory())."\n");
							}
							$package->endElement(); # Body

						$package->endElement(); # GovTalkMessage

	 // Flush the buffer, run any extension-specific digests, validate the schema
	 // and return the XML...
						$xmlPackage = $this->packageDigest($package->flush());
						$validXMLRequest = true;
						if (isset($this->_additionalXsiSchemaLocation) && ($this->_schemaValidation == true)) {
							$validation = new DOMDocument();
							$validation->loadXML($xmlPackage);
							if (!$validation->schemaValidate($this->_additionalXsiSchemaLocation)) {
								$validXMLRequest = false;
							}
						}
						if ($validXMLRequest === true) {
							return $xmlPackage;
						} else {
							return false;
						}
						
					} else {
						return false;
					}
				} else {
					return false;
				}
			} else {
				return false;
			}
		} else {
			return false;
		}

	}
	
	/**
	 * Packages the given array into an XMLWriter object where each element takes
	 * its name from the array index, and its value from the array value.  In the
	 * case of nested arrays each level is added below the previous element (as
	 * you would expect).  Where an array has numeric indices each element takes
	 * its name from the parent array.
	 *
	 * @param mixed $informationArray The information to be turned into an XMLWriter object.
	 * @param string $parentElement The name of the parent element, to be applied if the current $informationArray is numerically indexed.
	 * @return XMLWriter An XMLWriter object representing the given array in XML.
	 */
	protected function _xmlPackageArray($informationArray, $parentElement = null) {

		if (is_array($informationArray)) {
			$package = new XMLWriter();
			$package->openMemory();
			$package->setIndent(true);
			foreach ($informationArray AS $elementKey => $elementValue) {
				if (is_array($elementValue)) {
					$packagedArray = $this->_xmlPackageArray($elementValue, $elementKey);
					reset($elementValue);
					if (!is_int(key($elementValue))) {
						$package->startElement($elementKey);
							$package->writeRaw("\n".trim($packagedArray->outputMemory())."\n");
						$package->endElement();
					} else {
						$package->writeRaw("\n".trim($packagedArray->outputMemory())."\n");
					}
				} else {
					if (is_int($elementKey)) {
						$elementKey = $parentElement;
					}
					$package->writeElement($elementKey, $elementValue);
				}
			}
			return $package;
		} else {
			return false;
		}

	}

 /* Private methods. */

	/**
	 * Generates the transaction ID required for GovTalk authentication. Although
	 * the GovTalk specifcation defines a valid transaction ID as [0-9A-F]{0,32}
	 * some government gateways using GovTalk only accept numeric transaction
	 * IDs. Therefore this implementation generates only a numeric transaction
	 * ID.
	 *
	 * @return boolean Always returns true.
	 */
	private function _generateTransactionId() {
	
		list($usec, $sec) = explode(' ', microtime());
		$this->_transactionId = $sec.str_replace('0.', '', $usec);
		return true;

	}

}
