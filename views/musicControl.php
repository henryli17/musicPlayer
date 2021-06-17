<link rel="stylesheet" href="/assets/css/musicControl.css">

<div id="musicControl" class="fixed-bottom d-flex">
	<div id="playerDetails" class="h-100 d-flex">
		<img id="albumArt" class="my-auto"></img>
		<div class="my-auto w-75">
			<div id="songName"></div>
			<div id="artistName"></div>
		</div>
	</div>
	<div id="playerControls" class="h-100">
		<div class="h-75 d-flex">
			<div class="d-flex mx-auto">
				<button id="prevButton" class="my-auto mx-2">
					<svg class="previous" height="16" width="16">
						<path></path>
					</svg>
				</button>
				<button id="playButton" class="my-auto mx-2 paused">
					<svg class="play" height="16" width="16">
						<path></path>
					</svg>
				</button>
				<button id="skipButton" class="my-auto mx-2">
					<svg class="skip" height="16" width="16">
						<path></path>
					</svg>
				</button>
			</div>
		</div>
		<div class="h-25 d-flex mx-auto">
			<div class="mb-auto d-flex">
				<div id="elapsedTime" class="time">0:00</div>
				<div id="progressSlider"></div>
				<div id="endTime" class="time">0:00</div>
			</div>
		</div>
	</div>
	<div class="h-100 d-flex ml-auto">
		<svg id="followAlbumButton" class="my-auto mx-2" height="16" width="16">
			<path></path>
			<path></path>
		</svg>
		<svg id="volumeButton" class="my-auto mx-2 mute" height="16" width="16">
			<path></path>
		</svg>
		<div class="d-flex h-100">
			<div id="volumeSlider" class="my-auto"></div>
		</div>
	</div>
</div>

<script>
	(function() {
		var $songName = $('#songName');
		var $prevButton = $('#prevButton');
		var $playButton = $('#playButton');
		var $skipButton = $('#skipButton');
		var $followAlbumButton = $('#followAlbumButton');
		var $volumeSlider = $('#volumeSlider');
		var $volumeButton = $('#volumeButton');
		var $musicControl = $('#musicControl');
		var $progressSlider = $('#progressSlider');
		var $elapsedTime = $('#elapsedTime');
		var $endTime = $('#endTime');
		
		musicControl = new MusicControl($musicControl);

		initStateInterval();
		initSliders();
		initEvents();

		function initStateInterval() {
			setInterval(() => {
				if (!musicControl.loaded() || musicControl.disabled()) {
					return;
				}

				localStorage.setItem(
					"state",
					JSON.stringify({
						volume: musicControl.volume(),
						queue: musicControl.queue(),
						nextUp: musicControl.nextUp(),
						seek: musicControl.seek(),
						songId: musicControl.songId()
					})
				);
			}, 1000);
		}

		function initSliders() {
			const PROGRESS_INTERVAL_TIMEOUT = 100;

			var updateVolume = function(e, ui) {
				var volume = Math.pow(ui.value / 100, 4);

				$volumeButton.removeClass('mute low-volume medium-volume high-volume');
			
				if (ui.value === 0) {
					$volumeButton.addClass('mute');
				} else if (ui.value <= 33) {
					$volumeButton.addClass('low-volume');
				} else if (ui.value <= 66) {
					$volumeButton.addClass('medium-volume');
				} else {
					$volumeButton.addClass('high-volume');
				}

				musicControl.volume(volume);
			}

			var progressIntervalCallback = function() {
				if (!musicControl.loaded() || musicControl.disabled()) {
					return;
				}

				var duration = musicControl.duration();
				var progress = musicControl.seek() / duration;
				var elapsedSeconds = progress * duration;

				$elapsedTime.text(secondsToTimeString(elapsedSeconds));
				$progressSlider.slider("value", progress * 100);
			};

			var progressInterval = setInterval(progressIntervalCallback, PROGRESS_INTERVAL_TIMEOUT);

			initSlider($volumeSlider, Math.pow(musicControl.volume(), 1/4) * 100, { change: updateVolume, slide: updateVolume });
			initSlider(
				$progressSlider,
				0,
				{
					slide: (e, ui) => $elapsedTime.text(secondsToTimeString(ui.value / 100 * musicControl.duration())),
					start: e => clearInterval(progressInterval),
					stop: (e, ui) => {
						musicControl.seek(ui.value / 100 * musicControl.duration());
						progressInterval = setInterval(progressIntervalCallback, PROGRESS_INTERVAL_TIMEOUT);
					}
				},
				musicControl.disabled()
			);
		}

		function initEvents() {
			$songName.click(e => partialManager.loadPartial(`/album/${$songName.data('albumId')}`));
			$prevButton.click(e => musicControl.previous());
			$playButton.click(e => musicControl.togglePlay());
			$skipButton.click(e => musicControl.skip());
			$followAlbumButton.click(e => $followAlbumButton.toggleClass('active'));
			$volumeButton.click(e => updateVolumeButton());
			$volumeSlider.parent().on('mousewheel', e => adjustVolume(e));
			$(window).keydown(e => assignKeydown(e));
			$(window).keyup(e => assignKeyup(e));
		}

		function adjustVolume(e) {
			var volume = $volumeSlider.slider("value");

			$volumeSlider.slider(
				"value",
				(e.originalEvent.wheelDelta > 0) ? volume + 10 : volume - 10
			);
		}

		function updateVolumeButton() {
			var volume = $volumeSlider.slider("value");

			if (volume !== 0) {
				$volumeSlider.data("volume", volume);
				$volumeSlider.slider("value", 0);
			} else {
				$volumeSlider.slider("value", $volumeSlider.data("volume") || 10);		
			}
		}

		function assignKeydown(e) {
			// Ctrl + S
			if (e.ctrlKey && e.keyCode === 83) {
				e.preventDefault();
			}

			// Esc
			if (e.keyCode === 27) {
				e.preventDefault();
			}

			// Space
			if (e.keyCode === 32) {
				if ($(':focus').length === 0) {
					e.preventDefault();
					musicControl.togglePlay();
				}
			}
		}

		function assignKeyup(e) {
			// Ctrl + S
			if (e.ctrlKey && e.keyCode === 83) {
				e.preventDefault();

				musicControl.playNextUp({
					list: shuffle([<?= implode(", ", $songIds) ?>]),
					i: 0
				});
			}

			// Esc
			if (e.keyCode === 27) {
				e.preventDefault();
				partialManager.loadPartial('/');
			}
		}
	})();
</script>