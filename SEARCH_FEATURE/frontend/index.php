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
					<a class="nav-link text-white-50 d-flex align-items-center gap-1" href="favorites.php">
						<i class="bi bi-heart"></i>
						<span>Favorites</span>
						<span id="favorites-count" class="badge rounded-pill text-bg-light d-none"></span>
					</a>
				</li>
				<li class="nav-item">
					<a class="nav-link text-white-50" href="#">Developers</a>
				</li>
				<li class="nav-item">
					<a class="nav-link text-white-50" href="#">Contact</a>
				</li>
			</ul>
		</div>
	</div>
</nav>

<section class="hero-section">
	<div class="container text-center">
		<p class="mb-2 fw-semibold text-uppercase" style="letter-spacing: 0.14em; color: var(--primary); font-size: 12px;">
			MUMBAI'S SMARTEST PROPERTY SEARCH
		</p>

		<h1 class="fw-bold mb-2" style="font-size: clamp(28px, 5vw, 42px);">
			Find Your Perfect Home
		</h1>

		<p class="mb-0 mx-auto" style="max-width: 680px; color: rgba(255, 255, 255, 0.7); font-size: 18px;">
			Search naturally - just type what you're looking for
		</p>

		<div class="search-mode-switch mt-4 mx-auto" style="max-width: 480px;" role="tablist" aria-label="Search mode">
			<button
				id="search-mode-projects"
				class="search-mode-btn active"
				type="button"
				data-search-mode="projects"
				role="tab"
				aria-selected="true"
			>
				Normal Search
			</button>
			<button
				id="search-mode-posts"
				class="search-mode-btn"
				type="button"
				data-search-mode="posts"
				role="tab"
				aria-selected="false"
			>
				Posting Search
			</button>
		</div>
		<p id="search-mode-hint" class="mt-2 mb-0 text-white-50 small">Normal Search: projects and flats</p>

		<div class="mt-4 mx-auto position-relative" style="max-width: 680px;">
			<div id="search-wrapper" class="search-bar-wrapper mx-auto">
				<div class="input-group">
					<span class="input-group-text bg-white border-0">
						<i class="bi bi-search" style="color: var(--text-muted);"></i>
					</span>
					<input
						type="text"
						id="search-input"
						class="form-control border-0 shadow-none"
						placeholder="Try '2 BHK in Powai under 1.5 Cr' or '1 BHK vikhroli rent'"
						aria-label="Search properties"
					>
					<button id="search-btn" class="btn btn-primary-custom px-4" type="button">Search</button>
				</div>

				<div id="suggestion-dropdown" class="suggestion-dropdown d-none">
					<ul id="suggestion-list" class="list-unstyled mb-0"></ul>
				</div>
			</div>
		</div>

		<div class="d-flex gap-2 justify-content-center flex-wrap mt-3">
			<button
				class="quick-pill"
				data-query="1 BHK Mumbai"
				data-post-query="buyer 1 bhk vikhroli"
				data-project-label="1 BHK Mumbai"
				data-post-label="Buyer 1 BHK Vikhroli"
				type="button"
			>
				<i class="bi bi-house-door me-1"></i><span class="quick-pill-text">1 BHK Mumbai</span>
			</button>
			<button
				class="quick-pill"
				data-query="Ready to Move Thane"
				data-post-query="seller 2 bhk ghatkopar"
				data-project-label="Ready to Move Thane"
				data-post-label="Seller 2 BHK Ghatkopar"
				type="button"
			>
				<i class="bi bi-check-circle me-1"></i><span class="quick-pill-text">Ready to Move Thane</span>
			</button>
			<button
				class="quick-pill"
				data-query="Luxury Powai"
				data-post-query="office rent powai under 80k"
				data-project-label="Luxury Powai"
				data-post-label="Office Rent Powai"
				type="button"
			>
				<i class="bi bi-star me-1"></i><span class="quick-pill-text">Luxury Powai</span>
			</button>
			<button
				class="quick-pill"
				data-query="2 BHK under 1 Cr"
				data-post-query="buyer 2 bhk thane under 1.2 cr"
				data-project-label="2 BHK under 1 Cr"
				data-post-label="Buyer 2 BHK Under 1.2 Cr"
				type="button"
			>
				<i class="bi bi-cash-coin me-1"></i><span class="quick-pill-text">2 BHK under 1 Cr</span>
			</button>
		</div>
	</div>
</section>

<section id="results-section" class="d-none py-4">
	<div class="container">
		<div id="chips-row" class="d-none">
			<div class="d-flex align-items-center gap-2 flex-wrap py-3 border-bottom">
				<span class="text-muted small me-1">Filtered by:</span>
				<div id="chips-container" class="d-flex gap-2 flex-wrap"></div>
				<button id="clear-all" class="btn btn-sm btn-link text-danger ms-auto" type="button">Clear all</button>
			</div>
		</div>

		<div id="relaxed-banner" class="relaxed-banner mt-3 d-none">
			<i class="bi bi-exclamation-triangle-fill me-2"></i>Exact matches not found. Showing similar properties.
		</div>

		<div id="sort-wrapper" class="d-flex justify-content-between align-items-center my-3 flex-wrap gap-2">
			<h6 id="results-count" class="mb-0 fw-semibold"></h6>
			<select id="sort-select" class="form-select form-select-sm" style="max-width: 220px;" aria-label="Sort results">
				<option value="relevance" selected>Relevance</option>
				<option value="price_asc">Price Low to High</option>
				<option value="price_desc">Price High to Low</option>
				<option value="possession_date">Possession Date</option>
			</select>
		</div>

		<div id="results-grid" class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4"></div>

		<div id="empty-state" class="d-none text-center py-5">
			<svg width="84" height="84" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
				<path d="M10 30L32 12L54 30" stroke="#9CA3AF" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
				<path d="M18 30V52H46V30" stroke="#9CA3AF" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
				<path d="M28 52V38H36V52" stroke="#9CA3AF" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>
			<h5 class="mt-3 mb-2">No properties found</h5>
			<p class="text-muted mb-3">Try different keywords or browse all properties</p>
			<button id="clear-search-btn" type="button" class="btn btn-outline-custom">Clear Search</button>
		</div>

		<div id="pagination-wrapper" class="d-flex flex-column align-items-center mt-4 gap-2">
			<nav aria-label="Search pagination">
				<ul id="pagination-list" class="pagination pagination-custom"></ul>
			</nav>
			<small id="pagination-info" class="text-muted"></small>
		</div>
	</div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
