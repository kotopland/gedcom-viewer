# MacFamilyTree 11 Face Tags & Crops Exporter

This tool extracts face tag coordinates and crop areas directly from your MacFamilyTree 11 database package (`.mftpkg`) and generates a structured `faces.json` file for the Topland Family GEDCOM Viewer.

---

## 1. Where is the MacFamilyTree Database located?

MacFamilyTree 11 stores your family tree data inside an `.mftpkg` package (which is a macOS folder bundle).

Typically, this is saved in your macOS user directory:
- **Default Location**:
  `~/Documents/My Family Tree.mftpkg`
  or wherever you saved your `.mftpkg` file on your Mac.
- **Inside the package**:
  Right-click on your `.mftpkg` file in Finder and select **"Show Package Contents"**.
  Inside, you will find `Database.sqlite`.

---

## 2. Running the Exporter Script

Open Terminal on your Mac and run:

```bash
# Basic syntax:
python3 scripts/mft11_export_faces.py "/path/to/YourTree.mftpkg/Database.sqlite" "storage/app/private/faces.json"

# Example if your tree is in Documents:
python3 scripts/mft11_export_faces.py ~/Documents/*.mftpkg/Database.sqlite storage/app/private/faces.json
```

The script will:
1. Connect to MacFamilyTree 11's internal SQLite database.
2. Query `ZBASEMEDIARELATIONASSIGNMENT` and `ZMEDIARELATION` for face tags.
3. Automatically convert macOS Core Graphics coordinates (bottom-left origin) to web CSS top-left coordinates.
4. Export a formatted `faces.json` indexed `by_person`, `by_media`, and `all_tags`.

---

## 3. Applying the Exported Faces to the Web App

Once `faces.json` is generated:
1. Place it directly at `storage/app/private/faces.json` in this project.
2. Alternatively, log into the **Admin Dashboard** (`/dashboard`) and upload `faces.json` using the **"Upload Face Tags (faces.json)"** card.
3. Click **"Re-import / Refresh"** or upload your updated `.ged` file. All portraits and face tags will be immediately active.
