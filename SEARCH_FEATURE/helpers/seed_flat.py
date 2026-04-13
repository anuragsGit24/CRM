import mysql.connector
import random
from datetime import datetime

# =========================
# DB CONFIG
# =========================
conn = mysql.connector.connect(
    host="localhost",
    user="root",
    password="",
    database="real_estate_db"
)

cursor = conn.cursor()

# =========================
# FETCH PROJECT IDS
# =========================
cursor.execute("SELECT id FROM projects")
project_ids = [x[0] for x in cursor.fetchall()]

if not project_ids:
    raise Exception("❌ No projects found")

# =========================
# HELPERS
# =========================
def rand_phone():
    return random.randint(9000000000, 9999999999)

def calculate_charges(base_price):
    gst = int(base_price * 0.05)
    stamp = int(base_price * 0.06)
    reg = int(base_price * 0.01)
    dev = random.randint(50000, 200000)
    parking = random.randint(100000, 500000)
    other = random.randint(20000, 100000)

    total = base_price + gst + stamp + reg + dev + parking + other

    return gst, stamp, reg, dev, parking, other, total

# =========================
# INSERT QUERY
# =========================
query = """
INSERT INTO flat (
type, projects_id, properties_id, bathroom_count, balconies,
transaction_type, flat_type_id, booking_amount, base_price,
rate_per_sqft, gst, stampduty, registration,
developement_charges, car_parking, other_charges, total_charge,
door_facing_direction, carpet_area, builtup_area,
construction, budget_threshold, note, status,
created_on, modified_on, property_type, facing, remark
)
VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
"""

# =========================
# GENERATE FLATS
# =========================
for i in range(3000):

    project_id = random.choice(project_ids)

    bhk = random.choice(["1 BHK", "2 BHK", "3 BHK"])
    is_rent = random.random() < 0.5

    if is_rent:
        base_price = random.randint(15000, 80000)
        rate = random.randint(20, 60)
    else:
        base_price = random.randint(5000000, 20000000)
        rate = random.randint(8000, 25000)

    gst, stamp, reg, dev, parking, other, total = calculate_charges(base_price)

    carpet = random.randint(350, 1200)
    builtup = int(carpet * 1.2)

    cursor.execute(query, (
        bhk,                           # type
        project_id,                    # projects_id
        random.randint(1, 1000),       # properties_id
        str(random.randint(1, 3)),     # bathroom_count
        random.randint(0, 3),          # balconies
        "rent" if is_rent else "sale",
        random.randint(1, 5),          # flat_type_id
        int(base_price * 0.1),         # booking_amount
        base_price,
        rate,
        gst,
        stamp,
        reg,
        dev,
        parking,
        other,
        total,
        random.choice(["East", "West", "North", "South"]),
        str(carpet),
        str(builtup),
        random.choice(["RCC", "Brick"]),
        random.choice(["Budget", "Mid", "Premium"]),
        "Well designed flat with good ventilation",
        1,
        datetime.now(),
        datetime.now(),
        random.randint(1, 3),
        random.randint(1, 8),
        "Good investment opportunity"
    ))

conn.commit()
cursor.close()
conn.close()

print("✅ 3000 FULLY FILLED flats inserted successfully")