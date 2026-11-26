<p>
  <a href="https://miguelrojas.photo">
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset="brand/png/lockup-white.png">
      <img src="brand/png/lockup-black.png" alt="Miguel Rojas – Capturing the beauty of a moment" width="480">
    </picture>
  </a>
</p>

# Miguel Rojas

The code behind **[miguelrojas.photo](https://miguelrojas.photo)**, Miguel Rojas's photography site: a WordPress block theme (`themes/photos`) and a plugin (`plugins/photos-core`), hosted on WordPress.com.

- A full-bleed grid of photos, newest first, that loads more as you scroll.
- A full-screen viewer with the camera settings, location and date read from each photo, plus likes and comments (Jetpack).
- Albums, a logo drawn in code, and a watermark that matches it.

The photos and the posts themselves live in WordPress on the site, not in this repository.

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

## Going live

Hosted on WordPress.com (Personal plan) at [miguelrojas.photo](https://miguelrojas.photo). How it was set up: [docs/go-live.md](docs/go-live.md). `npm run package` builds the theme and plugin zips for uploading.

### Deploying updates

```bash
npm run deploy
```

Packages `themes/photos` and `plugins/photos-core` and installs them on the live site over SSH (WP-CLI), replacing the previous version. It deploys what's committed: with uncommitted changes in `themes/` or `plugins/` it stops, unless you run `npm run deploy -- --allow-dirty`. Content, photos and settings on the live site aren't touched.

Needs SSH enabled for the site with your key attached (WordPress.com → Settings → SFTP/SSH). Local settings go in `.deploy.env` (copy `.deploy.env.example`): the SSH host, and optionally which key to use.

## Publishing a photo

Create a post, set the photo as its **featured image**, write the description in the post body and fill in **Location** in the "Photo details" sidebar panel (or on the image in the Media Library: the post's field shows it when left empty, and wins when filled). Camera, lens, focal length, aperture, shutter speed, ISO and date taken are read from the photo file on upload. The grid shows photos newest-published first: leave the post date as the day you publish it (the date taken shown with the photo comes from the file, not the post date).

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

- Full logo (name, slogan, viewfinder corners): `brand/svg/lockup-black.svg` / `lockup-white.svg`, and PNGs in `brand/png/`.
- Site Icon: upload `brand/png/site-icon-512.png` in WordPress (until then the theme uses `assets/favicon.svg`).
- The header shows the logo through the theme's "Miguel Rojas Logo" block; "Rojas" uses the theme's Accent color.

## Watermark

Photos carry the name in red viewfinder corners (`brand/png/watermark-viewfinder.png`) in the bottom-right corner, at 80% opacity, about a quarter of the photo's shorter side wide.

- From the command line: `swift bin/prepare-photos.swift --out photos/web photos/*.jpg` resizes, watermarks and keeps the camera data (`--no-watermark` for photos already watermarked).
- From Lightroom Classic (Edit Watermarks): **Graphic** with that PNG, Opacity **80**, Size **Proportional 9**, Inset **3** / **3**, Anchor **bottom-right**. Checked against the script on portrait photos.

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
