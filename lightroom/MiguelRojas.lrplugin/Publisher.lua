--[[
Publishing one rendered photo: upload the image, then create or update its post.

A new photo becomes a post with the image as its featured image, Lightroom's Title as the
title, the Caption as the description and the IPTC Sublocation, City and Country as the
Location. Republishing uploads the new rendering under a new file name, points the post
at it and deletes the previous upload; the post keeps its date, address, likes and
comments.
]]

local LrTasks = import 'LrTasks'

local WordPress = require 'WordPress'

local Publisher = {}

local function field( photo, key )
	local ok, value = LrTasks.pcall( function()
		return photo:getFormattedMetadata( key )
	end )
	return ok and WordPress.trim( value ) or ''
end

--- What the post needs from a photo's metadata.
function Publisher.readMetadata( photo )
	local title = field( photo, 'title' )
	if title == '' then
		title = ( field( photo, 'fileName' ):gsub( '%.[%w]+$', '' ) )
	end
	-- Newer Lightroom versions have an Alt Text field; otherwise the Headline is used.
	local alt = field( photo, 'altTextAccessibility' )
	if alt == '' then
		alt = field( photo, 'headline' )
	end
	return {
		title = title,
		slug = WordPress.slugify( title ),
		caption = field( photo, 'caption' ),
		alt = alt,
		location = WordPress.joinLocation { field( photo, 'location' ), field( photo, 'city' ), field( photo, 'country' ) },
	}
end

local function fail( message )
	error( message, 0 )
end

--- Upload a rendered photo and create or update its post.
--- options.status: "publish" or "draft" for new posts. Returns { id = "123", link = "https://…" }.
function Publisher.publishOne( creds, options, existingId, filePath, meta )
	local post
	if existingId and existingId ~= '' then
		local found, err, code, status = WordPress.getPost( creds, existingId )
		if found then
			post = found
		elseif not ( status == 404 or code == 'rest_post_invalid_id' ) then
			fail( 'Couldn’t check the photo on the site: ' .. tostring( err ) )
		end
		-- Deleted on the site: publish it again as a new post.
	end

	local oldMedia
	local featured = post and tonumber( post.featured_media ) or 0
	if featured > 0 then
		oldMedia = WordPress.getMedia( creds, featured )
	end

	local fileName = WordPress.fileNameFor( meta.slug, oldMedia and oldMedia.source_url )
	local media, uploadError = WordPress.uploadMedia( creds, filePath, fileName, { alt_text = meta.alt, title = meta.title } )
	if not media then
		fail( 'Uploading the photo failed: ' .. tostring( uploadError ) )
	end

	local fields = {
		title = meta.title,
		content = WordPress.contentFromCaption( meta.caption ),
		featured_media = media.id,
		meta = { photos_location = meta.location },
	}
	local saved, saveError
	if post then
		if post.status == 'trash' then
			fields.status = options.status -- Republishing brings back a trashed post.
		end
		saved, saveError = WordPress.updatePost( creds, post.id, fields )
	else
		fields.status = options.status
		saved, saveError = WordPress.createPost( creds, fields )
	end
	if not saved then
		WordPress.deleteMedia( creds, media.id ) -- Don't leave an unused upload behind.
		fail( 'Saving the post failed: ' .. tostring( saveError ) )
	end

	-- Attach the image to its post, and remove the version it replaces (best effort).
	WordPress.updateMedia( creds, media.id, { post = saved.id } )
	if oldMedia and oldMedia.id ~= media.id then
		WordPress.deleteMedia( creds, oldMedia.id )
	end

	return { id = tostring( saved.id ), link = saved.link, status = saved.status }
end

return Publisher
