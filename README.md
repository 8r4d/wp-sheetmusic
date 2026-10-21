# wp-sheetmusic

A sheet music manager for the orchestra website

Simplifies creation of content types and taxonomies to create and organize sheet music libraries to provide access to members.

# Description

A sheet music manager for the orchestra website. 

_**Note:** This plugin does not provide security or block access to copyrighted materials. It is meant to be used on a page with restricted access to users with permission or rights to access materials. Please use a WordPress page or plugin that controls viewing of a page or post in conjunction with this plugin._

As the volunteer in charge of managing our online member music library where musicians can download their sheet music, it's a chore keeping things organized all season. I built this plugin to simplify the task. 

It simplifies managing files on the back end and creates interactive filters on the front.

**A new Content Type is created:**

Sheet Music, provides basic info including title, composer, and additonal notes fields, and attaching unlimited instrument-associated files to each record.

**Taxonomies:**

Instruments, allows nested and hierarchical lists of instruments organized by user, eg. Brass > Trumpet > Trumpet 1 that can be associated 1:1 with each file in the sheet music editor.

Seasons, is a categorization tool for each sheet music. Multiple seasons can be attached to each piece of sheet music. eg. We organize by "current", "christmas music", "2025" etc.

**Shortcodes:**

_Examples:_ 

whole collection: [sheet_music_library]

limited to season: [sheet_music_library season="standards"]

individual piece: [sheet_music_library id="3783"]

collection last updated: [sheet_music_last_updated]

**Blocks:**

_Sheet Music Library_, the block version of the shortcode. Pick seasons or a single piece in the block sidebar.

_Sheet Music Last Updated_, shows when the collection on the page last changed. Editing a piece doesn't change the page's modified date, so put this at the top of the page. By default it follows the collections on the same page; you can pick seasons instead.

# General Usage

**To show all seasons:**

[sheet_music_library]

**To show only limited selections:**

_All music from a single season:_

[sheet_music_library season="christmas"]

_Single piece:_

[sheet_music_library id="3108"]

**Program order:** on a season's edit screen (Sheet Music > Seasons), drag pieces into concert order. A library showing just that season lists pieces in that order. Add orderby="title" to keep it alphabetical.

**For members:** filtering by instrument or searching by title/composer is instant, their instrument is remembered, pieces changed since their last visit get a "New" or "Updated" badge, and "Download all parts" saves every part for their instrument as a single zip.



**Show when the collection was last updated (put at the top of the page):**

[sheet_music_last_updated]

_Options:_ season="christmas" to track a specific season, label="Updated:" to change the text.
