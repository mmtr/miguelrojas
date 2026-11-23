# Photos

Personal photography site built on WordPress, with a custom block theme in `themes/photos`.

## Local development

Requires Docker Desktop (running) and Node 22+.

```bash
npm install
npm start        # starts WordPress at http://localhost:8888
npm stop
```

Admin: http://localhost:8888/wp-admin — wp-env's default local login is `admin` / `password`.

`npm start` also runs `npm run setup`, which activates the theme, sets clean URLs (`/%postname%/`) and shows 24 photos per page.

Other commands:

- `npm run wp -- <command>` runs WP-CLI, e.g. `npm run wp -- post list`
- `npm run logs` shows container logs
- `npm run reset` wipes the local database and uploads (destructive)

## Publishing a photo

Create a post, set the photo as its **featured image**, write the description in the post body and fill in **Location** in the "Photo details" sidebar panel. Camera, lens, focal length, aperture, shutter speed, ISO and date taken are read from the photo file on upload.

## Publishing an album

**Albums → Add New Album**, then drop your photos into the gallery (new albums start with one). Text above or below the gallery becomes the album's introduction, and each image's caption is shown with it in the viewer. Set the album's **Location** in the "Album details" panel. A photo taken somewhere else can have its own location: select it in the Media Library and fill in **Location**.

The home grid shows the album's cover (the featured image, or the first photo if none is set) with its title and photo count. The photos themselves only appear on the album page (`/albums/<name>/`); all albums are listed at `/albums/`. A link to one photo is the album URL plus `#photo-<image id>`, which is what the address bar shows while viewing it.

The original file you upload stays public, including any GPS coordinates in it. Remove location data before uploading (on macOS: Photos → File → Export with "Include location information" off).

## Logo

The Miguel Rojas logo is drawn from simple geometry by `brand/build.py` (no font involved). Change the letter shapes, weight or red there, then rebuild:

```bash
python3 brand/build.py        # SVGs in brand/svg/, plus the theme's logo.svg and favicon.svg
python3 brand/export_png.py   # PNGs in brand/png/ (uses Google Chrome to render)
```

- Watermark for Lightroom/Photoshop: `brand/png/watermark-white-shadow.png` (or `watermark-white.png` / `watermark-black.png`).
- Site Icon: upload `brand/png/site-icon-512.png` in WordPress on the live site (until then the theme uses `assets/favicon.svg`).
- The header shows the logo through the theme's "Miguel Rojas Logo" block; "Rojas" uses the theme's Accent color.

## Layout

- `plugins/photos-core/`: site features that don't depend on the theme
  - `includes/details.php`: reads camera data on upload, location field, formatting
  - `includes/albums.php`: album post type, home grid mixing, cover fallback, Media Library location field
  - `blocks/photo-details/`: details list on a photo's page
  - `blocks/photo-viewer/`: full-screen viewer for grid and album photos (URL updates, Esc, arrow keys, swipe, back button)
  - `blocks/entry-label/`: album title and photo count over album covers in the grid
  - `blocks/album-summary/`: "7 photos · Location · Month Year" line on album pages
  - `editor/photo-panel.js`: "Photo details" / "Album details" panel in the editor
- `themes/photos/theme.json`: colors, type, spacing
- `themes/photos/templates/`: page templates (`index` = photo grid, `single` = one photo, `single-album` = album page)
- `themes/photos/parts/`: header and footer
- `themes/photos/style.css`: extra CSS

Theme files are edited live: save and reload the browser. Changes made in the Site Editor are saved to the database and take precedence over the files.
