--[[
The miguelrojas.photo publish service: settings dialog, export settings, publishing,
removing, and the comments and likes shown in Lightroom's Comments panel.
]]

local LrErrors = import 'LrErrors'
local LrFileUtils = import 'LrFileUtils'
local LrHttp = import 'LrHttp'
local LrPasswords = import 'LrPasswords'
local LrTasks = import 'LrTasks'
local LrView = import 'LrView'

local Publisher = require 'Publisher'
local WordPress = require 'WordPress'

local provider = {}

-- Credentials ----------------------------------------------------------------------------

-- The application password is kept in Lightroom's password store, not in the settings.
local function passwordKey( siteUrl, username )
	return 'wp-application-password:' .. WordPress.normalizeSiteUrl( siteUrl ) .. ':' .. WordPress.trim( username )
end

local function credsFor( settings, password )
	if password == nil then
		password = LrPasswords.retrieve( passwordKey( settings.siteUrl, settings.username ) )
	end
	return { siteUrl = settings.siteUrl, username = settings.username, password = password or '' }
end

local function savePassword( settings )
	if WordPress.trim( settings.username ) ~= '' and settings.appPassword then
		LrPasswords.store( passwordKey( settings.siteUrl, settings.username ), WordPress.trim( settings.appPassword ) )
	end
end

-- What the service is --------------------------------------------------------------------

provider.supportsIncrementalPublish = 'only'
provider.small_icon = 'icon.png'

provider.exportPresetFields = {
	{ key = 'siteUrl', default = 'https://miguelrojas.photo' },
	{ key = 'username', default = '' },
	{ key = 'newPostStatus', default = 'publish' },
}

-- Format and size are fixed (updateExportSettings); sharpening, metadata and the
-- watermark stay up to you.
provider.hideSections = { 'exportLocation', 'fileNaming', 'fileSettings', 'imageSettings', 'video', 'postProcessing' }
provider.allowFileFormats = { 'JPEG' }
provider.allowColorSpaces = { 'sRGB' }
provider.hidePrintResolution = true
provider.canExportVideo = false

provider.titleForGoToPublishedCollection = 'Show Site'
provider.titleForGoToPublishedPhoto = 'Show on Site'
provider.titleForPhotoRating = 'Likes'
provider.disableRenamePublishedCollection = true
provider.supportsCustomSortOrder = false

function provider.getCollectionBehaviorInfo( publishSettings )
	return {
		defaultCollectionName = 'Photos',
		defaultCollectionCanBeDeleted = false,
		canAddCollection = false,
		maxCollectionSetDepth = 0,
	}
end

--- Edits to these mark a published photo for republishing (develop edits always do).
function provider.metadataThatTriggersRepublish( publishSettings )
	return {
		default = false,
		title = true,
		caption = true,
		location = true,
	}
end

--- The site's format: sRGB JPEG, 3000 px on the long edge, quality 80.
function provider.updateExportSettings( exportSettings )
	exportSettings.LR_format = 'JPEG'
	exportSettings.LR_jpeg_quality = 0.8
	exportSettings.LR_jpeg_useLimitSize = false
	exportSettings.LR_export_colorSpace = 'sRGB'
	exportSettings.LR_size_doConstrain = true
	exportSettings.LR_size_doNotEnlarge = true
	exportSettings.LR_size_resizeType = 'longEdge'
	exportSettings.LR_size_maxHeight = 3000
	exportSettings.LR_size_maxWidth = 3000
	exportSettings.LR_size_units = 'pixels'
end

-- Settings dialog ------------------------------------------------------------------------

function provider.startDialog( propertyTable )
	propertyTable.appPassword = LrPasswords.retrieve( passwordKey( propertyTable.siteUrl, propertyTable.username ) ) or ''
	propertyTable.connectionStatus = ''
end

function provider.endDialog( propertyTable, why )
	if why ~= 'cancel' then
		propertyTable.siteUrl = WordPress.normalizeSiteUrl( propertyTable.siteUrl )
		savePassword( propertyTable )
	end
end

local function checkConnection( propertyTable )
	propertyTable.connectionStatus = 'Checking…'
	LrTasks.startAsyncTask( function()
		local me, err = WordPress.me( credsFor( propertyTable, propertyTable.appPassword ) )
		if not me then
			propertyTable.connectionStatus = tostring( err )
			return
		end
		savePassword( propertyTable )
		local caps = type( me.capabilities ) == 'table' and me.capabilities or {}
		if caps.publish_posts and caps.upload_files then
			propertyTable.connectionStatus = 'Connected as ' .. tostring( me.name or me.slug ) .. '.'
		else
			propertyTable.connectionStatus = 'Connected as ' .. tostring( me.name or me.slug ) .. ', but this user can’t publish posts with images.'
		end
	end )
end

function provider.sectionsForTopOfDialog( f, propertyTable )
	local bind, share = LrView.bind, LrView.share
	local function label( title )
		return f:static_text { title = title, alignment = 'right', width = share 'labelWidth' }
	end
	return {
		{
			title = 'miguelrojas.photo',
			synopsis = bind 'siteUrl',
			f:row {
				label 'Site:',
				f:edit_field { value = bind 'siteUrl', width_in_chars = 32, immediate = true },
			},
			f:row {
				label 'Username:',
				f:edit_field { value = bind 'username', width_in_chars = 20, immediate = true },
			},
			f:row {
				label 'Application password:',
				f:password_field { value = bind 'appPassword', width_in_chars = 32, immediate = true },
			},
			f:row {
				f:spacer { width = share 'labelWidth' },
				f:push_button {
					title = 'Create a Password…',
					action = function()
						LrHttp.openUrlInBrowser( WordPress.authorizeUrl( propertyTable.siteUrl ) )
					end,
				},
				f:push_button {
					title = 'Check Connection',
					action = function()
						checkConnection( propertyTable )
					end,
				},
			},
			f:row {
				f:spacer { width = share 'labelWidth' },
				f:static_text { title = bind 'connectionStatus', width_in_chars = 50, height_in_lines = 2 },
			},
			f:row {
				label 'New photos:',
				f:popup_menu {
					value = bind 'newPostStatus',
					items = {
						{ title = 'Published right away', value = 'publish' },
						{ title = 'Saved as drafts', value = 'draft' },
					},
				},
			},
			f:row {
				f:spacer { width = share 'labelWidth' },
				f:static_text {
					title = 'Photos go up as sRGB JPEGs, 3000 px on the long edge, quality 80. Below, under Metadata, keep All Metadata (the site shows the camera data), and choose the watermark.',
					width_in_chars = 50,
					height_in_lines = 3,
				},
			},
		},
	}
end

-- Publishing -----------------------------------------------------------------------------

function provider.processRenderedPhotos( functionContext, exportContext )
	local settings = exportContext.propertyTable
	local creds = credsFor( settings )
	if creds.password == '' or WordPress.trim( creds.username ) == '' then
		LrErrors.throwUserError( 'Add your username and application password in the publish service’s settings first (right-click it → Edit Settings…).' )
	end

	local count = exportContext.exportSession:countRenditions()
	local host = WordPress.siteHost( settings.siteUrl ) or 'the site'
	local progress = exportContext:configureProgress {
		title = count > 1 and string.format( 'Publishing %d photos to %s', count, host ) or ( 'Publishing a photo to ' .. host ),
	}
	local options = { status = settings.newPostStatus == 'draft' and 'draft' or 'publish' }

	for i, rendition in exportContext:renditions { stopIfCanceled = true } do
		local meta = Publisher.readMetadata( rendition.photo )
		local rendered, pathOrMessage = rendition:waitForRender()
		if progress:isCanceled() then
			break
		end
		if rendered then
			local ok, result = LrTasks.pcall( Publisher.publishOne, creds, options, rendition.publishedPhotoId, pathOrMessage, meta )
			LrFileUtils.delete( pathOrMessage )
			if ok then
				rendition:recordPublishedPhotoId( result.id )
				rendition:recordPublishedPhotoUrl( result.link )
			else
				rendition:uploadFailed( tostring( result ) )
			end
		else
			rendition:uploadFailed( pathOrMessage )
		end
		progress:setPortionComplete( i / count )
	end
	progress:done()
end

--- Removing photos from the collection moves their posts to the trash on the site.
function provider.deletePhotosFromPublishedCollection( publishSettings, arrayOfPhotoIds, deletedCallback )
	local creds = credsFor( publishSettings )
	for _, id in ipairs( arrayOfPhotoIds ) do
		local ok, err, code, status = WordPress.trashPost( creds, id )
		if ok or status == 404 or code == 'rest_post_invalid_id' or code == 'rest_already_trashed' then
			deletedCallback( id )
		else
			LrErrors.throwUserError( 'Couldn’t remove the photo from the site: ' .. tostring( err ) )
		end
	end
end

function provider.goToPublishedCollection( publishSettings, info )
	LrHttp.openUrlInBrowser( WordPress.normalizeSiteUrl( publishSettings.siteUrl ) )
end

-- Comments and likes ---------------------------------------------------------------------

function provider.canAddCommentsToService( publishSettings )
	return false
end

function provider.getCommentsFromPublishedCollection( publishSettings, arrayOfPhotoInfo, commentCallback )
	local creds = credsFor( publishSettings )
	for _, photoInfo in ipairs( arrayOfPhotoInfo ) do
		local comments = WordPress.getComments( creds, photoInfo.remoteId )
		if type( comments ) == 'table' then
			local list = {}
			for _, c in ipairs( comments ) do
				local posix = WordPress.posixFromGmt( c.date_gmt )
				list[ #list + 1 ] = {
					commentId = tostring( c.id ),
					commentText = WordPress.plainText( type( c.content ) == 'table' and c.content.rendered or '' ),
					dateCreated = posix and ( posix - 978307200 ) or nil, -- Lightroom counts from 2001.
					username = tostring( c.author_name or '' ),
					realname = tostring( c.author_name or '' ),
					url = c.link,
				}
			end
			commentCallback { publishedPhoto = photoInfo, comments = list }
		end
	end
end

function provider.getRatingsFromPublishedCollection( publishSettings, arrayOfPhotoInfo, ratingCallback )
	local creds = credsFor( publishSettings )
	for _, photoInfo in ipairs( arrayOfPhotoInfo ) do
		local likes = WordPress.likeCount( creds, photoInfo.remoteId )
		if likes then
			ratingCallback { publishedPhoto = photoInfo, rating = likes }
		end
	end
end

return provider
