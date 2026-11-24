// Prepare photos for the web: resize, add the watermark, keep the camera data.
//
//   swift bin/prepare-photos.swift --out photos/web photos/*.jpg
//   swift bin/prepare-photos.swift --out photos/web --no-watermark photos/old.jpg
//
// - Long edge limited to --max pixels (default 3000); smaller photos keep their size.
// - The white watermark (brand/png/watermark-white-shadow.png) goes in the bottom-right
//   corner, sized from the photo's shorter side so it looks the same on every photo.
// - EXIF (camera, lens, settings, date), GPS and IPTC are copied; orientation is applied
//   to the pixels; the embedded preview thumbnail (which would be unwatermarked) is dropped.
// Originals are never modified. Run from the repository root.

import CoreGraphics
import Foundation
import ImageIO
import UniformTypeIdentifiers

var maxSide = 3000
var addWatermark = true
var outDir = ""
var inputs: [String] = []
var args = Array(CommandLine.arguments.dropFirst())
while !args.isEmpty {
	let arg = args.removeFirst()
	switch arg {
	case "--max": maxSide = Int(args.removeFirst()) ?? maxSide
	case "--out": outDir = args.removeFirst()
	case "--no-watermark": addWatermark = false
	default: inputs.append(arg)
	}
}
guard !outDir.isEmpty, !inputs.isEmpty else {
	FileHandle.standardError.write("Usage: swift bin/prepare-photos.swift [--max 3000] [--no-watermark] --out <dir> <photos...>\n".data(using: .utf8)!)
	exit(1)
}

let watermarkURL = URL(fileURLWithPath: "brand/png/watermark-white-shadow.png")
guard let wmSource = CGImageSourceCreateWithURL(watermarkURL as CFURL, nil),
	let watermark = CGImageSourceCreateImageAtIndex(wmSource, 0, nil)
else {
	FileHandle.standardError.write("Watermark not found at \(watermarkURL.path). Run from the repository root.\n".data(using: .utf8)!)
	exit(1)
}

try FileManager.default.createDirectory(atPath: outDir, withIntermediateDirectories: true)
let sRGB = CGColorSpace(name: CGColorSpace.sRGB)!

for path in inputs {
	let url = URL(fileURLWithPath: path)
	guard let source = CGImageSourceCreateWithURL(url as CFURL, nil),
		let props = CGImageSourceCopyPropertiesAtIndex(source, 0, nil) as? [CFString: Any],
		let width = props[kCGImagePropertyPixelWidth] as? Int,
		let height = props[kCGImagePropertyPixelHeight] as? Int
	else {
		print("skip \(path): not a readable image")
		continue
	}

	// Decode at the target size with the EXIF orientation applied.
	let options: [CFString: Any] = [
		kCGImageSourceCreateThumbnailFromImageAlways: true,
		kCGImageSourceCreateThumbnailWithTransform: true,
		kCGImageSourceThumbnailMaxPixelSize: min(maxSide, max(width, height)),
		kCGImageSourceShouldCacheImmediately: true,
	]
	guard let photo = CGImageSourceCreateThumbnailAtIndex(source, 0, options as CFDictionary) else {
		print("skip \(path): could not decode")
		continue
	}

	let space = (photo.colorSpace?.model == .rgb) ? photo.colorSpace! : sRGB
	guard let ctx = CGContext(data: nil, width: photo.width, height: photo.height, bitsPerComponent: 8,
		bytesPerRow: 0, space: space, bitmapInfo: CGImageAlphaInfo.noneSkipLast.rawValue)
	else {
		print("skip \(path): could not create canvas")
		continue
	}
	ctx.interpolationQuality = .high
	ctx.draw(photo, in: CGRect(x: 0, y: 0, width: photo.width, height: photo.height))

	if addWatermark {
		let shortSide = Double(min(photo.width, photo.height))
		let wmWidth = shortSide * 0.30
		let wmHeight = wmWidth * Double(watermark.height) / Double(watermark.width)
		let margin = shortSide * 0.025
		ctx.setAlpha(0.85)
		// Core Graphics' origin is bottom-left: this is the bottom-right corner.
		ctx.draw(watermark, in: CGRect(x: Double(photo.width) - margin - wmWidth, y: margin, width: wmWidth, height: wmHeight))
	}
	guard let output = ctx.makeImage() else { continue }

	// Copy camera data, GPS and IPTC; pixels are already upright, so orientation is 1.
	var meta: [CFString: Any] = [:]
	for key in [kCGImagePropertyExifDictionary, kCGImagePropertyExifAuxDictionary, kCGImagePropertyTIFFDictionary,
		kCGImagePropertyGPSDictionary, kCGImagePropertyIPTCDictionary] {
		if let dict = props[key] { meta[key] = dict }
	}
	meta[kCGImagePropertyOrientation] = 1
	if var tiff = meta[kCGImagePropertyTIFFDictionary] as? [CFString: Any] {
		tiff[kCGImagePropertyTIFFOrientation] = 1
		meta[kCGImagePropertyTIFFDictionary] = tiff
	}
	if var exif = meta[kCGImagePropertyExifDictionary] as? [CFString: Any] {
		exif[kCGImagePropertyExifPixelXDimension] = output.width
		exif[kCGImagePropertyExifPixelYDimension] = output.height
		meta[kCGImagePropertyExifDictionary] = exif
	}
	meta[kCGImageDestinationLossyCompressionQuality] = 0.88

	let outURL = URL(fileURLWithPath: outDir).appendingPathComponent(url.lastPathComponent)
	guard let dest = CGImageDestinationCreateWithURL(outURL as CFURL, UTType.jpeg.identifier as CFString, 1, nil) else { continue }
	CGImageDestinationAddImage(dest, output, meta as CFDictionary)
	if CGImageDestinationFinalize(dest) {
		print("\(url.lastPathComponent): \(width)x\(height) -> \(output.width)x\(output.height)\(addWatermark ? ", watermarked" : "")")
	} else {
		print("failed \(path)")
	}
}
