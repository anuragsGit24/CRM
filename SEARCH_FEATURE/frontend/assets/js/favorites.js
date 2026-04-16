function favoriteEscape(value) {
	if (value === null || value === undefined) {
		return '';
	}

	return String(value)
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#039;');
}

function renderFavoriteCard(project) {
	const segment = getSegmentLabel(project.project_segment);
	const statusClass = String(project.project_status || '').toLowerCase().includes('ready')
		? 'ready-to-move'
		: String(project.project_status || '').toLowerCase().includes('under')
			? 'under-construction'
			: 'upcoming';
	const imageHtml = project.header_image
		? '<img class="card-image" src="assets/images/' + favoriteEscape(project.header_image) + '" alt="' + favoriteEscape(project.project_name || 'Property image') + '" loading="lazy" onerror="this.onerror=null;this.style.display=\'none\';this.parentElement.querySelector(\'.image-placeholder\').classList.remove(\'d-none\');">'
		: '';
	const fallbackClass = project.header_image ? 'image-placeholder d-none' : 'image-placeholder';
	const segmentBadge = segment ? '<span class="segment-badge ' + favoriteEscape(segment.class) + '">' + favoriteEscape(segment.label) + '</span>' : '';

	return [
		'<div class="col">',
		'<article class="card property-card h-100">',
		'<div class="position-relative">',
		imageHtml,
		'<div class="' + fallbackClass + '"><span class="initials">' + favoriteEscape(getProjectInitials(project.project_name || 'NA')) + '</span></div>',
		segmentBadge,
		'<span class="status-badge ' + favoriteEscape(statusClass) + '">' + favoriteEscape(project.project_status || 'Upcoming') + '</span>',
		'</div>',
		'<div class="card-body d-flex flex-column">',
		'<h5 class="card-title mb-1">' + favoriteEscape(project.project_name || '-') + '</h5>',
		'<p class="text-muted mb-2 small">' + favoriteEscape(project.builder_name || 'Unknown Builder') + ' • ' + favoriteEscape(project.location_name || '-') + '</p>',
		'<p class="mb-2"><span class="badge text-bg-light">' + favoriteEscape(project.flat_type || '-') + '</span></p>',
		'<div class="price-main">' + favoriteEscape(formatIndianCurrency(project.base_price)) + '</div>',
		'<div class="price-inclusive mb-3">All inclusive: ' + favoriteEscape(formatIndianCurrency(project.total_charge)) + '</div>',
		'<div class="mt-auto d-flex gap-2">',
		'<a class="btn btn-primary-custom w-100" href="property.php?id=' + encodeURIComponent(project.project_id) + '">View Details</a>',
		'<button class="btn btn-outline-custom remove-favorite-btn" data-project-id="' + favoriteEscape(project.project_id) + '" type="button" aria-label="Remove from favorites"><i class="bi bi-trash"></i></button>',
		'</div>',
		'</div>',
		'</article>',
		'</div>'
	].join('');
}

function renderFavoritesPage() {
	const grid = document.getElementById('favorites-grid');
	const empty = document.getElementById('favorites-empty');
	const clearBtn = document.getElementById('clear-favorites-btn');

	if (!grid || !empty) {
		return;
	}

	const favorites = getFavorites();
	if (favorites.length === 0) {
		grid.innerHTML = '';
		empty.classList.remove('d-none');
		if (clearBtn) {
			clearBtn.classList.add('d-none');
		}
		return;
	}

	empty.classList.add('d-none');
	if (clearBtn) {
		clearBtn.classList.remove('d-none');
	}
	grid.innerHTML = favorites.map(function mapFavorite(item) {
		return renderFavoriteCard(item);
	}).join('');

	grid.querySelectorAll('.remove-favorite-btn').forEach(function bindRemove(button) {
		button.addEventListener('click', function onRemove() {
			const projectId = Number(button.getAttribute('data-project-id') || 0);
			const nextFavorites = getFavorites().filter(function keep(item) {
				return Number(item.project_id) !== projectId;
			});
			saveFavorites(nextFavorites);
			updateFavoritesNavBadge();
			renderFavoritesPage();
		});
	});
}

document.addEventListener('DOMContentLoaded', function onFavoritesReady() {
	updateFavoritesNavBadge();
	const clearBtn = document.getElementById('clear-favorites-btn');
	if (clearBtn) {
		clearBtn.addEventListener('click', function onClearAll() {
			saveFavorites([]);
			updateFavoritesNavBadge();
			renderFavoritesPage();
		});
	}

	renderFavoritesPage();
});
