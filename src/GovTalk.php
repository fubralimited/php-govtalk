<?php
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
 *
 * @author Justin Busschau
 * @copyright 2013 - 2014, Justin Busschau
 * Refactored as PSR-2 for inclusion in justinbusschau/php-govtalk package.
 */

namespace GovTalk;

use XMLWriter;
use Guzzle\Http\ClientInterface;
use Guzzle\Http\Client as HttpClient;

class GovTalk
{

    /* Server related variables. */

    /**
     * A Guzzle client for making API calls
     *
     * @var \Guzzle\Http\ClientInterface
     */
    private $httpClient;

    /**
     * GovTalk server.
     *
     * @var string
     */
    private $govTalkServer;

    /**
     * GovTalk sender ID.
     *
     * @var string
     */
    protected $govTalkSenderId;

    /**
     * GovTalk sender password.
     *
     * @var string
     */
    protected $govTalkPassword;


    /**
     * Message log location.
     *
     * @var string - set null to suppress message logging
     */
    protected $messageLogLocation;


    /* General envelope related variables. */

    /**
     * Additional XSI SchemaLocation URL.  Default is null, no additional schema.
     *
     * @var string
     */
    private $additionalXsiSchemaLocation = null;

    /**
     * GovTalk test flag.  Default is 0, a real message.
     *
     * @var string
     */
    private $govTalkTest = '0';

    /**
     * Body of the message to be sent.
     *
     * @var mixed Can either be of type XMLWriter, or a string.
     */
    private $messageBody;


    /* MessageDetails related variables */

    /**
     * GovTalk message Class.
     *
     * @var string
     */
    private $messageClass;

    /**
     * GovTalk message Qualifier.
     *
     * @var string
     */
    private $messageQualifier;

    /**
     * GovTalk message Function.  Default is null, no specified function.
     *
     * @var string
     */
    private $messageFunction = null;

    /**
     * GovTalk message CorrelationID.  Default is null, no correlation ID.
     *
     * @var string
     */
    private $messageCorrelationId = null;

    /**
     * GovTalk message Transformation.  Default is null, return in standard XML.
     *
     * @var string
     */
    private $messageTransformation = 'XML';


    /* SenderDetails related variables. */

    /**
     * GovTalk SenderDetail EmailAddress.  Default is null, no email address.
     *
     * @var string
     */
    private $senderEmailAddress = null;

    /**
     * GovTalk message authentication type.
     *
     * @var string
     */
    private $messageAuthType;


    /* Keys related variables. */

    /**
     * GovTalk keys array.
     *
     * @var array
     */
    private $govTalkKeys = array();


    /* Channel routing related variables. */

    /**
     * GovTalk message channel routing array.
     *
     * @var array
     */
    private $messageChannelRouting = array();


    /* Target details related variables. */

    /**
     * GovTalk target details / organisations array.
     *
     * @var array
     */
    private $messageTargetDetails = array();


    /* Full request/response data variables. */

    /**
     * Full request data in string format (raw XML).
     *
     * @var string
     */
    protected $fullRequestString;

    /**
     * Full return data in string format (raw XML).
     *
     * @var string
     */
    protected $fullResponseString;

    /**
     * Full return data in object format (SimpleXML).
     *
     * @var string
     */
    protected $fullResponseObject;


    /* Error handling variables. */

    /**
     * An array containing all reported errors.
     *
     * The error array is stored and returned in the following format, one
     * one element for every error which has been reported:
     *   time => The unix timestamp (with microseconds) that this error was generated.
     *   code => A short error code. Defined by the function adding the error and not globally.
     *   message => A more descriptive error message. Defined by the function adding the error, but more verbose.
     *   function => The name of the calling function. (Optional.)
     *
     * @since 0.4
     * @var array
     */
    protected $errorArray = array();


    /* System / internal variables. */

    /**
     * Transaction ID of the last message sent / received.
     *
     * @var string
     */
    private $transactionId = null;

    /**
     * Flag indicating if the outgoing and incoming XML should be validated
     * against the XML schema. By default these checks will be made.
     *
     * @var boolean
     */
    private $schemaValidation = true;

    /**
     * Instance constructor.
     *
     * @param string $govTalkServer GovTalk server URL.
     * @param string $govTalkSenderId GovTalk sender ID.
     * @param string $govTalkPassword GovTalk password.
     * @param ClientInterface $httpClient A Guzzle client for making API calls
     * @param string $messageLogLocation Message log location (default null = no logging)
     */
    public function __construct(
        $govTalkServer,
        $govTalkSenderId,
        $govTalkPassword,
        ClientInterface $httpClient = null,
        $messageLogLocation = null
    ) {
        $this->setGovTalkServer($govTalkServer);
        $this->govTalkSenderId = $govTalkSenderId;
        $this->govTalkPassword = $govTalkPassword;
        $this->httpClient = $httpClient ?: $this->getDefaultHttpClient();
        $this->messageLogLocation = $messageLogLocation;
    }


    /* Public methods. */


    /* Error handling funtions. */

    /**
     * Adds a new error to the end of the error array.
     *
     * @since 0.4
     * @see $errorArray
     * @param string $errorCode An error code identifying this error being logged.
     *     While not globally unique, care should be taken to make this useful.
     * @param string $errorMessage An error message in plain text. This might be
     *     displayed to the user by applications, so should be something pretty descriptive. (Optional.)
     * @param string $function The function which generated this error. While this is optional,
     *     and might not be very helpful (depending on the error), it's easy to add with __FUNCTION__. (Optional.)
     * @return boolean This function always returns true.
     */
    protected function logError($errorCode, $errorMessage = null, $function = null)
    {
        $this->errorArray[] = array(
            'time' => microtime(true),
            'code' => $errorCode,
            'message' => $errorMessage,
            'function' => $function
        );
        return true;
    }

    /**
     * Returns the number of errors which have been logged in the error array
     * since this instance was initialised, or the error array was last reset.
     *
     * @since 0.4
     * @see logError(), clearErrors(), getErrors()
     * @return int The number of errors since the error array was last reset.
     */
    public function errorCount()
    {
        return count($this->errorArray);
    }

    /**
     * Returns the full error array.
     *
     * @since 0.4
     * @see getLastError(), $errorArray
     * @return array The complete error array.
     */
    public function getErrors()
    {
        return $this->errorArray();
    }

    /**
     * Returns the last error pushed onto the error array.
     *
     * @since 0.4
     * @see getErrors(), $errorArray
     * @return array The last element pushed onto the error array.
     */
    public function getLastError()
    {
        return end($this->errorArray);
    }

    /**
     * Clears all errors out of the error array.
     *
     * @since 0.4
     * @see $errorArray
     * @return boolean This function always returns true.
     */
    public function clearErrors()
    {
        $this->errorArray = array();
        return true;
    }


    /* Logical / operational / conditional methods */

    /**
     * Tests if a response has errors.  Should be checked before further
     * operations are carried out on the returned object.
     *
     * @return boolean True if errors are present, false if not.
     */
    public function responseHasErrors()
    {
        if (isset($this->fullResponseObject)) {
            if (isset($this->fullResponseObject->GovTalkDetails->GovTalkErrors)) {
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
    public function getTransactionId()
    {
        return $this->transactionId;
    }

    /**
     * Returns the full XML request from the last Gateway request, if there is
     * one.
     *
     * @return mixed The full text request from the Gateway, or false if this isn't set.
     */
    public function getFullXMLRequest()
    {
        if (isset($this->fullRequestString)) {
            return $this->fullRequestString;
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
    public function getFullXMLResponse()
    {
        if (isset($this->fullResponseString)) {
            return $this->fullResponseString;
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
    public function getResponseQualifier()
    {
        if (isset($this->fullResponseObject)) {
            return (string) $this->fullResponseObject->Header->MessageDetails->Qualifer;
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
    public function getGatewayTimestamp()
    {
        if (isset($this->fullResponseObject)) {
            return strtotime((string) $this->fullResponseObject->Header->MessageDetails->GatewayTimestamp);
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
    public function getResponseCorrelationId()
    {
        if (isset($this->fullResponseObject)) {
            if (isset($this->fullResponseObject->Header->MessageDetails->CorrelationID)) {
                return (string) $this->fullResponseObject->Header->MessageDetails->CorrelationID;
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
    public function getResponseEndpoint()
    {
        if (isset($this->fullResponseObject)) {
            if (isset($this->fullResponseObject->Header->MessageDetails->ResponseEndPoint)) {
                if (isset($this->fullResponseObject->Header->MessageDetails->ResponseEndPoint['PollInterval'])) {
                    $pollInterval = (string) $this->fullResponseObject
                        ->Header->MessageDetails->ResponseEndPoint['PollInterval'];
                } else {
                    $pollInterval = null;
                }
                $endpoint = (string) $this->fullResponseObject->Header->MessageDetails->ResponseEndPoint;
                return array(
                    'endpoint' => $endpoint,
                    'interval' => $pollInterval
                );
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
    public function getResponseErrors()
    {
        if ($this->responseHasErrors()) {
            $errorArray = array(
                'fatal' => array(),
                'recoverable' => array(),
                'business' => array(),
                'warning' => array()
            );
            foreach ($this->fullResponseObject->GovTalkDetails->GovTalkErrors->Error as $responseError) {
                $errorDetails = array(
                    'number' => (string) $responseError->Number,
                    'text' => (string) $responseError->Text
                );
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
    public function getResponseBody()
    {
        if (isset($this->fullResponseObject)) {
            return $this->fullResponseObject->Body;
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
    public function setGovTalkServer($govTalkServer)
    {
        $this->govTalkServer = $govTalkServer;
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
    public function setSchemaLocation($schemaLocation, $validate = null)
    {
        if (preg_match('/^https?:\/\/[\w-.]+\.gov\.uk/', $schemaLocation)) {
            $this->additionalXsiSchemaLocation = $schemaLocation;
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
    public function setSchemaValidation($validate)
    {
        if (is_bool($validate)) {
            $this->schemaValidation = $validate;
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
    public function setTestFlag($testFlag)
    {
        if (is_bool($testFlag)) {
            if ($testFlag === true) {
                $this->govTalkTest = '1';
            } else {
                $this->govTalkTest = '0';
            }
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * Gets the current status of the test flag.
     *
     * @return boolean the current state of the test flag.
     */
    public function getTestFlag()
    {
        return $this->govTalkTest == '1';
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
    public function setMessageBody($messageBody, $xmlSchema = null)
    {
        if (is_string($messageBody) || is_a($messageBody, 'XMLWriter')) {
            if ($xmlSchema !== null) {
                $validate = new DOMDocument();
                if (is_string($messageBody)) {
                    $validate->loadXML($messageBody);
                } else {
                    $validate->loadXML($messageBody->outputMemory());
                }
                if ($validate->schemaValidate($xmlSchema)) {
                    $this->messageBody = $messageBody;
                    return true;
                } else {
                    return false;
                }
            } else {
                $this->messageBody = $messageBody;
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
    public function setMessageClass($messageClass)
    {
        $messageClassLength = strlen($messageClass);
        if (($messageClassLength > 4) && ($messageClassLength < 32)) {
            $this->messageClass = $messageClass;
            return true;
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
    public function setMessageQualifier($messageQualifier)
    {
        $messageQualifier = strtolower($messageQualifier);
        switch ($messageQualifier) {
            case 'request':
            case 'acknowledgement':
            case 'reponse':
            case 'poll':
            case 'error':
                $this->messageQualifier = $messageQualifier;
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
    public function setMessageFunction($messageFunction)
    {
        $this->messageFunction = $messageFunction;
        return true;
    }

    /**
     * Sets the message CorrelationID for use in MessageDetails header.
     *
     * @param string $messageCorrelationId The correlation ID to set.
     * @return boolean True if the CorrelationID is valid and set, false if it's invalid (and therefore not set).
     * @see function getResponseCorrelationId
     */
    public function setMessageCorrelationId($messageCorrelationId)
    {
        if (preg_match('/[0-9A-F]{0,32}/', $messageCorrelationId)) {
            $this->messageCorrelationId = $messageCorrelationId;
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
    public function setMessageTransformation($transformation)
    {
        switch ($transformation) {
            case 'XML':
            case 'HTML':
            case 'text':
                $this->messageTransformation = $transformation;
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
    public function setSenderEmailAddress($senderEmailAddress)
    {
        if (preg_match('/[A-Za-z0-9\.\-_]{1,64}@[A-Za-z0-9\.\-_]{1,64}/', $senderEmailAddress)) {
            $this->senderEmailAddress = $senderEmailAddress;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Gets the currently configured email address.
     *
     * @return string The currently held email address
     */
    public function getSenderEmailAddress()
    {
        return $this->senderEmailAddress;
    }

    /**
     * Sets the type of authentication to use for with the message.  The message
     * type must be one of 'alternative', 'clear', 'MD5' or 'W3Csigned'. Other
     * values will not be set and will return false.
     *
     * @param string $messageAuthType The type of authentication to set.
     * @return boolean True if the authentication type is valid and set, false if it's invalid (and therefore not set).
     */
    public function setMessageAuthentication($messageAuthType)
    {
        switch ($messageAuthType) {
            case 'alternative':
            case 'clear':
            case 'MD5':
            case 'W3Csigned':
                $this->messageAuthType = $messageAuthType;
                return true;
            break;
            default:
                return false;
            break;
        }
    }

    /**
     * Gets the current value for authentication type
     *
     * @return string The current authentication method
     */
    public function getMessageAuthentication()
    {
        return $this->messageAuthType;
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
     * @param string $timestamp Representing the time this route processed the message (xsd:dateTime format).
     * @param boolean $force If true the route already exists check is not carried out
     *     and the target is added regardless of duplicates. (Defaults to false.)
     * @return boolean True if the route is valid and added, false if it's not valid (and therefore not added).
     */
    public function addChannelRoute(
        $uri,
        $softwareName = null,
        $softwareVersion = null,
        array $id = null,
        $timestamp = null,
        $force = false
    ) {
        if (is_string($uri)) {
            $newRoute = array('uri' => $uri);
            if ($softwareName !== null) {
                $newRoute['product'] = $softwareName;
            }
            if ($softwareVersion !== null) {
                $newRoute['version'] = $softwareVersion;
            }
            if ($id !== null && is_array($id)) {
                foreach ($id as $idElement) {
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
                foreach ($this->messageChannelRouting as $channelRoute) {
                    if (($channelRoute['product'] == $newRoute['product']) &&
                        ($channelRoute['version'] == $newRoute['version'])
                    ) {
                        $matchedChannel = true;
                        break;
                    }
                }
                if ($matchedChannel == false) {
                    $this->messageChannelRouting[] = $newRoute;
                }
                return true;
            } else {
                $this->messageChannelRouting[] = $newRoute;
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
    public function addMessageKey($keyType, $keyValue)
    {
        if (is_string($keyType) && $keyValue != '') {
            $this->govTalkKeys[] = array(
                'type' => $keyType,
                'value' => $keyValue
            );
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
    public function deleteMessageKey($keyType, $keyValue = null)
    {
        $deletedCount = 0;
        $possibleMatches = array();
        foreach ($this->govTalkKeys as $arrayKey => $value) {
            if ($value['type'] == $keyType) {
                if (($keyValue !== null) && ($keyValue !== $value['value'])) {
                    continue;
                }
                $deletedCount++;
                unset($this->govTalkKeys[$arrayKey]);
            }
        }

        return $deletedCount;
    }

    /**
     * Removes all GovTalkDetails Key key-value pairs.
     *
     * @return boolean Always returns true.
     */
    public function resetMessageKeys()
    {
        $this->govTalkKeys = array();
        return true;
    }


    /* Target details related methods. */

    /**
     * Add an organisation to the TargetDetails section of the GovTalkDetail
     * element.
     *
     * @param string $targetOrganisation The organisation to be added.
     * @param boolean $force If true the target already exists check is not carried out and the
     *     target is added regardless of duplicates. (Defaults to false.)
     * @return boolean True if the key is valid and added, false if it's not valid (and therefore not added).
     */
    public function addTargetOrganisation($targetOrganisation, $force = false)
    {
        if (($targetOrganisation != '') && (strlen($targetOrganisation) < 65)) {
            if (($force === false) &&
                in_array($targetOrganisation, $this->messageTargetDetails)
            ) {
                return true;
            } else {
                $this->messageTargetDetails[] = $targetOrganisation;
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
    public function deleteTargetOrganisation($targetOrganisation)
    {
        if (($targetOrganisation != '') && (strlen($targetOrganisation) < 65)) {
            $deletedCount = 0;
            foreach ($this->messageTargetDetails as $key => $organisation) {
                if ($organisation == $targetOrganisation) {
                    $deletedCount++;
                    unset($this->messageTargetDetails[$key]);
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
    public function resetTargetOrganisations()
    {
        $this->messageTargetDetails = array();
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
     * @param string $messageClass
     *     The class used when the request which generated the correlation ID was sent to the gateway.
     * @return boolean True if message was successfully deleted from the gateway, false otherwise.
     */
    public function sendDeleteRequest($correlationId = null, $messageClass = null)
    {
        if (($correlationId !== null) && ($messageClass !== null)) {
            if (preg_match('/[0-9A-F]{0,32}/', $correlationId)) {
                $correlationId = $correlationId;
                $messageClass = $messageClass;
            } else {
                return false;
            }
        } else {
            if ($correlationId = $this->getResponseCorrelationId()) {
                $messageClass = $this->messageClass;
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
    public function sendListRequest($messageClass = null)
    {
        if ($messageClass === null) {
            $messageClass = $this->messageClass;
        }

        $this->setMessageClass($messageClass);
        $this->setMessageQualifier('request');
        $this->setMessageFunction('list');
        $this->setMessageCorrelationId('');
        $this->setMessageBody('');

        if ($this->sendMessage() && ($this->responseHasErrors() === false)) {
            if ((string) $this->fullResponseObject->Header->MessageDetails->Qualifier == 'response') {
                $returnArray = array();
                foreach ($this->fullResponseObject->Body->StatusReport->StatusRecord as $reportNode) {
                    preg_match(
                        '#(\d{2})/(\d{2})/(\d{4}) (\d{2}):(\d{2}):(\d{2})#',
                        $reportNode->TimeStamp,
                        $timeChunks
                    );
                    $returnArray[] = array(
                        'timestamp' => mktime(
                            $timeChunks[4],
                            $timeChunks[5],
                            $timeChunks[6],
                            $timeChunks[2],
                            $timeChunks[1],
                            $timeChunks[3]
                        ),
                        'correlation' => (string) $reportNode->CorrelationID,
                        'transaction' => (string) $reportNode->TransactionID,
                        'status' => (string) $reportNode->Status
                    );
                }
                return $returnArray;
            }
        }
        return false;
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
     * @param mixed cRequestString If not null this will be used as the message payload
     * @return boolean True if the message was successfully submitted to the Gateway and a response was received.
     */
    public function sendMessage($cRequestString = null)
    {
        if ($cRequestString !== null) {
            $this->fullRequestString = $cRequestString;
        } else {
            $this->fullRequestString = $this->packageGovTalkEnvelope();
        }
        if ($this->fullRequestString) {
            $this->fullResponseString = $this->fullResponseObject = null;

            // Log the outgoing message
            if ($this->messageLogLocation !== null) {
                $ds = date('YmdHis');
                $f = fopen("{$this->messageLogLocation}/{$ds}-{$this->transactionId}-request.xml", 'w');
                fprintf($f, $this->fullRequestString);
                fclose($f);
            }

            $headers = array(
                'Content-Type' => 'text/xml; charset=utf-8'
            );

            $httpResponse = $this->httpClient->post(
                $this->govTalkServer,
                $headers,
                $this->fullRequestString
            )->send();

            $gatewayResponse = (string)$httpResponse->getBody();

//    Remove old usage of cURL - rather use via Guzzle as this is mockable
//            if (function_exists('curl_init')) {
//                $curlHandle = curl_init($this->govTalkServer);
//                curl_setopt($curlHandle, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
//                curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
//                curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, false);
//                curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $this->fullRequestString);
//                $gatewayResponse = curl_exec($curlHandle);
//                curl_close($curlHandle);
//            } else {
//                $streamOptions = array(
//                    'http' => array(
//                        'method' => 'POST',
//                        'header' => 'Content-Type: text/xml',
//                        'content' => $this->fullRequestString
//                    )
//                );
//                if ($fileHandle = @fopen($this->govTalkServer, 'r', false, stream_context_create($streamOptions))) {
//                    $gatewayResponse = stream_get_contents($fileHandle);
//                } else {
//                    return false;
//                }
//            }

            if ($gatewayResponse !== false) {

                // Log the incoming message
                if ($this->messageLogLocation !== null) {
                    $ds = date('YmdHis');
                    $f = fopen("{$this->messageLogLocation}/{$ds}-{$this->transactionId}-response.xml", 'w');
//                    fprintf($f, $gatewayResponse);
                    fprintf($f, $httpResponse);
                    fclose($f);
                }

                $this->fullResponseString = $gatewayResponse;
                $validXMLResponse = false;
                if ($this->messageTransformation == 'XML') {
                    if (isset($this->additionalXsiSchemaLocation) && ($this->schemaValidation == true)) {
                        $xsiSchemaHeaders = @get_headers($this->additionalXsiSchemaLocation);
                        if ($xsiSchemaHeaders[0] != 'HTTP/1.1 404 Not Found') {
                            $validate = new DOMDocument();
                            $validate->loadXML($this->fullResponseString);
                            if ($validate->schemaValidate($this->additionalXsiSchemaLocation)) {
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
                    $this->fullResponseObject = simplexml_load_string($gatewayResponse);
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
    protected function generateAlternativeAuthentication($transactionId)
    {
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
    protected function packageDigest($package)
    {
        return $package;
    }

    /**
     * Packages the message currently stored in the object into a valid GovTalk
     * envelope ready for sending.
     *
     * @return mixed The XML package (as a string) in GovTalk format, or false on failure.
     */
    protected function packageGovTalkEnvelope()
    {
        // Firstly check we have everything we need to build the envelope...
        if (isset($this->messageClass) and
            isset($this->messageQualifier) and
            isset($this->govTalkSenderId) and
            isset($this->govTalkPassword) and
            isset($this->messageAuthType)
        ) {

            // Generate the transaction ID...
            $this->generateTransactionId();
            if (isset($this->messageBody)) {

                // Create the XML document (in memory)...
                $package = new XMLWriter();
                $package->openMemory();
                $package->setIndent(true);

                // Packaging...
                $package->startElement('GovTalkMessage');
                $xsiSchemaName = 'http://www.govtalk.gov.uk/CM/envelope';
                $xsiSchemaLocation = $xsiSchemaName.' http://www.govtalk.gov.uk/documents/envelope-v2-0.xsd';
                if ($this->additionalXsiSchemaLocation !== null) {
                    $xsiSchemaLocation .= ' '.$this->additionalXsiSchemaLocation;
                }
                $package->writeAttribute('xmlns', $xsiSchemaName);
                $package->writeAttributeNS(
                    'xsi',
                    'schemaLocation',
                    'http://www.w3.org/2001/XMLSchema-instance',
                    $xsiSchemaLocation
                );
                $package->writeElement('EnvelopeVersion', '2.0');

                // Header...
                $package->startElement('Header');

                // Message details...
                $package->startElement('MessageDetails');
                $package->writeElement('Class', $this->messageClass);
                $package->writeElement('Qualifier', $this->messageQualifier);
                if ($this->messageFunction !== null) {
                    $package->writeElement('Function', $this->messageFunction);
                }
                $package->writeElement('TransactionID', $this->transactionId);
                $package->writeElement('CorrelationID', $this->messageCorrelationId);
                $package->writeElement('Transformation', $this->messageTransformation);
                $package->writeElement('GatewayTest', $this->govTalkTest);

                /**
                 * When using the Local Test Service (LTS) you need to set a
                 * time stamp in the Message Details element. If not testing
                 * to the LTS, the next four lines should be commented out
                 * to avoid adding the GatewayTimestamp element.
                 *
                 * NOTE: Using the standard ISO 8601 date format [i.e. date('c')] seemed
                 *     to cause the LTS some trouble, hence the hacked datestamp below.
                 */
                /*
                $package->writeElement(
                    'GatewayTimestamp',
                    ($this->govTalkTest == '1') ? substr(date('c'), 0, -6).'.000' : ''
                );
                */

                $package->endElement(); # MessageDetails

                // Sender details...
                $package->startElement('SenderDetails');

                // Authentication...
                $package->startElement('IDAuthentication');
                $package->writeElement('SenderID', $this->govTalkSenderId);
                $package->startElement('Authentication');
                switch ($this->messageAuthType) {
                    case 'alternative':
                        if ($authenticationArray = $this->generateAlternativeAuthentication($this->transactionId)) {
                            $package->writeElement('Method', $authenticationArray['method']);
                            $package->writeElement('Role', 'principal');
                            $package->writeElement('Value', $authenticationArray['token']);
                        } else {
                            return false;
                        }
                        break;
                    case 'clear':
                        $package->writeElement('Method', 'clear');
                        $package->writeElement('Role', 'principal');
                        $package->writeElement('Value', $this->govTalkPassword);
                        break;
                    case 'MD5':
                        $package->writeElement('Method', 'MD5');
                        $package->writeElement('Value', base64_encode(md5(strtolower($this->govTalkPassword), true)));
                        break;
                }
                $package->endElement(); # Authentication

                $package->endElement(); # IDAuthentication
                if ($this->senderEmailAddress !== null) {
                    $package->writeElement('EmailAddress', $this->senderEmailAddress);
                }

                $package->endElement(); # SenderDetails

                $package->endElement(); # Header

                // GovTalk details...
                $package->startElement('GovTalkDetails');

                // Keys...
                if (count($this->govTalkKeys) > 0) {
                    $package->startElement('Keys');
                    foreach ($this->govTalkKeys as $keyPair) {
                        $package->startElement('Key');
                        $package->writeAttribute('Type', $keyPair['type']);
                        $package->text($keyPair['value']);
                        $package->endElement(); # Key
                    }
                    $package->endElement(); # Keys
                }

                // Target details...
                if (count($this->messageTargetDetails) > 0) {
                    $package->startElement('TargetDetails');
                    foreach ($this->messageTargetDetails as $targetOrganisation) {
                        $package->writeElement('Organisation', $targetOrganisation);
                    }
                    $package->endElement(); # TargetDetails
                }

                // Channel routing...
                $channelRouteArray = $this->messageChannelRouting;
                $channelRouteArray[] = array(
                    'uri' => 'https://github.com/justinbusschau/php-govtalk/',
                    'product' => 'php-govtalk',
                    'version' => '0.1',
                    'timestamp' => date('c')
                );
                foreach ($channelRouteArray as $channelRoute) {
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
                        foreach ($channelRoute['id'] as $channelRouteId) {
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
                if (is_string($this->messageBody)) {
                    $package->writeRaw("\n".trim($this->messageBody)."\n");
                } elseif (is_a($this->messageBody, 'XMLWriter')) {
                    $package->writeRaw("\n".trim($this->messageBody->outputMemory())."\n");
                }
                $package->endElement(); # Body

                $package->endElement(); # GovTalkMessage

                // Flush the buffer, run any extension-specific digests, validate the schema
                // and return the XML...
                $xmlPackage = $this->packageDigest($package->flush());
                $validXMLRequest = true;
                if (isset($this->additionalXsiSchemaLocation) && ($this->schemaValidation == true)) {
                    $validation = new DOMDocument();
                    $validation->loadXML($xmlPackage);
                    if (!$validation->schemaValidate($this->additionalXsiSchemaLocation)) {
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
    }

    /**
     * Packages the given array into an XMLWriter object where each element takes
     * its name from the array index, and its value from the array value.  In the
     * case of nested arrays each level is added below the previous element (as
     * you would expect).  Where an array has numeric indices each element takes
     * its name from the parent array.
     *
     * @param mixed $informationArray The information to be turned into an XMLWriter object.
     * @param string $parentElement The name of the parent element, if the $informationArray is numerically indexed.
     * @return XMLWriter An XMLWriter object representing the given array in XML.
     */
    protected function xmlPackageArray($informationArray, $parentElement = null)
    {
        if (is_array($informationArray)) {
            $package = new XMLWriter();
            $package->openMemory();
            $package->setIndent(true);

            foreach ($informationArray as $elementKey => $elementValue) {
                if (is_array($elementValue)) {
                    $packagedArray = $this->xmlPackageArray($elementValue, $elementKey);
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
        }
        return false;
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
    private function generateTransactionId()
    {
        list($usec, $sec) = explode(' ', microtime());
        $this->transactionId = $sec.str_replace('0.', '', $usec);
        return true;
    }

    /*
     * Sets up the default HTTP Client
     */
    private function getDefaultHttpClient()
    {
        return new HttpClient(
            '',
            array(
                'curl.options' => array(
                    CURLOPT_CONNECTTIMEOUT => 60,
                    CURLOPT_RETURNTRANSFER => 1,
                    CURLOPT_SSL_VERIFYPEER => false
                )
            )
        );
    }
}
