<?php

namespace App\Helpers;

use Exception;

class SpotifyApi {
	private const API_BASE = "https://api.spotify.com/v1";
	private $spDc;
	
	public function __construct() {
		$this->spDc = $_ENV['SPOTIFY_SP_DC'];
	}

	public function authTest() {
		$this->getAccessToken();
	}

	public function getSpotifyId($isrc) {
		$res = 	$this->request(
			"GET",
			self::API_BASE . "/search?" . http_build_query([
				'q' => "isrc:$isrc",
				'type' => 'track'
			])
		);

		return (!empty($res->tracks->items)) ? $res->tracks->items[0]->id : null;
	}

	public function saveTrack($trackId) {
		return $this->request(
			"PUT",
			self::API_BASE . "/me/tracks?" . http_build_query(['ids' => $trackId])
		);
	}

	private function request($requestType, $endpoint, $payload = "") {
		$headers = [
			'Accept: application/json',
			'Content-Type: application/json',
			"Content-Length:" . strlen($payload),
			"Authorization: Bearer " . $this->getAccessToken()
		];

		return json_decode(
			$this->curlRequest($requestType, $endpoint, $headers, $payload)['data']
		);
	}

	private function getAccessToken() {
		$res = $this->curlRequest(
			'GET',
			'https://open.spotify.com/get_access_token?reason=transport&productType=web_player',
			[
				"Cookie: sp_dc=$this->spDc",
				"user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.114 Safari/537.36"
			]
		);

		if ($res['httpCode'] !== 200) {
			throw new Exception("Spotify API: Unauthorised");
		}

		return json_decode($res['data'])->accessToken;
	}

	private function curlRequest($requestType, $url, $headers = [], $payload = "") {
		$curl = curl_init();
	
		curl_setopt_array($curl, [
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_CUSTOMREQUEST => $requestType,
			CURLOPT_HTTPHEADER => $headers
		]);
	
		if ($requestType === 'POST') {
			curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
		}
	
		$data = curl_exec($curl);
		$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	
		curl_close($curl);
	
		return compact('data', 'httpCode');
	}
}
