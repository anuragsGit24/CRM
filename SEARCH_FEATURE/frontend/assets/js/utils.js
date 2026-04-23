function trimNumber(value) {
	return Number(value.toFixed(2)).toString();
}

function formatIndianCurrency(amount) {
	if (amount === null || amount === undefined || Number(amount) === 0 || Number.isNaN(Number(amount))) {
		return 'Price on Request';
	}

	const numericAmount = Number(amount);

	if (numericAmount < 100000) {
		return '₹' + numericAmount.toLocaleString('en-IN');
	}

	if (numericAmount < 10000000) {
		const inLac = numericAmount / 100000;
		return '₹' + trimNumber(inLac) + ' Lac';
	} 

	const inCrore = numericAmount / 10000000;
	return '₹' + trimNumber(inCrore) + ' Cr';
}

function formatPossessionDate(dateString) {
	if (!dateString) {
		return 'TBD';
	}

	const possessionDate = new Date(dateString);

	if (Number.isNaN(possessionDate.getTime())) {
		return 'TBD';
	}

	const today = new Date();
	today.setHours(0, 0, 0, 0);

	if (possessionDate < today) {
		return 'Ready to Move';
	}

	const sixMonthsAhead = new Date(today);
	sixMonthsAhead.setMonth(sixMonthsAhead.getMonth() + 6);

	if (possessionDate <= sixMonthsAhead) {
		return 'Possession Soon';
	}

	return possessionDate.toLocaleDateString('en-US', {
		month: 'short',
		year: 'numeric'
	});
}

function getSegmentLabel(segment) {
	const key = String(segment ?? '').trim();

	if (key === '1') {
		return { label: 'Affordable', class: 'affordable' };
	}

	if (key === '2') {
		return { label: 'Luxury', class: 'luxury' };
	}

	if (key === '3') {
		return { label: 'Ultra Luxury', class: 'ultra-luxury' };
	}

	if (key === '4') {
		return { label: 'Value', class: 'value' };
	}

	return null;
}

function getProjectInitials(name) {
	if (!name || typeof name !== 'string') {
		return 'NA';
	}

	const words = name.trim().split(/\s+/).filter(Boolean);

	if (words.length === 0) {
		return 'NA';
	}

	const first = words[0].charAt(0);
	const second = words.length > 1 ? words[1].charAt(0) : '';

	return (first + second).toUpperCase();
}

function debounce(func, wait) {
	let timeoutId;

	return function debouncedFunction(...args) {
		const context = this;
		clearTimeout(timeoutId);

		timeoutId = setTimeout(function runDebounced() {
			func.apply(context, args);
		}, wait);
	};
}

function getApiEndpoint(endpointFile) {
	const path = window.location.pathname || '';
	const frontendMarker = '/frontend/';
	const markerIndex = path.toLowerCase().indexOf(frontendMarker);
	const basePath = markerIndex >= 0
		? path.slice(0, markerIndex)
		: '/Anurag/CRM/SEARCH_FEATURE';

	return window.location.origin + basePath + '/endpoints/' + endpointFile;
}

const FAVORITES_STORAGE_KEY = 'propsearch_favorites';
const FAVORITES_SESSION_KEY = 'propsearch_favorites_session_id';
const FAVORITES_ENDPOINT = getApiEndpoint('favorites.php');

let fallbackFavoritesSessionId = null;

function getFavoritesSessionId() {
	if (fallbackFavoritesSessionId) {
		return fallbackFavoritesSessionId;
	}

	try {
		const existing = localStorage.getItem(FAVORITES_SESSION_KEY);
		if (existing && existing.trim() !== '') {
			fallbackFavoritesSessionId = existing;
			return existing;
		}

		const generated = (window.crypto && typeof window.crypto.randomUUID === 'function')
			? window.crypto.randomUUID()
			: 'sess_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2);

		localStorage.setItem(FAVORITES_SESSION_KEY, generated);
		fallbackFavoritesSessionId = generated;
		return generated;
	} catch (error) {
		console.error('Failed to initialize favorites session id:', error);
		fallbackFavoritesSessionId = 'sess_fallback_' + Math.random().toString(36).slice(2);
		return fallbackFavoritesSessionId;
	}
}

function getFavorites() {
	try {
		const raw = localStorage.getItem(FAVORITES_STORAGE_KEY);
		if (!raw) {
			return [];
		}

		const parsed = JSON.parse(raw);
		if (!Array.isArray(parsed)) {
			return [];
		}

		return parsed;
	} catch (error) {
		console.error('Failed to read favorites:', error);
		return [];
	}
}

function saveFavorites(favorites) {
	try {
		const normalizedFavorites = Array.isArray(favorites) ? favorites : [];
		localStorage.setItem(FAVORITES_STORAGE_KEY, JSON.stringify(normalizedFavorites));
	} catch (error) {
		console.error('Failed to save favorites:', error);
	}
}

function buildFavoritesListUrl() {
	const url = new URL(FAVORITES_ENDPOINT);
	url.searchParams.set('action', 'list');
	url.searchParams.set('session_id', getFavoritesSessionId());
	url.searchParams.set('page', '1');
	url.searchParams.set('limit', '50');
	return url.toString();
}

function buildFavoritesActionUrl(action) {
	const url = new URL(FAVORITES_ENDPOINT);
	url.searchParams.set('action', action);
	url.searchParams.set('session_id', getFavoritesSessionId());
	return url.toString();
}

async function requestFavoritesListFromServer() {
	const response = await fetch(buildFavoritesListUrl(), {
		method: 'GET',
		headers: {
			'Content-Type': 'application/json'
		}
	});

	let payload = null;
	try {
		payload = await response.json();
	} catch (error) {
		throw new Error('Could not parse favorites list response');
	}

	if (!response.ok || !payload || payload.status !== 'success') {
		const message = payload && payload.message ? String(payload.message) : ('Favorites list request failed with status ' + response.status);
		throw new Error(message);
	}

	return payload;
}

async function sendFavoritesMutation(action, mutationPayload) {
	const body = {
		action: action,
		session_id: getFavoritesSessionId(),
	};

	if (mutationPayload && typeof mutationPayload === 'object') {
		Object.keys(mutationPayload).forEach(function appendField(key) {
			const value = mutationPayload[key];
			if (value !== undefined) {
				body[key] = value;
			}
		});
	}

	const response = await fetch(FAVORITES_ENDPOINT, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json'
		},
		body: JSON.stringify(body)
	});

	let payload = null;
	try {
		payload = await response.json();
	} catch (error) {
		throw new Error('Could not parse favorites mutation response');
	}

	if (!response.ok || !payload || payload.status !== 'success') {
		const message = payload && payload.message ? String(payload.message) : ('Favorites mutation failed with status ' + response.status);
		throw new Error(message);
	}

	return payload;
}

function syncFavoritesMutationInBackground(action, mutationPayload) {
	sendFavoritesMutation(action, mutationPayload).catch(function onSyncError(error) {
		console.error('Favorites sync failed for action ' + action + ':', error);
	});
}

async function refreshFavoritesFromServer() {
	try {
		const payload = await requestFavoritesListFromServer();
		const serverFavorites = payload && Array.isArray(payload.data) ? payload.data : [];
		saveFavorites(serverFavorites);
		return serverFavorites;
	} catch (error) {
		console.error('Failed to refresh favorites from server:', error);
		return getFavorites();
	}
}

function isFavorite(projectId) {
	const id = Number(projectId);
	if (!Number.isFinite(id)) {
		return false;
	}

	return getFavorites().some(function match(project) {
		return Number(project.project_id) === id;
	});
}

function upsertFavorite(project) {
	if (!project || !project.project_id) {
		return false;
	}

	const favorites = getFavorites();
	const id = Number(project.project_id);
	const existingIndex = favorites.findIndex(function findIndex(item) {
		return Number(item.project_id) === id;
	});

	if (existingIndex >= 0) {
		favorites.splice(existingIndex, 1);
		saveFavorites(favorites);
		syncFavoritesMutationInBackground('remove', {
			project_id: id,
		});
		return false;
	}

	favorites.unshift(project);
	saveFavorites(favorites);
	syncFavoritesMutationInBackground('add', {
		project_id: id,
		flat_type: project.flat_type || null,
	});
	return true;
}

function removeFavoriteByProjectId(projectId) {
	const id = Number(projectId);
	if (!Number.isFinite(id) || id <= 0) {
		return false;
	}

	const favorites = getFavorites();
	const nextFavorites = favorites.filter(function keep(item) {
		return Number(item.project_id) !== id;
	});

	if (nextFavorites.length === favorites.length) {
		return false;
	}

	saveFavorites(nextFavorites);
	syncFavoritesMutationInBackground('remove', {
		project_id: id,
	});
	return true;
}

function clearFavorites() {
	const favorites = getFavorites();
	if (favorites.length === 0) {
		return;
	}

	saveFavorites([]);
	favorites.forEach(function removeItem(item) {
		const id = Number(item && item.project_id);
		if (Number.isFinite(id) && id > 0) {
			syncFavoritesMutationInBackground('remove', {
				project_id: id,
			});
		}
	});
}

function updateFavoritesNavBadge() {
	const badge = document.getElementById('favorites-count');
	if (!badge) {
		return;
	}

	const count = getFavorites().length;
	badge.textContent = String(count);
	badge.classList.toggle('d-none', count === 0);
}
