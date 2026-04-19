import argparse
import json
import math
import time
import urllib.parse
import urllib.request
from dataclasses import dataclass
from pathlib import Path
from typing import Dict, List, Optional, Sequence, Tuple

import mysql.connector

DB_CONFIG = {
    "host": "localhost",
    "user": "root",
    "password": "",
    "database": "real_estate_db",
}

NOMINATIM_URL = "https://nominatim.openstreetmap.org/search"
USER_AGENT = "MumbaiStationSeeder/1.0 (local-dev-script)"
REQUEST_DELAY_SECONDS = 1.0

# Known coordinates supplied by user in prior context.
KNOWN_COORDINATES = {
    "masjid": (18.9461, 72.8361),
    "sandhurst road": (18.9525, 72.8354),
    "lower parel": (18.9950, 72.8315),
    "prabhadevi": (19.0075, 72.8361),
    "mahim junction": (19.0410, 72.8471),
    "khar road": (19.0691, 72.8402),
    "jogeshwari": (19.1360, 72.8488),
    "malad": (19.1868, 72.8485),
    "mira road": (19.2818, 72.8558),
    "naigaon": (19.3499, 72.8398),
    "nalasopara": (19.4172, 72.8196),
    "nalla sopara": (19.4172, 72.8196),
    "virar": (19.4542, 72.8115),
    "currey road": (18.9944, 72.8335),
    "chinchpokli": (18.9863, 72.8302),
    "vidyavihar": (19.0792, 72.8973),
    "kanjurmarg": (19.1294, 72.9304),
    "nahur": (19.1539, 72.9463),
    "kalwa": (19.1997, 72.9937),
    "mumbra": (19.1895, 73.0248),
    "diva junction": (19.1873, 73.0441),
    "dombivli": (19.2184, 73.0867),
    "thakurli": (19.2238, 73.0991),
    "vithalwadi": (19.2319, 73.1465),
    "ulhasnagar": (19.2215, 73.1643),
    "ambernath": (19.2069, 73.1872),
    "badlapur": (19.1678, 73.2263),
    "cotton green": (18.9861, 72.8436),
    "sewri": (19.0003, 72.8551),
    "gtb nagar": (19.0373, 72.8660),
    "chunabhatti": (19.0494, 72.8752),
    "tilak nagar": (19.0673, 72.8931),
    "govandi": (19.0553, 72.9152),
    "juinagar": (19.0526, 73.0184),
    "sanpada": (19.0628, 73.0094),
    "koparkhairane": (19.1039, 73.0108),
    "ghansoli": (19.1219, 73.0078),
    "rabale": (19.1415, 72.9982),
    "airoli": (19.1579, 72.9934),
}

GEOCODE_ALIASES = {
    "csmt": ["Chhatrapati Shivaji Maharaj Terminus"],
    "cst": ["Chhatrapati Shivaji Maharaj Terminus"],
    "wadala road": ["Vadala Road", "Wadala Road railway station Mumbai"],
    "belapur cbd": ["CBD Belapur", "Belapur CBD"],
    "seawoods darave": ["Seawoods Darave", "Seawoods - Darave", "Seawoods"],
    "king s circle": ["Kings Circle", "King's Circle railway station"],
    "bhivpuri road": ["Bhivpuri Road"],
    "palasdhari": ["Palasdari", "Palasdhari"],
    "elphinstone road": ["Prabhadevi"],
    "kalyan junction": ["Kalyan"],
    "ram mandir": ["Ram Mandir railway station Mumbai"],
    "dockyard road": ["Dockyard Road railway station Mumbai"],
    "reay road": ["Reay Road railway station Mumbai"],
    "mansarovar": ["Mansarovar railway station Navi Mumbai"],
    "khandeshwar": ["Khandeshwar railway station Navi Mumbai"],
    "kelavli": ["Kelavli railway station"],
    "lowjee": ["Lowjee railway station"],
    "atgaon": ["Atgaon railway station"],
    "oobermali": ["Oombermali railway station"],
    "thansit": ["Thansit railway station"],
    "vaitarna": ["Vaitarna railway station"],
    "saphale": ["Saphale railway station"],
    "kelve road": ["Kelve Road railway station"],
    "umroli": ["Umroli railway station"],
    "vangaon": ["Vangaon railway station"],
}

LINES = {
    "Western": [
        "Churchgate",
        "Marine Lines",
        "Charni Road",
        "Grant Road",
        "Mumbai Central",
        "Mahalaxmi",
        "Lower Parel",
        "Prabhadevi",
        "Dadar",
        "Matunga Road",
        "Mahim Junction",
        "Bandra",
        "Khar Road",
        "Santacruz",
        "Vile Parle",
        "Andheri",
        "Jogeshwari",
        "Ram Mandir",
        "Goregaon",
        "Malad",
        "Kandivali",
        "Borivali",
        "Dahisar",
        "Mira Road",
        "Bhayandar",
        "Naigaon",
        "Vasai Road",
        "Nalasopara",
        "Virar",
        "Vaitarna",
        "Saphale",
        "Kelve Road",
        "Palghar",
        "Umroli",
        "Boisar",
        "Vangaon",
        "Dahanu Road",
    ],
    "Central Main": [
        "CSMT",
        "Masjid",
        "Sandhurst Road",
        "Byculla",
        "Chinchpokli",
        "Currey Road",
        "Parel",
        "Dadar",
        "Matunga",
        "Sion",
        "Kurla",
        "Vidyavihar",
        "Ghatkopar",
        "Vikhroli",
        "Kanjurmarg",
        "Bhandup",
        "Nahur",
        "Mulund",
        "Thane",
        "Kalwa",
        "Mumbra",
        "Diva Junction",
        "Dombivli",
        "Thakurli",
        "Kalyan Junction",
    ],
    "Central Kasara Branch": [
        "Kalyan Junction",
        "Shahad",
        "Ambivli",
        "Titwala",
        "Khadavli",
        "Vasind",
        "Asangaon",
        "Atgaon",
        "Thansit",
        "Khardi",
        "Oombermali",
        "Kasara",
    ],
    "Central Khopoli Branch": [
        "Kalyan Junction",
        "Vithalwadi",
        "Ulhasnagar",
        "Ambernath",
        "Badlapur",
        "Vangani",
        "Neral",
        "Bhivpuri Road",
        "Karjat",
        "Palasdhari",
        "Kelavli",
        "Dolavli",
        "Lowjee",
        "Khopoli",
    ],
    "Harbour CSMT-Panvel": [
        "CSMT",
        "Masjid",
        "Sandhurst Road",
        "Dockyard Road",
        "Reay Road",
        "Cotton Green",
        "Sewri",
        "Wadala Road",
        "GTB Nagar",
        "Chunabhatti",
        "Kurla",
        "Tilak Nagar",
        "Chembur",
        "Govandi",
        "Mankhurd",
        "Vashi",
        "Sanpada",
        "Juinagar",
        "Nerul",
        "Seawoods-Darave",
        "Belapur CBD",
        "Kharghar",
        "Mansarovar",
        "Khandeshwar",
        "Panvel",
    ],
    "Harbour Andheri-Panvel": [
        "Andheri",
        "Vile Parle",
        "Santacruz",
        "Khar Road",
        "Bandra",
        "Mahim Junction",
        "King's Circle",
        "Wadala Road",
        "GTB Nagar",
        "Chunabhatti",
        "Kurla",
        "Tilak Nagar",
        "Chembur",
        "Govandi",
        "Mankhurd",
        "Vashi",
        "Sanpada",
        "Juinagar",
        "Nerul",
        "Seawoods-Darave",
        "Belapur CBD",
        "Kharghar",
        "Mansarovar",
        "Khandeshwar",
        "Panvel",
    ],
    "Trans Harbour Thane-Panvel": [
        "Thane",
        "Airoli",
        "Rabale",
        "Ghansoli",
        "Koparkhairane",
        "Turbhe",
        "Juinagar",
        "Nerul",
        "Seawoods-Darave",
        "Belapur CBD",
        "Kharghar",
        "Mansarovar",
        "Khandeshwar",
        "Panvel",
    ],
}

LOCATION_TO_STATION_HINTS = {
    "vikhroli": "Vikhroli",
    "powai": "Kanjurmarg",
    "andheri": "Andheri",
    "bandra": "Bandra",
    "kurla": "Kurla",
    "ghatkopar": "Ghatkopar",
    "thane": "Thane",
    "mulund": "Mulund",
    "bhandup": "Bhandup",
    "kanjurmarg": "Kanjurmarg",
    "goregaon": "Goregaon",
    "malad": "Malad",
    "kandivali": "Kandivali",
    "borivali": "Borivali",
    "dahisar": "Dahisar",
    "mira road": "Mira Road",
    "kharghar": "Kharghar",
    "panvel": "Panvel",
    "vashi": "Vashi",
    "worli": "Prabhadevi",
    "lower parel": "Lower Parel",
    "dadar": "Dadar",
    "chembur": "Chembur",
    "sion": "Sion",
    "bkc": "Kurla",
    "juhu": "Andheri",
    "vile parle": "Vile Parle",
    "santacruz": "Santacruz",
    "jogeshwari": "Jogeshwari",
    "airoli": "Airoli",
    "belapur": "Belapur CBD",
    "nerul": "Nerul",
    "kalyan": "Kalyan Junction",
    "dombivli": "Dombivli",
    "nalasopara": "Nalasopara",
    "virar": "Virar",
    "vasai": "Vasai Road",
    "palghar": "Palghar",
    "badlapur": "Badlapur",
    "ulhasnagar": "Ulhasnagar",
    "neral": "Neral",
    "karjat": "Karjat",
    "titwala": "Titwala",
    "bhiwandi": "Thane",
    "taloja": "Khandeshwar",
    "kamothe": "Mansarovar",
    "ulwe": "Belapur CBD",
    "navi mumbai": "Vashi",
}


@dataclass
class StationRow:
    id: int
    name: str
    line: str
    latitude: float
    longitude: float
    sequence: int
    zone: str


@dataclass
class GraphRow:
    station_id: int
    prev_station_id: Optional[int]
    next_station_id: Optional[int]
    line: str


@dataclass
class StationContext:
    station: StationRow
    previous: Optional[StationRow]
    next: Optional[StationRow]


def normalize_key(value: str) -> str:
    cleaned = []
    for ch in value.lower().strip():
        if ch.isalnum() or ch.isspace():
            cleaned.append(ch)
        else:
            cleaned.append(" ")
    return " ".join("".join(cleaned).split())


def sql_escape(value: str) -> str:
    return value.replace("\\", "\\\\").replace("'", "\\'")


def haversine_km(lat1: float, lon1: float, lat2: float, lon2: float) -> float:
    radius_km = 6371.0
    d_lat = math.radians(lat2 - lat1)
    d_lon = math.radians(lon2 - lon1)
    a = (
        math.sin(d_lat / 2) ** 2
        + math.cos(math.radians(lat1))
        * math.cos(math.radians(lat2))
        * math.sin(d_lon / 2) ** 2
    )
    c = 2 * math.atan2(math.sqrt(a), math.sqrt(1 - a))
    return radius_km * c


def classify_zone(lat: float, lon: float) -> str:
    if lat < 19.02:
        return "South Mumbai"
    if lon > 72.98:
        return "Navi Mumbai"
    if lat < 19.22:
        return "Suburbs"
    return "Far Suburbs"


def fetch_json(url: str) -> List[dict]:
    req = urllib.request.Request(url, headers={"User-Agent": USER_AGENT})
    with urllib.request.urlopen(req, timeout=25) as response:
        payload = response.read().decode("utf-8")
        return json.loads(payload)


def geocode_station(station_name: str, cache: Dict[str, Tuple[float, float]]) -> Tuple[float, float]:
    key = normalize_key(station_name)
    if key in cache:
        return cache[key]

    if key in KNOWN_COORDINATES:
        cache[key] = KNOWN_COORDINATES[key]
        return cache[key]

    candidates = [station_name]
    if station_name.endswith(" Junction"):
        candidates.append(station_name.replace(" Junction", ""))
    alias_candidates = GEOCODE_ALIASES.get(key)
    if alias_candidates:
        candidates = alias_candidates + candidates

    # Preserve order but avoid duplicate geocode queries.
    unique_candidates: List[str] = []
    seen_candidates = set()
    for candidate in candidates:
        candidate_key = normalize_key(candidate)
        if candidate_key in seen_candidates:
            continue
        seen_candidates.add(candidate_key)
        unique_candidates.append(candidate)
    candidates = unique_candidates

    query_templates = [
        "{name} railway station mumbai india",
        "{name} railway station maharashtra india",
        "{name} station mumbai suburban railway",
        "{name} railway station india",
    ]

    for candidate in candidates:
        for query_template in query_templates:
            query = query_template.format(name=candidate)
            url = f"{NOMINATIM_URL}?{urllib.parse.urlencode({'q': query, 'format': 'jsonv2', 'limit': 1})}"
            try:
                rows = fetch_json(url)
            except Exception:
                rows = []

            time.sleep(REQUEST_DELAY_SECONDS)

            if not rows:
                continue

            row = rows[0]
            try:
                lat = round(float(row["lat"]), 6)
                lon = round(float(row["lon"]), 6)
            except (KeyError, ValueError, TypeError):
                continue

            cache[key] = (lat, lon)
            return cache[key]

    raise RuntimeError(f"Could not geocode station: {station_name}")


def build_station_rows() -> Tuple[List[StationRow], List[GraphRow]]:
    station_rows: List[StationRow] = []
    graph_rows: List[GraphRow] = []
    geocode_cache: Dict[str, Tuple[float, float]] = {}
    next_id = 1

    for line_name, sequence in LINES.items():
        line_station_ids: List[int] = []

        for position, station_name in enumerate(sequence, start=1):
            lat, lon = geocode_station(station_name, geocode_cache)
            zone = classify_zone(lat, lon)

            row = StationRow(
                id=next_id,
                name=station_name,
                line=line_name,
                latitude=lat,
                longitude=lon,
                sequence=position,
                zone=zone,
            )
            station_rows.append(row)
            line_station_ids.append(next_id)
            next_id += 1

        for i, station_id in enumerate(line_station_ids):
            prev_id = line_station_ids[i - 1] if i > 0 else None
            next_station_id = line_station_ids[i + 1] if i < len(line_station_ids) - 1 else None
            graph_rows.append(
                GraphRow(
                    station_id=station_id,
                    prev_station_id=prev_id,
                    next_station_id=next_station_id,
                    line=line_name,
                )
            )

    return station_rows, graph_rows


def generate_location_updates(
    connection,
    station_rows: Sequence[StationRow],
) -> List[str]:
    cursor = connection.cursor()
    cursor.execute("SELECT id, name, latitude, longitude FROM location WHERE status = 1 ORDER BY id ASC")
    locations = cursor.fetchall()
    cursor.close()

    update_sql: List[str] = []

    station_name_map: Dict[str, List[StationRow]] = {}
    line_sequence_map: Dict[Tuple[str, int], StationRow] = {}
    for station in station_rows:
        station_key = normalize_key(station.name)
        station_name_map.setdefault(station_key, []).append(station)
        line_sequence_map[(station.line, station.sequence)] = station

    station_keys_by_length = sorted(station_name_map.keys(), key=len, reverse=True)

    def nearest_from_candidates(
        candidates: Sequence[StationRow],
        lat: float,
        lon: float,
    ) -> StationRow:
        return min(candidates, key=lambda s: haversine_km(lat, lon, s.latitude, s.longitude))

    def nearest_any_station(lat: float, lon: float) -> StationRow:
        return min(station_rows, key=lambda s: haversine_km(lat, lon, s.latitude, s.longitude))

    def resolve_preferred_station(location_name: str, lat: float, lon: float) -> StationRow:
        normalized_location = normalize_key(location_name)

        for token, target_station_name in LOCATION_TO_STATION_HINTS.items():
            if token in normalized_location:
                mapped_candidates = station_name_map.get(normalize_key(target_station_name))
                if mapped_candidates:
                    return nearest_from_candidates(mapped_candidates, lat, lon)

        for station_key in station_keys_by_length:
            if station_key in normalized_location:
                return nearest_from_candidates(station_name_map[station_key], lat, lon)

        return nearest_any_station(lat, lon)

    def build_station_context(station: StationRow) -> StationContext:
        previous = line_sequence_map.get((station.line, station.sequence - 1))
        next_station = line_sequence_map.get((station.line, station.sequence + 1))
        return StationContext(station=station, previous=previous, next=next_station)

    update_sql.append("-- Location to station mapping with neighboring stations (previous/next)")

    for location_id, location_name, lat_raw, lon_raw in locations:
        if lat_raw is None or lon_raw is None:
            continue

        try:
            loc_lat = float(lat_raw)
            loc_lon = float(lon_raw)
        except (TypeError, ValueError):
            continue

        nearest_station = resolve_preferred_station(str(location_name), loc_lat, loc_lon)
        distance = haversine_km(loc_lat, loc_lon, nearest_station.latitude, nearest_station.longitude)
        context = build_station_context(nearest_station)

        prev_name = context.previous.name if context.previous is not None else "NULL"
        next_name = context.next.name if context.next is not None else "NULL"

        update_sql.append(
            "-- "
            f"{location_id}: {sql_escape(str(location_name))} -> "
            f"{sql_escape(nearest_station.name)} ({nearest_station.line}), "
            f"prev={sql_escape(prev_name)}, next={sql_escape(next_name)}, dist={distance:.3f} km"
        )
        update_sql.append(
            "UPDATE location "
            f"SET nearest_station_id = {nearest_station.id}, nearest_station_distance = {distance:.3f} "
            f"WHERE id = {int(location_id)};"
        )

    return update_sql


def build_sql(station_rows: Sequence[StationRow], graph_rows: Sequence[GraphRow], location_updates: Sequence[str]) -> str:
    station_values = []
    for row in station_rows:
        station_values.append(
            "(" + ", ".join(
                [
                    str(row.id),
                    f"'{sql_escape(row.name)}'",
                    f"'{sql_escape(row.line)}'",
                    f"{row.latitude:.6f}",
                    f"{row.longitude:.6f}",
                    str(row.sequence),
                    f"'{sql_escape(row.zone)}'",
                ]
            ) + ")"
        )

    graph_values = []
    for row in graph_rows:
        prev_value = "NULL" if row.prev_station_id is None else str(row.prev_station_id)
        next_value = "NULL" if row.next_station_id is None else str(row.next_station_id)
        graph_values.append(
            "(" + ", ".join(
                [
                    str(row.station_id),
                    prev_value,
                    next_value,
                    f"'{sql_escape(row.line)}'",
                ]
            ) + ")"
        )

    lines: List[str] = []
    lines.append("SET FOREIGN_KEY_CHECKS = 0;")
    lines.append("TRUNCATE TABLE station_graph;")
    lines.append("TRUNCATE TABLE stations;")
    lines.append("SET FOREIGN_KEY_CHECKS = 1;")
    lines.append("")

    lines.append(
        "INSERT INTO stations (id, name, line, latitude, longitude, sequence, zone) VALUES\n"
        + ",\n".join(station_values)
        + ";"
    )
    lines.append("")

    lines.append(
        "INSERT INTO station_graph (station_id, prev_station_id, next_station_id, line) VALUES\n"
        + ",\n".join(graph_values)
        + ";"
    )
    lines.append("")

    lines.append("-- Populate nearest station for each active location")
    lines.extend(location_updates)
    lines.append("")

    lines.append(
        "UPDATE projects p JOIN location l ON p.location_id = l.id "
        "SET p.proj_latitude = l.latitude, p.proj_longitude = l.longitude "
        "WHERE p.proj_latitude IS NULL;"
    )

    return "\n".join(lines) + "\n"


def apply_sql(connection, sql_text: str) -> None:
    cursor = connection.cursor()
    try:
        statements: List[str] = []
        current: List[str] = []
        in_single_quote = False
        escaped = False

        for ch in sql_text:
            current.append(ch)

            if escaped:
                escaped = False
                continue

            if ch == "\\":
                escaped = True
                continue

            if ch == "'":
                in_single_quote = not in_single_quote
                continue

            if ch == ";" and not in_single_quote:
                stmt = "".join(current).strip()
                if stmt:
                    statements.append(stmt)
                current = []

        tail = "".join(current).strip()
        if tail:
            statements.append(tail)

        for stmt in statements:
            cursor.execute(stmt)

        connection.commit()
    except Exception:
        connection.rollback()
        raise
    finally:
        cursor.close()


def main() -> None:
    parser = argparse.ArgumentParser(description="Generate and optionally apply Mumbai station seed SQL")
    parser.add_argument(
        "--output",
        default=str(Path(__file__).resolve().parent / "station_seed.sql"),
        help="Output SQL file path",
    )
    parser.add_argument(
        "--apply",
        action="store_true",
        help="Apply generated SQL directly to DB",
    )
    args = parser.parse_args()

    station_rows, graph_rows = build_station_rows()

    connection = mysql.connector.connect(**DB_CONFIG)
    try:
        location_updates = generate_location_updates(connection, station_rows)
        sql_text = build_sql(station_rows, graph_rows, location_updates)

        output_path = Path(args.output)
        output_path.parent.mkdir(parents=True, exist_ok=True)
        output_path.write_text(sql_text, encoding="utf-8")

        if args.apply:
            apply_sql(connection, sql_text)

        print(f"Stations rows: {len(station_rows)}")
        print(f"Station graph rows: {len(graph_rows)}")
        print(f"Location updates: {len(location_updates) // 2}")
        print(f"SQL written to: {output_path}")
        print(f"Applied to DB: {'yes' if args.apply else 'no'}")
    finally:
        connection.close()


if __name__ == "__main__":
    main()
