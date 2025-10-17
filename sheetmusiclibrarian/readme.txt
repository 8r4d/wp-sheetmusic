=== Sheet Music Librarian ===
Contributors: bradsalomons
Donate link: https://8r4d.com/plugins/
Tags: sheet music, pdf, library, orchestra, band 
Requires at least: 6.8
Tested up to: 6.8
Stable tag: 1.0.0
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Simplifies creation of content types and taxonomies to create and organize sheet music libraries to provide access to members.

== Description ==

A sheet music manager for the orchestra website. 

Note: This plugin does not provide security or block access to copyrighted materials. It is meant to be used on a page with restricted access to users with permission or rights to access materials. Please use a WordPress page or plugin that controls viewing of a page or post in conjunction with this plugin.

As the volunteer in charge of managing our online member music library where musicians can download their sheet music, it's a chore keeping things organized all season. I built this plugin to simplify the task. 

It simplifies managing files on the back end and creates interactive filters on the front.

A new Content Type is created: 

Sheet Music, provides basic info including title, composer, and additonal notes fields, and attaching unlimited instrument-associated files to each record.

Taxonomies:

Instruments, allows nested and hierarchical lists of instruments organized by user, eg. Brass > Trumpet > Trumpet 1 that can be associated 1:1 with each file in the sheet music editor.

Seasons, is a categorization tool for each sheet music. Multiple seasons can be attached to each piece of sheet music. eg. We organize by "current", "christmas music", "2025" etc.

Shortcodes:

eg. 
whole collection: [sheet_music_library]
limited to season: [sheet_music_library season="standards"]
individual piece: [sheet_music_library id="3783"]

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
= 1.0 =
* Initial public release. This is in use and in active testing on our orchestra's website.
