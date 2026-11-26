--[[
Lightroom Classic plug-in: a publish service for miguelrojas.photo (a WordPress site).
Install with File → Plug-in Manager → Add, then add the service in the Publish Services
panel. See the repository's README ("Publishing from Lightroom").
]]

return {
	LrSdkVersion = 10.0,
	LrSdkMinimumVersion = 6.0,
	LrToolkitIdentifier = 'photo.miguelrojas.lightroom',
	LrPluginName = 'miguelrojas.photo',
	LrPluginInfoUrl = 'https://github.com/mmtr/miguelrojas',
	LrExportServiceProvider = {
		title = 'miguelrojas.photo',
		file = 'ServiceProvider.lua',
	},
	VERSION = { major = 1, minor = 0, revision = 0 },
}
