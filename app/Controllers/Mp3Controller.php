<?php

namespace App\Controllers;

use App\Helpers\DeezerApi;
use App\Helpers\DeezerPrivateApi;
use App\Helpers\MusicDatabase;
use Flight;

class Mp3Controller extends Controller {
	public function song($songId) {
		if (str_starts_with($songId, DeezerApi::DEEZER_ID_PREFIX)) {
			$songId = str_replace(DeezerApi::DEEZER_ID_PREFIX, "", $songId);
			$filepath = "public/userData/deezer/mp3/$songId";
			
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
}

?>