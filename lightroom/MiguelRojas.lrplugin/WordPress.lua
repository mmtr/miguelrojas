--[[
A small client for the site's WordPress REST API (photo posts and their images), plus the
text helpers the publish service uses. Requests authenticate with an application password
(WordPress → Users → Profile → Application Passwords).
]]

local LrHttp = import 'LrHttp'
local LrStringUtils = import 'LrStringUtils'

local json = require 'json'

local WordPress = {}

local TIMEOUT = 120 -- Seconds; uploads of 3000 px JPEGs over slow connections.

-- Text helpers ---------------------------------------------------------------------------

local function trim( s )
	return ( tostring( s or '' ):gsub( '^%s+', '' ):gsub( '%s+$', '' ) )
end
WordPress.trim = trim

--- "miguelrojas.photo/" → "https://miguelrojas.photo".
function WordPress.normalizeSiteUrl( url )
	url = trim( url ):gsub( '/+$', '' )
	if url ~= '' and not url:find( '^https?://' ) then
		url = 'https://' .. url
	end
	return url
end

--- The site's host name ("miguelrojas.photo"), or nil.
function WordPress.siteHost( url )
	return WordPress.normalizeSiteUrl( url ):match( '^https?://([^/:]+)' )
end

function WordPress.urlEncode( s )
	return ( tostring( s ):gsub( '[^%w%-%._~]', function( c )
		return string.format( '%%%02X', c:byte() )
	end ) )
end

local accents = {
	{ '[áàäâãåÁÀÄÂÃÅ]', 'a' }, { '[éèëêÉÈËÊ]', 'e' }, { '[íìïîÍÌÏÎ]', 'i' },
	{ '[óòöôõøÓÒÖÔÕØ]', 'o' }, { '[úùüûÚÙÜÛ]', 'u' }, { '[ñÑ]', 'n' }, { '[çÇ]', 'c' },
}

--- A file name stem from a title: "Amor de verano" → "amor-de-verano".
function WordPress.slugify( title )
	local s = trim( title )
	-- Accented letters are two bytes in UTF-8, so match them as alternatives, not a class.
	for _, pair in ipairs( accents ) do
		local letters = pair[ 1 ]:sub( 2, -2 )
		for ch in letters:gmatch( '[%z\1-\127\194-\244][\128-\191]*' ) do
			s = s:gsub( ch:gsub( '%p', '%%%0' ), pair[ 2 ] )
		end
	end
	s = s:lower():gsub( '[^a-z0-9]+', '-' ):gsub( '^%-+', '' ):gsub( '%-+$', '' )
	return s ~= '' and s or 'photo'
end

--- The upload's file name. A republished photo gets a new name ("-v2", "-v3", …) so the
--- CDN, which caches images by URL, can't keep serving the old version.
function WordPress.fileNameFor( slug, previousSourceUrl )
	if not previousSourceUrl or previousSourceUrl == '' then
		return slug .. '.jpg'
	end
	local stem = previousSourceUrl:match( '([^/]+)$' ) or ''
	stem = stem:gsub( '%.[%w]+$', '' ):gsub( '%-scaled$', '' )
	local version = tonumber( stem:match( '%-v(%d+)$' ) ) or 1
	return string.format( '%s-v%d.jpg', slug, version + 1 )
end

--- "Central Park", "New York", "USA" → "Central Park, New York, USA" (empty parts skipped).
function WordPress.joinLocation( parts )
	local out, seen = {}, {}
	for _, part in ipairs( parts ) do
		part = trim( part )
		if part ~= '' and not seen[ part:lower() ] then
			out[ #out + 1 ] = part
			seen[ part:lower() ] = true
		end
	end
	return table.concat( out, ', ' )
end

local function escapeHtml( s )
	return ( s:gsub( '&', '&amp;' ):gsub( '<', '&lt;' ):gsub( '>', '&gt;' ) )
end

--- Post content from a caption: one paragraph block per line.
function WordPress.contentFromCaption( caption )
	local blocks = {}
	for line in ( tostring( caption or '' ) .. '\n' ):gmatch( '([^\n]*)\n' ) do
		line = trim( line:gsub( '\r', '' ) )
		if line ~= '' then
			blocks[ #blocks + 1 ] = '<!-- wp:paragraph -->\n<p>' .. escapeHtml( line ) .. '</p>\n<!-- /wp:paragraph -->'
		end
	end
	return table.concat( blocks, '\n\n' )
end

--- Plain text from rendered HTML (comments).
function WordPress.plainText( html )
	local entities = { amp = '&', lt = '<', gt = '>', quot = '"', apos = "'", nbsp = ' ', hellip = '…', rsquo = '’', lsquo = '‘', rdquo = '”', ldquo = '“', ndash = '–', mdash = '—' }
	local s = tostring( html or '' ):gsub( '<br%s*/?>', '\n' ):gsub( '</p>%s*<p[^>]*>', '\n\n' ):gsub( '<[^>]+>', '' )
	s = s:gsub( '&#[xX](%x+);', function( n )
		return json.utf8char( tonumber( n, 16 ) )
	end ):gsub( '&#(%d+);', function( n )
		return json.utf8char( tonumber( n ) )
	end ):gsub( '&(%a+);', function( name )
		return entities[ name ] or ( '&' .. name .. ';' )
	end )
	return trim( s )
end

--- Seconds since 1970 (UTC) from WordPress's "2026-11-26T13:08:08" (a *_gmt field).
function WordPress.posixFromGmt( iso )
	local y, m, d, hh, mm, ss = tostring( iso or '' ):match( '^(%d+)%-(%d+)%-(%d+)T(%d+):(%d+):(%d+)' )
	if not y then
		return nil
	end
	y, m, d = tonumber( y ), tonumber( m ), tonumber( d )
	-- Days from 1970-01-01 to the date (proleptic Gregorian calendar).
	if m <= 2 then
		y = y - 1
	end
	local era = math.floor( y / 400 )
	local yoe = y - era * 400
	local mp = ( m + 9 ) % 12
	local doy = math.floor( ( 153 * mp + 2 ) / 5 ) + d - 1
	local doe = yoe * 365 + math.floor( yoe / 4 ) - math.floor( yoe / 100 ) + doy
	local days = era * 146097 + doe - 719468
	return days * 86400 + tonumber( hh ) * 3600 + tonumber( mm ) * 60 + tonumber( ss )
end

-- Requests -------------------------------------------------------------------------------

--- creds: { siteUrl = …, username = …, password = … }.
local function authHeader( creds )
	local password = tostring( creds.password or '' ):gsub( '%s', '' )
	return { field = 'Authorization', value = 'Basic ' .. LrStringUtils.encodeBase64( trim( creds.username ) .. ':' .. password ) }
end

local function apiUrl( creds, path, query )
	local url = WordPress.normalizeSiteUrl( creds.siteUrl ) .. '/wp-json' .. path
	local params = {}
	for k, v in pairs( query or {} ) do
		params[ #params + 1 ] = WordPress.urlEncode( k ) .. '=' .. WordPress.urlEncode( v )
	end
	table.sort( params )
	if #params > 0 then
		url = url .. ( url:find( '?', 1, true ) and '&' or '?' ) .. table.concat( params, '&' )
	end
	return url
end

--- Turn a response into (data) or (nil, message, errorCode, status).
local function parse( body, headers )
	local status = headers and tonumber( headers.status )
	if not body and not status then
		local why = headers and headers.error and ( headers.error.name or headers.error.errorCode ) or 'no response'
		return nil, 'Couldn’t reach the site (' .. tostring( why ) .. ').', 'network'
	end
	-- Sites showing PHP warnings print them before the JSON; skip to where it starts.
	local data
	local from, attempts = 1, 0
	while body and attempts < 20 do
		local start = body:find( '[{%[]', from )
		if not start then
			break
		end
		local ok, value = pcall( json.decode, body:sub( start ) )
		if ok then
			data = value
			break
		end
		from, attempts = start + 1, attempts + 1
	end
	if not status or status >= 400 then
		local message = type( data ) == 'table' and data.message or nil
		local code = type( data ) == 'table' and data.code or nil
		if type( message ) ~= 'string' or message == '' then
			message = 'The site answered with an error' .. ( status and ( ' (HTTP ' .. status .. ')' ) or '' ) .. '.'
		end
		return nil, WordPress.plainText( message ), code, status
	end
	if data == nil then
		return nil, 'The site’s answer wasn’t understood (is the address right?).', 'bad_response', status
	end
	return data
end

--- method: GET, POST or DELETE (sent as a POST with _method, which WordPress accepts).
function WordPress.request( creds, method, path, body, query )
	query = query or {}
	local headers = { authHeader( creds ) }
	if method == 'GET' then
		return parse( LrHttp.get( apiUrl( creds, path, query ), headers, TIMEOUT ) )
	end
	if method ~= 'POST' then
		query._method = method
	end
	local payload = ''
	if body ~= nil then
		payload = json.encode( body )
		headers[ #headers + 1 ] = { field = 'Content-Type', value = 'application/json; charset=utf-8' }
	end
	return parse( LrHttp.post( apiUrl( creds, path, query ), payload, headers, 'POST', TIMEOUT ) )
end

--- Who the credentials belong to (checks the connection).
function WordPress.me( creds )
	return WordPress.request( creds, 'GET', '/wp/v2/users/me', nil, { context = 'edit' } )
end

function WordPress.uploadMedia( creds, filePath, fileName, fields )
	local content = { { name = 'file', fileName = fileName, filePath = filePath, contentType = 'image/jpeg' } }
	for k, v in pairs( fields or {} ) do
		content[ #content + 1 ] = { name = k, value = v }
	end
	local headers = { authHeader( creds ) }
	return parse( LrHttp.postMultipart( apiUrl( creds, '/wp/v2/media' ), content, headers, TIMEOUT ) )
end

function WordPress.getMedia( creds, id )
	return WordPress.request( creds, 'GET', '/wp/v2/media/' .. id, nil, { context = 'edit' } )
end

function WordPress.updateMedia( creds, id, fields )
	return WordPress.request( creds, 'POST', '/wp/v2/media/' .. id, fields )
end

--- Media can't go to the trash, so this deletes it for good.
function WordPress.deleteMedia( creds, id )
	return WordPress.request( creds, 'DELETE', '/wp/v2/media/' .. id, nil, { force = 'true' } )
end

function WordPress.getPost( creds, id )
	return WordPress.request( creds, 'GET', '/wp/v2/posts/' .. id, nil, { context = 'edit' } )
end

function WordPress.createPost( creds, fields )
	return WordPress.request( creds, 'POST', '/wp/v2/posts', fields )
end

function WordPress.updatePost( creds, id, fields )
	return WordPress.request( creds, 'POST', '/wp/v2/posts/' .. id, fields )
end

--- Moves a post to the trash (restorable from WordPress → Posts → Trash).
function WordPress.trashPost( creds, id )
	return WordPress.request( creds, 'DELETE', '/wp/v2/posts/' .. id )
end

function WordPress.getComments( creds, postId )
	return WordPress.request( creds, 'GET', '/wp/v2/comments', nil, { post = postId, per_page = 100, order = 'asc' } )
end

--- Jetpack like count, from WordPress.com's public API (nil when unavailable, e.g. locally).
function WordPress.likeCount( creds, postId )
	local host = WordPress.siteHost( creds.siteUrl )
	if not host or not host:find( '.', 1, true ) or host == 'localhost' then
		return nil
	end
	local url = 'https://public-api.wordpress.com/rest/v1.1/sites/' .. WordPress.urlEncode( host ) .. '/posts/' .. WordPress.urlEncode( postId ) .. '/likes'
	local data = parse( LrHttp.get( url, {}, 30 ) )
	return data and tonumber( data.found ) or nil
end

--- Where to create an application password for Lightroom.
function WordPress.authorizeUrl( siteUrl )
	return WordPress.normalizeSiteUrl( siteUrl ) .. '/wp-admin/authorize-application.php?app_name=' .. WordPress.urlEncode( 'Lightroom Classic' )
end

return WordPress
