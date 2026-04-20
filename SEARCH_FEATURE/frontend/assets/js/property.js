const PROPERTY_ENDPOINT = 'http://localhost/Internship/SEARCH_FEATURE/endpoints/property.php';
const DETAIL_IMAGE_FALLBACK = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 600"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="0"><stop offset="0%" stop-color="%231A1A2E"/><stop offset="100%" stop-color="%230F3460"/></linearGradient></defs><rect width="1200" height="600" fill="url(%23g)"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="rgba(255,255,255,0.35)" font-size="68" font-family="Inter,Arial,sans-serif">PropSearch</text></svg>';

function detailEscape(value) {
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

function getProjectIdFromUrl() {
	const params = new URLSearchParams(window.location.search);
	return Number(params.get('id') || 0);
}

function getProjectFromSessionCache(projectId) {
	try {
		const cache = JSON.parse(sessionStorage.getItem('propsearch_project_cache') || '{}');
		return cache[String(projectId)] || null;
	} catch (error) {
		console.error('Failed to parse project cache:', error);
		return null;
	}
}

function setFavoriteButtonState(button, projectId) {
	if (!button) {
		return;
	}

	const active = isFavorite(projectId);
	button.classList.toggle('favorite-active', active);
	button.innerHTML = active
		? '<i class="bi bi-heart-fill me-1"></i>Saved'
		: '<i class="bi bi-heart me-1"></i>Save';
}

function renderFlatRows(flats) {
	if (!Array.isArray(flats) || flats.length === 0) {
		return '<tr><td colspan="7" class="text-muted">No flat configurations available.</td></tr>';
	}

	return flats.map(function mapFlat(flat) {
		return [
			'<tr>',
			'<td>' + detailEscape(flat.type || '-') + '</td>',
			'<td>' + detailEscape(formatIndianCurrency(flat.base_price)) + '</td>',
			'<td>' + detailEscape(formatIndianCurrency(flat.total_charge)) + '</td>',
			'<td>' + detailEscape(flat.carpet_area || '-') + '</td>',
			'<td>' + detailEscape(flat.builtup_area || '-') + '</td>',
			'<td>' + detailEscape(flat.bathroom_count || '-') + '</td>',
			'<td>' + detailEscape(flat.transaction_type || '-') + '</td>',
			'</tr>'
		].join('');
	}).join('');
}

function applyPropertyDetails(project, flats) {
	document.getElementById('detail-name').textContent = project.project_name || 'Property Details';
	document.getElementById('detail-meta').textContent = (project.builder_name || 'Unknown Builder') + ' • ' + (project.location_name || '-');
	document.getElementById('detail-price-main').textContent = formatIndianCurrency(project.base_price);
	document.getElementById('detail-price-inclusive').textContent = 'All inclusive: ' + formatIndianCurrency(project.total_charge);
	document.getElementById('detail-status').textContent = project.project_status || 'Upcoming';
	document.getElementById('detail-possession').textContent = 'Possession: ' + formatPossessionDate(project.possession_date);
	document.getElementById('detail-builder').textContent = project.builder_name || '-';
	document.getElementById('detail-location').textContent = project.location_name || '-';
	document.getElementById('detail-rera').textContent = project.rera_no || '-';
	document.getElementById('detail-rank').textContent = String(project.rank || '-');
	document.getElementById('detail-coords').textContent = (project.latitude || '-') + ', ' + (project.longitude || '-');

	const segment = getSegmentLabel(project.project_segment);
	document.getElementById('detail-segment').textContent = segment ? segment.label : '-';

	const image = document.getElementById('detail-image');
	const mainImageSrc = project.header_image ? ('assets/images/' + project.header_image) : DETAIL_IMAGE_FALLBACK;
	const isFallbackDirect = !project.header_image;

	if (image.dataset.loadedSrc === mainImageSrc) {
		image.alt = project.project_name || 'Property image';
		document.getElementById('detail-flat-list').innerHTML = renderFlatRows(flats);
		return;
	}

	image.dataset.loadedSrc = mainImageSrc;
	if (project.header_image) {
		image.alt = project.project_name || 'Property image';
	} else {
		image.alt = 'Property image placeholder';
	}

	if (isFallbackDirect) {
		image.onerror = null;
		image.src = DETAIL_IMAGE_FALLBACK;
	} else {
		image.onerror = function onImageError() {
			image.onerror = null;
			image.dataset.loadedSrc = DETAIL_IMAGE_FALLBACK;
			image.alt = 'Property image placeholder';
			image.src = DETAIL_IMAGE_FALLBACK;
		};
		image.src = mainImageSrc;
	}

	document.getElementById('detail-flat-list').innerHTML = renderFlatRows(flats);
}

async function fetchPropertyDetails(projectId) {
	const response = await fetch(PROPERTY_ENDPOINT + '?id=' + encodeURIComponent(projectId));
	if (!response.ok) {
		throw new Error('Failed to load property details');
	}

	const payload = await response.json();
	if (!payload || payload.status !== 'success' || !payload.data || !payload.data.project) {
		throw new Error('Invalid property response');
	}

	return payload.data;
}

function toFavoritePayload(project) {
	return {
		project_id: project.project_id,
		project_name: project.project_name,
		builder_name: project.builder_name,
		location_name: project.location_name,
		flat_type: project.flat_type,
		base_price: project.base_price,
		total_charge: project.total_charge,
		carpet_area: project.carpet_area,
		project_status: project.project_status,
		possession_date: project.possession_date,
		header_image: project.header_image,
		rera_no: project.rera_no,
		project_segment: project.project_segment,
		latitude: project.latitude,
		longitude: project.longitude,
		rank: project.rank
	};
}

document.addEventListener('DOMContentLoaded', async function onPropertyReady() {
	updateFavoritesNavBadge();

	const loadingEl = document.getElementById('property-loading');
	const contentEl = document.getElementById('property-content');
	const errorEl = document.getElementById('property-error');
	const favoriteBtn = document.getElementById('detail-favorite-btn');
	const projectId = getProjectIdFromUrl();

	if (!loadingEl || !contentEl || !errorEl || !projectId) {
		return;
	}

	let currentProject = null;
	refreshFavoritesFromServer().then(function onFavoritesHydrated() {
		updateFavoritesNavBadge();
		if (currentProject && favoriteBtn) {
			setFavoriteButtonState(favoriteBtn, currentProject.project_id);
		}
	});

	function bindFavoriteButton(project) {
		if (!favoriteBtn || !project || !project.project_id) {
			return;
		}

		currentProject = project;
		setFavoriteButtonState(favoriteBtn, project.project_id);
		favoriteBtn.onclick = function onFavoriteClick() {
			if (!currentProject) {
				return;
			}

			upsertFavorite(toFavoritePayload(currentProject));
			setFavoriteButtonState(favoriteBtn, currentProject.project_id);
			updateFavoritesNavBadge();
		};
	}

	function showContent(project, flats) {
		applyPropertyDetails(project, flats);
		loadingEl.classList.add('d-none');
		errorEl.classList.add('d-none');
		contentEl.classList.remove('d-none');
		bindFavoriteButton(project);
	}

	const cachedProject = getProjectFromSessionCache(projectId);
	if (cachedProject) {
		// Render immediately from session cache to avoid visible loading flash.
		showContent(cachedProject, []);
	}

	try {
		const data = await fetchPropertyDetails(projectId);
		const project = data.project;
		const flats = Array.isArray(data.flats) ? data.flats : [];

		showContent(project, flats);
	} catch (error) {
		console.error(error);
		if (cachedProject) {
			showContent(cachedProject, []);
			return;
		}

		loadingEl.classList.add('d-none');
		errorEl.classList.remove('d-none');
	}
});
