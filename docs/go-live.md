# Go-live checklist

Decided on 2026-11-23:

- **Site name:** Miguel Rojas
- **Domain:** `miguelrojas.photo` (unregistered when checked on 2026-11-23; register it at a registrar separate from the host, so the host can change later)
- **Hosting:** WordPress.com **Personal** plan (6 GB storage). Upgrade to Premium (13 GB) or Business (50 GB, staging site) when needed; upgrading keeps everything.

Content (photos, posts, albums, settings) lives in WordPress's database, not in git, so it's created on the live site. Git carries the theme and plugin.

## 1. Buy and connect

- [ ] Register `miguelrojas.photo`.
- [ ] Create the WordPress.com site on the Personal plan (yearly billing is cheaper).
- [ ] Connect the domain to the site as a domain you already own, following WordPress.com's steps for the DNS records.
- [ ] Settings → Reading → Site Visibility → **Coming Soon**, until launch.

## 2. Developer access

- [ ] In the Hosting Dashboard, **Activate hosting features** (one time).
- [ ] Settings → SFTP/SSH: create credentials and enable SSH (host `ssh.wp.com`, port 22).

WordPress.com's own pages disagree on whether SFTP, SSH and GitHub Deployments are on Personal (the plan pages and deployment guide say yes; an older SFTP page says Business only). Step 3 works either way.

## 3. Install the theme and plugin

Either:

- **GitHub Deployments** (if the dashboard offers it): push this repo to GitHub and deploy it to `/wp-content`, since the repo's `themes/` and `plugins/` folders mirror it. Only those two folders should go up (to be set up before the first deployment).

Or, on any paid plan:

- [ ] Run `npm run package`, then upload `dist/photos-theme.zip` (Appearance → Themes → Add New → Upload) and `dist/photos-core.zip` (Plugins → Add New → Upload Plugin). Repeat to update.

Then:

- [ ] Activate the **Photos** theme and the **Photos Core** plugin.

## 4. Configure WordPress

- [ ] Settings → General: Site Title **Miguel Rojas**.
- [ ] Settings → Permalinks: **Post name** (`/%postname%/`), and save (also makes `/albums/` work).
- [ ] Settings → Reading: show **24** posts per page.
- [ ] Site Icon: upload `brand/png/site-icon-512.png`.
- [ ] Jetpack → Settings → Writing: turn **off** "Display images in a full-screen carousel gallery". Otherwise Jetpack's carousel takes over clicks on album photos instead of the site's viewer.
- [ ] Delete the default content: Sample Page and the "Hello world!" post.
- [ ] Check: home grid, the Albums link, a photo's viewer and an album page.

## 5. Add photos

- [ ] Export from Lightroom (or similar): long edge about 3000 px, JPEG quality around 80, sRGB, **location data removed**, camera data (EXIF) kept, watermark as in the README's Watermark section.
- [ ] A single photo: a post with the photo as its featured image, the description as text, and **Location** in the "Photo details" panel.
- [ ] An album: Albums → Add New Album, photos in the gallery, captions as descriptions, **Location** in "Album details".

## 6. Launch

- [ ] Settings → Reading → Site Visibility → **Public**.
