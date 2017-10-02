<?php

#
#  HMRC.php
#
#  Created by Jonathon Wardman on 18-01-2012.
#  Copyright 2012, Fubra Limited. All rights reserved.
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
 * HMRC API client. Extends the functionality provided by the GovTalk class to
 * help build and parse HMRC submissions. It is designed to be further extended
 * to offer service specific functionaility. The php-govtalk base class needs
 * including externally (or automatically) in order to use this extention.
 *
 * @author Jonathon Wardman
 * @copyright 2012, Fubra Limited
 * @licence http://www.gnu.org/licenses/gpl-3.0.txt GNU General Public License
 */
class Hmrc extends GovTalk {

 /* General IRenvelope related variables. */

	/**
	 * Flag indicating if the IRmark should be generated for outgoing XML.
	 *
	 * @var boolean
	 */
	private $_generateIRmark = true;

	/**
	 * Details of the agent sending the return declaration.
	 *
	 * @var array
	 */
	private $_agentDetails = array();

	/**
	 * Flag indicating if the delete requests should be made to the gateway on
	 * sucessful reciept of declaration response.
	 *
	 * @var boolean
	 */
	protected $_tidyGateway = false;

 /* Magic methods. */

	/**
	 * Instance constructor. Simply passes the arguments on to the main GovTalk
	 * GovTalk constructor. Anything clever should be done in at the level above
	 * this.
	 *
	 * @param string $govTalkServer GovTalk server URL.
	 * @param string $govTalkSenderId GovTalk sender ID.
	 * @param string $govTalkPassword GovTalk password.
	 */
	public function __construct($govTalkServer, $govTalkSenderId, $govTalkPassword) {

		parent::__construct($govTalkServer, $govTalkSenderId, $govTalkPassword);

	}

 /* Public methods. */

	/**
	 * Turns the IRmark generator on or off (by default the IRmark generator is
	 * turned off). When it's switched off no IRmark element will be sent with
	 * requests to HMRC.
	 *
	 * @param boolean $flag True to turn on IRmark generator, false to turn it off.
	 * @return boolean True on success, false on failure.
	 */
	public function setIRmarkGeneration($flag) {

		if (is_bool($flag)) {
			$this->_generateIRmark = $flag;
			return true;
		} else {
			return false;
		}

	}

	/**
	 * Turns the gateway tidying on or off (by default the gateway will not be
	 * tidied). It's polite to tidy the gateway up when running live services,
	 * but useful to leave responses on the server when developing.
	 *
	 * @param boolean $flag True to turn on IRmark generator, false to turn it off.
	 * @return boolean True on success, false on failure.
	 */
	public function setGatewayTidy($flag) {

		if (is_bool($flag)) {
			$this->_tidyGateway = $flag;
			return true;
		} else {
			return false;
		}

	}

	/**
	 * Sets details about the agent submitting the declaration.
	 *
	 * The agent company's address should be specified in the following format:
	 *   line => Array, each element containing a single line information.
	 *   postcode => The agent company's postcode.
	 *   country => The agent company's country. Defaults to England.
	 *
	 * The agent company's primary contact should be specified as follows:
	 *   name => Array, format as follows:
	 *     title => Contact's title (Mr, Mrs, etc.)
	 *     forename => Contact's forename.
	 *     surname => Contact's surname.
	 *   email => Contact's email address (optional).
	 *   telephone => Contact's telephone number (optional).
	 *   fax => Contact's fax number (optional).
	 *
	 * @param string $company The agent company's name.
	 * @param array $address The agent company's address in the format specified above.
	 * @param array $contact The agent company's key contact in the format specified above (optional, may be skipped with a null value).
	 * @param string $reference An identifier for the agent's own reference (optional).
	 */
	public function setAgentDetails($company, array $address, array $contact = null, $reference = null) {

		if (preg_match('/[A-Za-z0-9 &\'\(\)\*,\-\.\/]*/', $company)) {
			$this->_agentDetails['company'] = $company;
			$this->_agentDetails['address'] = $address;
			if (!isset($this->_agentDetails['address']['country'])) {
				$this->_agentDetails['address']['country'] = 'England';
			}
			if ($contact !== null) {
				$this->_agentDetails['contact'] = $contact;
			}
			if (($reference !== null) && preg_match('/[A-Za-z0-9 &\'\(\)\*,\-\.\/]*/', $reference)) {
				$this->_agentDetails['reference'] = $reference;
			}
		} else {
			return false;
		}

	}

 /* Protected methods. */

	/**
	 * Adds the Agent header elements, if required, to the given XMLWriter
	 * object. XMLWriter is handled as a reference and is added to in-situ,
	 * therefore this method returns null.
	 *
	 * @param XMLWriter $package The package to add the Agent headers to.
	 * @return null.
	 */
	protected function generateIrAgentHeader(&$package) {

		if (is_a($package, 'XMLWriter')) {
			if (count($this->_agentDetails) > 0) {
				$package->startElement('Agent');
					if (isset($this->_agentDetails['reference'])) {
						$package->writeElement('AgentID', $this->_agentDetails['reference']);
					}
					$package->writeElement('Company', $this->_agentDetails['company']);
					$package->startElement('Address');
						foreach ($this->_agentDetails['address']['line'] AS $line) {
							$package->writeElement('Line', $line);
						}
						$package->writeElement('PostCode', $this->_agentDetails['address']['postcode']);
						$package->writeElement('Country', $this->_agentDetails['address']['country']);
					$package->endElement(); # Address
					if (isset($this->_agentDetails['contact'])) {
						$package->startElement('Contact');
							$package->startElement('Name');
								$package->writeElement('Ttl', $this->_agentDetails['contact']['name']['title']);
								$package->writeElement('Fore', $this->_agentDetails['contact']['name']['forename']);
								$package->writeElement('Sur', $this->_agentDetails['contact']['name']['surname']);
							$package->endElement(); # Name
							if (isset($this->_agentDetails['contact']['email'])) {
								$package->writeElement('Email', $this->_agentDetails['contact']['email']);
							}
							if (isset($this->_agentDetails['contact']['telephone'])) {
								$package->writeElement('Telephone', $this->_agentDetails['contact']['telephone']);
							}
							if (isset($this->_agentDetails['contact']['fax'])) {
								$package->writeElement('Fax', $this->_agentDetails['contact']['fax']);
							}
						$package->endElement(); # Contact
					}
				$package->endElement(); # Agent
			}
		}

	}

	/**
	 * Adds the IRmark header elements, if required, to the given XMLWriter
	 * object. XMLWriter is handled as a reference and is added to in-situ,
	 * therefore this method returns null.
	 *
	 * @param XMLWriter $package The package to add the IRmark to.
	 * @return null.
	 */
	protected function generateIrmarkHeader(&$package) {

		if (is_a($package, 'XMLWriter')) {
			if ($this->_generateIRmark === true) {
				$package->startElement('IRmark');
					$package->writeAttribute('Type', 'generic');
					$package->text('IRmark+Token');
				$package->endElement(); # IRmark
			}
		}

	}


	/**
	 * Adds a valid IRmark to the given package.
	 *
	 * This function over-rides the packageDigest() function provided in the main
	 * php-govtalk class.
	 *
	 * @param string $package The package to add the IRmark to.
	 * @return string The new package after addition of the IRmark.
	 */
	protected function packageDigest($package) {

		if ($this->_generateIRmark === true) {
			$packageSimpleXML = simplexml_load_string($package);
			$packageNamespaces = $packageSimpleXML->getNamespaces();

			preg_match('/<Body>(.*?)<\/Body>/', str_replace("\n", '¬', $package), $matches);
			$packageBody = str_replace('¬', "\n", $matches[1]);

			$irMark = base64_encode($this->_generateIRMark($packageBody, $packageNamespaces));
			$package = str_replace('IRmark+Token', $irMark, $package);
		}

		return $package;

	}

 /* Private methods. */

	/**
	 * Generates an IRmark hash from the given XML string for use in the IRmark
	 * node inside the message body.  The string passed must contain one IRmark
	 * element containing the string IRmark (ie. <IRmark>IRmark</IRmark>) or the
	 * function will fail.
	 *
	 * @param $xmlString string The XML to generate the IRmark hash from.
	 * @return string The IRmark hash.
	 */
	private function _generateIRMark($xmlString, $namespaces = null) {

		if (is_string($xmlString)) {
			$xmlString = preg_replace('/<(vat:)?IRmark Type="generic">[A-Za-z0-9\/\+=]*<\/(vat:)?IRmark>/', '', $xmlString, -1, $matchCount);
			if ($matchCount == 1) {
				$xmlDom = new DOMDocument;

				if ($namespaces !== null && is_array($namespaces)) {
					$namespaceString = array();
					foreach ($namespaces AS $key => $value) {
						if ($key !== '') {
							$namespaceString[] = 'xmlns:'.$key.'="'.$value.'"';
						} else {
							$namespaceString[] = 'xmlns="'.$value.'"';
						}
					}
					$bodyCompiled = '<Body '.implode(' ', $namespaceString).'>'.$xmlString.'</Body>';
				} else {
					$bodyCompiled = '<Body>'.$xmlString.'</Body>';
				}
				$xmlDom->loadXML($bodyCompiled);

				return sha1($xmlDom->documentElement->C14N(), true);

			} else {
				return false;
			}
		} else {
			return false;
		}

	}

}