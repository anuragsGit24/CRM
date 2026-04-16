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
					<a class="nav-link text-white-50" href="index.php">Search</a>
				</li>
				<li class="nav-item">
					<a class="nav-link text-white-50 d-flex align-items-center gap-1" href="favorites.php">
						<i class="bi bi-heart"></i>
						<span>Favorites</span>
						<span id="favorites-count" class="badge rounded-pill text-bg-light d-none"></span>
					</a>
				</li>
			</ul>
		</div>
	</div>
</nav>

<section class="page-shell py-4">
	<div class="container">
		<div class="mb-3">
			<a href="index.php" class="text-decoration-none text-muted"><i class="bi bi-arrow-left"></i> Back to Search</a>
		</div>

		<div id="property-loading" class="detail-panel">
			<div class="spinner-border text-danger me-2" role="status" aria-hidden="true"></div>
			<span>Loading property details...</span>
		</div>

		<div id="property-error" class="alert alert-danger d-none" role="alert">
			Could not load property details. Please try again.
		</div>

		<div id="property-content" class="d-none">
			<div class="detail-hero mb-4">
				<img id="detail-image" class="detail-hero-image" src="" alt="Property image" loading="lazy">
			</div>

			<div class="row g-4">
				<div class="col-lg-8">
					<div class="detail-panel h-100">
						<div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
							<div>
								<h2 id="detail-name" class="section-title mb-1"></h2>
								<p id="detail-meta" class="text-muted mb-0"></p>
							</div>
							<div class="d-flex gap-2">
								<button id="detail-favorite-btn" type="button" class="btn btn-outline-custom">
									<i class="bi bi-heart me-1"></i>Save
								</button>
							</div>
						</div>

						<hr>

						<div class="row g-3">
							<div class="col-sm-6">
								<div class="detail-panel h-100">
									<h6 class="mb-2">Pricing</h6>
									<div class="price-main" id="detail-price-main"></div>
									<div class="price-inclusive" id="detail-price-inclusive"></div>
								</div>
							</div>
							<div class="col-sm-6">
								<div class="detail-panel h-100">
									<h6 class="mb-2">Possession & Status</h6>
									<p class="mb-1" id="detail-status"></p>
									<p class="mb-0 text-muted" id="detail-possession"></p>
								</div>
							</div>
						</div>

						<div class="detail-panel mt-3">
							<h6 class="mb-3">Flat Configurations</h6>
							<div class="table-responsive">
								<table class="table table-sm align-middle mb-0">
									<thead>
										<tr>
											<th>Type</th>
											<th>Base Price</th>
											<th>Total Charge</th>
											<th>Carpet Area</th>
											<th>Built-up Area</th>
											<th>Bathrooms</th>
											<th>Transaction</th>
										</tr>
									</thead>
									<tbody id="detail-flat-list"></tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
				<div class="col-lg-4">
					<div class="detail-panel">
						<h6 class="mb-3">Quick Facts</h6>
						<div class="kv-row"><span class="text-muted">Builder</span><strong id="detail-builder"></strong></div>
						<div class="kv-row"><span class="text-muted">Location</span><strong id="detail-location"></strong></div>
						<div class="kv-row"><span class="text-muted">RERA No.</span><strong id="detail-rera"></strong></div>
						<div class="kv-row"><span class="text-muted">Segment</span><strong id="detail-segment"></strong></div>
						<div class="kv-row"><span class="text-muted">Rank</span><strong id="detail-rank"></strong></div>
						<div class="kv-row"><span class="text-muted">Coordinates</span><strong id="detail-coords"></strong></div>
					</div>
				</div>
			</div>
		</div>
	</div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
