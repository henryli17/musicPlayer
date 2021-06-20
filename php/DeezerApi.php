<?php

class DeezerApi {
	private const API_BASE = "https://api.deezer.com";

	public function search($term, $limit = 5) {
		$songs = [];
		$albums = [];

		if (empty($term)) {
			return compact('songs', 'albums');
		}

		$res = json_decode(
			$this->curlRequest(
				"GET",
				self::API_BASE . "/search&" . http_build_query(['q' => $term])
			)
		);

		foreach ($res->data as $song) {
			if (count($songs) < $limit) {
				$songs[] = [
					"id" => "DEEZER-$song->id",
					"name" => $song->title,
					"artist" => $song->artist->name,
					"duration" => $song->duration,
					"artFilepath" => $song->album->cover
				];
			}

			if (count($albums) < $limit && !array_key_exists($song->album->id, $albums)) {
				$albums[$song->album->id] = [
					"id" => "DEEZER-" . $song->album->id,
					"name" => $song->album->title,
					"artist" => $song->artist->name,
					"duration" => $this->getAlbum($song->album->id)->duration,
					"artFilepath" => $song->album->cover
				];
			}
		}

		$albums = array_values($albums);

		return compact('songs', 'albums');
	}

	public function getAlbum($id) {
		$res = json_decode(
			$this->curlRequest(
				"GET",
				self::API_BASE . "/album/$id"
			)
		);

		return $res;
	}

	private function curlRequest($requestType, $url, $headers = [], $payload = "") {
		$curl = curl_init();
	
		curl_setopt_array($curl, [
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_CUSTOMREQUEST => $requestType,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
		]);
	
		if ($requestType === 'POST') {
			curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
		}
	
		$res = curl_exec($curl);
	
		curl_close($curl);
	
		return $res;
	}
}

?>