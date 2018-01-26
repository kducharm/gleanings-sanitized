@api
Feature: Content model
  In order to enter structured content into my site
  As a content editor
  I want to have content entity types that reflect my content model.

  Scenario: Bundles and fields
    Then exactly the following entity type bundles should exist
      | type              | label                 | machine name  | moderated | description                                                                   |
      | Contact form      | Contact Form          | sitewide      |           |                                                                               |
      | Contact form      | Personal contact form | personal      |           |                                                                               |
      | Content type      | Basic page            | page          |           | Use <em>basic pages</em> for your static content, such as an 'About us' page. |
      | Content type      | Landing page          | landing_page  |           | A special page with its own one-off layout and content.                       |
      | Crop type         | Freeform              | freeform      |           |                                                                               |
      | Custom block type | Basic block           | basic         |           | A basic block contains a title and a body.                                    |
      | Media type        | Document              | document      |           | A locally hosted document, such a PDF.                                        |
      | Media type        | Image                 | image         |           | Locally hosted images.                                                        |
      | Media type        | Instagram             | instagram     |           | Instagram posts.                                                              |
      | Media type        | Tweet                 | tweet         |           | Represents a tweet.                                                           |
      | Media type        | Video                 | video         |           | A video hosted by YouTube, Vimeo, or some other provider.                     |
      | Shortcut set      | Default               | default       |           |                                                                               |
      | Token type        | Access Token          | access_token  |           | The access token type.                                                        |
      | Token type        | Auth code             | auth_code     |           | The auth code type.                                                           |
      | Token type        | Refresh token         | refresh_token |           | The refresh token type.                                                       |
    And exactly the fields in "content_fields.csv" should exist
