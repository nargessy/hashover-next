<?php namespace HashOver;

// Copyright (C) 2010-2019 Jacob Barkdull
// This file is part of HashOver.
//
// HashOver is free software: you can redistribute it and/or modify
// it under the terms of the GNU Affero General Public License as
// published by the Free Software Foundation, either version 3 of the
// License, or (at your option) any later version.
//
// HashOver is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Affero General Public License for more details.
//
// You should have received a copy of the GNU Affero General Public License
// along with HashOver.  If not, see <http://www.gnu.org/licenses/>.
//
//--------------------
//
// IMPORTANT NOTICE:
//
// Do not edit this file unless you know what you are doing. Instead,
// please use the HashOver administration panel to graphically adjust
// the settings, or create/edit the settings JSON file.


// Automated settings
class Settings extends SensitiveSettings
{
	public $rootDirectory;
	public $commentsRoot;
	public $httpRoot;
	public $httpBackend;
	public $httpImages;
	public $domain;
	public $themePath;
	public $formFields;

	public function __construct ()
	{
		// Set encoding
		mb_internal_encoding ('UTF-8');

		// Get absolute root directory path
		$root_directory = dirname (dirname (__DIR__));

		// Get HTTP root directory
		$http_directory = '/' . basename ($root_directory);

		// Replace backslashes with forward slashes on Windows
		if (DIRECTORY_SEPARATOR === '\\') {
			$root_directory = str_replace ('\\', '/', $root_directory);
		}

		// Root directory for script
		$this->rootDirectory = $root_directory;

		// Comments directory path
		$this->commentsRoot = $root_directory . '/comments';

		// Root directory for HTTP
		$this->httpRoot = $http_directory;

		// Backend directory for HTTP
		$this->httpBackend = $this->joinPaths ($http_directory, 'backend');

		// Image directory for HTTP
		$this->httpImages = $this->joinPaths ($http_directory, 'images');

		// Domain name for refer checking & notifications
		$this->domain = Misc::getArrayItem ($_SERVER, 'HTTP_HOST') ?: 'localhost';

		// Load JSON settings
		$this->loadSettingsFile ();
	}

	// Joins to paths together with proper slashes in between
	public function joinPaths ($path1, $path2)
	{
		// Remove trailing slashes from first path
		$path1 = rtrim ($path1, '/');

		// Remove leading and trailing slashes from second path
		$path2 = trim ($path2, '/');

		// Construct new path
		$path = $path1 . '/' . $path2;

		// Remove trailing slashes from new path
		$path = rtrim ($path, '/');

		// And return new path
		return $path;
	}

	// Synchronizes specific settings after remote changes
	public function syncSettings ()
	{
		// Theme path
		$this->themePath = 'themes/' . $this->theme;

		// Check if timezone is set to auto
		if ($this->serverTimezone === 'auto') {
			// If so, set timezone setting to current timezone
			$this->serverTimezone = date_default_timezone_get ();
		} else {
			// If not, set timezone to given timezone
			$tz = @date_default_timezone_set ($this->serverTimezone);

			// And throw exception if timezone ID is invalid
			if ($tz === false) {
				throw new \Exception (sprintf (
					'"%s" is not a valid timezone',
					$this->serverTimezone
				));
			}
		}

		// Disable likes and dislikes if cookies are disabled
		if ($this->setsCookies === false) {
			$this->allowsLikes = false;
			$this->allowsDislikes = false;
		}

		// Store status of each form field as array
		$this->formFields = array (
			'name' => $this->nameField,
			'password' => $this->passwordField,
			'email' => $this->emailField,
			'website' => $this->websiteField
		);

		// Disable password if name is disabled
		if ($this->nameField === 'off') {
			$this->passwordField = 'off';
		}

		// Disable login if name or password is disabled
		if ($this->nameField === 'off' or $this->passwordField === 'off') {
			$this->allowsLogin = false;
		}

		// Disable autologin if login is disabled
		if ($this->allowsLogin === false) {
			$this->usesAutoLogin = false;
		}

		// Check if the Gravatar default image name is not custom
		if ($this->gravatarDefault !== 'custom') {
			// If so, list Gravatar default image names
			$gravatar_defaults = array ('identicon', 'monsterid', 'wavatar', 'retro');

			// And set Gravatar default image to custom if its value is invalid
			if (!in_array ($this->gravatarDefault, $gravatar_defaults, true)) {
				$this->gravatarDefault = 'custom';
			}
		}

		// Backend directory for HTTP
		$this->httpBackend = $this->joinPaths ($this->httpRoot, 'backend');

		// Image directory for HTTP
		$this->httpImages = $this->joinPaths ($this->httpRoot, 'images');
	}

	// Accepts an array of settings to override default settings
	protected function overrideSettings (array $settings, $class = 'Settings')
	{
		// Loop through JSON data
		foreach ($settings as $setting => $value) {
			// Check if the key contains dashes
			if (mb_strpos ($setting, '-') !== false) {
				// If so, convert setting key to lowercase
				$setting = mb_strtolower ($setting);

				// Then convert dashed-case setting key to camelCase
				$setting = preg_replace_callback ('/-([a-z])/S', function ($grp) {
					return mb_strtoupper ($grp[1]);
				}, $setting);
			}

			// Check if setting from JSON data exists
			if (property_exists ('HashOver\\' . $class, $setting)) {
				// If so, get default setting type
				$type = gettype ($this->{$setting});

				// Skip setting if its an empty string
				if ($type === 'string' and empty ($value)) {
					continue;
				}

				// Otherwise, override setting if types match
				if (gettype ($value) === $type) {
					$this->{$setting} = $value;
				}
			}
		}

		// Synchronize settings
		$this->syncSettings ();
	}

	// Overrides settings based on JSON data
	protected function loadJsonSettings ($json)
	{
		// Parse JSON data
		$settings = @json_decode ($json, true);

		// Check if JSON data parsed as an array
		if (is_array ($settings)) {
			// If so, use it to override settings
			$this->overrideSettings ($settings, 'Settings');
		} else {
			// If not, just synchronize settings
			$this->syncSettings ();
		}
	}

	// Reads JSON settings file and uses it to override default settings
	protected function loadSettingsFile ()
	{
		// JSON settings file path
		$path = $this->getAbsolutePath ('config/settings.json');

		// Check if JSON settings file exists
		if (file_exists ($path)) {
			// If so, read the file
			$json = @file_get_contents ($path);

			// And override settings
			$this->loadJsonSettings ($json);
		} else {
			// If not, just synchronize settings
			$this->syncSettings ();
		}
	}

	// Type juggle string values of an array
	protected function juggleStringArray (array $data)
	{
		// Run through array
		foreach ($data as &$value) {
			// Cast boolean strings to actual booleans
			if ($value === 'true' or $value === 'false') {
				$value = ($value === 'true');
				continue;
			}

			// Cast numeric strings to floats
			if (is_numeric ($value)) {
				$value = (float)($value);
				continue;
			}

			// Type juggle nested arrays
			if (is_array ($value)) {
				$value = $this->juggleStringArray ($value);
				continue;
			}
		}

		return $data;
	}

	// Override default settings by with cfg URL queries
	public function loadFrontendSettings ()
	{
		// Attempt to get user settings from GET data
		if (!empty ($_GET['cfg'])) {
			$settings = $_GET['cfg'];
		}

		// Attempt to get user settings from POST data
		if (!empty ($_POST['cfg'])) {
			$settings = $_POST['cfg'];
		}

		// Check if cfg queries is an array
		if (!empty ($settings) and is_array ($settings)) {
			// If so, type juggle cfg queries
			$settings = $this->juggleStringArray ($settings);

			// Only override settings safe to expose to the frontend
			$this->overrideSettings ($settings, 'SafeSettings');
		}
	}

	// Returns a server-side absolute file path
	public function getAbsolutePath ($file)
	{
		return $this->joinPaths ($this->rootDirectory, $file);
	}

	// Returns a client-side path for a file within the HashOver root
	public function getHttpPath ($file)
	{
		return $this->joinPaths ($this->httpRoot, $file);
	}

	// Returns a client-side path for a file within the backend directory
	public function getBackendPath ($file)
	{
		return $this->joinPaths ($this->httpBackend, $file);
	}

	// Returns a client-side path for a file within the images directory
	public function getImagePath ($filename)
	{
		$path  = $this->joinPaths ($this->httpImages, $filename);
		$path .= '.' . $this->imageFormat;

		return $path;
	}

	// Returns a client-side path for a file within the configured theme
	public function getThemePath ($file, $http = true)
	{
		// Path to the requested file in the configured theme
		$theme_file = $this->joinPaths ($this->themePath, $file);

		// Use the same file from the default theme if it doesn't exist
		if (!file_exists ($this->getAbsolutePath ($theme_file))) {
			$theme_file = 'themes/default/' . $file;
		}

		// Convert the theme file path for HTTP use if told to
		if ($http !== false) {
			$theme_file = $this->getHttpPath ($theme_file);
		}

		return $theme_file;
	}

	// Checks if connection is on HTTPS/SSL
	public function isHTTPS ()
	{
		// The connection is HTTPS if server says so
		if (!empty ($_SERVER['HTTPS']) and $_SERVER['HTTPS'] !== 'off') {
			return true;
		}

		// Assume connection is HTTPS on standard SSL port
		if (Misc::getArrayItem ($_SERVER, 'SERVER_PORT') === '443') {
			return true;
		}

		// Otherwise, assume connection is HTTP
		return false;
	}

	// Check if a given API format is enabled
	public function apiCheck ($api)
	{
		// Check if the given API is enabled
		if (is_array ($this->enabledApi)) {
			// Return true if all available APIs are enabled
			if (in_array ('all', $this->enabledApi)) {
				return true;
			}

			// Return true if the given API is enabled
			if (in_array ($api, $this->enabledApi)) {
				return true;
			}
		}

		// Otherwise, throw exception by default
		throw new \Exception (
			'This API is not enabled.'
		);
	}
}
