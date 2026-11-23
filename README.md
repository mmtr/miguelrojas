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

The original file you upload stays public, including any GPS coordinates in it. Remove location data before uploading (on macOS: Photos → File → Export with "Include location information" off).

## Layout

- `plugins/photos-core/`: site features that don't depend on the theme
  - `includes/details.php`: reads camera data on upload, location field, formatting
  - `blocks/photo-details/`: details list on a photo's page
  - `blocks/photo-viewer/`: full-screen viewer opened from the grid (URL updates, Esc, arrow keys, swipe, back button)
  - `editor/photo-panel.js`: "Photo details" panel in the post editor
- `themes/photos/theme.json`: colors, type, spacing
- `themes/photos/templates/`: page templates (`index` = photo grid, `single` = one photo)
- `themes/photos/parts/`: header and footer
- `themes/photos/style.css`: extra CSS

Theme files are edited live: save and reload the browser. Changes made in the Site Editor are saved to the database and take precedence over the files.
