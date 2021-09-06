<link rel="stylesheet" href="/public/css/home.css">

<div id="root" class="pt-2 pb-4 px-2 d-flex" data-simplebar>
	<div class="mx-4">
		<?= $searchResults ?>
	</div>
	<div id="albums" class="mx-auto centre">
		<?php foreach ($albums as $album): ?>
			<div class="album-container mx-2 my-2" data-album-id="<?= $album['id'] ?>" data-context-menu-actions="QUEUE,GO_TO_ALBUM">
				<img class="lazy album-art" data-src="<?= $album['artFilepath'] ?>">
				<div class="title">
					<?= $album['name'] ?>
				</div>
				<div class="artist mb-2">
					<?= $album['artist'] ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</div>

<script>
	(function() {
		$(function() {
			new LazyLoad({});
			updateBodyColour('#121212', false);
			auth();
			initEvents();
		});

		function initEvents() {
			$('.album-container').click(function() {
				PartialManager.sharedInstance.loadPartial(`/album/${$(this).data('album-id')}`)
			});
		}

		async function auth() {
			var res = await $.get('/auth');

			if (!res.success) {
				showToastNotification(res.success, res.message);
			}
		}
	})();
</script>