--[[
Tests for the Lightroom plug-in, run by tests/run.sh under Lua 5.1 (Lightroom's version).
Unit tests always run; the publishing tests run when WP_SITE, WP_USER, WP_PASSWORD and
TEST_PHOTO are set, against that WordPress (the local wp-env one).
]]

local stubs = require 'stubs'
local json = require 'json'
local WordPress = require 'WordPress'
local Publisher = require 'Publisher'
local provider = require 'ServiceProvider'

local passed, failed = 0, 0
local function check( name, ok, detail )
	if ok then
		passed = passed + 1
		print( 'ok    ' .. name )
	else
		failed = failed + 1
		print( 'FAIL  ' .. name .. ( detail and ( ': ' .. tostring( detail ) ) or '' ) )
	end
end
local function eq( name, got, want )
	check( name, got == want, 'got ' .. tostring( got ) .. ', want ' .. tostring( want ) )
end

-- JSON -----------------------------------------------------------------------------------

do
	local v = json.decode( '{"a":[1,2.5,-3e2,true,false,null],"s":"caf\\u00e9 \\ud83d\\udcf7 \\"q\\" \\/ \\n","o":{}}' )
	eq( 'json numbers', v.a[ 3 ], -300 )
	eq( 'json null', v.a[ 6 ], json.null )
	eq( 'json unicode escapes', v.s, 'café 📷 "q" / \n' )
	local back = json.decode( json.encode( { title = 'Ñandú "x"\n', ids = { 1, 2, 3 }, meta = { photos_location = '' }, flag = false } ) )
	eq( 'json round trip string', back.title, 'Ñandú "x"\n' )
	eq( 'json round trip array', #back.ids, 3 )
	eq( 'json round trip empty string', back.meta.photos_location, '' )
	eq( 'json round trip false', back.flag, false )
	eq( 'json integer format', json.encode( { n = 123 } ), '{"n":123}' )
	check( 'json rejects garbage', not pcall( json.decode, '<html>' ) )
end

-- Helpers --------------------------------------------------------------------------------

eq( 'slug: plain', WordPress.slugify( 'Intercambio' ), 'intercambio' )
eq( 'slug: spaces and accents', WordPress.slugify( '  Amor de verano: Pretérito Ñ ' ), 'amor-de-verano-preterito-n' )
eq( 'slug: empty', WordPress.slugify( '' ), 'photo' )
eq( 'file name: new', WordPress.fileNameFor( 'cortina', nil ), 'cortina.jpg' )
eq( 'file name: second', WordPress.fileNameFor( 'cortina', 'https://x/wp-content/uploads/2026/11/cortina-scaled.jpg' ), 'cortina-v2.jpg' )
eq( 'file name: after v3', WordPress.fileNameFor( 'cortina', 'https://x/uploads/cortina-v3-scaled.jpg' ), 'cortina-v4.jpg' )
eq( 'location: all parts', WordPress.joinLocation { 'Central Park', 'New York', 'USA' }, 'Central Park, New York, USA' )
eq( 'location: gaps and repeats', WordPress.joinLocation { '', 'Porto', ' porto ', 'Portugal' }, 'Porto, Portugal' )
eq( 'content: paragraphs', WordPress.contentFromCaption( 'One & two\n\n<three>' ),
	'<!-- wp:paragraph -->\n<p>One &amp; two</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>&lt;three&gt;</p>\n<!-- /wp:paragraph -->' )
eq( 'content: empty', WordPress.contentFromCaption( '' ), '' )
eq( 'plain text', WordPress.plainText( '<p>Awesome! It&#8217;s &amp; more&#x2026;</p>\n' ), 'Awesome! It’s & more…' )
eq( 'posix from gmt', WordPress.posixFromGmt( '2026-11-26T13:08:08' ), 1795698488 )
eq( 'posix epoch', WordPress.posixFromGmt( '1970-01-01T00:00:00' ), 0 )
eq( 'site url normalized', WordPress.normalizeSiteUrl( ' miguelrojas.photo/ ' ), 'https://miguelrojas.photo' )
eq( 'site host', WordPress.siteHost( 'http://localhost:8888' ), 'localhost' )
check( 'authorize url', WordPress.authorizeUrl( 'miguelrojas.photo' ):find( '^https://miguelrojas.photo/wp%-admin/authorize%-application%.php%?app_name=Lightroom%%20Classic$' ) )

do
	local s = {}
	provider.updateExportSettings( s )
	check( 'export settings forced', s.LR_format == 'JPEG' and s.LR_size_maxHeight == 3000 and s.LR_size_resizeType == 'longEdge' and s.LR_export_colorSpace == 'sRGB' )
	local sections = provider.sectionsForTopOfDialog( stubs.LrView.osFactory(), {} )
	check( 'dialog builds', type( sections ) == 'table' and #sections == 1 and sections[ 1 ].title == 'miguelrojas.photo' )
end

-- Publishing against WordPress -----------------------------------------------------------

local site, user, password, photoPath = os.getenv( 'WP_SITE' ), os.getenv( 'WP_USER' ), os.getenv( 'WP_PASSWORD' ), os.getenv( 'TEST_PHOTO' )
if not ( site and user and password and photoPath ) then
	print( '(publishing tests skipped: WP_SITE, WP_USER, WP_PASSWORD, TEST_PHOTO not set)' )
else
	local settings = { siteUrl = site, username = user, newPostStatus = 'draft', appPassword = password }
	local creds = { siteUrl = site, username = user, password = password }

	-- The dialog's Check Connection stores the password, as in Lightroom.
	provider.startDialog( settings )
	settings.appPassword = password
	provider.sectionsForTopOfDialog( stubs.LrView.osFactory(), settings )
	settings.connectionStatus = nil
	local dialogSettings = settings
	provider.endDialog( dialogSettings, 'ok' )
	check( 'password stored', next( stubs.LrPasswords._all ) ~= nil )

	local bad = WordPress.me( { siteUrl = site, username = user, password = 'wrong pass word' } )
	check( 'wrong password refused', bad == nil )
	local me, err = WordPress.me( creds )
	check( 'connection', me and me.id, err )

	-- A fake export: renditions of one photo with Lightroom-style metadata.
	local function copyPhoto()
		local path = os.tmpname() .. '.jpg'
		os.execute( 'cp ' .. photoPath .. ' ' .. path )
		return path
	end
	local function fakePhoto( meta )
		return {
			getFormattedMetadata = function( _, key )
				if key == 'altTextAccessibility' then
					error( 'unknown key' ) -- Like an older Lightroom.
				end
				return meta[ key ]
			end,
		}
	end
	local function runExport( renditionSpecs, status )
		settings.newPostStatus = status or 'draft'
		local renditions, recorded = {}, {}
		for i, spec in ipairs( renditionSpecs ) do
			local r = { photo = fakePhoto( spec.meta ), publishedPhotoId = spec.id, _path = copyPhoto() }
			function r:waitForRender()
				return true, self._path
			end
			function r:recordPublishedPhotoId( id )
				recorded[ i ] = recorded[ i ] or {}
				recorded[ i ].id = id
			end
			function r:recordPublishedPhotoUrl( url )
				recorded[ i ] = recorded[ i ] or {}
				recorded[ i ].url = url
			end
			function r:uploadFailed( message )
				recorded[ i ] = { failed = message }
			end
			renditions[ i ] = r
		end
		local exportContext = {
			propertyTable = settings,
			exportSession = { countRenditions = function()
				return #renditions
			end },
		}
		function exportContext:configureProgress()
			return { isCanceled = function()
				return false
			end, setPortionComplete = function() end, done = function() end }
		end
		function exportContext:renditions()
			local i = 0
			return function()
				i = i + 1
				if renditions[ i ] then
					return i, renditions[ i ]
				end
			end
		end
		provider.processRenderedPhotos( {}, exportContext )
		return recorded
	end

	-- 1. Publish a new photo.
	local meta = { title = 'Prueba Lightroom', caption = 'Una línea.\nOtra & más.', headline = 'Alt text de prueba', location = 'Central Park', city = 'New York', country = 'USA', fileName = 'IMG_0001.jpg' }
	local first = runExport( { { meta = meta } } )[ 1 ] or {}
	check( 'publish: recorded id and url', first.id and first.url, first.failed )
	local post = first.id and WordPress.getPost( creds, first.id )
	check( 'publish: post exists', post )
	if post then
		eq( 'publish: title', post.title and post.title.raw, 'Prueba Lightroom' )
		eq( 'publish: status draft', post.status, 'draft' )
		eq( 'publish: location', post.meta and post.meta.photos_location, 'Central Park, New York, USA' )
		check( 'publish: content', post.content and post.content.raw:find( '<p>Otra &amp; más.</p>', 1, true ), post.content and post.content.raw )
		local media = WordPress.getMedia( creds, post.featured_media )
		check( 'publish: featured image', media and media.id, 'no media' )
		if media then
			eq( 'publish: alt text', media.alt_text, 'Alt text de prueba' )
			eq( 'publish: attached to post', tonumber( media.post ), tonumber( post.id ) )
			check( 'publish: file name', media.source_url:find( '/prueba%-lightroom[%-%w]*%.jpg$' ), media.source_url )
			local exif = media.media_details and media.media_details.image_meta or {}
			check( 'publish: camera data read', exif.camera and exif.camera ~= '', json.encode( exif ) )
		end
		check( 'temp file deleted', #stubs.LrFileUtils.deleted >= 1 )

		-- 2. Republish with new metadata: new upload, old one removed, same post.
		local oldMediaId = post.featured_media
		meta.title = 'Prueba Lightroom (editada)'
		meta.city = ''
		local second = runExport( { { meta = meta, id = first.id } } )[ 1 ] or {}
		eq( 'republish: same post', second.id, first.id )
		local again = WordPress.getPost( creds, first.id )
		eq( 'republish: title', again and again.title.raw, 'Prueba Lightroom (editada)' )
		eq( 'republish: still draft', again and again.status, 'draft' )
		eq( 'republish: location', again and again.meta.photos_location, 'Central Park, USA' )
		check( 'republish: new image', again and again.featured_media ~= oldMediaId )
		local newMedia = again and WordPress.getMedia( creds, again.featured_media )
		check( 'republish: versioned file name', newMedia and newMedia.source_url:find( '%-v2[%-%w]*%.jpg$' ), newMedia and newMedia.source_url )
		local gone, _, _, goneStatus = WordPress.getMedia( creds, oldMediaId )
		check( 'republish: old image deleted', gone == nil and goneStatus == 404, goneStatus )

		-- 3. Comments and likes in Lightroom's Comments panel.
		WordPress.updatePost( creds, first.id, { status = 'publish', comment_status = 'open' } ) -- Drafts take no comments.
		local comment, commentError = WordPress.request( creds, 'POST', '/wp/v2/comments', { post = tonumber( first.id ), content = 'Awesome! It’s great.' } )
		check( 'comment created', comment and comment.id, commentError )
		local got
		provider.getCommentsFromPublishedCollection( settings, { { remoteId = first.id, publishedPhoto = 'p' } }, function( result )
			got = result
		end )
		check( 'comments: listed', got and #got.comments == 1, got and #got.comments )
		if got and got.comments[ 1 ] then
			eq( 'comments: text', got.comments[ 1 ].commentText, 'Awesome! It’s great.' )
			check( 'comments: date', type( got.comments[ 1 ].dateCreated ) == 'number' )
		end
		local rated = false
		provider.getRatingsFromPublishedCollection( settings, { { remoteId = first.id } }, function()
			rated = true
		end )
		check( 'likes: skipped for a local site', not rated )

		-- 4. A post deleted on the site is published again as a new one.
		if again then
			WordPress.deleteMedia( creds, again.featured_media )
		end
		WordPress.request( creds, 'DELETE', '/wp/v2/posts/' .. first.id, nil, { force = 'true' } )
		local third = runExport( { { meta = meta, id = first.id } }, 'publish' )[ 1 ] or {}
		check( 'deleted on site: new post', third.id and third.id ~= first.id, third.failed )
		local fresh = third.id and WordPress.getPost( creds, third.id )
		eq( 'deleted on site: published', fresh and fresh.status, 'publish' )

		-- 5. Removing from the collection trashes the post (twice is fine).
		local removed = {}
		provider.deletePhotosFromPublishedCollection( settings, { third.id }, function( id )
			removed[ #removed + 1 ] = id
		end )
		local trashed = WordPress.getPost( creds, third.id )
		eq( 'remove: trashed', trashed and trashed.status, 'trash' )
		provider.deletePhotosFromPublishedCollection( settings, { third.id }, function( id )
			removed[ #removed + 1 ] = id
		end )
		eq( 'remove: reported both times', #removed, 2 )

		-- Clean up.
		if trashed then
			WordPress.deleteMedia( creds, trashed.featured_media )
			WordPress.request( creds, 'DELETE', '/wp/v2/posts/' .. third.id, nil, { force = 'true' } )
		end
	end

	-- A failed upload is reported, not thrown.
	local savedPhoto = photoPath
	photoPath = '/dev/null'
	local failedRun = runExport( { { meta = { title = 'Vacía' } } } )[ 1 ] or {}
	photoPath = savedPhoto
	check( 'bad file: reported as failed', failedRun.failed ~= nil, failedRun.id )
end

print( string.format( '\n%d passed, %d failed', passed, failed ) )
os.exit( failed == 0 and 0 or 1 )
