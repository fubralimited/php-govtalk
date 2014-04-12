<?php

/*
 * This file is part of the GovTalk package
 *
 * (c) Justin Busschau
 *
 * For the full copyright and license information, please see the LICENSE
 * file that was distributed with this source code.
 */

namespace GovTalk;

use GovTalk\TestCase;
use GovTalk\GovTalk;
use XMLWriter;

/**
 * The base class for all GovTalk tests
 */
class GovTalkTest extends TestCase
{
    /**
     * The gateway user ID
     */
    private $gatewayUserID;

    /**
     * The gateway user password
     */
    private $gatewayUserPassword;

    /**
     * The gateway address
     */
    private $gatewayURL;


    /**
     * Set up the test environment
     */
    public function setUp()
    {
        parent::setUp();

        /**
         * The user name (Sender ID) and password given below are not valid for
         * either the live or any of the test/dev gateways. If you want to run
         * this test suite against actual servers, please contact the relevant
         * agency (HMRC / Companies House / etc.) and apply for valid credentials.
         */
        $this->gatewayUserID = 'XMLGatewayTestUserID';
        $this->gatewayUserPassword = 'XMLGatewayTestPassword';


        /**
         * By default messages to/from the gateway are mocked for this test suite.
         * Provide a legitimate test endpoint and remove all the calls to the
         * setMockHttpResponse function in order to test against actual gateways.
         */
        $this->gatewayURL = 'https://secure.dev.gateway.gov.uk/submission';

        /**
         * The following call sets up the service object used to interact with the
         * Government Gateway. Setting parameter 4 to null will force the test to
         * use the httpClient created on the fly within the GovTalk class and may
         * also effectively disable mockability.
         * Set parameter 5 to a valid path in order to log messages
         */
        $this->gtService = $this->setUpService();
    }

    private function setUpService()
    {
        return new GovTalk(
            $this->gatewayURL,
            $this->gatewayUserID,
            $this->gatewayUserPassword,
            $this->getHttpClient(),
            null
        );
    }

    public function testSettingTestFlag()
    {
        $this->assertTrue($this->gtService->setTestFlag(false));
        $this->assertFalse($this->gtService->getTestFlag());

        $this->assertFalse($this->gtService->setTestFlag('yes'));
        $this->assertFalse($this->gtService->getTestFlag());

        $this->assertTrue($this->gtService->setTestFlag(true));
        $this->assertTrue($this->gtService->getTestFlag());
    }

    public function testMessageAuthentication()
    {
        $this->assertTrue($this->gtService->setMessageAuthentication('alternative'));
        $this->assertEquals($this->gtService->getMessageAuthentication(), 'alternative');

        $this->assertFalse($this->gtService->setMessageAuthentication('someOther'));
        $this->assertEquals($this->gtService->getMessageAuthentication(), 'alternative');

        $this->assertTrue($this->gtService->setMessageAuthentication('clear'));
        $this->assertEquals($this->gtService->getMessageAuthentication(), 'clear');
    }

    public function testSettingEmailAddress()
    {
        $this->assertTrue($this->gtService->setSenderEmailAddress('jane@doeofjohn.com'));
        $this->assertEquals($this->gtService->getSenderEmailAddress(), 'jane@doeofjohn.com');

        $this->assertFalse($this->gtService->setSenderEmailAddress('joebloggscom'));
        $this->assertEquals($this->gtService->getSenderEmailAddress(), 'jane@doeofjohn.com');

        $this->assertTrue($this->gtService->setSenderEmailAddress('joe@bloggs.com'));
        $this->assertEquals($this->gtService->getSenderEmailAddress(), 'joe@bloggs.com');
    }
    
    public function testAddingMessageKey()
    {
        $this->assertTrue($this->gtService->addMessageKey('VATRegNo', '999900001'));
        $this->assertFalse($this->gtService->addMessageKey(array('VATRegNo'), '999900001'));
    }

    public function testDeletingMessageKey()
    {
        $this->assertTrue($this->gtService->addMessageKey('MyKey', '123456789'));
        $this->assertEquals($this->gtService->deleteMessageKey('MyKey'), 1);
    }

    public function testResettingMessageKeys()
    {
        $this->assertTrue($this->gtService->addMessageKey('VATRegNo', '999900001'));
        $this->assertTrue($this->gtService->addMessageKey('MyKey', '123456789'));
        $this->assertTrue($this->gtService->resetMessageKeys());
    }

    public function testSettingMessageClass()
    {
        $this->assertFalse($this->gtService->setMessageClass('HVD'));
        $this->assertTrue($this->gtService->setMessageClass('HMRC-VAT-DEC'));
    }

    public function testSettingMessageQualifier()
    {
        $this->assertTrue($this->gtService->setMessageQualifier('error'));
        $this->assertFalse($this->gtService->setMessageQualifier('other'));
        $this->assertTrue($this->gtService->setMessageQualifier('request'));
    }

    public function testSettingMessageFunction()
    {
        $this->assertTrue($this->gtService->setMessageFunction('submit'));
    }

    public function testAddingChannelRoute()
    {
        $this->assertFalse($this->gtService->addChannelRoute(array('uri' => 'a', 'product' => 'b', 'version' => 'c')));
        $this->assertTrue($this->gtService->addChannelRoute('a', 'b', 'c', array(array('1','2','3')), '2014-04-04T12:28.123'));
        $this->assertTrue($this->gtService->addChannelRoute('d', 'e', 'f', null, '', true));
    }

    public function testSettingMessageBody()
    {
        $this->assertFalse($this->gtService->setMessageBody(array('')));
        $this->assertTrue($this->gtService->setMessageBody(new XMLWriter));
        $this->assertTrue($this->gtService->setMessageBody(''));
        $this->assertTrue(
            $this->gtService->setMessageBody(
                file_get_contents(__DIR__.'/Messages/VatReturnIREnvelope.txt')
            )
        );
    }

    public function testConstructAndSendMessage()
    {
        $this->setMockHttpResponse('VatReturnAuthFailure.txt');

        $this->gtService = $this->setUpService();
        $this->assertTrue($this->gtService->setTestFlag(true));
        $this->assertTrue($this->gtService->setMessageAuthentication('clear'));
        $this->assertTrue($this->gtService->setSenderEmailAddress('joe@bloggs.com'));
        $this->assertTrue($this->gtService->addMessageKey('VATRegNo', '999900001'));
        $this->assertTrue($this->gtService->setMessageClass('HMRC-VAT-DEC'));
        $this->assertTrue($this->gtService->setMessageQualifier('request'));
        $this->assertTrue($this->gtService->setMessageFunction('submit'));
        $this->gtService->addChannelRoute('http://fakeurl.com/fakeGateway', 'A fake channel route', '0.0.1');
        $this->assertTrue(
            $this->gtService->setMessageBody(
                file_get_contents(__DIR__.'/Messages/VatReturnIREnvelope.txt')
            )
        );
        $this->assertTrue($this->gtService->sendMessage());
        $this->assertTrue($this->gtService->responseHasErrors());
    }

    public function testSendPrebuiltMessage()
    {
        $this->setMockHttpResponse('GiftAidResponseAck.txt');
        $preBuiltMessage = file_get_contents(__DIR__.'/Messages/GiftAidRequest.txt');

        $this->gtService->sendMessage($preBuiltMessage);

        $this->assertSame($preBuiltMessage, $this->gtService->getFullXMLRequest());
    }
}

/**
 * TODO: The following public functions need tests:
 * - errorCount
 * - getErrors
 * - getLastError
 * - clearErrors
 * - responseHasErrors [-1]
 * - getTransactionId
 * - getFullXMLRequest [-1]
 * - getFullXMLResponse
 * - getResponseQualifier
 * - getGatewayTimestamp
 * - getResponseCorrelationId
 * - getResponseEndpoint
 * - getResponseErrors
 * - getResponseBody
 * - setGovTalkServer
 * - setSchemaLocation
 * - setSchemaValidation
 * - setMessageCorrelationId
 * - setMessageTransformation
 * - addTargetOrganisation
 * - deleteTargetOrganisation
 * - resetTargetOrganisations
 * - sendDeleteRequest
 * - sendListRequest
 */
