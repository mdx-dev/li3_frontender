# Assets Plugin for [li3](http://li3.me)
This is a heavily modified fork; you probably shouldn't be using this. You can
find the original project here:
[joseym/li3_frontender](https://github.com/joseym/li3_frontender)

## Installation
There are several ways to grab and use this project:

### Use [Composer](https://getcomposer.org)
Include the following in your project's `composer.json` file

```json
{
    "require": {
        "mdx-dev/li3_frontender": "dev-master"
    }
}
```

Run `php composer.phar install` (or `php composer.phar update`) and, aside from adding it to your Libraries, you should be good to go.

### Load via Submodule
1. Clone/Download the plugin into your app's ``libraries`` directory.
2. Tell your app to load the plugin by adding the following to your app's ``config/bootstrap/libraries.php``:

	Libraries::add('li3_frontender', array('source' => 'submodule'));

	> Important to set the source to something else as 'composer'.
	> Configuration options are available, standby

### System executables
You'll need [Node.JS](http://nodejs.org/) along with less, coffeescript, and bless packages.

## Usage
Currently this project supports the following frontend tools:

1. LessCSS compiling
2. CoffeeScript compiling
3. Instant cache busting thru unique filenames
4. CSS/JS Compression

The project comes bundled with it's own [Helper](https://github.com/mdx-dev/li3_frontender/blob/master/extensions/helper/Assets.php), here's how use use it.

### Linking Stylesheets
You assign page styles by specifying the name of a manifest.

```php
<?php $this->assets->style('my-css-manifest'); ?>
```

### Linking Scripts
Like the style helper, the script helper also takes a manifest name.

```php
<?php $this->assets->script('my-script-manifest'); ?>
```

### Configuration options
Several options can be set from the `Libraries::add()` configuration. Here's an example.

```php
<?php
	Libraries::add('li3_frontender', array(
		'mergeAssets' => true,
		'root' => LITHIUM_APP_PATH . "/webroot/assets",
		'cacheBuster' =>false,
		'blessCss' => false,
	));
?>
```

<table>
	<tr>
		<th>Name</th>
		<th>Type</th>
		<th>Default</th>
		<th>Description</th>
	</tr>
	<tr>
		<td><strong>blessCss</strong></td>
		<td><code>Boolean</code></td>
		<td><code>false</code></td>
		<td>
			Checks that processed stylesheets don't have more selectors than IE's
			limit. If a stylesheet has too many selectors, it is split into multiple
			parts.
		</td>
	</tr>
	<tr>
		<td><strong>cacheBuster</strong></td>
		<td><code>Mixed</code></td>
		<td><code>"modtime"</code></td>
		<td>
			The cache busting strategy to use. Can be one of:
			<ul>
				<li><code>false</code>: Do not check original assets once they are processed.</li>
				<li>
					<code>"string"</code>: Tag processed assets with the
					<code>cacheString</code> option. Assets are re-processed if the
					<code>cacheString</code> changes.
				</li>
				<li>
					<code>"modtime"</code>: Tag processed assets with the original asset's
					file modification time. Assets are re-processed if the original file changes.
				</li>
			</ul>
		</td>
	</tr>
	<tr>
		<td><strong>cacheCheckLessImports</strong></td>
		<td><code>Boolean</code></td>
		<td><code>false</code></td>
		<td>
			If the <code>"modtime"</code> option is used for <code>cacheBuster</code>
			this option will find local assets in less <code>@import</code>s and check
			them for changes as well. This defaults to <code>false</code> because,
			depending on your environment, this option may cause a significant slow-down.
		</td>
	</tr>
	<tr>
		<td><strong>cacheCheckManifestSameAssets</strong></td>
		<td><code>Boolean</code></td>
		<td><code>true</code></td>
		<td>
			If <code>cacheBuster</code> and <code>mergeAssets</code> are enabled,
			this will tag processed manifests with a hash of their asset names. If the
			contents of the manifest change, even if the assets have not changed, the
			manifest will be re-processed.
		</td>
	</tr>
	<tr>
		<td><strong>cacheString</strong></td>
		<td><code>String</code></td>
		<td><code>"bust"</code></td>
		<td>
			When <code>cacheBuster</code> is <code>"string"</code> this is the value
			that assets are tagged with.
		</td>
	</tr>
	<tr>
		<td><strong>manifests</strong></td>
		<td><code>Array</code></td>
		<td>empty array</td>
		<td>
			Specifies the manifests used. See the section on manifests.
		</td>
	</tr>
	<tr>
		<td><strong>mergeAssets</strong></td>
		<td><code>Boolean</code></td>
		<td><code>false</code></td>
		<td>
			If set, processed assets will be merged together into as few files as
			possible. Otherwise each individual processed asset will be returned.
		</td>
	</tr>
	<tr>
		<td><strong>purgeStale</strong></td>
		<td><code>Boolean</code></td>
		<td><code>true</code></td>
		<td>
			When a manifest or asset is processed, if this is set, any other versions
			of the file will be deleted before the new one is saved.
		</td>
	</tr>
	<tr>
		<td><strong>root</strong></td>
		<td><code>String</code></td>
		<td><code>null</code></td>
		<td>
			The webroot where assets are stored. If left <code>null</code> then Li3
			will be queried for the webroot via <code>Media::webroot()</code>.
		</td>
	</tr>
	<tr>
		<td><strong>verbose</strong></td>
		<td><code>Boolean</code></td>
		<td><code>false</code></td>
		<td>
			If set, information about what assets are being processed will be printed
			with <code>echo</code>! Only useful for debugging, and not even really
			then.
		</td>
	</tr>
</table>

## Manifests
Use asset manifests to specify your assets in the library config.

First, specify the manifest like this (below, "main" is the name of a manifest for JavaScript files):

```php
<?php
	Libraries::add('li3_frontender', array(
		'manifests' => array(
			'js' => array(
				'main' => array(
					'main.coffee',
					'popup.js',
				)
			)
		)
	));
?>
```

Then, reference the manifest by name when calling the helper:

```php
<?php $this->assets->script('main'); ?>
```
