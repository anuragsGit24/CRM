import math
import random
from collections import defaultdict
from typing import Dict, List, Optional, Tuple

import mysql.connector

DB_CONFIG = {
    "host": "localhost",
    "user": "root",
    "password": "",
    "database": "real_estate_db",
}

LATITUDE_JITTER = 0.01
LONGITUDE_JITTER = 0.01
PROJECT_LATITUDE_JITTER = 0.01
PROJECT_LONGITUDE_JITTER = 0.01

MUMBAI_BOUNDS = {
    "min_lat": 18.85,
    "max_lat": 19.50,
    "min_lng": 72.75,
    "max_lng": 73.30,
}

MUMBAI_CENTROID = (19.0760, 72.8777)

STATIONS = [
    {"name": "Masjid", "lat": 18.9461, "lng": 72.8361, "line": "Western"},
    {"name": "Bhandup", "lat": 19.1421, "lng": 72.9373, "line": "Western"},
    {"name": "Vikhroli", "lat": 19.1111, "lng": 72.9272, "line": "Western"},
    {"name": "Sandhurst Road", "lat": 18.9525, "lng": 72.8354, "line": "Western/Central"},
    {"name": "Lower Parel", "lat": 18.9950, "lng": 72.8315, "line": "Western"},
    {"name": "Prabhadevi", "lat": 19.0075, "lng": 72.8361, "line": "Western"},
    {"name": "Mahim Junction", "lat": 19.0410, "lng": 72.8471, "line": "Western"},
    {"name": "Khar Road", "lat": 19.0691, "lng": 72.8402, "line": "Western"},
    {"name": "Jogeshwari", "lat": 19.1360, "lng": 72.8488, "line": "Western"},
    {"name": "Malad", "lat": 19.1868, "lng": 72.8485, "line": "Western"},
    {"name": "Mira Road", "lat": 19.2818, "lng": 72.8558, "line": "Western"},
    {"name": "Naigaon", "lat": 19.3499, "lng": 72.8398, "line": "Western"},
    {"name": "Nalla Sopara", "lat": 19.4172, "lng": 72.8196, "line": "Western"},
    {"name": "Virar", "lat": 19.4542, "lng": 72.8115, "line": "Western"},
    {"name": "Sandhurst Road (Low Level)", "lat": 18.9525, "lng": 72.8354, "line": "Central"},
    {"name": "Currey Road", "lat": 18.9944, "lng": 72.8335, "line": "Central"},
    {"name": "Chinchpokli", "lat": 18.9863, "lng": 72.8302, "line": "Central"},
    {"name": "Vidyavihar", "lat": 19.0792, "lng": 72.8973, "line": "Central"},
    {"name": "Kanjurmarg", "lat": 19.1294, "lng": 72.9304, "line": "Central"},
    {"name": "Nahur", "lat": 19.1539, "lng": 72.9463, "line": "Central"},
    {"name": "Kalwa", "lat": 19.1997, "lng": 72.9937, "line": "Central"},
    {"name": "Mumbra", "lat": 19.1895, "lng": 73.0248, "line": "Central"},
    {"name": "Diva Junction", "lat": 19.1873, "lng": 73.0441, "line": "Central"},
    {"name": "Dombivli", "lat": 19.2184, "lng": 73.0867, "line": "Central"},
    {"name": "Thakurli", "lat": 19.2238, "lng": 73.0991, "line": "Central"},
    {"name": "Vitthalwadi", "lat": 19.2319, "lng": 73.1465, "line": "Central"},
    {"name": "Ulhasnagar", "lat": 19.2215, "lng": 73.1643, "line": "Central"},
    {"name": "Ambernath", "lat": 19.2069, "lng": 73.1872, "line": "Central"},
    {"name": "Badlapur", "lat": 19.1678, "lng": 73.2263, "line": "Central"},
    {"name": "Cotton Green", "lat": 18.9861, "lng": 72.8436, "line": "Harbour"},
    {"name": "Sewri", "lat": 19.0003, "lng": 72.8551, "line": "Harbour"},
    {"name": "GTB Nagar", "lat": 19.0373, "lng": 72.8660, "line": "Harbour"},
    {"name": "Chunabhatti", "lat": 19.0494, "lng": 72.8752, "line": "Harbour"},
    {"name": "Tilak Nagar", "lat": 19.0673, "lng": 72.8931, "line": "Harbour"},
    {"name": "Govandi", "lat": 19.0553, "lng": 72.9152, "line": "Harbour"},
    {"name": "Juinagar", "lat": 19.0526, "lng": 73.0184, "line": "Trans-Harbour"},
    {"name": "Sanpada", "lat": 19.0628, "lng": 73.0094, "line": "Trans-Harbour"},
    {"name": "Koparkhairane", "lat": 19.1039, "lng": 73.0108, "line": "Trans-Harbour"},
    {"name": "Ghansoli", "lat": 19.1219, "lng": 73.0078, "line": "Trans-Harbour"},
    {"name": "Rabale", "lat": 19.1415, "lng": 72.9982, "line": "Trans-Harbour"},
    {"name": "Airoli", "lat": 19.1579, "lng": 72.9934, "line": "Trans-Harbour"},
]

# Known location names can be steered to station candidates when coordinates are missing.
LOCATION_STATION_HINTS = {
    "bhandup": ["Bhandup", "Nahur", "Kanjurmarg"],
    "vikhroli": ["Kanjurmarg", "Vidyavihar"],
    "powai": ["Kanjurmarg", "Vidyavihar"],
    "andheri": ["Jogeshwari", "Khar Road"],
    "thane": ["Kalwa", "Mumbra", "Diva Junction"],
    "mulund": ["Nahur", "Kanjurmarg", "Bhandup"],
    "borivali": ["Malad", "Mira Road"],
    "kanjurmarg": ["Kanjurmarg"],
    "ghatkopar": ["Vidyavihar", "Tilak Nagar"],
    "mira road": ["Mira Road"],
    "naigaon": ["Naigaon"],
    "nalla sopara": ["Nalla Sopara"],
    "nallasopara": ["Nalla Sopara"],
    "virar": ["Virar"],
    "dombivli": ["Dombivli", "Diva Junction"],
    "thakurli": ["Thakurli", "Dombivli"],
    "ulhasnagar": ["Ulhasnagar", "Vitthalwadi"],
    "ambernath": ["Ambernath"],
    "badlapur": ["Badlapur"],
    "airoli": ["Airoli", "Rabale"],
    "ghansoli": ["Ghansoli", "Rabale"],
    "koparkhairane": ["Koparkhairane", "Ghansoli"],
    "sanpada": ["Sanpada", "Juinagar"],
    "jui nagar": ["Juinagar"],
    "juinagar": ["Juinagar"],
    "govandi": ["Govandi", "Tilak Nagar"],
    "chembur": ["Tilak Nagar", "Govandi"],
    "kurla": ["Tilak Nagar", "Vidyavihar"],
    "mahim": ["Mahim Junction", "Prabhadevi"],
}


def normalize_text(value: str) -> str:
    cleaned_chars: List[str] = []
    for ch in value.lower():
        if ch.isalnum() or ch.isspace():
            cleaned_chars.append(ch)
        else:
            cleaned_chars.append(" ")
    return " ".join("".join(cleaned_chars).split())


def parse_float(value: object) -> Optional[float]:
    if value is None:
        return None
    try:
        parsed = float(value)
    except (TypeError, ValueError):
        return None
    if parsed == 0.0:
        return None
    return parsed


def is_within_mumbai(lat: float, lng: float) -> bool:
    return (
        MUMBAI_BOUNDS["min_lat"] <= lat <= MUMBAI_BOUNDS["max_lat"]
        and MUMBAI_BOUNDS["min_lng"] <= lng <= MUMBAI_BOUNDS["max_lng"]
    )


def clamp_to_mumbai(lat: float, lng: float) -> Tuple[float, float]:
    bounded_lat = max(MUMBAI_BOUNDS["min_lat"], min(MUMBAI_BOUNDS["max_lat"], lat))
    bounded_lng = max(MUMBAI_BOUNDS["min_lng"], min(MUMBAI_BOUNDS["max_lng"], lng))
    return bounded_lat, bounded_lng


def add_jitter(base_lat: float, base_lng: float, lat_jitter: float, lng_jitter: float) -> Tuple[float, float]:
    lat = base_lat + random.uniform(-lat_jitter, lat_jitter)
    lng = base_lng + random.uniform(-lng_jitter, lng_jitter)
    return clamp_to_mumbai(lat, lng)


def haversine_km(lat1: float, lng1: float, lat2: float, lng2: float) -> float:
    earth_radius_km = 6371.0
    d_lat = math.radians(lat2 - lat1)
    d_lng = math.radians(lng2 - lng1)

    a = (
        math.sin(d_lat / 2) ** 2
        + math.cos(math.radians(lat1))
        * math.cos(math.radians(lat2))
        * math.sin(d_lng / 2) ** 2
    )
    c = 2 * math.atan2(math.sqrt(a), math.sqrt(1 - a))
    return earth_radius_km * c


def nearest_station_by_coords(lat: float, lng: float) -> Tuple[Dict[str, object], float]:
    nearest = STATIONS[0]
    best_distance = float("inf")

    for station in STATIONS:
        distance = haversine_km(lat, lng, float(station["lat"]), float(station["lng"]))
        if distance < best_distance:
            best_distance = distance
            nearest = station

    return nearest, best_distance


def build_station_lookup() -> Dict[str, Dict[str, object]]:
    lookup: Dict[str, Dict[str, object]] = {}
    for station in STATIONS:
        lookup[normalize_text(str(station["name"]))] = station
    return lookup


def station_from_hints(
    location_name: str,
    aliases: List[str],
    station_lookup: Dict[str, Dict[str, object]],
) -> Optional[Dict[str, object]]:
    text_blob = normalize_text(" ".join([location_name] + aliases))
    if not text_blob:
        return None

    for location_key, station_candidates in LOCATION_STATION_HINTS.items():
        if location_key in text_blob:
            for station_name in station_candidates:
                station = station_lookup.get(normalize_text(station_name))
                if station is not None:
                    return station

    for station_key, station in station_lookup.items():
        if station_key and station_key in text_blob:
            return station

    return None


def pick_station_for_location(
    location_name: str,
    aliases: List[str],
    current_lat: Optional[float],
    current_lng: Optional[float],
    station_lookup: Dict[str, Dict[str, object]],
) -> Dict[str, object]:
    if current_lat is not None and current_lng is not None and is_within_mumbai(current_lat, current_lng):
        nearest, _ = nearest_station_by_coords(current_lat, current_lng)
        return nearest

    hinted_station = station_from_hints(location_name, aliases, station_lookup)
    if hinted_station is not None:
        return hinted_station

    fallback_station, _ = nearest_station_by_coords(MUMBAI_CENTROID[0], MUMBAI_CENTROID[1])
    return fallback_station


def fetch_alias_map(cursor) -> Dict[int, List[str]]:
    alias_map: Dict[int, List[str]] = defaultdict(list)
    cursor.execute("SELECT location_id, alias FROM location_aliases")
    for location_id, alias in cursor.fetchall():
        if location_id is None or alias is None:
            continue
        alias_map[int(location_id)].append(str(alias))
    return alias_map


def update_location_coordinates(cursor, alias_map: Dict[int, List[str]]) -> Dict[int, Tuple[float, float]]:
    cursor.execute("SELECT id, name, latitude, longitude FROM location WHERE status = 1")
    locations = cursor.fetchall()
    if not locations:
        raise RuntimeError("No active locations found")

    station_lookup = build_station_lookup()
    location_coordinates: Dict[int, Tuple[float, float]] = {}

    print(f"Updating coordinates for {len(locations)} active locations")

    for location_id, location_name, latitude, longitude in locations:
        loc_id = int(location_id)
        loc_name = str(location_name or "").strip()

        current_lat = parse_float(latitude)
        current_lng = parse_float(longitude)

        station = pick_station_for_location(
            location_name=loc_name,
            aliases=alias_map.get(loc_id, []),
            current_lat=current_lat,
            current_lng=current_lng,
            station_lookup=station_lookup,
        )

        seeded_lat, seeded_lng = add_jitter(
            float(station["lat"]),
            float(station["lng"]),
            LATITUDE_JITTER,
            LONGITUDE_JITTER,
        )
        seeded_lat = round(seeded_lat, 6)
        seeded_lng = round(seeded_lng, 6)

        cursor.execute(
            "UPDATE location SET latitude = %s, longitude = %s WHERE id = %s",
            (seeded_lat, seeded_lng, loc_id),
        )

        location_coordinates[loc_id] = (seeded_lat, seeded_lng)

        distance_to_station = haversine_km(
            seeded_lat,
            seeded_lng,
            float(station["lat"]),
            float(station["lng"]),
        )

        print(
            f"Location '{loc_name}' -> {station['name']} ({station['line']})"
            f" | lat={seeded_lat:.6f}, lng={seeded_lng:.6f},"
            f" offset={distance_to_station:.2f} km"
        )

    return location_coordinates


def update_project_coordinates(cursor, location_coordinates: Dict[int, Tuple[float, float]]) -> int:
    cursor.execute("SELECT id, location_id FROM projects WHERE status = 1")
    projects = cursor.fetchall()

    updates: List[Tuple[str, str, str, int]] = []

    for project_id, location_id in projects:
        if location_id is None:
            continue

        location_pair = location_coordinates.get(int(location_id))
        if location_pair is None:
            continue

        base_lat, base_lng = location_pair
        project_lat, project_lng = add_jitter(
            base_lat,
            base_lng,
            PROJECT_LATITUDE_JITTER,
            PROJECT_LONGITUDE_JITTER,
        )

        latlong = f"{project_lat:.6f},{project_lng:.6f}"
        maps_url = f"https://www.google.com/maps/search/?api=1&query={project_lat:.6f},{project_lng:.6f}"

        updates.append((latlong, maps_url, maps_url, int(project_id)))

    if updates:
        cursor.executemany(
            "UPDATE projects SET latlong = %s, google_map = %s, google_link = %s WHERE id = %s",
            updates,
        )

    return len(updates)


def main() -> None:
    random.seed()

    connection = mysql.connector.connect(**DB_CONFIG)
    cursor = connection.cursor()

    try:
        alias_map = fetch_alias_map(cursor)
        location_coordinates = update_location_coordinates(cursor, alias_map)
        updated_projects = update_project_coordinates(cursor, location_coordinates)

        connection.commit()

        print("Done")
        print(f"Locations updated: {len(location_coordinates)}")
        print(f"Projects updated: {updated_projects}")
        print("All coordinates seeded from Mumbai station data with +/-0.01 jitter")
    except Exception:
        connection.rollback()
        raise
    finally:
        cursor.close()
        connection.close()


if __name__ == "__main__":
    main()