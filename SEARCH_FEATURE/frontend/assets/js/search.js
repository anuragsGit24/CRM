const SEARCH_ENDPOINT = 'http://localhost/Internship/SEARCH_FEATURE/endpoints/search.php';
const SUGGEST_ENDPOINT = 'http://localhost/Internship/SEARCH_FEATURE/endpoints/suggest.php';

const state = {
	currentQuery: '',
	currentPage: 1,
	currentLimit: 20,
	queryInterpreted: {},
	totalCount: 0,
	totalPages: 0,
	isLoading: false,
	hasSearched: false,
	activeFilters: {},
	sortBy: 'relevance',
	currentResults: [],
	projectMap: {},
	activeSearchController: null,
	lastRequestKey: '',
	userCoords: null
};

let suggestionIndex = -1;
let currentSuggestions = [];

function getElements() {
	return {
		searchWrapper: document.getElementById('search-wrapper'),
		searchInput: document.getElementById('search-input'),
		searchBtn: document.getElementById('search-btn'),
		suggestionDropdown: document.getElementById('suggestion-dropdown'),
		suggestionList: document.getElementById('suggestion-list'),
		resultsSection: document.getElementById('results-section'),
		resultsGrid: document.getElementById('results-grid'),
		resultsCount: document.getElementById('results-count'),
		chipsRow: document.getElementById('chips-row'),
		chipsContainer: document.getElementById('chips-container'),
		relaxedBanner: document.getElementById('relaxed-banner'),
		paginationWrapper: document.getElementById('pagination-wrapper'),
		paginationList: document.getElementById('pagination-list'),
		paginationInfo: document.getElementById('pagination-info'),
		emptyState: document.getElementById('empty-state'),
		clearAllBtn: document.getElementById('clear-all'),
		clearSearchBtn: document.getElementById('clear-search-btn'),
		sortSelect: document.getElementById('sort-select')
	};
}

function escapeHtml(value) {
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

function normalizeFilterValue(key, value) {
	if (value === null || value === undefined || value === '') {
		return null;
	}

	if (key === 'max_budget') {
		return Number(value);
	}

	return value;
}

function buildFilterLabel(key, value) {
	if (key === 'max_budget') {
		return 'Under ' + formatIndianCurrency(Number(value));
	}

	return String(value);
}

function cleanLocationLabel(location) {
	const normalized = String(location || '').trim();
	if (!normalized) {
		return '';
	}

	return normalized.replace(/^in\s+/i, '').trim();
}

function buildQueryFromFilters(filters) {
	const fragments = [];

	if (filters.bhk) {
		fragments.push(String(filters.bhk));
	}

	if (filters.location) {
		fragments.push(cleanLocationLabel(filters.location));
	}

	if (filters.max_budget) {
		fragments.push('under ' + formatIndianCurrency(Number(filters.max_budget)).replace('₹', ''));
	}

	if (filters.transaction_type) {
		fragments.push(String(filters.transaction_type));
	}

	return fragments.join(' ').trim();
}

function getStatusClass(status) {
	const normalized = String(status || '').toLowerCase();

	if (normalized.includes('ready')) {
		return 'ready-to-move';
	}

	if (normalized.includes('under')) {
		return 'under-construction';
	}

	return 'upcoming';
}

function toNumber(value) {
	const parsed = Number(value);
	return Number.isFinite(parsed) ? parsed : 0;
}

function parseDateValue(dateValue) {
	const parsed = new Date(dateValue || '');
	if (Number.isNaN(parsed.getTime())) {
		return Number.MAX_SAFE_INTEGER;
	}

	return parsed.getTime();
}

function hasGeoIntent(query) {
	return /\b(?:near\s+me|nearby|close\s+to\s+me|around\s+me)\b/i.test(String(query || ''));
}

function getCurrentCoordinates() {
	if (state.userCoords && Number.isFinite(state.userCoords.lat) && Number.isFinite(state.userCoords.lng)) {
		return Promise.resolve(state.userCoords);
	}

	if (!navigator.geolocation) {
		return Promise.reject(new Error('Geolocation is not supported in this browser.'));
	}

	return new Promise(function resolveCoordinates(resolve, reject) {
		navigator.geolocation.getCurrentPosition(
			function onSuccess(position) {
				const lat = Number(position.coords.latitude);
				const lng = Number(position.coords.longitude);
				if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
					reject(new Error('Could not read your location. Please try again.'));
					return;
				}

				state.userCoords = { lat: lat, lng: lng };
				resolve(state.userCoords);
			},
			function onError() {
				reject(new Error('Please allow location access to use near me search.'));
			},
			{ enableHighAccuracy: true, timeout: 10000, maximumAge: 120000 }
		);
	});
}

function sortProjects(projects) {
	const sorted = [...projects];

	if (state.sortBy === 'price_asc') {
		sorted.sort(function priceAsc(a, b) {
			return toNumber(a.base_price) - toNumber(b.base_price);
		});
		return sorted;
	}

	if (state.sortBy === 'price_desc') {
		sorted.sort(function priceDesc(a, b) {
			return toNumber(b.base_price) - toNumber(a.base_price);
		});
		return sorted;
	}

	if (state.sortBy === 'possession_date') {
		sorted.sort(function possessionAsc(a, b) {
			return parseDateValue(a.possession_date) - parseDateValue(b.possession_date);
		});
		return sorted;
	}

	sorted.sort(function relevanceAsc(a, b) {
		return toNumber(a.rank) - toNumber(b.rank);
	});
	return sorted;
}

function hideSuggestions() {
	const { suggestionDropdown } = getElements();
	if (suggestionDropdown) {
		suggestionDropdown.classList.add('d-none');
	}
	suggestionIndex = -1;
}

function showSuggestions() {
	const { suggestionDropdown } = getElements();
	if (suggestionDropdown && currentSuggestions.length > 0) {
		suggestionDropdown.classList.remove('d-none');
	}
}

function setSuggestionActive(index) {
	const { suggestionList } = getElements();
	if (!suggestionList) {
		return;
	}

	const items = suggestionList.querySelectorAll('.suggestion-item');
	items.forEach(function clearActive(item) {
		item.classList.remove('active');
	});

	if (index >= 0 && index < items.length) {
		items[index].classList.add('active');
	}
}

async function performSearch(query, page = 1, overrideFilters = null) {
	const elements = getElements();
	if (!elements.resultsGrid || !elements.resultsSection) {
		return;
	}

	const incomingQuery = typeof query === 'string' ? query.trim() : '';
	if (!incomingQuery && !overrideFilters) {
		return;
	}

	if (overrideFilters) {
		state.activeFilters = { ...overrideFilters };
	}

	const queryFromFilters = buildQueryFromFilters(state.activeFilters);
	const finalQuery = (overrideFilters ? queryFromFilters : incomingQuery) || incomingQuery || state.currentQuery;
	const requestKey = [finalQuery, page, state.currentLimit].join('::');

	if (state.isLoading && state.lastRequestKey === requestKey) {
		return;
	}

	if (state.activeSearchController) {
		state.activeSearchController.abort();
	}

	const controller = new AbortController();
	state.activeSearchController = controller;
	state.lastRequestKey = requestKey;

	state.currentQuery = finalQuery;
	state.currentPage = page;
	state.isLoading = true;
	state.hasSearched = true;

	showSkeletons();
	hideSuggestions();

	try {
		const requestBody = {
			query: finalQuery,
			page: page,
			limit: state.currentLimit
		};

		if (hasGeoIntent(finalQuery)) {
			const coords = await getCurrentCoordinates();
			requestBody.geo_lat = coords.lat;
			requestBody.geo_lng = coords.lng;
		}

		const response = await fetch(SEARCH_ENDPOINT, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json'
			},
			signal: controller.signal,
			body: JSON.stringify(requestBody)
		});

		if (!response.ok) {
			let message = 'Search request failed with status ' + response.status;
			try {
				const errorPayload = await response.json();
				if (errorPayload && errorPayload.message) {
					message = String(errorPayload.message);
				}
			} catch (parseError) {
				console.error('Could not parse search error payload:', parseError);
			}
			throw new Error(message);
		}

		const payload = await response.json();
		if (!payload || payload.status !== 'success') {
			throw new Error('Search response status is not success');
		}

		renderResults(payload);
		updateChips(payload.query_interpreted || {});
		updatePagination(payload.pagination || {});
	} catch (error) {
		if (error && error.name === 'AbortError') {
			return;
		}
		console.error('Search error:', error);
		showError(error && error.message ? error.message : null);
	} finally {
		state.isLoading = false;
		if (state.activeSearchController === controller) {
			state.activeSearchController = null;
		}
	}
}

async function fetchSuggestions(query) {
	const trimmed = (query || '').trim();
	if (trimmed.length < 2) {
		currentSuggestions = [];
		hideSuggestions();
		return;
	}

	try {
		const response = await fetch(SUGGEST_ENDPOINT + '?q=' + encodeURIComponent(trimmed));
		if (!response.ok) {
			throw new Error('Suggestion request failed with status ' + response.status);
		}

		const payload = await response.json();
		if (payload && payload.status === 'success' && payload.data && Array.isArray(payload.data.suggestions)) {
			renderSuggestions(payload.data.suggestions);
			return;
		}

		renderSuggestions([]);
	} catch (error) {
		console.error('Suggestion error:', error);
		renderSuggestions([]);
	}
}

function renderResults(apiResponse) {
	const {
		resultsSection,
		resultsGrid,
		resultsCount,
		relaxedBanner,
		emptyState,
		paginationWrapper
	} = getElements();

	if (!resultsSection || !resultsGrid) {
		return;
	}

	const interpreted = apiResponse.query_interpreted || {};
	const pagination = apiResponse.pagination || {};
	const projects = Array.isArray(apiResponse.data) ? apiResponse.data : [];
	const apiTotalCount = Number(pagination.total_count || 0);
	const effectiveTotalCount = Math.max(apiTotalCount, projects.length);

	state.queryInterpreted = interpreted;
	state.totalCount = effectiveTotalCount;
	state.totalPages = Number(pagination.total_pages || 0);
	state.currentPage = Number(pagination.current_page || state.currentPage);
	state.currentResults = projects;
	state.projectMap = {};
	projects.forEach(function mapProject(project) {
		state.projectMap[String(project.project_id)] = project;
	});

	if (!Object.keys(state.activeFilters).length) {
		state.activeFilters = Object.entries(interpreted).reduce(function build(acc, entry) {
			const key = entry[0];
			const value = normalizeFilterValue(key, entry[1]);
			if (value !== null && value !== undefined && value !== '') {
				acc[key] = value;
			}
			return acc;
		}, {});
	}

	resultsSection.classList.remove('d-none');

	if (relaxedBanner) {
		relaxedBanner.classList.toggle('d-none', !Boolean(apiResponse.is_relaxed));
	}

	if (projects.length === 0) {
		resultsGrid.innerHTML = '';
		if (emptyState) {
			emptyState.classList.remove('d-none');
		}
		if (paginationWrapper) {
			paginationWrapper.classList.add('d-none');
		}
	} else {
		renderProjectGrid();

		if (emptyState) {
			emptyState.classList.add('d-none');
		}

		if (paginationWrapper) {
			paginationWrapper.classList.remove('d-none');
		}
	}

	if (resultsCount) {
		const cleanedLocation = cleanLocationLabel(interpreted.location);
		const locationText = cleanedLocation ? ' in ' + cleanedLocation : '';
		resultsCount.textContent = state.totalCount + ' properties found' + locationText;
	}
}

function renderProjectGrid() {
	const { resultsGrid } = getElements();
	if (!resultsGrid) {
		return;
	}

	const sortedProjects = sortProjects(state.currentResults);
	resultsGrid.innerHTML = sortedProjects.map(function mapProject(project) {
		return renderCard(project);
	}).join('');
}

function persistProjectForDetails(project) {
	if (!project || !project.project_id) {
		return;
	}

	try {
		const cache = JSON.parse(sessionStorage.getItem('propsearch_project_cache') || '{}');
		cache[String(project.project_id)] = project;
		sessionStorage.setItem('propsearch_project_cache', JSON.stringify(cache));
	} catch (error) {
		console.error('Failed to cache project for details page:', error);
	}
}

function makeFavoritePayload(project) {
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

function renderCard(project) {
	const segment = getSegmentLabel(project.project_segment);
	const statusClass = getStatusClass(project.project_status);
	const priceText = formatIndianCurrency(project.base_price);
	const inclusiveText = formatIndianCurrency(project.total_charge);
	const possessionText = formatPossessionDate(project.possession_date);
	const favoriteClass = isFavorite(project.project_id) ? 'favorite-active' : '';
	const favoriteIcon = isFavorite(project.project_id) ? 'bi-heart-fill' : 'bi-heart';

	const imageHtml = project.header_image
		? '<img class="card-image" src="assets/images/' + escapeHtml(project.header_image) + '" alt="' + escapeHtml(project.project_name) + '" loading="lazy" onerror="this.onerror=null;this.style.display=\'none\';this.parentElement.querySelector(\'.image-placeholder\').classList.remove(\'d-none\');">'
		: '';

	const fallbackClass = project.header_image ? 'image-placeholder d-none' : 'image-placeholder';
	const segmentBadge = segment
		? '<span class="segment-badge ' + escapeHtml(segment.class) + '">' + escapeHtml(segment.label) + '</span>'
		: '';

	return [
		'<div class="col">',
		'<article class="card property-card h-100 property-clickable" data-project-id="' + escapeHtml(project.project_id) + '">',
		'<div class="position-relative">',
		imageHtml,
		'<div class="' + fallbackClass + '"><span class="initials">' + escapeHtml(getProjectInitials(project.project_name)) + '</span></div>',
		segmentBadge,
		'<span class="status-badge ' + escapeHtml(statusClass) + '">' + escapeHtml(project.project_status || 'Upcoming') + '</span>',
		'</div>',
		'<div class="card-body d-flex flex-column">',
		'<h5 class="card-title mb-1">' + escapeHtml(project.project_name || '-') + '</h5>',
		'<p class="text-muted mb-2 small">' + escapeHtml(project.builder_name || 'Unknown Builder') + ' • ' + escapeHtml(project.location_name || '-') + '</p>',
		'<p class="mb-2"><span class="badge text-bg-light">' + escapeHtml(project.flat_type || '-') + '</span></p>',
		'<div class="price-main">' + escapeHtml(priceText) + '</div>',
		'<div class="price-inclusive mb-2">All inclusive: ' + escapeHtml(inclusiveText) + '</div>',
		'<div class="d-flex justify-content-between text-muted small mb-3">',
		'<span><i class="bi bi-aspect-ratio me-1"></i>' + escapeHtml(project.carpet_area || 'NA') + '</span>',
		'<span><i class="bi bi-calendar-event me-1"></i>' + escapeHtml(possessionText) + '</span>',
		'</div>',
		'<p class="small text-muted mb-3">RERA: ' + escapeHtml(project.rera_no || 'NA') + '</p>',
		'<div class="mt-auto d-flex gap-2">',
		'<button class="btn btn-primary-custom w-100 view-details-btn" data-project-id="' + escapeHtml(project.project_id) + '" type="button">View Details</button>',
		'<button class="btn btn-outline-custom favorite-btn ' + favoriteClass + '" data-project-id="' + escapeHtml(project.project_id) + '" type="button" aria-label="Add to favorites"><i class="bi ' + favoriteIcon + '"></i></button>',
		'</div>',
		'</div>',
		'</article>',
		'</div>'
	].join('');
}

function renderSuggestions(suggestions) {
	const { suggestionList } = getElements();
	currentSuggestions = Array.isArray(suggestions) ? suggestions : [];
	suggestionIndex = -1;

	if (!suggestionList) {
		return;
	}

	if (currentSuggestions.length === 0) {
		suggestionList.innerHTML = '';
		hideSuggestions();
		return;
	}

	suggestionList.innerHTML = currentSuggestions.map(function mapSuggestion(item, index) {
		const name = item && item.name ? item.name : '';
		return '<li class="suggestion-item" role="option" data-index="' + index + '">' + escapeHtml(name) + '</li>';
	}).join('');

	suggestionList.querySelectorAll('.suggestion-item').forEach(function bindSuggestion(item) {
		item.addEventListener('click', function onSuggestionClick() {
			const index = Number(item.getAttribute('data-index'));
			const selected = currentSuggestions[index];
			if (!selected || !selected.name) {
				return;
			}

			const { searchInput } = getElements();
			if (searchInput) {
				searchInput.value = selected.name;
			}

			hideSuggestions();
			performSearch(selected.name, 1);
		});
	});

	showSuggestions();
}

function updateChips(queryInterpreted) {
	const { chipsContainer, chipsRow } = getElements();
	if (!chipsContainer || !chipsRow) {
		return;
	}

	chipsContainer.innerHTML = '';

	state.activeFilters = Object.entries(queryInterpreted || {}).reduce(function collect(acc, entry) {
		const key = entry[0];
		const value = normalizeFilterValue(key, entry[1]);
		if (value !== null && value !== undefined && value !== '') {
			acc[key] = value;
		}
		return acc;
	}, {});

	const keys = Object.keys(state.activeFilters);
	if (keys.length === 0) {
		chipsRow.classList.add('d-none');
		return;
	}

	keys.forEach(function createChip(key) {
		const value = state.activeFilters[key];
		const chip = document.createElement('span');
		chip.className = 'filter-chip';
		chip.setAttribute('data-filter-key', key);
		chip.innerHTML = [
			'<span>' + escapeHtml(buildFilterLabel(key, value)) + '</span>',
			'<button type="button" class="chip-remove" aria-label="Remove filter">&times;</button>'
		].join('');

		const removeBtn = chip.querySelector('.chip-remove');
		if (removeBtn) {
			removeBtn.addEventListener('click', function onChipRemove() {
				const updatedFilters = { ...state.activeFilters };
				delete updatedFilters[key];

				state.activeFilters = updatedFilters;
				const rebuiltQuery = buildQueryFromFilters(updatedFilters);

				if (!rebuiltQuery) {
					const { searchInput } = getElements();
					if (searchInput) {
						searchInput.value = '';
					}
					chipsRow.classList.add('d-none');
					return;
				}

				const { searchInput } = getElements();
				if (searchInput) {
					searchInput.value = rebuiltQuery;
				}

				performSearch(rebuiltQuery, 1, updatedFilters);
			});
		}

		chipsContainer.appendChild(chip);
	});

	chipsRow.classList.remove('d-none');
}

function updatePagination(pagination) {
	const { paginationList, paginationInfo, paginationWrapper } = getElements();
	if (!paginationList || !paginationInfo || !paginationWrapper) {
		return;
	}

	const currentPage = Number(pagination.current_page || state.currentPage || 1);
	const perPage = Number(pagination.per_page || state.currentLimit || 20);
	const totalCount = Math.max(Number(pagination.total_count || 0), Number(state.totalCount || 0));
	const totalPages = Number(pagination.total_pages || state.totalPages || 0);

	state.currentPage = currentPage;
	state.currentLimit = perPage;
	state.totalCount = totalCount;
	state.totalPages = totalPages;

	if (totalCount === 0 || totalPages <= 1) {
		paginationList.innerHTML = '';
		paginationInfo.textContent = totalCount === 0 ? 'Showing 0 results' : 'Showing all results';
		paginationWrapper.classList.toggle('d-none', totalCount === 0);
		return;
	}

	const startPage = Math.max(1, currentPage - 2);
	const endPage = Math.min(totalPages, startPage + 4);
	const adjustedStart = Math.max(1, endPage - 4);

	const items = [];
	items.push(
		'<li class="page-item ' + (currentPage === 1 ? 'disabled' : '') + '">',
		'<button class="page-link" type="button" data-page="' + (currentPage - 1) + '">Previous</button>',
		'</li>'
	);

	for (let page = adjustedStart; page <= endPage; page += 1) {
		items.push(
			'<li class="page-item ' + (page === currentPage ? 'active' : '') + '">',
			'<button class="page-link" type="button" data-page="' + page + '">' + page + '</button>',
			'</li>'
		);
	}

	items.push(
		'<li class="page-item ' + (currentPage === totalPages ? 'disabled' : '') + '">',
		'<button class="page-link" type="button" data-page="' + (currentPage + 1) + '">Next</button>',
		'</li>'
	);

	paginationList.innerHTML = items.join('');

	const startResult = (currentPage - 1) * perPage + 1;
	const endResult = Math.min(currentPage * perPage, totalCount);
	paginationInfo.textContent = 'Showing ' + startResult + '-' + endResult + ' of ' + totalCount + ' results';

	paginationList.querySelectorAll('.page-link').forEach(function bindPageLink(btn) {
		btn.addEventListener('click', function onPageClick() {
			const target = Number(btn.getAttribute('data-page'));
			if (!Number.isFinite(target) || target < 1 || target > totalPages || target === currentPage) {
				return;
			}

			performSearch(state.currentQuery, target);
			scrollToResults();
		});
	});

	paginationWrapper.classList.remove('d-none');
}

function showSkeletons() {
	const { resultsSection, resultsGrid, emptyState, paginationWrapper } = getElements();
	if (!resultsSection || !resultsGrid) {
		return;
	}

	const skeletons = Array.from({ length: 6 }).map(function makeSkeleton() {
		return [
			'<div class="col">',
			'<div class="skeleton-card p-3">',
			'<div class="skeleton-line mb-3" style="height: 200px;"></div>',
			'<div class="skeleton-line mb-2" style="height: 18px; width: 75%;"></div>',
			'<div class="skeleton-line mb-2" style="height: 14px; width: 55%;"></div>',
			'<div class="skeleton-line mb-3" style="height: 24px; width: 40%;"></div>',
			'<div class="skeleton-line" style="height: 38px;"></div>',
			'</div>',
			'</div>'
		].join('');
	}).join('');

	resultsGrid.innerHTML = skeletons;
	resultsSection.classList.remove('d-none');

	if (emptyState) {
		emptyState.classList.add('d-none');
	}

	if (paginationWrapper) {
		paginationWrapper.classList.add('d-none');
	}
}

function showError(message) {
	const { resultsSection, resultsGrid, emptyState, paginationWrapper } = getElements();
	if (!resultsSection || !resultsGrid) {
		return;
	}
	const safeMessage = escapeHtml(message || 'Something went wrong. Please try again.');

	resultsSection.classList.remove('d-none');
	resultsGrid.innerHTML = '<div class="col-12"><div class="alert alert-danger mb-0" role="alert">' + safeMessage + '</div></div>';

	if (emptyState) {
		emptyState.classList.add('d-none');
	}

	if (paginationWrapper) {
		paginationWrapper.classList.add('d-none');
	}
}

function scrollToResults() {
	const section = document.getElementById('results-section');
	if (section) {
		section.scrollIntoView({ behavior: 'smooth' });
	}
}

function openPropertyDetails(projectId) {
	window.location.href = 'property.php?id=' + encodeURIComponent(projectId);
}

function bindResultGridActions() {
	const { resultsGrid } = getElements();
	if (!resultsGrid) {
		return;
	}

	resultsGrid.addEventListener('click', function onGridClick(event) {
		const favoriteBtn = event.target.closest('.favorite-btn');
		if (favoriteBtn) {
			event.preventDefault();
			event.stopPropagation();
			const projectId = String(favoriteBtn.getAttribute('data-project-id') || '');
			const project = state.projectMap[projectId];
			if (!project) {
				return;
			}

			const isNowFavorite = upsertFavorite(makeFavoritePayload(project));
			favoriteBtn.classList.toggle('favorite-active', isNowFavorite);
			const icon = favoriteBtn.querySelector('i');
			if (icon) {
				icon.classList.toggle('bi-heart', !isNowFavorite);
				icon.classList.toggle('bi-heart-fill', isNowFavorite);
			}
			updateFavoritesNavBadge();
			return;
		}

		const viewBtn = event.target.closest('.view-details-btn');
		if (viewBtn) {
			event.preventDefault();
			event.stopPropagation();
			const projectId = String(viewBtn.getAttribute('data-project-id') || '');
			const project = state.projectMap[projectId];
			if (project) {
				persistProjectForDetails(project);
			}
			openPropertyDetails(projectId);
			return;
		}

		const card = event.target.closest('.property-clickable');
		if (!card) {
			return;
		}

		const projectId = String(card.getAttribute('data-project-id') || '');
		if (!projectId) {
			return;
		}

		const project = state.projectMap[projectId];
		if (project) {
			persistProjectForDetails(project);
		}
		openPropertyDetails(projectId);
	});
}

document.addEventListener('DOMContentLoaded', function onReady() {
	updateFavoritesNavBadge();
	refreshFavoritesFromServer().then(function onFavoritesHydrated() {
		updateFavoritesNavBadge();
		if (state.currentResults.length > 0) {
			renderProjectGrid();
		}
	});

	const {
		searchInput,
		searchBtn,
		searchWrapper,
		suggestionDropdown,
		clearAllBtn,
		clearSearchBtn,
		sortSelect
	} = getElements();

	if (!searchInput || !searchBtn) {
		return;
	}

	bindResultGridActions();

	const debouncedSuggestions = debounce(function onInputSuggest(value) {
		fetchSuggestions(value);
	}, 300);

	searchInput.addEventListener('keydown', function onSearchEnter(event) {
		if (event.key === 'Enter') {
			event.preventDefault();

			if (!suggestionDropdown || suggestionDropdown.classList.contains('d-none')) {
				performSearch(searchInput.value, 1);
				return;
			}

			if (suggestionIndex >= 0 && currentSuggestions[suggestionIndex]) {
				searchInput.value = currentSuggestions[suggestionIndex].name;
				hideSuggestions();
				performSearch(searchInput.value, 1);
				return;
			}

			performSearch(searchInput.value, 1);
		}

		if (event.key === 'ArrowDown') {
			if (currentSuggestions.length === 0) {
				return;
			}

			event.preventDefault();
			suggestionIndex = Math.min(suggestionIndex + 1, currentSuggestions.length - 1);
			setSuggestionActive(suggestionIndex);
		}

		if (event.key === 'ArrowUp') {
			if (currentSuggestions.length === 0) {
				return;
			}

			event.preventDefault();
			suggestionIndex = Math.max(suggestionIndex - 1, 0);
			setSuggestionActive(suggestionIndex);
		}
	});

	searchBtn.addEventListener('click', function onSearchClick() {
		performSearch(searchInput.value, 1);
	});

	searchInput.addEventListener('input', function onSearchInput() {
		debouncedSuggestions(searchInput.value);
	});

	searchInput.addEventListener('focus', function onSearchFocus() {
		if (currentSuggestions.length > 0) {
			showSuggestions();
		}
	});

	document.addEventListener('click', function onDocumentClick(event) {
		if (!searchWrapper) {
			return;
		}

		if (!searchWrapper.contains(event.target) && (!suggestionDropdown || !suggestionDropdown.contains(event.target))) {
			hideSuggestions();
		}
	});

	document.querySelectorAll('.quick-pill').forEach(function bindQuickPill(button) {
		button.addEventListener('click', function onQuickPillClick() {
			const query = button.getAttribute('data-query') || '';
			searchInput.value = query;
			performSearch(query, 1);
		});
	});

	if (sortSelect) {
		sortSelect.addEventListener('change', function onSortChange() {
			state.sortBy = sortSelect.value || 'relevance';
			if (state.currentResults.length > 0) {
				renderProjectGrid();
			}
		});
	}

	if (clearAllBtn) {
		clearAllBtn.addEventListener('click', function onClearAll(event) {
			event.preventDefault();
			state.activeFilters = {};
			state.queryInterpreted = {};
			const { chipsRow, chipsContainer } = getElements();
			if (chipsContainer) {
				chipsContainer.innerHTML = '';
			}
			if (chipsRow) {
				chipsRow.classList.add('d-none');
			}
			performSearch(searchInput.value, 1);
		});
	}

	if (clearSearchBtn) {
		clearSearchBtn.addEventListener('click', function onClearSearch() {
			const {
				resultsGrid,
				resultsSection,
				emptyState,
				paginationWrapper,
				relaxedBanner,
				chipsRow,
				chipsContainer,
				resultsCount
			} = getElements();

			searchInput.value = '';
			hideSuggestions();
			state.currentQuery = '';
			state.currentPage = 1;
			state.totalCount = 0;
			state.totalPages = 0;
			state.queryInterpreted = {};
			state.activeFilters = {};
			state.currentResults = [];
			state.projectMap = {};
			state.hasSearched = false;

			if (resultsGrid) {
				resultsGrid.innerHTML = '';
			}

			if (resultsCount) {
				resultsCount.textContent = '';
			}

			if (emptyState) {
				emptyState.classList.add('d-none');
			}

			if (relaxedBanner) {
				relaxedBanner.classList.add('d-none');
			}

			if (paginationWrapper) {
				paginationWrapper.classList.add('d-none');
			}

			if (chipsContainer) {
				chipsContainer.innerHTML = '';
			}

			if (chipsRow) {
				chipsRow.classList.add('d-none');
			}

			if (resultsSection) {
				resultsSection.classList.add('d-none');
			}
		});
	}
});
