<div id="musicControl" class="fixed-bottom">
	<div id="songName"></div>
	<div id="playButton"></div>
</div>

<script>
	var musicPlayer = new MusicPlayer($('#musicControl'));

	$('#playButton').click(function() {
		musicPlayer.togglePlay();
	});

</script>