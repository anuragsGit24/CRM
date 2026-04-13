import mysql.connector
from datetime import datetime
import random

conn = mysql.connector.connect(
    host="localhost",
    user="root",
    password="",
    database="real_estate_db"
)

cursor = conn.cursor()

# 🔥 REAL BUILDERS LIST (India / Mumbai)
builders = [
    ("Lodha Group", "Mumbai"),
    ("Godrej Properties", "Mumbai"),
    ("Oberoi Realty", "Mumbai"),
    ("Hiranandani Developers", "Mumbai"),
    ("Kalpataru Group", "Mumbai"),
    ("Runwal Group", "Mumbai"),
    ("Piramal Realty", "Mumbai"),
    ("Mahindra Lifespaces", "Mumbai"),
    ("Rustomjee Group", "Mumbai"),
    ("Kanakia Spaces", "Mumbai"),
    ("Ajmera Realty", "Mumbai"),
    ("Shapoorji Pallonji Real Estate", "Mumbai"),
    ("Wadhwa Group", "Mumbai"),
    ("Sunteck Realty", "Mumbai"),
    ("Raymond Realty", "Mumbai"),
    ("Prestige Group", "Bangalore"),
    ("Brigade Group", "Bangalore"),
    ("Sobha Limited", "Bangalore"),
    ("DLF Limited", "Delhi"),
    ("Tata Housing", "Mumbai"),
    ("Kolte Patil Developers", "Pune"),
    ("Godrej Urban", "Mumbai"),
    ("Raheja Universal", "Mumbai"),
    ("Indiabulls Real Estate", "Mumbai"),
    ("JP Infra", "Mumbai"),
    ("Paranjape Schemes", "Pune"),
    ("Omkar Realtors", "Mumbai"),
    ("RNA Corp", "Mumbai"),
    ("Neelkanth Group", "Mumbai"),
    ("Sheth Creators", "Mumbai"),
    ("Hubtown Limited", "Mumbai"),
    ("Marathon Group", "Mumbai"),
    ("Nahar Group", "Mumbai"),
    ("Ekta World", "Mumbai"),
    ("Adani Realty", "Mumbai"),
    ("K Raheja Corp", "Mumbai"),
    ("MICL Group", "Mumbai"),
    ("Chandak Group", "Mumbai"),
    ("Ashwin Sheth Group", "Mumbai"),
    ("Crescent Group", "Mumbai")
]

query = """
INSERT INTO builder (
name, address, mobile_no, email, projects_id, status,
commission, sole_selling, brief, logo, file_type,
saleprosys, created_on, modified_on,
auto_cp_activation, insta_url, facebook_url, linkdin_url
)
VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
"""

for i, (name, city) in enumerate(builders, start=1):

    data = (
        name,
        f"{name}, {city}, India",
        random.randint(9000000000, 9999999999),
        name.lower().replace(" ", "") + "@example.com",
        None,  # projects_id (can remain NULL)
        1,
        f"{random.randint(1,5)}%",
        random.randint(0,1),
        f"{name} is a reputed real estate developer known for quality construction.",
        name.lower().replace(" ", "_") + ".png",
        "image/png",
        random.randint(0,1),
        datetime.now(),
        datetime.now(),
        random.randint(0,1),
        f"https://instagram.com/{name.replace(' ', '').lower()}",
        f"https://facebook.com/{name.replace(' ', '').lower()}",
        f"https://linkedin.com/company/{name.replace(' ', '').lower()}"
    )

    cursor.execute(query, data)

conn.commit()
cursor.close()
conn.close()

print("✅ 40 REAL builders inserted successfully")