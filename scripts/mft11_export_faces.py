#!/usr/bin/env python3
"""
MacFamilyTree 11 Face Tags & Crops Exporter
===========================================
This script extracts face tag image areas and crop rectangles from a MacFamilyTree 11
database package (*.mftpkg/Database.sqlite) and exports them to a structured faces.json
compatible with the Topland Family GEDCOM Viewer.

Usage:
    python3 mft11_export_faces.py <Path_To_Database.sqlite> <Output_File.json>

Example:
    python3 mft11_export_faces.py ~/Documents/MyTree.mftpkg/Database.sqlite storage/app/private/faces.json
"""

import sqlite3
import json
import re
import sys
import os

def parse_cocoa_rect(rect_str):
    """
    Parses Cocoa NSRect/CGRect strings:
    e.g. '{{0.245, 0.180}, {0.120, 0.155}}' or '0.245, 0.180, 0.120, 0.155'
    """
    if not rect_str or not isinstance(rect_str, str):
        return None
    nums = re.findall(r"[-+]?(?:\d*\.\d+|\d+)", rect_str)
    if len(nums) >= 4:
        try:
            return float(nums[0]), float(nums[1]), float(nums[2]), float(nums[3])
        except ValueError:
            return None
    return None

def main():
    if len(sys.argv) < 3:
        print("Usage: python3 mft11_export_faces.py <Path_To_Database.sqlite> <Output_File.json>")
        print("Example: python3 mft11_export_faces.py ~/Documents/MyTree.mftpkg/Database.sqlite faces.json")
        sys.exit(1)

    db_path = sys.argv[1]
    output_json_path = sys.argv[2]

    if not os.path.exists(db_path):
        print(f"Error: Database file not found at '{db_path}'")
        sys.exit(1)

    conn = sqlite3.connect(db_path)
    conn.row_factory = sqlite3.Row
    cursor = conn.cursor()

    # 1. Discover all tables in the SQLite database
    cursor.execute("SELECT name FROM sqlite_master WHERE type='table';")
    all_tables = [r["name"] for r in cursor.fetchall()]

    # 2. Inspect ZBASEOBJECT columns
    base_object_table = next((t for t in ["ZBASEOBJECT", "ZOBJECT", "ZENTRY"] if t in all_tables), None)
    
    objects = {}
    col_gedcom = "Z_PK"
    col_filename = "Z_PK"
    col_first = None
    col_last = None

    if base_object_table:
        cursor.execute(f"PRAGMA table_info({base_object_table});")
        obj_cols = {col["name"] for col in cursor.fetchall()}

        col_gedcom = next((c for c in ["ZGEDCOMID", "ZIDENTIFIER", "ZUNIQUEID", "ZID"] if c in obj_cols), "Z_PK")
        col_filename = next((c for c in ["ZORIGINALFILENAME", "ZFILENAME", "ZPATH", "ZRELATIVEPATH", "ZNAME"] if c in obj_cols), "Z_PK")
        col_first = next((c for c in ["ZGIVENNAME", "ZFIRSTNAME", "ZNAME"] if c in obj_cols), None)
        col_last = next((c for c in ["ZSURNAME", "ZLASTNAME"] if c in obj_cols), None)

        cursor.execute(f"SELECT * FROM {base_object_table};")
        objects = {row["Z_PK"]: dict(row) for row in cursor.fetchall()}

    # 3. Pull face-tagged relationships directly
    query = """
    SELECT 
        a.Z_PK AS assignment_pk,
        a.ZRECTSTRING AS rect_str,
        COALESCE(r.ZBASEMEDIA, r.Z16_BASEMEDIA) AS media_pk,
        COALESCE(r.ZMEDIACONTAINER, r.Z7_MEDIACONTAINER) AS container_pk
    FROM ZBASEMEDIARELATIONASSIGNMENT a
    LEFT JOIN ZMEDIARELATION r ON (
        a.ZMEDIARELATION = r.Z_PK 
        OR r.ZMEDIARELATIONASSIGNMENT = a.Z_PK
        OR r.Z2_MEDIARELATIONASSIGNMENT = a.Z_PK
    )
    WHERE a.ZRECTSTRING IS NOT NULL AND a.ZRECTSTRING != ''
    """

    try:
        cursor.execute(query)
        rows = cursor.fetchall()
    except sqlite3.OperationalError as e:
        print(f"Error querying ZBASEMEDIARELATIONASSIGNMENT / ZMEDIARELATION: {e}")
        conn.close()
        sys.exit(1)

    manifest = {
        "by_person": {},
        "by_media": {},
        "all_tags": []
    }

    count = 0
    for row in rows:
        rect = parse_cocoa_rect(row["rect_str"])
        if not rect:
            continue

        raw_x, raw_y, w, h = rect
        if w <= 0 or h <= 0:
            continue

        container_pk = row["container_pk"]
        media_pk = row["media_pk"]

        person_obj = objects.get(container_pk, {}) if container_pk else {}
        media_obj = objects.get(media_pk, {}) if media_pk else {}

        # Person identifier
        person_id = str(person_obj.get(col_gedcom) or container_pk or "").strip("@")
        first = person_obj.get(col_first, "") if col_first else ""
        last = person_obj.get(col_last, "") if col_last else ""
        person_name = f"{first or ''} {last or ''}".strip()

        # Media identifier
        media_id = str(media_obj.get(col_gedcom) or media_pk or "").strip("@")
        filename = ""
        if col_filename in media_obj and media_obj[col_filename]:
            filename = os.path.basename(str(media_obj[col_filename]))
        elif media_id:
            filename = media_id

        # Convert macOS Core Graphics coordinates (origin bottom-left) to Web CSS (origin top-left)
        web_y = round(max(0.0, 1.0 - (raw_y + h)), 4)
        web_x = round(max(0.0, raw_x), 4)
        web_w = round(min(1.0, w), 4)
        web_h = round(min(1.0, h), 4)

        tag = {
            "person_id": person_id,
            "person_name": person_name,
            "media_id": media_id,
            "filename": filename,
            "crop": {
                "x": web_x,
                "y": web_y,
                "width": web_w,
                "height": web_h
            },
            "css": {
                "left": f"{web_x * 100:.2f}%",
                "top": f"{web_y * 100:.2f}%",
                "width": f"{web_w * 100:.2f}%",
                "height": f"{web_h * 100:.2f}%"
            }
        }

        if person_id:
            manifest["by_person"].setdefault(person_id, []).append(tag)
        if filename:
            manifest["by_media"].setdefault(filename, []).append(tag)
        manifest["all_tags"].append(tag)
        count += 1

    # Ensure target directory exists
    output_dir = os.path.dirname(output_json_path)
    if output_dir and not os.path.exists(output_dir):
        os.makedirs(output_dir, exist_ok=True)

    with open(output_json_path, "w", encoding="utf-8") as f:
        json.dump(manifest, f, indent=2, ensure_ascii=False)

    print(f"Done! Successfully exported {count} face tag(s) to '{output_json_path}'.")
    conn.close()

if __name__ == "__main__":
    main()
