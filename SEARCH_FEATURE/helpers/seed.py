import mysql.connector
import random
from datetime import datetime, timedelta

# =========================
# 🔹 DB CONFIG
# =========================
conn = mysql.connector.connect(
    host="localhost",
    user="root",
    password="",
    database="real_estate_db"
)
cursor = conn.cursor()

# =========================
# 🔹 HELPERS
# =========================
def rand_date(start=-1000, end=1000):
    return datetime.now() + timedelta(days=random.randint(start, end))

def rand_phone():
    return random.randint(9000000000, 9999999999)

areas = ["Vikhroli", "Powai", "Andheri", "Thane", "Mulund", "Borivali", "Kanjurmarg", "Ghatkopar"]
project_suffix = ["Heights", "Residency", "Greens", "Skyline", "Tower", "Grande", "Urbania"]

amenities_list = [
    "Gym,Swimming Pool,Clubhouse",
    "Garden,Kids Play Area,Parking",
    "Gym,Parking,Security",
    "Pool,Gym,Jogging Track"
]

# Prefer exact coordinates tied to each locality.
# If the database already has latitude/longitude values, those are used first.
# Otherwise, these coordinates are written back to the location table and reused for projects.
LOCATION_COORDINATES = {
    "Vikhroli": (19.107550, 72.921200),
    "Powai": (19.117600, 72.905400),
    "Andheri": (19.113600, 72.869700),
    "Thane": (19.218300, 72.978100),
    "Mulund": (19.172600, 72.956200),
    "Borivali": (19.230700, 72.856700),
    "Kanjurmarg": (19.131000, 72.934000),
    "Ghatkopar": (19.089600, 72.908900),
}


def resolve_coordinates(location_name, latitude=None, longitude=None):
    """Return a stable latitude/longitude pair for a location."""
    try:
        if latitude is not None and longitude is not None:
            lat_value = float(latitude)
            lng_value = float(longitude)
            if lat_value != 0.0 and lng_value != 0.0:
                return lat_value, lng_value
    except (TypeError, ValueError):
        pass

    fallback = LOCATION_COORDINATES.get(location_name)
    if fallback:
        return fallback

    return None, None

# =========================
# 🔹 FETCH FK
# =========================
cursor.execute("SELECT id, name, latitude, longitude FROM location WHERE status = 1")
raw_locations = cursor.fetchall()

locations = []
for loc_id, loc_name, loc_lat, loc_lng in raw_locations:
    resolved_lat, resolved_lng = resolve_coordinates(loc_name, loc_lat, loc_lng)
    if resolved_lat is None or resolved_lng is None:
        raise Exception(f"❌ Missing coordinates for location: {loc_name}")

    # Keep the database aligned with the exact coordinates used for seeding.
    if loc_lat is None or loc_lng is None or float(loc_lat) == 0.0 or float(loc_lng) == 0.0:
        cursor.execute(
            "UPDATE location SET latitude = %s, longitude = %s WHERE id = %s",
            (resolved_lat, resolved_lng, loc_id)
        )

    locations.append((loc_id, loc_name, resolved_lat, resolved_lng))

cursor.execute("SELECT id FROM builder")
builders = [x[0] for x in cursor.fetchall()]

if not locations or not builders:
    raise Exception("❌ Seed location and builder first")

# =========================
# 🔥 1. PROJECTS (500)
# =========================
project_ids = []

project_query = """
INSERT INTO projects (
name, brief, approved_by, amenities, signification, connectivity,
project_status, launch_date, status, website_address, logo_name,
pdf_upload, project_description, rera_no, no_of_tower, site_address,
sale_address, offer, landmark, overlooking, possession_date,
flat_configuration, project_segment, type, ticket_size, total_area,
structure, location_id, builder_id, work_stage_id, total_unit,
unit_to_be_sold, concern_person, concern_no, mobile_view_expiry,
google_map, google_link, created_on, modified_on, header_image,
footer_image, whatsapp_brief, latlong, whatsapp_top,
whatsapp_middle, whatsapp_bottom, tc, package_id,
api_show_price, bd, rank, description_app,
signification_app, show_carpet, builder_commit_date
)
VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,
%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,
%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
"""

for i in range(1, 501):

    loc_id, loc_name, loc_lat, loc_lng = random.choice(locations)
    builder_id = random.choice(builders)

    name = f"{loc_name} {random.choice(project_suffix)} {i}"

    data = (
        name,
        f"{name} offers modern living",
        "BMC Approved",
        random.choice(amenities_list),
        "Great investment opportunity",
        "Close to metro and highways",
        random.choice(["Under Construction", "Ready To Move"]),
        rand_date(-1500, -500),
        1,
        "https://example.com",
        "logo.png",
        "brochure.pdf",
        "Detailed description of project",
        f"RERA{10000+i}",
        str(random.randint(1, 10)),
        f"{loc_name}, Mumbai",
        f"{loc_name} Sales Office",
        "Limited time offer",
        f"Near {loc_name} Station",
        random.choice(["Garden View", "City View"]),
        rand_date(200, 1500),
        "1 BHK,2 BHK,3 BHK",
        str(random.randint(1, 3)),
        str(random.randint(1, 5)),
        str(random.randint(1, 5)),
        f"{random.randint(1,10)} Acres",
        "RCC",
        loc_id,
        builder_id,
        1,
        random.randint(100, 5000),
        random.randint(0, 1000),
        f"Agent {i}",
        rand_phone(),
        rand_date(100, 500),
        f"https://www.google.com/maps/search/?api=1&query={loc_lat:.6f},{loc_lng:.6f}",
        f"https://www.google.com/maps/search/?api=1&query={loc_lat:.6f},{loc_lng:.6f}",
        datetime.now(),
        datetime.now(),
        "header.jpg",
        "footer.jpg",
        "Whatsapp summary",
        f"{loc_lat:.6f},{loc_lng:.6f}",
        "Top msg",
        "Middle msg",
        "Bottom msg",
        "Terms",
        1,
        1,
        random.randint(1, 10),
        random.randint(1, 3),
        "App desc",
        "App signification",
        1,
        rand_date(300, 1500)
    )

    cursor.execute(project_query, data)
    project_ids.append(cursor.lastrowid)

conn.commit()
print("✅ Projects inserted")

# =========================
# 🔥 2. FLATS (3000)
# =========================

flat_query = """
INSERT INTO flat (
type, projects_id, bathroom_count, balconies,
transaction_type, base_price, total_charge,
carpet_area, status, created_on
)
VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
"""

for i in range(3000):
    project_id = random.choice(project_ids)

    bhk = random.choice(["1 BHK", "2 BHK", "3 BHK"])
    is_rent = random.random() < 0.5

    if is_rent:
        price = random.randint(15000, 80000)
    else:
        price = random.randint(5000000, 20000000)

    cursor.execute(flat_query, (
        bhk,
        project_id,
        str(random.randint(1, 3)),
        random.randint(0, 3),
        "rent" if is_rent else "sale",
        price,
        price + random.randint(50000, 200000),
        str(random.randint(300, 1200)),
        1,
        datetime.now()
    ))

conn.commit()
print("✅ Flats inserted")

# =========================
# 🔥 3. LOCATION ALIASES (150)
# =========================

alias_query = """
INSERT INTO location_aliases (alias, location_id)
VALUES (%s,%s)
"""

for loc_id, loc_name in locations:
    for suffix in ["east", "west", "near metro", "station", "area"]:
        cursor.execute(alias_query, (f"{loc_name.lower()} {suffix}", loc_id))

conn.commit()
print("✅ Aliases inserted")

# =========================
# DONE
# =========================
cursor.close()
conn.close()

print("🚀 FULL DATA GENERATED SUCCESSFULLY")