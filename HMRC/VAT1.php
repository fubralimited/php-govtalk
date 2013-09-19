<?php

#
#  VAT.php
#
#  Created by Jonathon Wardman on 20-07-2009.
#  Copyright 2009, Fubra Limited. All rights reserved.
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
 * HMRC VAT API client.  Extends the functionality provided by the
 * GovTalk class to build and parse HMRC VAT submissions.  This class only
 * supports V1 of the HMRC VAT internet filing system.  The php-govtalk
 * base class needs including externally in order to use this extention.
 *
 * @author Jonathon Wardman
 * @copyright 2009, Fubra Limited
 * @licence http://www.gnu.org/licenses/gpl-3.0.txt GNU General Public License
 */
class HmrcVat1 extends GovTalk {

 /* Magic methods. */

	/**
	 * Instance constructor. Contains a hard-coded CH XMLGW URL and additional
	 * schema location.  Adds a channel route identifying the use of this
	 * extension.
	 *
	 * @param string $govTalkSenderId GovTalk sender ID.
	 * @param string $govTalkPassword GovTalk password.
	 * @param string $service The service to use ('tpvs', 'vsips', or 'live').
	 */
	public function __construct($govTalkSenderId, $govTalkPassword, $service = 'live') {

		switch ($service) {
			case 'tpvs':
				parent::__construct('https://www.tpvs.hmrc.gov.uk/HMRC/VATDEC', $govTalkSenderId, $govTalkPassword);
				$this->setTestFlag(true);
			break;
			case 'vsips':
				parent::__construct('https://secure.dev.gateway.gov.uk/submission', $govTalkSenderId, $govTalkPassword);
				$this->setTestFlag(true);
			break;
			default:
				parent::__construct('https://secure.gateway.gov.uk/submission', $govTalkSenderId, $govTalkPassword);
			break;
		}

		$this->setMessageAuthentication('clear');

	}

 /* Public methods. */

	/**
	 * Submits a VAT declaration request.
	 *
	 * This method supports final returns using the final argument.
	 *
	 * @param string $vatNumber The VAT number of the company this return is for.
	 * @param string $returnPeriod The period ID this return is for (in the format YYYY-MM).
	 * @param float $vatOutput VAT due on outputs (box 1).
	 * @param float $vatECAcq VAT due on EC acquisitions (box 2).
	 * @param float $vatReclaimedInput VAT reclaimed on inputs (box 4).
	 * @param float $netOutput Net sales and outputs (box 6).
	 * @param float $netInput Net purchases and inputs (box 7).
	 * @param float $netECSupply Net EC supplies (box 8).
	 * @param float $netECAcq Net EC acquisitions (box 9).
	 * @param float $totalVat Total VAT (box 3). If this value is not specified then it will be calculated as box 1 + box 2. May be skipped by passing null.
	 * @param float $netVat Net VAT (box 5). If this value is not specified then it will be calculated as the absolute difference between box 5 and box 4. May be skipped by passing null.
	 * @return mixed An array of 'endpoint', 'interval' and 'correlationid' on success, or false on failure.
	 */
	public function declarationRequest($vatNumber, $returnPeriod, $vatOutput, $vatECAcq, $vatReclaimedInput, $netOutput, $netInput, $netECSupply, $netECAcq, $totalVat = null, $netVat = null) {
	
		$vatNumber = trim(str_replace(' ', '', $vatNumber));
		if (preg_match('/^(GB)?(\d{9,12})$/', $vatNumber)) { # VAT number
			$this->addMessageKey('VATRegNo', $vatNumber);
			if (preg_match('/^\d{4}-\d{2}$/', $returnPeriod)) { # VAT period
					if (is_numeric($vatOutput) && is_numeric($vatECAcq) && is_numeric($vatReclaimedInput) && is_numeric($netOutput) && is_numeric($netInput) && is_numeric($netECSupply) && is_numeric($netECAcq)) {

	 // Set the message envelope bits and pieces for this request...
						$this->setMessageClass('HMCE-VATDEC-ORG-VAT100-STD');
						$this->setMessageQualifier('request');
						$this->setMessageFunction('submit');

	 // Build message body...
						$package = new XMLWriter();
						$package->openMemory();
						$package->setIndent(true);
						$package->startElement('VATDeclarationRequest');
							$package->writeAttribute('xmlns', 'http://www.govtalk.gov.uk/taxation/vat/vatdeclaration/1');
							$package->writeAttribute('xmlns:VATCore', 'http://www.govtalk.gov.uk/taxation/vat/core/1');
							$package->writeAttribute('xmlns:ns', 'http://www.govtalk.gov.uk/CM/gms-xs');
							$package->writeAttribute('SchemaVersion', '1.0');
							$package->startElement('Header');
								$package->startElement('VATCore:VATPeriod');
									$package->writeElement('VATCore:PeriodId', $returnPeriod);
								$package->endElement(); # VATCore:VATPeriod
								$package->writeElement('VATCore:CurrencyCode', 'GBP');
							$package->endElement(); # Header
							$package->startElement('Body');
								$package->writeElement('VATCore:VATDueOnOutputs', sprintf('%.2f', $vatOutput));
								$package->writeElement('VATCore:VATDueOnECAcquisitions', sprintf('%.2f', $vatECAcq));
								if ($totalVat === null) {
									$totalVat = $vatOutput + $vatECAcq;
								}
								$package->writeElement('VATCore:TotalVAT', sprintf('%.2f', $totalVat));
								$package->writeElement('VATCore:VATReclaimedOnInputs', sprintf('%.2f', $vatReclaimedInput));
								if ($netVat === null) {
									$netVat = abs($totalVat - $vatReclaimedInput);
								}
								if ($netVat < 0) {
								   return false;
								}
								$package->writeElement('VATCore:NetVAT', sprintf('%.2f', $netVat));
								$package->writeElement('VATCore:NetSalesAndOutputs', floor($netOutput));
								$package->writeElement('VATCore:NetPurchasesAndInputs', floor($netInput));
								$package->writeElement('VATCore:NetECSupplies', floor($netECSupply));
								$package->writeElement('VATCore:NetECAcquisitions', floor($netECAcq));
							$package->endElement(); # Body
						$package->endElement(); # VATDeclarationRequest

	 // Send the message and deal with the response...
						$this->setMessageBody($package);
						$this->addChannelRoute('http://blogs.fubra.com/php-govtalk/extensions/hmrc/vat/', 'php-govtalk HMRC VAT1 extension', '0.1.1');
						if ($this->sendMessage() && ($this->responseHasErrors() === false)) {
							$returnable = $this->getResponseEndpoint();
							$returnable['correlationid'] = $this->getResponseCorrelationId();
							return $returnable;
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
	 * Polls the Gateway for a submission response / error following a VAT
	 * declaration request. By default the correlation ID from the last response
	 * is used for the polling, but this can be over-ridden by supplying a
	 * correlation ID. The correlation ID can be skipped by passing a null value.
	 *
	 * If the resource is still pending this method will return the same array
	 * as declarationRequest() -- 'endpoint', 'interval' and 'correlationid' --
	 * if not then it'll return lots of useful information relating to the return
	 * and payment of any VAT due in the following array format:
	 *
	 *  message => an array of messages ('Thank you for your submission', etc.).
	 *  period => an array of information relating to the period of the return:
	 *    id => the period ID.
	 *    start => the start date of the period.
	 *    end => the end date of the period.
	 *  payment => an array of information relating to the payment of the return:
	 *    narrative => a string representation of the payment (generated by HMRC)
	 *    netvat => the net value due following this return.
	 *    payment => an array of information relating to the method of payment:
	 *      method => the method to be used to pay any money due, options are:
	 *        - nilpayment: no payment is due.
	 *        - repayment: a repayment from HMRC is due.
	 *        - directdebit: payment will be taken by previous direct debit.
	 *        - payment: payment should be made by alternative means.
	 *      additional => additional information relating to this payment.
	 *
	 * @param string $correlationId The correlation ID of the resource to poll. Can be skipped with a null value.
	 * @param string $pollUrl The URL of the Gateway to poll.
	 * @return mixed An array of details relating to the return and payment, or false on failure.
	 */
	public function declarationResponsePoll($correlationId = null, $pollUrl = null) {

		if ($correlationId === null) {
			$correlationId = $this->getResponseCorrelationId();
		}

		if ($this->setMessageCorrelationId($correlationId)) {
			if ($pollUrl !== null) {
				$this->setGovTalkServer($pollUrl);
			}
			$this->setMessageClass('HMCE-VATDEC-ORG-VAT100-STD');
			$this->setMessageQualifier('poll');
			$this->setMessageFunction('submit');
			$this->resetMessageKeys();
			$this->setMessageBody('');

			if ($this->sendMessage() && ($this->responseHasErrors() === false)) {

				$messageQualifier = (string) $this->_fullResponseObject->Header->MessageDetails->Qualifier;
				if ($messageQualifier == 'response') {

					$successResponse = $this->_fullResponseObject->Body->children('suc', true)->SuccessResponse;

					$responseMessage = array();
					foreach ($successResponse->Message AS $message) {
						$responseMessage[] = (string) $message;
					}

					$declarationResponse = $successResponse->ResponseData->children('ns', true)->VATDeclarationResponse;
					$declarationPeriod = array('id' => (string) $declarationResponse->Header->children('ns1', true)->VATPeriod->PeriodId,
					                           'start' => strtotime($declarationResponse->Header->children('ns1', true)->VATPeriod->PeriodStartDate),
					                           'end' => strtotime($declarationResponse->Header->children('ns1', true)->VATPeriod->PeriodEndDate));
					$paymentDueDate = strtotime($declarationResponse->Body->children('ns1', true)->PaymentDueDate);

					$paymentNotifcation = $declarationResponse->Body->children('ns1', true)->PaymentNotification;
					$paymentDetails = array('narrative' => (string) $paymentNotifcation->Narrative,
					                        'netvat' => (string) $paymentNotifcation->NetVAT);

					if (isset($paymentNotifcation->NilPaymentIndicator)) {
						$paymentDetails['payment'] = array('method' => 'nilpayment', 'additional' => null);
					} else if (isset($paymentNotifcation->RepaymentIndicator)) {
						$paymentDetails['payment'] = array('method' => 'repayment', 'additional' => null);
					} else if (isset($paymentNotifcation->DirectDebitPaymentStatus)) {
						$paymentDetails['payment'] = array('method' => 'directdebit', 'additional' => strtotime($paymentNotifcation->DirectDebitPaymentStatus->CollectionDate));
					} else if (isset($paymentNotifcation->PaymentRequest)) {
						$paymentDetails['payment'] = array('method' => 'payment', 'additional' => (string) $paymentNotifcation->PaymentRequest->DirectDebitInstructionStatus);
					}

					return array('success' => true,
					             'message' => $responseMessage,
					             'period' => $declarationPeriod,
					             'payment' => $paymentDetails);

				} else if ($messageQualifier == 'acknowledgement') {
					$returnable = $this->getResponseEndpoint();
					$returnable['correlationid'] = $this->getResponseCorrelationId();
					return $returnable;
				} else {
					return false;
				}
			} else {
				$errorArray = $this->getResponseErrors();
				if (count($errorArray['business']) > 0) {
					$returnArray = array();
					foreach ($this->_fullResponseObject->Body->children('err', true)->ErrorResponse->Error AS $errorResponse) {
						$returnArray[] = (string) $errorResponse->Text;
					}
					return array('error' => $returnArray);
				} else {
					return false;
				}
			}
		} else {
			return false;
		}

	}
	
 /* Protected methods. */

 /* Private methods. */

}