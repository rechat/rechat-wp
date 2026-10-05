# Rechat Plugin User Guide

## Overview

The Rechat plugin connects a WordPress site to your Rechat account. It pulls your agents, offices and regions into WordPress, shows live MLS listings, sends website leads to Rechat, and on a Multisite network builds a separate website for every agent and office.

| Area | What you get |
| --- | --- |
| Connection | Log in to Rechat once; the plugin keeps the login fresh and saves your brand, logo and primary color |
| Data sync | Agents, Offices, Regions and branding copied from Rechat every 12 hours, plus a manual sync |
| Content types | Agents, Offices, Regions, Neighborhoods, Off Market listings, Testimonials |
| Listings | Full search page, latest-listings sliders, search bar, listing detail pages |
| Leads | Lead forms that create contacts in Rechat |
| Page building | Gutenberg blocks and shortcodes for listings, agents, offices/regions, lead forms, testimonials |
| Multisite | One sub-site per agent or office, built from a theme you choose, kept in sync with the main site |

The main site is called the **hub** in this guide. Agent and office websites are **sub-sites** of the hub.

## Getting started

Connect the site to Rechat once, then run a first sync. Everything else in the plugin depends on this connection.

**Where it lives:** WP Admin → **Rechat** (top-level menu). Tabs: Sync Data · Connect · General · Local Logic & Maps · Import / Export · Portal Log · User Guide, and on a Multisite hub also Multisite · Agent wizard · Office wizard. On a Multisite network the Rechat menu shows only on the hub.

**Who can open it:** Administrators, super admins and users with the **Agent** role.

### Connect your Rechat account

1. Install and activate the plugin (WP Admin → Plugins).
2. Go to **Rechat → Connect** and click **Connect to Rechat**.
3. Log in at Rechat and approve access. You come back to the Connect tab with the message "Successfully connected to Rechat!"
4. The plugin saves your brand, logos and primary color automatically. There is no brand picker: the brand is the one attached to the Rechat account that approved access, so log in with the correct brand's account.
5. Go to **Settings → Permalinks** and click **Save** once, so listing and agent URLs work.
6. Go to **Rechat → Sync Data** and click **Sync now** (see Syncing).

### What the Connect tab shows

| Card | Button / field | What it does |
| --- | --- | --- |
| Connection | Status badge | Connected · Token missing · Not connected |
| Connection | **Connect to Rechat** | Starts the Rechat login (shown when not connected) |
| Connection | **Disconnect from Rechat** | Asks "Are you sure…?" → **Yes, Disconnect** removes the login and brand from this site. Sync and listings stop until you connect again |
| Data sync status | **Go to Sync Data** | Opens the Sync Data tab. The card shows last sync time, result, what was added/updated, and the next scheduled sync |
| Token failure alerts | **Save alert emails** | Emails (comma or new line) that get an alert when the Rechat login stops refreshing. Max one email per 6 hours. Left empty, alerts go to Rechat's default contacts |
| Token & refresh status | **Refresh access token now** | Forces a fresh login token. Use it when the status says expired or failed |

The login token refreshes itself (hourly retry when expired). If it fails, a red notice "Rechat OAuth token refresh failed" appears on Rechat pages with a link back to the Connect tab.

## Syncing

Sync copies your Agents, Offices, Regions and branding from Rechat into WordPress. It runs by itself every 12 hours, and you can run it any time with **Sync now**. Listings and testimonials are not copied: they load live from Rechat on every page view.

### Run a sync by hand

1. Go to **Rechat → Sync Data**.
2. Click **Sync now** in the "Update from Rechat API" card. A progress bar runs.
3. Read the result: "Sync completed successfully!" plus how many agents, offices and regions were added or updated. On a Multisite hub you also see lines for **Agent Sites (Multisite)** and **Office Sites (Multisite)**: created, updated, skipped, errors.

If you see "You are not yet connected to Rechat", connect first (Getting started).

### Sync Data buttons

| Button | What it does | When to use it |
| --- | --- | --- |
| **Sync now** | Full sync of agents, offices, regions and branding | After changes in Rechat you want live now |
| **Set brand IDs for all agents** | Finds each agent's personal brand in Rechat and saves it on the agent as Brand ID. Shows how many agents were updated | Once after setup, so agent testimonials and agent sites use the right brand |

### What sync changes

| Rechat data | Becomes in WordPress | Notes |
| --- | --- | --- |
| Region brands | Regions posts | Title = region name |
| Office brands | Offices posts | Office ID, address, phone, linked regions |
| Users | Agents posts | Name, bio, photo, phone, email, social links, license, designation, offices, regions |
| Brand settings | Primary color + logos | Also saved when you connect |
| Site domain (single site only) | Added to your Rechat brand portal | Lets Rechat widgets work on this domain |

- **Changed in Rechat:** the WordPress post is updated in place. Synced agent fields are read-only in WordPress; edit them in Rechat.
- **Kept by sync:** Display order, Agent title, Brand ID, Visibility and agent testimonials. Edit these in WordPress.
- **Removed from Rechat:** the WordPress post is permanently deleted. Posts you created by hand (no Rechat ID) are never deleted.
- **Not synced:** Neighborhoods and Off Market listings. You add them by hand.

**Tip:** To hide a synced agent, set **Agent Visibility → Hide** on the agent. Do not move a synced agent to Draft: sync looks only at published posts and will create a second copy.

### Check the schedule

**Rechat → Connect → Data sync status** shows last sync, next scheduled sync and whether WP-Cron is on. WordPress cron fires on site traffic, so a site with no visitors may sync late. The card warns you when the sync is not scheduled, has failed, or is more than 24 hours old. On Multisite the schedule runs on the hub only.

## Listings, blocks and shortcodes

Add Rechat content to any page with a Gutenberg block (Widgets category) or a shortcode. Each listing block has a matching shortcode, so classic editors and page builders work too.

| Block | Shortcode | Shows |
| --- | --- | --- |
| Listing Block | `[listings]` | Full search page: filters, sort, map, grid, pagination |
| (none) | `[rch_latest_listings]` | Latest listings as slider, list or grid |
| (none) | `[rch_search_listing_form]` | Search bar that sends visitors to your listings page |
| Leads Form Block | `[rch_leads_form]` | Contact form that creates a lead in Rechat |
| Testimonials Block | `[rch_testimonials]` | Testimonials from Rechat |
| Off Market Block | `[rch_off_market]` | Your manual Off Market listings |
| Agents Block | (none) | Agent cards with paging |
| Offices Block / Regions Block | (none) | Office or region lists with paging |

### Build a listings search page

1. Create a page, e.g. **Listings** at `/listings/`.
2. Add the **Listing Block**, or a Shortcode block with `[listings]`.
3. In the block sidebar set filters: **Listing Settings** (statuses, property type, price, beds, "Only our own listings", Open Houses Only), **Display** (hide filters/sort/map, color mode), **Map Settings** (style, center, zoom; needs a Google key).
4. Publish. Each listing opens at `/listing-detail/{city}/{street}/{id}/` automatically.

### Add a search bar to the home page

Use `[rch_search_listing_form target_page="/listings/"]`. Visitors type an area or ZIP and land on your listings page with the search applied. The target page must contain the Listing Block or `[listings]`.

### Common shortcode examples

```text
[listings listing_statuses="Active" property_types="Residential" own_listing="true" sort_by="-price"]
[rch_latest_listings display_type="swiper" listing_statuses="Active" limit="8" navigation="true"]
[rch_latest_listings display_type="grid" property_types="Sale" limit="6"]
[rch_search_listing_form target_page="/properties/" show_background="true" background_image="https://example.com/hero.jpg"]
[rch_leads_form form_title="Contact Us" tags="Website,Buyer" show_note="false"]
[rch_testimonials title="What our clients say" limit="6" load_more="false"]
[rch_off_market status="sold" display_type="swiper" columns="3" autoplay="true"]
```

The full attribute list for every shortcode is in the plugin's README.md.

### Useful listing options

| Option | Values | Effect |
| --- | --- | --- |
| `listing_statuses` | Active, Pending, Closed, Archived (comma list) | Which statuses show |
| `property_types` | Residential, Sale, Lease, Lots & Acreage, Commercial, All Listings | Which property types show |
| `own_listing` | true / false | Only your brand's listings |
| `filter_open_houses` | true / false | Only listings with open houses |
| `filter_boundary_ids` | IDs from the boundary finder | Limit to a neighborhood, city or ZIP |
| `hide_map`, `hide_filters`, `disable_sort` | true / false | Simplify the page |
| `sort_by` | `-list_date` (newest) or `-price` | Order |
| `display_type` (latest listings) | swiper, normal, grid | Layout |

### Agents, offices, regions, neighborhoods, off market

Each content type has its own archive and single page: `/agents/`, `/offices/`, `/regions/`, `/neighborhoods/`, `/off-market/`.

- **Agents:** synced; edit only Display order (0, 1, 2… pins to top), Agent title, Brand ID, Visibility and the Testimonials list (Name, Description, Stars, Link; up to 50).
- **Neighborhoods:** add by hand. Drop a pin in **Select Location on Map** (needs Google key) so Local Logic sections work. Assign offices in **Assigned Offices**; on Multisite this decides which agent sites get the neighborhood.
- **Off Market:** WP Admin → Off Market → Add New. Fill status (Active / Coming Soon / Pending / Sold Privately), price, address, beds/baths/sqft, gallery (first image = cover) and pick an Agent. Inquiries go to that agent.

### Customize page designs

Copy a template from the plugin's `templates/` folder into your theme at `wp-content/themes/<theme>/rechat/` and edit it there, e.g. `agents-single-custom.php`, `listing-single-custom.php`, `rch-agents-block-template.php`.

### Other automatic features

- **MLS short links:** `/mls/21191513` or `/NTREIS/21191513` redirects to the listing page.
- **SEO:** meta description, canonical, Open Graph and Twitter tags, unless an SEO plugin (Yoast, Rank Math, etc.) is active.
- **Schema:** structured data for agents, listings, breadcrumbs and the home page.

## Multisite agent websites

On a WordPress Multisite network the plugin builds one sub-site per agent (and per office) from the hub's Agents and Offices, gives it the theme you pick, and fills that theme's settings from each agent's profile. Agent sites show only that agent's listings; office sites show only that office's listings.

### Before you start

- WordPress Multisite is set up. Subdomain installs (`jdoe.yoursite.com`) need wildcard DNS and SSL.
- **Broadcast (ThreeWP Broadcast)** is installed and **network-activated**. Without it every site creation fails.
- You are a **network super admin**. Other admins can see the Multisite tab but their saves and buttons do nothing.
- The hub is connected to Rechat and synced. Each agent has an **email** (needed for their login) and Rechat agent IDs (needed for their listings).
- All screens are on the **hub**: WP Admin → Rechat → Multisite / Agent wizard / Office wizard. Sub-sites have no Rechat menu; they use the hub's connection, brand and API keys.

### Step 1 — Get the agent theme ready

1. Upload the theme folder to `wp-content/themes/` on the server.
2. The theme does not need to be network-enabled; the plugin enables it per site when it applies it.
3. For the Agent wizard to find the theme's settings, the theme must save them in one WordPress option array, set in `includes/themeoption.php` with plain `'key' => value` entries (keys starting `rch-` or containing a hyphen). Settings labels come from `includes/views/option-panel.php`. Rechat agent themes already follow this.
4. Optional: a `rechat-agent-wizard.json` file in the theme root can name the option (`storage.primary`), hide fields (`exclude_fields`) or relabel them (`fields`).

### Step 2 — Network settings

Go to **Rechat → Multisite → Network settings**:

1. Tick **Create a WordPress sub-site for each agent** (and for each office if you want office sites).
2. Pick **Default theme for agent sub-sites** (and for office sub-sites). This also decides which theme's fields the wizard shows.
3. Pick **Agent sub-site URL slug**: `jdoe` (default), `john-doe`, `johndoe` or `john`.
4. Leave **Agent login emails** off for now.
5. Click **Save Multisite Settings**.

### Step 3 — Create the sites

Use any one of these:

- **Provision all agent & office sites** (Create & maintain sites). Creates every missing site and refreshes names on existing ones. The page reloads when done.
- **Sync now** on the Sync Data tab. Sync creates sites for new agents automatically.
- One agent: **Enable Site** in the Agent site status table, or tick **Enable site for this agent** on the agent's edit screen and Update.

A new site gets the theme, the hub's permalinks, timezone and API keys, and has "Hello world!" and "Sample Page" removed. Posts already broadcast on the hub are copied to it, plus neighborhoods that match the agent's offices.

### Step 4 — Assign themes

| You want | Do this |
| --- | --- |
| Same theme on every agent site | Pick it in **Default theme for agent sub-sites**, then **Apply to all agent sub-sites** |
| A different theme for one agent | Choose it in that agent's **Theme for this sub-site** dropdown (status table or agent edit screen). It switches at once |
| Re-apply defaults + per-agent choices everywhere | **Sync themes (defaults + overrides)** |
| Sites showing a blank or broken theme | **Fix theme on existing sites** |

### Step 5 — Fill the theme with agent data (Agent wizard)

Go to **Rechat → Agent wizard**. The wizard writes theme settings on sites that already exist; it never creates sites.

1. **Scope & agent:** choose **Single agent** and pick an agent (loads automatically), or **All agent sub-sites**. Optionally **Import testimonials to sub-site(s)**. Click **Continue**.
2. **Theme options:** for each theme setting choose a **Source**: *Do not change*, *Set manually* (type a value, pick from a list, or **Media library**), or *Agent profile (metabox)* (pick a profile field such as Phone number or Profile image URL). In text fields, **Placeholders** insert `{$post_title}` etc., filled per agent.
3. **Broadcast content** (only with Broadcast): search hub posts/pages, tick them, **Broadcast selected** (15 per click). Pages already on a site are skipped, not updated.
4. **Menus & widgets:** build a menu (**New menu name**, add pages or custom links, tick theme locations, **Create menu on all targets**), or tick existing template menus and a widgets mode, then **Apply to target sites**.
5. **Preview & deploy:** check **What will change**, then **Deploy now**.

After a deploy:

- That setup becomes the network default and is applied automatically to every **new** agent site (within its first hour).
- When agent data changes on the hub (sync or edit), every wizard-deployed agent site is refreshed with the new data and its cache purged.

**Before deploying to All agent sub-sites:** loading one agent turns rows that had a value into *Set manually* with that agent's values. Set those rows back to *Do not change* or to a profile field, or every agent gets the first agent's phone, photo, etc.

### Step 6 — Give the agent access

1. Turn on **Agent login emails** and **Save Multisite Settings**.
2. Click **Update editor** in the agent's row (or **Update editor user & email** on the agent screen). The agent gets the **Agent** role on their site and an email with login details. This resets their password.
3. Turn **Agent login emails** off again.

Agents can then edit their site content and the theme's Theme Setting page.

### Office sites

Same flow with **Rechat → Office wizard**: pick an office, map fields from the office profile (name, content, Office brand ID, address, phone), deploy. Differences: no testimonials, no editor user, no auto-setup for new office sites, and no refresh after sync.

## Multisite buttons reference

Every control on the hub's Multisite tab, Agent wizard, Office wizard and the Agent Site / Office Site boxes. All Multisite-tab tools are safe to run more than once, except the Danger zone.

### Multisite tab — Network settings

| Setting | What it does |
| --- | --- |
| Create a WordPress sub-site for each agent | On by default. Allows sync, Provision and Enable to create agent sites |
| Create a WordPress sub-site for each office | On by default. Same for office sites |
| Agent login emails | Off by default. When on, new or updated agent accounts get a login email and **Update editor** resets their password |
| Default theme for agent sub-sites | Theme for new agent sites and for "Apply to all"; also the theme the Agent wizard reads |
| Default theme for office sub-sites | Same for office sites and the Office wizard |
| Site URL format | Display only. The real format comes from the network's wp-config (subdomain or subdirectory) |
| Agent sub-site URL slug | `jdoe` (default), `john-doe`, `johndoe`, `john`. Duplicates get 2, 3… Applies to new sites; use Migrate to rename existing ones |
| Office sub-site URL slug | With `o-` prefix (default) or no prefix |
| Owner User ID for new sites | User set as owner of each new site. Blank = network admin |
| When agent or office post is deleted | Ticked = permanently delete the linked site. Unticked = archive it. Applies also when sync removes an agent |
| **Save Multisite Settings** | Saves all of the above. Super admin only |

### Multisite tab — Tools

| Button | What it does |
| --- | --- |
| **Provision all agent & office sites** | Creates a site for every published agent/office that is enabled and has none; updates name and tagline on existing sites |
| **Migrate agent sub-site URLs** | Renames every agent site to the current slug format. Subdomain installs only. Old URLs do not redirect |
| **Sync themes (defaults + overrides)** | Puts each site on its own chosen theme, or the default. Skips sites already correct |
| **Fix theme on existing sites** | For sites with no theme: applies the theme and re-runs new-site setup |
| **Apply to all agent sub-sites** | Forces the selected default theme on every agent site, ignoring per-agent choices |
| **Apply to all office sub-sites** | Same for office sites |
| **Reassign role for all agents** | Gives each agent's WordPress user the Agent role on their site; copies missing API keys, brand color and map settings from the hub; turns on Local Content. Sends no email |
| **Preview duplicate cleanup** | Lists duplicate sites that cleanup would delete. Back up first: it can unlink duplicate hub agent posts even in preview |
| **Run cleanup (permanent)** | Permanently deletes duplicate sites (e.g. `jsmith2` when `jsmith` exists) and trashes duplicate hub agents. Cannot be undone. Two different agents with the same base slug look like duplicates, so check the preview |

### Multisite tab — status tables (one row per agent / office)

| Control | What it does |
| --- | --- |
| Theme for this sub-site | Per-site theme. Saves and switches at once ("Saved.") |
| **Disable Site** | Archives the site. Visitors see "This site has been archived or suspended." Admin still works |
| **Enable Site** | Unarchives the site, or creates it if missing |
| **Update editor** (agents only) | Creates or links the agent's user with the Agent role. With login emails on, emails login details and resets the password |

### Agent edit screen — Agent Site box

| Control | What it does |
| --- | --- |
| Enable site for this agent | Untick + Update = archive the site; tick + Update = restore or create it |
| Theme for this sub-site | Per-agent theme, applied when you Update |
| **View Site** / **Site Admin** | Opens the agent's site or its wp-admin |
| **Update editor user & email** | Same as Update editor |
| Create & set portal (Agent Details box) | Registers the agent site's domain in the agent's Rechat portal |

The **Office Site** box on offices has the same controls without the editor button.

### Agent wizard (Office wizard is the same unless noted)

| Step | Button | What it does |
| --- | --- | --- |
| 1 Scope | Single agent / All agent sub-sites | Deploy to one site or to every linked agent site |
| 1 Scope | **Load profile** (Office: **Load office**) | Loads the agent's data and the site's current theme values |
| 1 Scope | **Import testimonials to sub-site(s)** | Copies the agent's hub testimonials into testimonial posts on the site; removes ones deleted on the hub. Agents only |
| 1 Scope | **Delete all testimonials (main site + sub-site)** | Deletes every testimonial on the site, including hand-made ones, and empties the agent's hub list. Cannot be undone |
| 1 Scope | **Restore my draft** | Loads your own last saved draft |
| 2 Theme options | Source: Do not change / Set manually / Agent profile | Per setting: leave alone, fixed value, or value from each agent's profile |
| 2 Theme options | **Media library**, **Placeholders** | Pick an image/video; insert `{$field}` tokens filled per agent |
| 3 Broadcast | **Load list**, Previous / Next, Select all on page, Clear page | Find hub posts and pages |
| 3 Broadcast | **Broadcast selected** | Copies up to 15 selected items to the target sites. Existing copies are skipped. Keep the tab open on big runs |
| 4 Menus | **Load menus & sidebars** | Lists the template site's menus, widgets and locations |
| 4 Menus | **Load broadcasted list**, **Add**, **Add selected to menu**, **Search**, **Add custom link**, Move up / Move down / Remove | Build a new menu |
| 4 Menus | **Create menu on all targets** | Creates the built menu on every target, replacing any menu with the same name |
| 4 Menus | **Apply to target sites** | Copies ticked template menus and, if chosen, all widgets. Replaces same-name menus and all widget settings |
| 5 Deploy | **Refresh preview**, Show technical JSON | Re-draws what will change |
| 5 Deploy | **Save draft** | Saves your draft. In Single agent mode it also writes to that agent's live site |
| 5 Deploy | **Deploy now** | Writes the settings to the target sites' theme options (other settings kept), purges cache, and makes this the default for new agent sites |

### Other multisite boxes

- **Assigned Offices** (hub Neighborhoods): agent sites whose agent belongs to one of these offices get the neighborhood.
- **Rating & link** (sub-site testimonial): Stars and Link. Overwritten by the next testimonial sync.

## Settings reference

Site-wide options live on the **General**, **Local Logic & Maps**, **Import / Export** and **Portal Log** tabs. On Multisite, sub-sites inherit these from the hub unless they have their own.

### General tab

| Card | Setting | Effect |
| --- | --- | --- |
| Listings & location | Listing Display Mode | How agent pages show listings: Combined (default), Separate Sections (active and sold apart), Active Only, Sold Only |
| Listings & location | Country, State / Province | Default search area for every listing block, shortcode and search bar. "Any" = no limit |
| Listings & location | CAPTCHA | None (built-in anti-spam only), Cloudflare Turnstile, or Google reCAPTCHA v3. Needs both Site key and Secret key |
| Listings & location | Find boundary ID | Type a neighborhood, city or ZIP, click **Copy ID**, paste into `filter_boundary_ids` |
| Lead capture | Listing Page Lead Capture: Lead Source, Tags | Lead channel and tags for listing-page inquiries and `[rch_leads_form]` |
| Lead capture | Agent Page Lead Capture: Lead Source, Tags | Same for the agent profile contact form. Leads are assigned to that agent |
| Rechat brand color | Color mode, Theme, Corner radius, Map style, Brand color | Look of all Rechat listing widgets: Light/Dark, Rechat/Compact, Default/Sharp corners, map preset, accent color. **Save brand color** (administrators only) |

A lead form needs a Lead Source. Without one, submissions fail with "Lead channel is required."

Built-in spam protection is always on: hidden honeypot field, minimum fill time, same-site check, disposable-email block, and 30 submissions per visitor per hour.

### Local Logic & Maps tab

| Setting | Effect |
| --- | --- |
| Local Logic API Key (Listing Page) | Turns on Local Logic for listing and Off Market pages |
| Google Map API Key | Map picker in Listing Block and Neighborhoods, search bar map, Local Logic maps |
| Features: Local Content | Shows the Local Logic neighborhood widget on listing and Off Market pages |
| Local Logic API Key (Neighborhood Page) | Turns on Local Logic sections on Neighborhood pages |
| Neighborhood Features | Pick sections: Hero, Map, Highlights, Characteristics, Schools, Demographics, Property Value Drivers, Market Trends, Match. Each neighborhood needs a map pin |

### Import / Export tab

**Import agent bios and testimonials from CSV**

1. Click **Sample CSV (multi-row)** or **Simple CSV (one row)** to get a template.
2. Fill columns: `agent_match` (required: post ID, Rechat ID or exact agent name), `bio`, `title`, `testimonial_name`, `testimonial_description`, `testimonial_rank` (stars), `testimonial_link`. Leave `agent_match` blank to add more testimonials to the agent above.
3. Choose **Default match by** (Auto-detect, Post ID, Rechat ID, Agent post title).
4. Tick what to import (bio, agent title, testimonials) and **Replace** or **Merge** existing testimonials.
5. Upload the file, click **Preview import**, check the table, then **Run import**.

A blank bio never erases an existing one. On Multisite, imported testimonials also sync to agent sites.

**Export agents**

- **Export Email / Rechat ID / Brand ID**: quick 3-column file.
- Full export: tick fields, drag to order (Select all, Select none, Reset order), **Export CSV**. File name `agents-export-YYYY-MM-DD.csv`.
- When the theme has a Testimonial post type: **Export testimonials CSV** and **Import testimonials CSV** (rows with a matching post_id update, others are created).

### Portal Log tab (administrators)

Rechat widgets only work on domains registered in your Rechat brand portal. Single sites register themselves on each sync; this tab handles agent sites.

| Button | What it does |
| --- | --- |
| **Run diagnostic (dry — no API write)** | Checks which hostnames are missing, changes nothing |
| **Run now (live — registers hostnames)** | Registers missing hostnames, 20 agents per batch |
| **Clear log** | Empties the request log (keeps last 300 otherwise) |
| Copy cURL | Copies a request for debugging. It contains the live access token: do not share it |

### User Guide tab

Shows this guide inside WordPress. **Download guide (HTML)** saves a copy you can open in any browser, **Download Markdown** saves the plain-text source, and **Print / Save as PDF** opens the print dialog.

### Roles

The **Agent** role is an Editor that can also open Rechat settings, list users and edit theme options. Agents get it on their own site through **Update editor**.

## Troubleshooting

| Problem | Fix |
| --- | --- |
| Listing or agent pages show 404 | Settings → Permalinks → Save |
| No agents / empty pages | Check Connect tab says Connected, then **Sync now** |
| Sync not running on schedule | Connect → Data sync status. If WP-Cron is disabled, add a server cron; if not scheduled, load any front-end page or reactivate the plugin |
| "Rechat OAuth token refresh failed" | Connect tab → **Refresh access token now**; if it still fails, Disconnect and Connect again |
| Lead form says "Lead channel is required." | General → Lead capture → pick a Lead Source, or set `lead_channel` on the form |
| Map picker says Google Maps key not found | Local Logic & Maps → Google Map API Key |
| Duplicate agent after sync | A synced agent was set to Draft. Publish it again (or delete the copy) and use Visibility → Hide instead |
| Rechat widgets blank on an agent site | Portal Log → **Run now** to register the site's hostname |
| "Sub-site creation is blocked" banner | Network Admin → Plugins → network-activate Broadcast |
| Multisite buttons say "Insufficient permissions" or Save does nothing | Log in as a network super admin |
| Agent site listings are empty or disabled | The hub agent has no Rechat agent IDs. Check the agent in Rechat and sync |
| Office site shows all listings | Office was added by hand and has no Office ID. Use the synced office |
| Wizard shows "No theme options discovered" | Default theme for agent sub-sites is wrong, or the theme doesn't store settings in one option array. Save the theme's settings once on the hub, or add `rechat-agent-wizard.json` |
| Deploy error "This agent has no linked sub-site" | Create the site first: Provision or Enable Site |
| Agent site still shows old content after deploy | Purge the page cache for that site (office sites always need this) |
| Agent never got a login | Turn on Agent login emails, then **Update editor**; or have them use "Lost password" |
| Testimonials won't sync to agent site | The agent site's theme must have a Testimonial post type |
| Agent site says "archived or suspended" | Click **Enable Site** in the status table |
