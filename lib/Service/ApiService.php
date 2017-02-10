<?php

/**
 * Zenodo - based on files_zenodo from Lars Naesbye Christensen
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Lars Naesbye Christensen, DeIC
 * @author Maxence Lange <maxence@pontapreta.net>
 * @copyright 2017
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\Zenodo\Service;

use \OCA\Zenodo\Model\iError;
use \OCA\Zenodo\Service\ConfigService;
use OCA\Zenodo\Service\MiscService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;

class ApiService {

	const ZENODO_DOMAIN_SANDBOX = 'https://sandbox.zenodo.org/';
	const ZENODO_DOMAIN_PRODUCTION = 'https://zenodo.org/';

	const ZENODO_API_DEPOSITIONS_CREATE = 'api/deposit/depositions?';
	const ZENODO_API_DEPOSITIONS_FILES_UPLOAD = 'api/deposit/depositions/%ID%/files?';

	private $configService;
	private $miscService;

	private $production = false;
	private $token = '';

	public function __construct(ConfigService $configService, MiscService $miscService) {
		$this->configService = $configService;
		$this->miscService = $miscService;
	}


	public function init($production, &$iError = null) {

		if ($iError === null) {
			$iError = new $iError();
		}

		$this->production = $production;
		$this->initToken();

		if ($this->token === '') {
			$iError->setCode(iError::TOKEN_MISSING)
				   ->setMessage(
					   'No token defined for this operation; please contact your Nextcloud administrator'
				   );

			return false;
		}

		return true;
	}

	public function configured() {
		if ($this->token === '') {
			return false;
		}

		return true;
	}

	private function initToken() {

		if ($this->production === true) {
			$this->miscService->log("==== PROD");
			$this->token =
				$this->configService->getAppValue(ConfigService::ZENODO_TOKEN_PRODUCTION);
		} else {
			$this->token = $this->configService->getAppValue(ConfigService::ZENODO_TOKEN_SANDBOX);
		}
	}


	private function generateUrl($path) {

		if (!$this->configured()) {
			return false;
		}

		if ($this->production === true) {
			$url = self::ZENODO_DOMAIN_PRODUCTION;
		} else {
			$url = self::ZENODO_DOMAIN_SANDBOX;
		}

		return sprintf("%s%saccess_token=%s", $url, $path, $this->token);
	}

	public function create_deposition($metadata, &$iError = null) {

		if ($iError === null) {
			$iError = new $iError();
		}

		if (!$this->configured()) {
			return false;
		}

		$url = $this->generateURl(self::ZENODO_API_DEPOSITIONS_CREATE);
		$json = json_encode($metadata);
		$result = self::curlIt($url, $json, $iError);

		$this->miscService->log("_METADATA: " . var_export($metadata, true));
		$this->miscService->log("_RESULT: " . var_export($result, true));

		if ($result->status === 200) {
			return true;
		}

		$iError->setCode($result->status);
		if (sizeof($result->errors) > 0) {
			foreach ($result->errors as $error) {
				$iError->setMessage($error->field . ' - ' . $error->message);
			}
		}

		return false;
	}

	public static function curlIt($url, $json) {

		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt(
			$curl, CURLOPT_HTTPHEADER,
			array("Content-type: application/json")
		);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $json);

		return json_decode(curl_exec($curl));
	}

}