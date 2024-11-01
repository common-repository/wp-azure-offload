=== WP Azure offload ===
Contributors: Promact Infotech Pvt. Ltd.
Tags: media, uploads, azure, CDN
Requires at least: 4.4
Tested up to: 5.2.2
Stable tag: 2.0
License: GPLv3

While uploading files to media library, copies files to Azure Storage and serve them using CDN url for faster delivery.

== Description ==

WP Azure Offload offers service to automatically copy WordPress media( including images, videos and othe media ) to your Azure Storage account and replaces their url to Azure's CDN endpoint url for the faster delivery.

It also provides a service to create a new storage container to your storage account and also list out the storage containers already having in your storage account. You can choose either of this storage container or create a new storage container to store your WordPress media files.

There is also an option for the cache control, you can set the max-age of your choice. By default it is set to 2592000 seconds.

There is also an option to automatically remove files from your local server once they have been uploaded to your storage contaier.

This also provides options to the media library to copy or remove single media file as well as bulk media files from Azure storage.

This plugin also works well with the already existing media files. If you're adding this plugin to the existing site, there is an option to copy all the existed files to the Azure Storage container. Copy media progress runs in background so the user do not need to wait till the progress complition they can do any work instead.

If the existed media has been attached to any post, this plugin will automatically find posts the media is attached and replaces the url with the CDN url.

== Installation ==

1. Install the plugin
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Click the "Azure" link in the admin menu
4. Add the Azure storage account name and key to use the services

== Screenshots ==

1. Azure storage account configuration

2. Manually add storage 

3. Browse existing storage containers

4. Create new storage container

5. The settings after adding storage container

6. Media handlers at media library list view if copy diles to azure storage and serve files using CDN url is checked

== Upgrade Notice ==

= 1.0 =
* Original Release.
