<?php require_once __DIR__ . '/includes/header.php'; ?>

<nav class="navbar navbar-expand-lg navbar-dark sticky-top" style="background: var(--dark);">
	<div class="container">
		<a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="index.php">
			<i class="bi bi-buildings-fill"></i>
			<span>PropSearch</span>
		</a>

		<button
			class="navbar-toggler"
			type="button"
			data-bs-toggle="collapse"
			data-bs-target="#mainNavbar"
			aria-controls="mainNavbar"
			aria-expanded="false"
			aria-label="Toggle navigation"
		>
			<span class="navbar-toggler-icon"></span>
		</button>

		<div class="collapse navbar-collapse" id="mainNavbar">
			<ul class="navbar-nav ms-auto mb-2 mb-lg-0">
				<li class="nav-item">
					<a class="nav-link text-white active d-flex align-items-center gap-1" aria-current="page" href="favorites.php">
						<i class="bi bi-heart-fill"></i>
						<span>Favorites</span>
						<span id="favorites-count" class="badge rounded-pill text-bg-light d-none"></span>
					</a>
				</li>
				<li class="nav-item">
					<a class="nav-link text-white-50" href="index.php">Search</a>
				</li>
			</ul>
		</div>
	</div>
</nav>

<section class="page-shell py-4">
	<div class="container">
		<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
			<h3 class="section-title mb-0">Your Favorite Properties</h3>
			<button id="clear-favorites-btn" type="button" class="btn btn-outline-custom">Clear Favorites</button>
		</div>

		<p class="text-muted mb-4">Saved homes appear here so you can compare and revisit quickly.</p>

		<div id="favorites-empty" class="favorite-empty d-none">
			<i class="bi bi-heart display-6 text-muted"></i>
			<h5 class="mt-3">No favorites yet</h5>
			<p class="text-muted mb-3">Like properties from search results to build your shortlist.</p>
			<a class="btn btn-primary-custom" href="index.php">Start Exploring</a>
		</div>

		<div id="favorites-grid" class="favorites-grid row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4"></div>
	</div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
