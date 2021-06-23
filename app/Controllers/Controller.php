<?php

namespace App\Controllers;

use App\Helpers\MusicDatabase;
use Flight;
use PDO;

class Controller {
	public function __construct() {
		$directories = [
			'userData',
			'userData/albumArt',
			'userData/deezer',
			'userData/deezer/mp3',
			'userData/deezer/metadata',
			'userData/deezer/album'
		];

		foreach ($directories as $directory) {
			if (!file_exists($directory)) {
				mkdir($directory);
			}
		}
	}

	protected function view($name, $data = []) {
		Flight::render('searchResults', [], 'searchResults');

		if (filter_var(Flight::request()->query->partial, FILTER_VALIDATE_BOOLEAN)) {
			Flight::render($name, $data);
			return;
		}
	
		$db = new MusicDatabase();
		$conn = $db->getConn();
		$stmt = $conn->prepare("SELECT `id` FROM `songs`");
		$stmt->execute();
		$songIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
		
		Flight::render($name, $data, 'partial');
		Flight::render('musicControl', compact('songIds'), 'musicControl');
		Flight::render('shell');
	}

	protected function request() {
		return Flight::request();
	}
}

?>