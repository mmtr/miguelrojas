--[[
Stand-ins for the Lightroom SDK modules the plug-in imports, enough to run it under plain
Lua 5.1 against a real WordPress (LrHttp is implemented with curl).
]]

local function shellQuote( s )
	return "'" .. tostring( s ):gsub( "'", "'\\''" ) .. "'"
end

local tmpCounter = 0
local function tmpName()
	tmpCounter = tmpCounter + 1
	return string.format( '/tmp/lrstub-%d-%d', os.time(), tmpCounter )
end

local function readFile( path )
	local f = io.open( path, 'rb' )
	if not f then
		return nil
	end
	local s = f:read( '*a' )
	f:close()
	return s
end

local function writeFile( path, s )
	local f = assert( io.open( path, 'wb' ) )
	f:write( s )
	f:close()
end

-- Runs curl; returns body, { status = n } like LrHttp, or nil, { error = … }.
local function curl( args )
	local out = tmpName()
	local cmd = 'curl -sS -o ' .. out .. " -w '%{http_code}' " .. table.concat( args, ' ' ) .. ' 2>/dev/null'
	local p = io.popen( cmd )
	local code = p:read( '*a' )
	p:close()
	local body = readFile( out )
	os.remove( out )
	local status = tonumber( code )
	if not status or status == 0 then
		return nil, { error = { name = 'curl failed' } }
	end
	return body, { status = status }
end

local function headerArgs( headers )
	local args = {}
	for _, h in ipairs( headers or {} ) do
		args[ #args + 1 ] = '-H ' .. shellQuote( h.field .. ': ' .. h.value )
	end
	return table.concat( args, ' ' )
end

local LrHttp = { opened = {} }

function LrHttp.get( url, headers, timeout )
	return curl { headerArgs( headers ), shellQuote( url ) }
end

function LrHttp.post( url, body, headers, method, timeout )
	local file = tmpName()
	writeFile( file, body or '' )
	local a, b = curl { '-X', method or 'POST', headerArgs( headers ), '--data-binary', '@' .. file, shellQuote( url ) }
	os.remove( file )
	return a, b
end

function LrHttp.postMultipart( url, content, headers, timeout )
	local args = { headerArgs( headers ) }
	for _, part in ipairs( content ) do
		if part.filePath then
			args[ #args + 1 ] = '-F ' .. shellQuote( part.name .. '=@' .. part.filePath .. ';filename=' .. part.fileName .. ';type=' .. ( part.contentType or 'application/octet-stream' ) )
		else
			args[ #args + 1 ] = '--form-string ' .. shellQuote( part.name .. '=' .. tostring( part.value ) )
		end
	end
	args[ #args + 1 ] = shellQuote( url )
	return curl( args )
end

function LrHttp.openUrlInBrowser( url )
	LrHttp.opened[ #LrHttp.opened + 1 ] = url
end

local b64chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/'
local LrStringUtils = {}
function LrStringUtils.encodeBase64( data )
	return ( ( data:gsub( '.', function( x )
		local r, byte = '', x:byte()
		for i = 8, 1, -1 do
			r = r .. ( byte % 2 ^ i - byte % 2 ^ ( i - 1 ) > 0 and '1' or '0' )
		end
		return r
	end ) .. '0000' ):gsub( '%d%d%d?%d?%d?%d?', function( x )
		if #x < 6 then
			return ''
		end
		local c = 0
		for i = 1, 6 do
			c = c + ( x:sub( i, i ) == '1' and 2 ^ ( 6 - i ) or 0 )
		end
		return b64chars:sub( c + 1, c + 1 )
	end ) .. ( { '', '==', '=' } )[ #data % 3 + 1 ] )
end

local LrTasks = {
	pcall = pcall,
	startAsyncTask = function( fn )
		fn()
	end,
}

local passwords = {}
local LrPasswords = {
	store = function( key, value )
		passwords[ key ] = value
	end,
	retrieve = function( key )
		return passwords[ key ]
	end,
	_all = passwords,
}

local LrDate = {
	timeFromPosixDate = function( posix )
		return posix - 978307200 -- Lightroom counts from 2001-01-01.
	end,
}

local LrErrors = {
	throwUserError = function( message )
		error( 'USER ERROR: ' .. message, 0 )
	end,
}

local LrFileUtils = { deleted = {} }
function LrFileUtils.delete( path )
	table.insert( LrFileUtils.deleted, path )
	os.remove( path )
	return true
end

-- A view factory that records what it builds.
local factory = setmetatable( {}, {
	__index = function( _, kind )
		return function( _, args )
			args = args or {}
			args._kind = kind
			return args
		end
	end,
} )
local LrView = {
	bind = function( key )
		return { _bind = key }
	end,
	share = function( key )
		return { _share = key }
	end,
	osFactory = function()
		return factory
	end,
}

local modules = {
	LrHttp = LrHttp,
	LrStringUtils = LrStringUtils,
	LrTasks = LrTasks,
	LrPasswords = LrPasswords,
	LrDate = LrDate,
	LrErrors = LrErrors,
	LrFileUtils = LrFileUtils,
	LrView = LrView,
}

function import( name )
	return assert( modules[ name ], 'no stub for ' .. name )
end

return modules
