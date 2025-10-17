# wp-sheetmusic

A sheet music manager for the orchestra website

Simplifies creation of content types and taxonomies to create and organize sheet music libraries to provide access to members.

# Description

A sheet music manager for the orchestra website. 

_**Note:** This plugin does not provide security or block access. Please use a WordPress page or plugin that controls viewing of a page or post in conjunction with this plugin._

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

# General Usage

**To show all seasons:**

[sheet_music_library]

**To show only limited selections:**

_All music from a single season:_

[sheet_music_library season="christmas"]

_Single piece:_

[sheet_music_library id="3108"]


