--[[
Minimal JSON for Lightroom's Lua 5.1: encode tables (arrays when keys are 1..n) and
decode WordPress REST responses, including \u escapes. json.null stands for JSON null.
]]

local json = {}

json.null = setmetatable( {}, { __tostring = function() return 'null' end } )

-- Encoding -------------------------------------------------------------------------------

local escapes = {
	['"'] = '\\"', ['\\'] = '\\\\', ['\b'] = '\\b', ['\f'] = '\\f',
	['\n'] = '\\n', ['\r'] = '\\r', ['\t'] = '\\t',
}

local function encodeString( s )
	return '"' .. s:gsub( '[%c"\\]', function( c )
		return escapes[ c ] or string.format( '\\u%04x', c:byte() )
	end ) .. '"'
end

local function isArray( t )
	local n = 0
	for k in pairs( t ) do
		if type( k ) ~= 'number' or k < 1 or math.floor( k ) ~= k then
			return false
		end
		n = n + 1
	end
	for i = 1, n do
		if t[ i ] == nil then
			return false
		end
	end
	return true, n
end

local encode

local function encodeTable( t, seen )
	if seen[ t ] then
		error( 'json: circular reference' )
	end
	seen[ t ] = true
	local parts = {}
	local array, n = isArray( t )
	if array and n > 0 then
		for i = 1, n do
			parts[ i ] = encode( t[ i ], seen )
		end
		seen[ t ] = nil
		return '[' .. table.concat( parts, ',' ) .. ']'
	end
	local keys = {}
	for k in pairs( t ) do
		keys[ #keys + 1 ] = tostring( k )
	end
	table.sort( keys )
	for _, k in ipairs( keys ) do
		local v = t[ k ]
		if v == nil then
			v = t[ tonumber( k ) ]
		end
		parts[ #parts + 1 ] = encodeString( k ) .. ':' .. encode( v, seen )
	end
	seen[ t ] = nil
	return '{' .. table.concat( parts, ',' ) .. '}'
end

encode = function( v, seen )
	local kind = type( v )
	if v == nil or v == json.null then
		return 'null'
	elseif kind == 'boolean' then
		return tostring( v )
	elseif kind == 'number' then
		if v ~= v or v == math.huge or v == -math.huge then
			error( 'json: cannot encode ' .. tostring( v ) )
		end
		if math.floor( v ) == v and math.abs( v ) < 2^53 then
			return string.format( '%d', v )
		end
		return string.format( '%.14g', v )
	elseif kind == 'string' then
		return encodeString( v )
	elseif kind == 'table' then
		return encodeTable( v, seen or {} )
	end
	error( 'json: cannot encode a ' .. kind )
end

function json.encode( v )
	return encode( v, {} )
end

-- Decoding -------------------------------------------------------------------------------

local function utf8char( cp )
	if not cp or cp < 0 or cp > 0x10FFFF then
		return ''
	elseif cp < 0x80 then
		return string.char( cp )
	elseif cp < 0x800 then
		return string.char( 0xC0 + math.floor( cp / 0x40 ), 0x80 + cp % 0x40 )
	elseif cp < 0x10000 then
		return string.char( 0xE0 + math.floor( cp / 0x1000 ), 0x80 + math.floor( cp / 0x40 ) % 0x40, 0x80 + cp % 0x40 )
	end
	return string.char(
		0xF0 + math.floor( cp / 0x40000 ),
		0x80 + math.floor( cp / 0x1000 ) % 0x40,
		0x80 + math.floor( cp / 0x40 ) % 0x40,
		0x80 + cp % 0x40
	)
end

json.utf8char = utf8char

local unescapes = { ['"'] = '"', ['\\'] = '\\', ['/'] = '/', b = '\b', f = '\f', n = '\n', r = '\r', t = '\t' }

local decodeValue

local function skip( s, i )
	return s:find( '[^ \t\r\n]', i ) or #s + 1
end

local function fail( s, i, what )
	error( string.format( 'json: %s at character %d', what, i ) )
end

local function decodeString( s, i )
	-- s:sub( i, i ) is the opening quote.
	local out = {}
	local j = i + 1
	while true do
		local c = s:sub( j, j )
		if c == '' then
			fail( s, j, 'unterminated string' )
		elseif c == '"' then
			return table.concat( out ), j + 1
		elseif c == '\\' then
			local e = s:sub( j + 1, j + 1 )
			if e == 'u' then
				local cp = tonumber( s:sub( j + 2, j + 5 ), 16 )
				if not cp then
					fail( s, j, 'bad \\u escape' )
				end
				j = j + 6
				if cp >= 0xD800 and cp <= 0xDBFF and s:sub( j, j + 1 ) == '\\u' then
					local low = tonumber( s:sub( j + 2, j + 5 ), 16 )
					if low and low >= 0xDC00 and low <= 0xDFFF then
						cp = 0x10000 + ( cp - 0xD800 ) * 0x400 + ( low - 0xDC00 )
						j = j + 6
					end
				end
				out[ #out + 1 ] = utf8char( cp )
			elseif unescapes[ e ] then
				out[ #out + 1 ] = unescapes[ e ]
				j = j + 2
			else
				fail( s, j, 'bad escape' )
			end
		else
			local k = s:find( '["\\]', j ) or #s + 1
			out[ #out + 1 ] = s:sub( j, k - 1 )
			j = k
		end
	end
end

local function decodeArray( s, i )
	local result = {}
	i = skip( s, i + 1 )
	if s:sub( i, i ) == ']' then
		return result, i + 1
	end
	while true do
		local v
		v, i = decodeValue( s, i )
		result[ #result + 1 ] = v
		i = skip( s, i )
		local c = s:sub( i, i )
		if c == ']' then
			return result, i + 1
		elseif c ~= ',' then
			fail( s, i, 'expected , or ]' )
		end
		i = skip( s, i + 1 )
	end
end

local function decodeObject( s, i )
	local result = {}
	i = skip( s, i + 1 )
	if s:sub( i, i ) == '}' then
		return result, i + 1
	end
	while true do
		if s:sub( i, i ) ~= '"' then
			fail( s, i, 'expected a key' )
		end
		local k
		k, i = decodeString( s, i )
		i = skip( s, i )
		if s:sub( i, i ) ~= ':' then
			fail( s, i, 'expected :' )
		end
		local v
		v, i = decodeValue( s, skip( s, i + 1 ) )
		result[ k ] = v
		i = skip( s, i )
		local c = s:sub( i, i )
		if c == '}' then
			return result, i + 1
		elseif c ~= ',' then
			fail( s, i, 'expected , or }' )
		end
		i = skip( s, i + 1 )
	end
end

decodeValue = function( s, i )
	i = skip( s, i )
	local c = s:sub( i, i )
	if c == '{' then
		return decodeObject( s, i )
	elseif c == '[' then
		return decodeArray( s, i )
	elseif c == '"' then
		return decodeString( s, i )
	elseif s:sub( i, i + 3 ) == 'true' then
		return true, i + 4
	elseif s:sub( i, i + 4 ) == 'false' then
		return false, i + 5
	elseif s:sub( i, i + 3 ) == 'null' then
		return json.null, i + 4
	end
	local num = s:match( '^-?%d+%.?%d*[eE]?[-+]?%d*', i )
	if num and num ~= '' and tonumber( num ) then
		return tonumber( num ), i + #num
	end
	fail( s, i, 'unexpected character' )
end

function json.decode( s )
	if type( s ) ~= 'string' then
		error( 'json: expected a string' )
	end
	local v, i = decodeValue( s, 1 )
	i = skip( s, i )
	if i <= #s then
		fail( s, i, 'trailing characters' )
	end
	return v
end

return json
