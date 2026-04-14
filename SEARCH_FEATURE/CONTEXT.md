# Project Context: Real Estate CRM Search Engine API

## Project Overview
Building a production-ready REST API search engine for a Real Estate CRM.
Tech stack: PHP 8+ (procedural or OOP), MySQL (MariaDB 10.11), XAMPP (localhost).
No frameworks. Pure PHP with PDO. JSON API consumed by native mobile apps (iOS/Android) and web.

## Database
Host: localhost
DB Name: webprjlw_cpbroadcast_smcp
User: root
Password: (empty on XAMPP default)
Engine: MariaDB 10.11 / InnoDB

## Tables and Their Purpose

### location
Stores Mumbai localities. Key columns:
- id, name, status (1=active), latitude, longitude
- near_locs (comma-separated neighbouring area names)
- dist_range (radius in km for GPS search)
- FULLTEXT index on (name, near_locs)

### location_aliases
Maps nicknames/misspellings to canonical location_id.
- alias (varchar), location_id (FK to location.id)
- Example: "vk east", "vikhroli e" → location_id = 1 (Vikhroli)

### builder
Stores builder/developer info.
- id, name, status (1=active), brief, logo

### projects
Core listings table. Key columns:
- id, name, status (1=active), rank (lower = higher priority)
- location_id (FK to location.id)
- builder_id (FK to builder.id)
- project_status: "Under Construction", "Ready To Move", "Upcoming"
- project_segment: '1'=Affordable, '2'=Luxury, '3'=Ultra Luxury, '4'=Value
- type: '1'=New, '2'=Exclusive, '3'=Trending, '4'=Ready to Move, '5'=Under Construction, '6'=Upcoming
- ticket_size: '1'=30-50L, '2'=50-80L, '3'=80L-1cr, '4'=1-1.5cr
- possession_date (DATE), launch_date (DATE)
- landmark (MEDIUMTEXT) — important for keyword search
- flat_configuration (varchar) — e.g. "1 BHK,2 BHK,3 BHK"
- header_image, logo_name
- latlong (varchar), google_map, google_link
- FULLTEXT index on (name, site_address, landmark, amenities, flat_configuration)

### flat
Individual unit configurations per project. Key columns:
- id, projects_id (FK to projects.id), status (1=active)
- type (varchar): "1 BHK", "2 BHK", "3 BHK", "4 BHK", "Studio"
- transaction_type (varchar): "Buy", "Rent", "Lease", "Resale"
- base_price (bigint), total_charge (bigint)
- carpet_area (varchar), builtup_area (varchar)
- bathroom_count, balconies
- property_type: 1=flat, 2=office space, 3=shop
- booking_amount, rate_per_sqft

### search_logs
Analytics table.
- raw_query, parsed_output (JSON), result_count
- platform (web/android/ios), user_id, geo_lat, geo_lng
- created_on

## Folder Structure
/api
  /config
    database.php        ← PDO singleton connection
    constants.php       ← BHK map, segment map, transaction map
  /services
    QueryParser.php     ← raw text string → structured array
    LocationResolver.php ← raw location string → location_id via aliases
    SearchQueryBuilder.php ← structured array → SQL + params array
    SearchLogger.php    ← writes to search_logs table
  /endpoints
    search.php          ← POST /api/endpoints/search.php
    suggest.php         ← GET /api/endpoints/suggest.php
  /helpers
    Response.php        ← standard JSON response wrapper
    Sanitizer.php       ← input cleaning and validation

## Core Search Flow
1. Client sends POST to search.php with { "query": "1 bhk vikhroli under 1 cr" }
2. Sanitizer cleans input
3. QueryParser extracts: bhk, transaction_type, min_budget, max_budget, 
   project_segment, possession_status, raw_location_string, geo_intent flag
4. LocationResolver resolves raw_location_string → location_id 
   (checks aliases first, then location.name exact, then LIKE)
5. SearchQueryBuilder builds parameterized SQL joining all 4 tables
6. PDO executes query with pagination (default 20 per page)
7. SearchLogger logs to search_logs (non-blocking)
8. Response::success() returns standard JSON envelope

## Standard API Response Format
{
  "status": "success",
  "query_interpreted": {
    "bhk": "1 BHK",
    "location": "Vikhroli",
    "max_budget": 10000000,
    "transaction_type": "Buy"
  },
  "is_relaxed": false,
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total_count": 43,
    "total_pages": 3
  },
  "data": [
    {
      "project_id": 1,
      "project_name": "Lodha Vikhroli Heights",
      "builder_name": "Lodha Group",
      "location_name": "Vikhroli",
      "flat_type": "1 BHK",
      "base_price": 8500000,
      "total_charge": 9800000,
      "carpet_area": "435 sqft",
      "project_status": "Under Construction",
      "possession_date": "2026-12-31",
      "header_image": "lodha_vikhroli.jpg",
      "rera_no": "P51900038217",
      "project_segment": "2",
      "latitude": 19.107550,
      "longitude": 72.921200,
      "rank": 1
    }
  ]
}

## Parsing Rules (QueryParser must handle these)
- BHK: "1 bhk", "2bhk", "3 bedroom", "studio" → maps to flat.type values
- Transaction: "rent", "buy", "sale", "resale", "lease" → flat.transaction_type
- Budget: "25k"→25000, "1.5 lakh"→150000, "1 cr"→10000000
  Context: "under/below/upto/max" = max_budget, "above/minimum/min" = min_budget
- Segment: "luxury"→'2', "affordable"→'1', "ultra luxury"→'3', "value"→'4'
- Possession: "ready to move"/"rtm" → project_status = 'Ready To Move'
             "under construction"/"uc" → project_status = 'Under Construction'
- Location: everything left after above extractions = raw_location_string
- GPS: "near me"/"nearby"/"close to me" → geo_intent = true, skip location extraction

## Query Builder SQL Pattern
Base (always):
SELECT p.id, p.name, p.project_status, p.possession_date, p.header_image,
       p.rera_no, p.project_segment, p.rank, p.landmark,
       b.name AS builder_name,
       l.name AS location_name, l.latitude, l.longitude,
       f.type AS flat_type, f.base_price, f.total_charge, f.carpet_area
FROM projects p
JOIN location l ON p.location_id = l.id
JOIN flat f ON f.projects_id = p.id
JOIN builder b ON p.builder_id = b.id
WHERE p.status = 1 AND f.status = 1

Dynamic conditions added:
- location_id resolved → AND p.location_id = ?
- location_id NOT resolved but string exists → AND MATCH(p.name, p.site_address, p.landmark, p.amenities) AGAINST(? IN BOOLEAN MODE)
- bhk → AND f.type = ?
- transaction_type → AND f.transaction_type = ?
- max_budget → AND f.base_price <= ?
- min_budget → AND f.base_price >= ?
- project_segment → AND p.project_segment = ?
- possession filter → AND p.project_status = ?

ORDER BY:
- If fulltext used: ORDER BY relevance DESC, p.rank ASC
- Else: ORDER BY p.rank ASC, p.possession_date ASC

LIMIT ? OFFSET ?  (always paginated, default 20 per page)

## Zero Results Fallback
If COUNT = 0:
  → Remove max_budget filter, re-run
If still 0:
  → Also remove bhk filter, re-run
  → Set is_relaxed: true in response

## Suggest Endpoint
GET /suggest.php?q=vikhr&user_id=123
- Minimum 3 characters
- Query: location.name LIKE '%q%' UNION location_aliases.alias LIKE '%q%'
- Return max 8 results
- Must respond under 100ms
- No search logging needed

## Production Rules
- All DB values via PDO prepared statements, never string concatenation
- All responses via Response::success() or Response::error()
- Input always through Sanitizer before use
- Rate limit: 30 requests/min/IP (track in DB or APCu)
- CORS headers on every endpoint
- Never expose raw DB errors to client
- Max query string: 200 characters
- Default pagination: 20 per page, max 50