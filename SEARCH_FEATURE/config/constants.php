<?php
declare(strict_types=1);

if (!defined('BHK_MAP')) {
	define('BHK_MAP', [
		'studio' => 'Studio',
		'1 bhk' => '1 BHK',
		'1bhk' => '1 BHK',
		'1 bedroom' => '1 BHK',
		'1 bed room' => '1 BHK',
		'2 bhk' => '2 BHK',
		'2bhk' => '2 BHK',
		'2 bedroom' => '2 BHK',
		'2 bed room' => '2 BHK',
		'3 bhk' => '3 BHK',
		'3bhk' => '3 BHK',
		'3 bedroom' => '3 BHK',
		'3 bed room' => '3 BHK',
		'4 bhk' => '4 BHK',
		'4bhk' => '4 BHK',
		'4 bedroom' => '4 BHK',
		'4 bed room' => '4 BHK',
	]);
}

if (!defined('TRANSACTION_MAP')) {
	define('TRANSACTION_MAP', [
		'buy' => 'Buy',
		'sale' => 'Buy',
		'purchase' => 'Buy',
		'rent' => 'Rent',
		'rental' => 'Rent',
		'lease' => 'Lease',
		'resale' => 'Resale',
	]);
}

if (!defined('SEGMENT_MAP')) {
	define('SEGMENT_MAP', [
		'affordable' => '1',
		'luxury' => '2',
		'ultra luxury' => '3',
		'value' => '4',
	]);
}

if (!defined('BUDGET_MULTIPLIERS')) {
	define('BUDGET_MULTIPLIERS', [
		'k' => 1000,
		'lakh' => 100000,
		'l' => 100000,
		'cr' => 10000000,
	]);
}

if (!defined('PROPERTY_TYPE_MAP')) {
	define('PROPERTY_TYPE_MAP', [
		'flat' => 1,
		'apartment' => 1,
		'office' => 2,
		'office space' => 2,
		'commercial' => 2,
		'shop' => 3,
		'retail' => 3,
	]);
}

if (!defined('POST_TYPE_MAP')) {
	define('POST_TYPE_MAP', [
		'buyer' => 1,
		'buy' => 1,
		'looking' => 1,
		'need' => 1,
		'want' => 1,
		'seller' => 2,
		'sell' => 2,
		'selling' => 2,
		'sale' => 2,
		'resale' => 2,
	]);
}

if (!defined('POST_PROPERTY_TYPE_MAP')) {
	define('POST_PROPERTY_TYPE_MAP', [
		'flat' => 1,
		'apartment' => 1,
		'office' => 2,
		'office space' => 2,
		'commercial' => 2,
		'shop' => 3,
		'retail' => 3,
		'bungalow' => 4,
		'villa' => 4,
		'independent house' => 4,
		'plot' => 5,
		'land' => 5,
		'na plot' => 5,
	]);
}

if (!defined('POST_STATUS_MAP')) {
	define('POST_STATUS_MAP', [
		'in progress' => 0,
		'pending' => 0,
		'verified' => 1,
		'active' => 1,
		'expired' => 2,
		'lapsed' => 2,
		'deal lapsed' => 2,
		'blocked' => 3,
		'deal closed' => 4,
		'confirmed' => 4,
		'deal confirmed' => 4,
	]);
}

if (!defined('POST_FOR_MAP')) {
	define('POST_FOR_MAP', [
		'rent' => 2,
		'rental' => 2,
		'lease' => 2,
		'monthly' => 2,
		'sell' => 1,
		'selling' => 1,
		'sale' => 1,
		'resale' => 1,
		'buy' => 1,
		'purchase' => 1,
	]);
}

if (!defined('POST_FLAT_TYPE_MAP')) {
	define('POST_FLAT_TYPE_MAP', [
		'1 bhk' => 1,
		'1bhk' => 1,
		'1.5 bhk' => 2,
		'1.5bhk' => 2,
		'2 bhk' => 3,
		'2bhk' => 3,
		'3 bhk' => 4,
		'3bhk' => 4,
		'4 bhk' => 5,
		'4bhk' => 5,
		'studio' => 6,
		'5 bhk' => 7,
		'5bhk' => 7,
	]);
}

if (!defined('FLAT_TYPE_ID_TO_LABEL')) {
	define('FLAT_TYPE_ID_TO_LABEL', [
		1 => '1 BHK',
		2 => '1.5 BHK',
		3 => '2 BHK',
		4 => '3 BHK',
		5 => '4 BHK',
		6 => 'Studio',
		7 => '5 BHK',
	]);
}

if (!defined('DEFAULT_POST_STATUSES')) {
	define('DEFAULT_POST_STATUSES', [0, 1]);
}

if (!defined('AMENITY_ALIASES')) {
	define('AMENITY_ALIASES', [
		'gym' => 'Gym',
		'fitness' => 'Gym',
		'pool' => 'Swimming Pool',
		'swimming' => 'Swimming Pool',
		'swimming pool' => 'Swimming Pool',
		'club' => 'Club House',
		'clubhouse' => 'Club House',
		'club house' => 'Club House',
		'parking' => 'Car Parking',
		'garden' => 'Landscape Garden',
		'landscape' => 'Landscape Garden',
		'jogging' => 'Jogging Track',
		'jogging track' => 'Jogging Track',
		'play area' => 'Children Play Area',
		'children' => 'Children Play Area',
		'yoga' => 'Yoga & Meditation Area',
		'meditation' => 'Yoga & Meditation Area',
		'gazebo' => 'Gazebo',
		'party' => 'Party Lawn',
		'party lawn' => 'Party Lawn',
		'senior' => 'Senior Citizen Sit Out',
		'theatre' => 'Theatre Room',
		'theater' => 'Theatre Room',
		'study' => 'Study Zone',
		'indoor games' => 'Indoor Games',
		'lobby' => 'Premium Lobby',
	]);
}

if (!defined('BUILDER_SEARCH_MIN_LENGTH')) {
	define('BUILDER_SEARCH_MIN_LENGTH', 3);
}

if (!defined('DEFAULT_LIMIT')) {
	define('DEFAULT_LIMIT', 20);
}

if (!defined('MAX_LIMIT')) {
	define('MAX_LIMIT', 50);
}
