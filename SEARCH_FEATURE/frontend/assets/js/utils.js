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

const FAVORITES_STORAGE_KEY = 'propsearch_favorites';

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
		localStorage.setItem(FAVORITES_STORAGE_KEY, JSON.stringify(favorites));
	} catch (error) {
		console.error('Failed to save favorites:', error);
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
		return false;
	}

	favorites.unshift(project);
	saveFavorites(favorites);
	return true;
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
