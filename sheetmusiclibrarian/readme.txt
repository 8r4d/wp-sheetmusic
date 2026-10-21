=== Sheet Music Librarian ===
Contributors: bradsalomons
Donate link: https://8r4d.com/plugins/
Tags: sheet music, pdf, library, orchestra, band 
Requires at least: 6.8
Tested up to: 6.8
Stable tag: 1.3.0
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Simplifies creation of content types and taxonomies to create and organize sheet music libraries to provide access to members.

== Description ==

A sheet music manager for the orchestra website. 

Note: This plugin does not provide security or block access to copyrighted materials. It is meant to be used on a page with restricted access to users with permission or rights to access materials. Please use a WordPress page or plugin that controls viewing of a page or post in conjunction with this plugin.

As the volunteer in charge of managing our online member music library where musicians can download their sheet music, it's a chore keeping things organized all season. I built this plugin to simplify the task. 

It simplifies managing files on the back end and creates interactive filters on the front.

A new Content Type is created: 

Sheet Music, provides basic info including title, composer, additonal notes, and one or more external reference links (eg. YouTube videos), and attaching unlimited instrument-associated files to each record.

Taxonomies:

Instruments, allows nested and hierarchical lists of instruments organized by user, eg. Brass > Trumpet > Trumpet 1 that can be associated 1:1 with each file in the sheet music editor.

Seasons, is a categorization tool for each sheet music. Multiple seasons can be attached to each piece of sheet music. eg. We organize by "current", "christmas music", "2025" etc.

Shortcodes:

eg. 
whole collection: [sheet_music_library]
limited to season: [sheet_music_library season="standards"]
individual piece: [sheet_music_library id="3783"]
season, alphabetical instead of program order: [sheet_music_library season="standards" orderby="title"]
collection last updated: [sheet_music_last_updated]

Blocks:

Sheet Music Library, the block version of [sheet_music_library]. Pick seasons or a single piece in the block sidebar.

Sheet Music Last Updated, shows the date the collection on the page last changed (a piece added, edited, removed or moved between seasons). Editing a piece doesn't change the page's own modified date, so put this block (or the [sheet_music_last_updated] shortcode) at the top of the page. By default it follows the collections on the same page; you can instead pick seasons in the block sidebar, or use [sheet_music_last_updated season="christmas"] and label="Updated:".

File protection:

Sheet Music > File Protection moves your sheet music files into a private folder. Members download them through links that only appear on your library page and expire after a few weeks, so files can't be opened from old, shared or search-engine links. Protect the page itself with your usual page-protection plugin (eg. Passster); this plugin protects the files.

== Installation ==

**Automatic**

* From your WordPress Admin, navigate to: **Plugins** > **Add New**
* Search for: **Sheet Music Librarian**
* Install it
* Activate it

**Manual**

* Download
* Unzip
* Upload to /plugins/ folder
* Activate


 == Changelog ==
= 1.3.0 =
* Instrument and search filtering now happen instantly, without reloading the page.
* Search pieces by title or composer.
* The member's instrument choice is remembered on their device.
* Pieces with no part for the chosen instrument are hidden.
* "New" and "Updated" badges on pieces changed since the member's last visit (or the last 14 days on a first visit).
* "Download all parts" button saves every part for the chosen instrument as one zip, built in the browser.
* Program order: drag pieces into concert order on the season's edit screen; a library showing one season uses that order (orderby="title" for alphabetical).
* File protection: sheet music files move to a private folder and are downloaded through expiring links (14-28 days) that only appear on the library page. Old direct addresses, including ones indexed by search engines, stop working.
* New Sheet Music > File Protection screen to protect existing files in one click and check that the server blocks direct access (with the Nginx rule to add if not).
* Files are protected automatically when a piece is saved, and downloads are marked noindex for search engines.
* Library download links are left out of RSS feeds and anonymous REST API responses; protected files are hidden from the public media API and attachment pages; pages showing a library are not cached.
= 1.2.0 =
* New Sheet Music Library block (block-editor version of the shortcode).
* New Sheet Music Last Updated block and [sheet_music_last_updated] shortcode showing when the collection on the page last changed.
* The instrument shortcode attribute now works; the front-end filter no longer uses a nonce, which could expire behind page caching.
* Fixed PHP warnings when a file's instrument is unassigned or deleted.
* Piece "Updated" dates now use the site's timezone.
* Editor scripts and styles only load on Sheet Music screens.
= 1.1.3 =
* Added a Wikipedia icon for external links pointing to wikipedia.org.

= 1.1.2 =
* External links now show a platform-specific icon (YouTube, Spotify, SoundCloud, Apple Music, Vimeo) based on the link's domain, falling back to a generic link icon.

= 1.1.1 =
* External links are now drag-and-drop sortable in the editor; the front-end display order follows the order set there.

= 1.1 =
* Added support for one or more external reference links (eg. YouTube videos) per piece, editable below the Notes field and displayed on the front end.

= 1.0 =
* Initial public release. This is in use and in active testing on our orchestra's website.
