<?php

namespace App\Controllers;

use App\Helpers\MusicDatabase;
use App\Helpers\MusicManager;
use App\Helpers\DeezerApi;
use App\Helpers\DeezerPrivateApi;
use Flight;
use PDO;

class ApiController extends Controller {
	private const DEEZER_SEARCH_PREFIX = "e: ";

	public function update() {
		MusicManager::updateDatabase();
	}

	public function mp3($songId) {
		if (str_starts_with($songId, DeezerApi::DEEZER_ID_PREFIX)) {
			$songId = str_replace(DeezerApi::DEEZER_ID_PREFIX, "", $songId);
			$filepath = "userData/deezer/mp3/$songId";
			
			if (!file_exists($filepath)) {
				$deezerPrivateApi = new DeezerPrivateApi();
				$song = $deezerPrivateApi->getSong($songId);
				file_put_contents(
					$filepath,
					($song !== false) ? $song : ""
				);
			}
		} else {
			$db = new MusicDatabase();
			$conn = $db->getConn();

			$stmt = $conn->prepare("SELECT `filepath` FROM `songs` WHERE `id` = :id");
			$stmt->bindParam(":id", $songId);
			$stmt->execute();
			$filepath = $stmt->fetchColumn();
		}

		$filesize = filesize($filepath);
		$startOffset = 0;
		$endOffset = $filesize;
	
		if (isset($_SERVER['HTTP_RANGE'])) {
			list($startOffset, $endOffset) = explode(
				"-",
				str_replace("bytes=", "", $_SERVER['HTTP_RANGE'])
			);

			if (empty($endOffset)) {
				$endOffset = $filesize;
			}
		}
	
		Flight::response()
			->header('Accept-Ranges', 'bytes')
			->header('Content-Type', 'audio/mpeg')
			->header('Content-Range', "bytes $startOffset-" . ($endOffset - 1) . "/$filesize")
			->status(206)
			->write($this->getPartialMp3Data($filepath, $startOffset, $endOffset))
			->send()
		;
	}

	private function getPartialMp3Data($filepath, $startOffset, $endOffset) {	
		$file = fopen($filepath, 'r');
		fseek($file, $startOffset);
		$data = fread($file, $endOffset - $startOffset);
		fclose($file);

		return $data;
	}

	public function song($songId) {
		Flight::json(
			str_starts_with($songId, DeezerApi::DEEZER_ID_PREFIX) ? $this->getDeezerSong($songId) : $this->getLocalSong($songId)
		);
	}

	private function getDeezerSong($songId) {
		$songId = str_replace(DeezerApi::DEEZER_ID_PREFIX, "", $songId);
		$filepath = "userData/deezer/metadata/$songId";

		if (!file_exists($filepath)) {
			$deezerPrivateApi = new DeezerPrivateApi();
			$song = $deezerPrivateApi->getSongData($songId);
			file_put_contents($filepath, serialize($song));
		} else {
			$song = unserialize(file_get_contents($filepath));
		}

		return $song;
	}

	private function getLocalSong($songId) {
		$db = new MusicDatabase();
		$conn = $db->getConn();
		
		$stmt = $conn->prepare(
			"SELECT
				`songs`.`name` AS 'songName',
				`songs`.`artist` AS 'songArtist',
				`albums`.`artFilepath` AS 'albumArtUrl',
				`albums`.`name` AS 'albumName',
				`albums`.`id` AS 'albumId'
			FROM `songs`
			INNER JOIN `song-album` ON `songs`.`id` = `song-album`.`songId`
			INNER JOIN `albums` ON `song-album`.`albumId` = `albums`.`id`
			WHERE `songs`.`id` = :id"
		);
		$stmt->bindParam(":id", $songId);
		$stmt->execute();
		$song = $stmt->fetch();

		return $song;
	}

	public function album($albumId) {	
		Flight::json(
			str_starts_with($albumId, DeezerApi::DEEZER_ID_PREFIX) ? $this->getDeezerAlbum($albumId) : $this->getLocalAlbum($albumId)
		);
	}

	private function getDeezerAlbum($albumId) {
		$albumId = str_replace(DeezerApi::DEEZER_ID_PREFIX, "", $albumId);
		$filepath = "userData/deezer/album/$albumId";

		if (!file_exists($filepath)) {
			$deezerApi = new DeezerApi();
			$res = $deezerApi->getAlbum($albumId);
			file_put_contents($filepath, serialize($res));
		} else {
			$res = unserialize(file_get_contents($filepath));
		}

		return ['songIds' => array_column($res['songs'], "id")];
	}

	private function getLocalAlbum($albumId) {
		$db = new MusicDatabase();
		$conn = $db->getConn();
		$stmt = $conn->prepare(
			"SELECT `songs`.`id`
			FROM `songs`
			INNER JOIN `song-album` ON `songs`.`id` = `song-album`.`songId`
			WHERE `song-album`.`albumId` = :albumId
			ORDER BY `songs`.`discNumber`, `songs`.`trackNumber`"
		);
		$stmt->bindParam(":albumId", $albumId);
		$stmt->execute();
		$songIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

		return ['songIds' => $songIds];
	}

	public function search() {
		$term = Flight::request()->query->term;
	
		Flight::json(
			str_starts_with($term, self::DEEZER_SEARCH_PREFIX) ? $this->searchDeezer($term) : $this->searchLocal($term)
		);
	}

	private function searchDeezer($term) {	
		$deezerApi = new DeezerApi();
		$res = $deezerApi->search(
			substr($term, strlen(self::DEEZER_SEARCH_PREFIX))
		);

		return $res;
	}

	private function searchLocal($term) {
		$term = str_replace(" ", "%", $term);
		$term = "%$term%";
	
		$db = new MusicDatabase();
		$conn = $db->getConn();

		$stmt = $conn->prepare(
			"SELECT `id`, `name`, `artist`, `duration`, `albumDetails`.`duration`, `artFilepath`
			FROM `albums`
			INNER JOIN `albumDetails` ON `albums`.`id` = `albumDetails`.`albumId`
			WHERE CONCAT(`name`, `artist`) LIKE :term
			OR CONCAT(`artist`, `name`) LIKE :term
			LIMIT 5"
		);
		$stmt->bindParam(":term", $term);
		$stmt->execute();
		$albums = $stmt->fetchAll();
	
		$stmt = $conn->prepare(
			"SELECT `songs`.`id`, `songs`.`name`, `songs`.`artist`, `songs`.`duration`, `albums`.`artFilepath`, `albums`.`id` AS `albumId`
			FROM `songs`
			INNER JOIN `song-album` ON `songs`.`id` = `song-album`.`songId`
			INNER JOIN `albums` ON `song-album`.`albumId` = `albums`.`id`
			WHERE CONCAT(`songs`.`name`, `songs`.`artist`) LIKE :term
			OR CONCAT(`songs`.`artist`, `songs`.`name`) LIKE :term
			LIMIT 5"
		);
		$stmt->bindParam(":term", $term);
		$stmt->execute();
		$songs = $stmt->fetchAll();

		return compact('albums', 'songs');
	}
}

?>