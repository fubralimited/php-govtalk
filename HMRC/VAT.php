<?php

#
#  VAT.php
#
#  Created by Jonathon Wardman on 20-07-2009.
#  Copyright 2009 - 2012, Fubra Limited. All rights reserved.
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
 * GovTalk and HMRC classes to build and parse HMRC VAT submissions.  This class
 * only supports V2 of the HMRC VAT internet filing system.  Both the Hmrc and
 * php-govtalk base classes need including externally (or automatically) in
 * order to use this extention.
 *
 * @author Jonathon Wardman
 * @copyright 2012, Fubra Limited
 * @licence http://www.gnu.org/licenses/gpl-3.0.txt GNU General Public License
 */
class HmrcVat extends Hmrc {

 /* Extension specific information. */
	private $_extensionDetails = array('name' => 'php-govtalk HMRC VAT extension',
	                                   'version' => '0.2.0',
	                                   'url' => 'http://blogs.fubra.com/php-govtalk/extensions/hmrc/vat/');

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
		
		$this->setSchemaLocation('http://www.govtalk.gov.uk/taxation/vat/vatdeclaration/2/VATDeclarationRequest-v2-1.xsd', false);
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
	 * @param string $senderCapacity The capacity this return is being submitted under (Agent, Trust, Company, etc.).
	 * @param float $vatOutput VAT due on outputs (box 1).
	 * @param float $vatECAcq VAT due on EC acquisitions (box 2).
	 * @param float $vatReclaimedInput VAT reclaimed on inputs (box 4).
	 * @param float $netOutput Net sales and outputs (box 6).
	 * @param float $netInput Net purchases and inputs (box 7).
	 * @param float $netECSupply Net EC supplies (box 8).
	 * @param float $netECAcq Net EC acquisitions (box 9).
	 * @param float $totalVat Total VAT (box 3). If this value is not specified then it will be calculated as box 1 + box 2. May be skipped by passing null.
	 * @param float $netVat Net VAT (box 5). If this value is not specified then it will be calculated as the absolute difference between box 5 and box 4. May be skipped by passing null.
	 * @param boolean $finalReturn Flag indicating if this return is a final VAT return.
	 * @return mixed An array of 'endpoint', 'interval' and 'correlationid' on success, or false on failure.
	 */
	public function declarationRequest($vatNumber, $returnPeriod, $senderCapacity, $vatOutput, $vatECAcq, $vatReclaimedInput, $netOutput, $netInput, $netECSupply, $netECAcq, $totalVat = null, $netVat = null, $finalReturn = false) {
	
		$vatNumber = trim(str_replace(' ', '', $vatNumber));
		if (preg_match('/^(GB)?(\d{9,12})$/', $vatNumber)) { # VAT number
			$this->addMessageKey('VATRegNo', $vatNumber);
			if (preg_match('/^\d{4}-\d{2}$/', $returnPeriod)) { # VAT period
				$validCapacities = array('Individual', 'Company', 'Agent',
				                         'Bureau', 'Partnership', 'Trust',
				                         'Employer', 'Government', 'Acting in Capacity',
				                         'Other');
				if (in_array($senderCapacity, $validCapacities)) {
					if (is_numeric($vatOutput) && is_numeric($vatECAcq) && is_numeric($vatReclaimedInput) && is_numeric($netOutput) && is_numeric($netInput) && is_numeric($netECSupply) && is_numeric($netECAcq)) {
				
	 // Set the message envelope bits and pieces for this request...
						$this->setMessageClass('HMRC-VAT-DEC');
						$this->setMessageQualifier('request');
						$this->setMessageFunction('submit');
				
	 // Build message body...
						$package = new XMLWriter();
						$package->openMemory();
						$package->setIndent(true);
						$package->startElement('IRenvelope');
							$package->writeAttribute('xmlns', 'http://www.govtalk.gov.uk/taxation/vat/vatdeclaration/2');

	 // IRheader...
							$package->startElement('IRheader');
								$package->startElement('Keys');
									$package->startElement('Key');
										$package->writeAttribute('Type', 'VATRegNo');
										$package->text($vatNumber);
									$package->endElement(); # Key
								$package->endElement(); # Keys
								$package->writeElement('PeriodID', $returnPeriod);
								$this->generateIrAgentHeader($package); # Agent element for IRheader
								$package->writeElement('DefaultCurrency', 'GBP');
								$this->generateIrmarkHeader($package); # IRMark header for IRheader
								$package->writeElement('Sender', $senderCapacity);
							$package->endElement(); # IRheader

	 // VATDeclarationRequest...
							$package->startElement('VATDeclarationRequest');
								if ($finalReturn === true) {
									$package->writeAttribute('finalReturn', 'yes');
								}
								$package->writeElement('VATDueOnOutputs', sprintf('%.2f', $vatOutput));
								$package->writeElement('VATDueOnECAcquisitions', sprintf('%.2f', $vatECAcq));
								if ($totalVat === null) {
									$totalVat = $vatOutput + $vatECAcq;
								}
								$package->writeElement('TotalVAT', sprintf('%.2f', $totalVat));
								$package->writeElement('VATReclaimedOnInputs', sprintf('%.2f', $vatReclaimedInput));
								if ($netVat === null) {
									$netVat = abs($totalVat - $vatReclaimedInput);
								}
								if ($netVat < 0) {
								   return false;
								}
								$package->writeElement('NetVAT', sprintf('%.2f', $netVat));
								$package->writeElement('NetSalesAndOutputs', floor($netOutput));
								$package->writeElement('NetPurchasesAndInputs', floor($netInput));
								$package->writeElement('NetECSupplies', floor($netECSupply));
								$package->writeElement('NetECAcquisitions', floor($netECAcq));
							$package->endElement(); # VATDeclarationRequest

						$package->endElement(); # IRenvelope
						
	 // Send the message and deal with the response...
						$this->setMessageBody($package);
						$this->addChannelRoute($this->_extensionDetails['url'], $this->_extensionDetails['name'], $this->_extensionDetails['version']);
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
	 *  accept_time => the time the submission was accepted by the HMRC server.
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
			$this->setMessageClass('HMRC-VAT-DEC');
			$this->setMessageQualifier('poll');
			$this->setMessageFunction('submit');
			$this->resetMessageKeys();
			$this->setMessageBody('');
			if ($this->sendMessage() && ($this->responseHasErrors() === false)) {
			
				$messageQualifier = (string) $this->_fullResponseObject->Header->MessageDetails->Qualifier;
				if ($messageQualifier == 'response') {
				
					$successResponse = $this->_fullResponseObject->Body->SuccessResponse;
					
					if (isset($successResponse->IRmarkReceipt)) {
						$irMarkReceipt = (string) $successResponse->IRmarkReceipt->Message;
					} else {
						$irMarkReceipt = null;
					}
					
					$responseMessage = array();
					foreach ($successResponse->Message AS $message) {
						$responseMessage[] = (string) $message;
					}
					$responseAcceptedTime = strtotime($successResponse->AcceptedTime);
					
					$declarationResponse = $successResponse->ResponseData->VATDeclarationResponse;
					$declarationPeriod = array('id' => (string) $declarationResponse->Header->VATPeriod->PeriodId,
					                           'start' => strtotime($declarationResponse->Header->VATPeriod->PeriodStartDate),
					                           'end' => strtotime($declarationResponse->Header->VATPeriod->PeriodEndDate));

					$paymentDueDate = strtotime($declarationResponse->Body->PaymentDueDate);

					$paymentNotifcation = $declarationResponse->Body->PaymentNotification;
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
					
					if ($this->_tidyGateway === true) {
						$this->sendDeleteRequest();
					}
					
					return array('message' => $responseMessage,
					             'irmark' => $irMarkReceipt,
					             'accept_time' => $responseAcceptedTime,
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
				return false;
			}
		} else {
			return false;
		}
	
	}
	
 /* Protected methods. */

 /* Private methods. */
 

}