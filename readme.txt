=== SW - WPForms GraphQL ===
Contributors: seniors
Tags: wpforms, wpgraphql, headless, forms, rest-api
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Exposes WPForms form structure via WPGraphQL and provides a REST endpoint for headless form submissions.

== Description ==

This plugin enables headless WordPress form handling by exposing WPForms form structure through WPGraphQL and providing a REST API endpoint for form submissions.

**GraphQL Query — Fetch form structure:**

`
{
  wpForm(id: 123) {
    id
    title
    submitText
    fields {
      id
      type
      label
      placeholder
      required
      cssClass
      choices {
        label
        value
      }
      format
    }
  }
}
`

**REST API — Submit form entries:**

`
POST /wp-json/sw/v1/forms/{form_id}/submit
Content-Type: application/json

{
  "fields": {
    "1": "John",
    "2": "Doe",
    "3": "john@example.com"
  }
}
`

**Features:**

* Exposes form fields with type, label, placeholder, required, CSS classes, and choices
* Supports all WPForms field types (text, email, phone, textarea, select, radio, checkbox, GDPR)
* CSS class passthrough (`wpforms-one-half`, etc.) for frontend layout control
* Submit button text exposed via `submitText` field
* Server-side validation for required fields and email format
* Triggers WPForms notifications (emails) on successful submission
* Entries saved to WPForms for admin review

== Requirements ==

* [WPForms](https://wpforms.com/) (Lite or Pro)
* [WPGraphQL](https://www.wpgraphql.com/)

== Installation ==

1. Upload the `sw-wpforms-graphql` folder to `/wp-content/plugins/`
2. Activate the plugin through the "Plugins" menu in WordPress
3. Create forms in WPForms and use their IDs in GraphQL queries

== Changelog ==

= 1.0.0 =
* Initial release
* GraphQL query for form structure
* REST API endpoint for form submissions
* Server-side field validation
* WPForms notification triggering
