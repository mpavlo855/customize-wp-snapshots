=== Customize Snapshots ===
Contributors: mpavlo
Requires at least: 4.7
Tested up to: 4.9
Stable tag: 0.7.0
Requires PHP: 5.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Tags: customizer, customize, changesets

Provide a UI for managing Customizer changesets; save changesets as named drafts, schedule for publishing; inspect in admin and preview on frontend.

== Description ==

Customize Snapshots is the feature plugin which prototyped [Customizer changesets](https://make.wordpress.org/core/2016/10/12/customize-changesets-technical-design-decisions/); this feature was [merged](https://make.wordpress.org/core/2016/10/12/customize-changesets-formerly-transactions-merge-proposal/) as part of WordPress 4.7. The term “snapshots” was chosen because the Customizer feature revolved around saving the state (taking a snapshot) of the Customizer at a given time so that the changes could be saved as a draft and scheduled for future publishing.

While the plugin's technical infrastructure for changesets was merged in WordPress 4.7, the user interface still remains largely in the Customize Snapshots plugin, in which we will continue to iterate and prototype features to merge into core.

For a rundown of all the features, see the screenshots below as well as the 0.6 release video:

[youtube https://www.youtube.com/watch?v=GH0xo7bTiSs]

This plugin works particularly well with [Customizer Browser History](https://wordpress.org/plugins/customizer-browser-history/), which ensures that URL in the browser corresponds to the current panel/section/control that is expanded, as well as the current URL and device being previewed.

Requires PHP 5.3+. **Development of this plugin is done [on GitHub](https://github.com/xwp/wp-customize-snapshots). Pull requests welcome. Please see [issues](https://github.com/xwp/wp-customize-snapshots) reported there before going to the [plugin forum](https://wordpress.org/support/plugin/customize-snapshots).**

== Screenshots ==

1. The “Save & Publish” button becomes a combo-button that allows you to select the status for the changeset when saving. In addition to publishing, a changeset can be saved as a permanent draft (as opposed to just an auto-draft), pending review, scheduled for future publishing. A revision is made each time you press the button.
2. For non-administrator users (who lack the new `customize_publish` capability) the “Publish” button is replaced with a “Submit” button. This takes the changeset and puts it into a pending status.
3. When selecting to schedule a changeset, the future publish date can be supplied. Changesets can be supplied a name which serves like a commit message.
4. When selecting Publish, a confirmation appears. Additionally, a link is shown which allows you to browse the frontend with the changeset applied. This preview URL can be shared with authenticated and non-authenticated users alike.
5. The admin bar shows information about the current changeset when previewing the changeset on the frontend.
6. The Customize link is promoted to the top in the admin menu; a link to list all changesets is also added.
7. The Customize link in the admin bar likewise gets a submenu item to link to the changesets post list.
8. The Changesets admin screen lists all of the changeset posts that have been saved or published. Row actions provide shortcuts to preview changeset on frontend, open changeset in Customizer for editing, or inspect the changeset's contents on the edit post screen.
9. When excerpts are shown in the post list table, the list of settings that are contained in each changeset are displayed.
10. Opening a changeset's edit post screen shows which settings are contained in the changeset and what their values are. Settings may be removed from a changeset here. A changeset can also be scheduled or published from here just as one would do for any post, and the settings will be saved once the changeset is published. Buttons are also present to preview the changeset on the frontend and to open the changeset in the Customizer for further revisions.
11. Each time a user saves changes to an existing changeset, a new revision will be stored (if revisions are enabled in WordPress). Users' contributions to a given changeset can be inspected here and even reverted prior to publishing.
12. Multiple changesets can be merged into a single changeset, allowing multiple users' work to be combined for previewing together and publishing all at once.